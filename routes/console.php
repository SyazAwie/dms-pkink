<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Bersihkan fail sementara Imbas & Arkib yang ditinggalkan (lebih 24 jam).
// Nota: jadual ini hanya berjalan jika pelayan menjalankan `php artisan schedule:run`
// setiap minit (cron / Task Scheduler). Tanpanya, jalankan arahan ini secara manual.
Schedule::command('imbas-arkib:bersih')->daily();

// Sandaran pangkalan data + fail imbasan (SandaranJalan). Waktu tengah hari kerana komputer
// pembangunan biasanya hidup ketika itu; penjadual tidak berjalan bila komputer dimatikan.
Schedule::command('sandaran:jalan')->dailyAt('12:30')->withoutOverlapping();

// Muat model AI (Ollama) ke GPU sebelum digunakan, cth. sebelum demo/pembentangan,
// supaya imbasan pertama tidak menunggu model dimuat. Model kekal selama OLLAMA_KEEP_ALIVE.
Artisan::command('ai:panaskan', function () {
    $ai = app(\App\Services\OllamaExtractionService::class);
    if ($ai->sudahDimuat()) {
        $this->info('Model AI sudah dimuat.');

        return 0;
    }
    $mula = microtime(true);
    if (!$ai->panaskan()) {
        $this->error('Model AI gagal dimuat. Pastikan Ollama berjalan dan OLLAMA_MODEL betul.');

        return 1;
    }
    $this->info(sprintf('Model AI dimuat dalam %.1f saat (kekal %s).', microtime(true) - $mula, config('services.ollama.keep_alive')));

    return 0;
})->purpose('Muat model AI Ollama ke GPU lebih awal');
