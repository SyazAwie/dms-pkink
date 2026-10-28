<?php

namespace App\Console\Commands;

use App\Support\Demo;
use App\Support\PemulihanSandaran;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Kembalikan DB & fail mod demo/percubaan kepada keadaan asas (Fasa D3), cth. antara sesi.
 * Mod percubaan: ini juga memadam akaun & data peribadi peserta (No. KP, emel).
 * Hanya DB mod dicipta semula; semua peserta dilog keluar (jadual sesi turut dipulihkan).
 */
class DemoSetSemula extends Command
{
    protected $signature = 'demo:set-semula
                            {--percubaan : Set semula mod percubaan (padam akaun & data peserta)}
                            {--paksa : Tanpa pengesahan}';

    protected $description = 'Set semula data demo/percubaan kepada keadaan asas';

    public function handle(): int
    {
        $mod = $this->option('percubaan') ? Demo::PERCUBAAN : Demo::DEMO;
        $modDb = Demo::namaDb($mod);
        $asas = Demo::folderData($mod, 'asas.sql');
        if (! File::exists($asas) || ! File::isDirectory(Demo::folderData($mod, 'asas-fail'))) {
            $this->error("Keadaan asas mod {$mod} tiada. Jalankan `php artisan demo:sedia".($mod === Demo::PERCUBAAN ? ' --percubaan' : '').'` dahulu.');

            return self::FAILURE;
        }
        if (! $this->option('paksa') && ! $this->confirm("Semua perubahan peserta dalam `{$modDb}` akan dibuang. Teruskan?")) {
            return self::FAILURE;
        }

        $mula = microtime(true);
        try {
            DB::statement("DROP DATABASE IF EXISTS `{$modDb}`");
            DB::statement("CREATE DATABASE `{$modDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            PemulihanSandaran::importSql($asas, $modDb);

            File::deleteDirectory(Demo::folderData($mod, 'fail'));
            File::copyDirectory(Demo::folderData($mod, 'asas-fail'), Demo::folderData($mod, 'fail'));
        } catch (\Throwable $e) {
            $this->error('Set semula gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Data mod %s dikembalikan kepada keadaan asas (%.1f s).', $mod, microtime(true) - $mula));

        return self::SUCCESS;
    }
}
