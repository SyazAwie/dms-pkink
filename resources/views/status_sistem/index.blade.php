@extends('layouts.app')

@section('title', 'Status Sistem')

@section('content')

<div class="page-intro">
    <div>
        <h2>Status Sistem</h2>
        <p>Semakan langsung komponen OCR/AI, notifikasi, sandaran dan penjadual. Muat semula halaman untuk menyemak semula.</p>
    </div>
    <a href="{{ route('status-sistem.index') }}" class="outline-button kekal-label text-decoration-none"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> <span>Semak Semula</span></a>
</div>

<div class="ss-ringkasan mb-4">
    <div class="glass-panel ss-kad ss-ok"><i class="bi bi-check-circle" aria-hidden="true"></i><strong>{{ $ringkasan['ok'] }}</strong><span>Baik</span></div>
    <div class="glass-panel ss-kad ss-amaran"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><strong>{{ $ringkasan['amaran'] }}</strong><span>Amaran</span></div>
    <div class="glass-panel ss-kad ss-ralat"><i class="bi bi-x-octagon" aria-hidden="true"></i><strong>{{ $ringkasan['ralat'] }}</strong><span>Ralat</span></div>
</div>

@foreach($kumpulan as $tajuk => $semakan)
    <div class="glass-panel mb-4 overflow-hidden">
        <div class="section-header px-4 pt-4 pb-3"><h3>{{ $tajuk }}</h3></div>
        @foreach($semakan as $s)
            <div class="ss-baris">
                <span class="ss-lencana ss-{{ $s['status'] }}" title="{{ ['ok' => 'Baik', 'amaran' => 'Amaran', 'ralat' => 'Ralat'][$s['status']] }}">
                    <i class="bi {{ ['ok' => 'bi-check-lg', 'amaran' => 'bi-exclamation-lg', 'ralat' => 'bi-x-lg'][$s['status']] }}" aria-hidden="true"></i>
                </span>
                <div class="ss-teks">
                    <strong>{{ $s['nama'] }}</strong>
                    <span>{{ $s['nilai'] }}</span>
                    @if($s['nota'])<small>{{ $s['nota'] }}</small>@endif
                </div>
            </div>
        @endforeach
    </div>
@endforeach

@endsection

@push('styles')
<style>
    .ss-ringkasan { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .ss-kad { display: grid; grid-template-columns: auto 1fr; grid-template-rows: auto auto; column-gap: 12px; align-items: center; padding: 14px 18px; }
    .ss-kad i { grid-row: span 2; font-size: 24px; }
    .ss-kad strong { font-size: 22px; color: var(--dms-brand); line-height: 1; }
    .ss-kad span { color: var(--dms-muted); font-size: 12px; font-weight: 700; }
    .ss-ok i { color: var(--dms-mint); } .ss-amaran i { color: #b45309; } .ss-ralat i { color: var(--dms-danger); }
    .ss-baris { display: grid; grid-template-columns: 30px 1fr; gap: 12px; align-items: start; padding: 12px 24px; border-top: 1px solid var(--dms-line); }
    .section-header + .ss-baris { border-top: 0; padding-top: 16px; }
    .ss-lencana { display: grid; width: 30px; height: 30px; place-items: center; border-radius: 9px; font-size: 16px; }
    .ss-lencana.ss-ok { color: var(--dms-mint); background: rgba(37, 169, 121, .12); }
    .ss-lencana.ss-amaran { color: #b45309; background: rgba(245, 158, 11, .14); }
    .ss-lencana.ss-ralat { color: var(--dms-danger); background: rgba(220, 53, 69, .1); }
    .ss-teks { display: grid; gap: 2px; min-width: 0; }
    .ss-teks strong { color: var(--dms-text); font-size: 13.5px; }
    .ss-teks span { color: var(--dms-muted); font-size: 12.5px; overflow-wrap: anywhere; }
    .ss-teks small { color: #8a5a0a; font-size: 12px; }
    @media (max-width: 575.98px) { .ss-baris { padding: 12px 16px; } .ss-kad { padding: 12px; } }
</style>
@endpush
