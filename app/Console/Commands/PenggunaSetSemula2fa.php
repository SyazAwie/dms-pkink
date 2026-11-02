<?php

namespace App\Console\Commands;

use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Set semula pengesahan dua faktor (2FA) seorang pengguna dari pelayan, bila pengguna
 * kehilangan telefon DAN kod pemulihan. Hanya pentadbir pelayan (akses konsol) boleh
 * menjalankannya; tiada fungsi setara dalam antara muka web, supaya akaun pentadbir yang
 * dicuri tidak boleh mematikan 2FA akaun lain.
 *
 *   php artisan pengguna:set-semula-2fa 900101031234
 *
 * Selepas ditetapkan semula, pengguna log masuk dengan kata laluan sahaja dan (bagi
 * SUPERADMIN/ADMIN bila DUA_FAKTOR_WAJIB=true) terus diminta mendaftar 2FA semula.
 */
class PenggunaSetSemula2fa extends Command
{
    protected $signature = 'pengguna:set-semula-2fa {no_kp : No. Kad Pengenalan pengguna}';

    protected $description = 'Set semula pengesahan dua faktor (2FA) pengguna yang kehilangan telefon dan kod pemulihan';

    public function handle(): int
    {
        $user = User::where('ic_pekerja', trim((string) $this->argument('no_kp')))->first();
        if (! $user) {
            $this->error('Pengguna dengan No. KP tersebut tidak dijumpai.');

            return self::FAILURE;
        }
        if (! $user->dua_faktor_disahkan_pada && ! $user->dua_faktor_rahsia) {
            $this->info("{$user->nama_staff} belum mendaftar 2FA. Tiada perubahan dibuat.");

            return self::SUCCESS;
        }
        if (! $this->confirm("Set semula 2FA bagi {$user->nama_staff} ({$user->email})?", true)) {
            $this->line('Dibatalkan.');

            return self::FAILURE;
        }

        $user->forceFill(['dua_faktor_rahsia' => null, 'dua_faktor_kod_pemulihan' => null, 'dua_faktor_disahkan_pada' => null])->saveQuietly();

        LogAudit::create([
            'user_id' => null, 'tindakan' => 'Set Semula 2FA', 'nama_jadual' => 'users', 'rekod_id' => $user->user_id,
            'data_lama' => null, 'data_baharu' => ['pengguna' => $user->nama_staff, 'sumber' => 'konsol'],
            'alamat_ip' => null, 'peranti_pengguna' => 'artisan', 'created_at' => now(),
        ]);

        $this->info("2FA bagi {$user->nama_staff} telah ditetapkan semula. Pengguna perlu mendaftar 2FA semula selepas log masuk.");

        return self::SUCCESS;
    }
}
