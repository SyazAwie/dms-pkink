<?php

namespace App\Support;

/**
 * Teks daripada pengguna (nama, sebab tolak) yang dimasukkan ke dalam emel
 * MailMessage. Templat emel Laravel memproses kandungan sebagai Markdown:
 * HTML sudah di-escape, tetapi sintaks seperti [teks](url) masih menjadi
 * pautan. Tanpa escape, pemohon boleh menyuntik pautan pancingan data ke dalam
 * emel rasmi sistem (semakan keselamatan, Isu 2 / #41).
 */
class TeksEmel
{
    /**
     * Letak backslash di hadapan aksara Markdown supaya dipaparkan seperti biasa.
     *
     * & < > " ' SENGAJA tidak di-escape: Blade menukarnya kepada entiti HTML
     * ({{ }}) sebelum Markdown diproses, dan backslash di hadapan entiti akan
     * merosakkan paparan (cth. O'Brien menjadi O&#039;Brien).
     */
    public static function selamat(?string $teks): string
    {
        return preg_replace('/([\\\\`*_{}\[\]()#+\-.!|~=])/', '\\\\$1', (string) $teks);
    }
}
