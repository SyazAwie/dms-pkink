<?php

namespace Tests\Feature;

use App\Jobs\AnalisisImbasJob;
use App\Jobs\OcrFailJob;
use App\Models\Dokumen;
use App\Models\DokumenField;
use App\Models\DokumenOcr;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\User;
use App\Services\AnalisisImbasService;
use App\Services\OcrFailService;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use App\Support\AntrianOcr;
use App\Support\Demo;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * T2: OCR/AI melalui queue `ocr` (satu worker). Bila worker berdegup, analisis Imbas dan
 * "Jalankan OCR" dihantar ke queue dan halaman menyemak status sendiri; bila tidak,
 * semuanya segerak seperti sebelum T2 (diuji oleh ujian Imbas/OCR sedia ada).
 */
class AntrianOcrTest extends TestCase
{
    use SkemaUjian;

    private User $kerani;

    private JenisDokumen $jenis;

    private DokumenField $medanSiri;

    private MockInterface $ocr;

    private string $failDegupan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('local');

        $this->failDegupan = sys_get_temp_dir().'/dms-ujian-degupan-'.uniqid().'.json';
        config(['queue.ocr_degupan' => $this->failDegupan]);

        $bahagian = $this->buatBahagian();
        $this->kerani = $this->buatPengguna(['KERANI'], ['bahagian_id' => $bahagian->bahagian_id]);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon Komputer']);
        $this->medanSiri = DokumenField::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'No. Siri',
            'kod_field' => 'no_siri', 'jenis_data' => 'teks', 'is_required' => true, 'susunan' => 1,
        ]);

        $this->ocr = $this->spy(OcrService::class);
        $this->ocr->shouldReceive('proses')->andReturn(['teks' => 'No Siri: ABC123', 'skor' => 91.0, 'enjin' => ['tesseract']]);
        $this->mock(OllamaExtractionService::class, fn (MockInterface $m) => $m
            ->shouldReceive('ekstrak')->andReturn([$this->medanSiri->dokumen_field_id => 'ABC123']));
    }

    protected function tearDown(): void
    {
        @unlink($this->failDegupan);
        parent::tearDown();
    }

    /** Worker berjalan: queue database + degupan baharu. Kerja dipalsukan (tidak dijalankan). */
    private function workerHidup(bool $sibuk = false, int $umurSaat = 0): void
    {
        config(['queue.default' => 'database']);
        file_put_contents($this->failDegupan, json_encode(['masa' => time() - $umurSaat, 'sibuk' => $sibuk]));
        Queue::fake();
    }

    private function analisis(): string
    {
        $res = $this->actingAs($this->kerani)->post(route('imbas-arkib.analisis'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
            'fail_scan' => UploadedFile::fake()->create('Borang.pdf', 50, 'application/pdf'),
        ]);
        $res->assertRedirect();

        return basename(parse_url($res->headers->get('Location'), PHP_URL_PATH));
    }

    private function jalankanKerjaImbas(): void
    {
        Queue::assertPushedOn(AntrianOcr::NAMA, AnalisisImbasJob::class);
        Queue::pushed(AnalisisImbasJob::class)->each(fn (AnalisisImbasJob $k) => $k->handle(app(AnalisisImbasService::class)));
    }

    // ---- Status worker ----

    public function test_worker_hidup_ikut_degupan(): void
    {
        config(['queue.default' => 'sync']);
        file_put_contents($this->failDegupan, json_encode(['masa' => time(), 'sibuk' => false]));
        $this->assertFalse(AntrianOcr::workerHidup(), 'queue sync: tiada worker');

        config(['queue.default' => 'database']);
        $this->assertTrue(AntrianOcr::workerHidup());

        file_put_contents($this->failDegupan, json_encode(['masa' => time() - 120, 'sibuk' => false]));
        $this->assertFalse(AntrianOcr::workerHidup(), 'degupan lama semasa menunggu = mati');

        file_put_contents($this->failDegupan, json_encode(['masa' => time() - 120, 'sibuk' => true]));
        $this->assertTrue(AntrianOcr::workerHidup(), 'sedang memproses kerja panjang = hidup');

        unlink($this->failDegupan);
        $this->assertFalse(AntrianOcr::workerHidup());
    }

    public function test_pendengar_looping_tulis_degupan_dan_tidak_hentikan_worker(): void
    {
        // Looping === false menghentikan worker (Worker::daemonShouldRun)
        $this->assertNotSame(false, Event::until(new Looping('database', 'ocr')));
        $this->assertFileExists($this->failDegupan);
        $this->assertFalse(AntrianOcr::degupan()['sibuk']);
    }

    // ---- Imbas & Arkib melalui queue ----

    public function test_analisis_dihantar_ke_queue_dan_papar_menunggu(): void
    {
        $this->workerHidup();
        $token = $this->analisis();

        Queue::assertPushedOn(AntrianOcr::NAMA, AnalisisImbasJob::class, fn ($k) => $k->token === $token && $k->modDemo === null);
        $this->ocr->shouldNotHaveReceived('proses');
        $this->assertSame('menunggu', AnalisisImbasService::bacaMeta($token)['status']);

        $this->get(route('imbas-arkib.semak', $token))->assertOk()
            ->assertViewIs('imbas_arkib.menunggu')->assertSee('Dalam giliran');
        $this->getJson(route('imbas-arkib.status', $token))
            ->assertJson(['status' => 'menunggu', 'muat_semula' => false]);
    }

    public function test_simpan_disekat_sebelum_analisis_siap(): void
    {
        $this->workerHidup();
        $token = $this->analisis();

        $this->post(route('imbas-arkib.simpan', $token), [
            'tarikh_dokumen' => '2025-03-01', 'medan_data' => [$this->medanSiri->dokumen_field_id => 'X'],
        ])->assertRedirect(route('imbas-arkib.semak', $token));
        $this->assertSame(0, Dokumen::count());
    }

    public function test_kerja_siap_isi_meta_dan_papar_skrin_semak(): void
    {
        $this->workerHidup();
        $token = $this->analisis();
        $this->jalankanKerjaImbas();

        $meta = AnalisisImbasService::bacaMeta($token);
        $this->assertSame('siap', $meta['status']);
        $this->assertSame('No Siri: ABC123', $meta['teks_ocr']);
        $this->assertSame('ABC123', $meta['nilai_ekstrak'][$this->medanSiri->dokumen_field_id]);

        $this->getJson(route('imbas-arkib.status', $token))->assertJson(['status' => 'siap', 'muat_semula' => true]);
        $this->get(route('imbas-arkib.semak', $token))->assertOk()->assertViewIs('imbas_arkib.semak')->assertSee('ABC123');

        $this->post(route('imbas-arkib.simpan', $token), [
            'tarikh_dokumen' => '2025-03-01', 'medan_data' => [$this->medanSiri->dokumen_field_id => 'ABC123'],
        ])->assertRedirect();
        $this->assertSame(1, Dokumen::count());
    }

    public function test_kerja_tidak_hidupkan_token_yang_sudah_tiada(): void
    {
        $this->workerHidup();
        $token = $this->analisis();
        Storage::disk('local')->deleteDirectory(AnalisisImbasService::folder($token));

        $this->jalankanKerjaImbas();

        $this->assertFalse(Storage::disk('local')->exists(AnalisisImbasService::folder($token).'/meta.json'));
        $this->ocr->shouldNotHaveReceived('proses');
    }

    public function test_worker_mati_sebelum_kerja_diambil_semak_proses_segerak(): void
    {
        $this->workerHidup();
        $token = $this->analisis();
        unlink($this->failDegupan); // worker berhenti

        $this->getJson(route('imbas-arkib.status', $token))->assertJson(['status' => 'menunggu', 'muat_semula' => true]);
        $this->get(route('imbas-arkib.semak', $token))->assertRedirect(route('imbas-arkib.semak', $token));
        $this->assertSame('siap', AnalisisImbasService::bacaMeta($token)['status']);

        // Kerja lama dalam queue tidak memproses semula token yang sudah siap
        $this->jalankanKerjaImbas();
        $this->ocr->shouldHaveReceived('proses')->once();
    }

    public function test_kerja_tersangkut_dianggap_mati(): void
    {
        $this->workerHidup();
        $token = $this->analisis();
        $meta = AnalisisImbasService::bacaMeta($token);
        AnalisisImbasService::tulisMeta($token, array_merge($meta, [
            'status' => 'diproses', 'mula_proses' => now()->subHour()->toIso8601String(),
        ]));

        $this->getJson(route('imbas-arkib.status', $token))->assertJson(['status' => 'diproses', 'muat_semula' => true]);
    }

    public function test_kerja_gagal_buka_skrin_semak_dengan_amaran(): void
    {
        $this->workerHidup();
        $token = $this->analisis();

        (new AnalisisImbasJob($token))->failed(new \RuntimeException('ralat'));

        $this->get(route('imbas-arkib.semak', $token))->assertOk()->assertViewIs('imbas_arkib.semak')
            ->assertSee('isi semua medan secara manual');
    }

    public function test_baca_semula_melalui_queue_paksa_vlm(): void
    {
        $this->workerHidup();
        $token = $this->analisis();
        $this->jalankanKerjaImbas();
        Queue::fake();

        $this->post(route('imbas-arkib.baca-semula', $token))->assertRedirect(route('imbas-arkib.semak', $token));
        $this->assertSame('menunggu', AnalisisImbasService::bacaMeta($token)['status']);
        $this->assertTrue(AnalisisImbasService::bacaMeta($token)['paksa_vlm']);

        $this->jalankanKerjaImbas();
        $this->ocr->shouldHaveReceived('proses')->with(\Mockery::any(), true)->once();
        $meta = AnalisisImbasService::bacaMeta($token);
        $this->assertSame('siap', $meta['status']);
        $this->assertArrayNotHasKey('paksa_vlm', $meta);
    }

    public function test_status_token_pengguna_lain_404(): void
    {
        $this->workerHidup();
        $token = $this->analisis();
        $lain = $this->buatPengguna(['KERANI'], ['bahagian_id' => $this->kerani->bahagian_id]);

        $this->actingAs($lain)->getJson(route('imbas-arkib.status', $token))->assertNotFound();
    }

    // ---- "Jalankan OCR" di Butiran Dokumen ----

    private function scan(string $status = 'Pending'): DokumenScan
    {
        $dokumen = Dokumen::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => 'BPK/2026/0001',
            'tarikh_dokumen' => '2026-10-27', 'created_by' => $this->kerani->user_id, 'bahagian_id' => $this->kerani->bahagian_id]);
        Storage::disk('local')->put('dokumen_scan/a.pdf', '%PDF-1.4');

        return DokumenScan::create(['dokumen_id' => $dokumen->dokumen_id, 'nama_fail' => 'borang.pdf',
            'lokasi_fail' => 'dokumen_scan/a.pdf', 'status_ocr' => $status]);
    }

    public function test_jalankan_ocr_masuk_giliran_dan_kerja_siapkan(): void
    {
        $this->workerHidup();
        $scan = $this->scan();

        $this->actingAs($this->kerani)->post(route('dokumen.scan.ocr', $scan->dokumen_scan_id))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Dalam Giliran', $scan->fresh()->status_ocr);
        $this->ocr->shouldNotHaveReceived('proses');
        $this->getJson(route('dokumen.scan.ocr.status', $scan->dokumen_scan_id))
            ->assertJson(['status' => 'Dalam Giliran', 'berjalan' => true]);
        $this->get(route('dokumen.show', $scan->dokumen_id))->assertSee('data-status-ocr="', false);

        // Klik kedua semasa dalam giliran tidak menghantar kerja lagi
        $this->post(route('dokumen.scan.ocr', $scan->dokumen_scan_id));
        Queue::assertPushedTimes(OcrFailJob::class, 1);

        Queue::pushed(OcrFailJob::class)->each(fn (OcrFailJob $k) => $k->handle(app(OcrFailService::class)));

        $this->assertSame('Selesai', $scan->fresh()->status_ocr);
        $this->assertSame('No Siri: ABC123', DokumenOcr::where('dokumen_scan_id', $scan->dokumen_scan_id)->value('teks_dibaca'));
        $log = LogAudit::where('tindakan', 'Jalankan OCR')->sole();
        $this->assertSame($this->kerani->user_id, $log->user_id);
        $this->assertSame('127.0.0.1', $log->alamat_ip);
        $this->assertSame('ocr_selesai', $this->kerani->notifications()->sole()->data['jenis']);
        $this->getJson(route('dokumen.scan.ocr.status', $scan->dokumen_scan_id))->assertJson(['berjalan' => false]);
    }

    public function test_kerja_ocr_fail_gagal_tanda_ralat(): void
    {
        $this->workerHidup();
        $scan = $this->scan('Dalam Giliran');

        (new OcrFailJob($scan->dokumen_scan_id, ['user_id' => $this->kerani->user_id, 'ip' => null, 'ua' => null]))
            ->failed(new \RuntimeException('ralat'));

        $this->assertSame('Ralat', $scan->fresh()->status_ocr);
    }

    public function test_dalam_giliran_tersangkut_boleh_dijalankan_semula_segerak(): void
    {
        $scan = $this->scan('Dalam Giliran');
        DokumenScan::whereKey($scan->dokumen_scan_id)->update(['updated_at' => now()->subMinutes(10)]);
        // Worker tidak berjalan (queue sync): status tersangkut, butang Cuba Semula dipaparkan

        $this->actingAs($this->kerani)->get(route('dokumen.show', $scan->dokumen_id))
            ->assertSee('Cuba Semula')->assertDontSee('data-status-ocr="', false);
        $this->post(route('dokumen.scan.ocr', $scan->dokumen_scan_id))->assertSessionHas('success');
        $this->assertSame('Selesai', $scan->fresh()->status_ocr);
    }

    // ---- Semakan kod: proses berganda, kerja hilang, degupan ----

    public function test_worker_tidak_ambil_token_yang_sedang_diproses_segerak(): void
    {
        $this->workerHidup();
        $token = $this->analisis();
        $meta = AnalisisImbasService::bacaMeta($token);
        AnalisisImbasService::tulisMeta($token, array_merge($meta, ['status' => 'diproses', 'mula_proses' => now()->toIso8601String()]));

        $this->jalankanKerjaImbas();

        $this->ocr->shouldNotHaveReceived('proses');
        $this->assertSame('diproses', AnalisisImbasService::bacaMeta($token)['status']);
    }

    public function test_token_yang_sudah_dituntut_tidak_diproses_lagi(): void
    {
        $this->workerHidup();
        $token = $this->analisis();
        Storage::disk('local')->put(AnalisisImbasService::folder($token).'/proses.kunci', '');

        app(AnalisisImbasService::class)->jalankanToken($token, segerak: true);

        $this->ocr->shouldNotHaveReceived('proses');
        $this->assertSame('menunggu', AnalisisImbasService::bacaMeta($token)['status']);
    }

    public function test_kunci_tuntutan_lama_diambil_alih_dan_dibuang(): void
    {
        $this->workerHidup();
        $token = $this->analisis();
        $kunci = Storage::disk('local')->path(AnalisisImbasService::folder($token).'/proses.kunci');
        touch($kunci, time() - AntrianOcr::hadKerja() - 10);

        $this->jalankanKerjaImbas();

        $this->assertSame('siap', AnalisisImbasService::bacaMeta($token)['status']);
        $this->assertFileDoesNotExist($kunci);
    }

    public function test_worker_dibunuh_semasa_diproses_token_disambung_segera(): void
    {
        $this->workerHidup();
        $token = $this->analisis();
        $meta = AnalisisImbasService::bacaMeta($token);
        AnalisisImbasService::tulisMeta($token, array_merge($meta, ['status' => 'diproses', 'mula_proses' => now()->toIso8601String()]));
        $kunci = AnalisisImbasService::folder($token).'/proses.kunci';

        // Pemproses masih hidup (PID proses ini): tunggu, jangan proses semula
        Storage::disk('local')->put($kunci, (string) getmypid());
        $this->getJson(route('imbas-arkib.status', $token))->assertJson(['status' => 'diproses', 'muat_semula' => false]);

        // Pemproses dibunuh (PID tiada): sambung segera secara segerak, kunci lama diambil alih
        Storage::disk('local')->put($kunci, '999999');
        $this->getJson(route('imbas-arkib.status', $token))->assertJson(['muat_semula' => true]);
        $this->get(route('imbas-arkib.semak', $token))->assertRedirect(route('imbas-arkib.semak', $token));
        $this->assertSame('siap', AnalisisImbasService::bacaMeta($token)['status']);
        $this->assertFalse(Storage::disk('local')->exists($kunci));
    }

    public function test_sedang_diproses_tanpa_worker_hidup_boleh_dicuba_semula_selepas_had_permintaan(): void
    {
        $scan = $this->scan('Sedang Diproses');
        $this->assertTrue($scan->fresh()->ocrSedangBerjalan(), 'mungkin sedang diproses segerak');

        DokumenScan::whereKey($scan->dokumen_scan_id)->update(['updated_at' => now()->subSeconds((int) config('services.ocr.had_masa', 300) + 60)]);
        $this->assertFalse($scan->fresh()->ocrSedangBerjalan(), 'worker mati & melebihi had permintaan');
    }

    public function test_jadual_jobs_tiada_imbas_dan_ocr_teruskan_segerak(): void
    {
        // Worker berdegup tetapi menghantar kerja gagal (jadual jobs belum dicipta)
        config(['queue.default' => 'database']);
        file_put_contents($this->failDegupan, json_encode(['masa' => time(), 'sibuk' => false]));

        $token = $this->analisis();
        $this->assertSame('siap', AnalisisImbasService::bacaMeta($token)['status']);

        $scan = $this->scan();
        $this->post(route('dokumen.scan.ocr', $scan->dokumen_scan_id))->assertSessionHas('success');
        $this->assertSame('Selesai', $scan->fresh()->status_ocr);
    }

    public function test_kerja_hilang_dari_jadual_jobs_tidak_menyangkut(): void
    {
        $this->workerHidup();
        $scan = $this->scan('Dalam Giliran');
        Cache::put(DokumenScan::kunciKerjaOcr($scan->dokumen_scan_id), 999999);
        $this->assertFalse($scan->fresh()->ocrSedangBerjalan(), 'kerja 999999 tiada dalam jobs');

        $token = $this->analisis();
        Cache::put('ocr-kerja:imbas:'.$token, 999999);
        $this->getJson(route('imbas-arkib.status', $token))->assertJson(['status' => 'menunggu', 'muat_semula' => true]);
    }

    public function test_worker_sibuk_yang_prosesnya_mati_tidak_dianggap_hidup(): void
    {
        config(['queue.default' => 'database']);
        file_put_contents($this->failDegupan, json_encode(['masa' => time() - 60, 'sibuk' => true, 'pid' => 999999]));
        $this->assertFalse(AntrianOcr::workerHidup());

        file_put_contents($this->failDegupan, json_encode(['masa' => time() - 60, 'sibuk' => true, 'pid' => getmypid()]));
        $this->assertTrue(AntrianOcr::workerHidup(), 'proses PHP ini masih hidup');
    }

    public function test_kerja_ocr_fail_tidak_proses_fail_yang_sudah_dituntut(): void
    {
        $this->workerHidup();
        $scan = $this->scan('Sedang Diproses');

        (new OcrFailJob($scan->dokumen_scan_id, ['user_id' => $this->kerani->user_id, 'ip' => null, 'ua' => null]))
            ->handle(app(OcrFailService::class));

        $this->ocr->shouldNotHaveReceived('proses');
    }

    // ---- Mod demo ----

    public function test_kerja_membawa_mod_demo_dan_dalam_mod_pulihkan_persekitaran(): void
    {
        config(['demo.db' => 'dms_ujian_demo']);
        $sambungan = config('database.default');
        $dbAsal = config("database.connections.{$sambungan}.database");
        $rootAsal = config('filesystems.disks.local.root');

        $dalam = Demo::dalamMod(Demo::DEMO, fn () => [config('demo.mod'), config("database.connections.{$sambungan}.database"), config('mail.default')]);

        $this->assertSame([Demo::DEMO, 'dms_ujian_demo', 'log'], $dalam);
        $this->assertNull(config('demo.mod'));
        $this->assertSame($dbAsal, config("database.connections.{$sambungan}.database"));
        $this->assertSame($rootAsal, config('filesystems.disks.local.root'));
        $this->assertSame('antrian', config('queue.connections.database.connection'));
    }
}
