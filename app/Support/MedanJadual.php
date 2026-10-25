<?php

namespace App\Support;

use App\Models\DokumenField;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Jenis medan EAV "jadual": baris berulang dengan lajur yang ditetapkan admin
 * (cth. Bil / Jenis Peralatan / Kuantiti / Justifikasi dalam Borang Peralatan ICT).
 *
 * Tetapan dalam dokumen_field.tetapan:
 *   lajur  [{kod, nama, jenis, pilihan?}]  jenis: lihat JENIS_LAJUR
 *   baris  ["Emel", "Intranet", ...]        (pilihan) label baris tetap; tanpanya
 *                                           pengguna boleh tambah/buang baris
 *
 * Nilai disimpan sebagai senarai JSON objek, satu objek setiap baris yang diisi:
 *   [{"jenis":"Laptop","kuantiti":"2"}]  atau, dengan baris tetap,
 *   [{"_baris":"Emel","tarikh":"2026-10-25","catatan":"Siap"}]
 *
 * Setiap sel disahkan, disimpan dan dipaparkan dengan peraturan JenisMedan
 * yang sama seperti medan biasa (melalui medan "palsu" bagi setiap lajur).
 */
final class MedanJadual
{
    /** Jenis data yang dibenarkan bagi satu lajur jadual. */
    public const JENIS_LAJUR = [
        'teks' => 'Teks',
        'nombor' => 'Nombor',
        'wang' => 'Wang (RM)',
        'tarikh' => 'Tarikh',
        'masa' => 'Masa',
        'ya_tidak' => 'Ya / Tidak',
        'dropdown' => 'Dropdown',
    ];

    public const MAKS_BARIS = 100;
    public const MAKS_TEKS_SEL = 1000;

    /** Kunci label baris tetap dalam setiap objek baris. */
    public const KUNCI_BARIS = '_baris';

    /** @return array<int, array{kod: string, nama: string, jenis: string, pilihan?: array}> */
    public static function lajur(DokumenField $medan): array
    {
        return array_values(array_filter($medan->tetapan['lajur'] ?? [], fn ($l) => is_array($l) && isset($l['kod'], $l['nama'])));
    }

    /** @return array<int, string> */
    public static function barisTetap(DokumenField $medan): array
    {
        return array_values(array_filter($medan->tetapan['baris'] ?? [], 'is_string'));
    }

    /** Medan "palsu" bagi satu lajur supaya peraturan/simpanan/paparan JenisMedan boleh diguna semula. */
    public static function medanLajur(array $lajur): DokumenField
    {
        $medan = new DokumenField([
            'nama_field' => $lajur['nama'],
            'kod_field' => $lajur['kod'],
            'jenis_data' => $lajur['jenis'] ?? 'teks',
            'pilihan' => $lajur['pilihan'] ?? null,
            'is_required' => false,
        ]);
        $medan->dokumen_field_id = 0;

        return $medan;
    }

    public static function peraturan(DokumenField $medan): array
    {
        $kunci = "medan_data.{$medan->dokumen_field_id}";
        $utama = [$medan->is_required ? 'required' : 'nullable', 'array', 'max:' . self::MAKS_BARIS];
        if ($medan->is_required) {
            $utama[] = function (string $attribute, mixed $value, Closure $fail) use ($medan) {
                if (self::untukSimpan($medan, $value) === null) {
                    $fail("Sila isi sekurang-kurangnya satu baris {$medan->nama_field}.");
                }
            };
        }

        $hasil = [$kunci => $utama, "{$kunci}.*" => ['array']];
        foreach (self::lajur($medan) as $lajur) {
            $peraturan = JenisMedan::peraturan(self::medanLajur($lajur))['medan_data.0'];
            $peraturan[0] = 'nullable';
            if (($lajur['jenis'] ?? 'teks') === 'teks') {
                $peraturan[] = 'max:' . self::MAKS_TEKS_SEL;
            }
            $hasil["{$kunci}.*.{$lajur['kod']}"] = $peraturan;
        }
        if ($tetap = self::barisTetap($medan)) {
            $hasil["{$kunci}.*." . self::KUNCI_BARIS] = ['nullable', 'string', Rule::in($tetap)];
        }

        return $hasil;
    }

    /** Baris daripada borang (sudah divalidasi) → JSON simpanan; baris kosong dibuang. */
    public static function untukSimpan(DokumenField $medan, mixed $nilai): ?string
    {
        $lajur = self::lajur($medan);
        $tetap = self::barisTetap($medan);
        $hasil = [];

        foreach (is_array($nilai) ? $nilai : [] as $baris) {
            if (!is_array($baris)) {
                continue;
            }
            $sel = [];
            $adaNilai = false;
            foreach ($lajur as $l) {
                $v = JenisMedan::untukSimpan(self::medanLajur($l), $baris[$l['kod']] ?? null);
                $sel[$l['kod']] = $v;
                $adaNilai = $adaNilai || $v !== null;
            }
            if (!$adaNilai) {
                continue;
            }
            if ($tetap) {
                $label = (string) ($baris[self::KUNCI_BARIS] ?? '');
                if (!in_array($label, $tetap, true)) {
                    continue;
                }
                $sel = [self::KUNCI_BARIS => $label] + $sel;
            }
            $hasil[] = $sel;
        }

        return $hasil ? json_encode($hasil, JSON_UNESCAPED_UNICODE) : null;
    }

    /** JSON simpanan → baris untuk borang (lihat susunBaris). */
    public static function untukBorang(DokumenField $medan, ?string $nilai): array
    {
        $baris = json_decode((string) $nilai, true);

        return self::susunBaris($medan, is_array($baris) ? $baris : []);
    }

    /**
     * Baris untuk dipaparkan dalam borang: dengan baris tetap, satu baris bagi setiap
     * label (ikut susunan tetapan); tanpanya, baris sedia ada atau satu baris kosong.
     * Kunci baris dikekalkan (selepas baris dibuang, old() boleh ada kunci 0, 2, ...)
     * supaya nama input sepadan dengan kunci ralat validasi.
     */
    public static function susunBaris(DokumenField $medan, ?array $baris): array
    {
        $baris = array_filter($baris ?? [], 'is_array');

        if ($tetap = self::barisTetap($medan)) {
            $ikutLabel = [];
            foreach ($baris as $b) {
                $ikutLabel[(string) ($b[self::KUNCI_BARIS] ?? '')] = $b;
            }

            return array_map(fn ($label) => [self::KUNCI_BARIS => $label] + ($ikutLabel[$label] ?? []), $tetap);
        }

        return $baris ?: [[]];
    }

    public static function papar(DokumenField $medan, string $nilai): string
    {
        $lajur = self::lajur($medan);
        $baris = json_decode($nilai, true);
        if (!is_array($baris) || !$lajur) {
            return e($nilai);
        }
        $tetap = (bool) self::barisTetap($medan);

        $html = '<div class="medan-jadual-wrap"><table class="medan-jadual-papar"><thead><tr>';
        $html .= '<th>' . ($tetap ? '' : 'Bil') . '</th>';
        foreach ($lajur as $l) {
            $html .= '<th>' . e($l['nama']) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach (array_values(array_filter($baris, 'is_array')) as $i => $b) {
            $html .= '<tr><td class="medan-jadual-label">' . ($tetap ? e((string) ($b[self::KUNCI_BARIS] ?? '')) : $i + 1) . '</td>';
            foreach ($lajur as $l) {
                $sel = $b[$l['kod']] ?? null;
                $html .= '<td>' . (is_scalar($sel) ? (string) JenisMedan::papar(self::medanLajur($l), (string) $sel) : '') . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table></div>';
    }

    public static function panduanAi(DokumenField $medan): string
    {
        $kunci = array_map(
            fn ($l) => '"' . $l['kod'] . '" (' . $l['nama'] . ': ' . JenisMedan::panduanAi(self::medanLajur($l)) . ')',
            self::lajur($medan)
        );
        $panduan = 'senarai JSON objek, satu objek bagi setiap baris jadual, dengan kunci ' . implode(', ', $kunci);
        if ($tetap = self::barisTetap($medan)) {
            $panduan .= '; setiap objek juga ada kunci "' . self::KUNCI_BARIS . '" dengan salah satu label baris [' . implode(', ', $tetap) . ']';
        }

        return $panduan . '; pulangkan [] jika jadual kosong';
    }

    /** Baris cadangan AI → JSON simpanan; sel tidak sah menjadi kosong. */
    public static function bersihkanAi(DokumenField $medan, mixed $nilai): ?string
    {
        if (is_string($nilai)) {
            $nilai = json_decode($nilai, true);
        }
        if (!is_array($nilai)) {
            return null;
        }
        // Satu objek (bukan senarai) dianggap satu baris
        if ($nilai !== [] && !array_is_list($nilai)) {
            $nilai = [$nilai];
        }

        $labelKecil = [];
        foreach (self::barisTetap($medan) as $label) {
            $labelKecil[mb_strtolower($label)] = $label;
        }

        $baris = [];
        foreach ($nilai as $b) {
            if (!is_array($b)) {
                continue;
            }
            $bersih = [];
            foreach (self::lajur($medan) as $l) {
                $bersih[$l['kod']] = JenisMedan::bersihkanAi(self::medanLajur($l), $b[$l['kod']] ?? $b[$l['nama']] ?? null);
            }
            if ($labelKecil) {
                $label = mb_strtolower(trim((string) ($b[self::KUNCI_BARIS] ?? '')));
                $bersih[self::KUNCI_BARIS] = $labelKecil[$label] ?? null;
            }
            $baris[] = $bersih;
        }

        return self::untukSimpan($medan, $baris);
    }

    /**
     * Tetapan jadual daripada satu baris pembina medan (Kategori Dokumen).
     * Kod lajur sedia ada dikekalkan (data lama merujuk kod); lajur baharu dapat
     * kod daripada nama.
     */
    public static function tetapanDaripadaPembina(array $baris): array
    {
        $lajur = [];
        $kodDiguna = [];
        foreach (array_values(is_array($baris['lajur'] ?? null) ? $baris['lajur'] : []) as $l) {
            $nama = trim((string) ($l['nama'] ?? ''));
            if ($nama === '') {
                continue;
            }
            $kodAsas = Str::slug((string) (($l['kod'] ?? '') ?: $nama), '_') ?: 'lajur';
            $kod = $kodAsas;
            for ($n = 2; in_array($kod, $kodDiguna, true); $n++) {
                $kod = "{$kodAsas}_{$n}";
            }
            $kodDiguna[] = $kod;

            $jenis = array_key_exists($l['jenis'] ?? '', self::JENIS_LAJUR) ? $l['jenis'] : 'teks';
            $item = ['kod' => $kod, 'nama' => $nama, 'jenis' => $jenis];
            if ($jenis === 'dropdown') {
                $pilihan = is_array($l['pilihan'] ?? null) ? $l['pilihan'] : explode(',', (string) ($l['pilihan'] ?? ''));
                $item['pilihan'] = array_values(array_filter(array_map('trim', $pilihan), fn ($p) => $p !== ''));
            }
            $lajur[] = $item;
        }

        $tetap = is_array($baris['baris_tetap'] ?? null) ? $baris['baris_tetap'] : explode(',', (string) ($baris['baris_tetap'] ?? ''));
        $tetap = array_values(array_unique(array_filter(array_map('trim', $tetap), fn ($t) => $t !== '')));

        return array_filter(['lajur' => $lajur, 'baris' => $tetap]);
    }
}
