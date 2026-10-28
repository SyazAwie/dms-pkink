<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keselamatan bagi setiap respons web, untuk akses awam (demo melalui terowong
 * HTTPS / produksi):
 * - X-Frame-Options: halaman hanya boleh dibingkai oleh laman sendiri (pratonton imbasan)
 * - X-Content-Type-Options: pelayar tidak meneka jenis fail (fail imbasan dimuat naik pengguna)
 * - Referrer-Policy: URL penuh (cth. token semak) tidak dihantar ke laman lain
 * - Permissions-Policy: kamera dibenarkan untuk laman sendiri sahaja (ambil gambar borang)
 * - Strict-Transport-Security: hanya bila permintaan HTTPS
 *
 * CSP penuh belum ditetapkan kerana banyak skrip/gaya sebaris dalam view.
 */
class HeaderKeselamatan
{
    /**
     * CSP bagi respons fail imbasan muat naik pengguna (Fasa Keselamatan S1): sebarang
     * kandungan aktif (skrip PDF/SVG/HTML yang menyamar) tidak boleh berjalan dalam
     * asal (origin) sistem, jadi tidak boleh mencuri sesi pengguna yang membukanya.
     */
    public const CSP_FAIL = "sandbox; default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
