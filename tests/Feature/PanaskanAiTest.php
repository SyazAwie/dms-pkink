<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * OCR Pantas & Tepat P2: panaskan model AI semasa halaman Imbas dibuka.
 */
class PanaskanAiTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        config(['services.ollama.model' => 'sea-lion-local']);
    }

    public function test_halaman_imbas_panggil_panaskan(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('imbas-hantar.create'))
            ->assertOk()
            ->assertSee('imbas\/panaskan-ai', false);
    }

    public function test_model_sudah_dimuat_tiada_muat_semula(): void
    {
        Http::fake(['*/api/ps' => Http::response(['models' => [['name' => 'sea-lion-local:latest', 'model' => 'sea-lion-local:latest']]])]);

        $this->actingAs($this->buatPengguna(['STAFF']))->postJson(route('imbas.panaskan-ai'))
            ->assertOk()->assertJson(['sedia' => true]);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/api/generate'));
    }

    public function test_model_sejuk_dimuat(): void
    {
        Http::fake([
            '*/api/ps' => Http::response(['models' => []]),
            '*/api/generate' => Http::response(['response' => '', 'done' => true]),
        ]);

        $this->actingAs($this->buatPengguna(['KERANI']))->postJson(route('imbas.panaskan-ai'))
            ->assertOk()->assertJson(['sedia' => true]);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/generate') && $r['prompt'] === '');
    }

    public function test_ollama_mati_tidak_ralat(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('ditolak'));

        $this->actingAs($this->buatPengguna(['STAFF']))->postJson(route('imbas.panaskan-ai'))
            ->assertOk()->assertJson(['sedia' => false]);
    }

    public function test_peranan_tanpa_muat_naik_disekat(): void
    {
        $this->actingAs($this->buatPengguna(['AUDIT']))->postJson(route('imbas.panaskan-ai'))->assertForbidden();
    }
}
