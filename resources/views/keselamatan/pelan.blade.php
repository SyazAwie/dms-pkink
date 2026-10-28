@extends('layouts.app')

@section('title', 'Pelan Tindak Balas Insiden')

@section('content')

<div class="page-intro tiada-cetak">
    <div>
        <h2>Pelan Tindak Balas Insiden</h2>
        <p><strong>Cetak dan simpan salinan kertas di luar bilik pelayan.</strong> Bila pelayan diserang, halaman ini mungkin tidak boleh dibuka.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('keselamatan.index') }}" class="outline-button kekal-label text-decoration-none"><i class="bi bi-arrow-left" aria-hidden="true"></i> <span>Pusat Keselamatan</span></a>
        <button type="button" class="btn text-white save-btn" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i> Cetak</button>
    </div>
</div>

<div class="glass-panel pelan-isi">
    {!! $html !!}
</div>

@endsection

@push('styles')
<style>
    .pelan-isi { padding: 28px 34px; font-size: 14px; line-height: 1.7; color: var(--dms-text); }
    .pelan-isi h1 { color: var(--dms-brand); font-size: 22px; font-weight: 800; margin-bottom: 14px; }
    .pelan-isi h2 { color: var(--dms-brand); font-size: 18px; font-weight: 800; margin: 26px 0 10px; padding-top: 14px; border-top: 1px solid var(--dms-line); }
    .pelan-isi h3 { color: var(--dms-text); font-size: 15px; font-weight: 800; margin: 20px 0 8px; }
    .pelan-isi blockquote { margin: 0 0 16px; padding: 12px 16px; border-left: 4px solid var(--dms-danger); background: rgba(220, 53, 69, .06); border-radius: 0 10px 10px 0; }
    .pelan-isi blockquote p { margin: 0; }
    .pelan-isi table { width: 100%; margin: 8px 0 16px; border-collapse: collapse; font-size: 13px; }
    .pelan-isi th, .pelan-isi td { padding: 7px 10px; border: 1px solid var(--dms-line); vertical-align: top; }
    .pelan-isi th { background: rgba(23, 105, 210, .06); }
    .pelan-isi pre { padding: 12px 14px; border-radius: 10px; background: #0f2236; color: #e6eef8; font-size: 12.5px; white-space: pre-wrap; }
    .pelan-isi code { font-size: 12.5px; }
    .pelan-isi hr { display: none; }
    .save-btn { background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary)); border: none; border-radius: 10px; font-weight: 700; }
    @media (max-width: 575.98px) { .pelan-isi { padding: 18px; } }
    @media print {
        .sidebar, .topbar, header, .tiada-cetak, nav { display: none !important; }
        .content-wrap, .main-area, main { margin: 0 !important; padding: 0 !important; }
        .pelan-isi { box-shadow: none !important; border: 0 !important; background: #fff !important; padding: 0; font-size: 11pt; }
        .pelan-isi pre { background: #f3f3f3; color: #000; border: 1px solid #ccc; }
        .pelan-isi h2 { break-after: avoid; }
        .pelan-isi table, .pelan-isi pre { break-inside: avoid; }
    }
</style>
@endpush
