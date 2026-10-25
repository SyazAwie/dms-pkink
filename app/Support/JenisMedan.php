<?php

namespace App\Support;

use App\Models\DokumenField;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

/**
 * Sumber tunggal bagi jenis data medan EAV (dokumen_field.jenis_data).
 *
 * Setiap jenis ditentukan di satu tempat:
 * - peraturan validasi (borang muat naik, edit, Imbas & Arkib)
 * - bentuk simpanan dalam dokumen_data.nilai_data (sentiasa teks)
 * - format paparan di halaman Butiran Dokumen
 * - panduan & pembersihan nilai cadangan AI (OllamaExtractionService)
 * - label dalam pembina medan Kategori Dokumen
 *
 * Tetapan tambahan setiap medan ada dalam dokumen_field.tetapan (JSON):
 *   bantuan  teks bantuan di bawah label (bagi seksyen: keterangan seksyen)
 *   lain     true = tambah pilihan "Lain-lain (nyatakan)" (dropdown/radio/kotak semak)
 *   tunjuk_jika  {kod, nilai}: medan hanya dipaparkan (dan disahkan/disimpan) jika medan
 *                berkod `kod` bernilai `nilai` (lihat kelihatan() dan medan-skrip)
 *
 * Kotak semak disimpan sebagai senarai JSON, cth. ["Emel","Lain-lain: Intranet"].
 * Jadual (baris berulang) diurus oleh App\Support\MedanJadual.
 *
 * Input borang dirender oleh satu partial: resources/views/dokumen/partials/medan-input.blade.php
 */
final class JenisMedan
{
    /** kod jenis_data => label dalam pembina medan (ikut susunan paparan) */
    public const SENARAI = [
        'teks' => 'Teks',
        'textarea' => 'Textarea Panjang',
        'nombor' => 'Nombor',
        'wang' => 'Wang (RM)',
        'tarikh' => 'Tarikh',
        'masa' => 'Masa',
        'tarikh_masa' => 'Tarikh & Masa',
        'emel' => 'Emel',
        'telefon' => 'No. Telefon / Faks',
        'url' => 'Pautan (URL)',
        'dropdown' => 'Dropdown',
        'radio' => 'Pilihan Tunggal (Radio)',
        'kotak_semak' => 'Kotak Semak (Banyak Pilihan)',
        'ya_tidak' => 'Ya / Tidak',
        'jadual' => 'Jadual (Baris Berulang)',
        'seksyen' => 'Tajuk Seksyen (tiada nilai)',
    ];

    /** Jenis yang menggunakan senarai `pilihan` (dan boleh ada "Lain-lain"). */
    public const ADA_PILIHAN = ['dropdown', 'radio', 'kotak_semak'];

    /** Jenis paparan sahaja: tiada input, tiada nilai disimpan, tidak dihantar kepada AI. */
    public const TANPA_NILAI = ['seksyen'];

    /** Nilai dalaman pilihan "Lain-lain (nyatakan)" dalam borang (tidak disimpan). */
    public const NILAI_LAIN = '__lain__';

    /** Awalan nilai tersimpan bagi jawapan "Lain-lain". */
    public const AWALAN_LAIN = 'Lain-lain: ';

    public const YA_TIDAK = ['Ya', 'Tidak'];

    private const REGEX_TELEFON = '/^\+?[0-9][0-9\s\-()]{5,19}$/';

    public static function kod(): array
    {
        return array_keys(self::SENARAI);
    }

    public static function adaLain(DokumenField $medan): bool
    {
        return in_array($medan->jenis_data, self::ADA_PILIHAN, true) && !empty($medan->tetapan['lain']);
    }

    /** False bagi jenis paparan sahaja (tajuk seksyen). */
    public static function adaNilai(DokumenField $medan): bool
    {
        return !in_array($medan->jenis_data, self::TANPA_NILAI, true);
    }

    /** Jenis yang boleh menjadi sumber syarat "tunjuk jika" (nilai daripada senarai tetap). */
    public const SUMBER_SYARAT = ['dropdown', 'radio', 'kotak_semak', 'ya_tidak'];

    /** @return array{kod: string, nilai: string}|null */
    public static function syarat(DokumenField $medan): ?array
    {
        $syarat = $medan->tetapan['tunjuk_jika'] ?? null;
        if (!is_array($syarat) || empty($syarat['kod']) || !isset($syarat['nilai']) || $syarat['nilai'] === '') {
            return null;
        }

        return ['kod' => (string) $syarat['kod'], 'nilai' => (string) $syarat['nilai']];
    }

    /**
     * Adakah medan ini dipaparkan bagi nilai borang semasa? Medan tanpa syarat, atau
     * yang medan sumbernya tiada lagi, sentiasa dipaparkan. Medan tersembunyi tidak
     * divalidasi dan nilainya tidak disimpan (sama seperti di pelayar).
     *
     * @param iterable<DokumenField> $medanSemua semua medan Jenis Dokumen yang sama
     * @param array $medanData input medan_data borang [dokumen_field_id => nilai]
     */
    public static function kelihatan(DokumenField $medan, iterable $medanSemua, array $medanData): bool
    {
        $syarat = self::syarat($medan);
        if ($syarat === null) {
            return true;
        }
        $sumber = collect($medanSemua)->firstWhere('kod_field', $syarat['kod']);
        if ($sumber === null || $sumber->dokumen_field_id === $medan->dokumen_field_id) {
            return true;
        }

        $nilai = $medanData[$sumber->dokumen_field_id] ?? null;

        return is_array($nilai)
            ? in_array($syarat['nilai'], array_map('strval', array_filter($nilai, 'is_scalar')), true)
            : (string) $nilai === $syarat['nilai'];
    }

    /**
     * Peraturan validasi bagi satu medan: [kunci input => peraturan].
     * Kunci utama medan_data.{id}; medan dengan "Lain-lain" juga ada medan_lain.{id}.
     */
    public static function peraturan(DokumenField $medan): array
    {
        if (!self::adaNilai($medan)) {
            return [];
        }
        if ($medan->jenis_data === 'jadual') {
            return MedanJadual::peraturan($medan);
        }

        $id = $medan->dokumen_field_id;
        $kunci = "medan_data.{$id}";
        $peraturan = [$medan->is_required ? 'required' : 'nullable'];
        $hasil = [];

        switch ($medan->jenis_data) {
            case 'nombor':
                $peraturan[] = 'numeric';
                break;
            case 'wang':
                $peraturan[] = 'numeric';
                $peraturan[] = 'decimal:0,2';
                $peraturan[] = 'min:0';
                break;
            case 'tarikh':
                $peraturan[] = 'date';
                break;
            case 'masa':
                $peraturan[] = 'date_format:H:i';
                break;
            case 'tarikh_masa':
                $peraturan[] = 'date_format:Y-m-d\TH:i,Y-m-d H:i';
                break;
            case 'emel':
                $peraturan[] = 'email';
                $peraturan[] = 'max:255';
                break;
            case 'telefon':
                $peraturan[] = 'regex:' . self::REGEX_TELEFON;
                break;
            case 'url':
                $peraturan[] = 'url:http,https';
                $peraturan[] = 'max:2048';
                break;
            case 'dropdown':
            case 'radio':
                $peraturan[] = Rule::in(self::pilihanDibenarkan($medan));
                break;
            case 'kotak_semak':
                $peraturan[] = 'array';
                $hasil["{$kunci}.*"] = ['string', Rule::in(self::pilihanDibenarkan($medan))];
                break;
            case 'ya_tidak':
                $peraturan[] = Rule::in(self::YA_TIDAK);
                break;
            default:
                $peraturan[] = 'string';
                break;
        }

        $hasil = [$kunci => $peraturan] + $hasil;
        if (self::adaLain($medan)) {
            $hasil["medan_lain.{$id}"] = ['nullable', 'string', 'max:255', new LainDinyatakan($id, $medan->nama_field)];
        }

        return $hasil;
    }

    private static function pilihanDibenarkan(DokumenField $medan): array
    {
        $dibenarkan = $medan->pilihan ?? [];
        if (self::adaLain($medan)) {
            $dibenarkan[] = self::NILAI_LAIN;
        }

        return $dibenarkan;
    }

    /**
     * Senarai nilai kotak semak daripada nilai tersimpan (JSON). Nilai bukan JSON
     * (cth. jenis medan ditukar selepas data diisi) dianggap satu nilai.
     */
    public static function senaraiNilai(?string $nilai): array
    {
        if ($nilai === null || $nilai === '') {
            return [];
        }
        $senarai = json_decode($nilai, true);

        return is_array($senarai) ? array_values(array_filter($senarai, 'is_string')) : [$nilai];
    }

    /**
     * Nilai simpanan bagi satu medan daripada permintaan borang (sudah divalidasi).
     * Jika $medanSemua diberi, medan yang tersembunyi oleh syarat "tunjuk jika" → null.
     */
    public static function dariPermintaan(DokumenField $medan, Request $request, ?iterable $medanSemua = null): ?string
    {
        if ($medanSemua !== null && !self::kelihatan($medan, $medanSemua, (array) $request->input('medan_data', []))) {
            return null;
        }

        $id = $medan->dokumen_field_id;

        return self::untukSimpan($medan, $request->input("medan_data.{$id}"), $request->input("medan_lain.{$id}"));
    }

    /**
     * Tukar nilai yang telah disahkan kepada bentuk simpanan dokumen_data.nilai_data.
     * Kosong (null / '') dipulangkan sebagai null, iaitu "tiada nilai".
     */
    public static function untukSimpan(DokumenField $medan, mixed $nilai, mixed $nilaiLain = null): ?string
    {
        if (!self::adaNilai($medan) || $nilai === null || $nilai === '' || $nilai === []) {
            return null;
        }

        if ($medan->jenis_data === 'jadual') {
            return MedanJadual::untukSimpan($medan, $nilai);
        }

        if ($medan->jenis_data === 'kotak_semak') {
            $dipilih = (array) $nilai;
            // Ikut susunan senarai pilihan supaya nilai sama sentiasa disimpan sama (diff log audit)
            $hasil = array_values(array_filter($medan->pilihan ?? [], fn ($p) => in_array($p, $dipilih, true)));
            if (in_array(self::NILAI_LAIN, $dipilih, true)) {
                $hasil[] = self::AWALAN_LAIN . trim((string) $nilaiLain);
            }

            return $hasil ? json_encode($hasil, JSON_UNESCAPED_UNICODE) : null;
        }

        if ($nilai === self::NILAI_LAIN) {
            return self::AWALAN_LAIN . trim((string) $nilaiLain);
        }

        return match ($medan->jenis_data) {
            'wang' => number_format((float) $nilai, 2, '.', ''),
            'tarikh_masa' => str_replace('T', ' ', (string) $nilai),
            default => (string) $nilai,
        };
    }

    /**
     * Pecahkan nilai tersimpan kepada [nilai input, teks Lain-lain] untuk mengisi borang.
     * Cth. "Lain-lain: Pen Drive" → ['__lain__', 'Pen Drive']. Bagi kotak semak, nilai input
     * ialah senarai: '["Emel","Lain-lain: Intranet"]' → [['Emel', '__lain__'], 'Intranet'].
     */
    public static function untukBorang(DokumenField $medan, ?string $nilai): array
    {
        if ($medan->jenis_data === 'jadual') {
            return [MedanJadual::untukBorang($medan, $nilai), ''];
        }

        if ($medan->jenis_data === 'kotak_semak') {
            $dipilih = [];
            $teksLain = '';
            foreach (self::senaraiNilai($nilai) as $v) {
                if (self::adaLain($medan) && str_starts_with($v, self::AWALAN_LAIN)) {
                    $dipilih[] = self::NILAI_LAIN;
                    $teksLain = substr($v, strlen(self::AWALAN_LAIN));
                } else {
                    $dipilih[] = $v;
                }
            }

            return [$dipilih, $teksLain];
        }

        $nilai = (string) ($nilai ?? '');

        if (self::adaLain($medan) && str_starts_with($nilai, self::AWALAN_LAIN)) {
            return [self::NILAI_LAIN, substr($nilai, strlen(self::AWALAN_LAIN))];
        }
        if ($medan->jenis_data === 'tarikh_masa' && $nilai !== '') {
            return [str_replace(' ', 'T', substr($nilai, 0, 16)), ''];
        }

        return [$nilai, ''];
    }

    /**
     * Format nilai tersimpan untuk paparan (sudah di-escape). $medan boleh null
     * jika definisi medan tidak lagi wujud.
     */
    public static function papar(?DokumenField $medan, ?string $nilai): HtmlString
    {
        $nilai = (string) ($nilai ?? '');
        $e = fn (string $s) => e($s);

        if ($nilai === '' || $medan === null) {
            return new HtmlString($e($nilai));
        }

        $html = match ($medan->jenis_data) {
            'wang' => is_numeric($nilai) ? 'RM ' . number_format((float) $nilai, 2) : $e($nilai),
            'tarikh' => self::formatTarikh($nilai, 'd/m/Y') ?? $e($nilai),
            'tarikh_masa' => self::formatTarikh($nilai, 'd/m/Y, H:i') ?? $e($nilai),
            'emel' => filter_var($nilai, FILTER_VALIDATE_EMAIL)
                ? '<a href="mailto:' . $e($nilai) . '">' . $e($nilai) . '</a>'
                : $e($nilai),
            'url' => preg_match('#^https?://#i', $nilai)
                ? '<a href="' . $e($nilai) . '" target="_blank" rel="noopener noreferrer">' . $e($nilai) . '</a>'
                : $e($nilai),
            'textarea' => nl2br($e($nilai)),
            'kotak_semak' => implode(' ', array_map(
                fn ($v) => '<span class="medan-cip">' . $e($v) . '</span>',
                self::senaraiNilai($nilai)
            )),
            'jadual' => MedanJadual::papar($medan, $nilai),
            default => $e($nilai),
        };

        return new HtmlString($html);
    }

    /**
     * Panduan format bagi model AI (dimasukkan ke dalam prompt pengekstrakan).
     */
    public static function panduanAi(DokumenField $medan): string
    {
        $panduan = match ($medan->jenis_data) {
            'nombor' => 'nombor, angka sahaja',
            'wang' => 'jumlah wang dalam RM, angka sahaja tanpa "RM" atau koma (cth. 1500.50)',
            'tarikh' => 'tarikh, format YYYY-MM-DD',
            'masa' => 'masa 24 jam, format HH:MM',
            'tarikh_masa' => 'tarikh dan masa, format YYYY-MM-DD HH:MM',
            'emel' => 'alamat emel',
            'telefon' => 'nombor telefon/faks',
            'url' => 'pautan laman web bermula http:// atau https://',
            'dropdown', 'radio' => 'pilih SATU nilai TEPAT daripada [' . implode(', ', $medan->pilihan ?? []) . ']'
                . (self::adaLain($medan) ? '; jika jawapan lain yang jelas, tulis jawapan itu' : ''),
            'kotak_semak' => 'senarai JSON nilai yang ditanda, dipilih daripada [' . implode(', ', $medan->pilihan ?? []) . ']'
                . (self::adaLain($medan) ? '; jawapan lain yang jelas boleh dimasukkan' : '') . ', cth. ["a","b"]',
            'ya_tidak' => 'tulis "Ya" atau "Tidak" sahaja',
            'jadual' => MedanJadual::panduanAi($medan),
            // Arahan, bukan label: label seperti "teks panjang" pernah disalin oleh AI sebagai nilai
            'textarea' => 'salin teks berkaitan dari dokumen, boleh beberapa ayat',
            default => 'salin nilai berkaitan dari dokumen',
        };

        return $panduan;
    }

    /**
     * Bersihkan nilai cadangan AI supaya sah bagi medan ini; nilai yang tidak sah
     * menjadi null (pengguna isi sendiri di skrin semak). Pulangkan bentuk simpanan.
     */
    public static function bersihkanAi(DokumenField $medan, mixed $nilai): ?string
    {
        if (!self::adaNilai($medan)) {
            return null;
        }
        if ($medan->jenis_data === 'kotak_semak') {
            return self::bersihkanKotakSemakAi($medan, $nilai);
        }
        if ($medan->jenis_data === 'jadual') {
            return MedanJadual::bersihkanAi($medan, $nilai);
        }
        if (is_array($nilai) || is_object($nilai)) {
            return null;
        }
        $nilai = trim((string) ($nilai ?? ''));
        if ($nilai === '' || self::salinanPanduan($medan, $nilai)) {
            return null;
        }

        switch ($medan->jenis_data) {
            case 'nombor':
            case 'wang':
                $nilai = str_ireplace(['RM', ',', ' '], '', $nilai);
                break;
            case 'tarikh_masa':
                $nilai = str_replace('T', ' ', substr($nilai, 0, 16));
                break;
            case 'ya_tidak':
                $kecil = mb_strtolower($nilai);
                $nilai = in_array($kecil, ['ya', 'yes', 'y', 'true', 'ada'], true) ? 'Ya'
                    : (in_array($kecil, ['tidak', 'tak', 'no', 'n', 'false', 'tiada'], true) ? 'Tidak' : $nilai);
                break;
            case 'dropdown':
            case 'radio':
                if (in_array($nilai, $medan->pilihan ?? [], true)) {
                    return $nilai;
                }
                return self::adaLain($medan) ? self::AWALAN_LAIN . mb_substr($nilai, 0, 255) : null;
        }

        $peraturan = self::peraturan($medan)["medan_data.{$medan->dokumen_field_id}"];
        $peraturan[0] = 'nullable';

        if (Validator::make(['v' => $nilai], ['v' => $peraturan])->fails()) {
            return null;
        }

        return self::untukSimpan($medan, $nilai);
    }

    /**
     * True jika AI memulangkan teks panduan prompt atau label jenis data sebagai nilai
     * (cth. "Teks panjang" bagi medan textarea) dan bukan nilai daripada dokumen.
     */
    private static function salinanPanduan(DokumenField $medan, string $nilai): bool
    {
        $kecil = mb_strtolower(trim($nilai, " \t\n\r\0\x0B.\"'"));

        return in_array($kecil, [
            mb_strtolower(self::panduanAi($medan)),
            mb_strtolower(self::SENARAI[$medan->jenis_data] ?? ''),
            // Bukan "tiada": pilihan sah (Ada/Tiada) dan bermaksud "Tidak" bagi ya/tidak
            'teks', 'teks panjang', 'textarea', 'null', 'n/a',
        ], true);
    }

    /**
     * Kotak semak daripada AI: senarai (atau teks "a, b") dipadankan dengan senarai
     * pilihan tanpa mengira huruf besar/kecil; selebihnya menjadi "Lain-lain" jika dibenarkan.
     */
    private static function bersihkanKotakSemakAi(DokumenField $medan, mixed $nilai): ?string
    {
        if (is_string($nilai)) {
            $nilai = preg_split('/\s*[,;]\s*/', trim($nilai));
        }
        if (!is_array($nilai)) {
            return null;
        }

        $pilihanKecil = [];
        foreach ($medan->pilihan ?? [] as $p) {
            $pilihanKecil[mb_strtolower($p)] = $p;
        }

        $dipilih = [];
        $lain = [];
        foreach ($nilai as $v) {
            if (!is_scalar($v) || trim((string) $v) === '') {
                continue;
            }
            $v = trim((string) $v);
            if (isset($pilihanKecil[mb_strtolower($v)])) {
                $dipilih[] = $pilihanKecil[mb_strtolower($v)];
            } elseif (self::adaLain($medan)) {
                $lain[] = $v;
            }
        }
        if ($lain) {
            $dipilih[] = self::NILAI_LAIN;
        }

        return self::untukSimpan($medan, $dipilih, mb_substr(implode(', ', $lain), 0, 255));
    }

    private static function formatTarikh(string $nilai, string $format): ?string
    {
        try {
            return e(Carbon::parse($nilai)->format($format));
        } catch (\Throwable) {
            return null;
        }
    }
}
