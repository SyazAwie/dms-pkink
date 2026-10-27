<?php

namespace App\Http\Controllers;

use App\Support\Notifikasi;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

/**
 * Notifikasi dalam sistem (loceng topbar & halaman Notifikasi). Senarai ditapis ikut
 * peranan aktif pengguna (lihat App\Support\Notifikasi::kelihatan).
 */
class NotifikasiController extends Controller
{
    public function index(Request $request)
    {
        $semua = Notifikasi::kelihatan(Auth::user(), $request->query('papar') === 'belum', 500);
        $halaman = LengthAwarePaginator::resolveCurrentPage();
        $senarai = new LengthAwarePaginator($semua->forPage($halaman, 20)->values(), $semua->count(), 20, $halaman,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('notifikasi.index', compact('senarai'));
    }

    /** JSON untuk loceng (semakan berkala setiap 30 s dan popup notifikasi baharu). */
    public function ringkasan()
    {
        $user = Auth::user();

        return response()->json([
            'belum_dibaca' => Notifikasi::kelihatan($user, true)->count(),
            'terkini' => Notifikasi::kelihatan($user, false, 30)->take(6)->map(fn ($n) => [
                'id' => $n->id,
                'tajuk' => $n->data['tajuk'] ?? 'Notifikasi',
                'mesej' => $n->data['mesej'] ?? '',
                'ikon' => $n->data['ikon'] ?? 'bi-bell',
                'warna' => $n->data['warna'] ?? 'biru',
                'url' => route('notifikasi.buka', $n->id),
                'masa' => $n->created_at?->diffForHumans(),
                'dibaca' => $n->read_at !== null,
            ])->values(),
        ]);
    }

    /** Buka notifikasi: tandakan dibaca, kemudian ke halaman berkaitan (cth. dokumen). */
    public function buka(string $id)
    {
        $notifikasi = Auth::user()->notifications()->findOrFail($id);
        $notifikasi->markAsRead();

        return redirect($notifikasi->data['url'] ?? route('dashboard'));
    }

    /**
     * Simpan langganan Web Push pelayar/peranti semasa (selepas pengguna membenarkan
     * notifikasi). Endpoint yang dahulunya milik pengguna lain dipindah kepada pengguna ini.
     */
    public function langgan(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:1024', function ($atribut, $nilai, $gagal) {
                if (! self::endpointPushSah((string) $nilai)) {
                    \Illuminate\Support\Facades\Log::info('Langganan peranti ditolak: hos ' . (parse_url((string) $nilai, PHP_URL_HOST) ?: '(tiada)'));
                    $gagal('Alamat langganan bukan daripada perkhidmatan notifikasi pelayar yang dikenali.');
                }
            }],
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
            'contentEncoding' => 'nullable|in:aesgcm,aes128gcm',
        ]);

        Auth::user()->updatePushSubscription($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aes128gcm');

        return response()->json(['mesej' => 'Notifikasi peranti dihidupkan.']);
    }

    /**
     * Pelayan menghantar POST ke endpoint langganan setiap kali notifikasi dihantar, jadi hanya
     * perkhidmatan push pelayar yang dikenali dibenarkan (elak SSRF ke alamat dalaman seperti
     * Ollama di 127.0.0.1): FCM (Chrome, Android, Opera, Samsung), Mozilla (Firefox),
     * WNS (Edge di Windows) dan Apple (Safari / iPhone).
     */
    public static function endpointPushSah(string $endpoint): bool
    {
        if (strtolower((string) parse_url($endpoint, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }
        $hos = rtrim(strtolower((string) parse_url($endpoint, PHP_URL_HOST)), '.');

        return in_array($hos, ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com'], true)
            || str_ends_with($hos, '.notify.windows.com')
            || str_ends_with($hos, '.push.apple.com');
    }

    /** Buang langganan peranti semasa (dimatikan atau sebelum log keluar pada peranti dikongsi). */
    public function hentiLanggan(Request $request)
    {
        $request->validate(['endpoint' => 'required|string|max:1024']);
        Auth::user()->deletePushSubscription($request->input('endpoint'));

        return response()->json(['mesej' => 'Notifikasi peranti dimatikan.']);
    }

    public function bacaSemua()
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandakan sebagai dibaca.');
    }
}
