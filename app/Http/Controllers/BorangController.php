<?php

namespace App\Http\Controllers;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\LogAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * ============================================================================
 * ALIRAN KELULUSAN — VERSI ASAS (SENGAJA RINGKAS)
 * ============================================================================
 * Sistem ini baru digunakan oleh Bahagian Digital untuk scan dokumen sedia
 * ada secara digital. Aliran kelulusan penuh BELUM diperlukan di peringkat
 * ini, jadi modul ini dibina cukup untuk "wujud dan berfungsi", bukan
 * lengkap. Bila sistem sedia digunakan secara menyeluruh, sila semak dan
 * lengkapkan perkara berikut (lihat juga README.md > "Aliran Kelulusan"):
 *
 *   1. SATU rekod borang sahaja bagi setiap dokumen (updateOrCreate dalam
 *      hantar()) — hantar semula TIMPA ulasan/tarikh lama. Tiada sejarah
 *      berbilang penghantaran. Jika diperlukan kelak, tambah jadual
 *      `borang_sejarah` atau buang constraint 1:1 yang diandaikan di sini.
 *   2. Penyokong & Pelulus TIDAK diskop ikut bahagian — mana-mana pengguna
 *      berperanan PENYOKONG/PELULUS boleh bertindak ke atas borang
 *      mana-mana bahagian. Tiada carta organisasi/hierarki kelulusan.
 *   3. Tiada notifikasi (e-mel/loceng dalam sistem) apabila status berubah.
 *      Pemohon perlu semak sendiri status di halaman Butiran Dokumen.
 *   4. Kebenaran disemak terus dalam controller (hasAnyRole), bukan
 *      Policy/Gate berasingan seperti DokumenPolicy — cukup untuk skop
 *      kecil sekarang, tapi patut dipindah ke BorangPolicy jika modul ini
 *      berkembang.
 *   5. Tiada had/syarat ke atas ulasan penolakan selain wajib diisi.
 * ============================================================================
 */
class BorangController extends Controller
{
    /**
     * Senarai borang yang perlu tindakan (Penyokong/Pelulus) atau semua (Admin).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Borang::with(['dokumen.jenisDokumen', 'pemohon', 'penyokong', 'pelulus']);

        if ($user->hasAnyRole('SUPERADMIN', 'ADMIN')) {
            // Admin nampak semua borang, tanpa penapis status
        } elseif ($user->hasRole('PENYOKONG')) {
            $query->where('status_permohonan', 'Dalam Proses');
        } elseif ($user->hasRole('PELULUS')) {
            $query->where('status_permohonan', 'Disokong');
        } else {
            abort(403, 'Anda tidak mempunyai kebenaran untuk mengakses halaman ini.');
        }

        $senarai = $query->orderByDesc('borang_id')->paginate(15);

        return view('kelulusan.index', compact('senarai'));
    }

    /**
     * Hantar dokumen untuk kelulusan, atau hantar semula selepas ditolak.
     * Guna skop kebenaran yang sama seperti edit dokumen (DokumenPolicy::update).
     */
    public function hantar(Request $request, $dokumenId)
    {
        $dokumen = Dokumen::findOrFail($dokumenId);
        Gate::authorize('update', $dokumen);

        $borang = Borang::updateOrCreate(
            ['dokumen_id' => $dokumen->dokumen_id],
            [
                'pemohon_id' => Auth::id(),
                'pegawai_penyokong_id' => null,
                'pegawai_pelulus_id' => null,
                'status_permohonan' => 'Dalam Proses',
                'tarikh_permohonan' => now(),
                'tarikh_sokongan' => null,
                'tarikh_kelulusan' => null,
                'ulasan_penyokong' => null,
                'ulasan_pelulus' => null,
            ]
        );

        $this->log($dokumen, $borang, 'Hantar untuk Kelulusan');

        return back()->with('success', 'Dokumen telah dihantar untuk kelulusan.');
    }

    /** Penyokong: sokong permohonan (Dalam Proses -> Disokong). */
    public function sokong(Request $request, $dokumenId)
    {
        $this->pastikanPeranan('PENYOKONG');
        $borang = $this->borangAktif($dokumenId);
        $this->pastikanStatus($borang, 'Dalam Proses');

        $request->validate(['ulasan_penyokong' => 'nullable|string|max:500']);

        $borang->update([
            'pegawai_penyokong_id' => Auth::id(),
            'status_permohonan' => 'Disokong',
            'tarikh_sokongan' => now(),
            'ulasan_penyokong' => $request->ulasan_penyokong,
        ]);

        $this->log($borang->dokumen, $borang, 'Sokong Permohonan');

        return back()->with('success', 'Permohonan telah disokong.');
    }

    /** Pelulus: luluskan permohonan (Disokong -> Diluluskan). */
    public function lulus(Request $request, $dokumenId)
    {
        $this->pastikanPeranan('PELULUS');
        $borang = $this->borangAktif($dokumenId);
        $this->pastikanStatus($borang, 'Disokong');

        $request->validate(['ulasan_pelulus' => 'nullable|string|max:500']);

        $borang->update([
            'pegawai_pelulus_id' => Auth::id(),
            'status_permohonan' => 'Diluluskan',
            'tarikh_kelulusan' => now(),
            'ulasan_pelulus' => $request->ulasan_pelulus,
        ]);

        $this->log($borang->dokumen, $borang, 'Luluskan Permohonan');

        return back()->with('success', 'Permohonan telah diluluskan.');
    }

    /** Penyokong ATAU Pelulus: tolak ikut peringkat semasa. Ulasan wajib. */
    public function tolak(Request $request, $dokumenId)
    {
        $borang = $this->borangAktif($dokumenId);
        $user = Auth::user();

        if ($borang->status_permohonan === 'Dalam Proses' && $user->hasAnyRole('PENYOKONG', 'SUPERADMIN', 'ADMIN')) {
            $lajurUlasan = 'ulasan_penyokong';
        } elseif ($borang->status_permohonan === 'Disokong' && $user->hasAnyRole('PELULUS', 'SUPERADMIN', 'ADMIN')) {
            $lajurUlasan = 'ulasan_pelulus';
        } else {
            abort(403, 'Anda tidak boleh menolak permohonan pada peringkat ini.');
        }

        $request->validate([$lajurUlasan => 'required|string|max:500']);

        $borang->update([
            'status_permohonan' => 'Ditolak',
            $lajurUlasan => $request->input($lajurUlasan),
        ]);

        $this->log($borang->dokumen, $borang, 'Tolak Permohonan');

        return back()->with('success', 'Permohonan telah ditolak.');
    }

    private function borangAktif($dokumenId): Borang
    {
        return Borang::with('dokumen')->where('dokumen_id', $dokumenId)->firstOrFail();
    }

    private function pastikanPeranan(string ...$peranan): void
    {
        if (!Auth::user()->hasAnyRole(...$peranan, ...['SUPERADMIN', 'ADMIN'])) {
            abort(403, 'Anda tidak mempunyai kebenaran untuk tindakan ini.');
        }
    }

    private function pastikanStatus(Borang $borang, string $statusDijangka): void
    {
        if ($borang->status_permohonan !== $statusDijangka) {
            abort(409, "Permohonan ini kini berstatus \"{$borang->status_permohonan}\", bukan \"{$statusDijangka}\".");
        }
    }

    private function log(Dokumen $dokumen, Borang $borang, string $tindakan): void
    {
        LogAudit::create([
            'user_id' => Auth::id(),
            'tindakan' => $tindakan,
            'nama_jadual' => 'borang',
            'rekod_id' => $borang->borang_id,
            'data_lama' => null,
            'data_baharu' => [
                'dokumen' => $dokumen->no_rujukan,
                'status_permohonan' => $borang->status_permohonan,
            ],
            'alamat_ip' => request()->ip(),
            'peranti_pengguna' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}