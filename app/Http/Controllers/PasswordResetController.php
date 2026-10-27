<?php

namespace App\Http\Controllers;

use App\Support\PolisiKataLaluan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    // 1. Papar borang masukkan emel
    public function paparBorangRequest()
    {
        return view('auth.forgot-password');
    }

    // 2. Proses jana token dan hantar pautan ke emel pengguna
    public function hantarPautanReset(Request $request)
    {
        // Had 5 permintaan seminit bagi setiap gabungan emel + IP. Disemak sebelum
        // validasi supaya permintaan yang ditolak pun turut dikira.
        $kunciHad = 'lupa-kata-laluan|' . Str::lower((string) $request->input('email')) . '|' . $request->ip();
        // Lapisan kedua ikut IP sahaja: 10 permintaan setiap 10 minit, supaya banyak
        // emel berbeza tidak boleh dicuba dari IP yang sama.
        $kunciHadIp = 'lupa-kata-laluan-ip|' . $request->ip();
        foreach ([$kunciHad => 5, $kunciHadIp => 10] as $kunci => $maksimum) {
            if (RateLimiter::tooManyAttempts($kunci, $maksimum)) {
                $saat = RateLimiter::availableIn($kunci);

                return back()->withInput()->withErrors([
                    'email' => "Terlalu banyak permintaan. Cuba lagi dalam {$saat} saat.",
                ]);
            }
        }
        RateLimiter::hit($kunciHad, 60);
        RateLimiter::hit($kunciHadIp, 600);

        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Sila masukkan alamat emel.',
            'email.email' => 'Format alamat emel tidak sah.',
        ]);

        // Respons sama sama ada emel wujud, tiada, di-throttle atau SMTP gagal, supaya
        // borang ini tidak boleh digunakan untuk meneka emel yang berdaftar.
        try {
            // Laravel jana token, simpan dalam password_reset_tokens, dan hantar emel
            // (kandungan emel ditetapkan dalam AppServiceProvider -> ResetPassword::toMailUsing)
            $status = Password::sendResetLink($request->only('email'));

            if ($status !== Password::RESET_LINK_SENT) {
                Log::info("Lupa kata laluan: pautan tidak dihantar ({$status}).");
            }
        } catch (\Throwable $e) {
            // Biasanya masalah tetapan SMTP (.env MAIL_*): kata laluan aplikasi salah, port disekat, dll.
            Log::error('Gagal hantar emel tetapan semula kata laluan: ' . $e->getMessage());
        }

        return back()->with(
            'success',
            'Jika emel ini berdaftar dalam sistem, pautan tetapan semula kata laluan telah dihantar. Sila semak peti masuk (dan folder Spam).'
        );
    }

    // 3. Papar borang tukar kata laluan (selepas klik pautan di emel)
    public function paparBorangReset(Request $request, $token)
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    // 4. Proses simpan kata laluan baharu
    public function kemaskiniKataLaluan(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PolisiKataLaluan::peraturan()], // Mesti ada ruangan password_confirmation
            'token' => 'required'
        ], PolisiKataLaluan::mesej('password'));

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                // Kata laluan kini dipilih sendiri oleh pengguna: tiada lagi
                // kata laluan sementara yang perlu ditukar atau tamat tempoh.
                $user->forceFill([
                    'password' => Hash::make($password),
                    'perlu_tukar_kata_laluan' => false,
                    'kata_laluan_tamat_pada' => null,
                ])->setRememberToken(Str::random(60));

                $user->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
                    ? redirect('/')->with('success', 'Kemas kini berjaya! Sila log masuk menggunakan kata laluan baharu.')
                    : back()->withErrors(['email' => ['Gagal menukar kata laluan. Pautan mungkin telah tamat tempoh.']]);
    }
}