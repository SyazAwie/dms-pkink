<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // Paparkan halaman login
    public function paparkanLogin()
    {
        return view('auth.login');
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

                return back()->withErrors([
                    'ic_pekerja' => "Terlalu banyak percubaan log masuk. Cuba lagi dalam {$saat} saat.",
                ])->onlyInput('ic_pekerja');
            }
        }

        // 2. Semak di pangkalan data (Auth::attempt akan automatik cari ic_pekerja)
        if (Auth::attempt($credentials)) {
            // Kaunter IP sengaja TIDAK direset: penyerang boleh log masuk ke akaun
            // sendiri untuk mengosongkannya.
            RateLimiter::clear($kunciHad);
            $request->session()->regenerate();
            
            // Bawa ke dashboard selepas berjaya
            return redirect()->intended('dashboard'); 
        }

        // 3. Jika salah IC atau password
        RateLimiter::hit($kunciHad, 60);
        RateLimiter::hit($kunciHadIp, 600);

        return back()->withErrors([
            'ic_pekerja' => 'No. Kad Pengenalan atau Kata Laluan tidak dipadankan.',
        ])->onlyInput('ic_pekerja');
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