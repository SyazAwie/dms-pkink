<?php

namespace App\Http\Middleware;

use App\Support\Demo;
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

            // cloudflared menulis semula Host kepada laman Herd (dms-pkink.test). Pulihkan alamat
            // terowong pada permintaan itu sendiri supaya fullUrl(), url.intended, paginasi dan
            // isSecure() betul untuk telefon peserta (bukan hanya URL yang dijana).
            if ($host = parse_url((string) Demo::urlTerowong(), PHP_URL_HOST)) {
                $request->headers->set('HOST', $host);
                $request->server->add(['HTTP_HOST' => $host, 'SERVER_NAME' => $host, 'HTTPS' => 'on', 'SERVER_PORT' => 443, 'REQUEST_SCHEME' => 'https']);
            }
        }

        return $next($request);
    }
}
