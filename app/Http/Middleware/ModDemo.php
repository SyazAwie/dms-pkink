<?php

namespace App\Http\Middleware;

use App\Support\Demo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mod Demo (Fasa D3): selaraskan URL dengan alamat terowong dan sekat tindakan yang
 * menjejaskan peserta lain atau keluar dari sistem (config demo.sekat). Tiada kesan
 * bila mod demo mati.
 */
class ModDemo
{
    public const MESEJ = 'Tindakan ini dimatikan dalam mod demo.';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Demo::aktif()) {
            return $next($request);
        }

        Demo::selarasUrl($request);

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
