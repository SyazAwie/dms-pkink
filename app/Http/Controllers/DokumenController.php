<?php

namespace App\Http\Controllers;

use App\Models\Bahagian;
use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenScan;
use App\Models\DokumenOcr;
use App\Services\OcrService;
use Illuminate\Support\Facades\Log;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Support\JenisMedan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DokumenController extends Controller
{
    // Senarai dokumen + carian kata kunci + penapis
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Dokumen::class);

        $request->validate([
            'q' => 'nullable|string|max:100',
            'jenis' => 'nullable|integer',
            'bahagian' => 'nullable|integer',
            'dari' => 'nullable|date',
            'hingga' => 'nullable|date|after_or_equal:dari',
            'susun' => 'nullable|in:terbaru,tarikh_desc,tarikh_asc,rujukan',
        ]);

        // bolehDilihat: STAFF = milik sendiri, PENGURUS = bahagian sendiri, lain = semua
        $query = Dokumen::bolehDilihat(Auth::user())->with(['jenisDokumen', 'bahagian', 'pemuatNaik']);

        // Carian kata kunci: no. rujukan, perkara, nama jenis, NILAI MEDAN EAV, nama fail imbasan
        $kata = trim((string) $request->input('q'));
        if ($kata !== '') {
            $like = '%' . addcslashes($kata, '%_\\') . '%';
            $query->where(function ($q) use ($like) {
                $q->where('no_rujukan', 'like', $like)
                  ->orWhere('perkara', 'like', $like)
                  ->orWhereHas('jenisDokumen', fn ($j) => $j->where('nama_dokumen', 'like', $like))
                  ->orWhereHas('data', fn ($d) => $d->where('nilai_data', 'like', $like))
                  ->orWhereHas('scans', fn ($f) => $f->where('nama_fail', 'like', $like));
            });
        }

        if ($request->filled('jenis')) {
            $query->where('jenis_dokumen_id', $request->jenis);
        }
        if ($request->filled('bahagian')) {
            $query->where('bahagian_id', $request->bahagian);
        }
        if ($request->filled('dari')) {
            $query->whereDate('tarikh_dokumen', '>=', $request->dari);
        }
        if ($request->filled('hingga')) {
            $query->whereDate('tarikh_dokumen', '<=', $request->hingga);
        }

        switch ($request->input('susun', 'terbaru')) {
            case 'tarikh_desc':
                $query->orderByDesc('tarikh_dokumen')->orderByDesc('dokumen_id');
                break;
            case 'tarikh_asc':
                $query->orderBy('tarikh_dokumen')->orderBy('dokumen_id');
                break;
            case 'rujukan':
                $query->orderBy('no_rujukan');
                break;
            default:
                $query->orderByDesc('dokumen_id');
        }

        $senarai = $query->paginate(15)->withQueryString();

        $jenisDokumenSenarai = JenisDokumen::orderBy('nama_dokumen')->get();
        $bahagianSenarai = Bahagian::orderBy('nama_bahagian')->get();
        $adaPenapis = $request->hasAny(['q', 'jenis', 'bahagian', 'dari', 'hingga'])
            && collect($request->only(['q', 'jenis', 'bahagian', 'dari', 'hingga']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();

        return view('dokumen.index', compact('senarai', 'jenisDokumenSenarai', 'bahagianSenarai', 'adaPenapis'));
    }

    // Borang muat naik dokumen baharu (?jenis=ID: kategori dipilih dahulu, cth. dari halaman Isi Borang)
    public function create(Request $request)
    {
        Gate::authorize('create', Dokumen::class);

        $jenisDokumenSenarai = JenisDokumen::orderBy('nama_dokumen')->get();

        // Selepas ralat validasi, render semula medan kategori yang dipilih di pelayan
        // supaya nilai yang ditaip (old()) dan mesej ralat medan dipaparkan.
        $idTerpilih = old('jenis_dokumen_id', $request->integer('jenis') ?: null);
        $jenisTerpilih = $idTerpilih ? JenisDokumen::with('fields')->find($idTerpilih) : null;
        $nilaiAutoIsi = $jenisTerpilih ? JenisMedan::nilaiAutoIsi($jenisTerpilih->fields, Auth::user()) : [];

        return view('dokumen.create', compact('jenisDokumenSenarai', 'jenisTerpilih', 'nilaiAutoIsi'));
    }

    // Simpan dokumen + nilai medan EAV + fail imbasan
    public function store(Request $request)
    {
        Gate::authorize('create', Dokumen::class);

        // Peraturan validasi DINAMIK ikut definisi dokumen_field bagi Jenis Dokumen dipilih,
        // disahkan sekali dengan medan tetap supaya semua ralat dipaparkan serentak.
        $jenisDipilih = JenisDokumen::with('fields')->find($request->input('jenis_dokumen_id'));
        $mesejAutoIsi = $jenisDipilih
            ? $this->gantiNilaiAutoIsi($request, $jenisDipilih, JenisMedan::nilaiAutoIsi($jenisDipilih->fields, Auth::user()))
            : [];

        $request->validate([
            'jenis_dokumen_id' => 'required|exists:jenis_dokumen,jenis_dokumen_id',
            'tarikh_dokumen' => 'required|date',
            'perkara' => 'nullable|max:255',
            // Pilihan: borang boleh diisi secara digital tanpa fail imbasan (Situasi 2)
            'fail_scan' => 'nullable|array',
            'fail_scan.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
            'tindakan' => 'nullable|in:simpan,hantar',
        ] + ($jenisDipilih ? $this->peraturanMedan($jenisDipilih, $request) : []), $mesejAutoIsi);

        $jenisDokumen = $jenisDipilih;
        // "Simpan & Hantar untuk Kelulusan": borang "Dalam Proses" dicipta dalam transaksi yang sama
        $hantar = $request->input('tindakan') === 'hantar';

        // Cuba simpan dengan percubaan semula sekiranya no_rujukan bertembung
        // (dua staf muat naik jenis+tahun sama pada masa hampir serentak).
        $percubaan = 0;
        do {
            $laluanDitulis = [];

            try {
                $dokumen = DB::transaction(function () use ($request, $jenisDokumen, $hantar, &$laluanDitulis) {
                    $noRujukan = $this->janaNoRujukan($jenisDokumen, $request->tarikh_dokumen);

                    $dokumenBaharu = Dokumen::create([
                        'jenis_dokumen_id' => $jenisDokumen->jenis_dokumen_id,
                        'no_rujukan' => $noRujukan,
                        'tarikh_dokumen' => $request->tarikh_dokumen,
                        'perkara' => $request->perkara,
                        'bahagian_id' => Auth::user()->bahagian_id,
                        'created_by' => Auth::id(),
                    ]);

                    // Gelung atas medan Jenis Dokumen ini sahaja (bukan kunci permintaan),
                    // supaya nilai untuk medan kategori lain atau ID palsu diabaikan.
                    foreach ($jenisDokumen->fields as $field) {
                        $nilai = JenisMedan::dariPermintaan($field, $request, $jenisDokumen->fields);
                        if ($nilai === null) {
                            continue;
                        }
                        DokumenData::create([
                            'dokumen_id' => $dokumenBaharu->dokumen_id,
                            'dokumen_field_id' => $field->dokumen_field_id,
                            'nilai_data' => $nilai,
                        ]);
                    }

                    $namaFolder = Str::slug($dokumenBaharu->no_rujukan, '_');
                    foreach ($request->file('fail_scan', []) as $fail) {
                        $laluanDitulis[] = $laluan = $this->simpanFailScan($fail, $namaFolder);

                        DokumenScan::create([
                            'dokumen_id' => $dokumenBaharu->dokumen_id,
                            'nama_fail' => $fail->getClientOriginalName(),
                            'lokasi_fail' => $laluan,
                        ]);
                    }

                    if ($hantar) {
                        // Sama seperti BorangController::hantar bagi dokumen baharu
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
                                'dokumen' => $dokumenBaharu->no_rujukan,
                                'status_permohonan' => $borang->status_permohonan,
                            ],
                            'alamat_ip' => $request->ip(),
                            'peranti_pengguna' => $request->userAgent(),
                            'created_at' => now(),
                        ]);
                    }

                    return $dokumenBaharu;
                });

                break; // berjaya, keluar dari gelung percubaan
            } catch (\Throwable $e) {
                // Transaksi dibatalkan: buang fail yang sempat ditulis supaya tiada fail
                // yatim dalam storan (termasuk percubaan yang akan diulang kerana 1062).
                Storage::disk('local')->delete($laluanDitulis);

                if (!$e instanceof \Illuminate\Database\QueryException) {
                    throw $e;
                }
                $percubaan++;
                if ($percubaan >= 3 || $e->errorInfo[1] !== 1062) {
                    // Bukan ralat "Duplicate entry" (1062), atau dah 3 kali cuba — lepaskan.
                    throw $e;
                }
                // Ulang gelung: janaNoRujukan() akan kira semula bilangan rekod terkini
            }
        } while ($percubaan < 3);

        if ($hantar) {
            return redirect()->route('dokumen.show', $dokumen->dokumen_id)
                             ->with('success', "Dokumen {$dokumen->no_rujukan} berjaya disimpan dan dihantar untuk kelulusan.");
        }

        return redirect()->route('dokumen.index')
                         ->with('success', "Dokumen berjaya dimuat naik! No. Rujukan: {$dokumen->no_rujukan}");
    }

    // Papar butiran satu dokumen (nilai EAV + fail imbasan)
    public function show($id)
    {
        $dokumen = Dokumen::with(['jenisDokumen.fields', 'bahagian', 'pemuatNaik', 'scans.ocr', 'data.field', 'borang'])
            ->findOrFail($id);
        Gate::authorize('view', $dokumen);

        $barisMedan = $this->barisPaparanMedan($dokumen);

        return view('dokumen.show', compact('dokumen', 'barisMedan'));
    }

    /**
     * Baris "Medan Dokumen" di halaman Butiran: ikut susunan medan kategori, dengan
     * tajuk seksyen (hanya jika seksyen itu ada nilai). Nilai bagi medan yang telah
     * dipadam daripada kategori dipaparkan di hujung supaya data lama tidak hilang.
     *
     * @return array<int, array{seksyen?: DokumenField, medan?: ?DokumenField, nilai?: string}>
     */
    protected function barisPaparanMedan(Dokumen $dokumen): array
    {
        $nilaiIkutMedan = $dokumen->data->keyBy('dokumen_field_id');
        $medanAktif = $dokumen->jenisDokumen?->fields ?? collect();

        $baris = [];
        $seksyenMenunggu = null;
        foreach ($medanAktif as $medan) {
            if (!JenisMedan::adaNilai($medan)) {
                $seksyenMenunggu = $medan;
                continue;
            }
            $data = $nilaiIkutMedan->get($medan->dokumen_field_id);
            if ($data === null || $data->nilai_data === null || $data->nilai_data === '') {
                continue;
            }
            if ($seksyenMenunggu) {
                $baris[] = ['seksyen' => $seksyenMenunggu];
                $seksyenMenunggu = null;
            }
            $baris[] = ['medan' => $medan, 'nilai' => $data->nilai_data];
        }

        $idAktif = $medanAktif->pluck('dokumen_field_id')->all();
        foreach ($dokumen->data as $data) {
            if (!in_array($data->dokumen_field_id, $idAktif)) {
                $baris[] = ['medan' => $data->field, 'nilai' => $data->nilai_data];
            }
        }

        return $baris;
    }

    // Borang edit: Jenis Dokumen & No. Rujukan dikunci (hanya paparan)
    public function edit($id)
    {
        $dokumen = Dokumen::with(['jenisDokumen.fields', 'data', 'scans'])->findOrFail($id);
        Gate::authorize('update', $dokumen);

        $nilaiSedia = $dokumen->data->pluck('nilai_data', 'dokumen_field_id');
        $nilaiAutoIsi = $this->nilaiAutoIsiEdit($dokumen);

        return view('dokumen.edit', compact('dokumen', 'nilaiSedia', 'nilaiAutoIsi'));
    }

    /**
     * Medan auto-isi semasa edit: kekal nilai pemohon asal (bukan profil penyunting).
     * Nilai yang masih kosong (cth. medan ditetapkan auto-isi selepas dokumen dicipta)
     * diisi daripada profil pemuat naik dokumen.
     */
    private function nilaiAutoIsiEdit(Dokumen $dokumen): array
    {
        return JenisMedan::nilaiAutoIsi(
            $dokumen->jenisDokumen->fields,
            $dokumen->pemuatNaik,
            $dokumen->data->pluck('nilai_data', 'dokumen_field_id')->all()
        );
    }

    // Kemaskini tarikh, perkara, nilai medan EAV dan fail imbasan
    public function update(Request $request, $id)
    {
        $dokumen = Dokumen::with(['jenisDokumen.fields', 'data', 'scans'])->findOrFail($id);
        Gate::authorize('update', $dokumen);

        $mesejAutoIsi = $this->gantiNilaiAutoIsi($request, $dokumen->jenisDokumen, $this->nilaiAutoIsiEdit($dokumen));

        $request->validate([
            'tarikh_dokumen' => 'required|date',
            'perkara' => 'nullable|max:255',
            'hapus_scan' => 'nullable|array',
            'hapus_scan.*' => 'integer',
            'fail_scan' => 'nullable|array',
            'fail_scan.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ] + $this->peraturanMedan($dokumen->jenisDokumen, $request), $mesejAutoIsi);

        // Hanya fail milik dokumen ini boleh dibuang
        $idHapus = $dokumen->scans
            ->whereIn('dokumen_scan_id', $request->input('hapus_scan', []))
            ->pluck('dokumen_scan_id')
            ->all();

        // Dokumen yang ada fail imbasan mesti kekal ada sekurang-kurangnya satu (arkib imbasan
        // tidak boleh dikosongkan). Dokumen yang diisi secara digital boleh kekal tanpa fail.
        $bakiFail = $dokumen->scans->count() - count($idHapus) + count($request->file('fail_scan', []));
        if ($dokumen->scans->isNotEmpty() && $bakiFail < 1) {
            return back()->withInput()->withErrors([
                'fail_scan' => 'Dokumen mesti mempunyai sekurang-kurangnya satu fail imbasan.',
            ]);
        }

        $nilaiSedia = $dokumen->data->pluck('nilai_data', 'dokumen_field_id');
        $lamaMedan = $baharuMedan = $failDibuang = $failDitambah = $laluanUntukPadam = $laluanDitulis = [];

        try {
            DB::transaction(function () use (
                $request, $dokumen, $idHapus, $nilaiSedia,
                &$lamaMedan, &$baharuMedan, &$failDibuang, &$failDitambah, &$laluanUntukPadam, &$laluanDitulis
            ) {
                $dokumen->update([
                    'tarikh_dokumen' => $request->tarikh_dokumen,
                    'perkara' => $request->perkara,
                ]);

                // Nilai medan EAV: hanya proses medan yang nilainya berubah
                foreach ($dokumen->jenisDokumen->fields as $field) {
                    $fieldId = $field->dokumen_field_id;
                    $baharu = JenisMedan::dariPermintaan($field, $request, $dokumen->jenisDokumen->fields);
                    $lama = isset($nilaiSedia[$fieldId]) ? (string) $nilaiSedia[$fieldId] : null;

                    if ($lama === $baharu) {
                        continue;
                    }

                    $lamaMedan[$field->nama_field] = $lama;
                    $baharuMedan[$field->nama_field] = $baharu;

                    if ($baharu === null) {
                        DokumenData::where('dokumen_id', $dokumen->dokumen_id)
                            ->where('dokumen_field_id', $fieldId)
                            ->delete();
                    } else {
                        DokumenData::updateOrCreate(
                            ['dokumen_id' => $dokumen->dokumen_id, 'dokumen_field_id' => $fieldId],
                            ['nilai_data' => $baharu]
                        );
                    }
                }

                // Buang rekod fail yang ditanda (fail fizikal dipadam selepas commit)
                foreach ($dokumen->scans->whereIn('dokumen_scan_id', $idHapus) as $scan) {
                    $failDibuang[] = $scan->nama_fail;
                    $laluanUntukPadam[] = $scan->lokasi_fail;
                    $scan->delete();
                }

                // Tambah fail imbasan baharu
                $namaFolder = Str::slug($dokumen->no_rujukan, '_');
                foreach ($request->file('fail_scan', []) as $fail) {
                    $laluanDitulis[] = $laluan = $this->simpanFailScan($fail, $namaFolder);

                    DokumenScan::create([
                        'dokumen_id' => $dokumen->dokumen_id,
                        'nama_fail' => $fail->getClientOriginalName(),
                        'lokasi_fail' => $laluan,
                    ]);
                    $failDitambah[] = $fail->getClientOriginalName();
                }
            });
        } catch (\Throwable $e) {
            // Transaksi dibatalkan: buang fail baharu yang sempat ditulis. Fail yang
            // ditanda untuk dibuang masih utuh kerana ia hanya dipadam selepas commit.
            Storage::disk('local')->delete($laluanDitulis);
            throw $e;
        }

        if ($laluanUntukPadam) {
            Storage::disk('local')->delete($laluanUntukPadam);
        }

        // DokumenObserver hanya menangkap perubahan lajur dokumen (tarikh/perkara).
        // Perubahan medan EAV & fail dilog di sini secara berasingan.
        if ($lamaMedan || $failDibuang || $failDitambah) {
            LogAudit::create([
                'user_id' => Auth::id(),
                'tindakan' => 'Kemaskini Medan & Fail',
                'nama_jadual' => $dokumen->getTable(),
                'rekod_id' => $dokumen->getKey(),
                'data_lama' => ['medan' => $lamaMedan, 'fail_dibuang' => $failDibuang],
                'data_baharu' => ['medan' => $baharuMedan, 'fail_ditambah' => $failDitambah],
                'alamat_ip' => $request->ip(),
                'peranti_pengguna' => $request->userAgent(),
                'created_at' => now(),
            ]);
        }

        return redirect()->route('dokumen.show', $dokumen->dokumen_id)
                         ->with('success', "Dokumen {$dokumen->no_rujukan} berjaya dikemas kini.");
    }

    // Padam (soft delete). Fail fizikal dikekalkan untuk arkib & jejak audit.
    public function destroy($id)
    {
        $dokumen = Dokumen::findOrFail($id);
        Gate::authorize('delete', $dokumen);

        $noRujukan = $dokumen->no_rujukan;
        $dokumen->delete();

        return redirect()->route('dokumen.index')
                         ->with('success', "Dokumen {$noRujukan} telah dipadam.");
    }

    /**
     * Tulis satu fail imbasan ke disk peribadi. Disk 'local' dikonfigurasi
     * 'throw' => false, jadi store() memulangkan false bila gagal; tukar kepada
     * pengecualian supaya transaksi dibatalkan dan tiada rekod tanpa fail.
     */
    private function simpanFailScan(\Illuminate\Http\UploadedFile $fail, string $namaFolder): string
    {
        $laluan = $fail->store("dokumen_scan/{$namaFolder}", 'local');
        if ($laluan === false) {
            throw new \RuntimeException("Fail imbasan \"{$fail->getClientOriginalName()}\" tidak dapat disimpan ke storan.");
        }

        return $laluan;
    }

    /**
     * Peraturan validasi dinamik bagi nilai medan EAV (dikongsi store, update
     * dan Imbas & Arkib). Peraturan setiap jenis data ada dalam JenisMedan.
     * Medan yang tersembunyi oleh syarat "tunjuk jika" tidak divalidasi.
     */
    protected function peraturanMedan(JenisDokumen $jenisDokumen, ?Request $request = null): array
    {
        $medanData = (array) $request?->input('medan_data', []);
        $peraturanMedan = [];
        foreach ($jenisDokumen->fields as $field) {
            if ($request && !JenisMedan::kelihatan($field, $jenisDokumen->fields, $medanData)) {
                continue;
            }
            $peraturanMedan += JenisMedan::peraturan($field);
        }

        return $peraturanMedan;
    }

    /**
     * Medan auto-isi (tetapan.auto_isi): ganti input dengan nilai profil SEBELUM validasi,
     * supaya apa sahaja yang dihantar dari pelayar diabaikan (tidak boleh dipalsukan).
     * Pulangkan mesej validasi bagi medan wajib yang profilnya tiada maklumat itu.
     *
     * @param array $nilaiAutoIsi [dokumen_field_id => nilai] daripada JenisMedan::nilaiAutoIsi()
     */
    protected function gantiNilaiAutoIsi(Request $request, JenisDokumen $jenisDokumen, array $nilaiAutoIsi): array
    {
        if (!$nilaiAutoIsi) {
            return [];
        }

        $request->merge(['medan_data' => array_replace((array) $request->input('medan_data', []), $nilaiAutoIsi)]);

        $mesej = [];
        foreach ($jenisDokumen->fields as $field) {
            if (array_key_exists($field->dokumen_field_id, $nilaiAutoIsi)) {
                $label = mb_strtolower(JenisMedan::SUMBER_AUTO_ISI[JenisMedan::autoIsi($field)]);
                $mesej["medan_data.{$field->dokumen_field_id}.required"] =
                    "Medan {$field->nama_field} diisi daripada profil, tetapi profil tiada maklumat {$label}. Sila kemas kini profil terlebih dahulu.";
            }
        }

        return $mesej;
    }

    /**
     * Jana No. Rujukan automatik: {KOD_DOKUMEN}/{TAHUN}/{TURUTAN 4-digit}
     *
     * Turutan diambil daripada nombor TERTINGGI sedia ada (termasuk dokumen
     * yang telah dipadam), bukan count(), supaya padam dokumen tidak
     * menyebabkan nombor bertembung atau digunakan semula.
     */
    protected function janaNoRujukan(JenisDokumen $jenisDokumen, string $tarikhDokumen): string
    {
        $tahun = date('Y', strtotime($tarikhDokumen));
        $awalan = "{$jenisDokumen->kod_dokumen}/{$tahun}/";

        $terakhir = Dokumen::withTrashed()
            ->where('no_rujukan', 'like', addcslashes($awalan, '%_\\') . '%')
            ->lockForUpdate()
            ->orderByDesc('no_rujukan')
            ->value('no_rujukan');

        $turutan = $terakhir ? ((int) substr($terakhir, strlen($awalan))) + 1 : 1;

        return $awalan . str_pad($turutan, 4, '0', STR_PAD_LEFT);
    }
    /**
     * Papar/muat turun satu fail imbasan. Disk 'local' (peribadi) + semakan
     * DokumenPolicy::view di sini ialah satu-satunya cara fail ini boleh dicapai —
     * tiada URL awam terus seperti sebelum ini (storage:link / disk 'public').
     */
    public function paparScan($id)
    {
        $scan = DokumenScan::with('dokumen')->findOrFail($id);
        Gate::authorize('view', $scan->dokumen);

        if (!Storage::disk('local')->exists($scan->lokasi_fail)) {
            abort(404, 'Fail tidak dijumpai dalam storan.');
        }

        return Storage::disk('local')->response($scan->lokasi_fail, $scan->nama_fail);
    }

    /**
     * Jalankan OCR untuk SATU fail imbasan, dicetuskan manual oleh staf
     * (butang "Jalankan OCR" dalam halaman Butiran Dokumen). Guna kebenaran
     * sama seperti edit dokumen induk.
     *
     * Mesej ralat SEBENAR (bukan generik) di-flash balik ke skrin supaya
     * masalah pemasangan Tesseract/Poppler/PATH senang dikesan semasa
     * pembangunan.
     */
    public function jalankanOcr($scanId)
    {
        $scan = DokumenScan::with('dokumen')->findOrFail($scanId);
        Gate::authorize('update', $scan->dokumen);

        // OCR berjalan segerak; PDF berbilang muka surat boleh melebihi max_execution_time (30 saat)
        set_time_limit((int) config('services.ocr.had_masa', 300));

        try {
            $hasil = app(OcrService::class)->proses($scan->lokasi_fail);

            DokumenOcr::updateOrCreate(
                ['dokumen_scan_id' => $scan->dokumen_scan_id],
                [
                    'teks_dibaca' => $hasil['teks'] !== '' ? $hasil['teks'] : null,
                    'skor_padanan' => $hasil['skor'],
                    'status_semakan' => 'Belum Disemak',
                ]
            );

            $scan->update(['status_ocr' => $hasil['teks'] !== '' ? 'Selesai' : 'Tiada Teks Dikesan']);

            return back()->with('success', "OCR selesai untuk \"{$scan->nama_fail}\".");
        } catch (\Throwable $e) {
            Log::warning("OCR gagal untuk dokumen_scan_id {$scan->dokumen_scan_id}: " . $e->getMessage());
            $scan->update(['status_ocr' => 'Ralat']);

            return back()->with('error', "OCR gagal untuk \"{$scan->nama_fail}\": " . $e->getMessage());
        }
    }

}