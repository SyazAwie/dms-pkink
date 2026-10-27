<?php

namespace App\Support;

/**
 * Penulis CSV untuk eksport (senarai dokumen, log audit): UTF-8 dengan BOM supaya terus
 * dibuka betul dalam Excel, dan setiap sel dilindungi daripada suntikan formula.
 */
class Csv
{
    /** @param resource $keluar */
    public static function bom($keluar): void
    {
        fwrite($keluar, "\xEF\xBB\xBF");
    }

    /** @param resource $keluar */
    public static function baris($keluar, array $sel): void
    {
        fputcsv($keluar, array_map(self::sel(...), $sel));
    }

    /**
     * Teks pengguna bermula dengan = + - @ (atau tab/CR) ditafsir sebagai formula oleh
     * Excel; awalkan dengan ' supaya dipapar sebagai teks (amalan OWASP).
     */
    public static function sel(mixed $nilai): string
    {
        $teks = (string) $nilai;

        return $teks !== '' && in_array($teks[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$teks : $teks;
    }
}
