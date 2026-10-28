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
        }

        if ($sekat = $this->pintuKod($request)) {
            return $sekat;
        }

        if (Demo::percubaan()) {
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

    /**
     * Kod akses bagi permintaan melalui terowong (Internet). Akses di komputer ini dikecualikan.
     * ?kod=… (dari QR) membuka akses dan dibuang dari bar alamat; tanpa kod, halaman masukkan kod.
     */
    private function pintuKod(Request $request): ?Response
    {
        if (! Demo::melaluiTerowong($request) || Demo::kodAkses() === null || Demo::aksesDibuka($request)
            || $request->routeIs('demo.kod')) {
            return null;
        }

        if ($request->isMethod('GET') && $request->query->has('kod')) {
            if (Demo::kodSah((string) $request->query('kod'))) {
                $request->session()->put(Demo::SESI_KOD, Demo::kodAkses());
                // Bina semula dengan url() (alamat terowong), bukan Host permintaan (ditulis semula
                // oleh cloudflared kepada laman Herd yang tidak boleh dibuka dari telefon)
                $baki = $request->query();
                unset($baki['kod']);

                return redirect()->to(url($request->path()).($baki ? '?'.http_build_query($baki) : ''));
            }

            return response()->view('demo.kod', ['ke' => '/', 'salah' => true], 403);
        }

        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            return response()->view('demo.kod', ['ke' => $request->getRequestUri(), 'salah' => false], 403);
        }

        return response($request->expectsJson() ? ['message' => 'Kod akses diperlukan.'] : 'Kod akses diperlukan.', 403);
    }
}
