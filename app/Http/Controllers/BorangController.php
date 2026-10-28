<?php

namespace App\Http\Controllers;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\LogAudit;
use App\Support\SkopKelulusan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

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
 *   1. SATU rekod borang bagi setiap dokumen (updateOrCreate dalam hantar());
 *      hantar semula menimpa ulasan/tarikh dalam `borang`, tetapi setiap
 *      tindakan kekal dalam `borang_sejarah` (P4, BorangSejarahObserver).
 *   2. Skop (P5, App\Support\SkopKelulusan): PENYOKONG ikut bahagian dokumen
 *      (semua PENYOKONG jika bahagian itu tiada PENYOKONG); PELULUS semua
 *      bahagian. Delegasi semasa cuti melalui `delegasi_kelulusan`; tindakan
 *      penerima direkod "bagi pihak" pemberi. Tiada carta organisasi penuh.
 *   3. Notifikasi loceng + peranti dihantar oleh BorangNotifikasiObserver;
 *      emel hanya bila NOTIFIKASI_EMEL=true (mati secara lalai).
 *   4. Kebenaran disemak terus dalam controller (hasAnyRole), bukan
 *      Policy/Gate berasingan seperti DokumenPolicy — cukup untuk skop
 *      kecil sekarang, tapi patut dipindah ke BorangPolicy jika modul ini
 *      berkembang.
 *   5. Tiada had/syarat ke atas ulasan penolakan selain wajib diisi.
 * ============================================================================
 */
class BorangController extends Controller
{
    /** Status permohonan yang sah (tab penapis halaman Kelulusan). */
    public const STATUS = ['Dalam Proses', 'Disokong', 'Diluluskan', 'Ditolak'];

    /**
     * Senarai borang yang perlu tindakan (Penyokong/Pelulus) atau semua (Admin).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        // Borang bagi dokumen yang telah dipadam tidak dipaparkan
        $query = Borang::dokumenAktif()->with(['dokumen.jenisDokumen', 'pemohon', 'penyokong', 'pelulus']);

        $request->validate([
            'status' => ['nullable', Rule::in(self::STATUS)],
            'q' => 'nullable|string|max:100',
        ]);

        if ($user->hasAnyRole('SUPERADMIN', 'ADMIN')) {
            // Admin nampak semua borang; boleh tapis ikut status (P10)
            $query->when($request->filled('status'), fn ($q) => $q->where('status_permohonan', $request->status));
        } elseif ($user->hasRole('PENYOKONG')) {
            $query->where('status_permohonan', 'Dalam Proses')->bukanMilik($user->user_id);
            SkopKelulusan::hadkanGiliranSokongan($query, $user);
        } elseif ($user->hasRole('PELULUS')) {
            $query->where('status_permohonan', 'Disokong')->bukanMilik($user->user_id);
        } else {
            abort(403, 'Anda tidak mempunyai kebenaran untuk mengakses halaman ini.');
        }

        // Carian No. Rujukan atau nama pemohon (P10)
        if ($request->filled('q')) {
            $kata = '%'.$request->q.'%';
            $query->where(fn ($q) => $q->whereHas('dokumen', fn ($d) => $d->where('no_rujukan', 'like', $kata))
                ->orWhereHas('pemohon', fn ($p) => $p->where('nama_staff', 'like', $kata)));
        }

        $senarai = $query->orderByDesc('borang_id')->paginate(15)->withQueryString();

        // Bilangan setiap status untuk tab pentadbir
        $bilStatus = $user->hasAnyRole('SUPERADMIN', 'ADMIN')
            ? Borang::dokumenAktif()->selectRaw('status_permohonan, COUNT(*) as jumlah')->groupBy('status_permohonan')->pluck('jumlah', 'status_permohonan')
            : collect();

        return view('kelulusan.index', compact('senarai', 'bilStatus'));
    }

    /**
     * Hantar dokumen untuk kelulusan, atau hantar semula selepas ditolak.
     * Guna skop kebenaran yang sama seperti edit dokumen (DokumenPolicy::update).
     */
    public function hantar(Request $request, $dokumenId)
    {
        $dokumen = Dokumen::findOrFail($dokumenId);
        Gate::authorize('update', $dokumen);

        // Hantar hanya jika belum pernah dihantar, atau selepas ditolak. Tanpa semakan
        // ini, POST terus boleh menetapkan semula borang Disokong/Diluluskan (termasuk
        // arkib Imbas & Arkib) kepada "Dalam Proses" dan memadam rekod kelulusannya.
        $sedia = Borang::where('dokumen_id', $dokumen->dokumen_id)->first();
        if ($sedia && $sedia->status_permohonan !== 'Ditolak') {
            abort(409, "Permohonan ini kini berstatus \"{$sedia->status_permohonan}\" dan tidak boleh dihantar semula. Hanya permohonan yang ditolak boleh dihantar semula.");
        }

        $borang = Borang::updateOrCreate(
            ['dokumen_id' => $dokumen->dokumen_id],
            [
                'pemohon_id' => Auth::id(),
                'pegawai_penyokong_id' => null,
                'pegawai_pelulus_id' => null,
                'pegawai_penolak_id' => null,
                'status_permohonan' => 'Dalam Proses',
                'tarikh_permohonan' => now(),
                'tarikh_sokongan' => null,
                'tarikh_kelulusan' => null,
                'tarikh_tolak' => null,
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
        $this->pastikanBukanPemohon($borang);
        $this->pastikanStatus($borang, 'Dalam Proses');
        $this->pastikanSkopSokongan($borang);

        $request->validate(['ulasan_penyokong' => 'nullable|string|max:500']);

        DB::transaction(function () use ($request, $borang) {
            $borang->bagiPihakId = SkopKelulusan::bagiPihak(Auth::user(), 'PENYOKONG', $borang);
            $borang->update([
                'pegawai_penyokong_id' => Auth::id(),
                'status_permohonan' => 'Disokong',
                'tarikh_sokongan' => now(),
                'ulasan_penyokong' => $request->ulasan_penyokong,
            ]);

            $this->log($borang->dokumen, $borang, 'Sokong Permohonan');
        });

        return back()->with('success', 'Permohonan telah disokong.');
    }

    /** Pelulus: luluskan permohonan (Disokong -> Diluluskan). */
    public function lulus(Request $request, $dokumenId)
    {
        $this->pastikanPeranan('PELULUS');
        $borang = $this->borangAktif($dokumenId);
        $this->pastikanBukanPemohon($borang);
        $this->pastikanStatus($borang, 'Disokong');

        $request->validate(['ulasan_pelulus' => 'nullable|string|max:500']);

        DB::transaction(function () use ($request, $borang) {
            $borang->bagiPihakId = SkopKelulusan::bagiPihak(Auth::user(), 'PELULUS', $borang);
            $borang->update([
                'pegawai_pelulus_id' => Auth::id(),
                'status_permohonan' => 'Diluluskan',
                'tarikh_kelulusan' => now(),
                'ulasan_pelulus' => $request->ulasan_pelulus,
            ]);

            $this->log($borang->dokumen, $borang, 'Luluskan Permohonan');
        });

        return back()->with('success', 'Permohonan telah diluluskan.');
    }

    /** Penyokong ATAU Pelulus: tolak ikut peringkat semasa. Ulasan wajib. */
    public function tolak(Request $request, $dokumenId)
    {
        $borang = $this->borangAktif($dokumenId);
        $this->pastikanBukanPemohon($borang);
        $user = Auth::user();

        if ($borang->status_permohonan === 'Dalam Proses' && $user->hasAnyRole('PENYOKONG', 'SUPERADMIN', 'ADMIN')) {
            $this->pastikanSkopSokongan($borang);
            [$lajurUlasan, $peranan] = ['ulasan_penyokong', 'PENYOKONG'];
        } elseif ($borang->status_permohonan === 'Disokong' && $user->hasAnyRole('PELULUS', 'SUPERADMIN', 'ADMIN')) {
            [$lajurUlasan, $peranan] = ['ulasan_pelulus', 'PELULUS'];
        } else {
            abort(403, 'Anda tidak boleh menolak permohonan pada peringkat ini.');
        }

        $request->validate([$lajurUlasan => 'required|string|max:500']);

        DB::transaction(function () use ($request, $borang, $lajurUlasan, $peranan, $user) {
            $borang->bagiPihakId = SkopKelulusan::bagiPihak($user, $peranan, $borang);
            $borang->update([
                'status_permohonan' => 'Ditolak',
                $lajurUlasan => $request->input($lajurUlasan),
                'pegawai_penolak_id' => Auth::id(),
                'tarikh_tolak' => now(),
            ]);

            $this->log($borang->dokumen, $borang, 'Tolak Permohonan');
        });

        return back()->with('success', 'Permohonan telah ditolak.');
    }

    /**
     * Borang bagi dokumen yang telah dipadam dianggap tiada (404), supaya
     * tiada tindakan kelulusan boleh dibuat ke atasnya melalui POST terus.
     */
    private function borangAktif($dokumenId): Borang
    {
        return Borang::dokumenAktif()->with('dokumen')->where('dokumen_id', $dokumenId)->firstOrFail();
    }

    private function pastikanPeranan(string ...$peranan): void
    {
        if (! Auth::user()->hasAnyRole(...$peranan, ...['SUPERADMIN', 'ADMIN'])) {
            abort(403, 'Anda tidak mempunyai kebenaran untuk tindakan ini.');
        }
    }

    /**
     * Pemohon tidak boleh menyokong, meluluskan atau menolak permohonannya sendiri,
     * termasuk SUPERADMIN/ADMIN. Tindakan mesti dibuat oleh pegawai lain.
     */
    private function pastikanBukanPemohon(Borang $borang): void
    {
        if ($borang->dipohonOleh(Auth::id())) {
            abort(403, 'Anda tidak boleh menyokong, meluluskan atau menolak permohonan anda sendiri.');
        }
    }

    /** PENYOKONG hanya bertindak dalam skop bahagiannya (P5). */
    private function pastikanSkopSokongan(Borang $borang): void
    {
        if (! SkopKelulusan::bolehSokong(Auth::user(), $borang)) {
            abort(403, 'Permohonan ini di luar bahagian anda. Ia akan disokong oleh Penyokong bahagian berkenaan.');
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
