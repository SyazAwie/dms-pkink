<?php

namespace App\Http\Middleware;

use App\Support\Demo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mod Demo & Percubaan (Fasa D): selaraskan URL dengan alamat terowong. Mod demo turut
 * menyekat tindakan yang menjejaskan peserta lain atau keluar dari sistem (config demo.sekat).
 * Mod percubaan: akses tempatan (dms-pkink.test) semasa terowong hidup dialihkan ke alamat
 * terowong, supaya pentadbir dan peserta berkongsi satu domain (sesi, CSRF, pautan emel).
 * Tiada kesan bila kedua-dua mod mati.
 */
class ModDemo
{
    public const MESEJ = 'Tindakan ini dimatikan dalam mod demo.';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Demo::mod()) {
            return $next($request);
        }

        Demo::selarasUrl($request);

        if (Demo::percubaan()) {
            $url = Demo::urlTerowong();
            if ($url && ! Demo::melaluiTerowong($request) && $request->isMethod('GET') && ! $request->expectsJson()) {
                return redirect()->away($url.$request->getRequestUri());
            }

            return $next($request);
        }

        $laluan = $request->route()?->getName();
        if ($laluan && Str::is(config('demo.sekat', []), $laluan)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => self::MESEJ], 403);
            }

            return redirect()->to(url()->previous() !== $request->fullUrl() ? url()->previous() : route('login'))
                ->with('demo_disekat', self::MESEJ);
        }

        return $next($request);
    }
}
