<?php

namespace App\Http\Controllers;

use App\Models\JenisDokumen;
use App\Models\DokumenField;
use App\Support\JenisMedan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class JenisDokumenController extends Controller
{
    // 1. Fungsi paparkan senarai
    public function index()
    {
        $senarai = JenisDokumen::all();
        return view('jenis_dokumen.index', compact('senarai'));
    }

    // 2. Fungsi paparkan borang tambah baharu
    public function create()
    {
        return view('jenis_dokumen.create');
    }

    // 3. Fungsi simpan data ke database
    public function store(Request $request)
    {
        $validated = $this->validasiBorang($request);
        $this->semakKodMedanPendua($request);

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
        return view('jenis_dokumen.edit', compact('dokumen'));
    }

    // 5. Simpan kemas kini data
    public function update(Request $request, $id)
    {
        $dokumen = JenisDokumen::findOrFail($id);

        $validated = $this->validasiBorang($request, $id);
        $this->semakKodMedanPendua($request);

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

        return view('dokumen.partials.medan-senarai', ['medanSenarai' => $dokumen->fields]);
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
        ], [
            'medan.*.bantuan.max' => 'Teks bantuan medan tidak boleh melebihi 255 aksara.',
        ]);
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