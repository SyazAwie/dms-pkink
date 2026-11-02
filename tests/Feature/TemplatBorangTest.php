<?php

namespace Tests\Feature;

use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\TemplatBorang;
use App\Models\User;
use App\Services\AnalisisImbasService;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use App\Services\TemplatBorangService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Fasa TB: templat borang kosong bagi kategori. Teks bercetak borang (ada dalam templat)
 * dibezakan dengan tulisan pengisi («…») untuk AI; muat naik/urus templat; cadangan kategori.
 */
class TemplatBorangTest extends TestCase
{
    use SkemaUjian;

    private const TEKS_TEMPLAT = "BORANG PENGGUNAAN PERALATAN KOMPUTER\nTarikh:\nNama Pemohon:\nJabatan/ Syarikat:\nCaj adalah RM (Sewaan Sahaja)\nPermohonan disokong/ tidak disokong\nDaftar KEW.PA-9:";

    private const TEKS_IMBASAN = "BORANG PENGGUNAAN PERALATAN KOMPUTER\nTarikh: 12/8/2026\nNama Pemohon: ZAID BIN ALWI\nJabatan/ Syarikat: BAHAGIAN HARTANAH\nCaj adalah RM (Sewaan Sahaja)\nPermohonan disokong/ ~~tidak-disokong~~\nDaftar KEW.PA-9: [□]";

    private User $admin;

    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('templat');
        Storage::fake('local');
        $this->admin = $this->buatPengguna(['ADMIN']);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'BD-SEWA', 'nama_dokumen' => 'Borang Guna Sewa']);
    }

    private function templat(JenisDokumen $jenis, string $teks = self::TEKS_TEMPLAT, array $atribut = []): TemplatBorang
    {
        return TemplatBorang::create($atribut + [
            'jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'versi' => 1, 'nama_asal' => 'kosong.pdf', 'lokasi_fail' => 'X/kosong.pdf',
            'status_ocr' => 'Selesai', 'aktif' => true,
            'ocr_json' => ['teks' => $teks, 'skor' => 90, 'token' => TemplatBorangService::tokenTeks($teks)],
        ]);
    }

    // ---- TB3: beza teks ----

    public function test_isian_dibalut_teks_bercetak_tidak(): void
    {
        $this->templat($this->jenis);

        $hasil = app(TemplatBorangService::class)->tandaIsian(self::TEKS_IMBASAN, $this->jenis);

        $this->assertGreaterThan(0.9, $hasil['padanan']);
        $this->assertStringContainsString('Nama Pemohon: «ZAID BIN ALWI»', $hasil['teks']);
        $this->assertStringContainsString('Tarikh: «12/8/2026»', $hasil['teks']);
        // Teks bercetak, potongan (token "tidak disokong" ada dalam templat) dan penanda kekal tanpa «»
        $this->assertStringContainsString('Permohonan disokong/ ~~tidak-disokong~~', $hasil['teks']);
        $this->assertStringContainsString('Caj adalah RM (Sewaan Sahaja)', $hasil['teks']);
        $this->assertStringContainsString('Daftar KEW.PA-9: [□]', $hasil['teks']);
    }

    public function test_tanpa_templat_atau_borang_lain_teks_tidak_berubah(): void
    {
        $this->assertSame(self::TEKS_IMBASAN, app(TemplatBorangService::class)->tandaIsian(self::TEKS_IMBASAN, $this->jenis)['teks']);

        $this->templat($this->jenis, "MINIT MESYUARAT JAWATANKUASA\nKehadiran\nPengerusi\nUrus setia\nPerkara berbangkit");
        $hasil = app(TemplatBorangService::class)->tandaIsian(self::TEKS_IMBASAN, $this->jenis);
        $this->assertSame(self::TEKS_IMBASAN, $hasil['teks'], 'padanan rendah: jangan tanda');
        $this->assertLessThan(TemplatBorangService::PADANAN_MIN, $hasil['padanan']);
    }

    public function test_templat_tidak_aktif_atau_belum_ocr_diabaikan(): void
    {
        $this->templat($this->jenis, self::TEKS_TEMPLAT, ['aktif' => false]);
        $this->templat($this->jenis, self::TEKS_TEMPLAT, ['status_ocr' => 'Dalam Giliran', 'versi' => 2]);

        $this->assertNull(app(TemplatBorangService::class)->tandaIsian(self::TEKS_IMBASAN, $this->jenis)['templat']);
    }

    public function test_cadangan_kategori_ikut_templat_paling_sepadan(): void
    {
        $lain = JenisDokumen::create(['kod_dokumen' => 'MINIT', 'nama_dokumen' => 'Minit']);
        $this->templat($this->jenis);
        $this->templat($lain, "MINIT MESYUARAT JAWATANKUASA\nKehadiran\nPengerusi");

        $padan = app(TemplatBorangService::class)->padanKategori(self::TEKS_IMBASAN);

        $this->assertSame($this->jenis->jenis_dokumen_id, $padan['jenis_dokumen_id']);
    }

    public function test_analisis_hantar_teks_bertanda_kepada_ai_dan_simpan_teks_asal(): void
    {
        $this->templat($this->jenis);
        $medan = DokumenField::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'Nama Pemohon',
            'kod_field' => 'nama_pemohon', 'jenis_data' => 'teks', 'susunan' => 1]);
        Storage::disk('local')->put('ocr_sementara/t/fail.pdf', '%PDF');
        $this->mock(OcrService::class, fn (MockInterface $m) => $m->shouldReceive('proses')->andReturn(['teks' => self::TEKS_IMBASAN, 'skor' => 88.0, 'enjin' => ['tesseract']]));
        $this->mock(OllamaExtractionService::class, fn (MockInterface $m) => $m->shouldReceive('ekstrak')
            ->withArgs(fn ($teks) => str_contains($teks, '«ZAID BIN ALWI»'))->andReturn([$medan->dokumen_field_id => 'ZAID BIN ALWI']));

        $hasil = app(AnalisisImbasService::class)->analisis('ocr_sementara/t/fail.pdf', $this->jenis->load('fields'), false);

        $this->assertSame(self::TEKS_IMBASAN, $hasil['teks_ocr'], 'teks disimpan tanpa «»');
        $this->assertGreaterThan(0.9, $hasil['padanan_templat']);
        $this->assertSame('ZAID BIN ALWI', $hasil['nilai_ekstrak'][$medan->dokumen_field_id]);
    }

    /** Semakan kod: isian yang sama dengan perkataan bercetak SETERUSNYA kekal isian. */
    public function test_isian_sama_dengan_perkataan_bercetak_seterusnya(): void
    {
        $this->templat($this->jenis, "BORANG PENGGUNAAN PERALATAN KOMPUTER\nBilangan unit:\n3. PENGESAHAN\nJawatan:\nPegawai Penyokong:");

        $teks = app(TemplatBorangService::class)->tandaIsian(
            "BORANG PENGGUNAAN PERALATAN KOMPUTER\nBilangan unit: 3\n3. PENGESAHAN\nJawatan: Pegawai\nPegawai Penyokong: ALI", $this->jenis)['teks'];

        $this->assertStringContainsString("Bilangan unit: «3»\n3. PENGESAHAN", $teks);
        $this->assertStringContainsString("Jawatan: «Pegawai»\nPegawai Penyokong: «ALI»", $teks);
    }

    public function test_cadangan_kategori_lain_melalui_analisis(): void
    {
        $lain = JenisDokumen::create(['kod_dokumen' => 'MINIT', 'nama_dokumen' => 'Minit Mesyuarat']);
        $this->templat($lain, self::TEKS_TEMPLAT); // imbasan sepadan dengan templat kategori LAIN
        Storage::disk('local')->put('ocr_sementara/t/fail.pdf', '%PDF');
        $this->mock(OcrService::class, fn (MockInterface $m) => $m->shouldReceive('proses')->andReturn(['teks' => self::TEKS_IMBASAN, 'skor' => 88.0, 'enjin' => ['tesseract']]));
        $this->mock(OllamaExtractionService::class, fn (MockInterface $m) => $m->shouldReceive('ekstrak')->andReturn([]));

        $hasil = app(AnalisisImbasService::class)->analisis('ocr_sementara/t/fail.pdf', $this->jenis->load('fields'), false);

        $this->assertSame($lain->jenis_dokumen_id, $hasil['cadangan_kategori']['jenis_dokumen_id']);
    }

    // ---- Penapis AI ----

    public function test_pembetulan_ralat_ocr_oleh_ai_dikekalkan(): void
    {
        $this->assertTrue(OllamaExtractionService::adaDalamIsian('Kerani', 'Jawatan: «Keranl»'));
        $this->assertTrue(OllamaExtractionService::adaDalamIsian('09-7481234', 'Tel: «O9-748 1234»', true));
        $this->assertFalse(OllamaExtractionService::adaDalamIsian('09-0000000', 'Tel: «O9-748 1234»', true));
        $this->assertFalse(OllamaExtractionService::adaDalamIsian('Permohonan disokong', "Nama: «ALI»\nPermohonan disokong"));
        $this->assertTrue(OllamaExtractionService::adaDalamIsian('apa-apa', 'tiada isian bertanda'), 'tanpa templat: tiada penapis');
    }

    public function test_nilai_teks_yang_semuanya_cetakan_borang_dibuang(): void
    {
        $jawapan = [];
        Http::fake(['*/api/generate' => function () use (&$jawapan) {
            return Http::response(['response' => json_encode($jawapan)]);
        }]);
        $nama = new DokumenField(['kod_field' => 'nama', 'nama_field' => 'Nama Pemohon', 'jenis_data' => 'teks']);
        $nama->dokumen_field_id = 1;
        $teks = "Nama Pemohon: «S Y A Z M I»\nJabatan/ Syarikat:\nPermohonan disokong/ tidak disokong";

        $jawapan = ['nama' => 'Permohonan disokong'];
        $this->assertNull(app(OllamaExtractionService::class)->ekstrak($teks, collect([$nama]))[1], 'cetakan borang');
        $jawapan = ['nama' => 'SYAZMI'];
        $this->assertSame('SYAZMI', app(OllamaExtractionService::class)->ekstrak($teks, collect([$nama]))[1], 'huruf dalam kotak dicantum');
        Http::assertSent(fn ($r) => str_contains($r['prompt'], 'TULISAN PENGISI'));
    }

    public function test_tarikh_format_malaysia_daripada_ai_diterima(): void
    {
        $this->assertSame('2026-09-21', OllamaExtractionService::normalTarikhAi('21/09/2026'));
        $this->assertSame('2026-09-21 10:05', OllamaExtractionService::normalTarikhAi('21-9-2026 10:05'));
        $this->assertSame('2026-09-21', OllamaExtractionService::normalTarikhAi('2026-09-21'), 'format ISO tidak berubah');
        $this->assertSame('bukan tarikh', OllamaExtractionService::normalTarikhAi('bukan tarikh'));
    }

    // ---- TB2: urus templat ----

    private function ocrPalsu(): void
    {
        $this->mock(OcrService::class, fn (MockInterface $m) => $m->shouldReceive('proses')
            ->andReturn(['teks' => self::TEKS_TEMPLAT, 'skor' => 91.0, 'enjin' => ['tesseract']]));
    }

    public function test_pentadbir_muat_naik_templat_dan_ocr_siap(): void
    {
        $this->ocrPalsu();

        $this->actingAs($this->admin)->post(route('templat.store', $this->jenis->jenis_dokumen_id), [
            'templat' => UploadedFile::fake()->create('Borang Kosong.pdf', 40, 'application/pdf'),
            'catatan_templat' => 'versi 2026',
        ])->assertRedirect(route('jenis-dokumen.edit', $this->jenis->jenis_dokumen_id))->assertSessionHas('success');

        $t = TemplatBorang::sole();
        $this->assertSame(1, $t->versi);
        $this->assertSame('Borang Kosong.pdf', $t->nama_asal);
        $this->assertStringStartsWith('BD-SEWA/', $t->lokasi_fail);
        Storage::disk('templat')->assertExists($t->lokasi_fail);
        $this->assertSame('Selesai', $t->status_ocr);
        $this->assertContains('kew', $t->token());
        $this->assertSame('Muat Naik Templat', LogAudit::where('nama_jadual', 'templat_borang')->sole()->tindakan);

        $this->get(route('jenis-dokumen.edit', $this->jenis->jenis_dokumen_id))->assertOk()->assertSee('Versi 1')->assertSee('versi 2026');
        $this->get(route('templat.papar', $t->templat_borang_id))->assertOk();
    }

    public function test_togol_dan_padam_templat_direkod(): void
    {
        $t = $this->templat($this->jenis);

        $this->actingAs($this->admin)->post(route('templat.togol', $t->templat_borang_id))->assertSessionHas('success');
        $this->assertFalse($t->fresh()->aktif);
        $this->delete(route('templat.destroy', $t->templat_borang_id))->assertSessionHas('success');
        $this->assertSoftDeleted($t);
        $this->assertSame(['Nyahaktifkan Templat', 'Padam Templat'], LogAudit::where('nama_jadual', 'templat_borang')->orderBy('log_audit_id')->pluck('tindakan')->all());
    }

    public function test_padam_templat_buang_cache_latar_dan_cache_yatim(): void
    {
        $t = $this->templat($this->jenis);
        $kekal = $this->templat($this->jenis, self::TEKS_TEMPLAT, ['versi' => 2]);
        $local = Storage::disk('local');
        $local->put("templat_cache/{$t->templat_borang_id}_abc123/meta.json", '[]');
        $local->put("templat_cache/{$kekal->templat_borang_id}_def456/meta.json", '[]');
        $local->put('templat_cache/9999_yatim/meta.json', '[]');

        $this->actingAs($this->admin)->delete(route('templat.destroy', $t->templat_borang_id))->assertSessionHas('success');

        $this->assertFalse($local->exists("templat_cache/{$t->templat_borang_id}_abc123"));
        $this->assertFalse($local->exists('templat_cache/9999_yatim'));
        $this->assertTrue($local->exists("templat_cache/{$kekal->templat_borang_id}_def456/meta.json"));
    }

    public function test_bukan_pentadbir_tidak_boleh_urus_templat(): void
    {
        $t = $this->templat($this->jenis);
        $kerani = $this->buatPengguna(['KERANI']);

        $this->actingAs($kerani)->get(route('templat.papar', $t->templat_borang_id))->assertForbidden();
        $this->actingAs($kerani)->post(route('templat.togol', $t->templat_borang_id))->assertForbidden();
        $this->actingAs($kerani)->delete(route('templat.destroy', $t->templat_borang_id))->assertForbidden();
        $this->actingAs($kerani)->post(route('templat.store', $this->jenis->jenis_dokumen_id), [
            'templat' => UploadedFile::fake()->create('kosong.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->assertTrue($t->fresh()->aktif);
        $this->assertSame(1, TemplatBorang::count());
    }

    public function test_mod_demo_percubaan_tidak_boleh_ubah_templat(): void
    {
        config(['demo.mod' => 'percubaan']);

        $this->actingAs($this->admin)->post(route('templat.store', $this->jenis->jenis_dokumen_id), [
            'templat' => UploadedFile::fake()->create('kosong.pdf', 10, 'application/pdf'),
        ])->assertSessionHas('error');
        $this->assertSame(0, TemplatBorang::count());

        $t = $this->templat($this->jenis);
        $this->post(route('templat.togol', $t->templat_borang_id))->assertSessionHas('error');
        $this->delete(route('templat.destroy', $t->templat_borang_id))->assertSessionHas('error');
        $this->assertTrue($t->fresh()->aktif);
        $this->assertNotSoftDeleted($t);
    }

    public function test_cipta_kategori_bersama_templat(): void
    {
        $this->ocrPalsu();

        $this->actingAs($this->admin)->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => 'BD-BARU', 'nama_dokumen' => 'Borang Baharu',
            'templat' => UploadedFile::fake()->create('kosong.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('jenis-dokumen.index'))->assertSessionHas('success');

        $jenis = JenisDokumen::where('kod_dokumen', 'BD-BARU')->sole();
        $this->assertSame(1, $jenis->templat()->count());
    }
}
