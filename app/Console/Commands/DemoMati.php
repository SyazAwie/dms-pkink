<?php

namespace App\Console\Commands;

use App\Support\Demo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** Matikan mod demo/percubaan (Fasa D3): laman web kembali kepada DB & fail sebenar. */
class DemoMati extends Command
{
    protected $signature = 'demo:mati';

    protected $description = 'Matikan mod demo/percubaan dan kembali kepada pangkalan data sebenar';

    public function handle(): int
    {
        $mod = Demo::modPenanda();
        File::delete([Demo::folder('aktif'), Demo::folder('kod.txt')]);
        $this->info('Mod '.($mod ?? 'demo').' MATI: laman web kembali menggunakan pangkalan data & fail sebenar.');
        if ($mod === Demo::PERCUBAAN) {
            $this->line('Padam akaun & data peribadi peserta dengan: php artisan demo:set-semula --percubaan');
        }

        return self::SUCCESS;
    }
}
