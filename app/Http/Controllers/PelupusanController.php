<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use App\Models\LogAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Tempoh simpanan & pelupusan (P12), SUPERADMIN/ADMIN. Dokumen yang tamat tempoh
 * (dokumen.tarikh_tamat_simpanan) menunggu keputusan:
 * - Kekalkan: lanjutkan N tahun dengan catatan (status "Dikekalkan").
 * - Lupuskan / Serah Arkib Negara: ditanda dan dipindah ke Tong Sampah (soft delete).
 *   Fail imbasan KEKAL dalam storan (keputusan pengguna), jadi boleh dipulihkan jika
 *   tersilap; setiap keputusan direkod dalam log audit.
 */
class PelupusanController extends Controller
{
    /** Tempoh "akan tamat" yang dipaparkan lebih awal (hari). */
    public const HARI_AMARAN = 90;

    public const TAB = ['tamat' => 'Tamat Tempoh', 'akan' => 'Akan Tamat', 'selesai' => 'Telah Dilupuskan'];

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), self::TAB) ? $request->query('tab') : 'tamat';

        $senarai = $this->query($tab)->with(['jenisDokumen', 'bahagian', 'pelupus'])
            ->orderBy($tab === 'selesai' ? 'tarikh_pelupusan' : 'tarikh_tamat_simpanan', $tab === 'selesai' ? 'desc' : 'asc')
            ->paginate(20)->withQueryString();
        $bilangan = collect(array_keys(self::TAB))->mapWithKeys(fn ($t) => [$t => $this->query($t)->count()]);

        return view('pelupusan.index', compact('senarai', 'tab', 'bilangan'));
    }

    /** Bilangan dokumen tamat tempoh (lencana menu). */
    public static function bilanganTamat(): int
    {
        return Dokumen::whereDate('tarikh_tamat_simpanan', '<=', today())->count();
    }

    public function kekalkan(Request $request, $id)
    {
        $dokumen = Dokumen::whereDate('tarikh_tamat_simpanan', '<=', today()->addDays(self::HARI_AMARAN))->findOrFail($id);
        $data = $request->validate([
            'tahun' => 'required|integer|min:1|max:50',
            'catatan' => 'required|string|max:255',
        ], [], ['tahun' => 'tempoh lanjutan', 'catatan' => 'catatan']);

        $lama = $dokumen->tarikh_tamat_simpanan?->toDateString();
        $baharu = $dokumen->tarikh_tamat_simpanan->copy()->max(today())->addYears((int) $data['tahun'])->toDateString();
        $this->simpan($request, $dokumen, 'Kekalkan Dokumen', ['tarikh_tamat_simpanan' => $lama], [
            'status_pelupusan' => 'Dikekalkan',
            'tarikh_tamat_simpanan' => $baharu,
            'catatan_pelupusan' => $data['catatan'],
        ]);

        return back()->with('success', "{$dokumen->no_rujukan} dikekalkan hingga ".now()->parse($baharu)->format('d/m/Y').'.');
    }

    public function lupus(Request $request, $id)
    {
        $dokumen = Dokumen::whereDate('tarikh_tamat_simpanan', '<=', today())->findOrFail($id);
        $data = $request->validate([
            'kaedah' => ['required', Rule::in(Dokumen::STATUS_DILUPUS)],
            'catatan' => 'required|string|max:255',
        ], [], ['kaedah' => 'kaedah', 'catatan' => 'catatan']);

        DB::transaction(function () use ($request, $dokumen, $data) {
            $this->simpan($request, $dokumen, 'Lupus Dokumen', ['status_pelupusan' => $dokumen->status_pelupusan], [
                'status_pelupusan' => $data['kaedah'],
                'catatan_pelupusan' => $data['catatan'],
            ]);
            $dokumen->delete();
        });

        return back()->with('success', "{$dokumen->no_rujukan}: {$data['kaedah']}. Rekod dan fail kekal dalam Tong Sampah.");
    }

    private function query(string $tab)
    {
        return match ($tab) {
            'akan' => Dokumen::whereDate('tarikh_tamat_simpanan', '>', today())
                ->whereDate('tarikh_tamat_simpanan', '<=', today()->addDays(self::HARI_AMARAN)),
            'selesai' => Dokumen::onlyTrashed()->whereIn('status_pelupusan', Dokumen::STATUS_DILUPUS),
            default => Dokumen::whereDate('tarikh_tamat_simpanan', '<=', today()),
        };
    }

    private function simpan(Request $request, Dokumen $dokumen, string $tindakan, array $lama, array $baharu): void
    {
        // Senyap: DokumenObserver akan melog "Kemaskini"; log khusus di bawah lebih jelas
        $dokumen->forceFill($baharu + ['tarikh_pelupusan' => now(), 'pelupusan_oleh' => Auth::id()])->saveQuietly();

        LogAudit::create([
            'user_id' => Auth::id(), 'tindakan' => $tindakan, 'nama_jadual' => 'dokumen', 'rekod_id' => $dokumen->dokumen_id,
            'data_lama' => $lama, 'data_baharu' => ['dokumen' => $dokumen->no_rujukan] + $baharu,
            'alamat_ip' => $request->ip(), 'peranti_pengguna' => $request->userAgent(), 'created_at' => now(),
        ]);
    }
}
