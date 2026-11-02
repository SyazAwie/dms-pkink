<?php

namespace App\Http\Controllers;

use App\Models\Dokumen;
use App\Models\LogAudit;
use App\Models\TemplatBorang;
use App\Services\BorangSiapService;
use App\Services\CadangKedudukanService;
use App\Support\Demo;
use App\Support\JenisMedan;
use App\Support\KedudukanMedan;
use App\Support\MedanJadual;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Borang siap (TB6): templat borang kosong diisi dengan nilai dokumen.
 * - muatTurun: sesiapa yang boleh melihat dokumen (DokumenPolicy::view), sebarang status.
 * - susun / simpanSusunan / muka / pratonton: editor kedudukan medan, SUPERADMIN/ADMIN
 *   (kumpulan laluan). Simpanan disekat dalam mod demo/percubaan seperti perubahan templat.
 */
class BorangSiapController extends Controller
{
    public function __construct(private BorangSiapService $servis) {}

    public function muatTurun(Request $request, $id)
    {
        $dokumen = Dokumen::with(['jenisDokumen.fields', 'data', 'borang.penolak'])->findOrFail($id);
        Gate::authorize('view', $dokumen);

        $templat = $this->servis->templatUntuk($dokumen->jenis_dokumen_id);
        if (! $templat) {
            return back()->with('error', 'Kategori ini belum ada templat borang yang disusun. Sila hubungi Pentadbir Sistem.');
        }
        // Nama pegawai kekal pada cop walaupun akaunnya telah dipadam (seperti slip P6)
        $dokumen->borang?->load([
            'pemohon' => fn ($q) => $q->withTrashed(),
            'penyokong' => fn ($q) => $q->withTrashed(),
            'pelulus' => fn ($q) => $q->withTrashed(),
        ]);

        set_time_limit((int) config('services.ocr.had_masa', 300));
        $pengguna = $request->user();
        try {
            $pdf = $this->servis->jana(
                $templat,
                $this->servis->nilaiDokumen($dokumen),
                $this->servis->cop($dokumen),
                "Salinan terkawal | Dimuat turun oleh {$pengguna->nama_staff} pada ".now()->format('d/m/Y H:i')
                    ." | {$dokumen->no_rujukan} | Sistem Arkib Digital PKINK",
                $this->servis->asasDokumen($dokumen)
            );
        } catch (\Throwable $e) {
            Log::warning("Borang siap gagal bagi dokumen {$dokumen->dokumen_id}: ".$e->getMessage());

            return back()->with('error', 'Borang siap tidak dapat dijana. Sila hubungi Pentadbir Sistem.');
        }

        LogAudit::create([
            'user_id' => $pengguna->user_id, 'tindakan' => 'Muat Turun Borang Siap', 'nama_jadual' => 'dokumen',
            'rekod_id' => $dokumen->dokumen_id, 'data_lama' => null,
            'data_baharu' => ['dokumen' => $dokumen->no_rujukan, 'templat_versi' => $templat->versi, 'status' => $dokumen->borang?->status_permohonan ?? 'Belum Dihantar'],
            'alamat_ip' => $request->ip(), 'peranti_pengguna' => $request->userAgent(), 'created_at' => now(),
        ]);

        $nama = 'Borang_'.str_replace('/', '-', $dokumen->no_rujukan).'.pdf';

        return response()->streamDownload(fn () => print ($pdf), $nama, ['Content-Type' => 'application/pdf']);
    }

    /** Editor "Susun Kedudukan Medan" bagi satu versi templat. */
    public function susun($id)
    {
        $templat = TemplatBorang::with(['jenisDokumen.fields'])->findOrFail($id);
        abort_unless($templat->jenisDokumen, 404);

        try {
            $muka = $this->servis->latar($templat);
        } catch (\Throwable $e) {
            Log::warning("Latar templat {$templat->templat_borang_id} gagal: ".$e->getMessage());

            return redirect()->route('jenis-dokumen.edit', $templat->jenis_dokumen_id)
                ->with('error', 'Templat tidak dapat dipaparkan untuk disusun (fail rosak atau Poppler tiada).');
        }

        return view('templat.susun', [
            'templat' => $templat,
            'muka' => array_map(fn ($m) => ['lebar' => $m['lebar'], 'tinggi' => $m['tinggi']], $muka),
            'item' => $this->senaraiItem($templat),
            'kedudukan' => $templat->kedudukan_medan ?? ['medan' => []],
            'sekatDemo' => Demo::mod() !== null,
        ]);
    }

    public function simpanSusunan(Request $request, $id)
    {
        $templat = TemplatBorang::with(['jenisDokumen.fields'])->findOrFail($id);
        abort_unless($templat->jenisDokumen, 404);
        if (Demo::mod() !== null) {
            return response()->json(['mesej' => 'Susunan templat tidak boleh diubah dalam mod demo/percubaan.'], 403);
        }
        $request->validate(['kedudukan' => 'required|array']);

        $lama = $templat->kedudukan_medan;
        try {
            $baharu = KedudukanMedan::bersihkan($request->input('kedudukan'), $templat->jenisDokumen->fields, count($this->servis->latar($templat)));
            $templat->update(['kedudukan_medan' => $baharu]);
        } catch (\Throwable $e) {
            Log::warning("Simpan susunan templat {$templat->templat_borang_id} gagal: ".$e->getMessage());

            return response()->json(['mesej' => 'Susunan tidak dapat disimpan. Pastikan SQL TB6 (lajur kedudukan_medan) sudah dijalankan dan fail templat wujud.'], 500);
        }

        LogAudit::create([
            'user_id' => Auth::id(), 'tindakan' => 'Susun Kedudukan Medan', 'nama_jadual' => 'templat_borang',
            'rekod_id' => $templat->templat_borang_id,
            'data_lama' => ['medan_diletak' => KedudukanMedan::bilangan($lama)],
            'data_baharu' => ['kategori' => $templat->jenisDokumen->kod_dokumen, 'versi' => $templat->versi, 'medan_diletak' => KedudukanMedan::bilangan($baharu)],
            'alamat_ip' => $request->ip(), 'peranti_pengguna' => $request->userAgent(), 'created_at' => now(),
        ]);

        return response()->json(['mesej' => 'Susunan disimpan ('.KedudukanMedan::bilangan($baharu).' medan diletak).', 'kedudukan' => $baharu]);
    }

    /** Cadangan automatik kedudukan (tidak disimpan; editor hanya mengisi item yang belum diletak). */
    public function cadang($id, CadangKedudukanService $cadang)
    {
        $templat = TemplatBorang::with(['jenisDokumen.fields'])->findOrFail($id);
        abort_unless($templat->jenisDokumen, 404);
        set_time_limit((int) config('services.ocr.had_masa', 300));

        try {
            $hasil = $cadang->cadang($templat);
            $bersih = KedudukanMedan::bersihkan($hasil['kedudukan'], $templat->jenisDokumen->fields, count($this->servis->latar($templat)));
        } catch (\Throwable $e) {
            Log::warning("Cadang kedudukan templat {$templat->templat_borang_id} gagal: ".$e->getMessage());

            return response()->json(['mesej' => 'Cadangan automatik gagal (Tesseract tidak dapat membaca templat). Sila letak kotak secara manual.'], 500);
        }

        return response()->json(['kedudukan' => $bersih, 'dijumpai' => $hasil['dijumpai'], 'tidak' => $hasil['tidak']]);
    }

    /** Imej latar satu muka surat (untuk editor). */
    public function muka($id, int $no)
    {
        $templat = TemplatBorang::findOrFail($id);
        try {
            $muka = $this->servis->latar($templat)[$no - 1] ?? null;
        } catch (\Throwable $e) {
            Log::warning("Latar templat {$templat->templat_borang_id} gagal: ".$e->getMessage());
            $muka = null;
        }
        abort_unless($muka, 404);

        return response()->file($muka['fail'], ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=600']);
    }

    /** PDF pratonton dengan nilai contoh (susunan yang disimpan). */
    public function pratonton($id)
    {
        $templat = TemplatBorang::with(['jenisDokumen.fields'])->findOrFail($id);
        abort_unless($templat->jenisDokumen, 404);
        set_time_limit((int) config('services.ocr.had_masa', 300));
        try {
            $pdf = $this->servis->jana($templat, $this->servis->nilaiContoh($templat->jenisDokumen), [
                'label' => 'DILULUSKAN', 'warna' => [37, 140, 101], 'qr' => null, 'kod' => null,
                'baris' => ['No. Rujukan: CONTOH/2026/0001', 'Dihantar: Nama Pemohon · '.now()->format('d/m/Y'), 'Diluluskan: Nama Pelulus · '.now()->format('d/m/Y')],
            ], 'PRATONTON SUSUNAN | nilai contoh | '.$templat->jenisDokumen->kod_dokumen.' versi '.$templat->versi, $this->servis->asasContoh());
        } catch (\Throwable $e) {
            Log::warning("Pratonton borang siap templat {$templat->templat_borang_id} gagal: ".$e->getMessage());

            return response('Pratonton tidak dapat dijana (fail templat rosak atau Poppler tiada). Sila hubungi Pentadbir Sistem.', 500)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="pratonton.pdf"', 'Cache-Control' => 'no-store']);
    }

    /**
     * Item yang boleh diletak dalam editor: medan biasa (kotak), setiap pilihan, baris & lajur
     * jadual, dan cop.
     *
     * @return list<array{kunci: string, label: string, kumpulan: string, jenis: string}>
     */
    private function senaraiItem(TemplatBorang $templat): array
    {
        $item = [];
        foreach (KedudukanMedan::ASAS as $kunci => $label) {
            $item[] = ['kunci' => "asas:{$kunci}", 'label' => $label, 'kumpulan' => 'Maklumat asas dokumen', 'jenis' => 'asas', 'kod' => $kunci];
        }
        foreach ($templat->jenisDokumen->fields as $medan) {
            if (! JenisMedan::adaNilai($medan)) {
                continue;
            }
            $id = $medan->dokumen_field_id;
            $nama = $medan->nama_field;
            if ($medan->jenis_data === 'jadual') {
                $item[] = ['kunci' => "baris:{$id}", 'label' => 'Baris pertama', 'kumpulan' => $nama, 'jenis' => 'baris', 'medan' => $id];
                foreach (MedanJadual::lajur($medan) as $l) {
                    $item[] = ['kunci' => "lajur:{$id}:{$l['kod']}", 'label' => 'Lajur: '.$l['nama'], 'kumpulan' => $nama, 'jenis' => 'lajur', 'medan' => $id, 'kod' => $l['kod'],
                        'wang' => ($l['jenis'] ?? 'teks') === 'wang'];
                }

                continue;
            }
            $pilihan = KedudukanMedan::pilihanMedan($medan);
            $item[] = ['kunci' => "kotak:{$id}", 'label' => $pilihan ? 'Nilai sebagai teks' : $nama, 'kumpulan' => $pilihan ? $nama : '', 'jenis' => 'kotak', 'medan' => $id, 'textarea' => $medan->jenis_data === 'textarea', 'wang' => $medan->jenis_data === 'wang'];
            foreach ($pilihan as $p) {
                $item[] = ['kunci' => "pilihan:{$id}:{$p}", 'label' => 'Pilihan: '.($p === JenisMedan::NILAI_LAIN ? 'Lain-lain (nyatakan)' : $p), 'kumpulan' => $nama, 'jenis' => 'pilihan', 'medan' => $id, 'pilihan' => $p];
            }
        }
        $item[] = ['kunci' => 'cop', 'label' => 'Cop status + QR', 'kumpulan' => '', 'jenis' => 'cop'];

        return $item;
    }
}
