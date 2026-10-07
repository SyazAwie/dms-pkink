<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    // 1. Papar borang masukkan emel
    public function paparBorangRequest()
    {
        return view('auth.forgot-password');
    }

    // 2. Proses jana token dan hantar pautan
    public function hantarPautanReset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'Rekod emel ini tidak dijumpai dalam pangkalan data PKINK.'
        ]);

        // Laravel akan secara automatik jana token dan simpan dalam password_reset_tokens
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', 'Pautan pemulihan kata laluan telah dihantar. (Sila semak fail laravel.log)');
        }

        return back()->withErrors(['email' => 'Gagal menghantar pautan pemulihan.']);
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
            'password' => 'required|min:6|confirmed', // Mesti ada ruangan password_confirmation
            'token' => 'required'
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
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