<?php

namespace App\Http\Controllers;

use App\Models\Bahagian;
use App\Models\LogMasuk;
use App\Models\User;
use App\Support\DuaFaktor;
use App\Support\KeadaanKeselamatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /** Kunci sesi pengguna yang lulus kata laluan dan menunggu kod 2FA (P13). */
    public const SESI_DUA_FAKTOR = 'dua_faktor_menunggu';

    /** Tempoh (minit) untuk memasukkan kod 2FA selepas kata laluan betul. */
    public const MINIT_DUA_FAKTOR = 5;

    // Paparkan halaman login (termasuk senarai bahagian untuk modal Daftar Akaun Baharu)
    public function paparkanLogin()
    {
        $senaraiBahagian = Bahagian::orderBy('nama_bahagian')->get(['bahagian_id', 'kod_bahagian', 'nama_bahagian']);

        return view('auth.login', compact('senaraiBahagian'));
    }

    // Proses semakan data login menggunakan IC
    public function prosesLogin(Request $request)
    {
        // 1. Validasi input ic_pekerja dan password
        $credentials = $request->validate([
            'ic_pekerja' => ['required', 'string'],
            'password' => ['required'],
        ], [
            // Mesej ralat (optional) jika kotak dibiarkan kosong
            'ic_pekerja.required' => 'Sila masukkan No. Kad Pengenalan.',
            'password.required' => 'Sila masukkan Kata Laluan.'
        ]);

        // Had 5 percubaan gagal seminit bagi setiap gabungan No. IC + IP (elak teka kata laluan)
        $kunciHad = 'login|' . Str::lower($request->input('ic_pekerja')) . '|' . $request->ip();
        // Lapisan kedua ikut IP sahaja: 20 percubaan gagal setiap 10 minit, supaya satu
        // kata laluan tidak boleh dicuba merentas banyak No. IC dari IP yang sama.
        $kunciHadIp = 'login-ip|' . $request->ip();
        foreach ([$kunciHad => 5, $kunciHadIp => 20] as $kunci => $maksimum) {
            if (RateLimiter::tooManyAttempts($kunci, $maksimum)) {
                $saat = RateLimiter::availableIn($kunci);
                LogMasuk::rekod($request, $this->idIkutIc($credentials['ic_pekerja']), false, 'Disekat (terlalu banyak cubaan)');

                return back()->withErrors([
                    'ic_pekerja' => "Terlalu banyak percubaan log masuk. Cuba lagi dalam {$saat} saat.",
                ])->onlyInput('ic_pekerja');
            }
        }

        // 2. Semak kata laluan TANPA log masuk dahulu: akaun ber-2FA perlu kod sebelum sesi dibuka
        if (Auth::validate($credentials)) {
            /** @var User $user */
            $user = Auth::getLastAttempted();

            // Kata laluan sementara (emel pendaftaran) hanya sah 72 jam
            $tamat = $user->kata_laluan_tamat_pada;
            if ($tamat && $tamat->isPast()) {
                LogMasuk::rekod($request, $user->user_id, false, 'Kata laluan sementara tamat');

                return back()->withErrors([
                    'ic_pekerja' => 'Kata laluan sementara anda telah tamat tempoh. Sila guna "Lupa Kata Laluan?" untuk menetapkan kata laluan baharu.',
                ])->onlyInput('ic_pekerja');
            }

            // Fasa Keselamatan S3: akaun dikunci pentadbir / mod kecemasan (bukan SUPERADMIN)
            if ($sekatan = $this->sekatanAkaun($user)) {
                LogMasuk::rekod($request, $user->user_id, false, $sekatan[0]);

                return back()->withErrors(['ic_pekerja' => $sekatan[1]])->onlyInput('ic_pekerja');
            }

            // Kaunter IP sengaja TIDAK direset: penyerang boleh log masuk ke akaun
            // sendiri untuk mengosongkannya.
            RateLimiter::clear($kunciHad);
            $request->session()->regenerate();

            if ($user->duaFaktorAktif()) {
                $request->session()->put(self::SESI_DUA_FAKTOR, [
                    'user_id' => $user->user_id,
                    'sehingga' => now()->addMinutes(self::MINIT_DUA_FAKTOR)->timestamp,
                ]);

                return redirect()->route('dua-faktor.cabaran');
            }

            Auth::login($user);
            $request->session()->put('masa_log_masuk', now()->timestamp);
            LogMasuk::rekod($request, $user->user_id, true);

            // Bawa ke dashboard selepas berjaya
            return redirect()->intended('dashboard');
        }

        // 3. Jika salah IC atau password
        RateLimiter::hit($kunciHad, 60);
        RateLimiter::hit($kunciHadIp, 600);
        $user = Auth::getLastAttempted();
        LogMasuk::rekod($request, $user?->user_id, false, $user ? 'Kata laluan salah' : 'No. KP tidak berdaftar');

        return back()->withErrors([
            'ic_pekerja' => 'No. Kad Pengenalan atau Kata Laluan tidak dipadankan.',
        ])->onlyInput('ic_pekerja');
    }

    /** Skrin kod 2FA selepas kata laluan betul (P13). */
    public function cabaranDuaFaktor(Request $request)
    {
        if (! $this->penggunaMenunggu($request)) {
            return redirect()->route('login')->withErrors(['ic_pekerja' => 'Sesi pengesahan tamat. Sila log masuk semula.']);
        }

        return view('auth.dua-faktor');
    }

    public function sahkanDuaFaktor(Request $request)
    {
        $user = $this->penggunaMenunggu($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['ic_pekerja' => 'Sesi pengesahan tamat. Sila log masuk semula.']);
        }

        $request->validate(['kod' => ['required', 'string', 'max:20']], ['kod.required' => 'Sila masukkan kod pengesahan.']);

        // 5 percubaan seminit bagi setiap akaun: kod 6 digit tidak boleh diteka secara kasar
        $kunci = 'dua-faktor|'.$user->user_id;
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            LogMasuk::rekod($request, $user->user_id, false, 'Disekat (kod 2FA)');

            return back()->withErrors(['kod' => 'Terlalu banyak percubaan. Cuba lagi dalam '.RateLimiter::availableIn($kunci).' saat.']);
        }

        $kod = $request->input('kod');
        $sebab = null;
        if (! DuaFaktor::sahKod($user->dua_faktor_rahsia, $kod)) {
            if (! DuaFaktor::gunaKodPemulihan($user, $kod)) {
                RateLimiter::hit($kunci, 60);
                LogMasuk::rekod($request, $user->user_id, false, 'Kod 2FA salah');

                return back()->withErrors(['kod' => 'Kod pengesahan tidak sah.']);
            }
            $sebab = 'Kod pemulihan digunakan';
        }

        RateLimiter::clear($kunci);
        $request->session()->forget(self::SESI_DUA_FAKTOR);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('masa_log_masuk', now()->timestamp);
        LogMasuk::rekod($request, $user->user_id, true, $sebab);

        $redirect = redirect()->intended('dashboard');

        return $sebab
            ? $redirect->with('warning', 'Anda log masuk dengan kod pemulihan. Baki kod: '.count($user->fresh()->dua_faktor_kod_pemulihan ?? []).'. Jana kod baharu di Profil jika hampir habis.')
            : $redirect;
    }

    private function penggunaMenunggu(Request $request): ?User
    {
        $menunggu = $request->session()->get(self::SESI_DUA_FAKTOR);
        if (! is_array($menunggu) || ($menunggu['sehingga'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::SESI_DUA_FAKTOR);

            return null;
        }

        $user = User::find($menunggu['user_id'] ?? null);

        return $user?->duaFaktorAktif() && ! $this->sekatanAkaun($user) ? $user : null;
    }

    /**
     * Sebab log masuk ditolak walaupun kata laluan betul (Fasa Keselamatan S3).
     *
     * @return array{0: string, 1: string}|null [sebab log, mesej pengguna]
     */
    private function sekatanAkaun(User $user): ?array
    {
        if ($user->dikunci()) {
            return ['Akaun dikunci', 'Akaun anda telah dikunci oleh pentadbir sistem. Sila hubungi Pentadbir Sistem.'];
        }
        if (KeadaanKeselamatan::kecemasan() && ! in_array('SUPERADMIN', $user->kodPerananSendiri(), true)) {
            return ['Mod kecemasan', 'Sistem kini dalam mod kecemasan. Hanya Super Admin boleh log masuk buat sementara waktu.'];
        }

        return null;
    }

    private function idIkutIc(string $ic): ?int
    {
        return User::where('ic_pekerja', $ic)->value('user_id');
    }

    // Proses log keluar
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
