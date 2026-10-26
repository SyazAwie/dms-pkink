<?php

namespace App\Support;

/**
 * Buang garisan NIPIS mendatar & menegak (kotak huruf, jadual, garis isian borang)
 * daripada imej sebelum OCR. Tesseract kerap membaca garisan kotak sebagai huruf
 * (cth. "S|Y|A|Z|M|I" → "SIYVIAIZIMITI"); selepas dibuang, borang berkotak naik
 * daripada 65% kepada 81% keyakinan dalam ujian (OcrService memilih hasil terbaik
 * antara imej asal dan imej ini, jadi dokumen lain tidak menjadi lebih teruk).
 *
 * Segmen gelap panjang hanya dibuang jika piksel sejauh "tebal" di kedua-dua sisinya
 * kebanyakannya cerah, supaya blok gelap tebal (cth. kepala surat berwarna) dan huruf
 * kekal. Ambang dikira untuk imej A4 300dpi (lebar ~2480px) dan diskala ikut lebar imej.
 *
 * Hanya guna GD (sedia ada dalam PHP). Peta piksel disimpan sebagai rentetan (1 bait
 * setiap piksel, ~9MB bagi A4 300dpi), bukan tatasusunan PHP yang memerlukan ratusan MB.
 */
final class BuangGarisan
{
    private const LEBAR_RUJUKAN = 2480;
    private const AMBANG_GELAP = 140;      // kecerahan 0-255; lebih rendah = piksel gelap
    private const MIN_MENDATAR = 120;      // panjang minimum garisan mendatar (px @ 300dpi)
    private const MIN_MENEGAK = 70;        // panjang minimum garisan menegak (lebih pendek: sisi kotak huruf)
    private const TEBAL = 6;               // jarak semakan sisi; garisan lebih tebal dikekalkan
    private const NISBAH_SISI_GELAP = 0.3;

    /** Imej lebih besar dilangkau (OCR guna imej asal sahaja); A4 300dpi ~8.7 juta piksel. */
    private const MAKS_PIKSEL = 25_000_000;

    /** Anggaran memori GD + peta bagi setiap piksel (diukur ~12 bait/piksel; termasuk ruang selamat). */
    private const BAIT_SETIAP_PIKSEL = 16;

    /**
     * Tulis imej tanpa garisan ke $keluar (PNG). False jika imej tidak dapat dibaca atau
     * terlalu besar. Had memori PHP dinaikkan sementara (A4 300dpi perlu ~120MB, melebihi
     * memory_limit lalai 128MB bersama permintaan) dan dipulihkan selepas itu.
     */
    public static function proses(string $masuk, string $keluar): bool
    {
        $saiz = @getimagesize($masuk);
        if ($saiz === false || $saiz[0] * $saiz[1] > self::MAKS_PIKSEL) {
            return false;
        }

        $hadAsal = ini_get('memory_limit');
        $perlu = memory_get_usage(true) + $saiz[0] * $saiz[1] * self::BAIT_SETIAP_PIKSEL + 32 * 1048576;
        if ($hadAsal !== '-1' && self::keBait($hadAsal) < $perlu) {
            ini_set('memory_limit', (string) $perlu);
        }

        try {
            return self::buang($masuk, $keluar);
        } finally {
            ini_set('memory_limit', $hadAsal);
        }
    }

    private static function keBait(string $had): int
    {
        $nilai = (int) $had;

        return match (strtoupper(substr(trim($had), -1))) {
            'G' => $nilai * 1073741824,
            'M' => $nilai * 1048576,
            'K' => $nilai * 1024,
            default => $nilai,
        };
    }

    private static function buang(string $masuk, string $keluar): bool
    {
        $im = @imagecreatefromstring((string) @file_get_contents($masuk));
        if ($im === false) {
            return false;
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        imagefilter($im, IMG_FILTER_GRAYSCALE);

        $w = imagesx($im);
        $h = imagesy($im);
        $skala = max(0.3, $w / self::LEBAR_RUJUKAN);
        $minH = (int) round(self::MIN_MENDATAR * $skala);
        $minV = (int) round(self::MIN_MENEGAK * $skala);
        $tebal = max(2, (int) round(self::TEBAL * $skala));

        $peta = str_repeat("\0", $w * $h);
        for ($y = 0; $y < $h; $y++) {
            $o = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                if ((imagecolorat($im, $x, $y) & 0xFF) < self::AMBANG_GELAP) {
                    $peta[$o + $x] = "\1";
                }
            }
        }
        $gelap = fn (int $x, int $y): bool => $x >= 0 && $y >= 0 && $x < $w && $y < $h && $peta[$y * $w + $x] === "\1";

        $segmen = [];
        // Mendatar
        for ($y = 0; $y < $h; $y++) {
            $o = $y * $w;
            $run = 0;
            for ($x = 0; $x <= $w; $x++) {
                if ($x < $w && $peta[$o + $x] === "\1") {
                    $run++;
                    continue;
                }
                if ($run >= $minH) {
                    $sisi = 0;
                    for ($i = $x - $run; $i < $x; $i++) {
                        $sisi += $gelap($i, $y - $tebal) || $gelap($i, $y + $tebal);
                    }
                    if ($sisi / $run < self::NISBAH_SISI_GELAP) {
                        $segmen[] = [$x - $run, $y, $x - 1, $y];
                    }
                }
                $run = 0;
            }
        }
        // Menegak
        for ($x = 0; $x < $w; $x++) {
            $run = 0;
            for ($y = 0; $y <= $h; $y++) {
                if ($y < $h && $peta[$y * $w + $x] === "\1") {
                    $run++;
                    continue;
                }
                if ($run >= $minV) {
                    $sisi = 0;
                    for ($i = $y - $run; $i < $y; $i++) {
                        $sisi += $gelap($x - $tebal, $i) || $gelap($x + $tebal, $i);
                    }
                    if ($sisi / $run < self::NISBAH_SISI_GELAP) {
                        $segmen[] = [$x, $y - $run, $x, $y - 1];
                    }
                }
                $run = 0;
            }
        }

        $putih = imagecolorallocate($im, 255, 255, 255);
        foreach ($segmen as [$x0, $y0, $x1, $y1]) {
            imageline($im, $x0, $y0, $x1, $y1, $putih);
        }

        $ok = imagepng($im, $keluar, 1);
        imagedestroy($im);

        return $ok;
    }
}
