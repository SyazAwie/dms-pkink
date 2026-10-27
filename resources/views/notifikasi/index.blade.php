@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')

<div class="page-intro">
    <div>
        <h2>Notifikasi</h2>
        <p>Makluman permohonan, kelulusan dan sistem yang berkaitan dengan peranan anda.</p>
    </div>
    <form method="POST" action="{{ route('notifikasi.baca-semua') }}" class="m-0">
        @csrf
        <button type="submit" class="outline-button kekal-label"><i class="bi bi-check2-all" aria-hidden="true"></i> <span>Tandakan semua dibaca</span></button>
    </form>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif

<div class="d-flex gap-2 mb-3">
    <a href="{{ route('notifikasi.index') }}" class="tab-notif {{ request('papar') === 'belum' ? '' : 'aktif' }}">Semua</a>
    <a href="{{ route('notifikasi.index', ['papar' => 'belum']) }}" class="tab-notif {{ request('papar') === 'belum' ? 'aktif' : '' }}">Belum dibaca</a>
</div>

<div class="glass-panel overflow-hidden">
    @forelse($senarai as $n)
        <a href="{{ route('notifikasi.buka', $n->id) }}" class="notif-baris {{ $n->read_at ? '' : 'belum-baca' }}">
            <span class="loceng-ikon warna-{{ $n->data['warna'] ?? 'biru' }}"><i class="bi {{ $n->data['ikon'] ?? 'bi-bell' }}" aria-hidden="true"></i></span>
            <span class="notif-teks">
                <strong>{{ $n->data['tajuk'] ?? 'Notifikasi' }}</strong>
                <span>{{ $n->data['mesej'] ?? '' }}</span>
            </span>
            <small class="notif-masa" title="{{ $n->created_at?->translatedFormat('d M Y, h:i A') }}">{{ $n->created_at?->diffForHumans() }}</small>
        </a>
    @empty
        <div class="text-center py-5 dms-hint"><i class="bi bi-bell-slash d-block mb-2" style="font-size: 28px;" aria-hidden="true"></i>Tiada notifikasi.</div>
    @endforelse

    @if($senarai->hasPages())
        <div class="px-4 pb-4 pt-3">{{ $senarai->links() }}</div>
    @endif
</div>

@endsection

@push('styles')
<style>
    .tab-notif { padding: 7px 14px; border: 1px solid var(--dms-line); border-radius: 999px; color: var(--dms-text); background: var(--dms-surface-strong); font-size: 12.5px; font-weight: 700; }
    .tab-notif.aktif { border-color: var(--dms-primary); color: #fff; background: var(--dms-primary); }
    .notif-baris { display: grid; grid-template-columns: 34px minmax(0, 1fr) auto; gap: 12px; align-items: start; padding: 14px 18px; border-bottom: 1px solid var(--dms-line); color: var(--dms-text); }
    .notif-baris:last-child { border-bottom: 0; }
    .notif-baris:hover { background: rgba(23, 105, 210, .04); }
    .notif-baris.belum-baca { background: rgba(23, 105, 210, .06); }
    .notif-baris.belum-baca strong::after { content: ""; display: inline-block; width: 7px; height: 7px; margin-left: 6px; border-radius: 50%; background: var(--dms-primary); vertical-align: middle; }
    .notif-teks strong { display: block; font-size: 13.5px; }
    .notif-teks span { color: var(--dms-muted); font-size: 12.5px; }
    .notif-masa { color: var(--dms-muted); font-size: 11.5px; white-space: nowrap; }
    .dms-hint { font-size: 12px; color: var(--dms-muted); }
    @media (max-width: 575.98px) {
        .notif-baris { grid-template-columns: 34px minmax(0, 1fr); }
        .notif-masa { grid-column: 2; }
    }
</style>
@endpush
