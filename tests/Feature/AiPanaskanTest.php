<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ai:panaskan (dipanggil oleh demo:semak --panaskan): Ollama yang tidak berjalan memberi mesej
 * BM yang jelas dan kod keluar 1, bukan pengecualian tidak ditangkap.
 */
class AiPanaskanTest extends TestCase
{
    public function test_ollama_tidak_berjalan_beri_mesej_jelas(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $this->artisan('ai:panaskan')
            ->expectsOutputToContain('Ollama tidak berjalan')
            ->assertExitCode(1);
    }

    public function test_model_sudah_dimuat(): void
    {
        config(['services.ollama.model' => 'sea-lion-local']);
        Http::fake(['*/api/ps' => Http::response(['models' => [['name' => 'sea-lion-local:latest', 'model' => 'sea-lion-local:latest']]])]);

        $this->artisan('ai:panaskan')->expectsOutput('Model AI sudah dimuat.')->assertExitCode(0);
    }

    public function test_model_dimuat_bila_belum_ada_dalam_memori(): void
    {
        Http::fake([
            '*/api/ps' => Http::response(['models' => []]),
            '*/api/generate' => Http::response(['response' => '', 'done' => true]),
        ]);

        $this->artisan('ai:panaskan')->expectsOutputToContain('Model AI dimuat dalam')->assertExitCode(0);
    }
}
