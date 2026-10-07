<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        // 2. Semak di pangkalan data (Auth::attempt akan automatik cari ic_pekerja)
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
            // Bawa ke dashboard selepas berjaya
            return redirect()->intended('dashboard'); 
        }

        // 3. Jika salah IC atau password
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