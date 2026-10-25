<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\User;
use App\Services\OllamaExtractionService;
use App\Support\JenisMedan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Langkah 3 medan EAV: kotak semak (banyak pilihan, disimpan sebagai JSON) dan
 * tajuk seksyen (paparan sahaja), cth. borang Laman Web PKINK & Pendaftaran Emel.
 */
class MedanEavKotakSemakTest extends TestCase
{
    use SkemaUjian;

    private User $admin;
    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->admin = $this->buatPengguna(['ADMIN'], ['bahagian_id' => $this->buatBahagian()->bahagian_id]);

        $this->actingAs($this->admin)->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => 'PLW',
            'nama_dokumen' => 'Borang Pengemaskinian Laman Web PKINK',
            'medan' => [
                ['nama_field' => 'Nama Pegawai', 'kod_field' => 'nama_pegawai', 'jenis_data' => 'teks'],
                ['nama_field' => 'Maklumat Pengemaskinian', 'kod_field' => 'seksyen_maklumat', 'jenis_data' => 'seksyen', 'is_required' => '1', 'bantuan' => 'Isi jika ada maklumat terkini'],
                ['nama_field' => 'Kategori Maklumat', 'kod_field' => 'kategori', 'jenis_data' => 'kotak_semak', 'pilihan' => 'Galeri, Berita Terkini, Aktiviti Utama', 'lain' => '1', 'is_required' => '1'],
                ['nama_field' => 'Urusan Bahagian Digital', 'kod_field' => 'seksyen_bd', 'jenis_data' => 'seksyen'],
                ['nama_field' => 'Tarikh Terima', 'kod_field' => 'tarikh_terima', 'jenis_data' => 'tarikh'],
            ],
        ])->assertRedirect(route('jenis-dokumen.index'));

        $this->jenis = JenisDokumen::with('fields')->where('kod_dokumen', 'PLW')->sole();
    }

    private function medan(string $kod): DokumenField
    {
        return $this->jenis->fields->firstWhere('kod_field', $kod);
    }

    private function id(string $kod): int
    {
        return $this->medan($kod)->dokumen_field_id;
    }

    public function test_pembina_simpan_seksyen_tanpa_wajib_dan_kotak_semak_dengan_pilihan(): void
    {
        $seksyen = $this->medan('seksyen_maklumat');
        $this->assertSame('seksyen', $seksyen->jenis_data);
        $this->assertFalse($seksyen->is_required, 'Seksyen tidak boleh wajib');
        $this->assertSame(['bantuan' => 'Isi jika ada maklumat terkini'], $seksyen->tetapan);

        $kotak = $this->medan('kategori');
        $this->assertSame(['Galeri', 'Berita Terkini', 'Aktiviti Utama'], $kotak->pilihan);
        $this->assertSame(['lain' => true], $kotak->tetapan);
    }

    public function test_peraturan_kotak_semak_dan_lain_lain(): void
    {
        $kotak = $this->medan('kategori');
        $id = $kotak->dokumen_field_id;
        $sah = fn (array $input) => Validator::make($input, JenisMedan::peraturan($kotak));

        $this->assertTrue($sah(['medan_data' => [$id => ['Galeri', 'Aktiviti Utama']]])->passes());
        $this->assertFalse($sah(['medan_data' => [$id => ['Galeri', 'Bukan Pilihan']]])->passes());
        $this->assertFalse($sah(['medan_data' => [$id => 'Galeri']])->passes(), 'Mesti senarai');
        $this->assertFalse($sah(['medan_data' => []])->passes(), 'Wajib sekurang-kurangnya satu');

        $ralat = $sah(['medan_data' => [$id => ['Galeri', JenisMedan::NILAI_LAIN]]]);
        $this->assertTrue($ralat->fails());
        $this->assertSame(
            'Sila nyatakan pilihan "Lain-lain" bagi Kategori Maklumat.',
            $ralat->errors()->first("medan_lain.{$id}")
        );
        $this->assertTrue($sah(['medan_data' => [$id => [JenisMedan::NILAI_LAIN]], 'medan_lain' => [$id => 'Buletin']])->passes());

        $this->assertSame([], JenisMedan::peraturan($this->medan('seksyen_bd')));
    }

    public function test_simpanan_borang_dan_paparan_kotak_semak(): void
    {
        $kotak = $this->medan('kategori');

        // Disusun ikut senarai pilihan; Lain-lain di hujung
        $json = JenisMedan::untukSimpan($kotak, [JenisMedan::NILAI_LAIN, 'Aktiviti Utama', 'Galeri'], 'Buletin PKINK');
        $this->assertSame('["Galeri","Aktiviti Utama","Lain-lain: Buletin PKINK"]', $json);
        $this->assertNull(JenisMedan::untukSimpan($kotak, []));
        $this->assertSame([['Galeri', 'Aktiviti Utama', JenisMedan::NILAI_LAIN], 'Buletin PKINK'], JenisMedan::untukBorang($kotak, $json));

        $this->assertSame(['nilai lama'], JenisMedan::senaraiNilai('nilai lama'), 'Nilai bukan JSON dianggap satu nilai');
        $this->assertSame(
            '<span class="medan-cip">Galeri</span> <span class="medan-cip">&lt;b&gt;</span>',
            (string) JenisMedan::papar($kotak, '["Galeri","<b>"]')
        );

        $this->assertNull(JenisMedan::untukSimpan($this->medan('seksyen_bd'), 'apa-apa'));
    }

    public function test_pembersihan_ai_kotak_semak(): void
    {
        $kotak = $this->medan('kategori');

        $this->assertSame('["Galeri","Berita Terkini"]', JenisMedan::bersihkanAi($kotak, 'berita terkini, GALERI'));
        $this->assertSame('["Galeri","Lain-lain: Buletin, MBJ"]', JenisMedan::bersihkanAi($kotak, ['Galeri', 'Buletin', 'MBJ']));
        $this->assertNull(JenisMedan::bersihkanAi($kotak, []));
        $this->assertNull(JenisMedan::bersihkanAi($this->medan('seksyen_bd'), 'apa-apa'));
    }

    public function test_muat_naik_edit_dan_butiran(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
            'tarikh_dokumen' => '2026-10-25',
            'medan_data' => [
                $this->id('nama_pegawai') => 'Ali',
                $this->id('kategori') => ['Berita Terkini', JenisMedan::NILAI_LAIN],
            ],
            'medan_lain' => [$this->id('kategori') => 'Buletin PKINK'],
            'fail_scan' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $dokumen = Dokumen::sole();
        $this->assertSame('["Berita Terkini","Lain-lain: Buletin PKINK"]', DokumenData::where('dokumen_field_id', $this->id('kategori'))->value('nilai_data'));
        $this->assertSame(2, DokumenData::count(), 'Seksyen tidak disimpan');

        $this->actingAs($this->admin)->get(route('dokumen.edit', $dokumen->dokumen_id))
            ->assertOk()
            ->assertSee('value="Berita Terkini" checked', false)
            ->assertSee('value="' . JenisMedan::NILAI_LAIN . '" checked', false)
            ->assertSee('value="Buletin PKINK"', false)
            ->assertDontSee('value="Galeri" checked', false)
            ->assertSee('medan-seksyen-tajuk', false);

        // Butiran: seksyen dipaparkan hanya bila ada nilai di bawahnya
        $this->actingAs($this->admin)->get(route('dokumen.show', $dokumen->dokumen_id))
            ->assertOk()
            ->assertSee('<div class="medan-seksyen-papar">Maklumat Pengemaskinian</div>', false)
            ->assertDontSee('Urusan Bahagian Digital')
            ->assertSee('<span class="medan-cip">Lain-lain: Buletin PKINK</span>', false);
    }

    public function test_ralat_validasi_kekalkan_kotak_semak_yang_dinyahtanda(): void
    {
        $dokumen = Dokumen::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => 'PLW/2026/0001', 'tarikh_dokumen' => '2026-10-25',
            'bahagian_id' => $this->admin->bahagian_id, 'created_by' => $this->admin->user_id,
        ]);
        DokumenData::create(['dokumen_id' => $dokumen->dokumen_id, 'dokumen_field_id' => $this->id('kategori'), 'nilai_data' => '["Galeri"]']);

        // Semua kotak dinyahtanda (tiada dalam permintaan) + medan wajib kosong → ralat
        $this->actingAs($this->admin)->from(route('dokumen.edit', $dokumen->dokumen_id))
            ->put(route('dokumen.update', $dokumen->dokumen_id), ['tarikh_dokumen' => '2026-10-25'])
            ->assertSessionHasErrors("medan_data.{$this->id('kategori')}");

        $this->actingAs($this->admin)->get(route('dokumen.edit', $dokumen->dokumen_id))
            ->assertOk()
            ->assertDontSee('value="Galeri" checked', false);
    }

    public function test_prompt_ai_kecualikan_seksyen(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => json_encode([
            'nama_pegawai' => 'Ali', 'kategori' => ['Galeri'], 'seksyen_bd' => 'x',
        ])])]);

        $hasil = app(OllamaExtractionService::class)->ekstrak('teks', $this->jenis->fields);

        $this->assertArrayNotHasKey($this->id('seksyen_bd'), $hasil);
        $this->assertSame('["Galeri"]', $hasil[$this->id('kategori')]);
        Http::assertSent(fn ($req) => !str_contains($req['prompt'], 'seksyen_bd')
            && str_contains($req['prompt'], '- kod "kategori" (Kategori Maklumat): senarai JSON nilai yang ditanda'));
    }
}
