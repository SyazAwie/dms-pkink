<?php

namespace App\Console\Commands;

use App\Support\Demo;
use App\Support\PemulihanSandaran;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Kembalikan DB & fail demo kepada keadaan asas (Fasa D3), cth. antara sesi demo.
 * Hanya DB demo dicipta semula; semua peserta dilog keluar (jadual sesi turut dipulihkan).
 */
class DemoSetSemula extends Command
{
    protected $signature = 'demo:set-semula {--paksa : Tanpa pengesahan}';

    protected $description = 'Set semula data demo kepada keadaan asas';

    public function handle(): int
    {
        $demoDb = Demo::namaDb();
        $asas = Demo::folder('asas.sql');
        if (! File::exists($asas) || ! File::isDirectory(Demo::folder('asas-fail'))) {
            $this->error('Keadaan asas tiada. Jalankan `php artisan demo:sedia` dahulu.');

            return self::FAILURE;
        }
        if (! $this->option('paksa') && ! $this->confirm("Semua perubahan peserta dalam `{$demoDb}` akan dibuang. Teruskan?")) {
            return self::FAILURE;
        }

        $mula = microtime(true);
        try {
            DB::statement("DROP DATABASE IF EXISTS `{$demoDb}`");
            DB::statement("CREATE DATABASE `{$demoDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            PemulihanSandaran::importSql($asas, $demoDb);

            File::deleteDirectory(Demo::folder('fail'));
            File::copyDirectory(Demo::folder('asas-fail'), Demo::folder('fail'));
        } catch (\Throwable $e) {
            $this->error('Set semula demo gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Data demo dikembalikan kepada keadaan asas (%.1f s).', microtime(true) - $mula));

        return self::SUCCESS;
    }
}
