<?php

namespace App\Support;

use App\Models\DokumenField;

/**
 * Kedudukan medan atas templat borang (TB6), disimpan dalam templat_borang.kedudukan_medan.
 *
 * Koordinat ialah pecahan halaman (0–1) dari bucu kiri atas, jadi tidak bergantung pada
 * resolusi imej. Satu "kotak" = {muka, x, y, w, h} (+ saiz fon pilihan, + tanpa_unit: medan wang
 * ditulis tanpa "RM" kerana borang sudah mencetaknya).
 *
 *   {
 *     "disahkan": true,                            false = diwarisi versi lama, belum disimpan pentadbir
 *     "medan": {
 *       "<dokumen_field_id>": {
 *         "kotak":   {kotak}                       teks/nombor/tarikh/textarea…, atau nilai pilihan sebagai teks
 *         "pilihan": {"<pilihan>": {kotak}, …}     radio/dropdown/kotak semak/ya-tidak ("__lain__" = Lain-lain)
 *         "gaya":    "tanda" | "potong"            ✓ pada pilihan dipilih, atau garis pada pilihan TIDAK dipilih
 *         "baris":   {kotak, bilangan}             jadual: baris pertama (y & h = tinggi setiap baris),
 *                                                  bilangan = baris yang ada pada borang (lebihan → lampiran)
 *         "lajur":   {"<kod lajur>": {kotak}, …}   jadual: x & w setiap lajur
 *       }
 *     },
 *     "asas": {"tarikh_dokumen"|"no_rujukan"|"perkara": {kotak}},   medan asas dokumen (bukan EAV)
 *     "cop": {kotak}                               cop status + QR
 *   }
 *
 * bersihkan() menerima input editor (tidak dipercayai) dan hanya menyimpan medan, pilihan
 * dan lajur yang wujud dalam kategori.
 */
final class KedudukanMedan
{
    public const GAYA = ['tanda', 'potong'];

    /** Medan asas dokumen (bukan EAV) yang boleh diletak, cth. "Tarikh:" yang ada pada kebanyakan borang. */
    public const ASAS = ['tarikh_dokumen' => 'Tarikh Dokumen', 'no_rujukan' => 'No. Rujukan', 'perkara' => 'Perkara'];

    public const MAKS_MUKA = 20;

    public const SAIZ_MIN = 6;

    public const SAIZ_MAKS = 20;

    /**
     * @param  iterable<DokumenField>  $medanKategori
     */
    public static function bersihkan(mixed $input, iterable $medanKategori, int $bilanganMuka = 1): array
    {
        $input = is_array($input) ? $input : [];
        $hadMuka = max(1, min(self::MAKS_MUKA, $bilanganMuka));
        // Disimpan oleh pentadbir = disahkan (susunan diwarisi versi lama bermula false, lihat TemplatBorangService::daftar)
        $hasil = ['disahkan' => true, 'medan' => []];

        foreach ($medanKategori as $medan) {
            $data = $input['medan'][$medan->dokumen_field_id] ?? $input['medan'][(string) $medan->dokumen_field_id] ?? null;
            if (! is_array($data) || ! JenisMedan::adaNilai($medan)) {
                continue;
            }
            $bersih = [];
            if ($medan->jenis_data === 'jadual') {
                $baris = self::kotak($data['baris'] ?? null, $hadMuka);
                $lajur = [];
                foreach (MedanJadual::lajur($medan) as $l) {
                    if ($k = self::kotak($data['lajur'][$l['kod']] ?? null, $hadMuka)) {
                        $lajur[$l['kod']] = $k;
                    }
                }
                if ($baris && $lajur) {
                    // Bilangan baris yang ada pada borang; baris lebihan dicetak pada lampiran
                    $bilangan = $data['baris']['bilangan'] ?? null;
                    $baris['bilangan'] = is_numeric($bilangan) ? max(1, min(MedanJadual::MAKS_BARIS, (int) $bilangan)) : 1;
                    $bersih = ['baris' => $baris, 'lajur' => $lajur];
                }
            } else {
                if ($k = self::kotak($data['kotak'] ?? null, $hadMuka)) {
                    $bersih['kotak'] = $k;
                }
                $pilihan = [];
                foreach (self::pilihanMedan($medan) as $p) {
                    if ($k = self::kotak($data['pilihan'][$p] ?? null, $hadMuka)) {
                        $pilihan[$p] = $k;
                    }
                }
                if ($pilihan) {
                    $bersih['pilihan'] = $pilihan;
                    $bersih['gaya'] = in_array($data['gaya'] ?? null, self::GAYA, true) ? $data['gaya'] : 'tanda';
                }
            }
            if ($bersih) {
                $hasil['medan'][(string) $medan->dokumen_field_id] = $bersih;
            }
        }

        foreach (array_keys(self::ASAS) as $kunci) {
            if ($k = self::kotak($input['asas'][$kunci] ?? null, $hadMuka)) {
                $hasil['asas'][$kunci] = $k;
            }
        }

        if ($cop = self::kotak($input['cop'] ?? null, $hadMuka)) {
            $hasil['cop'] = $cop;
        }

        return $hasil;
    }

    /** Pilihan yang boleh diletak bagi satu medan (termasuk "__lain__" jika ada Lain-lain). */
    public static function pilihanMedan(DokumenField $medan): array
    {
        if ($medan->jenis_data === 'ya_tidak') {
            return JenisMedan::YA_TIDAK;
        }
        if (! in_array($medan->jenis_data, JenisMedan::ADA_PILIHAN, true)) {
            return [];
        }
        $pilihan = array_values(array_filter($medan->pilihan ?? [], 'is_string'));
        if (JenisMedan::adaLain($medan)) {
            $pilihan[] = JenisMedan::NILAI_LAIN;
        }

        return $pilihan;
    }

    /**
     * Templat boleh digunakan untuk borang siap: ada sekurang-kurangnya satu medan diletak dan
     * susunan sudah disahkan (disimpan) oleh pentadbir. Susunan yang diwarisi daripada versi
     * lama (`disahkan: false`) tidak digunakan sehingga disemak & disimpan, kerana borang
     * versi baharu mungkin berubah susun atur.
     */
    public static function adaMedan(?array $kedudukan): bool
    {
        return (! empty($kedudukan['medan']) || ! empty($kedudukan['asas'])) && ($kedudukan['disahkan'] ?? true) !== false;
    }

    /** Susunan diwarisi daripada versi lama dan belum disimpan semula oleh pentadbir. */
    public static function perluSemak(?array $kedudukan): bool
    {
        return ($kedudukan['disahkan'] ?? true) === false && self::bilangan($kedudukan) > 0;
    }

    /** Bilangan medan (EAV + asas) yang diletak. */
    public static function bilangan(?array $kedudukan): int
    {
        return count($kedudukan['medan'] ?? []) + count($kedudukan['asas'] ?? []);
    }

    /** Satu kotak dalam had halaman; null jika tidak sah. */
    private static function kotak(mixed $k, int $hadMuka): ?array
    {
        if (! is_array($k)) {
            return null;
        }
        foreach (['x', 'y', 'w', 'h'] as $kunci) {
            if (! isset($k[$kunci]) || ! is_numeric($k[$kunci]) || ! is_finite((float) $k[$kunci])) {
                return null;
            }
        }
        $x = min(max((float) $k['x'], 0), 0.99);
        $y = min(max((float) $k['y'], 0), 0.99);
        $w = min(max((float) $k['w'], 0.005), 1 - $x);
        $h = min(max((float) $k['h'], 0.005), 1 - $y);
        $muka = is_numeric($k['muka'] ?? null) ? (int) $k['muka'] : 1;
        if ($muka < 1 || $muka > $hadMuka) {
            return null;
        }
        $hasil = ['muka' => $muka, 'x' => round($x, 4), 'y' => round($y, 4), 'w' => round($w, 4), 'h' => round($h, 4)];
        if (is_numeric($k['saiz'] ?? null)) {
            $hasil['saiz'] = (float) min(max((float) $k['saiz'], self::SAIZ_MIN), self::SAIZ_MAKS);
        }
        // Medan wang: borang sudah mencetak "RM" sebelum ruang isian, jadi tulis nombor sahaja
        if (($k['tanpa_unit'] ?? false) === true) {
            $hasil['tanpa_unit'] = true;
        }

        return $hasil;
    }
}
