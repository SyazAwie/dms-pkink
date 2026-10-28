<?php

namespace Tests\Feature;

use App\Http\Middleware\HeaderKeselamatan;
use App\Models\Dokumen;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use App\Services\PemeriksaFail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Fasa Keselamatan S1: had "bom", PDF aktif, antivirus dan CSP sandbox fail imbasan.
 */
class FailSelamatTest extends TestCase
{
    use SkemaUjian;

    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        // Pengimbas palsu yang tiada: hasil "tanpa imbasan" boleh dijangka dalam semua mesin
        config(['services.keselamatan.periksa_fail' => true, 'services.keselamatan.antivirus_bin' => base_path('tiada-antivirus.exe')]);
        $this->folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'fail_selamat_'.uniqid();
        mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->folder.'/*') ?: []);
        @rmdir($this->folder);
        parent::tearDown();
    }

    private function tulis(string $nama, string $kandungan): string
    {
        file_put_contents($laluan = $this->folder.DIRECTORY_SEPARATOR.$nama, $kandungan);

        return $laluan;
    }

    private function pdf(int $mukaSurat = 1, string $tambahan = ''): string
    {
        $pdf = new \FPDF;
        for ($i = 0; $i < $mukaSurat; $i++) {
            $pdf->AddPage();
        }

        return str_replace('%%EOF', $tambahan."\n%%EOF", $pdf->Output('S'));
    }

    public function test_pdf_aktif_berbahaya_ditolak_javascript_dibenarkan(): void
    {
        $p = app(PemeriksaFail::class);

        $this->assertSame(PemeriksaFail::BERBAHAYA, $p->periksa($this->tulis('a.pdf', $this->pdf(1, '<< /S /Launch /F (cmd.exe) >>')), 'pdf')['status']);
        // Nama PDF dikod hex (#61 = a) tidak terlepas
        $this->assertSame(PemeriksaFail::BERBAHAYA, $p->periksa($this->tulis('b.pdf', $this->pdf(1, '<< /S /L#61unch >>')), 'pdf')['status']);
        $this->assertSame(PemeriksaFail::BERBAHAYA, $p->periksa($this->tulis('c.pdf', $this->pdf(1, '<< /EmbeddedFiles 5 0 R >>')), 'pdf')['status']);

        $js = $p->periksa($this->tulis('d.pdf', $this->pdf(1, '<< /S /JavaScript /JS (app.alert(1)) >>')), 'pdf');
        $this->assertSame(PemeriksaFail::BERSIH, $js['status']);
        $this->assertStringContainsString('Tiada pengimbas antivirus', $js['amaran']);

        $this->assertSame(PemeriksaFail::DITOLAK, $p->periksa($this->tulis('e.pdf', 'MZ bukan pdf'), 'pdf')['status']);
    }

    public function test_had_piksel_imej_dan_muka_surat_pdf(): void
    {
        config(['services.keselamatan.maks_piksel' => 1000]);
        $imej = imagecreatetruecolor(50, 50);
        ob_start();
        imagepng($imej);
        $hasil = app(PemeriksaFail::class)->periksa($this->tulis('besar.png', (string) ob_get_clean()), 'png');
        $this->assertSame(PemeriksaFail::DITOLAK, $hasil['status']);
        $this->assertStringContainsString('Imej terlalu besar', $hasil['mesej']);

        // Muka surat disemak dengan pdfinfo (Poppler); tanpa pdfinfo, had dikuatkuasakan semasa OCR (-l)
        config(['services.keselamatan.maks_muka_surat' => 2]);
        $hasil = app(PemeriksaFail::class)->periksa($this->tulis('panjang.pdf', $this->pdf(3)), 'pdf');
        $pdfinfo = str_ireplace('pdftoppm', 'pdfinfo', (string) config('services.ocr.pdftoppm_bin'));
        if (is_file($pdfinfo)) {
            $this->assertSame(PemeriksaFail::DITOLAK, $hasil['status']);
            $this->assertStringContainsString('3 muka surat', $hasil['mesej']);
        } else {
            $this->assertSame(PemeriksaFail::BERSIH, $hasil['status']);
        }
    }

    public function test_antivirus_wajib_tolak_bila_tiada_pengimbas(): void
    {
        config(['services.keselamatan.antivirus_wajib' => true]);
        $hasil = app(PemeriksaFail::class)->periksa($this->tulis('ok.pdf', $this->pdf()), 'pdf');

        $this->assertSame(PemeriksaFail::DITOLAK, $hasil['status']);
        $this->assertStringContainsString('tidak dapat diimbas', $hasil['mesej']);
    }

    public function test_muat_naik_berbahaya_ditolak_dilog_dan_pentadbir_dimaklumkan(): void
    {
        Storage::fake('local');
        $admin = $this->buatPengguna(['ADMIN']);
        $staf = $this->buatPengguna(['STAFF']);
        $jenis = JenisDokumen::create(['kod_dokumen' => 'SRT', 'nama_dokumen' => 'Surat']);

        $this->actingAs($staf)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'tarikh_dokumen' => '2026-10-28',
            'fail_scan' => [UploadedFile::fake()->createWithContent('invois.pdf', $this->pdf(1, '<< /S /Launch /F (x.exe) >>'))],
        ])->assertSessionHasErrors(['fail_scan.0' => 'PDF ini mengandungi arahan untuk melancarkan program (/Launch) dan ditolak atas sebab keselamatan.']);

        $this->assertSame(0, DokumenScan::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseHas('log_audit', ['tindakan' => 'Fail Berbahaya Ditolak', 'user_id' => $staf->user_id]);
        $this->assertStringContainsString('invois.pdf', $admin->fresh()->notifications->first()->data['mesej']);
    }

    public function test_pratonton_fail_dihidang_dengan_csp_sandbox(): void
    {
        Storage::fake('local');
        $admin = $this->buatPengguna(['ADMIN']);
        $jenis = JenisDokumen::create(['kod_dokumen' => 'SRT', 'nama_dokumen' => 'Surat']);
        $d = Dokumen::create(['jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'no_rujukan' => 'SRT/1', 'tarikh_dokumen' => '2026-10-28', 'created_by' => $admin->user_id]);
        Storage::disk('local')->put('dokumen_scan/a.pdf', $this->pdf());
        $scan = DokumenScan::create(['dokumen_id' => $d->dokumen_id, 'nama_fail' => 'a.pdf', 'lokasi_fail' => 'dokumen_scan/a.pdf']);

        $this->actingAs($admin)->get(route('dokumen.scan.papar', $scan->dokumen_scan_id))->assertOk()
            ->assertHeader('Content-Security-Policy', HeaderKeselamatan::CSP_FAIL);
    }
}
