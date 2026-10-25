<?php

namespace App\Support;

use App\Models\DokumenField;
use Illuminate\Validation\Rule;

/**
 * Sumber tunggal bagi jenis data medan EAV (dokumen_field.jenis_data).
 *
 * Setiap jenis menentukan tiga perkara di satu tempat:
 * - peraturan validasi nilai (borang muat naik, edit, Imbas & Arkib)
 * - cara nilai disimpan dalam dokumen_data.nilai_data (sentiasa teks)
 * - label untuk pembina medan dalam halaman Kategori Dokumen
 *
 * Input borang dirender oleh satu partial: resources/views/dokumen/partials/medan-input.blade.php
 */
final class JenisMedan
{
    /** kod jenis_data => label dalam pembina medan */
    public const SENARAI = [
        'teks' => 'Teks',
        'nombor' => 'Nombor',
        'tarikh' => 'Tarikh',
        'dropdown' => 'Dropdown',
        'textarea' => 'Textarea Panjang',
    ];

    /** Jenis yang menggunakan senarai `pilihan`. */
    public const ADA_PILIHAN = ['dropdown'];

    public static function kod(): array
    {
        return array_keys(self::SENARAI);
    }

    /**
     * Peraturan validasi bagi nilai satu medan (kunci medan_data.{id}).
     */
    public static function peraturan(DokumenField $medan): array
    {
        $peraturan = [$medan->is_required ? 'required' : 'nullable'];

        switch ($medan->jenis_data) {
            case 'nombor':
                $peraturan[] = 'numeric';
                break;
            case 'tarikh':
                $peraturan[] = 'date';
                break;
            case 'dropdown':
                $peraturan[] = Rule::in($medan->pilihan ?? []);
                break;
            default:
                $peraturan[] = 'string';
                break;
        }

        return $peraturan;
    }

    /**
     * Tukar nilai yang telah disahkan kepada bentuk simpanan dokumen_data.nilai_data.
     * Kosong (null / '') dipulangkan sebagai null, iaitu "tiada nilai".
     */
    public static function untukSimpan(DokumenField $medan, mixed $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        return (string) $nilai;
    }
}
