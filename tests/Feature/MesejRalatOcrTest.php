<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use App\Models\User;
use App\Services\OcrService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Mesej pengecualian OCR/AI (laluan binari, respons Ollama) hanya dipaparkan
 * semasa pembangunan (APP_DEBUG=true). Dalam produksi pengguna dapat mesej umum.
 */
class MesejRalatOcrTest extends TestCase
{
    use SkemaUjian;

    private const RALAT = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe tidak dijumpai';

    private User $kerani;
    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('local');

        $this->kerani = $this->buatPengguna(['KERANI'], ['bahagian_id' => $this->buatBahagian()->bahagian_id]);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon Komputer']);
        $this->mock(OcrService::class)->shouldReceive('proses')->andThrow(new \RuntimeException(self::RALAT));
    }

    private function jalankanOcr()
    {
        $dokumen = Dokumen::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => 'BPK/2026/0001',
            'tarikh_dokumen' => '2026-10-27', 'bahagian_id' => $this->kerani->bahagian_id, 'created_by' => $this->kerani->user_id,
        ]);
        $scan = DokumenScan::create(['dokumen_id' => $dokumen->dokumen_id, 'nama_fail' => 'borang.pdf', 'lokasi_fail' => 'dokumen/borang.pdf']);

        $res = $this->actingAs($this->kerani)->post(route('dokumen.scan.ocr', $scan->dokumen_scan_id));
        $this->assertSame('Ralat', $scan->fresh()->status_ocr);

        return $res;
    }

    private function analisisImbasArkib(): string
    {
        $res = $this->actingAs($this->kerani)->post(route('imbas-arkib.analisis'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
            'fail_scan' => UploadedFile::fake()->create('Borang.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        return $this->get($res->headers->get('Location'))->assertOk()->getContent();
    }

    public function test_ocr_manual_produksi_papar_mesej_umum(): void
    {
        config(['app.debug' => false]);

        $this->jalankanOcr()->assertSessionHas('error', 'OCR gagal untuk "borang.pdf". Sila cuba lagi atau hubungi Pentadbir Sistem.');
    }

    public function test_ocr_manual_pembangunan_papar_butiran(): void
    {
        config(['app.debug' => true]);

        $this->jalankanOcr()->assertSessionHas('error', fn ($m) => str_contains($m, self::RALAT));
    }

    public function test_imbas_arkib_produksi_tidak_dedah_butiran(): void
    {
        config(['app.debug' => false]);

        $html = $this->analisisImbasArkib();
        $this->assertStringContainsString('OCR/AI gagal diproses automatik — sila isi semua medan secara manual.', $html);
        $this->assertStringNotContainsString('tesseract.exe', $html);
    }

    public function test_imbas_arkib_pembangunan_papar_butiran(): void
    {
        config(['app.debug' => true]);

        $this->assertStringContainsString('tesseract.exe', $this->analisisImbasArkib());
    }
}
