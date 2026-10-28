<?php

namespace App\Http\Controllers;

use App\Models\LogAudit;
use App\Support\DuaFaktor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Pengurusan 2FA pengguna sendiri dari halaman Profil (P13):
 * mula (jana rahsia dalam sesi + QR) → sahkan kod → aktif + kod pemulihan;
 * matikan / jana semula kod pemulihan memerlukan kata laluan semasa.
 * Bila DUA_FAKTOR_WAJIB=true, SUPERADMIN/ADMIN tidak boleh mematikannya.
 */
class DuaFaktorController extends Controller
{
    /** Rahsia yang belum disahkan (hanya dalam sesi sehingga kod pertama betul). */
    public const SESI_RAHSIA_BAHARU = 'dua_faktor_rahsia_baharu';

    public function mula(Request $request)
    {
        abort_if($request->user()->duaFaktorAktif(), 409, '2FA sudah aktif.');
        $request->session()->put(self::SESI_RAHSIA_BAHARU, DuaFaktor::rahsiaBaharu());

        return redirect()->to(route('profil.index').'#dua-faktor');
    }

    public function sahkan(Request $request)
    {
        $user = $request->user();
        $rahsia = $request->session()->get(self::SESI_RAHSIA_BAHARU);
        if (! $rahsia) {
            return redirect()->to(route('profil.index').'#dua-faktor')->with('error', 'Sesi pendaftaran 2FA tamat. Sila mula semula.');
        }

        $request->validateWithBag('duaFaktor', ['kod' => 'required|string|max:10'], ['kod.required' => 'Sila masukkan kod 6 digit daripada aplikasi.']);
        if (! DuaFaktor::sahKod($rahsia, $request->input('kod'))) {
            throw ValidationException::withMessages(['kod' => 'Kod tidak sah. Pastikan jam telefon tepat dan cuba kod terkini.'])
                ->errorBag('duaFaktor')->redirectTo(route('profil.index').'#dua-faktor');
        }

        $kodPemulihan = DuaFaktor::kodPemulihanBaharu();
        $user->forceFill([
            'dua_faktor_rahsia' => $rahsia,
            'dua_faktor_kod_pemulihan' => $kodPemulihan,
            'dua_faktor_disahkan_pada' => now(),
        ])->saveQuietly();
        $request->session()->forget(self::SESI_RAHSIA_BAHARU);
        $this->log($request, 'Hidupkan 2FA');

        return redirect()->to(route('profil.index').'#dua-faktor')
            ->with('success', 'Pengesahan dua faktor kini aktif.')
            ->with('kod_pemulihan', $kodPemulihan);
    }

    public function matikan(Request $request)
    {
        $user = $request->user();
        if ($this->wajib($user)) {
            return back()->with('error', 'Pengesahan dua faktor wajib bagi peranan anda dan tidak boleh dimatikan.');
        }
        $this->pastikanKataLaluan($request);

        $user->forceFill(['dua_faktor_rahsia' => null, 'dua_faktor_kod_pemulihan' => null, 'dua_faktor_disahkan_pada' => null])->saveQuietly();
        $this->log($request, 'Matikan 2FA');

        return redirect()->to(route('profil.index').'#dua-faktor')->with('success', 'Pengesahan dua faktor telah dimatikan.');
    }

    public function janaKodPemulihan(Request $request)
    {
        $user = $request->user();
        abort_unless($user->duaFaktorAktif(), 409, '2FA belum aktif.');
        $this->pastikanKataLaluan($request);

        $kodPemulihan = DuaFaktor::kodPemulihanBaharu();
        $user->forceFill(['dua_faktor_kod_pemulihan' => $kodPemulihan])->saveQuietly();
        $this->log($request, 'Jana Semula Kod Pemulihan 2FA');

        return redirect()->to(route('profil.index').'#dua-faktor')
            ->with('success', 'Kod pemulihan baharu dijana. Kod lama tidak lagi sah.')
            ->with('kod_pemulihan', $kodPemulihan);
    }

    /** 2FA wajib bagi SUPERADMIN/ADMIN bila DUA_FAKTOR_WAJIB=true (Fasa H). */
    public static function wajib($user): bool
    {
        return (bool) config('services.dua_faktor.wajib')
            && (bool) array_intersect(['SUPERADMIN', 'ADMIN'], $user->kodPerananSendiri());
    }

    private function pastikanKataLaluan(Request $request): void
    {
        $request->validateWithBag('duaFaktor', ['kata_laluan' => 'required|string'], ['kata_laluan.required' => 'Sila masukkan kata laluan semasa.']);
        if (! Hash::check($request->input('kata_laluan'), $request->user()->password)) {
            throw ValidationException::withMessages(['kata_laluan' => 'Kata laluan semasa tidak tepat.'])
                ->errorBag('duaFaktor')->redirectTo(route('profil.index').'#dua-faktor');
        }
    }

    private function log(Request $request, string $tindakan): void
    {
        LogAudit::create([
            'user_id' => Auth::id(), 'tindakan' => $tindakan, 'nama_jadual' => 'users', 'rekod_id' => Auth::id(),
            'data_lama' => null, 'data_baharu' => null,
            'alamat_ip' => $request->ip(), 'peranti_pengguna' => $request->userAgent(), 'created_at' => now(),
        ]);
    }
}
