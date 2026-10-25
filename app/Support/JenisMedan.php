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
 *   bantuan  teks bantuan di bawah label
 *   lain     true = tambah pilihan "Lain-lain (nyatakan)" (dropdown/radio)
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
        'ya_tidak' => 'Ya / Tidak',
    ];

    /** Jenis yang menggunakan senarai `pilihan` (dan boleh ada "Lain-lain"). */
    public const ADA_PILIHAN = ['dropdown', 'radio'];

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

    /**
     * Peraturan validasi bagi satu medan: [kunci input => peraturan].
     * Kunci utama medan_data.{id}; medan dengan "Lain-lain" juga ada medan_lain.{id}.
     */
    public static function peraturan(DokumenField $medan): array
    {
        $id = $medan->dokumen_field_id;
        $kunci = "medan_data.{$id}";
        $peraturan = [$medan->is_required ? 'required' : 'nullable'];

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
                $dibenarkan = $medan->pilihan ?? [];
                if (self::adaLain($medan)) {
                    $dibenarkan[] = self::NILAI_LAIN;
                }
                $peraturan[] = Rule::in($dibenarkan);
                break;
            case 'ya_tidak':
                $peraturan[] = Rule::in(self::YA_TIDAK);
                break;
            default:
                $peraturan[] = 'string';
                break;
        }

        $hasil = [$kunci => $peraturan];
        if (self::adaLain($medan)) {
            $hasil["medan_lain.{$id}"] = ['nullable', 'string', 'max:255', 'required_if:' . $kunci . ',' . self::NILAI_LAIN];
        }

        return $hasil;
    }

    /**
     * Nilai simpanan bagi satu medan daripada permintaan borang (sudah divalidasi).
     */
    public static function dariPermintaan(DokumenField $medan, Request $request): ?string
    {
        $id = $medan->dokumen_field_id;

        return self::untukSimpan($medan, $request->input("medan_data.{$id}"), $request->input("medan_lain.{$id}"));
    }

    /**
     * Tukar nilai yang telah disahkan kepada bentuk simpanan dokumen_data.nilai_data.
     * Kosong (null / '') dipulangkan sebagai null, iaitu "tiada nilai".
     */
    public static function untukSimpan(DokumenField $medan, mixed $nilai, mixed $nilaiLain = null): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
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
     * Cth. "Lain-lain: Pen Drive" → ['__lain__', 'Pen Drive'].
     */
    public static function untukBorang(DokumenField $medan, ?string $nilai): array
    {
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
            'ya_tidak' => 'tulis "Ya" atau "Tidak" sahaja',
            'textarea' => 'teks panjang',
            default => 'teks',
        };

        return $panduan;
    }

    /**
     * Bersihkan nilai cadangan AI supaya sah bagi medan ini; nilai yang tidak sah
     * menjadi null (pengguna isi sendiri di skrin semak). Pulangkan bentuk simpanan.
     */
    public static function bersihkanAi(DokumenField $medan, mixed $nilai): ?string
    {
        if (is_array($nilai) || is_object($nilai)) {
            return null;
        }
        $nilai = trim((string) ($nilai ?? ''));
        if ($nilai === '') {
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

    private static function formatTarikh(string $nilai, string $format): ?string
    {
        try {
            return e(Carbon::parse($nilai)->format($format));
        } catch (\Throwable) {
            return null;
        }
    }
}
