<?php

namespace App\Http\Middleware;

use App\Http\Controllers\DuaFaktorController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bila DUA_FAKTOR_WAJIB=true (ditetapkan semasa serahan, Fasa H), SUPERADMIN/ADMIN
 * tanpa 2FA dibawa ke Profil untuk mendaftar dahulu sebelum boleh membuka halaman lain.
 * Lalai false: akaun demo tidak memerlukan telefon authenticator.
 *
 * Guna: ->middleware('wajib.dua.faktor') pada kumpulan laluan 'auth'.
 */
class WajibDuaFaktor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->duaFaktorAktif() && DuaFaktorController::wajib($user)
            && ! $request->routeIs('profil.index', 'profil.dua-faktor.*', 'kata-laluan.*')) {
            if ($request->expectsJson()) {
                abort(403, 'Sila hidupkan pengesahan dua faktor terlebih dahulu.');
            }

            return redirect()->to(route('profil.index').'#dua-faktor')
                ->with('error', 'Pengesahan dua faktor (2FA) wajib bagi akaun pentadbir. Sila hidupkan sebelum meneruskan.');
        }

        return $next($request);
    }
}
