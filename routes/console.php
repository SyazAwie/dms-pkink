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
