<?php

namespace App\Console\Commands;

use App\Models\LogAudit;
use App\Support\KeadaanKeselamatan;
use App\Support\PemulihanSandaran;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Pemulihan berpandu daripada sandaran (Fasa Keselamatan S4), untuk selepas serangan
 * (data dipadam/disulitkan) atau kerosakan. Langkah:
 *  1. Mod kecemasan dihidupkan (tiada pengguna boleh mengubah data semasa pemulihan).
 *  2. Sandaran keadaan semasa diambil dahulu (bukti/forensik; diteruskan jika gagal).
 *  3. Pangkalan data diimport daripada zip; fail imbasan disalin semula.
 *  4. Mod kecemasan DIBIARKAN aktif: semak sistem, kemudian matikan di Pusat Keselamatan
 *     atau `php artisan keselamatan:kecemasan off`.
 *
 *   php artisan sandaran:pulih                       sandaran terkini, DB + fail
 *   php artisan sandaran:pulih E:\DMS_Sandaran\sandaran_20261028_123000.zip
 *   php artisan sandaran:pulih --pangkalan-data --ke-db=dms_semakan   (pulih ke DB lain untuk disemak)
 */
class SandaranPulih extends Command
{
    protected $signature = 'sandaran:pulih
        {fail? : Zip sandaran (lalai: terkini dalam folder sandaran)}
        {--pangkalan-data : Pulihkan pangkalan data sahaja}
        {--fail-imbasan : Pulihkan fail imbasan sahaja}
        {--ke-db= : Import ke pangkalan data lain (tidak menyentuh DB sebenar)}
        {--ya : Langkau soalan pengesahan}';

    protected $description = 'Pulihkan pangkalan data dan/atau fail imbasan daripada zip sandaran';

    public function handle(): int
    {
        $zip = $this->argument('fail') ?: PemulihanSandaran::terkini();
        if (! $zip || ! File::exists($zip)) {
            $this->error('Fail sandaran tidak dijumpai.');

            return self::FAILURE;
        }

        $db = (bool) $this->option('pangkalan-data');
        $fail = (bool) $this->option('fail-imbasan');
        if (! $db && ! $fail) {
            $db = $fail = true;
        }
        $keDb = $this->option('ke-db');
        $dbSasaran = $keDb ?: config('database.connections.'.config('database.default').'.database');
        $keDbSebenar = ! $keDb;

        $this->warn('PEMULIHAN DARIPADA SANDARAN');
        $this->line('Fail       : '.$zip.' ('.number_format(File::size($zip) / 1048576, 1).' MB, '.date('d/m/Y H:i', File::lastModified($zip)).')');
        $this->line('Pulihkan   : '.implode(' + ', array_filter([$db ? "pangkalan data → {$dbSasaran}" : null, $fail ? 'fail imbasan → storage/app/private' : null])));
        if ($keDbSebenar && $db) {
            $this->warn('Data dalam pangkalan data sebenar akan DIGANTI dengan isi sandaran ini.');
        }
        if (! $this->option('ya') && $this->ask('Taip PULIHKAN untuk meneruskan') !== 'PULIHKAN') {
            $this->info('Dibatalkan. Tiada apa-apa diubah.');

            return self::FAILURE;
        }

        if ($keDbSebenar) {
            KeadaanKeselamatan::hidupkanKecemasan('sandaran:pulih', 'Pemulihan sandaran sedang berjalan');
            $this->line('Mod kecemasan dihidupkan.');
            $this->line('Mengambil sandaran keadaan semasa (bukti)...');
            if (Artisan::call('sandaran:jalan') !== self::SUCCESS) {
                $this->warn('Sandaran keadaan semasa gagal; pemulihan diteruskan.');
            }
        }

        $folder = null;
        try {
            [$folder, $sql] = PemulihanSandaran::buka($zip);
            if ($db) {
                if ($keDb) {
                    DB::statement("CREATE DATABASE IF NOT EXISTS `{$keDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                }
                PemulihanSandaran::importSql($sql, $dbSasaran);
                $this->info("Pangkalan data dipulihkan ke {$dbSasaran}.");
            }
            $bilFail = $fail ? PemulihanSandaran::pulihkanFail($folder) : 0;
            if ($fail) {
                $this->info("{$bilFail} fail imbasan dipulihkan.");
            }
        } catch (\Throwable $e) {
            $this->error('Pemulihan gagal: '.$e->getMessage());
            $this->line('Mod kecemasan kekal aktif. Cuba sandaran lain (cth. salinan luar talian).');

            return self::FAILURE;
        } finally {
            if ($folder) {
                File::deleteDirectory($folder);
            }
        }

        try {
            LogAudit::create([
                'user_id' => null, 'tindakan' => 'Pulihkan Sandaran', 'nama_jadual' => 'sistem', 'rekod_id' => null, 'data_lama' => null,
                'data_baharu' => ['fail' => basename($zip), 'pangkalan_data' => $db ? $dbSasaran : null, 'bil_fail' => $bilFail],
                'alamat_ip' => null, 'peranti_pengguna' => 'artisan', 'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Log audit dalam DB yang baru dipulihkan; jangan gagalkan pemulihan kerananya
        }

        if ($keDbSebenar) {
            $this->warn('Mod kecemasan MASIH AKTIF. Semak sistem, tukar semua kata laluan, kemudian matikan:');
            $this->line('  php artisan keselamatan:kecemasan off   (atau di Pusat Keselamatan)');
        }

        return self::SUCCESS;
    }
}
