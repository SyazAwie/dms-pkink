<?php

namespace Tests\Feature;

use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use App\Services\VlmOcrService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Laluan OCR hibrid: Tesseract dahulu; muka surat berkeyakinan rendah (tulisan tangan)
 * dibaca semula oleh model penglihatan tempatan (GLM-OCR melalui Ollama).
 */
class OcrHibridTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.ocr.vlm_aktif' => true, 'services.ocr.vlm_model' => 'glm-ocr:q8_0', 'services.ocr.vlm_ambang' => 80]);
    }

    /** OcrService dengan Tesseract palsu (pulangkan teks & skor tetap) supaya ujian tidak perlukan binari. */
    private function ocr(string $teks, ?float $skor): OcrService
    {
        return new class($teks, $skor) extends OcrService {
            public function __construct(private string $t, private ?float $s)
            {
                parent::__construct();
            }

            protected function ocrTerbaik(string $laluanImej, string $folderSementara): array
            {
                return [$this->t, $this->s];
            }
        };
    }

    private function imej(int $lebar = 400): string
    {
        $im = imagecreatetruecolor($lebar, 100);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        ob_start();
        imagepng($im);
        Storage::disk('local')->put('ujian/borang.png', ob_get_clean());

        return 'ujian/borang.png';
    }

    public function test_skor_tinggi_kekal_tesseract_tanpa_panggil_vlm(): void
    {
        Http::fake();

        $hasil = $this->ocr('Nama: ALI', 92.0)->proses($this->imej());

        $this->assertSame('Nama: ALI', $hasil['teks']);
        $this->assertSame(['tesseract'], $hasil['enjin']);
        Http::assertNothingSent();
    }

    public function test_skor_rendah_dibaca_semula_oleh_vlm(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => "Nama Pemohon: ALI BinABu\nJabatan: HARTANAH"])]);

        $hasil = $this->ocr('L ARY AWN AW', 77.15)->proses($this->imej());

        $this->assertSame("Nama Pemohon: ALI BinABu\nJabatan: HARTANAH", $hasil['teks']);
        $this->assertSame(77.15, $hasil['skor']);           // skor Tesseract kekal sebagai penunjuk
        $this->assertSame(['vlm'], $hasil['enjin']);
        Http::assertSent(fn (Request $r) => $r['model'] === 'glm-ocr:q8_0'
            && $r['prompt'] === 'Text Recognition:'
            && count($r['images']) === 1);
    }

    public function test_vlm_gagal_guna_teks_tesseract(): void
    {
        Http::fake(['*/api/generate' => Http::response('ralat', 500)]);

        $hasil = $this->ocr('teks tesseract', 60.0)->proses($this->imej());

        $this->assertSame('teks tesseract', $hasil['teks']);
        $this->assertSame(['tesseract'], $hasil['enjin']);
    }

    public function test_paksa_vlm_walaupun_skor_tinggi(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => 'teks vlm'])]);

        $this->assertSame(['vlm'], $this->ocr('teks', 95.0)->proses($this->imej(), true)['enjin']);
    }

    public function test_vlm_dimatikan(): void
    {
        config(['services.ocr.vlm_aktif' => false]);
        Http::fake();

        $this->assertSame(['tesseract'], $this->ocr('teks', 40.0)->proses($this->imej())['enjin']);
        Http::assertNothingSent();
    }

    public function test_imej_besar_dikecilkan_sebelum_dihantar(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => 'ok'])]);

        app(VlmOcrService::class)->baca(Storage::disk('local')->path($this->imej(2480)));

        Http::assertSent(function (Request $r) {
            $saiz = getimagesizefromstring(base64_decode($r['images'][0]));

            return $saiz[0] === 1240;
        });
    }

    public function test_tahun_dua_digit_dinormalkan(): void
    {
        $tahunDepan = (int) date('y') + 1;

        $this->assertSame('Tarikh: 20/9/2026', OllamaExtractionService::normalTahun('Tarikh: 20/9/26'));
        $this->assertSame('13/8/2025 dan 21/09/2026', OllamaExtractionService::normalTahun('13/8/25 dan 21/09/26'));
        $this->assertSame('1/1/1998', OllamaExtractionService::normalTahun('1/1/98'));
        $this->assertSame('1/1/20' . $tahunDepan, OllamaExtractionService::normalTahun('1/1/' . $tahunDepan));
        // Tidak disentuh: tahun 4 digit, rujukan fail, nombor bukan tarikh
        foreach (['12/8/2026', 'PKINK/BD/UJI/100-2/1 (5)', 'Bil. 4/2026', '45/13/26', '3/4'] as $teks) {
            $this->assertSame($teks, OllamaExtractionService::normalTahun($teks), $teks);
        }
    }

    public function test_pengekstrakan_suhu_sifar_seed_tetap(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => '{"nama":null}'])]);
        $f = new DokumenField(['nama_field' => 'Nama', 'kod_field' => 'nama', 'jenis_data' => 'teks']);
        $f->dokumen_field_id = 1;

        app(OllamaExtractionService::class)->ekstrak('Tarikh: 20/9/26', collect([$f]));

        Http::assertSent(fn (Request $r) => $r['options']['temperature'] === 0
            && $r['options']['seed'] === 42
            && str_contains($r['prompt'], '20/9/2026'));
    }

    public function test_skrin_semak_tunjuk_muka_surat_dibaca_vlm(): void
    {
        $this->sediakanSkema();
        $kerani = $this->buatPengguna(['KERANI']);
        $jenis = JenisDokumen::create(['kod_dokumen' => 'TUKAR', 'nama_dokumen' => 'Borang Tukar']);
        DokumenField::create([
            'jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'nama_field' => 'Nama Pemohon',
            'kod_field' => 'nama_pemohon', 'jenis_data' => 'teks', 'is_required' => false, 'susunan' => 1,
        ]);
        $this->mock(OcrService::class, fn (MockInterface $m) => $m
            ->shouldReceive('proses')->andReturn(['teks' => 'Nama Pemohon: ALI', 'skor' => 77.2, 'enjin' => ['vlm']]));
        $this->mock(OllamaExtractionService::class, fn (MockInterface $m) => $m
            ->shouldReceive('ekstrak')->andReturn([]));

        $res = $this->actingAs($kerani)->post(route('imbas-arkib.analisis'), [
            'jenis_dokumen_id' => $jenis->jenis_dokumen_id,
            'fail_scan' => UploadedFile::fake()->create('borang.pdf', 10, 'application/pdf'),
        ]);
        $token = basename(parse_url($res->headers->get('Location'), PHP_URL_PATH));

        $this->actingAs($kerani)->get(route('imbas-arkib.semak', $token))
            ->assertOk()
            ->assertSee('Muka surat 1 dibaca dengan')
            ->assertSee('AI penglihatan');
    }
}
