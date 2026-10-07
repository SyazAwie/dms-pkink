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
                        <td>{{ $borang->dokumen->jenisDokumen->nama_dokumen ?? '-' }}</td>
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
                        <td colspan="6" class="text-center py-4 dms-hint">Tiada permohonan menunggu tindakan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 pb-4 pt-2">
        {{ $senarai->links() }}
    </div>
</div>

@endsection

@push('styles')
<style>
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