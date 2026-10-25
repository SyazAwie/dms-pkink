<?php

namespace App\Support;

/**
 * Peraturan validasi nama kakitangan (pendaftaran awam, profil, Pengurusan
 * Kakitangan): huruf (termasuk Unicode), ruang dan aksara ' ’ . - @ / sahaja.
 * Cth. sah: "Ahmad bin Ali", "Siti a/p Muthu", "Lee @ Ali", "O'Brien".
 * Menghalang sintaks Markdown/pautan dalam nama (semakan keselamatan, #41).
 */
class PeraturanNama
{
    public static function peraturan(): array
    {
        return ['required', 'string', 'max:255', "regex:/^[\\pL\\s'’.\\-@\\/]+$/u"];
    }

    public static function mesej(string $medan = 'nama_staff'): array
    {
        return [
            "{$medan}.required" => 'Sila masukkan nama penuh.',
            "{$medan}.max" => 'Nama penuh terlalu panjang.',
            "{$medan}.regex" => "Nama hanya boleh mengandungi huruf, ruang dan aksara ' . - @ /",
        ];
    }
}
