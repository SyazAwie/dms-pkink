<?php

namespace App\Support;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Pengesahan dua faktor TOTP (P13), serasi Google/Microsoft Authenticator.
 * Kod 6 digit berubah setiap 30 saat; satu tetingkap (±30 s) dibenarkan untuk
 * perbezaan jam telefon. Kod pemulihan sekali guna untuk telefon hilang.
 */
class DuaFaktor
{
    public const BIL_KOD_PEMULIHAN = 8;

    public static function rahsiaBaharu(): string
    {
        return (new Google2FA)->generateSecretKey(32);
    }

    /** Kod QR (SVG sebaris) untuk diimbas oleh aplikasi authenticator. */
    public static function qrSvg(User $user, string $rahsia): string
    {
        // Label akaun = emel (bukan No. KP: label ini kelihatan dalam aplikasi telefon)
        $uri = (new Google2FA)->getQRCodeUrl('DMS PKINK', $user->email, $rahsia);

        return (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($uri);
    }

    public static function sahKod(string $rahsia, ?string $kod): bool
    {
        $kod = preg_replace('/\s+/', '', (string) $kod);

        return preg_match('/^\d{6}$/', $kod) === 1 && (new Google2FA)->verifyKey($rahsia, $kod, 1);
    }

    /** @return array<int, string> cth. "K7Q2-9XMP" */
    public static function kodPemulihanBaharu(): array
    {
        return collect(range(1, self::BIL_KOD_PEMULIHAN))
            ->map(fn () => Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4)))->all();
    }

    /**
     * Guna satu kod pemulihan: jika sah, ia dibuang daripada senarai (sekali guna).
     */
    public static function gunaKodPemulihan(User $user, ?string $kod): bool
    {
        $kod = Str::upper(trim((string) $kod));
        $senarai = $user->dua_faktor_kod_pemulihan ?? [];
        $jumpa = collect($senarai)->search(fn ($k) => hash_equals($k, $kod));
        if ($kod === '' || $jumpa === false) {
            return false;
        }

        unset($senarai[$jumpa]);
        $user->forceFill(['dua_faktor_kod_pemulihan' => array_values($senarai)])->saveQuietly();

        return true;
    }
}
