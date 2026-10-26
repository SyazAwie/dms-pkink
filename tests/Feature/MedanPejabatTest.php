<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\User;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use App\Support\JenisMedan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * OCR 3 Situasi, Fasa C3: seksyen "diisi oleh pegawai/pejabat" (tetapan.pejabat).
 * Pemohon tidak wajib mengisinya; Imbas & Hantar tidak menghantarnya kepada AI,
 * tidak memaparkan dan tidak menyimpannya; Imbas & Arkib kekal seperti biasa.
 */
class MedanPejabatTest extends TestCase
{
    use SkemaUjian;

    private User $admin;
    private User $staf;
    private JenisDokumen $jenis;
    private ?Collection $medanKeAi = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('local');
        $bahagian = $this->buatBahagian();
        $this->admin = $this->buatPengguna(['ADMIN'], ['bahagian_id' => $bahagian->bahagian_id]);
        $this->staf = $this->buatPengguna(['STAFF'], ['bahagian_id' => $bahagian->bahagian_id]);

        $this->actingAs($this->admin)->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => 'SEWA', 'nama_dokumen' => 'Borang Sewaan',
            'medan' => [
                ['nama_field' => 'Nama Pemohon', 'kod_field' => 'nama_pemohon', 'jenis_data' => 'teks', 'is_required' => '1', 'pejabat' => '1'],
                ['nama_field' => 'Urusan BD', 'kod_field' => 'urusan_bd', 'jenis_data' => 'seksyen', 'pejabat' => '1'],
                ['nama_field' => 'Keputusan', 'kod_field' => 'keputusan', 'jenis_data' => 'radio', 'pilihan' => 'Lulus, Tolak', 'is_required' => '1'],
                ['nama_field' => 'Caj', 'kod_field' => 'caj', 'jenis_data' => 'wang'],
                ['nama_field' => 'Lampiran', 'kod_field' => 'lampiran', 'jenis_data' => 'seksyen'],
                ['nama_field' => 'Catatan', 'kod_field' => 'catatan', 'jenis_data' => 'teks', 'is_required' => '1'],
            ],
        ])->assertSessionHasNoErrors();

        $this->jenis = JenisDokumen::with('fields')->sole();

        $this->mock(OcrService::class, fn (MockInterface $m) => $m
            ->shouldReceive('proses')->andReturn(['teks' => 'Nama: Ali Keputusan: Lulus Caj: 50', 'skor' => 90.0]));
        $this->mock(OllamaExtractionService::class, fn (MockInterface $m) => $m
            ->shouldReceive('ekstrak')->andReturnUsing(function (string $teks, Collection $medan) {
                $this->medanKeAi = $medan;

                return [$this->id('nama_pemohon') => 'Ali', $this->id('keputusan') => 'Lulus', $this->id('caj') => '50'];
            }));
    }

    private function medan(string $kod): DokumenField
    {
        return $this->jenis->fields->firstWhere('kod_field', $kod);
    }

    private function id(string $kod): int
    {
        return $this->medan($kod)->dokumen_field_id;
    }

    private function nilai(string $kod): ?string
    {
        return DokumenData::where('dokumen_field_id', $this->id($kod))->value('nilai_data');
    }

    private function analisis(string $mod, User $user): string
    {
        $res = $this->actingAs($user)->post(route("imbas-{$mod}.analisis"), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
            'fail_scan' => UploadedFile::fake()->create('borang.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        return basename(parse_url($res->headers->get('Location'), PHP_URL_PATH));
    }

    public function test_pembina_simpan_pejabat_pada_seksyen_sahaja(): void
    {
        $this->assertTrue($this->medan('urusan_bd')->tetapan['pejabat']);
        $this->assertTrue(JenisMedan::seksyenPejabat($this->medan('urusan_bd')));
        $this->assertNull($this->medan('nama_pemohon')->tetapan);
        $this->assertFalse(JenisMedan::seksyenPejabat($this->medan('lampiran')));

        // Dari seksyen pejabat hingga seksyen seterusnya
        $this->assertSame(
            [$this->id('urusan_bd'), $this->id('keputusan'), $this->id('caj')],
            JenisMedan::idMedanPejabat($this->jenis->fields)
        );
    }

    public function test_muat_naik_seksyen_pejabat_berlipat_dan_tidak_wajib(): void
    {
        $this->actingAs($this->staf)->get(route('jenis-dokumen.medan', $this->jenis->jenis_dokumen_id))
            ->assertOk()
            ->assertSeeInOrder(['<details class="medan-pejabat">', 'Urusan BD', 'Untuk kegunaan pejabat (pilihan)', 'Keputusan', 'Caj', '</details>', 'Lampiran', 'Catatan'], false)
            ->assertSee('<span>Tiada</span>', false);            // radio Keputusan tidak wajib

        $this->actingAs($this->staf)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'tarikh_dokumen' => '2026-10-26',
            'medan_data' => [$this->id('nama_pemohon') => 'Ali'],
        ])->assertSessionHasErrors(["medan_data.{$this->id('catatan')}"])     // medan bukan pejabat kekal wajib
          ->assertSessionDoesntHaveErrors(["medan_data.{$this->id('keputusan')}"]);

        $this->actingAs($this->staf)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'tarikh_dokumen' => '2026-10-26',
            'medan_data' => [$this->id('nama_pemohon') => 'Ali', $this->id('catatan') => 'Kursus', $this->id('caj') => 'abc'],
        ])->assertSessionHasErrors(["medan_data.{$this->id('caj')}"]);   // format tetap disahkan

        $this->actingAs($this->staf)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'tarikh_dokumen' => '2026-10-26',
            'medan_data' => [$this->id('nama_pemohon') => 'Ali', $this->id('catatan') => 'Kursus'],
        ])->assertSessionHasNoErrors();
        $this->assertNull($this->nilai('keputusan'));
    }

    public function test_kemaskini_pejabat_boleh_diisi_kemudian(): void
    {
        $this->actingAs($this->staf)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'tarikh_dokumen' => '2026-10-26',
            'medan_data' => [$this->id('nama_pemohon') => 'Ali', $this->id('catatan') => 'Kursus'],
        ])->assertSessionHasNoErrors();
        $dokumen = Dokumen::sole();

        $this->actingAs($this->admin)->get(route('dokumen.edit', $dokumen->dokumen_id))
            ->assertOk()
            ->assertDontSee('<details class="medan-pejabat">', false);

        $this->actingAs($this->admin)->put(route('dokumen.update', $dokumen->dokumen_id), [
            'tarikh_dokumen' => '2026-10-26',
            'medan_data' => [$this->id('nama_pemohon') => 'Ali', $this->id('catatan') => 'Kursus', $this->id('keputusan') => 'Lulus'],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Lulus', $this->nilai('keputusan'));
    }

    public function test_imbas_hantar_abaikan_seksyen_pejabat(): void
    {
        $token = $this->analisis('hantar', $this->staf);

        // AI tidak menerima medan pejabat
        $this->assertSame(
            [$this->id('nama_pemohon'), $this->id('lampiran'), $this->id('catatan')],
            $this->medanKeAi->pluck('dokumen_field_id')->all()
        );

        $this->actingAs($this->staf)->get(route('imbas-hantar.semak', $token))
            ->assertOk()
            ->assertDontSee('Urusan BD')
            ->assertDontSee("medan_data[{$this->id('keputusan')}]", false)
            ->assertSee("medan_data[{$this->id('catatan')}]", false);

        // Nilai pejabat yang dihantar juga diabaikan oleh pelayan
        $this->actingAs($this->staf)->post(route('imbas-hantar.simpan', $token), [
            'tarikh_dokumen' => '2026-10-26', 'pengesahan' => '1',
            'medan_data' => [
                $this->id('nama_pemohon') => 'Ali', $this->id('catatan') => 'Kursus',
                $this->id('keputusan') => 'Lulus', $this->id('caj') => '50',
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Ali', $this->nilai('nama_pemohon'));
        $this->assertNull($this->nilai('keputusan'));
        $this->assertNull($this->nilai('caj'));
    }

    public function test_imbas_arkib_isi_seksyen_pejabat_seperti_biasa(): void
    {
        $kerani = $this->buatPengguna(['KERANI']);
        $token = $this->analisis('arkib', $kerani);

        $this->assertTrue($this->medanKeAi->contains('dokumen_field_id', $this->id('keputusan')));
        $this->actingAs($kerani)->get(route('imbas-arkib.semak', $token))
            ->assertSee('Urusan BD')
            ->assertSee("medan_data[{$this->id('keputusan')}]", false);

        // Keputusan kekal wajib dalam arkib
        $this->actingAs($kerani)->post(route('imbas-arkib.simpan', $token), [
            'tarikh_dokumen' => '2026-10-26',
            'medan_data' => [$this->id('nama_pemohon') => 'Ali', $this->id('catatan') => 'Kursus'],
        ])->assertSessionHasErrors(["medan_data.{$this->id('keputusan')}"]);

        $this->actingAs($kerani)->post(route('imbas-arkib.simpan', $token), [
            'tarikh_dokumen' => '2026-10-26',
            'medan_data' => [$this->id('nama_pemohon') => 'Ali', $this->id('catatan') => 'Kursus', $this->id('keputusan') => 'Lulus', $this->id('caj') => '50'],
        ])->assertSessionHasNoErrors();
        $this->assertSame('Lulus', $this->nilai('keputusan'));
        $this->assertSame('50.00', $this->nilai('caj'));
    }
}
