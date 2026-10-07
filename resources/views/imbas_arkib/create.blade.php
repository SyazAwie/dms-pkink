@extends('layouts.app')

@section('title', 'Imbas & Arkib')

@section('content')

<div class="page-intro">
    <div>
        <h2>Imbas &amp; Arkib</h2>
        <p>Khusus untuk dokumen <strong>fizikal yang sedia lulus</strong> — sekadar nak disimpan dalam bentuk digital. Sistem akan cuba isi medan secara automatik daripada imbasan.</p>
    </div>
    <div>
        <a href="{{ route('dokumen.index') }}" class="outline-button text-decoration-none">
            <i class="bi bi-arrow-left"></i> <span>Kembali ke Senarai</span>
        </a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        @foreach($errors->all() as $e)
            <div>{{ $e }}</div>
        @endforeach
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="glass-panel form-panel">
            <div class="section-header px-4 pt-4 pb-3">
                <h3>Langkah 1: Pilih Jenis &amp; Muat Naik Fail</h3>
            </div>

            <form action="{{ route('imbas-arkib.analisis') }}" method="POST" enctype="multipart/form-data" class="px-4 pb-4" id="formAnalisis">
                @csrf

                <div class="mb-4">
                    <label class="dms-label">Jenis Dokumen <span class="text-danger">*</span></label>
                    <select name="jenis_dokumen_id" class="dms-input" required>
                        <option value="">Pilih Jenis Dokumen...</option>
                        @foreach($jenisDokumenSenarai as $jd)
                            <option value="{{ $jd->jenis_dokumen_id }}">{{ $jd->nama_dokumen }} ({{ $jd->kod_dokumen }})</option>
                        @endforeach
                    </select>
                    <div class="dms-hint">Medan yang akan cuba diisi automatik bergantung kategori ini.</div>
                </div>

                <div class="mb-2">
                    <label class="dms-label">Fail Imbasan <span class="text-danger">*</span></label>
                    <input type="file" name="fail_scan" class="dms-input" accept=".pdf,.jpg,.jpeg,.png" required>
                    <div class="dms-hint">PDF, JPG, PNG sahaja. Maksimum 10MB. Satu fail setiap kali.</div>
                </div>

                <hr class="dms-divider">

                <div class="d-flex justify-content-end">
                    <button type="submit" id="btnAnalisis" class="btn text-white save-btn">
                        <i class="bi bi-magic me-2"></i> Analisis &amp; Isi Automatik
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="glass-panel tips-panel">
            <div class="summary-head px-1">
                <div><div class="summary-label">CARA IA BERFUNGSI</div></div>
                <div class="summary-icon" style="color: var(--dms-mint); background: rgba(37,169,121,.12);"><i class="bi bi-lightbulb"></i></div>
            </div>
            <ul class="tips-list">
                <li><i class="bi bi-1-circle"></i> OCR baca teks daripada imbasan</li>
                <li><i class="bi bi-2-circle"></i> AI tempatan cuba padankan teks ke medan yang betul</li>
                <li><i class="bi bi-3-circle"></i> <strong>Anda semak &amp; betulkan</strong> sebelum simpan — tiada apa disimpan secara automatik tanpa semakan</li>
                <li><i class="bi bi-4-circle"></i> Selepas simpan, dokumen terus bertanda <strong>Diluluskan</strong> (bukan perlu Penyokong/Pelulus)</li>
            </ul>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .form-panel, .tips-panel { padding: 0; }
    .tips-panel { padding: 20px; }
    .section-header { border-bottom: 1px solid var(--dms-line); }
    .section-header h3 { margin: 0; color: var(--dms-brand); font-size: 16px; font-weight: 800; }
    .dms-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--dms-text); margin-bottom: 6px; }
    .dms-input {
        width: 100%; padding: 11px 14px; border: 1px solid var(--dms-line); border-radius: 10px;
        background: var(--dms-surface-strong); font-size: 13.5px; color: var(--dms-text); outline: none;
    }
    .dms-hint { font-size: 11px; color: var(--dms-muted); margin-top: 6px; }
    .dms-divider { border-top: 1px solid var(--dms-line); opacity: 1; margin: 8px 0; }
    .save-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none; border-radius: 10px; font-weight: 700; padding: 10px 22px;
    }
    .save-btn:hover { color: #fff; }
    .save-btn:disabled { opacity: .7; cursor: wait; }
    .tips-list { list-style: none; padding: 0; margin: 14px 0 0; display: flex; flex-direction: column; gap: 10px; }
    .tips-list li { font-size: 12px; color: var(--dms-muted); display: flex; align-items: flex-start; gap: 8px; }
    .tips-list li i { color: var(--dms-mint); margin-top: 1px; }
</style>
@endpush

@push('scripts')
<script>
    document.getElementById('formAnalisis').addEventListener('submit', function () {
        const btn = document.getElementById('btnAnalisis');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Memproses OCR + AI... (boleh ambil masa)';
    });
</script>
@endpush