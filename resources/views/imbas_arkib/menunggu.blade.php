@extends('layouts.app')

{{-- Analisis OCR/AI dalam queue (T2). Dikongsi Imbas & Arkib dan Imbas & Hantar. --}}
@section('title', 'Semak Hasil Imbasan')

@section('content')

<div class="page-intro">
    <div>
        <h2>Semak &amp; Sahkan</h2>
        <p>{{ $jenisDokumen->nama_dokumen }} &middot; {{ $namaAsal }}</p>
    </div>
    <div>
        <a href="{{ route("imbas-{$mod}.create") }}" class="outline-button text-decoration-none">
            <i class="bi bi-arrow-left"></i> <span>Mula Semula</span>
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success"><i class="bi bi-check2-circle me-1"></i> {{ session('success') }}</div>
@endif

<div class="row g-4">
    <div class="col-lg-6 col-xxl-7">
        <div class="glass-panel menunggu-panel" id="panelMenunggu" data-url="{{ route("imbas-{$mod}.status", $token) }}" aria-live="polite">
            <div class="spinner-border text-primary mb-3" role="status" aria-hidden="true"></div>
            <h3 id="tajukStatus">
                @if($status === 'diproses')
                    {{ $bacaSemula ? 'Sedang membaca semula dengan AI penglihatan…' : 'Sedang membaca dokumen…' }}
                @else
                    Dalam giliran
                @endif
            </h3>
            <p class="mb-1" id="teksGiliran">
                @if($status !== 'diproses' && $diHadapan)
                    {{ $diHadapan }} dokumen di hadapan anda.
                @elseif($status !== 'diproses')
                    Dokumen anda akan diproses sebentar lagi.
                @else
                    OCR dan AI sedang mengisi medan borang.
                @endif
            </p>
            <p class="dms-hint mb-0">Masa berlalu: <strong id="masaBerlalu">0 s</strong>. Biasanya 10–40 saat bagi setiap dokumen.
                Halaman ini dikemas kini sendiri; anda boleh menyemak fail imbasan di sebelah sementara menunggu.</p>
        </div>
    </div>

    <div class="col-lg-6 col-xxl-5">
        <div class="glass-panel tips-panel">
            <div class="summary-head px-1">
                <div><div class="summary-label">FAIL IMBASAN</div></div>
                <a href="{{ route("imbas-{$mod}.pratonton", $token) }}" target="_blank" rel="noopener" class="summary-icon text-decoration-none" title="Buka dalam tab baharu">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>
            </div>
            @if(in_array(strtolower(pathinfo($namaAsal, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true))
                <img src="{{ route("imbas-{$mod}.pratonton", $token) }}" alt="Imbasan {{ $namaAsal }}" class="pratonton-fail mt-2">
            @else
                <iframe src="{{ route("imbas-{$mod}.pratonton", $token) }}" title="Imbasan {{ $namaAsal }}" class="pratonton-fail mt-2"></iframe>
            @endif
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .menunggu-panel { padding: 40px 24px; text-align: center; }
    .menunggu-panel h3 { color: var(--dms-brand); font-size: 18px; font-weight: 800; margin-bottom: 6px; }
    .menunggu-panel .spinner-border { width: 2.5rem; height: 2.5rem; }
    .tips-panel { padding: 18px; }
    .dms-hint { font-size: 11.5px; color: var(--dms-muted); }
    .pratonton-fail {
        display: block; width: 100%; height: 60vh; min-height: 320px; border: 1px solid var(--dms-line);
        border-radius: 10px; background: #fff;
    }
    img.pratonton-fail { height: auto; min-height: 0; max-height: 60vh; object-fit: contain; }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const panel = document.getElementById('panelMenunggu');
        const mula = Date.now();
        const masa = document.getElementById('masaBerlalu');
        setInterval(() => { masa.textContent = Math.floor((Date.now() - mula) / 1000) + ' s'; }, 1000);

        async function semak() {
            try {
                const res = await fetch(panel.dataset.url, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                if (!res.ok) throw new Error(res.status);
                const d = await res.json();
                if (d.muat_semula) { window.location.reload(); return; }
                if (d.status === 'diproses') {
                    document.getElementById('tajukStatus').textContent = 'Sedang membaca dokumen…';
                    document.getElementById('teksGiliran').textContent = 'OCR dan AI sedang mengisi medan borang.';
                } else if (d.di_hadapan) {
                    document.getElementById('tajukStatus').textContent = 'Dalam giliran';
                    document.getElementById('teksGiliran').textContent = d.di_hadapan + ' dokumen di hadapan anda.';
                }
            } catch (e) { /* rangkaian terputus seketika: cuba lagi */ }
            setTimeout(semak, 2000);
        }
        setTimeout(semak, 1500);
    })();
</script>
@endpush
