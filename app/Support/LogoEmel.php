<?php

namespace App\Support;

use Illuminate\Mail\Message;
use WeakMap;

/**
 * Logo PKINK dalam kepala emel. Logo dibenam (inline/CID) kerana pelayan sistem
 * tidak boleh dicapai oleh Gmail dsb. (localhost/rangkaian dalaman), jadi pautan
 * URL ke gambar akan pecah.
 *
 * Templat notifikasi dirender dua kali bagi setiap emel (HTML + teks biasa)
 * dengan objek Message yang sama: CID disimpan supaya logo dilampirkan sekali sahaja.
 */
class LogoEmel
{
    public const LALUAN = 'images/emel/logo-pkink.png';

    private static ?WeakMap $cid = null;

    /** CID bagi emel sebenar, atau URL biasa bila tiada Message (pratonton, ujian). */
    public static function src(?Message $mesej): string
    {
        if (! $mesej) {
            return asset(self::LALUAN);
        }

        self::$cid ??= new WeakMap;

        return self::$cid[$mesej] ??= $mesej->embed(public_path(self::LALUAN));
    }
}
