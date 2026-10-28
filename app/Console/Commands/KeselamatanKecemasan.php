<?php

namespace App\Console\Commands;

use App\Models\IpDisekat;
use App\Models\LogAudit;
use App\Support\KeadaanKeselamatan;
use Illuminate\Console\Command;

/**
 * Kawalan kecemasan dari pelayan (Fasa Keselamatan S3), untuk bila SUPERADMIN sendiri
 * tidak boleh log masuk (akaun dicuri, terkunci, atau IP tersekat):
 *   php artisan keselamatan:kecemasan on --sebab="..."   hidupkan mod kecemasan
 *   php artisan keselamatan:kecemasan off                 matikan
 *   php artisan keselamatan:kecemasan status              keadaan semasa
 *   php artisan keselamatan:kecemasan buka-ip             buka semua sekatan IP
 */
class KeselamatanKecemasan extends Command
{
    protected $signature = 'keselamatan:kecemasan {tindakan : on | off | status | buka-ip} {--sebab= : Sebab (untuk on)}';

    protected $description = 'Hidupkan/matikan mod kecemasan atau buka sekatan IP dari pelayan';

    public function handle(): int
    {
        $tindakan = $this->argument('tindakan');
        if (! in_array($tindakan, ['on', 'off', 'status', 'buka-ip'], true)) {
            $this->error('Tindakan tidak sah. Guna: on, off, status atau buka-ip.');

            return self::FAILURE;
        }

        match ($tindakan) {
            'on' => KeadaanKeselamatan::hidupkanKecemasan('Konsol pelayan', $this->option('sebab')),
            'off' => KeadaanKeselamatan::matikanKecemasan(),
            'buka-ip' => IpDisekat::aktif()->get()->each(fn ($s) => $s->forceFill(['tamat_pada' => now()])->save()),
            default => null,
        };
        if ($tindakan !== 'status') {
            LogAudit::create([
                'user_id' => null, 'tindakan' => $tindakan === 'buka-ip' ? 'Buka Sekatan IP' : 'Mod Kecemasan', 'nama_jadual' => 'sistem',
                'rekod_id' => null, 'data_lama' => null, 'data_baharu' => ['tindakan' => $tindakan, 'sumber' => 'konsol', 'sebab' => $this->option('sebab')],
                'alamat_ip' => null, 'peranti_pengguna' => 'artisan', 'created_at' => now(),
            ]);
        }

        $k = KeadaanKeselamatan::kecemasan();
        $this->info($k ? "Mod kecemasan AKTIF sejak {$k['sejak']} oleh ".($k['oleh'] ?? '-') : 'Mod kecemasan tidak aktif.');
        $this->line('IP disekat aktif: '.IpDisekat::aktif()->count());

        return self::SUCCESS;
    }
}
