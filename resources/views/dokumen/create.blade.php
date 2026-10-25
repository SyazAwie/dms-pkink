@extends('layouts.app')

@section('title', 'Muat Naik Dokumen')

@section('content')

<div class="page-intro">
    <div>
        <h2>Muat Naik Dokumen</h2>
        <p>Daftarkan dokumen baharu ke dalam Sistem Arkib Digital PKINK.</p>
    </div>
    <div>
        <a href="{{ route('dokumen.index') }}" class="outline-button text-decoration-none">
            <i class="bi bi-arrow-left"></i> <span>Kembali ke Senarai</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="glass-panel form-panel">
            <div class="section-header px-4 pt-4 pb-3">
                <div>
                    <h3>Maklumat Dokumen</h3>
                    <p>Sila lengkapkan medan bertanda <span class="text-danger">*</span></p>
                </div>
            </div>

            <form action="{{ route('dokumen.store') }}" method="POST" id="dokumenForm" class="px-4 pb-4" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <label class="dms-label">Jenis Dokumen <span class="text-danger">*</span></label>
                    <select name="jenis_dokumen_id"
                            id="jenis_dokumen_id"
                            class="dms-input @error('jenis_dokumen_id') is-invalid @enderror">
                        <option value="">Pilih Jenis Dokumen...</option>
                        @foreach($jenisDokumenSenarai as $jd)
                            <option value="{{ $jd->jenis_dokumen_id }}" {{ old('jenis_dokumen_id') == $jd->jenis_dokumen_id ? 'selected' : '' }}>
                                {{ $jd->nama_dokumen }} ({{ $jd->kod_dokumen }})
                            </option>
                        @endforeach
                    </select>
                    @error('jenis_dokumen_id') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label class="dms-label">Tarikh Dokumen <span class="text-danger">*</span></label>
                    <input type="date"
                           name="tarikh_dokumen"
                           class="dms-input @error('tarikh_dokumen') is-invalid @enderror"
                           value="{{ old('tarikh_dokumen', date('Y-m-d')) }}">
                    <div class="dms-hint">No. Rujukan akan dijana automatik ikut tahun tarikh ini.</div>
                    @error('tarikh_dokumen') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <div class="mb-2">
                    <label class="dms-label">Perkara <span class="dms-optional">(Pilihan)</span></label>
                    <input type="text"
                           name="perkara"
                           class="dms-input @error('perkara') is-invalid @enderror"
                           value="{{ old('perkara') }}"
                           placeholder="Ringkasan perkara dokumen"
                           maxlength="255">
                    @error('perkara') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <hr class="dms-divider">

                <!-- ============ Medan Dinamik (EAV) ikut Jenis Dokumen ============ -->
                <div class="eav-section">
                    <label class="dms-label mb-0">Medan Dokumen</label>
                    <div class="dms-hint mb-2">Medan berikut akan berubah automatik ikut Jenis Dokumen dipilih.</div>

                    {{-- Selepas ralat validasi, medan dirender di sini dengan nilai old(); jika tidak, dimuatkan melalui fetch() --}}
                    <div id="medanDataContainer">
                        @if($jenisTerpilih)
                            @include('dokumen.partials.medan-senarai', ['medanSenarai' => $jenisTerpilih->fields])
                        @endif
                    </div>
                    <div id="medanDataLoading" class="dms-hint text-center py-3" style="display:none;">
                        Memuatkan medan...
                    </div>
                    <div id="medanDataKosong" class="dms-hint text-center py-3" @if($jenisTerpilih) style="display:none;" @endif>
                        Sila pilih Jenis Dokumen dahulu untuk papar medan berkaitan.
                    </div>
                </div>

                <hr class="dms-divider">

                <div class="mb-2">
                    <label class="dms-label">Fail Imbasan <span class="text-danger">*</span></label>
                    <input type="file"
                           name="fail_scan[]"
                           class="dms-input @error('fail_scan') is-invalid @enderror"
                           accept=".pdf,.jpg,.jpeg,.png"
                           multiple>
                    <div class="dms-hint">Format dibenarkan: PDF, JPG, PNG. Maksimum 10MB setiap fail. Boleh pilih lebih dari satu fail.</div>
                    @error('fail_scan') <div class="dms-error">{{ $message }}</div> @enderror
                    @error('fail_scan.*') <div class="dms-error">{{ $message }}</div> @enderror
                </div>

                <hr class="dms-divider">

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="{{ route('dokumen.index') }}" class="outline-button text-decoration-none">
                        <span>Batal</span>
                    </a>
                    <button type="submit" class="btn text-white save-btn">
                        <i class="bi bi-cloud-upload me-2"></i> Muat Naik Dokumen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass-panel tips-panel">
            <div class="summary-head px-1">
                <div>
                    <div class="summary-label">PANDUAN RINGKAS</div>
                </div>
                <div class="summary-icon" style="color: var(--dms-mint); background: rgba(37,169,121,.12);"><i class="bi bi-lightbulb"></i></div>
            </div>
            <ul class="tips-list">
                <li><i class="bi bi-check2-circle"></i> No. Rujukan dijana automatik: {KOD}/{TAHUN}/{TURUTAN}.</li>
                <li><i class="bi bi-check2-circle"></i> Pilih Jenis Dokumen dahulu untuk papar medan berkaitan.</li>
                <li><i class="bi bi-check2-circle"></i> Medan bertanda <span class="text-danger">*</span> wajib diisi.</li>
                <li><i class="bi bi-check2-circle"></i> Fail imbasan akan dilampirkan terus pada dokumen ini.</li>
            </ul>
        </div>
    </div>
</div>

@endsection

@push('styles')
@include('dokumen.partials.medan-gaya')
<style>
    .form-panel, .tips-panel { padding: 0; }
    .tips-panel { padding: 20px; }

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

    .tips-list { list-style: none; padding: 0; margin: 14px 0 0; display: flex; flex-direction: column; gap: 10px; }
    .tips-list li { font-size: 12px; color: var(--dms-muted); display: flex; align-items: flex-start; gap: 8px; }
    .tips-list li i { color: var(--dms-mint); margin-top: 1px; }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const selectJenis = document.getElementById('jenis_dokumen_id');
    const container = document.getElementById('medanDataContainer');
    const emptyState = document.getElementById('medanDataKosong');
    const loadingState = document.getElementById('medanDataLoading');
    // Laluan dijana oleh route() (bukan laluan keras), jadi tetap betul jika aplikasi dipasang dalam subfolder
    const urlMedan = @json(route('jenis-dokumen.medan', '__ID__'));

    // Medan dirender di pelayan (partial dokumen.partials.medan-senarai) dan dipulangkan sebagai HTML
    async function papar(jenisId) {
        container.innerHTML = '';
        emptyState.style.display = 'none';
        loadingState.style.display = 'none';

        if (!jenisId) {
            emptyState.style.display = 'block';
            return;
        }

        loadingState.style.display = 'block';
        try {
            const res = await fetch(urlMedan.replace('__ID__', encodeURIComponent(jenisId)), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) throw new Error('Gagal ambil medan');
            container.innerHTML = await res.text();
        } catch (e) {
            container.innerHTML = '<div class="dms-error">Gagal muatkan medan. Sila cuba semula.</div>';
        } finally {
            loadingState.style.display = 'none';
        }
    }

    if (selectJenis) {
        selectJenis.addEventListener('change', () => papar(selectJenis.value));
        // Jika medan sudah dirender di pelayan (selepas ralat validasi), jangan muat semula: nilai old() akan hilang
        if (selectJenis.value && !container.children.length) papar(selectJenis.value);
    }
})();
</script>
@endpush
