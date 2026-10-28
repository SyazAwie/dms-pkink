<?php

namespace App\Http\Controllers;

use App\Models\LogAudit;
use App\Models\LogMasuk;
use App\Support\DuaFaktor;
use App\Support\PeraturanNama;
use App\Support\PeraturanProfil;
use App\Support\PolisiKataLaluan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Profil pengguna sendiri, termasuk skrin WAJIB tukar kata laluan bagi
 * pengguna yang memegang kata laluan sementara atau kata laluan yang
 * ditetapkan oleh admin (lihat middleware WajibTukarKataLaluan).
 */
class ProfilController extends Controller
{
    // Halaman profil: maklumat akaun, tukar kata laluan, 2FA & log masuk terkini (P13)
    public function index()
    {
        $user = Auth::user()->load(['bahagian', 'roles']);

        // Pendaftaran 2FA yang sedang berjalan: rahsia dalam sesi, QR untuk diimbas
        $rahsiaBaharu = $user->duaFaktorAktif() ? null : session(DuaFaktorController::SESI_RAHSIA_BAHARU);
        $qrDuaFaktor = $rahsiaBaharu ? DuaFaktor::qrSvg($user, $rahsiaBaharu) : null;
        $duaFaktorWajib = DuaFaktorController::wajib($user);
        $logMasukTerkini = LogMasuk::where('user_id', $user->user_id)->latest('log_masuk_id')->limit(5)->get();

        return view('profil.index', compact('user', 'rahsiaBaharu', 'qrDuaFaktor', 'duaFaktorWajib', 'logMasukTerkini'));
    }

    /**
     * Kemaskini nama, emel, jawatan & no. telefon. No. KP (ID log masuk) dan bahagian
     * (skop PENGURUS & dokumen) hanya boleh diubah oleh admin. Tukar emel memerlukan
     * kata laluan semasa kerana emel digunakan untuk tetapan semula kata laluan.
     */
    public function kemaskiniMaklumat(Request $request)
    {
        $user = Auth::user();
        $tukarEmel = Str::lower(trim((string) $request->email)) !== Str::lower($user->email);

        $data = $request->validateWithBag('maklumat', [
            'nama_staff' => PeraturanNama::peraturan(),
            'email' => ['required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')->whereNull('deleted_at')],
            'kata_laluan_semasa' => [$tukarEmel ? 'required' : 'nullable', 'current_password'],
        ] + PeraturanProfil::peraturan(), PeraturanNama::mesej() + PeraturanProfil::mesej() + [
            'email.required' => 'Sila masukkan alamat emel.',
            'email.email' => 'Format alamat emel tidak sah.',
            'email.max' => 'Alamat emel terlalu panjang.',
            'email.unique' => 'Emel ini sudah digunakan oleh akaun lain.',
            'kata_laluan_semasa.required' => 'Sila masukkan kata laluan semasa untuk menukar emel.',
            'kata_laluan_semasa.current_password' => 'Kata laluan semasa tidak betul.',
        ]);

        // UserObserver merekod perubahan maklumat dalam log audit
        $user->fill([
            'nama_staff' => $data['nama_staff'],
            'jawatan' => $data['jawatan'] ?? null,
            'email' => $data['email'],
            'no_telefon' => $data['no_telefon'] ?? null,
        ]);

        if (!$user->isDirty()) {
            return redirect()->route('profil.index')->with('success', 'Tiada perubahan pada maklumat profil.');
        }

        $user->save();

        return redirect()->route('profil.index')->with('success', 'Maklumat profil berjaya dikemas kini.');
    }

    public function kemaskiniKataLaluan(Request $request)
    {
        $user = Auth::user();

        $request->validateWithBag('kataLaluan', [
            'kata_laluan_lama' => ['required', 'current_password'],
            'kata_laluan' => ['required', 'confirmed', PolisiKataLaluan::peraturan()],
        ], PolisiKataLaluan::mesej('kata_laluan') + [
            'kata_laluan_lama.required' => 'Sila masukkan kata laluan semasa.',
            'kata_laluan_lama.current_password' => 'Kata laluan semasa tidak betul.',
        ]);

        if (Hash::check($request->kata_laluan, $user->password)) {
            return back()->withErrors([
                'kata_laluan' => 'Kata laluan baharu mesti berbeza daripada kata laluan semasa.',
            ], 'kataLaluan');
        }

        DB::transaction(function () use ($request, $user) {
            $user->password = Hash::make($request->kata_laluan);
            $user->save();

            $this->logTukarKataLaluan($request, $user->getKey());
        });

        return redirect()->route('profil.index')->with('success', 'Kata laluan berjaya ditukar.');
    }

    /**
     * Tukar peranan aktif (menu nama di topbar) tanpa log masuk semula. Satu peranan pada
     * satu masa: hanya peranan yang dimiliki (termasuk delegasi aktif) boleh dipilih;
     * disimpan dalam sesi dan ditapis oleh User::kodPeranan(). Dibawa ke papan pemuka
     * kerana halaman semasa mungkin tidak dibenarkan bagi peranan baharu.
     */
    public function tukarPeranan(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'peranan' => ['required', 'string', Rule::in($user->semuaKodPeranan())],
        ], [
            'peranan.required' => 'Sila pilih satu peranan.',
            'peranan.in' => 'Anda tidak mempunyai peranan tersebut.',
        ]);

        $kod = $request->input('peranan');
        $request->session()->put(\App\Models\User::SESI_PERANAN_AKTIF, $kod);
        $nama = \App\Models\Role::where('kod_roles', $kod)->value('nama_roles') ?? $kod;

        return redirect()->route('dashboard')->with('success', "Kini menggunakan peranan {$nama}.");
    }

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
