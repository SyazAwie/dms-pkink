<?php

namespace Tests\Feature;

use App\Models\DokumenField;
use App\Services\OllamaExtractionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * OCR Pantas & Tepat P1: opsyen prestasi Ollama (model penuh dalam GPU, konteks,
 * had jawapan, keep_alive) dan cuba semula tanpa num_gpu jika model gagal dimuat.
 */
class OllamaPrestasiTest extends TestCase
{
    private function medan()
    {
        $f = new DokumenField(['nama_field' => 'Nama', 'kod_field' => 'nama', 'jenis_data' => 'teks']);
        $f->dokumen_field_id = 1;

        return collect([$f]);
    }

    public function test_ekstrak_hantar_opsyen_prestasi(): void
    {
        config(['services.ollama.num_gpu' => 99, 'services.ollama.num_ctx' => 4096,
            'services.ollama.num_predict' => 2048, 'services.ollama.keep_alive' => '30m']);
        Http::fake(['*/api/generate' => Http::response(['response' => '{"nama":"Ali"}'])]);

        $hasil = app(OllamaExtractionService::class)->ekstrak('Nama: Ali', $this->medan());

        $this->assertSame([1 => 'Ali'], $hasil);
        Http::assertSent(fn (Request $r) => $r['keep_alive'] === '30m'
            && $r['options']['num_gpu'] === 99
            && $r['options']['num_ctx'] === 4096
            && $r['options']['num_predict'] === 2048);
    }

    public function test_cuba_semula_tanpa_num_gpu_jika_gagal_dimuat(): void
    {
        Http::fakeSequence('*/api/generate')
            ->push(['error' => 'model requires more system memory'], 500)
            ->push(['response' => '{"nama":"Ali"}']);

        $hasil = app(OllamaExtractionService::class)->ekstrak('Nama: Ali', $this->medan());

        $this->assertSame([1 => 'Ali'], $hasil);
        $permintaan = Http::recorded()->map(fn ($p) => $p[0]);
        $this->assertCount(2, $permintaan);
        $this->assertArrayHasKey('num_gpu', $permintaan[0]['options']);
        $this->assertArrayNotHasKey('num_gpu', $permintaan[1]['options']);
    }

    public function test_num_gpu_kosong_biar_ollama_tentukan(): void
    {
        config(['services.ollama.num_gpu' => '']);
        Http::fake(['*/api/generate' => Http::response(['response' => '{"nama":null}'])]);

        app(OllamaExtractionService::class)->ekstrak('Nama: -', $this->medan());

        Http::assertSent(fn (Request $r) => !array_key_exists('num_gpu', $r['options']));
    }

    public function test_panaskan_muat_model_dengan_opsyen_sama(): void
    {
        config(['services.ollama.num_gpu' => 99, 'services.ollama.num_ctx' => 4096]);
        Http::fake(['*/api/generate' => Http::response(['response' => '', 'done' => true])]);

        $this->assertTrue(app(OllamaExtractionService::class)->panaskan());

        Http::assertSent(fn (Request $r) => $r['prompt'] === ''
            && $r['options'] === ['num_ctx' => 4096, 'num_gpu' => 99]);
    }
}
