<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Keadaan kecemasan (Fasa Keselamatan S3) dalam storage/app/private/keselamatan.json:
 * mod kecemasan dan penanda "log keluar paksa". Fail, bukan cache, supaya `cache:clear`
 * tidak mematikan mod kecemasan atau menghidupkan semula sesi yang telah ditamatkan.
 *
 * Log keluar paksa: sesi yang bermula (masa_log_masuk) SEBELUM penanda ditamatkan oleh
 * PengawalKeselamatan; berfungsi untuk mana-mana SESSION_DRIVER.
 */
class KeadaanKeselamatan
{
    private static ?array $cache = null;

    public static function laluan(): string
    {
        return (string) config('services.keselamatan.fail_keadaan', storage_path('app/private/keselamatan.json'));
    }

    public static function baca(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $data = File::exists(self::laluan()) ? json_decode(File::get(self::laluan()), true) : null;

        return self::$cache = is_array($data) ? $data : [];
    }

    private static function tulis(array $data): void
    {
        File::ensureDirectoryExists(dirname(self::laluan()));
        File::put(self::laluan(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), true);
        self::$cache = $data;
    }

    public static function kosongkanCache(): void
    {
        self::$cache = null;
    }

    // ---- Mod kecemasan ----

    public static function kecemasan(): ?array
    {
        $k = self::baca()['kecemasan'] ?? null;

        return ($k['aktif'] ?? false) ? $k : null;
    }

    public static function hidupkanKecemasan(?string $oleh, ?string $sebab): void
    {
        $data = self::baca();
        $data['kecemasan'] = ['aktif' => true, 'oleh' => $oleh, 'sebab' => $sebab, 'sejak' => now()->toIso8601String()];
        self::tulis($data);
    }

    public static function matikanKecemasan(): void
    {
        $data = self::baca();
        unset($data['kecemasan']);
        self::tulis($data);
    }

    // ---- Log keluar paksa ----

    public static function logKeluarPengguna(int $userId): void
    {
        $data = self::baca();
        $data['log_keluar_pengguna'][(string) $userId] = now()->timestamp;
        self::tulis($data);
    }

    public static function logKeluarSemua(): void
    {
        $data = self::baca();
        $data['log_keluar_semua'] = now()->timestamp;
        self::tulis($data);
    }

    /** Sesi pengguna yang bermula sebelum masa ini mesti ditamatkan (0 = tiada). */
    public static function sahSelepas(int $userId): int
    {
        $data = self::baca();

        return max((int) ($data['log_keluar_semua'] ?? 0), (int) ($data['log_keluar_pengguna'][(string) $userId] ?? 0));
    }
}
