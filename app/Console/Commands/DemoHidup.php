<?php

namespace App\Console\Commands;

use App\Support\Demo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** Hidupkan mod demo (Fasa D3) dengan mencipta fail penanda; .env tidak diubah. */
class DemoHidup extends Command
{
    protected $signature = 'demo:hidup';

    protected $description = 'Hidupkan mod demo (DB & fail demo, log masuk butang akaun demo)';

    public function handle(): int
    {
        $demoDb = Demo::namaDb();
        if (! DB::selectOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$demoDb])
            || ! File::isDirectory(Demo::folder('fail'))) {
            $this->error("DB demo `{$demoDb}` belum disediakan. Jalankan `php artisan demo:sedia` dahulu.");

            return self::FAILURE;
        }

        File::put(Demo::folder('aktif'), now()->toIso8601String());
        $this->info("Mod demo HIDUP: laman web kini menggunakan `{$demoDb}` dan storage/app/demo/fail.");
        $this->line('Matikan selepas demo dengan: php artisan demo:mati');

        return self::SUCCESS;
    }
}
