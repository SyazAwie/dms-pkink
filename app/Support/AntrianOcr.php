<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

/**
 * Queue OCR/AI (T2). Satu worker (`php artisan queue:work --queue=ocr`, dijalankan oleh
 * skrip/worker-ocr.cmd melalui Task Scheduler) memproses kerja satu demi satu, kerana GPU
 * 6GB hanya muat satu model pada satu masa.
 *
 * Jadual `jobs` sentiasa dalam DB sebenar (sambungan `antrian`, disalin daripada sambungan
 * lalai SEBELUM mod demo menukarnya, lihat AppServiceProvider), supaya worker konsol dan
 * permintaan web mod demo berkongsi satu giliran. Setiap kerja membawa mod demo asalnya.
 *
 * Degupan worker disimpan dalam fail (bukan cache/DB, yang bertukar ikut mod demo).
 * Jika worker tidak berdegup, kerja dijalankan segerak dalam permintaan seperti sebelum T2,
 * jadi tiada apa yang rosak bila worker mati.
 */
class AntrianOcr
{
    public const NAMA = 'ocr';

    /** Worker dianggap hidup jika berdegup dalam tempoh ini semasa menunggu kerja (saat). */
    private const DEGUPAN_SAAT = 30;

    public static function failDegupan(): string
    {
        return config('queue.ocr_degupan') ?: storage_path('framework/antrian-ocr.json');
    }

    /** Dipanggil oleh pendengar acara worker (AppServiceProvider). */
    public static function degup(bool $sibuk): void
    {
        // Tulis ke fail sementara kemudian rename (atomik): pembaca tidak pernah melihat
        // fail separuh ditulis dan menganggap worker mati.
        $fail = self::failDegupan();
        $sementara = $fail.'.'.getmypid().'.tmp';
        try {
            File::put($sementara, json_encode(['masa' => time(), 'sibuk' => $sibuk, 'pid' => getmypid()]));
            if (! @rename($sementara, $fail)) {
                File::put($fail, (string) File::get($sementara));
                @unlink($sementara);
            }
        } catch (\Throwable $e) {
            Log::warning('Degupan worker OCR gagal ditulis: '.$e->getMessage());
        }
    }

    /** @return array{masa: int, sibuk: bool, pid: int|null}|null */
    public static function degupan(): ?array
    {
        $data = json_decode((string) @file_get_contents(self::failDegupan()), true);

        return is_array($data) && isset($data['masa'])
            ? ['masa' => (int) $data['masa'], 'sibuk' => (bool) ($data['sibuk'] ?? false), 'pid' => isset($data['pid']) ? (int) $data['pid'] : null]
            : null;
    }

    /** Had masa satu kerja OCR/AI (saat): selepas ini kerja dianggap tersangkut. */
    public static function hadKerja(): int
    {
        return (int) config('services.ocr.had_masa', 300) * 2 + 60;
    }

    /**
     * Worker sedang berjalan: berdegup baru-baru ini, atau sedang memproses satu kerja
     * (tiada degupan semasa kerja panjang) dalam had masa kerja DAN prosesnya masih wujud
     * (worker yang dihentikan di tengah kerja tidak dianggap hidup sehingga had tamat).
     */
    public static function workerHidup(): bool
    {
        if (config('queue.default') === 'sync') {
            return false;
        }
        $d = self::degupan();
        if (! $d) {
            return false;
        }
        $umur = time() - $d['masa'];
        if ($umur <= self::DEGUPAN_SAAT) {
            return true;
        }

        return $d['sibuk'] && $umur <= self::hadKerja() && ($d['pid'] === null || self::prosesWujud($d['pid']));
    }

    /**
     * Proses PHP dengan PID ini masih berjalan (Windows: tasklist; Linux: /proc/{pid}, kemudian
     * posix_kill). /proc dahulu kerana worker mungkin dijalankan oleh pengguna sistem lain
     * daripada pelayan web: posix_kill() memulangkan false (EPERM) walaupun proses hidup.
     */
    public static function prosesWujud(int $pid): bool
    {
        static $cache = [];
        if (array_key_exists($pid, $cache)) {
            return $cache[$pid];
        }
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $hasil = Process::timeout(5)
                    ->run(['tasklist', '/FI', "PID eq {$pid}", '/NH', '/FO', 'CSV']);
                $ada = str_contains($hasil->output(), "\"{$pid}\"") && stripos($hasil->output(), 'php') !== false;
            } elseif (is_dir('/proc/1')) {
                $ada = is_dir("/proc/{$pid}");
            } elseif (function_exists('posix_kill')) {
                // EPERM (1) = proses wujud tetapi milik pengguna lain
                $ada = posix_kill($pid, 0) || (function_exists('posix_get_last_error') && posix_get_last_error() === 1);
            } else {
                $ada = true;
            }
        } catch (\Throwable) {
            $ada = true; // tidak dapat menyemak: percaya degupan
        }

        return $cache[$pid] = $ada;
    }

    /** Kerja $id masih dalam jadual jobs (belum siap). False jika tiada atau tidak dapat disemak. */
    public static function kerjaWujud(int $id): bool
    {
        try {
            return DB::connection(config('queue.connections.database.connection'))
                ->table(config('queue.connections.database.table', 'jobs'))->where('id', $id)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Hantar kerja ke queue OCR; pulangkan ID kerja (untuk kedudukan giliran) jika ada. */
    public static function hantar(object $kerja): ?int
    {
        $id = Queue::pushOn(self::NAMA, $kerja);

        return is_numeric($id) ? (int) $id : null;
    }

    /** Bilangan kerja OCR di hadapan kerja $id (belum siap, dihantar lebih awal). */
    public static function diHadapan(?int $id): ?int
    {
        if (! $id) {
            return null;
        }
        try {
            return DB::connection(config('queue.connections.database.connection'))
                ->table(config('queue.connections.database.table', 'jobs'))
                ->where('queue', self::NAMA)->where('id', '<', $id)->count();
        } catch (\Throwable) {
            return null;
        }
    }
}
