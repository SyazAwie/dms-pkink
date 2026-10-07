@extends('layouts.app')

@section('title', 'Tambah Kategori Dokumen')

@section('content')

<!-- Bahagian Pengenalan Halaman -->
<div class="page-intro">
    <div>
        <h2>Tambah Kategori Baharu</h2>
        <p>Daftar klasifikasi fail baharu ke dalam Sistem Arkib Digital PKINK.</p>
    </div>
    <div>
        <a href="{{ route('jenis-dokumen.index') }}" class="outline-button text-decoration-none">
            <i class="bi bi-arrow-left"></i> <span>Kembali ke Senarai</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Borang Utama -->
    <div class="col-lg-8">
        <div class="glass-panel form-panel">
            <div class="section-header px-4 pt-4 pb-3">
                <div>
                    <h3>Maklumat Kategori</h3>
                    <p>Sila lengkapkan medan bertanda <span class="text-danger">*</span></p>
                </div>
            </div>

            <form action="{{ route('jenis-dokumen.store') }}" method="POST" id="jenisDokumenForm" class="px-4 pb-4">
                @csrf

                <div class="mb-4">
                    <label class="dms-label">Kod Dokumen <span class="text-danger">*</span></label>
                    <input type="text"
                           name="kod_dokumen"
                           id="kod_dokumen"
                           class="dms-input @error('kod_dokumen') is-invalid @enderror"
                           value="{{ old('kod_dokumen') }}"
                           placeholder="Contoh: SRT-RASMI"
                           maxlength="20"
                           autocomplete="off">
                    <div class="dms-hint">Kod ringkas dan unik untuk klasifikasi fail (cth: SRT-RASMI, INV, MEMO)</div>
                    @error('kod_dokumen') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label class="dms-label">Nama Dokumen <span class="text-danger">*</span></label>
                    <input type="text"
                           name="nama_dokumen"
                           id="nama_dokumen"
                           class="dms-input @error('nama_dokumen') is-invalid @enderror"
                           value="{{ old('nama_dokumen') }}"
                           placeholder="Contoh: Surat Rasmi Kerajaan"
                           maxlength="150"
                           autocomplete="off">
                    @error('nama_dokumen') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <div class="mb-2">
                    <label class="dms-label">Kategori Kumpulan <span class="dms-optional">(Pilihan)</span></label>
                    <input type="text"
                           name="kategori"
                           id="kategori"
                           class="dms-input"
                           value="{{ old('kategori') }}"
                           placeholder="Contoh: Pentadbiran"
                           maxlength="100"
                           autocomplete="off">
                    <div class="dms-hint">Kumpulan umum untuk memudahkan carian dan penapisan</div>
                </div>

                <hr class="dms-divider">

                <!-- ============ Seksyen Medan Dinamik (EAV) ============ -->
                <div class="eav-section">
                    <div class="eav-header">
                        <div>
                            <label class="dms-label mb-0">Medan Dokumen (EAV)</label>
                            <div class="dms-hint mb-0">Medan input yang akan dipaparkan bila kakitangan muat naik dokumen jenis ini.</div>
                        </div>
                        <button type="button" id="btnTambahMedan" class="btn text-white save-btn eav-btn-tambah">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Medan
                        </button>
                    </div>

                    @error('medan')
                        <div class="dms-error mb-2">{{ $message }}</div>
                    @enderror

                    <div id="senaraiMedan" class="mt-3"></div>
                    <div id="medanKosong" class="dms-hint text-center py-3">
                        Belum ada medan didaftarkan. Klik "Tambah Medan" untuk mula.
                    </div>
                </div>
                <input type="hidden" name="medan_dipadam" id="medan_dipadam" value="">
                <!-- ============ Tamat Seksyen EAV ============ -->

                <hr class="dms-divider">

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route('jenis-dokumen.index') }}" class="outline-button text-decoration-none">
                        <span>Batal</span>
                    </a>
                    <button type="submit" class="btn text-white save-btn">
                        <i class="bi bi-save me-2"></i> Simpan Rekod
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Panel Bantuan / Pratonton -->
    <div class="col-lg-4">
        <div class="glass-panel preview-panel mb-4">
            <div class="summary-head px-1">
                <div>
                    <div class="summary-label">PRATONTON REKOD</div>
                </div>
                <div class="summary-icon"><i class="bi bi-eye"></i></div>
            </div>
            <div class="preview-row">
                <span class="badge kod-badge" id="previewKod">KOD</span>
                <span class="preview-name" id="previewNama">Nama dokumen akan dipaparkan di sini</span>
            </div>
            <div class="preview-kategori-row" id="previewKategoriRow" style="display:none;">
                <span class="badge kategori-badge" id="previewKategori"></span>
            </div>
            <div class="dms-hint mt-2">Begini kategori anda akan kelihatan dalam senarai.</div>
        </div>

        <div class="glass-panel tips-panel">
            <div class="summary-head px-1">
                <div>
                    <div class="summary-label">PANDUAN RINGKAS</div>
                </div>
                <div class="summary-icon" style="color: var(--dms-mint); background: rgba(37,169,121,.12);"><i class="bi bi-lightbulb"></i></div>
            </div>
            <ul class="tips-list">
                <li><i class="bi bi-check2-circle"></i> Gunakan kod pendek dan konsisten (cth: SRT-RASMI).</li>
                <li><i class="bi bi-check2-circle"></i> Elakkan pertindihan kod dengan kategori sedia ada.</li>
                <li><i class="bi bi-check2-circle"></i> Kategori kumpulan bersifat pilihan tetapi membantu carian.</li>
                <li><i class="bi bi-check2-circle"></i> Setiap Medan mesti ada Kod Medan yang unik dalam kategori ini.</li>
            </ul>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .form-panel, .preview-panel, .tips-panel { padding: 0; }
    .preview-panel, .tips-panel { padding: 20px; }

    .section-header { display: flex; justify-content: space-between; align-items: center; gap: 14px; flex-wrap: wrap; border-bottom: 1px solid var(--dms-line); }
    .section-header h3 { margin: 0; color: var(--dms-brand); font-size: 16px; font-weight: 800; }
    .section-header p { margin: 4px 0 0; color: var(--dms-muted); font-size: 11px; }

    .dms-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--dms-text); margin-bottom: 6px; }
    .dms-optional { font-weight: 500; color: var(--dms-muted); font-size: 11.5px; }
    .dms-input {
        width: 100%;
        padding: 11px 14px;
        border: 1px solid var(--dms-line);
        border-radius: 10px;
        background: var(--dms-surface-strong);
        font-size: 13.5px;
        color: var(--dms-text);
        outline: none;
        transition: border-color .2s ease, box-shadow .2s ease;
    }
    .dms-input:focus { border-color: var(--dms-primary); box-shadow: 0 0 0 3px rgba(23,105,210,.12); }
    .dms-input.is-invalid { border-color: var(--dms-danger); }
    .dms-hint { font-size: 11px; color: var(--dms-muted); margin-top: 6px; }
    .dms-error { font-size: 11.5px; color: var(--dms-danger); font-weight: 600; margin-top: 6px; }
    .dms-divider { border-top: 1px solid var(--dms-line); opacity: 1; margin: 8px 0; }

    #kod_dokumen { text-transform: uppercase; letter-spacing: .5px; font-weight: 700; }

    .save-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none;
        border-radius: 10px;
        font-weight: 700;
        padding: 10px 22px;
        box-shadow: 0 8px 20px rgba(23, 105, 210, .2);
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .save-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(23,105,210,.28); color: #fff; }

    .preview-row {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-top: 14px;
        padding: 14px;
        border: 1px dashed var(--dms-line);
        border-radius: 10px;
        background: rgba(23, 105, 210, .03);
    }
    .preview-kategori-row { margin-top: 10px; padding: 0 14px; }
    .kod-badge {
        background: rgba(23, 105, 210, .1);
        color: var(--dms-primary);
        font-weight: 800;
        padding: 7px 10px;
        border-radius: 6px;
        letter-spacing: .3px;
        white-space: nowrap;
    }
    .kategori-badge {
        background: rgba(37, 169, 121, .1);
        color: var(--dms-mint);
        font-weight: 700;
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 11px;
    }
    .preview-name { font-size: 13px; font-weight: 700; color: var(--dms-brand); }

    .tips-list { list-style: none; padding: 0; margin: 14px 0 0; display: flex; flex-direction: column; gap: 10px; }
    .tips-list li { font-size: 12px; color: var(--dms-muted); display: flex; align-items: flex-start; gap: 8px; }
    .tips-list li i { color: var(--dms-mint); margin-top: 1px; }

    /* ============ Seksyen Medan Dinamik (EAV) ============ */
    .eav-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .eav-btn-tambah { padding: 8px 16px; font-size: 12.5px; white-space: nowrap; }

    .medan-row {
        border: 1px dashed var(--dms-line);
        border-radius: 10px;
        padding: 14px;
        margin-bottom: 12px;
        background: rgba(23, 105, 210, .02);
    }
    .medan-grid {
        display: grid;
        grid-template-columns: 1.6fr 1.2fr 1fr auto auto;
        gap: 12px;
        align-items: end;
    }
    @media (max-width: 900px) {
        .medan-grid { grid-template-columns: 1fr 1fr; }
    }
    .medan-checkbox {
        display: flex; align-items: center; gap: 6px;
        font-size: 12.5px; color: var(--dms-text); font-weight: 600;
        padding: 11px 10px; border: 1px solid var(--dms-line); border-radius: 10px;
        background: var(--dms-surface-strong); white-space: nowrap; cursor: pointer;
    }
    .btn-buang-medan {
        border: 1px solid var(--dms-line); background: var(--dms-surface-strong);
        color: var(--dms-danger); border-radius: 10px; padding: 10px 12px;
        cursor: pointer; transition: background .2s ease;
    }
    .btn-buang-medan:hover { background: rgba(220,53,69,.08); }
    .medan-pilihan-wrap { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; }
</style>
@endpush

@push('scripts')
<script>
    (() => {
        const kodInput = document.getElementById('kod_dokumen');
        const namaInput = document.getElementById('nama_dokumen');
        const kategoriInput = document.getElementById('kategori');
        const previewKod = document.getElementById('previewKod');
        const previewNama = document.getElementById('previewNama');
        const previewKategori = document.getElementById('previewKategori');
        const previewKategoriRow = document.getElementById('previewKategoriRow');

        function updatePreview() {
            previewKod.textContent = kodInput.value.trim() ? kodInput.value.trim().toUpperCase() : 'KOD';
            previewNama.textContent = namaInput.value.trim() ? namaInput.value.trim() : 'Nama dokumen akan dipaparkan di sini';

            const kategoriVal = kategoriInput.value.trim();
            if (kategoriVal) {
                previewKategori.textContent = kategoriVal;
                previewKategoriRow.style.display = 'block';
            } else {
                previewKategoriRow.style.display = 'none';
            }
        }

        if (kodInput) {
            kodInput.addEventListener('input', () => {
                kodInput.value = kodInput.value.toUpperCase();
                updatePreview();
            });
        }
        if (namaInput) namaInput.addEventListener('input', updatePreview);
        if (kategoriInput) kategoriInput.addEventListener('input', updatePreview);

        updatePreview();
    })();
</script>

<script>
    window.__medanSediaAda = [];
</script>
@include('jenis_dokumen.partials.medan-script')
@endpush