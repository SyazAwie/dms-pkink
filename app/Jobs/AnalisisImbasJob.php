<?php

namespace App\Jobs;

use App\Services\AnalisisImbasService;
use App\Support\Demo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Analisis OCR + AI satu token Imbas & Arkib / Imbas & Hantar dalam worker queue OCR (T2). */
class AnalisisImbasJob implements ShouldQueue
{
    use Queueable;

    /** Tiada cuba semula automatik: kegagalan dipaparkan sebagai amaran di skrin semak. */
    public int $tries = 1;

    public function __construct(public string $token, public ?string $modDemo = null) {}

    public function handle(AnalisisImbasService $servis): void
    {
        Demo::dalamMod($this->modDemo, fn () => $servis->jalankanToken($this->token));
    }

    public function failed(?\Throwable $e): void
    {
        Demo::dalamMod($this->modDemo, fn () => AnalisisImbasService::tandaGagal($this->token));
    }
}
