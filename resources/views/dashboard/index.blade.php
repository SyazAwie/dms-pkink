@extends('layouts.app')

@section('title', 'Papan Pemuka')

@section('content')

@php
    $u = Auth::user();
    $jam = (int) now()->format('G');
    $ucapan = match (true) {
        $jam < 12 => 'Selamat pagi',
        $jam < 14 => 'Selamat tengah hari',
        $jam < 19 => 'Selamat petang',
        default => 'Selamat malam',
    };
    // Nama ringkas: sebelum "bin"/"binti" (cth. "Ahmad Hakim bin Yusof" → "Ahmad Hakim")
    $namaRingkas = trim(preg_split('/\s+(bin|binti|b\.|bt\.|a\/l|a\/p)\s+/i', $u->nama_staff)[0]);
@endphp

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif

{{-- Kepala: ucapan, peranan & tarikh --}}
<div class="pp-kepala">
    <div>
        <h2>{{ $ucapan }}, {{ $namaRingkas }}</h2>
        <div class="pp-peranan">
            @foreach($u->roles->whereIn('kod_roles', $u->kodPeranan()) as $r){{-- peranan aktif sahaja --}}
                <span class="pp-cip">{{ $r->nama_roles }}</span>
            @endforeach
            @if($u->bahagian)
                <span class="pp-cip pp-cip-kelabu"><i class="bi bi-building"></i> {{ $u->bahagian->nama_bahagian }}</span>
            @endif
        </div>
    </div>
    <div class="date-chip"><i class="bi bi-calendar3"></i> {{ now()->locale('ms')->translatedFormat('d F Y') }}</div>
</div>

{{-- Tindakan pantas ikut peranan --}}
@if($tindakanPantas)
    <nav class="pp-pantas" aria-label="Tindakan pantas">
        @foreach($tindakanPantas as $t)
            <a href="{{ $t['url'] }}" class="{{ ($t['utama'] ?? false) ? 'utama' : '' }}">
                <i class="bi {{ $t['ikon'] }}" aria-hidden="true"></i> <span>{{ $t['label'] }}</span>
            </a>
        @endforeach
    </nav>
@endif

{{-- Satu bahagian bagi setiap peranan, ikut keutamaan (DashboardController::index) --}}
@forelse($bahagian as $nama => $data)
    @include("dashboard.partials.{$nama}", $data)
@empty
    <div class="glass-panel pp-kosong">
        <i class="bi bi-person-gear"></i>
        <div>Akaun anda belum mempunyai peranan. Sila hubungi pentadbir sistem.</div>
    </div>
@endforelse

@endsection

@push('styles')
@include('dashboard.partials.gaya')
@endpush
