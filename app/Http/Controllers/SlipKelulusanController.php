<?php

namespace App\Http\Controllers;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\LogAudit;
use App\Services\SlipKelulusanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Slip kelulusan PDF ber-QR (P6).
 * - muatTurun: pengguna yang boleh melihat dokumen menjana slip bagi permohonan Diluluskan.
 * - sahkan: halaman AWAM (tanpa log masuk) yang dibuka oleh kod QR. Kod ialah HMAC
 *   (Borang::kodPengesahan), jadi rekod tidak boleh diteka; status dibaca semasa, jadi
 *   slip yang ditarik balik, dipadam atau diluluskan semula dipaparkan "tidak sah".
 *   Hanya maklumat minimum dipaparkan (tiada medan borang, IC atau emel).
 */
class SlipKelulusanController extends Controller
{
    public function muatTurun(Request $request, $id)
    {
        $dokumen = Dokumen::with(['jenisDokumen', 'bahagian', 'borang'])->findOrFail($id);
        Gate::authorize('view', $dokumen);

        $borang = $dokumen->borang;
        if (! $borang || ! $borang->kodPengesahan()) {
            return back()->with('error', 'Slip kelulusan hanya boleh dijana bagi permohonan yang telah diluluskan.');
        }
        $this->muatPegawai($borang);

        $pdf = app(SlipKelulusanService::class)->jana(
            $borang,
            route('slip.sahkan', ['borang' => $borang->borang_id, 'kod' => $borang->kodPengesahan()]),
            $request->user()->nama_staff
        );

        LogAudit::create([
            'user_id' => $request->user()->user_id, 'tindakan' => 'Jana Slip Kelulusan', 'nama_jadual' => 'borang',
            'rekod_id' => $borang->borang_id, 'data_lama' => null,
            'data_baharu' => ['dokumen' => $dokumen->no_rujukan, 'kod' => $borang->kodPengesahan()],
            'alamat_ip' => $request->ip(), 'peranti_pengguna' => $request->userAgent(), 'created_at' => now(),
        ]);

        $nama = 'Slip_Kelulusan_'.str_replace('/', '-', $dokumen->no_rujukan).'.pdf';

        return response()->streamDownload(fn () => print ($pdf), $nama, ['Content-Type' => 'application/pdf']);
    }

    public function sahkan($borangId, string $kod)
    {
        $borang = Borang::with(['dokumen.jenisDokumen'])->find($borangId);
        $kodSemasa = $borang?->dokumen ? $borang->kodPengesahan() : null;
        $sah = $kodSemasa !== null && hash_equals($kodSemasa, strtoupper($kod));

        if ($sah) {
            $this->muatPegawai($borang);
        }

        return response()->view('slip.sahkan', ['sah' => $sah, 'borang' => $sah ? $borang : null])
            ->header('X-Robots-Tag', 'noindex');
    }

    /** Nama pegawai kekal dipapar walaupun akaun telah dipadam. */
    private function muatPegawai(Borang $borang): void
    {
        $borang->load([
            'pemohon' => fn ($q) => $q->withTrashed(),
            'penyokong' => fn ($q) => $q->withTrashed(),
            'pelulus' => fn ($q) => $q->withTrashed(),
        ]);
    }
}
