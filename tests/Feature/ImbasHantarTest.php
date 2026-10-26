<?php

namespace Tests\Feature;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenField;
use App\Models\DokumenOcr;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\User;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * OCR 3 Situasi, Fasa B: "Imbas & Hantar" (mod hantar aliran token Imbas & Arkib).
 * Staf imbas borang fizikal sendiri → OCR/AI → sahkan → borang "Dalam Proses".
 */
class ImbasHantarTest extends TestCase
{
    use SkemaUjian;

    private User $staf;
    private User $kerani;
    private JenisDokumen $jenis;
    private DokumenField $medanNama;
    private DokumenField $medanTujuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('local');

        $bahagian = $this->buatBahagian();
        $this->staf = $this->buatPengguna(['STAFF'], ['nama_staff' => 'Siti Aminah', 'bahagian_id' => $bahagian->bahagian_id]);
        $this->kerani = $this->buatPengguna(['KERANI'], ['bahagian_id' => $bahagian->bahagian_id]);

        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'SEWA', 'nama_dokumen' => 'Borang Sewaan']);
        $this->medanNama = DokumenField::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'Nama Pemohon',
            'kod_field' => 'nama_pemohon', 'jenis_data' => 'teks', 'is_required' => true, 'susunan' => 1,
            'tetapan' => ['auto_isi' => 'nama'],
        ]);
        $this->medanTujuan = DokumenField::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'Tujuan',
            'kod_field' => 'tujuan', 'jenis_data' => 'teks', 'is_required' => true, 'susunan' => 2,
        ]);

        $this->mock(OcrService::class, fn (MockInterface $m) => $m
            ->shouldReceive('proses')->andReturn(['teks' => 'Nama: S1ti Am1nah Tujuan: Kursus', 'skor' => 81.0]));
        // AI salah baca nama (OCR); medan auto-isi profil mesti mengatasinya
        $this->mock(OllamaExtractionService::class, fn (MockInterface $m) => $m
            ->shouldReceive('ekstrak')->andReturn([
                $this->medanNama->dokumen_field_id => 'S1ti Am1nah',
                $this->medanTujuan->dokumen_field_id => 'Kursus',
            ]));
    }

    private function analisis(User $user, string $mod = 'hantar', string $fail = 'Borang Diisi.pdf'): string
    {
        $res = $this->actingAs($user)->post(route("imbas-{$mod}.analisis"), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
            'fail_scan' => str_ends_with($fail, '.pdf')
                ? UploadedFile::fake()->create($fail, 50, 'application/pdf')
                : UploadedFile::fake()->image($fail),
        ]);
        $res->assertRedirect();

        return basename(parse_url($res->headers->get('Location'), PHP_URL_PATH));
    }

    private function dataSah(array $tambahan = []): array
    {
        return array_merge([
            'tarikh_dokumen' => '2026-10-26',
            'medan_data' => [
                $this->medanNama->dokumen_field_id => 'Nama Palsu',
                $this->medanTujuan->dokumen_field_id => 'Kursus',
            ],
            'pengesahan' => '1',
        ], $tambahan);
    }

    public function test_staf_boleh_guna_imbas_hantar_tetapi_bukan_imbas_arkib(): void
    {
        $this->actingAs($this->staf)->get(route('imbas-hantar.create'))
            ->assertOk()
            ->assertSee('Imbas &amp; Hantar', false)
            ->assertSee(route('imbas-hantar.analisis'));
        $this->actingAs($this->staf)->get(route('imbas-arkib.create'))->assertForbidden();
    }

    public function test_skrin_semak_pratonton_pengesahan_dan_auto_isi_atasi_ai(): void
    {
        $token = $this->analisis($this->staf);

        $meta = json_decode(Storage::disk('local')->get("ocr_sementara/{$token}/meta.json"), true);
        $this->assertSame('hantar', $meta['mod']);

        $this->actingAs($this->staf)->get(route('imbas-hantar.semak', $token))
            ->assertOk()
            ->assertSee('<iframe src="' . route('imbas-hantar.pratonton', $token) . '"', false)
            ->assertSee('name="pengesahan"', false)
            ->assertSee('Sahkan &amp; Hantar', false)
            ->assertSee('berstatus <strong>"Dalam Proses"</strong>', false)
            ->assertSee('value="Siti Aminah" required readonly', false)
            ->assertDontSee('value="S1ti Am1nah"', false)
            ->assertSee('value="Kursus"', false)
            ->assertSee(route('imbas-hantar.simpan', $token));
    }

    public function test_pratonton_imej_guna_tag_img(): void
    {
        $token = $this->analisis($this->staf, 'hantar', 'borang.png');

        $this->actingAs($this->staf)->get(route('imbas-hantar.semak', $token))
            ->assertSee('<img src="' . route('imbas-hantar.pratonton', $token) . '"', false);
    }

    public function test_pratonton_hanya_untuk_pemilik_token(): void
    {
        $token = $this->analisis($this->staf);

        $this->actingAs($this->staf)->get(route('imbas-hantar.pratonton', $token))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->kerani)->get(route('imbas-hantar.pratonton', $token))->assertNotFound();
    }

    public function test_simpan_tanpa_pengesahan_ditolak_dan_token_kekal(): void
    {
        $token = $this->analisis($this->staf);

        $this->actingAs($this->staf)
            ->from(route('imbas-hantar.semak', $token))
            ->post(route('imbas-hantar.simpan', $token), $this->dataSah(['pengesahan' => null]))
            ->assertRedirect(route('imbas-hantar.semak', $token))
            ->assertSessionHasErrors(['pengesahan' => 'Sila tandakan pengesahan bahawa maklumat ini sepadan dengan borang fizikal yang anda isi.']);

        $this->assertSame(0, Dokumen::count());
        Storage::disk('local')->assertExists("ocr_sementara/{$token}/meta.json");
    }

    public function test_hantar_cipta_borang_dalam_proses_dengan_fail_dan_ocr(): void
    {
        $token = $this->analisis($this->staf);

        $res = $this->actingAs($this->staf)->post(route('imbas-hantar.simpan', $token), $this->dataSah());
        $res->assertSessionHasNoErrors();

        $dokumen = Dokumen::sole();
        $res->assertRedirect(route('dokumen.show', $dokumen->dokumen_id));
        $this->assertNull($dokumen->status);
        $this->assertSame($this->staf->user_id, (int) $dokumen->created_by);

        $borang = Borang::sole();
        $this->assertSame('Dalam Proses', $borang->status_permohonan);
        $this->assertSame($this->staf->user_id, $borang->pemohon_id);
        $this->assertNull($borang->tarikh_kelulusan);
        $this->assertNull($borang->ulasan_pelulus);

        // Nilai palsu yang dihantar diabaikan; profil digunakan
        $this->assertSame('Siti Aminah', DokumenData::where('dokumen_field_id', $this->medanNama->dokumen_field_id)->value('nilai_data'));
        $this->assertSame('Kursus', DokumenData::where('dokumen_field_id', $this->medanTujuan->dokumen_field_id)->value('nilai_data'));

        $scan = DokumenScan::sole();
        $this->assertSame('Borang Diisi.pdf', $scan->nama_fail);
        Storage::disk('local')->assertExists($scan->lokasi_fail);
        $this->assertSame(1, DokumenOcr::count());
        Storage::disk('local')->assertMissing("ocr_sementara/{$token}");

        $log = LogAudit::where('tindakan', 'Hantar untuk Kelulusan')->sole();
        $this->assertSame($borang->borang_id, (int) $log->rekod_id);
        $this->assertSame(0, LogAudit::where('tindakan', 'Arkib Terus (Sedia Lulus)')->count());

        // Aliran kelulusan sedia ada: PENYOKONG nampak dalam senarai
        $penyokong = $this->buatPengguna(['PENYOKONG']);
        $this->actingAs($penyokong)->get(route('kelulusan.index'))->assertOk()->assertSee($dokumen->no_rujukan);
    }

    public function test_token_satu_mod_tidak_boleh_disimpan_melalui_mod_lain(): void
    {
        // Token hantar tidak boleh "diluluskan terus" melalui Imbas & Arkib, dan sebaliknya
        $tokenHantar = $this->analisis($this->kerani, 'hantar');
        $this->actingAs($this->kerani)->post(route('imbas-arkib.simpan', $tokenHantar), $this->dataSah())->assertNotFound();
        $this->actingAs($this->kerani)->get(route('imbas-arkib.semak', $tokenHantar))->assertNotFound();

        $tokenArkib = $this->analisis($this->kerani, 'arkib');
        $this->actingAs($this->kerani)->post(route('imbas-hantar.simpan', $tokenArkib), $this->dataSah())->assertNotFound();
        $this->actingAs($this->kerani)->get(route('imbas-hantar.pratonton', $tokenArkib))->assertNotFound();

        $this->assertSame(0, Dokumen::count());
    }

    public function test_imbas_arkib_kekal_guna_nilai_ai_dan_terus_diluluskan(): void
    {
        $token = $this->analisis($this->kerani, 'arkib');

        $this->actingAs($this->kerani)->get(route('imbas-arkib.semak', $token))
            ->assertOk()
            ->assertSee('value="S1ti Am1nah"', false)
            ->assertDontSee('name="pengesahan"', false)
            ->assertSee(route('imbas-arkib.pratonton', $token));

        $this->actingAs($this->kerani)->post(route('imbas-arkib.simpan', $token), $this->dataSah([
            'medan_data' => [
                $this->medanNama->dokumen_field_id => 'Pemohon Lain',
                $this->medanTujuan->dokumen_field_id => 'Kursus',
            ],
            'pengesahan' => null,
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Diluluskan', Borang::sole()->status_permohonan);
        $this->assertSame('Archived', Dokumen::sole()->status);
        $this->assertSame('Pemohon Lain', DokumenData::where('dokumen_field_id', $this->medanNama->dokumen_field_id)->value('nilai_data'));
    }
}
