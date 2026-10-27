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

    public function bacaSemua()
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandakan sebagai dibaca.');
    }
}
