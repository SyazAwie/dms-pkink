<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Polisi kata laluan yang ditetapkan oleh pengguna sendiri (tukar kata laluan
 * sementara, profil, tetapan semula melalui emel): sekurang-kurangnya 8 aksara,
 * mengandungi huruf dan nombor.
 *
 * Kata laluan yang ditetapkan oleh admin (Pengurusan Kakitangan) tidak guna
 * polisi ini kerana pengguna wajib menukarnya pada log masuk seterusnya.
 */
class PolisiKataLaluan
{
    public static function peraturan(): Password
    {
        return Password::min(8)->letters()->numbers();
    }

    /**
     * Mesej ralat BM bagi medan kata laluan yang diberi.
     */
    public static function mesej(string $medan): array
    {
        return [
            "{$medan}.required" => 'Sila masukkan kata laluan baharu.',
            "{$medan}.string" => 'Kata laluan tidak sah.',
            "{$medan}.confirmed" => 'Pengesahan kata laluan tidak sepadan.',
            "{$medan}.min" => 'Kata laluan mesti sekurang-kurangnya 8 aksara.',
            "{$medan}.password.letters" => 'Kata laluan mesti mengandungi sekurang-kurangnya satu huruf.',
            "{$medan}.password.numbers" => 'Kata laluan mesti mengandungi sekurang-kurangnya satu nombor.',
        ];
    }
}
