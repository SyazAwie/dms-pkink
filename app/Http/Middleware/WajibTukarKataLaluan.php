<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengguna yang masih memegang kata laluan sementara (dari emel pendaftaran)
 * atau kata laluan yang ditetapkan oleh admin mesti menukarnya dahulu sebelum
 * boleh membuka halaman lain.
 *
 * Guna: ->middleware('wajib.tukar') pada kumpulan laluan 'auth'.
 */
class WajibTukarKataLaluan
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && $user->perlu_tukar_kata_laluan
            && !$request->routeIs('kata-laluan.tukar', 'kata-laluan.simpan')) {
            if ($request->expectsJson()) {
                abort(403, 'Sila tukar kata laluan sementara anda terlebih dahulu.');
            }

            return redirect()->route('kata-laluan.tukar');
        }

        return $next($request);
    }
}
