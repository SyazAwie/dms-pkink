<?php

namespace App\Http\Controllers;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenOcr;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\PembetulanAi;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use App\Support\JenisMedan;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
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
 * Mod (OCR 3 Situasi): aliran token yang sama digunakan oleh dua mod.
 *   arkib   Imbas & Arkib (kelas ini): KERANI/ADMIN/SUPERADMIN, terus "Diluluskan".
 *   hantar  Imbas & Hantar (ImbasHantarController): staf isi borang fizikal sendiri,
 *           sahkan hasil OCR/AI, kemudian borang "Dalam Proses" → Penyokong → Pelulus.
 * Mod disimpan dalam meta.json; token satu mod tidak boleh disimpan melalui mod lain.
 *
 * Warisi DokumenController untuk guna semula peraturanMedan() & janaNoRujukan()
 * (protected) — elak duplikasi logik.
 * ============================================================================
 */
class ImbasArkibController extends DokumenController
{
    private const FOLDER_SEMENTARA = 'ocr_sementara';
    private const MINIT_TAMAT = 120;

    /** 'arkib' atau 'hantar' (ImbasHantarController). */
    protected function mod(): string
    {
        return 'arkib';
    }

    /** Nama laluan bagi mod ini, cth. imbas-arkib.semak / imbas-hantar.semak */
    protected function laluan(string $nama): string
    {
        return "imbas-{$this->mod()}.{$nama}";
    }

    private function modHantar(): bool
    {
        return $this->mod() === 'hantar';
    }

    /**
     * Medan yang diisi dalam mod ini. Imbas & Hantar: pemohon tidak mengisi seksyen
     * pejabat (pengesahan/sokongan/urusan BD), jadi medan itu tidak dihantar kepada AI
     * (elak nilai rekaan), tidak dipaparkan dan tidak disimpan.
     */
    private function medanUntukMod(JenisDokumen $jenisDokumen): \Illuminate\Support\Collection
    {
        if (!$this->modHantar()) {
            return $jenisDokumen->fields;
        }
        $idPejabat = JenisMedan::idMedanPejabat($jenisDokumen->fields);

        return $jenisDokumen->fields->reject(fn ($f) => in_array($f->dokumen_field_id, $idPejabat, true))->values();
    }

    // Borang: pilih Jenis Dokumen + satu fail (?jenis=ID: kategori dipilih dahulu, cth. dari Isi Borang)
    public function create(Request $request)
    {
        Gate::authorize('create', Dokumen::class);

        $jenisDokumenSenarai = JenisDokumen::with('fields')->orderBy('nama_dokumen')->get();
        return view('imbas_arkib.create', [
            'jenisDokumenSenarai' => $jenisDokumenSenarai,
            'mod' => $this->mod(),
            'idTerpilih' => (int) old('jenis_dokumen_id', $request->integer('jenis')),
        ]);
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

        // OCR + AI berjalan segerak dan boleh melebihi max_execution_time php.ini (30 saat)
        set_time_limit((int) config('services.ocr.had_masa', 300));

        // Nama fail dalam storan dijana sendiri (bukan nama asal daripada pelayar);
        // nama asal hanya disimpan dalam meta.json untuk paparan.
        $fail = $request->file('fail_scan');
        $token = Str::random(40);
        $namaFail = 'fail.' . $fail->extension();
        $laluanSementara = $fail->storeAs($this->folderToken($token), $namaFail, 'local');

        $this->tulisMeta($token, [
            'mod' => $this->mod(),
            'user_id' => Auth::id(),
            'jenis_dokumen_id' => $jenisDokumen->jenis_dokumen_id,
            'nama_asal' => $fail->getClientOriginalName(),
            'fail' => $namaFail,
            'dicipta_pada' => now()->toIso8601String(),
        ] + $this->jalankanOcrAi($laluanSementara, $jenisDokumen));

        return redirect()->route($this->laluan('semak'), $token);
    }

    /**
     * Butang "Baca semula (tulisan tangan)" di skrin semak: baca SEMUA muka surat dengan model
     * penglihatan (GLM-OCR) walaupun skor Tesseract melepasi ambang, kemudian ekstrak semula.
     * Untuk dokumen tulisan tangan yang tidak dikesan secara automatik.
     */
    public function bacaSemula(string $token)
    {
        Gate::authorize('create', Dokumen::class);

        $meta = $this->bacaMeta($token);
        if ($meta === null) {
            return $this->sesiTamat();
        }
        $jenisDokumen = JenisDokumen::with('fields')->findOrFail($meta['jenis_dokumen_id']);

        set_time_limit((int) config('services.ocr.had_masa', 300));
        $baharu = $this->jalankanOcrAi($this->folderToken($token) . '/' . $meta['fail'], $jenisDokumen, true);

        // Token mungkin telah disimpan (dituntut) semasa bacaan berjalan: jangan hidupkan semula
        if (!Storage::disk('local')->exists($this->folderToken($token) . '/meta.json')) {
            return $this->sesiTamat();
        }
        $this->tulisMeta($token, array_merge($meta, $baharu));

        return redirect()->route($this->laluan('semak'), $token)
            ->with('success', 'Dokumen dibaca semula dengan AI penglihatan. Sila semak semula setiap medan.');
    }

    /**
     * OCR (hibrid Tesseract → model penglihatan, lihat OcrService) + pengekstrakan AI.
     * Kegagalan OCR/AI tidak menghalang semakan: amaran dipaparkan dan medan diisi manual.
     *
     * @return array{teks_ocr: string, skor_ocr: float|null, enjin: list<string>, nilai_ekstrak: array,
     *               mesej_amaran: string|null, masa: array{ocr: float|null, ai: float|null}}
     */
    private function jalankanOcrAi(string $laluanFail, JenisDokumen $jenisDokumen, bool $paksaVlm = false): array
    {
        $hasil = ['teks_ocr' => '', 'skor_ocr' => null, 'enjin' => [], 'nilai_ekstrak' => [], 'mesej_amaran' => null,
            // Masa setiap langkah (saat), dipaparkan di skrin semak
            'masa' => ['ocr' => null, 'ai' => null]];

        try {
            $mula = microtime(true);
            $ocr = app(OcrService::class)->proses($laluanFail, $paksaVlm);
            $hasil['masa']['ocr'] = round(microtime(true) - $mula, 1);
            $hasil['teks_ocr'] = $ocr['teks'];
            $hasil['skor_ocr'] = $ocr['skor'];
            $hasil['enjin'] = $ocr['enjin'] ?? [];

            if ($ocr['teks'] === '') {
                $hasil['mesej_amaran'] = 'OCR tidak kesan sebarang teks pada fail ini. Sila isi semua medan secara manual.';
            } else {
                $mula = microtime(true);
                // Turut ekstrak Tarikh Dokumen & Perkara (medan asas, bukan EAV)
                $hasil['nilai_ekstrak'] = app(OllamaExtractionService::class)->ekstrak($ocr['teks'], $this->medanUntukMod($jenisDokumen), true);
                $hasil['masa']['ai'] = round(microtime(true) - $mula, 1);
            }
        } catch (\Throwable $e) {
            Log::warning('Imbas & Arkib: OCR/AI gagal: ' . $e->getMessage());
            $hasil['mesej_amaran'] = 'OCR/AI gagal diproses automatik' . $this->butiranRalat($e) . ' — sila isi semua medan secara manual.';
        }

        return $hasil;
    }

    private function tulisMeta(string $token, array $meta): void
    {
        Storage::disk('local')->put($this->folderToken($token) . '/meta.json',
            json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
    }

    // Skrin semak (GET): boleh dimuat semula (F5) dan menjadi sasaran back() bila validasi gagal
    public function semak(string $token)
    {
        Gate::authorize('create', Dokumen::class);

        $meta = $this->bacaMeta($token);
        if ($meta === null) {
            return $this->sesiTamat();
        }

        $jenisDokumen = JenisDokumen::with('fields')->findOrFail($meta['jenis_dokumen_id']);

        return view('imbas_arkib.semak', [
            'mod' => $this->mod(),
            'token' => $token,
            'jenisDokumen' => $jenisDokumen,
            'namaAsal' => $meta['nama_asal'],
            'pratontonImej' => in_array(pathinfo($meta['fail'], PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png'], true),
            'teksOcr' => $meta['teks_ocr'],
            'skorOcr' => $meta['skor_ocr'],
            'nilaiEkstrak' => $meta['nilai_ekstrak'],
            'tarikhAi' => $meta['nilai_ekstrak'][OllamaExtractionService::TARIKH_DOKUMEN] ?? null,
            'perkaraAi' => $meta['nilai_ekstrak'][OllamaExtractionService::PERKARA] ?? null,
            'masa' => $meta['masa'] ?? null,
            // Nombor muka surat (1-based) yang dibaca oleh model penglihatan (tulisan tangan)
            'mukaVlm' => array_map(fn ($i) => $i + 1, array_keys(array_filter($meta['enjin'] ?? [], fn ($e) => $e === 'vlm'))),
            // Butang "Baca semula" disembunyikan jika semua muka surat sudah dibaca model penglihatan
            'bolehBacaSemula' => app(\App\Services\VlmOcrService::class)->aktif()
                && in_array('tesseract', $meta['enjin'] ?? ['tesseract'], true),
            'mesejAmaran' => $meta['mesej_amaran'],
            // Imbas & Hantar: borang milik pengguna sendiri, jadi medan auto-isi profil mengatasi AI.
            // Imbas & Arkib: borang milik orang lain, jadi nilai AI digunakan.
            'nilaiAutoIsi' => $this->modHantar() ? JenisMedan::nilaiAutoIsi($jenisDokumen->fields, Auth::user()) : [],
        ]);
    }

    /**
     * Panaskan model AI (dipanggil oleh skrip halaman Imbas semasa pengguna memilih fail),
     * supaya analisis tidak menunggu model dimuat (~7 saat jika sejuk). Pulang segera jika
     * model sudah dimuat. Ralat diabaikan: analisis akan memuat model seperti biasa.
     */
    public function panaskanAi()
    {
        Gate::authorize('create', Dokumen::class);

        try {
            $ai = app(OllamaExtractionService::class);
            $sedia = $ai->sudahDimuat() || $ai->panaskan();
        } catch (\Throwable) {
            $sedia = false;
        }

        return response()->json(['sedia' => $sedia]);
    }

    /**
     * Pratonton fail imbasan sementara di skrin semak (iframe/img). Hanya pemilik
     * token (bacaMeta: 404 bagi pengguna lain) dan hanya sebelum disimpan.
     */
    public function pratonton(string $token)
    {
        Gate::authorize('create', Dokumen::class);

        $meta = $this->bacaMeta($token);
        abort_if($meta === null, 404);

        return Storage::disk('local')->response(
            $this->folderToken($token) . '/' . $meta['fail'],
            $meta['nama_asal'],
            ['Cache-Control' => 'private, no-store']
        );
    }

    // Pegawai sahkan/betulkan nilai, simpan Dokumen + data + scan + OCR + Borang
    // (arkib: terus Diluluskan; hantar: Dalam Proses untuk kelulusan)
    public function simpan(Request $request, string $token)
    {
        Gate::authorize('create', Dokumen::class);

        $meta = $this->bacaMeta($token);
        if ($meta === null) {
            return $this->sesiTamat();
        }

        $jenisDokumen = JenisDokumen::with('fields')->findOrFail($meta['jenis_dokumen_id']);

        $mesej = [];
        $peraturanTambahan = [];
        if ($this->modHantar()) {
            $mesej = $this->gantiNilaiAutoIsi($request, $jenisDokumen, JenisMedan::nilaiAutoIsi($jenisDokumen->fields, Auth::user()));
            $peraturanTambahan['pengesahan'] = 'accepted';
            $mesej['pengesahan.accepted'] = 'Sila tandakan pengesahan bahawa maklumat ini sepadan dengan borang fizikal yang anda isi.';
        }

        // Hanya tarikh, perkara dan nilai medan datang daripada borang
        $request->validate([
            'tarikh_dokumen' => 'required|date',
            'perkara' => 'nullable|max:255',
        ] + $peraturanTambahan + $this->peraturanMedan($jenisDokumen, $request, $this->modHantar() ? 'abai' : 'wajib'), $mesej);

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

        $mesejBerjaya = $this->modHantar()
            ? "Dokumen {$dokumen->no_rujukan} berjaya dihantar untuk kelulusan (status: Dalam Proses)."
            : "Dokumen {$dokumen->no_rujukan} berjaya diarkibkan (status: Diluluskan).";

        return redirect()->route('dokumen.show', $dokumen->dokumen_id)->with('success', $mesejBerjaya);
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

                    // Mod hantar: status dokumen ikut lalai DB, sama seperti Muat Naik biasa
                    $dokumenBaharu = Dokumen::create([
                        'jenis_dokumen_id' => $jenisDokumen->jenis_dokumen_id,
                        'no_rujukan' => $noRujukan,
                        'tarikh_dokumen' => $request->tarikh_dokumen,
                        'perkara' => $request->perkara,
                        'bahagian_id' => Auth::user()->bahagian_id,
                        'created_by' => Auth::id(),
                    ] + ($this->modHantar() ? [] : ['status' => 'Archived']));

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

                    $nilaiAi = $meta['nilai_ekstrak'] ?? [];
                    foreach ($this->medanUntukMod($jenisDokumen) as $f) {
                        $nilai = JenisMedan::dariPermintaan($f, $request, $jenisDokumen->fields);

                        // P16: nilai AI vs nilai akhir staf (medan auto-isi profil dalam mod hantar
                        // ditimpa pelayan, jadi bukan pembetulan AI)
                        if (array_key_exists($f->dokumen_field_id, $nilaiAi) && ! ($this->modHantar() && JenisMedan::autoIsi($f))) {
                            PembetulanAi::rekod($dokumenBaharu, $f, $nilaiAi[$f->dokumen_field_id], $nilai, Auth::id());
                        }

                        if ($nilai === null) {
                            continue;
                        }
                        DokumenData::create([
                            'dokumen_id' => $dokumenBaharu->dokumen_id,
                            'dokumen_field_id' => $f->dokumen_field_id,
                            'nilai_data' => $nilai,
                        ]);
                    }

                    if ($this->modHantar()) {
                        // Imbas & Hantar: permohonan baharu oleh pengisi borang sendiri,
                        // melalui aliran Penyokong → Pelulus sedia ada (BorangController)
                        $borang = Borang::create([
                            'dokumen_id' => $dokumenBaharu->dokumen_id,
                            'pemohon_id' => Auth::id(),
                            'status_permohonan' => 'Dalam Proses',
                            'tarikh_permohonan' => now(),
                        ]);

                        LogAudit::create([
                            'user_id' => Auth::id(),
                            'tindakan' => 'Hantar untuk Kelulusan',
                            'nama_jadual' => 'borang',
                            'rekod_id' => $borang->borang_id,
                            'data_lama' => null,
                            'data_baharu' => [
                                'dokumen' => $noRujukan,
                                'status_permohonan' => 'Dalam Proses',
                                'kaedah' => 'Imbas & Hantar (disahkan pemohon)',
                            ],
                            'alamat_ip' => request()->ip(),
                            'peranti_pengguna' => request()->userAgent(),
                            'created_at' => now(),
                        ]);

                        return $dokumenBaharu;
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
        // Token mod hantar tidak boleh disimpan melalui Imbas & Arkib (terus Diluluskan), dan
        // sebaliknya. Token lama tanpa 'mod' dicipta oleh Imbas & Arkib.
        abort_unless(($meta['mod'] ?? 'arkib') === $this->mod(), 404);

        $tamat = Carbon::parse($meta['dicipta_pada'])->addMinutes(self::MINIT_TAMAT)->isPast();
        if ($tamat || !Storage::disk('local')->exists("{$folder}/{$meta['fail']}")) {
            Storage::disk('local')->deleteDirectory($folder);
            return null;
        }

        return $meta + ['teks_ocr' => '', 'skor_ocr' => null, 'nilai_ekstrak' => [], 'mesej_amaran' => null];
    }

    private function sesiTamat()
    {
        return redirect()->route($this->laluan('create'))->withErrors([
            'token' => 'Sesi semakan ini telah tamat tempoh atau sudah disimpan. Sila muat naik semula fail imbasan.',
        ]);
    }
}
