<?php

namespace App\Console\Commands;

use App\Support\Demo;
use Illuminate\Console\Command;

/** Papar atau tukar kod akses demo/percubaan (Fasa D). Kod baharu membatalkan akses lama. */
class DemoKod extends Command
{
    protected $signature = 'demo:kod {--baharu : Jana kod baharu (semua peserta perlu masukkan kod semula)}';

    protected $description = 'Papar atau tukar kod akses peserta demo/percubaan';

    public function handle(): int
    {
        if (! Demo::penandaWujud()) {
            $this->error('Mod demo/percubaan belum hidup: php artisan demo:hidup [--percubaan]');

            return self::FAILURE;
        }

        $kod = $this->option('baharu') ? Demo::janaKod() : (Demo::kodAkses() ?? Demo::janaKod());
        $this->info("Kod akses: {$kod}");
        if ($url = Demo::urlTerowong()) {
            $this->line('Halaman QR: '.rtrim($url, '/')."/demo?kod={$kod}");
        }

        return self::SUCCESS;
    }
}
