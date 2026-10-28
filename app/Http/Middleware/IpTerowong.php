<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terowong Cloudflare (demo:terowong) berjalan pada komputer ini, jadi semua permintaan
 * peserta tiba daripada 127.0.0.1. IP sebenar peserta ada dalam CF-Connecting-IP: gunakan
 * IP itu supaya had kadar log masuk, sekatan IP automatik dan Sejarah Log Masuk dikira
 * bagi setiap peranti, bukan dikongsi oleh semua peserta.
 *
 * Header hanya dipercayai daripada loopback (cloudflared tempatan); permintaan dari IP lain
 * tidak boleh memalsukannya.
 */
class IpTerowong
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->headers->get('CF-Connecting-IP');
        if ($ip && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true) && filter_var($ip, FILTER_VALIDATE_IP)) {
            $request->attributes->set('melalui_terowong', true);
            $request->server->set('REMOTE_ADDR', $ip);
        }

        return $next($request);
    }
}
