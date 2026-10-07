<?php

namespace App\Http\Controllers;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenOcr;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * IMBAS & ARKIB — untuk dokumen FIZIKAL yang SEDIA LULUS sebelum sistem ini
 * digunakan (cth. fail lama Bahagian Digital). BUKAN untuk dokumen baharu
 * yang masih perlu melalui aliran kelulusan Penyokong/Pelulus — guna
 * "Muat Naik Dokumen" biasa untuk itu.
 *
 * Alur: (1) pilih Jenis Dokumen + satu fail imbasan → (2) OCR + AI tempatan
 * (Ollama) cuba isi medan secara automatik → (3) STAFF SEMAK & BETULKAN
 * sebelum simpan → (4) simpan, Borang terus berstatus "Diluluskan" (tiada
 * Penyokong/Pelulus sebenar — ini arkib, bukan permohonan baharu).
 *
 * Warisi DokumenController untuk guna semula peraturanMedan() & janaNoRujukan()
 * (protected) — elak duplikasi logik.
 * ============================================================================
 */
class ImbasArkibController extends DokumenController
{
    // Borang: pilih Jenis Dokumen + satu fail
    public function create()
    {
        Gate::authorize('create', Dokumen::class);

        $jenisDokumenSenarai = JenisDokumen::with('fields')->orderBy('nama_dokumen')->get();
        return view('imbas_arkib.create', compact('jenisDokumenSenarai'));
    }

    // OCR + ekstrak AI, papar borang semakan (TIDAK simpan ke DB lagi)
    public function analisis(Request $request)
    {
        Gate::authorize('create', Dokumen::class);

        $request->validate([
            'jenis_dokumen_id' => 'required|exists:jenis_dokumen,jenis_dokumen_id',
            'fail_scan' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $jenisDokumen = JenisDokumen::with('fields')->findOrFail($request->jenis_dokumen_id);

        // Simpan fail terus ke disk peribadi, tapi dalam folder SEMENTARA
        // (dokumen belum wujud lagi — no_rujukan belum dijana).
        $fail = $request->file('fail_scan');
        $tokenSementara = Str::random(20);
        $namaAsal = $fail->getClientOriginalName();
        $laluanSementara = $fail->storeAs('ocr_sementara', $tokenSementara . '_' . $namaAsal, 'local');

        $mesejAmaran = null;
        $teksOcr = '';
        $skorOcr = null;
        $nilaiEkstrak = [];

        try {
            $hasilOcr = app(OcrService::class)->proses($laluanSementara);
            $teksOcr = $hasilOcr['teks'];
            $skorOcr = $hasilOcr['skor'];

            if ($teksOcr === '') {
                $mesejAmaran = 'OCR tidak kesan sebarang teks pada fail ini. Sila isi semua medan secara manual.';
            } else {
                $nilaiEkstrak = app(OllamaExtractionService::class)->ekstrak($teksOcr, $jenisDokumen->fields);
            }
        } catch (\Throwable $e) {
            $mesejAmaran = 'OCR/AI gagal diproses automatik: ' . $e->getMessage() . ' — sila isi semua medan secara manual.';
        }

        return view('imbas_arkib.semak', [
            'jenisDokumen' => $jenisDokumen,
            'namaAsal' => $namaAsal,
            'laluanSementara' => $laluanSementara,
            'teksOcr' => $teksOcr,
            'skorOcr' => $skorOcr,
            'nilaiEkstrak' => $nilaiEkstrak,
            'mesejAmaran' => $mesejAmaran,
        ]);
    }

    // Staf sahkan/betulkan nilai, simpan Dokumen + data + scan + OCR + Borang (terus Diluluskan)
    public function simpan(Request $request)
    {
        Gate::authorize('create', Dokumen::class);

        $request->validate([
            'jenis_dokumen_id' => 'required|exists:jenis_dokumen,jenis_dokumen_id',
            'laluan_sementara' => 'required|string',
            'nama_asal' => 'required|string',
            'tarikh_dokumen' => 'required|date',
            'perkara' => 'nullable|max:255',
        ]);

        if (!Storage::disk('local')->exists($request->laluan_sementara)) {
            return back()->withInput()->withErrors([
                'laluan_sementara' => 'Fail sementara tidak dijumpai (mungkin sesi tamat tempoh). Sila mula semula dari langkah muat naik.',
            ]);
        }

        $jenisDokumen = JenisDokumen::with('fields')->findOrFail($request->jenis_dokumen_id);
        $request->validate($this->peraturanMedan($jenisDokumen));

        $dokumen = DB::transaction(function () use ($request, $jenisDokumen) {
            $noRujukan = $this->janaNoRujukan($jenisDokumen, $request->tarikh_dokumen);

            $dokumenBaharu = Dokumen::create([
                'jenis_dokumen_id' => $jenisDokumen->jenis_dokumen_id,
                'no_rujukan' => $noRujukan,
                'tarikh_dokumen' => $request->tarikh_dokumen,
                'perkara' => $request->perkara,
                'status' => 'Archived',
                'bahagian_id' => Auth::user()->bahagian_id,
                'created_by' => Auth::id(),
            ]);

            // Pindah fail daripada folder sementara ke lokasi kekal
            $namaFolder = Str::slug($noRujukan, '_');
            $laluanBaharu = "dokumen_scan/{$namaFolder}/" . basename($request->laluan_sementara);
            Storage::disk('local')->move($request->laluan_sementara, $laluanBaharu);

            $scan = DokumenScan::create([
                'dokumen_id' => $dokumenBaharu->dokumen_id,
                'nama_fail' => $request->nama_asal,
                'lokasi_fail' => $laluanBaharu,
                'status_ocr' => $request->teks_ocr ? 'Selesai' : 'Tiada Teks Dikesan',
            ]);

            if ($request->filled('teks_ocr')) {
                DokumenOcr::create([
                    'dokumen_scan_id' => $scan->dokumen_scan_id,
                    'teks_dibaca' => $request->teks_ocr,
                    'skor_padanan' => $request->skor_ocr !== '' ? $request->skor_ocr : null,
                    'status_semakan' => 'Belum Disemak',
                ]);
            }

            foreach ($jenisDokumen->fields as $f) {
                $nilai = $request->input("medan_data.{$f->dokumen_field_id}");
                if ($nilai === null || $nilai === '') {
                    continue;
                }
                DokumenData::create([
                    'dokumen_id' => $dokumenBaharu->dokumen_id,
                    'dokumen_field_id' => $f->dokumen_field_id,
                    'nilai_data' => $nilai,
                ]);
            }

            // Borang TERUS "Diluluskan" — ini arkib dokumen yang sedia lulus
            // secara fizikal, BUKAN permohonan baharu. Tiada Penyokong/Pelulus
            // sebenar terlibat, jadi kedua-dua lajur itu dibiar NULL supaya
            // log audit sentiasa jujur tentang apa yang benar-benar berlaku.
            $borang = Borang::create([
                'dokumen_id' => $dokumenBaharu->dokumen_id,
                'pemohon_id' => Auth::id(),
                'pegawai_penyokong_id' => null,
                'pegawai_pelulus_id' => null,
                'status_permohonan' => 'Diluluskan',
                'tarikh_permohonan' => now(),
                'tarikh_sokongan' => null,
                'tarikh_kelulusan' => now(),
                'ulasan_penyokong' => null,
                'ulasan_pelulus' => 'Diarkibkan terus melalui Imbas & Arkib — dokumen fizikal didapati telah lulus sebelum sistem ini digunakan.',
            ]);

            LogAudit::create([
                'user_id' => Auth::id(),
                'tindakan' => 'Arkib Terus (Sedia Lulus)',
                'nama_jadual' => 'borang',
                'rekod_id' => $borang->borang_id,
                'data_lama' => null,
                'data_baharu' => ['dokumen' => $noRujukan, 'status_permohonan' => 'Diluluskan'],
                'alamat_ip' => request()->ip(),
                'peranti_pengguna' => request()->userAgent(),
                'created_at' => now(),
            ]);

            return $dokumenBaharu;
        });

        return redirect()->route('dokumen.show', $dokumen->dokumen_id)
                         ->with('success', "Dokumen {$dokumen->no_rujukan} berjaya diarkibkan (status: Diluluskan).");
    }
}