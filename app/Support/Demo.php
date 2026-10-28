<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

/**
 * Mod Demo (Fasa D). Fail penanda storage/app/demo/aktif menghidupkan mod ini bagi
 * permintaan web sahaja (lihat AppServiceProvider::register):
 * - pangkalan data salinan (config demo.db) dan folder fail berasingan (storage/app/demo/fail),
 *   jadi peserta tidak menyentuh rekod atau fail sebenar;
 * - APP_DEBUG dimatikan, emel ditulis ke log, notifikasi emel dimatikan;
 * - log masuk melalui butang akaun demo sahaja (DemoController), tindakan berisiko disekat
 *   (middleware ModDemo).
 *
 * Struktur folder storage/app/demo:
 *   aktif            fail penanda mod demo
 *   fail/            akar disk `local` semasa demo
 *   asas.sql         keadaan asas DB demo (untuk demo:set-semula)
 *   asas-fail/       keadaan asas fail demo
 *   terowong.json    alamat terowong semasa (ditulis oleh demo:terowong)
 */
class Demo
{
    public static function folder(string $laluan = ''): string
    {
        return storage_path('app/demo'.($laluan !== '' ? '/'.$laluan : ''));
    }

    /** Fail penanda wujud (digunakan oleh arahan konsol; permintaan web guna aktif()). */
    public static function penandaWujud(): bool
    {
        return File::exists(self::folder('aktif'));
    }

    /** Mod demo berkuat kuasa bagi permintaan semasa. */
    public static function aktif(): bool
    {
        return (bool) config('demo.aktif');
    }

    /** Nama DB demo; enggan jika sama dengan DB sebenar (perlindungan daripada tersalah tulis). */
    public static function namaDb(): string
    {
        $demo = (string) config('demo.db');
        $sebenar = (string) config('database.connections.'.config('database.default').'.database');
        if ($demo === '' || strcasecmp($demo, $sebenar) === 0 || ! preg_match('/^[A-Za-z0-9_]+$/', $demo)) {
            throw new \RuntimeException("Nama DB demo tidak sah atau sama dengan DB sebenar ({$sebenar}). Semak DEMO_DB.");
        }

        return $demo;
    }

    /** Tukar konfigurasi kepada persekitaran demo. Dipanggil sebelum sambungan DB dibuka. */
    public static function terapkan(): void
    {
        $sambungan = config('database.default');
        config([
            'demo.aktif' => true,
            "database.connections.{$sambungan}.database" => self::namaDb(),
            'filesystems.disks.local.root' => self::folder('fail'),
            'app.debug' => false,
            'mail.default' => 'log',
            'services.notifikasi.emel' => false,
        ]);
    }

    /**
     * Permintaan melalui terowong Cloudflare: cloudflared menulis semula Host kepada laman
     * Herd (dms-pkink.test), jadi URL dijana ikut alamat terowong yang disimpan demo:terowong.
     */
    public static function selarasUrl(Request $request): void
    {
        if (! $request->headers->has('CF-Connecting-IP') || ! in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)) {
            return;
        }
        if ($url = self::urlTerowong()) {
            URL::forceRootUrl($url);
            URL::forceScheme('https');
        }
    }

    public static function urlTerowong(): ?string
    {
        $data = json_decode((string) @file_get_contents(self::folder('terowong.json')), true);
        $url = is_array($data) ? ($data['url'] ?? null) : null;

        return is_string($url) && preg_match('#^https://[a-z0-9.-]+$#i', $url) ? $url : null;
    }

    /** No. KP akaun demo (config demo.akaun). */
    public static function akaun(): array
    {
        return array_values(array_unique(array_map('strval', (array) config('demo.akaun'))));
    }
}
