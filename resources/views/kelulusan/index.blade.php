@extends('layouts.app')

@section('title', 'Kelulusan')

@section('content')

<div class="page-intro">
    <div>
        <h2>Kelulusan</h2>
        <p>
            @if(Auth::user()->hasAnyRole('SUPERADMIN', 'ADMIN'))
                Semua permohonan kelulusan dokumen dalam sistem.
            @elseif(Auth::user()->hasRole('PENYOKONG'))
                Permohonan menunggu sokongan anda.
            @else
                Permohonan menunggu kelulusan anda.
            @endif
        </p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Penapis (P10): tab status untuk pentadbir + carian No. Rujukan / pemohon --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="d-flex flex-wrap gap-2">
        @if(Auth::user()->hasAnyRole('SUPERADMIN', 'ADMIN'))
            <a href="{{ route('kelulusan.index', array_filter(['q' => request('q')])) }}" class="tab-kel {{ request('status') ? '' : 'aktif' }}">Semua <span class="tab-bil">{{ $bilStatus->sum() }}</span></a>
            @foreach(\App\Http\Controllers\BorangController::STATUS as $s)
                <a href="{{ route('kelulusan.index', array_filter(['status' => $s, 'q' => request('q')])) }}" class="tab-kel {{ request('status') === $s ? 'aktif' : '' }}">{{ $s }} <span class="tab-bil">{{ $bilStatus[$s] ?? 0 }}</span></a>
            @endforeach
        @endif
    </div>
    <form method="GET" action="{{ route('kelulusan.index') }}" class="kel-cari" role="search">
        @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" name="q" value="{{ request('q') }}" class="dms-input" placeholder="Cari No. Rujukan atau pemohon..." aria-label="Cari permohonan">
    </form>
</div>

<div class="glass-panel">
    <div class="table-responsive">
        <table class="table dms-table align-middle mb-0">
            <thead>
                <tr>
                    <th>No. Rujukan</th>
                    <th>Jenis Dokumen</th>
                    <th>Pemohon</th>
                    <th>Tarikh Mohon</th>
                    <th>Status</th>
                    <th class="text-end">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($senarai as $borang)
                    @php
                        $kelas = match ($borang->status_permohonan) {
                            'Diluluskan' => 'kel-lulus',
                            'Disokong' => 'kel-sokong',
                            'Ditolak' => 'kel-tolak',
                            default => 'kel-proses',
                        };
                    @endphp
                    <tr>
                        <td><span class="badge kod-badge">{{ $borang->dokumen->no_rujukan ?? '-' }}</span></td>
                        <td class="boleh-balut">{{ $borang->dokumen->jenisDokumen->nama_dokumen ?? '-' }}</td>
                        <td>{{ $borang->pemohon->nama_staff ?? '-' }}</td>
                        <td>{{ $borang->tarikh_permohonan?->format('d/m/Y') }}</td>
                        <td><span class="badge kel-badge {{ $kelas }}">{{ $borang->status_permohonan }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('dokumen.show', $borang->dokumen_id) }}" class="aksi-btn" title="Lihat & Bertindak">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 dms-hint">{{ request()->hasAny(['status', 'q']) ? 'Tiada permohonan sepadan dengan penapis.' : 'Tiada permohonan menunggu tindakan.' }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($senarai->hasPages())
    <div class="px-4 pb-4 pt-2">
        {{ $senarai->links() }}
    </div>
    @endif
</div>

@endsection

@push('styles')
<style>
    .tab-kel { display: inline-flex; align-items: center; gap: 8px; padding: 7px 14px; border: 1px solid var(--dms-line); border-radius: 999px; color: var(--dms-text); background: var(--dms-surface-strong); font-size: 12.5px; font-weight: 700; }
    .tab-kel.aktif { border-color: var(--dms-primary); color: #fff; background: var(--dms-primary); }
    .tab-bil { min-width: 22px; padding: 1px 7px; border-radius: 10px; background: rgba(104, 121, 141, .15); font-size: 11px; text-align: center; }
    .tab-kel.aktif .tab-bil { background: rgba(255, 255, 255, .25); }
    .kel-cari { position: relative; min-width: min(280px, 100%); }
    .kel-cari i { position: absolute; top: 50%; left: 12px; color: var(--dms-muted); transform: translateY(-50%); }
    .kel-cari .dms-input { width: 100%; padding: 9px 12px 9px 34px; border: 1px solid var(--dms-line); border-radius: 10px; font-size: 13px; background: #fff; }
    .dms-table { font-size: 13px; }
    .dms-table thead th {
        font-size: 11px; text-transform: uppercase; letter-spacing: .4px;
        color: var(--dms-muted); border-bottom: 1px solid var(--dms-line); padding: 14px 16px;
    }
    .dms-table tbody td { padding: 14px 16px; border-bottom: 1px solid var(--dms-line); }
    .dms-hint { font-size: 12px; color: var(--dms-muted); }
    .kod-badge { background: rgba(23,105,210,.1); color: var(--dms-primary); font-weight: 700; padding: 6px 10px; border-radius: 6px; font-size: 11.5px; }

    .kel-badge { font-weight: 700; padding: 6px 10px; border-radius: 6px; font-size: 11px; }
    .kel-proses { background: rgba(245,158,11,.14); color: #b45309; }
    .kel-sokong { background: rgba(23,105,210,.1); color: var(--dms-primary); }
    .kel-lulus { background: rgba(37,169,121,.12); color: var(--dms-mint); }
    .kel-tolak { background: rgba(220,53,69,.1); color: var(--dms-danger); }

    .aksi-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; border: 1px solid var(--dms-line); border-radius: 8px;
        background: var(--dms-surface-strong); color: var(--dms-primary); text-decoration: none;
        transition: background .2s ease, border-color .2s ease;
    }
    .aksi-btn:hover { background: rgba(23,105,210,.08); border-color: var(--dms-primary); color: var(--dms-primary); }
</style>
@endpush 