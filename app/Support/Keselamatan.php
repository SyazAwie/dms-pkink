<?php

namespace App\Support;

use App\Models\LogAudit;
use App\Models\LogMasuk;
use App\Models\User;
use App\Notifications\NotifikasiDms;
use App\Services\PemeriksaFail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Pusat Keselamatan (Fasa Keselamatan S2): ringkasan ancaman, senarai semak pengerasan
 * dan amaran automatik kepada SUPERADMIN/ADMIN. Semua amaran dihadkan sekali bagi setiap
 * tempoh (Cache::add) supaya serangan berterusan tidak membanjiri loceng & telefon.
 */
class Keselamatan
{
    /** Log masuk gagal dari satu IP dalam tetingkap ini mencetuskan amaran (dan sekatan S3). */
    public const AMBANG_GAGAL_IP = 10;

    public const MINIT_TETINGKAP = 10;

    /** Kata laluan lalai/lemah yang disemak pada akaun pentadbir. */
    private const KATA_LALUAN_LEMAH = ['123456789', '12345678', 'password', 'Password1', 'pkink123', 'admin123'];

    public const TINDAKAN_SENSITIF = ['Tetapan Peranan', 'Tukar Kata Laluan', 'Matikan 2FA', 'Fail Berbahaya Ditolak',
        'Pulihkan', 'Lupus Dokumen', 'Kunci Akaun', 'Buka Kunci Akaun', 'Sekat IP', 'Buka Sekatan IP',
        'Log Keluar Paksa', 'Mod Kecemasan'];

    // ------------------------------------------------------------------ Ringkasan

    /** @return array<string, mixed> */
    public static function ringkasan(): array
    {
        $sejak = now()->subDay();
        $gagal = LogMasuk::where('berjaya', false)->where('created_at', '>=', $sejak);

        return [
            'gagal24j' => (clone $gagal)->count(),
            'berjaya24j' => LogMasuk::where('berjaya', true)->where('created_at', '>=', $sejak)->count(),
            'ipGagal' => (clone $gagal)->selectRaw('alamat_ip, COUNT(*) AS bil, MAX(created_at) AS terakhir')
                ->groupBy('alamat_ip')->orderByDesc('bil')->limit(10)->get(),
            'akaunDisasar' => (clone $gagal)->whereNotNull('user_id')->selectRaw('user_id, COUNT(*) AS bil')
                ->groupBy('user_id')->orderByDesc('bil')->limit(5)->with('pengguna')->get(),
            'padam24j' => LogAudit::where('tindakan', 'like', 'Padam%')->where('created_at', '>=', $sejak)->count(),
            'failBerbahaya7h' => LogAudit::where('tindakan', 'Fail Berbahaya Ditolak')->where('created_at', '>=', now()->subDays(7))->count(),
            'tindakanSensitif' => LogAudit::with('pengguna')
                ->where(fn ($q) => $q->whereIn('tindakan', self::TINDAKAN_SENSITIF)->orWhere('tindakan', 'like', 'Padam%'))
                ->latest('log_audit_id')->limit(15)->get(),
        ];
    }

    /**
     * Senarai semak pengerasan: status ok/amaran/ralat + nota tindakan (bahasa mudah).
     *
     * @return list<array{nama: string, status: string, nilai: string, nota: ?string}>
     */
    public static function senaraiSemak(): array
    {
        $semak = fn ($nama, $status, $nilai, $nota = null) => compact('nama', 'status', 'nilai', 'nota');
        $hasil = [];

        // Pangkalan data: akaun root / tanpa kata laluan / terbuka pada rangkaian
        $db = config('database.connections.'.config('database.default'));
        if (($db['driver'] ?? '') === 'mysql' || ($db['driver'] ?? '') === 'mariadb') {
            $tanpaKataLaluan = ($db['password'] ?? '') === '';
            $root = ($db['username'] ?? '') === 'root';
            $hasil[] = $tanpaKataLaluan || $root
                ? $semak('Akaun pangkalan data', 'ralat', ($root ? 'Sistem guna akaun root' : 'Akaun '.$db['username']).($tanpaKataLaluan ? ' tanpa kata laluan' : ''),
                    'Cipta pengguna DB khas (dms_app) dengan kata laluan kuat dan keistimewaan terhad. Lihat Pelan Tindak Balas Insiden bahagian Pengerasan.')
                : $semak('Akaun pangkalan data', 'ok', 'Pengguna khas '.$db['username'].' dengan kata laluan');

            $bind = self::pembolehUbahDb('bind_address');
            $rangkaian = self::pembolehUbahDb('skip_networking') !== 'ON' && ! in_array($bind, ['127.0.0.1', 'localhost', '::1'], true);
            $hasil[] = $rangkaian
                ? $semak('Akses rangkaian pangkalan data', 'ralat', 'MySQL menerima sambungan dari rangkaian ('.($bind ?: 'semua antara muka').')',
                    'Tetapkan bind-address=127.0.0.1 dalam my.ini dan mulakan semula MySQL, supaya DB hanya boleh dicapai dari pelayan ini.')
                : $semak('Akses rangkaian pangkalan data', 'ok', 'Hanya dari pelayan ini ('.$bind.')');
        }

        $hasil[] = config('app.debug')
            ? $semak('Mod debug', 'amaran', 'APP_DEBUG=true', 'Mod debug mendedahkan butiran teknikal kepada penyerang. Tetapkan APP_DEBUG=false bila sistem dibuka kepada orang lain.')
            : $semak('Mod debug', 'ok', 'Dimatikan');

        $antivirus = app(PemeriksaFail::class)->statusAntivirus();
        $hasil[] = $antivirus['aktif']
            ? $semak('Antivirus fail muat naik', 'ok', $antivirus['nama'].(config('services.keselamatan.antivirus_wajib') ? ' (wajib)' : ''))
            : $semak('Antivirus fail muat naik', 'amaran', 'Tidak aktif', $antivirus['nota'].' Fail masih diterima tanpa imbasan.');

        $pentadbir = self::pentadbir();
        $tanpa2fa = $pentadbir->reject->duaFaktorAktif();
        $hasil[] = $tanpa2fa->isEmpty()
            ? $semak('2FA pentadbir', 'ok', 'Semua '.$pentadbir->count().' akaun pentadbir ada 2FA')
            : $semak('2FA pentadbir', config('services.dua_faktor.wajib') ? 'ralat' : 'amaran',
                $tanpa2fa->count().' daripada '.$pentadbir->count().' tanpa 2FA: '.$tanpa2fa->pluck('nama_staff')->take(5)->join(', '),
                'Pentadbir boleh hidupkan 2FA di Profil. Tetapkan DUA_FAKTOR_WAJIB=true semasa serahan.');

        $lemah = self::pentadbirKataLaluanLemah();
        $hasil[] = $lemah->isEmpty()
            ? $semak('Kata laluan pentadbir', 'ok', 'Tiada kata laluan lalai/lemah dikesan')
            : $semak('Kata laluan pentadbir', 'ralat', $lemah->count().' akaun guna kata laluan lalai/lemah: '.$lemah->pluck('nama_staff')->take(5)->join(', '),
                'Tukar segera. Penyerang mencuba kata laluan seperti 123456789 dahulu.');

        $hasil[] = self::semakSandaran($semak);
        $hasil[] = self::semakLuarTalian($semak);

        $integriti = self::statusJson('integriti.json');
        $hasil[] = ! $integriti
            ? $semak('Integriti fail imbasan', 'amaran', 'Belum pernah disemak', 'php artisan fail:integriti')
            : (($integriti['utuh'] ?? false)
                ? $semak('Integriti fail imbasan', 'ok', "{$integriti['disemak']} fail utuh · ".Carbon::parse($integriti['masa'])->diffForHumans())
                : $semak('Integriti fail imbasan', 'ralat', "{$integriti['hilang']} hilang, {$integriti['berubah']} berubah",
                    'Fail mungkin diubah atau disulitkan oleh penyerang. Pulihkan daripada sandaran luar talian.'));

        return $hasil;
    }

    private static function semakSandaran(callable $semak): array
    {
        $s = self::statusJson('terakhir.json');
        if (! $s) {
            return $semak('Sandaran harian', 'ralat', 'Belum pernah dibuat', 'php artisan sandaran:jalan');
        }
        $bila = Carbon::parse($s['masa']);
        if (! ($s['berjaya'] ?? false)) {
            return $semak('Sandaran harian', 'ralat', 'Gagal '.$bila->diffForHumans(), $s['ralat'] ?? null);
        }

        return $bila->lt(now()->subHours(36))
            ? $semak('Sandaran harian', 'amaran', $bila->diffForHumans(), 'Lebih 36 jam tanpa sandaran.')
            : $semak('Sandaran harian', 'ok', $bila->diffForHumans().(! empty($s['salinan']) ? ' · salinan kedua' : ''));
    }

    private static function semakLuarTalian(callable $semak): array
    {
        $s = self::statusJson('luar_talian.json');
        if (! $s) {
            return $semak('Salinan luar talian', 'ralat', 'Belum pernah dibuat',
                'Ransomware boleh memadam sandaran dalam pelayan & OneDrive. Salin ke cakera luaran yang kemudian DICABUT: php artisan sandaran:luar-talian E:\\');
        }
        $bila = Carbon::parse($s['masa']);

        return $bila->lt(now()->subDays(7))
            ? $semak('Salinan luar talian', 'amaran', $bila->diffForHumans(), 'Lebih 7 hari. Buat salinan baharu ke cakera luaran dan cabut selepas itu.')
            : $semak('Salinan luar talian', 'ok', $bila->diffForHumans());
    }

    // ------------------------------------------------------------------ Amaran

    /** Dipanggil selepas setiap log masuk gagal (AuthController). */
    public static function selepasGagal(?string $ip): void
    {
        if (! $ip) {
            return;
        }
        $bil = LogMasuk::where('berjaya', false)->where('alamat_ip', $ip)
            ->where('created_at', '>=', now()->subMinutes(self::MINIT_TETINGKAP))->count();
        if ($bil < self::AMBANG_GAGAL_IP) {
            return;
        }

        if (Cache::add("keselamatan:amaran-ip:{$ip}", true, now()->addHour())) {
            self::maklumkan('Cubaan teka kata laluan dikesan',
                "{$bil} log masuk gagal dari IP {$ip} dalam ".self::MINIT_TETINGKAP.' minit.', 'gagal-ip');
        }
    }

    /** Dipanggil selepas log masuk berjaya: pentadbir dari IP yang belum pernah digunakan (30 hari). */
    public static function selepasBerjaya(User $user, ?string $ip): void
    {
        if (! $ip || ! array_intersect(['SUPERADMIN', 'ADMIN'], $user->kodPerananSendiri())) {
            return;
        }
        $sejarah = LogMasuk::where('user_id', $user->user_id)->where('berjaya', true)
            ->where('created_at', '>=', now()->subDays(30));
        // Log masuk pertama yang direkod: tiada perbandingan, jangan beri amaran palsu
        if ((clone $sejarah)->count() <= 1 || (clone $sejarah)->where('alamat_ip', $ip)->count() > 1) {
            return;
        }

        self::maklumkan('Log masuk pentadbir dari IP baharu',
            "{$user->nama_staff} log masuk dari IP {$ip} yang tidak pernah digunakan dalam 30 hari. Jika bukan tuan/puan, kunci akaun dan tukar kata laluan segera.",
            "ip-baharu:{$user->user_id}:{$ip}", $user);
    }

    /** Semakan berkala (penjadual): sandaran terlalu lama. */
    public static function semakBerkala(): void
    {
        $s = self::statusJson('terakhir.json');
        $lama = ! $s || ! ($s['berjaya'] ?? false) || Carbon::parse($s['masa'])->lt(now()->subHours(26));
        if ($lama && Cache::add('keselamatan:amaran-sandaran', true, now()->addDay())) {
            self::maklumkan('Sandaran tertunggak', 'Tiada sandaran berjaya dalam 26 jam terakhir. Tanpa sandaran, data tidak boleh dipulihkan jika diserang.', 'sandaran');
        }
    }

    private static function maklumkan(string $tajuk, string $mesej, string $kunci, ?User $tambahan = null): void
    {
        Log::warning("[Keselamatan] {$tajuk}: {$mesej}");
        try {
            $penerima = self::pentadbir()->when($tambahan, fn ($c) => $c->push($tambahan))->unique('user_id');
            Notification::send($penerima, new NotifikasiDms('amaran_keselamatan', $tajuk, $mesej,
                route('keselamatan.index'), 'bi-shield-exclamation', 'merah', Notifikasi::PERANAN_PENTADBIR));
        } catch (\Throwable $e) {
            Log::warning("Amaran keselamatan \"{$kunci}\" gagal dihantar: ".$e->getMessage());
        }
    }

    // ------------------------------------------------------------------ Pembantu

    /** @return Collection<int, User> */
    public static function pentadbir(): Collection
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('kod_roles', Notifikasi::PERANAN_PENTADBIR))->get();
    }

    /** Disimpan 10 minit: bcrypt sengaja perlahan, jadi jangan semak setiap kali halaman dibuka. */
    public static function pentadbirKataLaluanLemah(): Collection
    {
        $id = Cache::remember('keselamatan:kata-laluan-lemah', now()->addMinutes(10), fn () => self::pentadbir()
            ->filter(fn ($u) => collect(self::KATA_LALUAN_LEMAH)->contains(fn ($k) => Hash::check($k, $u->password)))
            ->pluck('user_id')->all());

        return User::whereIn('user_id', $id)->get();
    }

    private static function pembolehUbahDb(string $nama): ?string
    {
        try {
            $baris = DB::selectOne('SHOW VARIABLES LIKE ?', [$nama]);

            return $baris ? (string) ($baris->Value ?? $baris->value ?? '') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function statusJson(string $fail): ?array
    {
        $laluan = rtrim((string) config('services.sandaran.folder'), '\\/')."/{$fail}";

        return File::exists($laluan) ? json_decode(File::get($laluan), true) : null;
    }
}
