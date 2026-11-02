<?php

namespace App\Http\Controllers;

use App\Http\Middleware\HeaderKeselamatan;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\TemplatBorang;
use App\Rules\FailSelamat;
use App\Services\BorangSiapService;
use App\Services\TemplatBorangService;
use App\Support\Demo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Templat borang kosong bagi kategori dokumen (Fasa TB2). SUPERADMIN/ADMIN sahaja (kumpulan
 * laluan). Fail dalam folder Templat/ (disk `templat`), dihidangkan hanya melalui `papar`.
 * Tidak boleh diubah dalam mod demo/percubaan: folder Templat/ dikongsi dengan sistem sebenar.
 */
class TemplatBorangController extends Controller
{
    public const PERATURAN_FAIL = ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];

    public function store(Request $request, $jenisId, TemplatBorangService $servis)
    {
        $jenis = JenisDokumen::findOrFail($jenisId);
        if ($sekat = $this->sekatModDemo()) {
            return $sekat;
        }
        $request->validate([
            'templat' => ['required', ...self::PERATURAN_FAIL, new FailSelamat],
            'catatan_templat' => 'nullable|string|max:255',
        ], [
            'templat.required' => 'Sila pilih fail borang kosong.',
            'templat.mimes' => 'Templat mesti PDF, JPG atau PNG.',
            'templat.max' => 'Saiz templat maksimum 10MB.',
        ]);

        $templat = $servis->simpanMuatNaik($jenis, $request->file('templat'), Auth::id(), $request->input('catatan_templat'));
        $this->log('Muat Naik Templat', $templat, null, ['versi' => $templat->versi, 'nama_fail' => $templat->nama_asal]);

        return redirect()->route('jenis-dokumen.edit', $jenis->jenis_dokumen_id)
            ->with('success', "Templat versi {$templat->versi} dimuat naik. Teks borang kosong sedang dibaca (OCR).");
    }

    /** Pratonton fail templat (PDF/imej) dalam tab baharu. */
    public function papar($id)
    {
        $templat = TemplatBorang::findOrFail($id);
        abort_unless(Storage::disk(TemplatBorangService::DISK)->exists($templat->lokasi_fail), 404);

        return Storage::disk(TemplatBorangService::DISK)->response($templat->lokasi_fail, $templat->nama_asal, [
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => HeaderKeselamatan::CSP_FAIL,
        ]);
    }

    /** Aktif = digunakan dalam padanan imbasan; versi lama boleh dinyahaktifkan tanpa dipadam. */
    public function togol($id)
    {
        $templat = TemplatBorang::findOrFail($id);
        if ($sekat = $this->sekatModDemo()) {
            return $sekat;
        }
        $templat->update(['aktif' => ! $templat->aktif]);
        $this->log($templat->aktif ? 'Aktifkan Templat' : 'Nyahaktifkan Templat', $templat, ['aktif' => ! $templat->aktif], ['aktif' => $templat->aktif]);

        return back()->with('success', "Templat versi {$templat->versi} ".($templat->aktif ? 'diaktifkan.' : 'dinyahaktifkan.'));
    }

    /** Padam (soft delete): fail kekal dalam Templat/ untuk jejak audit; cache latar dibuang. */
    public function destroy($id)
    {
        $templat = TemplatBorang::findOrFail($id);
        if ($sekat = $this->sekatModDemo()) {
            return $sekat;
        }
        $templat->delete();
        $this->log('Padam Templat', $templat, ['versi' => $templat->versi, 'nama_fail' => $templat->nama_asal], null);
        // Imej latar borang siap (TB6) tidak diperlukan lagi; fail templat asal kekal untuk audit
        app(BorangSiapService::class)->bersihCache($templat);

        return back()->with('success', "Templat versi {$templat->versi} dipadam.");
    }

    private function sekatModDemo()
    {
        return Demo::mod() !== null
            ? back()->with('error', 'Templat borang tidak boleh diubah dalam mod demo/percubaan (folder Templat dikongsi dengan sistem sebenar).')
            : null;
    }

    private function log(string $tindakan, TemplatBorang $templat, ?array $lama, ?array $baharu): void
    {
        LogAudit::create([
            'user_id' => Auth::id(),
            'tindakan' => $tindakan,
            'nama_jadual' => 'templat_borang',
            'rekod_id' => $templat->templat_borang_id,
            'data_lama' => $lama,
            'data_baharu' => ['kategori' => $templat->jenisDokumen?->kod_dokumen] + ($baharu ?? []),
            'alamat_ip' => request()->ip(),
            'peranti_pengguna' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
