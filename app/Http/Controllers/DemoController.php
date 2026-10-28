<?php

namespace App\Http\Controllers;

use App\Models\LogMasuk;
use App\Models\User;
use App\Support\Demo;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Mod Demo (Fasa D3/D7): log masuk dengan satu ketikan sebagai akaun demo (peserta tidak
 * menaip No. KP/kata laluan di telefon) dan halaman /demo (kod QR + senarai akaun) untuk
 * ditayang. Kedua-duanya 404 bila mod demo mati.
 */
class DemoController extends Controller
{
    /**
     * Akaun demo ikut susunan config demo.akaun, dengan peranan yang dipaparkan pada butang.
     *
     * @return Collection<int, array{ic: string, nama: string, peranan: string}>
     */
    public static function senaraiAkaun(): Collection
    {
        $susunan = Demo::akaun();
        $pengguna = User::with('roles')->whereIn('ic_pekerja', $susunan)->get()->keyBy(fn (User $u) => (string) $u->ic_pekerja);

        return collect($susunan)
            ->filter(fn (string $ic) => $pengguna->has($ic))
            ->map(fn (string $ic) => [
                'ic' => $ic,
                'nama' => $pengguna[$ic]->nama_staff,
                'peranan' => (string) User::perananLalai($pengguna[$ic]->kodPerananSendiri()),
            ])->values();
    }

    public function masuk(Request $request)
    {
        abort_unless(Demo::aktif(), 404);

        $data = $request->validate(['ic' => ['required', 'string', 'in:'.implode(',', Demo::akaun())]], [
            'ic.in' => 'Akaun demo tidak sah.',
        ]);
        $user = User::where('ic_pekerja', $data['ic'])->firstOrFail();

        $request->session()->regenerate();
        Auth::login($user);
        $request->session()->put('masa_log_masuk', now()->timestamp);
        LogMasuk::rekod($request, $user->user_id, true, 'Mod demo');

        return redirect()->route('dashboard');
    }

    /** Halaman tayangan: kod QR pautan + senarai akaun demo (mod demo) atau langkah daftar (mod percubaan). */
    public function halaman()
    {
        abort_unless(Demo::mod() !== null, 404);

        $url = Demo::urlTerowong() ?? url('/');
        $qr = (new Writer(new ImageRenderer(new RendererStyle(320, 1), new SvgImageBackEnd)))->writeString($url);

        return view('demo.index', [
            'url' => $url,
            'qr' => $qr,
            'percubaan' => Demo::percubaan(),
            'akaun' => Demo::aktif() ? self::senaraiAkaun() : collect(),
            'adaTerowong' => Demo::urlTerowong() !== null,
        ]);
    }
}
