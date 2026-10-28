<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

/**
 * Mod Demo & Mod Percubaan (Fasa D). Fail penanda storage/app/demo/aktif (kandungan: nama mod)
 * menghidupkan mod bagi permintaan web sahaja (lihat AppServiceProvider::register); arahan
 * konsol & penjadual (sandaran, semakan keselamatan) kekal pada DB sebenar.
 *
 * demo       Peserta mencuba sistem dengan selamat: log masuk butang akaun demo sahaja,
 *            tindakan berisiko disekat (ModDemo), emel ke log.
 * percubaan  Sistem penuh tanpa sekatan untuk latihan: peserta mendaftar akaun sendiri,
 *            pentadbir meluluskan, emel sebenar dihantar. Semua URL ikut alamat terowong
 *            supaya pautan dalam emel boleh dibuka dari telefon.
 *
 * Kedua-dua mod: DB salinan & folder fail sendiri (dicipta oleh demo:sedia), APP_DEBUG mati.
 *
 * Kod akses: permintaan melalui terowong mesti membuka akses dengan kod 6 aksara (dijana oleh
 * demo:hidup, terkandung dalam QR /demo), supaya pautan yang tersebar sahaja tidak mencukupi.
 *
 * storage/app/demo        aktif, kod.txt, terowong.json, fail/, asas.sql, asas-fail/   (mod demo)
 * storage/app/percubaan   fail/, asas.sql, asas-fail/                         (mod percubaan)
 */
class Demo
{
    public const DEMO = 'demo';

    public const PERCUBAAN = 'percubaan';

    /** Terowong dianggap mati jika degupan demo:terowong lebih lama daripada ini (saat). */
    public const DEGUPAN_SAAT = 30;

    /** Folder kawalan (penanda & terowong) dan data mod demo. */
    public static function folder(string $laluan = ''): string
    {
        return storage_path('app/demo'.($laluan !== '' ? '/'.$laluan : ''));
    }

    /** Folder data (fail/, asas.sql, asas-fail/) bagi satu mod. */
    public static function folderData(string $mod, string $laluan = ''): string
    {
        return storage_path('app/'.self::sahkanMod($mod).($laluan !== '' ? '/'.$laluan : ''));
    }

    public static function sahkanMod(string $mod): string
    {
        if (! in_array($mod, [self::DEMO, self::PERCUBAAN], true)) {
            throw new \InvalidArgumentException("Mod tidak sah: {$mod}");
        }

        return $mod;
    }

    /** Mod yang dihidupkan oleh fail penanda (untuk konsol; permintaan web guna mod()). */
    public static function modPenanda(): ?string
    {
        if (! File::exists(self::folder('aktif'))) {
            return null;
        }
        $isi = trim((string) File::get(self::folder('aktif')));

        return $isi === self::PERCUBAAN ? self::PERCUBAAN : self::DEMO;
    }

    public static function penandaWujud(): bool
    {
        return self::modPenanda() !== null;
    }

    /** Mod yang berkuat kuasa bagi permintaan semasa (null = sistem biasa). */
    public static function mod(): ?string
    {
        return config('demo.mod');
    }

    /** Mod demo (sekatan + log masuk butang). */
    public static function aktif(): bool
    {
        return self::mod() === self::DEMO;
    }

    /** Mod percubaan (sistem penuh untuk latihan). */
    public static function percubaan(): bool
    {
        return self::mod() === self::PERCUBAAN;
    }

    /** Nama DB bagi mod; enggan jika sama dengan DB sebenar (perlindungan daripada tersalah tulis). */
    public static function namaDb(string $mod = self::DEMO): string
    {
        $nama = (string) config(self::sahkanMod($mod) === self::PERCUBAAN ? 'demo.percubaan_db' : 'demo.db');
        $sebenar = (string) config('database.connections.'.config('database.default').'.database');
        if ($nama === '' || strcasecmp($nama, $sebenar) === 0 || ! preg_match('/^[A-Za-z0-9_]+$/', $nama)) {
            throw new \RuntimeException("Nama DB {$mod} tidak sah atau sama dengan DB sebenar ({$sebenar}). Semak DEMO_DB / PERCUBAAN_DB.");
        }

        return $nama;
    }

    /** Tukar konfigurasi kepada persekitaran mod. Dipanggil sebelum sambungan DB dibuka. */
    public static function terapkan(string $mod = self::DEMO): void
    {
        $sambungan = config('database.default');
        config([
            'demo.mod' => self::sahkanMod($mod),
            "database.connections.{$sambungan}.database" => self::namaDb($mod),
            'filesystems.disks.local.root' => self::folderData($mod, 'fail'),
            'app.debug' => false,
        ]);

        // Demo: tiada emel keluar. Percubaan: emel sebenar + notifikasi emel aliran kelulusan
        config($mod === self::DEMO
            ? ['mail.default' => 'log', 'services.notifikasi.emel' => false]
            : ['services.notifikasi.emel' => true]);
    }

    /**
     * Permintaan datang melalui cloudflared (berjalan pada komputer ini): header
     * CF-Connecting-IP daripada alamat loopback. Ditanda oleh IpTerowong sebelum IP ditukar.
     */
    public static function melaluiTerowong(Request $request): bool
    {
        return (bool) ($request->attributes->get('melalui_terowong')
            ?? ($request->headers->has('CF-Connecting-IP') && in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)));
    }

    /**
     * URL dijana ikut alamat terowong: bagi permintaan melalui terowong (cloudflared menulis
     * semula Host kepada laman Herd), dan dalam mod percubaan bagi SEMUA permintaan supaya
     * pautan dalam emel (cth. diluluskan dari komputer pentadbir) boleh dibuka dari telefon.
     */
    public static function selarasUrl(Request $request): void
    {
        $url = self::urlTerowong();
        if ($url && (self::melaluiTerowong($request) || self::percubaan())) {
            URL::forceRootUrl($url);
            URL::forceScheme('https');
        }
    }

    /** Alamat terowong yang masih hidup (degupan baharu), atau null. */
    public static function urlTerowong(): ?string
    {
        $data = json_decode((string) @file_get_contents(self::folder('terowong.json')), true);
        if (! is_array($data) || ! isset($data['url'], $data['degupan'])) {
            return null;
        }
        if (time() - (int) $data['degupan'] > self::DEGUPAN_SAAT) {
            return null;
        }

        return is_string($data['url']) && preg_match('#^https://[a-z0-9.-]+$#i', $data['url']) ? $data['url'] : null;
    }

    // ---- Kod akses (permintaan melalui terowong mesti membuka akses dengan kod ini) ----

    public const SESI_KOD = 'kod_akses_demo';

    /** Aksara kod: tanpa O/0, I/1/L yang mudah tersilap baca pada projektor. */
    private const AKSARA_KOD = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function kodAkses(): ?string
    {
        $kod = trim((string) @file_get_contents(self::folder('kod.txt')));

        return preg_match('/^[A-Z0-9]{6}$/', $kod) ? $kod : null;
    }

    /** Jana kod baharu; akses yang dibuka dengan kod lama tidak lagi sah. */
    public static function janaKod(): string
    {
        $kod = '';
        for ($i = 0; $i < 6; $i++) {
            $kod .= self::AKSARA_KOD[random_int(0, strlen(self::AKSARA_KOD) - 1)];
        }
        File::ensureDirectoryExists(self::folder());
        File::put(self::folder('kod.txt'), $kod);

        return $kod;
    }

    public static function kodSah(?string $cubaan): bool
    {
        $kod = self::kodAkses();

        return $kod !== null && is_string($cubaan) && hash_equals($kod, strtoupper(trim($cubaan)));
    }

    /** Sesi ini sudah membuka akses dengan kod semasa. */
    public static function aksesDibuka(Request $request): bool
    {
        return self::kodSah($request->session()->get(self::SESI_KOD));
    }

    /** Pautan halaman/QR yang terus membuka akses (kod dalam pautan). */
    public static function pautanBerkod(string $url): string
    {
        $kod = self::kodAkses();

        return $kod ? rtrim($url, '/').'/?kod='.$kod : $url;
    }

    /** No. KP akaun demo (config demo.akaun). */
    public static function akaun(): array
    {
        return array_values(array_unique(array_map('strval', (array) config('demo.akaun'))));
    }
}
