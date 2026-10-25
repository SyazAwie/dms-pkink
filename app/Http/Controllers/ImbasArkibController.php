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
use App\Support\JenisMedan;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
 * (Ollama) cuba isi medan secara automatik → (3) pegawai SEMAK & BETULKAN
 * sebelum simpan → (4) simpan, Borang terus berstatus "Diluluskan" (tiada
 * Penyokong/Pelulus sebenar — ini arkib, bukan permohonan baharu).
 *
 * Akses: KERANI/ADMIN/SUPERADMIN sahaja (middleware `peranan:` dalam routes/web.php).
 * STAFF & PENGURUS tidak boleh, kerana dokumen di sini terus "Diluluskan".
 *
 * Token semakan: hasil langkah (2) disimpan di pelayan dalam
 * ocr_sementara/{token}/ (fail imbasan + meta.json), bukan dalam medan
 * tersembunyi borang. Pelayar hanya memegang token; jenis dokumen, nama
 * fail, teks & skor OCR dibaca semula daripada meta.json semasa simpan.
 * Token terikat pada pengguna yang memuat naik dan tamat selepas 120 minit.
 *
 * Warisi DokumenController untuk guna semula peraturanMedan() & janaNoRujukan()
 * (protected) — elak duplikasi logik.
 * ============================================================================
 */
class ImbasArkibController extends DokumenController
{
    private const FOLDER_SEMENTARA = 'ocr_sementara';
    private const MINIT_TAMAT = 120;

    // Borang: pilih Jenis Dokumen + satu fail
    public function create()
    {
        Gate::authorize('create', Dokumen::class);

        $jenisDokumenSenarai = JenisDokumen::with('fields')->orderBy('nama_dokumen')->get();
        return view('imbas_arkib.create', compact('jenisDokumenSenarai'));
    }

    // OCR + ekstrak AI, simpan hasil dalam folder token, kemudian redirect ke skrin semak
    // (TIDAK simpan ke DB lagi)
    public function analisis(Request $request)
    {
        Gate::authorize('create', Dokumen::class);

        $request->validate([
            'jenis_dokumen_id' => 'required|exists:jenis_dokumen,jenis_dokumen_id',
            'fail_scan' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $jenisDokumen = JenisDokumen::with('fields')->findOrFail($request->jenis_dokumen_id);

        // Nama fail dalam storan dijana sendiri (bukan nama asal daripada pelayar);
        // nama asal hanya disimpan dalam meta.json untuk paparan.
        $fail = $request->file('fail_scan');
        $token = Str::random(40);
        $namaFail = 'fail.' . $fail->extension();
        $laluanSementara = $fail->storeAs($this->folderToken($token), $namaFail, 'local');

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

        Storage::disk('local')->put($this->folderToken($token) . '/meta.json', json_encode([
            'user_id' => Auth::id(),
            'jenis_dokumen_id' => $jenisDokumen->jenis_dokumen_id,
            'nama_asal' => $fail->getClientOriginalName(),
            'fail' => $namaFail,
            'teks_ocr' => $teksOcr,
            'skor_ocr' => $skorOcr,
            'nilai_ekstrak' => $nilaiEkstrak,
            'mesej_amaran' => $mesejAmaran,
            'dicipta_pada' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));

        return redirect()->route('imbas-arkib.semak', $token);
    }

    // Skrin semak (GET): boleh dimuat semula (F5) dan menjadi sasaran back() bila validasi gagal
    public function semak(string $token)
    {
        Gate::authorize('create', Dokumen::class);

        $meta = $this->bacaMeta($token);
        if ($meta === null) {
            return $this->sesiTamat();
        }

        return view('imbas_arkib.semak', [
            'token' => $token,
            'jenisDokumen' => JenisDokumen::with('fields')->findOrFail($meta['jenis_dokumen_id']),
            'namaAsal' => $meta['nama_asal'],
            'teksOcr' => $meta['teks_ocr'],
            'skorOcr' => $meta['skor_ocr'],
            'nilaiEkstrak' => $meta['nilai_ekstrak'],
            'mesejAmaran' => $meta['mesej_amaran'],
        ]);
    }

    // Pegawai sahkan/betulkan nilai, simpan Dokumen + data + scan + OCR + Borang (terus Diluluskan)
    public function simpan(Request $request, string $token)
    {
        Gate::authorize('create', Dokumen::class);

        $meta = $this->bacaMeta($token);
        if ($meta === null) {
            return $this->sesiTamat();
        }

        $jenisDokumen = JenisDokumen::with('fields')->findOrFail($meta['jenis_dokumen_id']);

        // Hanya tarikh, perkara dan nilai medan datang daripada borang
        $request->validate([
            'tarikh_dokumen' => 'required|date',
            'perkara' => 'nullable|max:255',
        ] + $this->peraturanMedan($jenisDokumen));

        // Tuntut token secara atomik: rename hanya berjaya bagi SATU permintaan,
        // jadi klik dua kali / dua tab tidak mencipta dua dokumen.
        $folder = $this->folderToken($token);
        if (!Storage::disk('local')->move("{$folder}/meta.json", "{$folder}/meta.json.diproses")) {
            return $this->sesiTamat();
        }

        try {
            $dokumen = $this->simpanDenganCubaSemula($request, $jenisDokumen, $meta, "{$folder}/{$meta['fail']}");
        } catch (\Throwable $e) {
            // Pulihkan token supaya pengguna boleh cuba simpan semula
            Storage::disk('local')->move("{$folder}/meta.json.diproses", "{$folder}/meta.json");
            throw $e;
        }

        // Fail sementara dipadam hanya selepas commit berjaya
        Storage::disk('local')->deleteDirectory($folder);

        return redirect()->route('dokumen.show', $dokumen->dokumen_id)
                         ->with('success', "Dokumen {$dokumen->no_rujukan} berjaya diarkibkan (status: Diluluskan).");
    }

    /**
     * Cipta semua rekod dalam satu transaksi, dengan cuba semula (maks 3 kali)
     * sekiranya no_rujukan bertembung (1062), sama seperti DokumenController::store.
     *
     * Fail imbasan DISALIN (bukan dipindah) ke dokumen_scan di dalam transaksi.
     * Jika transaksi gagal, salinan dipadam dan fail asal dalam folder token
     * kekal utuh, jadi tiada fail hilang atau tertinggal tanpa rekod.
     */
    private function simpanDenganCubaSemula(Request $request, JenisDokumen $jenisDokumen, array $meta, string $laluanSementara): Dokumen
    {
        $percubaan = 0;
        do {
            $laluanSalinan = null;

            try {
                return DB::transaction(function () use ($request, $jenisDokumen, $meta, $laluanSementara, &$laluanSalinan) {
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

                    // Salin fail daripada folder token ke lokasi kekal (nama rawak seperti store())
                    $namaFolder = Str::slug($noRujukan, '_');
                    $sambungan = pathinfo($meta['fail'], PATHINFO_EXTENSION);
                    $laluanSalinan = "dokumen_scan/{$namaFolder}/" . Str::random(40) . ".{$sambungan}";
                    if (!Storage::disk('local')->copy($laluanSementara, $laluanSalinan)) {
                        $laluanSalinan = null;
                        throw new \RuntimeException('Fail imbasan sementara tidak dapat disalin ke storan kekal.');
                    }

                    $teksOcr = (string) $meta['teks_ocr'];

                    $scan = DokumenScan::create([
                        'dokumen_id' => $dokumenBaharu->dokumen_id,
                        'nama_fail' => $meta['nama_asal'],
                        'lokasi_fail' => $laluanSalinan,
                        'status_ocr' => $teksOcr !== '' ? 'Selesai' : 'Tiada Teks Dikesan',
                    ]);

                    if ($teksOcr !== '') {
                        DokumenOcr::create([
                            'dokumen_scan_id' => $scan->dokumen_scan_id,
                            'teks_dibaca' => $teksOcr,
                            'skor_padanan' => $meta['skor_ocr'],
                            'status_semakan' => 'Belum Disemak',
                        ]);
                    }

                    foreach ($jenisDokumen->fields as $f) {
                        $nilai = JenisMedan::dariPermintaan($f, $request);
                        if ($nilai === null) {
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
            } catch (\Throwable $e) {
                if ($laluanSalinan !== null) {
                    Storage::disk('local')->delete($laluanSalinan);
                }

                $percubaan++;
                if (!$e instanceof QueryException || ($e->errorInfo[1] ?? null) !== 1062 || $percubaan >= 3) {
                    throw $e;
                }
                // Ulang: janaNoRujukan() akan ambil nombor tertinggi terkini
            }
        } while (true);
    }

    private function folderToken(string $token): string
    {
        return self::FOLDER_SEMENTARA . '/' . $token;
    }

    /**
     * Baca meta.json bagi token milik pengguna semasa.
     *
     * - null  : token tiada (sudah disimpan / sedang diproses) atau tamat tempoh
     * - 404   : token milik pengguna lain, atau meta rosak
     */
    private function bacaMeta(string $token): ?array
    {
        $folder = $this->folderToken($token);
        $kandungan = Storage::disk('local')->get("{$folder}/meta.json");
        if ($kandungan === null) {
            return null;
        }

        $meta = json_decode($kandungan, true);
        $lengkap = is_array($meta)
            && isset($meta['user_id'], $meta['jenis_dokumen_id'], $meta['nama_asal'], $meta['fail'], $meta['dicipta_pada'])
            && preg_match('/^fail\.[a-z0-9]+$/', $meta['fail']);
        abort_unless($lengkap, 404);
        abort_unless((int) $meta['user_id'] === (int) Auth::id(), 404);

        $tamat = Carbon::parse($meta['dicipta_pada'])->addMinutes(self::MINIT_TAMAT)->isPast();
        if ($tamat || !Storage::disk('local')->exists("{$folder}/{$meta['fail']}")) {
            Storage::disk('local')->deleteDirectory($folder);
            return null;
        }

        return $meta + ['teks_ocr' => '', 'skor_ocr' => null, 'nilai_ekstrak' => [], 'mesej_amaran' => null];
    }

    private function sesiTamat()
    {
        return redirect()->route('imbas-arkib.create')->withErrors([
            'token' => 'Sesi semakan ini telah tamat tempoh atau sudah disimpan. Sila muat naik semula fail imbasan.',
        ]);
    }
}
