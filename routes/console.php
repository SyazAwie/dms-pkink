<?php

use App\Services\OllamaExtractionService;
use App\Support\Keselamatan;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

// Degupan penjadual: halaman Status Sistem menyemak penjadual (Task Scheduler) masih berjalan
Schedule::call(fn () => Cache::forever('penjadual:degupan', now()->toIso8601String()))
    ->everyMinute()->name('degupan-penjadual');

// Fasa Keselamatan: amaran bila sandaran tertunggak (sekali sehari)
Schedule::call(fn () => Keselamatan::semakBerkala())->hourly()->name('semak-keselamatan');

// Fasa Keselamatan S4: uji pemulihan sandaran terkini ke DB sementara, setiap Rabu
Schedule::command('sandaran:uji')->weeklyOn(3, '13:00')->withoutOverlapping();

// Semakan integriti fail imbasan (FailIntegriti): fail hilang / SHA-256 berubah, setiap Isnin
Schedule::command('fail:integriti')->weeklyOn(1, '12:45')->withoutOverlapping();

// Muat model AI (Ollama) ke GPU sebelum digunakan, cth. sebelum demo/pembentangan,
// supaya imbasan pertama tidak menunggu model dimuat. Model kekal selama OLLAMA_KEEP_ALIVE.
// Ollama tidak berjalan → mesej jelas & kod keluar 1 (bukan surih tindanan), jadi
// demo:semak --panaskan tetap memaparkan senarai semak penuh dengan baris RALAT Ollama.
Artisan::command('ai:panaskan', function () {
    $ai = app(OllamaExtractionService::class);
    try {
        if ($ai->sudahDimuat()) {
            $this->info('Model AI sudah dimuat.');

            return 0;
        }
        $mula = microtime(true);
        $dimuat = $ai->panaskan();
    } catch (ConnectionException) {
        $this->error('Ollama tidak berjalan ('.config('services.ollama.url').'). Buka aplikasi Ollama dahulu, kemudian cuba semula.');

        return 1;
    }
    if (! $dimuat) {
        $this->error('Model AI gagal dimuat. Pastikan Ollama berjalan dan OLLAMA_MODEL betul.');

        return 1;
    }
    $this->info(sprintf('Model AI dimuat dalam %.1f saat (kekal %s).', microtime(true) - $mula, config('services.ollama.keep_alive')));

    return 0;
})->purpose('Muat model AI Ollama ke GPU lebih awal');
