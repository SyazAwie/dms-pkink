@extends('layouts.app')

@section('title', 'Tong Sampah')

@section('content')

<div class="page-intro">
    <div>
        <h2>Tong Sampah</h2>
        <p>Rekod yang telah dipadam. Data dan fail imbasan dikekalkan untuk arkib; pulihkan jika dipadam secara tidak sengaja.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif

<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($senaraiJenis as $kod => $label)
        <a href="{{ route('tong-sampah.index', ['jenis' => $kod]) }}" class="tab-sampah {{ $jenis === $kod ? 'aktif' : '' }}">
            {{ $label }} <span class="tab-bil">{{ $bilangan[$kod] }}</span>
        </a>
    @endforeach
</div>

<div class="glass-panel overflow-hidden">
    <div class="table-responsive">
        <table class="table dms-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">{{ $senaraiJenis[$jenis] }}</th>
                    <th>Butiran</th>
                    <th>Dipadam Pada</th>
                    <th>Dipadam Oleh</th>
                    <th class="text-end pe-4">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($senarai as $rekod)
                    <tr>
                        <td class="ps-4 boleh-balut">
                            @switch($jenis)
                                @case('dokumen') <span class="badge kod-badge">{{ $rekod->no_rujukan }}</span> @break
                                @case('kategori') <span class="badge kod-badge">{{ $rekod->kod_dokumen }}</span> {{ $rekod->nama_dokumen }} @break
                                @case('bahagian') <span class="badge kod-badge">{{ $rekod->kod_bahagian }}</span> {{ $rekod->nama_bahagian }} @break
                                @case('kakitangan') <strong>{{ $rekod->nama_staff }}</strong><div class="dms-hint">{{ $rekod->ic_pekerja }} · {{ $rekod->email }}</div> @break
                            @endswitch
                        </td>
                        <td class="boleh-balut dms-hint">
                            @switch($jenis)
                                @case('dokumen') {{ $rekod->jenisDokumen->nama_dokumen ?? '-' }} · {{ $rekod->pemuatNaik->nama_staff ?? '-' }} @break
                                @case('kategori') {{ $rekod->kategori ?: '-' }} @break
                                @case('bahagian') - @break
                                @case('kakitangan') {{ $rekod->bahagian->nama_bahagian ?? '-' }} · {{ $rekod->roles->pluck('nama_roles')->join(', ') ?: 'Tiada peranan' }} @break
                            @endswitch
                        </td>
                        <td class="text-nowrap">{{ $rekod->deleted_at?->translatedFormat('d M Y, h:i A') }}</td>
                        <td>{{ $pemadam[$rekod->getKey()]?->pengguna?->nama_staff ?? '-' }}</td>
                        <td class="text-end pe-4">
                            <form method="POST" action="{{ route('tong-sampah.pulih', [$jenis, $rekod->getKey()]) }}" class="d-inline">
                                @csrf
                                <button type="submit" class="outline-button kekal-label"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> <span>Pulihkan</span></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 dms-hint"><i class="bi bi-trash3 d-block mb-2" style="font-size: 26px;" aria-hidden="true"></i>Tiada {{ strtolower($senaraiJenis[$jenis]) }} dalam tong sampah.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($senarai->hasPages())
        <div class="px-4 pb-4 pt-2">{{ $senarai->links() }}</div>
    @endif
</div>

@endsection

@push('styles')
<style>
    .tab-sampah { display: inline-flex; align-items: center; gap: 8px; padding: 7px 14px; border: 1px solid var(--dms-line); border-radius: 999px; color: var(--dms-text); background: var(--dms-surface-strong); font-size: 12.5px; font-weight: 700; }
    .tab-sampah.aktif { border-color: var(--dms-primary); color: #fff; background: var(--dms-primary); }
    .tab-bil { min-width: 22px; padding: 1px 7px; border-radius: 10px; background: rgba(104, 121, 141, .15); font-size: 11px; text-align: center; }
    .tab-sampah.aktif .tab-bil { background: rgba(255, 255, 255, .25); }
    .dms-table { font-size: 13px; }
    .dms-table thead th { color: var(--dms-muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
    .kod-badge { background: rgba(23, 105, 210, .08); color: var(--dms-primary); font-weight: 700; padding: 6px 10px; border-radius: 6px; font-size: 11.5px; }
    .dms-hint { font-size: 12px; color: var(--dms-muted); }
</style>
@endpush
