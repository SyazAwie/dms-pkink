@extends('layouts.app')

@section('title', 'Akses Ditolak')

@section('content')

@php
    $mesej = $exception->getMessage();
    if ($mesej === '' || $mesej === 'This action is unauthorized.') {
        $mesej = 'Anda tidak mempunyai kebenaran untuk melakukan tindakan ini.';
    }
@endphp

<div class="ralat-wrap">
    <div class="glass-panel ralat-panel">
        <div class="ralat-ikon"><i class="bi bi-shield-lock"></i></div>
        <div class="ralat-kod">403</div>
        <h2>Akses Ditolak</h2>
        <p>{{ $mesej }}</p>
        <p class="ralat-hint">Jika anda rasa ini satu kesilapan, sila hubungi Pentadbir Sistem untuk menyemak peranan akaun anda.</p>
        <div class="d-flex justify-content-center gap-2 mt-4 flex-wrap">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}" class="outline-button text-decoration-none">
                <i class="bi bi-arrow-left"></i> <span>Kembali</span>
            </a>
            <a href="{{ route('dashboard') }}" class="btn text-white ralat-btn text-decoration-none">
                <i class="bi bi-grid-1x2-fill me-2"></i> Papan Pemuka
            </a>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
    .ralat-wrap { display: flex; justify-content: center; padding: 30px 0; }
    .ralat-panel { max-width: 520px; width: 100%; padding: 40px 32px; text-align: center; }
    .ralat-ikon {
        width: 64px; height: 64px; margin: 0 auto 14px; border-radius: 18px;
        display: grid; place-items: center; font-size: 28px;
        color: var(--dms-danger); background: rgba(220, 53, 69, .1);
    }
    .ralat-kod { font-size: 13px; font-weight: 800; letter-spacing: 2px; color: var(--dms-muted); }
    .ralat-panel h2 { margin: 6px 0 10px; color: var(--dms-brand); font-weight: 800; }
    .ralat-panel p { color: var(--dms-text); font-size: 14px; margin-bottom: 6px; }
    .ralat-panel .ralat-hint { color: var(--dms-muted); font-size: 12px; }
    .ralat-btn {
        background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary));
        border: none; border-radius: 10px; font-weight: 700; padding: 10px 22px;
    }
    .ralat-btn:hover { color: #fff; }
</style>
@endpush