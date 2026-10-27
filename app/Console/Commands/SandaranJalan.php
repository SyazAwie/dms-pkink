<?php

namespace App\Console\Commands;

use App\Support\Notifikasi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Sandaran harian: pangkalan data (mysqldump) + fail imbasan (disk `local`) dalam satu zip
 * sandaran_YYYYMMDD_HHMMSS.zip:
 *   pangkalan_data/{nama_db}.sql
 *   fail/...                       (storage/app/private, kecuali ocr_sementara)
 *
 * Disimpan dalam services.sandaran.folder, disalin ke services.sandaran.salinan jika
 * ditetapkan (cth. OneDrive), dan sandaran lebih lama daripada simpan_hari dipadam di
 * kedua-dua lokasi. Keputusan terakhir ditulis ke {folder}/terakhir.json.
 *
 * Pemulihan: lihat README bahagian 10.2.
 */
class SandaranJalan extends Command
{
    /** Fail sementara Imbas & Arkib tidak perlu disandarkan. */
    private const DIKECUALIKAN = ['ocr_sementara'];

    protected $signature = 'sandaran:jalan
                            {--simpan= : Bilangan hari sandaran disimpan (lalai: SANDARAN_SIMPAN_HARI)}';

    protected $description = 'Sandarkan pangkalan data dan fail imbasan ke dalam satu fail zip';

    public function handle(): int
    {
        $folder = rtrim((string) config('services.sandaran.folder'), '\\/');
        $salinan = config('services.sandaran.salinan') ? rtrim((string) config('services.sandaran.salinan'), '\\/') : null;
        $simpanHari = (int) ($this->option('simpan') ?? config('services.sandaran.simpan_hari', 14));
        if ($simpanHari < 1) {
            $this->error('Bilangan hari simpanan mesti sekurang-kurangnya 1.');

            return self::FAILURE;
        }

        $mula = microtime(true);
        $nama = 'sandaran_' . now()->format('Ymd_His') . '.zip';
        $sql = null;

        try {
            File::ensureDirectoryExists($folder);
            $sql = $this->dumpPangkalanData($folder);
            $bilFail = $this->bungkus("{$folder}/{$nama}", $sql);

            $disalin = false;
            if ($salinan) {
                File::ensureDirectoryExists($salinan);
                File::copy("{$folder}/{$nama}", "{$salinan}/{$nama}");
                $disalin = true;
            }

            $dipadam = $this->padamLama($folder, $simpanHari) + ($salinan ? $this->padamLama($salinan, $simpanHari) : 0);
        } catch (\Throwable $e) {
            Log::error('Sandaran gagal: ' . $e->getMessage());
            $this->tulisStatus($folder, ['berjaya' => false, 'masa' => now()->toDateTimeString(), 'ralat' => $e->getMessage()]);
            $this->error('Sandaran gagal: ' . $e->getMessage());
            Notifikasi::amaranSistem('Sandaran gagal', 'Sandaran pangkalan data & fail imbasan gagal: ' . $e->getMessage());

            return self::FAILURE;
        } finally {
            if ($sql && File::exists($sql)) {
                File::delete($sql);
            }
        }

        $saiz = File::size("{$folder}/{$nama}");
        $tempoh = round(microtime(true) - $mula, 1);
        $this->tulisStatus($folder, [
            'berjaya' => true,
            'masa' => now()->toDateTimeString(),
            'fail' => $nama,
            'saiz' => $saiz,
            'bil_fail' => $bilFail,
            'salinan' => $disalin ? $salinan : null,
            'tempoh_saat' => $tempoh,
        ]);
        Log::info("Sandaran berjaya: {$nama} (" . number_format($saiz / 1048576, 1) . " MB, {$bilFail} fail imbasan).");

        $this->info("Sandaran berjaya: {$folder}/{$nama}");
        $this->line(sprintf('Saiz %.1f MB, %d fail imbasan, %.1f saat.', $saiz / 1048576, $bilFail, $tempoh));
        if ($disalin) {
            $this->line("Salinan: {$salinan}/{$nama}");
        }
        if ($dipadam) {
            $this->line("{$dipadam} sandaran lebih lama daripada {$simpanHari} hari dipadam.");
        }

        return self::SUCCESS;
    }

    /** mysqldump ke fail .sql sementara dalam folder sandaran. */
    private function dumpPangkalanData(string $folder): string
    {
        $db = config('database.connections.' . config('database.default'));
        if (! in_array($db['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new \RuntimeException('Sandaran hanya menyokong pangkalan data MySQL/MariaDB.');
        }

        $sql = "{$folder}/{$db['database']}_" . now()->format('Ymd_His') . '.sql';
        $hasil = Process::timeout((int) config('services.sandaran.had_masa', 900))
            // Kata laluan melalui persekitaran, bukan baris arahan (tidak kelihatan dalam senarai proses)
            ->env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])
            ->run([
                config('services.sandaran.mysqldump_bin', 'mysqldump'),
                '--host=' . $db['host'],
                '--port=' . $db['port'],
                '--user=' . $db['username'],
                '--single-transaction',
                '--routines',
                '--triggers',
                '--default-character-set=utf8mb4',
                '--result-file=' . $sql,
                $db['database'],
            ]);

        if ($hasil->failed() || ! File::exists($sql) || File::size($sql) === 0) {
            throw new \RuntimeException('mysqldump gagal: ' . trim($hasil->errorOutput() ?: $hasil->output() ?: 'tiada output'));
        }

        return $sql;
    }

    /** Zip fail .sql + semua fail disk `local` (kecuali folder sementara). Pulangkan bilangan fail imbasan. */
    private function bungkus(string $zip, string $sql): int
    {
        $arkib = new ZipArchive;
        if ($arkib->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Fail zip tidak dapat dicipta: {$zip}");
        }

        $arkib->addFile($sql, 'pangkalan_data/' . preg_replace('/_\d{8}_\d{6}(?=\.sql$)/', '', basename($sql)));

        $disk = Storage::disk('local');
        $bil = 0;
        foreach ($disk->allFiles() as $fail) {
            if (in_array(strtok($fail, '/'), self::DIKECUALIKAN, true) || basename($fail) === '.gitignore') {
                continue;
            }
            $arkib->addFile($disk->path($fail), 'fail/' . $fail);
            $bil++;
        }

        if (! $arkib->close()) {
            throw new \RuntimeException("Fail zip gagal ditulis: {$zip}");
        }

        return $bil;
    }

    /** Padam sandaran_*.zip yang lebih lama daripada $hari hari. */
    private function padamLama(string $folder, int $hari): int
    {
        $had = now()->subDays($hari)->getTimestamp();
        $bil = 0;
        foreach (File::glob("{$folder}/sandaran_*.zip") as $fail) {
            if (File::lastModified($fail) < $had) {
                File::delete($fail);
                $bil++;
            }
        }

        return $bil;
    }

    private function tulisStatus(string $folder, array $status): void
    {
        try {
            File::ensureDirectoryExists($folder);
            File::put("{$folder}/terakhir.json", json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
            // Status hanya maklumat tambahan; jangan tutup ralat sebenar
        }
    }
}
