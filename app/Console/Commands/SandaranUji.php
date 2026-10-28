<?php

namespace App\Console\Commands;

use App\Support\Notifikasi;
use App\Support\PemulihanSandaran;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Uji pemulihan sandaran (Fasa Keselamatan S4): sandaran yang tidak pernah diuji mungkin
 * tidak boleh dipulihkan bila diperlukan. Buka zip terkini, import SQL ke DB sementara
 * dms_ujian_pulih_*, bandingkan jadual & bilangan baris dengan DB sebenar, semak fail
 * imbasan dalam zip, kemudian padam DB sementara. Keputusan dalam uji_pulih.json dan
 * Pusat Keselamatan; kegagalan dimaklumkan kepada pentadbir. Dijadualkan mingguan.
 *
 * Perlu keistimewaan CREATE/DROP pada `dms\_ujian\_pulih\_%` bagi pengguna DB aplikasi.
 */
class SandaranUji extends Command
{
    protected $signature = 'sandaran:uji {fail? : Zip sandaran (lalai: terkini)}';

    protected $description = 'Uji pemulihan sandaran ke pangkalan data sementara dan laporkan hasilnya';

    public function handle(): int
    {
        $zip = $this->argument('fail') ?: PemulihanSandaran::terkini();
        if (! $zip || ! File::exists($zip)) {
            return $this->gagal('Tiada fail sandaran untuk diuji.');
        }

        $dbSebenar = config('database.connections.'.config('database.default').'.database');
        $dbUji = PemulihanSandaran::AWALAN_DB_UJI.now()->format('Ymd_His');
        $folder = null;
        $mula = microtime(true);

        try {
            [$folder, $sql] = PemulihanSandaran::buka($zip);
            DB::statement("CREATE DATABASE `{$dbUji}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            PemulihanSandaran::importSql($sql, $dbUji);

            $jadualSebenar = $this->jadual($dbSebenar);
            $jadualUji = $this->jadual($dbUji);
            $hilang = array_values(array_diff($jadualSebenar, $jadualUji));
            $baris = [];
            foreach ($jadualUji as $j) {
                $baris[] = [$j, $this->kira($dbUji, $j), in_array($j, $jadualSebenar, true) ? $this->kira($dbSebenar, $j) : '-'];
            }
            $bilFail = File::isDirectory("{$folder}/fail") ? count(File::allFiles("{$folder}/fail")) : 0;
        } catch (\Throwable $e) {
            return $this->gagal($e->getMessage(), basename($zip));
        } finally {
            try {
                DB::statement("DROP DATABASE IF EXISTS `{$dbUji}`");
            } catch (\Throwable) {
            }
            if ($folder) {
                File::deleteDirectory($folder);
            }
        }

        $this->table(['Jadual', 'Dalam sandaran', 'Semasa'], $baris);
        if ($hilang) {
            return $this->gagal('Jadual tiada dalam sandaran: '.implode(', ', $hilang), basename($zip));
        }

        $tempoh = round(microtime(true) - $mula, 1);
        PemulihanSandaran::tulisStatus('uji_pulih.json', ['berjaya' => true, 'masa' => now()->toDateTimeString(), 'fail' => basename($zip),
            'jadual' => count($jadualUji), 'bil_fail' => $bilFail, 'tempoh_saat' => $tempoh]);
        $this->info('Pemulihan berjaya diuji: '.basename($zip).' → '.count($jadualUji)." jadual, {$bilFail} fail, {$tempoh} s. DB sementara telah dipadam.");

        return self::SUCCESS;
    }

    private function gagal(string $mesej, ?string $fail = null): int
    {
        PemulihanSandaran::tulisStatus('uji_pulih.json', ['berjaya' => false, 'masa' => now()->toDateTimeString(), 'fail' => $fail, 'ralat' => $mesej]);
        $this->error("Uji pemulihan gagal: {$mesej}");
        Notifikasi::amaranSistem('Uji pemulihan sandaran gagal', "Sandaran mungkin tidak boleh dipulihkan bila diperlukan: {$mesej}");

        return self::FAILURE;
    }

    /** @return list<string> */
    private function jadual(string $db): array
    {
        return collect(DB::select('SELECT TABLE_NAME AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?', [$db, 'BASE TABLE']))
            ->pluck('n')->sort()->values()->all();
    }

    private function kira(string $db, string $jadual): int
    {
        return (int) DB::selectOne("SELECT COUNT(*) AS c FROM `{$db}`.`{$jadual}`")->c;
    }
}
