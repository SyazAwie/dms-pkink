<?php

namespace App\Http\Controllers;

use App\Models\JenisDokumen;
use App\Models\DokumenData;
use App\Models\DokumenField;
use App\Support\JenisMedan;
use App\Support\MedanJadual;
use App\Support\TemplatKategori;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JenisDokumenController extends Controller
{
    // 1. Fungsi paparkan senarai
    public function index()
    {
        $senarai = JenisDokumen::withCount('fields')->get();
        return view('jenis_dokumen.index', compact('senarai'));
    }

    // 2. Fungsi paparkan borang tambah baharu
    public function create()
    {
        // Templat permulaan (memo, surat, minit, borang) dan kategori sedia ada untuk disalin
        return view('jenis_dokumen.create', [
            'templat' => TemplatKategori::senarai(),
            'salinan' => TemplatKategori::salinanKategori(),
            'kategoriCadangan' => TemplatKategori::kategoriCadangan(),
        ]);
    }

    // 3. Fungsi simpan data ke database
    public function store(Request $request)
    {
        $validated = $this->validasiBorang($request);
        $this->semakKodMedanPendua($request);
        $this->semakLajurJadual($request);
        $this->semakSyaratPaparan($request);

        DB::transaction(function () use ($request, $validated) {
            $dokumen = JenisDokumen::create([
                'kod_dokumen' => $validated['kod_dokumen'],
                'nama_dokumen' => $validated['nama_dokumen'],
                'kategori' => $validated['kategori'] ?? null,
            ]);

            $this->simpanMedan($dokumen, $request->input('medan', []));
        });

        return redirect()->route('jenis-dokumen.index')
                         ->with('success', 'Kategori Dokumen berjaya didaftarkan!');
    }

    // 4. Paparkan borang edit
    public function edit($id)
    {
        $dokumen = JenisDokumen::with('fields')->findOrFail($id);
        $kategoriCadangan = TemplatKategori::kategoriCadangan();

        // Bilangan nilai tersimpan bagi setiap medan: pembina memberi amaran bila jenis
        // data medan yang sudah ada nilai ditukar (#16)
        $bilNilai = DokumenData::whereIn('dokumen_field_id', $dokumen->fields->pluck('dokumen_field_id'))
            ->selectRaw('dokumen_field_id, COUNT(*) AS bil')
            ->groupBy('dokumen_field_id')
            ->pluck('bil', 'dokumen_field_id');
        $medanPembina = $dokumen->fields
            ->map(fn ($f) => $f->toArray() + ['bil_nilai' => (int) ($bilNilai[$f->dokumen_field_id] ?? 0), 'jenis_asal' => $f->jenis_data])
            ->values();

        return view('jenis_dokumen.edit', compact('dokumen', 'kategoriCadangan', 'medanPembina'));
    }

    // 5. Simpan kemas kini data
    public function update(Request $request, $id)
    {
        $dokumen = JenisDokumen::findOrFail($id);

        $validated = $this->validasiBorang($request, $id);
        $this->semakKodMedanPendua($request);
        $this->semakLajurJadual($request);
        $this->semakSyaratPaparan($request);
        $this->semakTukarJenis($request, $dokumen);

        DB::transaction(function () use ($request, $validated, $dokumen) {
            $dokumen->update([
                'kod_dokumen' => $validated['kod_dokumen'],
                'nama_dokumen' => $validated['nama_dokumen'],
                'kategori' => $validated['kategori'] ?? null,
            ]);

            // Padam (soft delete) medan yang dibuang di UI
            $idDipadam = array_filter(array_map('trim', explode(',', (string) $request->input('medan_dipadam', ''))));
            if ($idDipadam) {
                DokumenField::whereIn('dokumen_field_id', $idDipadam)
                    ->where('jenis_dokumen_id', $dokumen->jenis_dokumen_id)
                    ->delete();
            }

            $this->simpanMedan($dokumen, $request->input('medan', []));
        });

        return redirect()->route('jenis-dokumen.index')
                         ->with('success', 'Kategori Dokumen berjaya dikemas kini!');
    }

    /**
     * Kembalikan input medan EAV bagi satu Jenis Dokumen sebagai HTML (partial
     * yang sama dengan Kemaskini Dokumen & Imbas & Arkib). Dipanggil melalui
     * fetch() bila pengguna tukar pilihan Jenis Dokumen dalam borang Muat Naik.
     */
    public function medanBorang($id)
    {
        $dokumen = JenisDokumen::with('fields')->findOrFail($id);

        return view('dokumen.partials.medan-senarai', [
            'medanSenarai' => $dokumen->fields,
            'nilaiAutoIsi' => JenisMedan::nilaiAutoIsi($dokumen->fields, Auth::user()),
            'medanPejabat' => 'lipat',
        ]);
    }

    // 6. Padam rekod
    public function destroy($id)
    {
        $dokumen = JenisDokumen::findOrFail($id);
        $dokumen->delete();

        return redirect()->route('jenis-dokumen.index')
                         ->with('success', 'Kategori Dokumen telah dipadam.');
    }

    /**
     * Validasi medan utama Jenis Dokumen (dikongsi antara store & update).
     */
    private function validasiBorang(Request $request, $idSemasa = null): array
    {
        return $request->validate([
            'kod_dokumen' => [
                'required', 'max:20',
                Rule::unique('jenis_dokumen')->ignore($idSemasa, 'jenis_dokumen_id')->whereNull('deleted_at'),
            ],
            'nama_dokumen' => 'required|max:150',
            'kategori' => 'nullable|max:100',
            'medan' => 'nullable|array',
            'medan.*.nama_field' => 'nullable|max:100',
            'medan.*.kod_field' => 'nullable|max:100',
            'medan.*.jenis_data' => ['nullable', Rule::in(JenisMedan::kod())],
            'medan.*.pilihan' => 'nullable|string',
            'medan.*.bantuan' => 'nullable|string|max:255',
            'medan.*.lain' => 'nullable|boolean',
            // Jadual: definisi lajur dan baris tetap (App\Support\MedanJadual)
            'medan.*.lajur' => 'nullable|array|max:20',
            'medan.*.lajur.*.nama' => 'nullable|string|max:100',
            'medan.*.lajur.*.kod' => 'nullable|string|max:100',
            'medan.*.lajur.*.jenis' => ['nullable', Rule::in(array_keys(MedanJadual::JENIS_LAJUR))],
            'medan.*.lajur.*.pilihan' => 'nullable|string|max:1000',
            'medan.*.baris_tetap' => 'nullable|string|max:1000',
            // Syarat paparan "tunjuk jika" dan pengesahan tukar jenis data (#16)
            'medan.*.tunjuk_kod' => 'nullable|string|max:100',
            'medan.*.tunjuk_nilai' => 'nullable|string|max:255',
            'medan.*.sahkan_tukar' => 'nullable|boolean',
        ], [
            'medan.*.bantuan.max' => 'Teks bantuan medan tidak boleh melebihi 255 aksara.',
            'medan.*.lajur.max' => 'Jadual tidak boleh melebihi 20 lajur.',
        ]);
    }

    /**
     * Syarat "tunjuk jika" mesti merujuk medan lain (dropdown/radio/kotak semak/ya-tidak)
     * dalam kategori yang sama, dan nilainya mesti salah satu pilihan medan itu.
     */
    private function semakSyaratPaparan(Request $request): void
    {
        $medanIkutKod = [];
        foreach ($request->input('medan', []) as $baris) {
            if (!empty($baris['kod_field'])) {
                $medanIkutKod[Str::slug($baris['kod_field'], '_')] = $baris;
            }
        }

        foreach ($request->input('medan', []) as $baris) {
            $kod = Str::slug((string) ($baris['tunjuk_kod'] ?? ''), '_');
            if ($kod === '' || empty($baris['nama_field'])) {
                continue;
            }
            $nama = $baris['nama_field'];
            $sumber = $medanIkutKod[$kod] ?? null;
            $nilai = trim((string) ($baris['tunjuk_nilai'] ?? ''));

            $ralat = match (true) {
                $sumber === null => "kod medan \"{$kod}\" tidak wujud dalam kategori ini",
                $kod === Str::slug((string) ($baris['kod_field'] ?? ''), '_') => 'medan tidak boleh bergantung pada dirinya sendiri',
                !in_array($sumber['jenis_data'] ?? 'teks', JenisMedan::SUMBER_SYARAT, true) => "medan \"{$sumber['nama_field']}\" mesti jenis Dropdown, Radio, Kotak Semak atau Ya/Tidak",
                $nilai === '' => 'nilai syarat mesti diisi',
                !in_array($nilai, $this->pilihanSumber($sumber), true) => "nilai \"{$nilai}\" bukan salah satu pilihan medan \"{$sumber['nama_field']}\"",
                default => null,
            };
            if ($ralat) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'medan' => "Syarat paparan bagi medan \"{$nama}\": {$ralat}.",
                ]);
            }
        }
    }

    /** Pilihan sah bagi medan sumber syarat (baris pembina). */
    private function pilihanSumber(array $baris): array
    {
        if (($baris['jenis_data'] ?? null) === 'ya_tidak') {
            return JenisMedan::YA_TIDAK;
        }

        return $this->pilihanKeArray(is_array($baris['pilihan'] ?? null) ? implode(',', $baris['pilihan']) : ($baris['pilihan'] ?? '')) ?? [];
    }

    /**
     * #16: menukar jenis data medan yang sudah ada nilai boleh menyebabkan nilai lama
     * gagal validasi semasa dokumen diedit. Tolak jika ada nilai yang tidak serasi
     * dengan jenis baharu, kecuali admin menanda "Teruskan tukar jenis" (sahkan_tukar).
     */
    private function semakTukarJenis(Request $request, JenisDokumen $dokumen): void
    {
        $sediaAda = $dokumen->fields()->get()->keyBy('dokumen_field_id');

        foreach ($request->input('medan', []) as $baris) {
            $lama = $sediaAda[$baris['id'] ?? 0] ?? null;
            $jenisBaru = $baris['jenis_data'] ?? 'teks';
            if (!$lama || $jenisBaru === $lama->jenis_data || !empty($baris['sahkan_tukar'])) {
                continue;
            }

            // Medan calon dengan jenis & tetapan baharu, untuk menyemak nilai lama
            $calon = $lama->replicate();
            $calon->dokumen_field_id = $lama->dokumen_field_id;
            $calon->jenis_data = $jenisBaru;
            $calon->pilihan = $this->pilihanKeArray((string) ($baris['pilihan'] ?? ''));
            $calon->tetapan = ['lain' => !empty($baris['lain'])]
                + ($jenisBaru === 'jadual' ? MedanJadual::tetapanDaripadaPembina($baris) : []);

            $tidakSerasi = DokumenData::where('dokumen_field_id', $lama->dokumen_field_id)
                ->pluck('nilai_data')
                ->filter(fn ($v) => $v !== null && $v !== '' && JenisMedan::bersihkanAi($calon, $v) === null)
                ->count();

            if ($tidakSerasi > 0) {
                $label = JenisMedan::SENARAI[$jenisBaru] ?? $jenisBaru;
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'medan' => "Medan \"{$baris['nama_field']}\": {$tidakSerasi} nilai sedia ada tidak serasi dengan jenis \"{$label}\". "
                        . 'Tandakan "Teruskan tukar jenis" pada medan itu jika anda pasti (nilai lama tidak dipadam, tetapi mungkin perlu dibetulkan semasa dokumen diedit).',
                ]);
            }
        }
    }

    /**
     * Setiap medan jadual mesti ada sekurang-kurangnya satu lajur bernama.
     */
    private function semakLajurJadual(Request $request): void
    {
        foreach ($request->input('medan', []) as $baris) {
            if (($baris['jenis_data'] ?? null) !== 'jadual' || empty($baris['nama_field'])) {
                continue;
            }
            if (empty(MedanJadual::tetapanDaripadaPembina($baris)['lajur'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'medan' => "Medan jadual \"{$baris['nama_field']}\" mesti ada sekurang-kurangnya satu lajur.",
                ]);
            }
        }
    }

    /**
     * Pastikan kod_field tidak berulang dalam penyerahan borang yang sama.
     */
    private function semakKodMedanPendua(Request $request): void
    {
        $kodSenarai = collect($request->input('medan', []))
            ->pluck('kod_field')
            ->filter()
            ->map(fn ($k) => Str::slug($k, '_'));

        if ($kodSenarai->count() !== $kodSenarai->unique()->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'medan' => 'Kod Medan tidak boleh berulang dalam Jenis Dokumen yang sama.',
            ]);
        }
    }

    /**
     * Simpan/kemaskini baris medan EAV yang dihantar dari borang.
     * Baris tanpa 'id' = medan baharu. Baris dengan 'id' = kemaskini medan sedia ada.
     */
    private function simpanMedan(JenisDokumen $dokumen, array $senaraiMedan): void
    {
        foreach (array_values($senaraiMedan) as $i => $row) {
            if (empty($row['nama_field']) || empty($row['kod_field'])) {
                continue;
            }

            $jenisData = in_array($row['jenis_data'] ?? null, JenisMedan::kod(), true)
                ? $row['jenis_data']
                : 'teks';

            $adaPilihan = in_array($jenisData, JenisMedan::ADA_PILIHAN, true);

            // Tetapan tambahan (lihat App\Support\JenisMedan); kunci kosong tidak disimpan
            $tetapan = array_filter([
                'bantuan' => trim((string) ($row['bantuan'] ?? '')) ?: null,
                'lain' => $adaPilihan && !empty($row['lain']) ? true : null,
            ], fn ($v) => $v !== null);
            if ($jenisData === 'jadual') {
                $tetapan += MedanJadual::tetapanDaripadaPembina($row);
            }
            // Syarat paparan (disahkan oleh semakSyaratPaparan)
            $tunjukKod = Str::slug((string) ($row['tunjuk_kod'] ?? ''), '_');
            $tunjukNilai = trim((string) ($row['tunjuk_nilai'] ?? ''));
            if ($tunjukKod !== '' && $tunjukNilai !== '') {
                $tetapan['tunjuk_jika'] = ['kod' => $tunjukKod, 'nilai' => $tunjukNilai];
            }
            // Seksyen diisi oleh pegawai/pejabat (bukan pemohon), lihat JenisMedan::idMedanPejabat
            if (in_array($jenisData, JenisMedan::TANPA_NILAI, true) && !empty($row['pejabat'])) {
                $tetapan['pejabat'] = true;
            }
            // Auto-isi daripada profil: hanya sumber yang serasi dengan jenis data medan
            $autoIsi = (string) ($row['auto_isi'] ?? '');
            if (in_array($autoIsi, JenisMedan::AUTO_ISI_SERASI[$jenisData] ?? [], true)) {
                $tetapan['auto_isi'] = $autoIsi;
            }

            $data = [
                'nama_field' => $row['nama_field'],
                'kod_field' => Str::slug($row['kod_field'], '_'),
                'jenis_data' => $jenisData,
                'pilihan' => $adaPilihan ? $this->pilihanKeArray($row['pilihan'] ?? '') : null,
                'tetapan' => $tetapan ?: null,
                'is_required' => !empty($row['is_required']) && !in_array($jenisData, JenisMedan::TANPA_NILAI, true),
                'susunan' => $i + 1,
            ];

            if (!empty($row['id'])) {
                // Kemas kini melalui model, sama seperti create() di bawah (cast pilihan/tetapan & event model)
                DokumenField::where('dokumen_field_id', $row['id'])
                    ->where('jenis_dokumen_id', $dokumen->jenis_dokumen_id)
                    ->first()
                    ?->update($data);
            } else {
                $dokumen->fields()->create($data);
            }
        }
    }

    /**
     * Tukar teks "Baru, Dalam Proses, Selesai" kepada array JSON.
     */
    private function pilihanKeArray(?string $teks): ?array
    {
        if (!$teks) {
            return null;
        }

        $senarai = array_filter(array_map('trim', explode(',', $teks)));
        return $senarai ? array_values($senarai) : null;
    }
}