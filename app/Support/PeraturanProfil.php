<?php

namespace App\Support;

/**
 * Peraturan validasi jawatan & no. telefon kakitangan (profil sendiri dan
 * Pengurusan Kakitangan). Kedua-duanya pilihan; digunakan sebagai sumber
 * auto-isi medan borang (JenisMedan::SUMBER_AUTO_ISI).
 * Jawatan: huruf, nombor, ruang dan tanda baca biasa sahaja (cth. "Pegawai
 * Teknologi Maklumat F41"), tanpa sintaks Markdown/pautan (lihat #41).
 */
class PeraturanProfil
{
    public static function peraturan(): array
    {
        return [
            'jawatan' => ['nullable', 'string', 'max:150', "regex:/^[\\pL\\pN\\s'’.,\\-@\\/()&]+$/u"],
            'no_telefon' => ['nullable', 'string', 'max:20', 'regex:' . JenisMedan::REGEX_TELEFON],
        ];
    }

    public static function mesej(): array
    {
        return [
            'jawatan.max' => 'Jawatan terlalu panjang (maksimum 150 aksara).',
            'jawatan.regex' => 'Jawatan hanya boleh mengandungi huruf, nombor, ruang dan aksara \' . , - @ / ( ) &',
            'no_telefon.max' => 'No. telefon terlalu panjang.',
            'no_telefon.regex' => 'Format no. telefon tidak sah. Cth: 012-345 6789 atau 09-748 1234.',
        ];
    }
}
