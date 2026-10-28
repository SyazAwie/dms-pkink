<?php

namespace App\Console\Commands;

use App\Support\Demo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/** Matikan mod demo (Fasa D3): laman web kembali kepada DB & fail sebenar. */
class DemoMati extends Command
{
    protected $signature = 'demo:mati';

    protected $description = 'Matikan mod demo dan kembali kepada pangkalan data sebenar';

    public function handle(): int
    {
        File::delete(Demo::folder('aktif'));
        $this->info('Mod demo MATI: laman web kembali menggunakan pangkalan data & fail sebenar.');

        return self::SUCCESS;
    }
}
