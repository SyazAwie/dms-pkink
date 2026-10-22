<?php

namespace App\Http\Controllers;

use App\Models\LogAudit;
use App\Support\PolisiKataLaluan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Profil pengguna sendiri, termasuk skrin WAJIB tukar kata laluan bagi
 * pengguna yang memegang kata laluan sementara atau kata laluan yang
 * ditetapkan oleh admin (lihat middleware WajibTukarKataLaluan).
 */
class ProfilController extends Controller
{
    // Skrin wajib tukar kata laluan (selepas log masuk dengan kata laluan sementara)
    public function paparWajibTukar()
    {
        if (!Auth::user()->perlu_tukar_kata_laluan) {
            return redirect()->route('dashboard');
        }

        return view('auth.tukar-kata-laluan');
    }

    public function simpanWajibTukar(Request $request)
    {
        $user = Auth::user();

        if (!$user->perlu_tukar_kata_laluan) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'kata_laluan' => ['required', 'confirmed', PolisiKataLaluan::peraturan()],
        ], PolisiKataLaluan::mesej('kata_laluan'));

        if (Hash::check($request->kata_laluan, $user->password)) {
            return back()->withErrors([
                'kata_laluan' => 'Kata laluan baharu mesti berbeza daripada kata laluan sementara.',
            ]);
        }

        DB::transaction(function () use ($request, $user) {
            $user->password = Hash::make($request->kata_laluan);
            $user->perlu_tukar_kata_laluan = false;
            $user->kata_laluan_tamat_pada = null;
            $user->save();

            $this->logTukarKataLaluan($request, $user->getKey());
        });

        return redirect()->route('dashboard')
                         ->with('success', 'Kata laluan berjaya ditukar. Selamat datang ke Sistem Arkib Digital PKINK.');
    }

    /**
     * UserObserver tidak merekod kata laluan; catat kejadian tanpa nilainya.
     */
    private function logTukarKataLaluan(Request $request, $userId): void
    {
        LogAudit::create([
            'user_id' => Auth::id(),
            'tindakan' => 'Tukar Kata Laluan',
            'nama_jadual' => 'users',
            'rekod_id' => $userId,
            'data_lama' => null,
            'data_baharu' => null,
            'alamat_ip' => $request->ip(),
            'peranti_pengguna' => $request->userAgent(),
            'created_at' => now(),
        ]);
    }
}
