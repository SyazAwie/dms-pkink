<?php

namespace App\Console\Commands;

use App\Support\Demo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** Hidupkan mod demo atau percubaan (Fasa D3) dengan fail penanda; .env tidak diubah. */
class DemoHidup extends Command
{
    protected $signature = 'demo:hidup {--percubaan : Mod percubaan (sistem penuh: daftar akaun, kelulusan pentadbir, emel sebenar)}';

    protected $description = 'Hidupkan mod demo atau percubaan (DB & fail berasingan)';

    public function handle(): int
    {
        $mod = $this->option('percubaan') ? Demo::PERCUBAAN : Demo::DEMO;
        $modDb = Demo::namaDb($mod);
        if (! DB::selectOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$modDb])
            || ! File::isDirectory(Demo::folderData($mod, 'fail'))) {
            $this->error("DB `{$modDb}` belum disediakan. Jalankan `php artisan demo:sedia".($mod === Demo::PERCUBAAN ? ' --percubaan --kekal=<No. KP anda>' : '').'` dahulu.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(Demo::folder());
        File::put(Demo::folder('aktif'), $mod);
        $this->info("Mod {$mod} HIDUP: laman web kini menggunakan `{$modDb}` dan storage/app/{$mod}/fail.");
        if ($mod === Demo::PERCUBAAN) {
            $this->line('Emel SEBENAR akan dihantar ('.config('mail.default').', daripada '.config('mail.from.address').').');
            $this->line('Semasa terowong hidup, buka sistem melalui pautan terowong (dms-pkink.test akan dialihkan ke situ).');
        }
        $this->line('Matikan selepas sesi dengan: php artisan demo:mati');

        return self::SUCCESS;
    }
}
