<?php

namespace App\Http\Middleware;

use App\Models\IpDisekat;
use App\Support\KeadaanKeselamatan;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Butang kecemasan (Fasa Keselamatan S3), dikuatkuasakan pada setiap permintaan web:
 * 1. IP disekat: halaman 403 sahaja (localhost dikecualikan).
 * 2. Pengguna log masuk yang dikunci, yang sesinya ditamatkan paksa, atau bukan
 *    SUPERADMIN semasa mod kecemasan: dilog keluar serta-merta.
 * 3. Mod kecemasan: sistem baca sahaja (semua POST/PUT/DELETE disekat kecuali log masuk,
 *    log keluar, 2FA dan tindakan Pusat Keselamatan), dan laluan awam lain ditutup.
 */
class PengawalKeselamatan
{
    /** Laluan yang kekal dibenarkan semasa mod kecemasan. */
    private const DIBENARKAN_KECEMASAN = ['login', 'login.proses', 'logout', 'dua-faktor.*', 'keselamatan.*', 'peranan.tukar'];

    public function handle(Request $request, Closure $next): Response
    {
        if (IpDisekat::disekat($request->ip())) {
            return response()->view('errors.ip-disekat', [], 403);
        }

        $kecemasan = KeadaanKeselamatan::kecemasan();
        $user = Auth::user();

        if ($user) {
            $mesej = match (true) {
                $user->dikunci_hingga !== null && $user->dikunci_hingga->isFuture() => 'Akaun anda telah dikunci oleh pentadbir sistem. Sila hubungi Pentadbir Sistem.',
                (int) $request->session()->get('masa_log_masuk', 0) < KeadaanKeselamatan::sahSelepas($user->user_id) => 'Sesi anda telah ditamatkan oleh pentadbir sistem atas sebab keselamatan. Sila log masuk semula.',
                $kecemasan && ! in_array('SUPERADMIN', $user->kodPerananSendiri(), true) => 'Sistem kini dalam mod kecemasan. Hanya Super Admin boleh log masuk buat sementara waktu.',
                default => null,
            };
            if ($mesej) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['message' => $mesej], 401)
                    : redirect()->route('login')->withErrors(['ic_pekerja' => $mesej]);
            }
        }

        if ($kecemasan && ! $request->routeIs(...self::DIBENARKAN_KECEMASAN)) {
            if (! $user) {
                return response()->view('errors.kecemasan', [], 503);
            }
            if (! $request->isMethodSafe()) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Mod kecemasan: sistem baca sahaja.'], 503)
                    : back()->with('error', 'Sistem dalam mod kecemasan (baca sahaja). Perubahan tidak dibenarkan sehingga mod kecemasan dimatikan.');
            }
        }

        return $next($request);
    }
}
