@extends('layouts.app')

@section('title', 'Pengesahan Pendaftaran')

@section('content')
@php
    $superadmin = Auth::user()->hasRole('SUPERADMIN');
    $tab = ['Menunggu' => 'Menunggu', 'Diluluskan' => 'Diluluskan', 'Ditolak' => 'Ditolak', 'Semua' => 'Semua'];
    $kelasStatus = ['Menunggu' => 'kel-proses', 'Diluluskan' => 'kel-lulus', 'Ditolak' => 'kel-tolak'];
    $idStaf = $senaraiRole->firstWhere('kod_roles', 'STAFF')?->roles_id;
@endphp

<div class="page-intro">
    <div>
        <h2>Pengesahan Pendaftaran</h2>
        <p>Semak permohonan akaun daripada staf baharu. Pemohon dimaklumkan melalui emel bagi setiap keputusan.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        @foreach($errors->all() as $ralat)<div>{{ $ralat }}</div>@endforeach
    </div>
@endif

<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($tab as $nilai => $label)
        <a href="{{ route('pendaftaran.index', ['status' => $nilai]) }}"
           class="tab-status {{ $status === $nilai ? 'aktif' : '' }}">
            {{ $label }}
            @if($nilai === 'Menunggu' && $bilMenunggu > 0)
                <span class="tab-bil">{{ $bilMenunggu }}</span>
            @endif
        </a>
    @endforeach
</div>

<div class="glass-panel">
    <div class="table-responsive">
        <table class="table dms-table align-middle mb-0">
            <thead>
                <tr>
                    <th>Tarikh Mohon</th>
                    <th>Pemohon</th>
                    <th>No. K/P</th>
                    <th>Bahagian</th>
                    <th>Status</th>
                    <th class="text-end">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($senarai as $p)
                    <tr>
                        <td>{{ $p->created_at?->format('d/m/Y h:i A') }}</td>
                        <td>
                            <div class="fw-bold">{{ $p->nama_staff }}</div>
                            <div class="dms-hint">{{ $p->email }}</div>
                        </td>
                        <td>{{ $p->ic_pekerja }}</td>
                        <td>{{ $p->bahagian->nama_bahagian ?? '-' }}</td>
                        <td>
                            <span class="badge kel-badge {{ $kelasStatus[$p->status] ?? 'kel-proses' }}">{{ $p->status }}</span>
                            @if($p->status !== 'Menunggu')
                                <div class="dms-hint mt-1">oleh {{ $p->penyemak->nama_staff ?? '-' }}, {{ $p->disemak_pada?->format('d/m/Y') }}</div>
                            @endif
                            @if($p->status === 'Ditolak' && $p->sebab_tolak)
                                <div class="dms-hint mt-1" title="{{ $p->sebab_tolak }}">Sebab: {{ \Illuminate\Support\Str::limit($p->sebab_tolak, 60) }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if($p->status === 'Menunggu')
                                <div class="d-inline-flex gap-2">
                                    <button type="button" class="btn-aksi lulus" data-bs-toggle="modal" data-bs-target="#modalLulus"
                                            data-url="{{ route('pendaftaran.lulus', $p->pendaftaran_staf_id) }}" data-nama="{{ $p->nama_staff }}">
                                        <i class="bi bi-check2-circle"></i> Lulus
                                    </button>
                                    <button type="button" class="btn-aksi tolak" data-bs-toggle="modal" data-bs-target="#modalTolak"
                                            data-url="{{ route('pendaftaran.tolak', $p->pendaftaran_staf_id) }}" data-nama="{{ $p->nama_staff }}">
                                        <i class="bi bi-x-circle"></i> Tolak
                                    </button>
                                </div>
                            @else
                                <span class="dms-hint">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center py-4 dms-hint">Tiada permohonan {{ $status === 'Semua' ? '' : 'berstatus ' . $status }}.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-4 pb-4 pt-2">
        {{ $senarai->links() }}
    </div>
</div>

{{-- Modal Lulus --}}
<div class="modal fade" id="modalLulus" tabindex="-1" aria-labelledby="modalLulusTajuk" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content" id="borangLulus">
            @csrf
            <div class="modal-header">
                <h3 class="modal-title fs-6 fw-bold" id="modalLulusTajuk">Luluskan Pendaftaran</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Luluskan pendaftaran <strong data-isi="nama"></strong>? Akaun akan dicipta dan kata laluan sementara (sah 72 jam) dihantar ke emel pemohon.</p>
                @if($superadmin)
                    <div class="dms-label mb-2">Peranan <span class="text-danger">*</span></div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($senaraiRole as $role)
                            <label class="role-pilih">
                                <input type="checkbox" name="roles[]" value="{{ $role->roles_id }}" {{ (int) $role->roles_id === (int) $idStaf ? 'checked' : '' }}>
                                <span>{{ $role->nama_roles }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <div class="dms-hint">Peranan: <strong>STAFF</strong>. Hanya Super Admin boleh menetapkan peranan lain melalui Pengurusan Kakitangan.</div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success fw-bold" data-hantar>Luluskan &amp; Hantar Emel</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Tolak --}}
<div class="modal fade" id="modalTolak" tabindex="-1" aria-labelledby="modalTolakTajuk" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content" id="borangTolak">
            @csrf
            <div class="modal-header">
                <h3 class="modal-title fs-6 fw-bold" id="modalTolakTajuk">Tolak Pendaftaran</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Tolak pendaftaran <strong data-isi="nama"></strong>? Sebab penolakan akan dinyatakan dalam emel kepada pemohon.</p>
                <label class="dms-label" for="sebab_tolak">Sebab Penolakan <span class="text-danger">*</span></label>
                <textarea name="sebab_tolak" id="sebab_tolak" class="dms-input" rows="3" maxlength="500" required>{{ old('sebab_tolak') }}</textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-danger fw-bold" data-hantar>Tolak &amp; Hantar Emel</button>
            </div>
        </form>
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
    .dms-hint { font-size: 11.5px; color: var(--dms-muted); }
    .dms-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--dms-text); }
    .dms-input {
        width: 100%; padding: 10px 12px; border: 1px solid var(--dms-line); border-radius: 10px;
        background: var(--dms-surface-strong); font-size: 13px; color: var(--dms-text); resize: vertical; font-family: inherit;
    }

    .kel-badge { font-weight: 700; padding: 6px 10px; border-radius: 6px; font-size: 11px; }
    .kel-proses { background: rgba(245,158,11,.14); color: #b45309; }
    .kel-lulus { background: rgba(37,169,121,.12); color: var(--dms-mint); }
    .kel-tolak { background: rgba(220,53,69,.1); color: var(--dms-danger); }

    .tab-status {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 14px; border: 1px solid var(--dms-line); border-radius: 999px;
        background: var(--dms-surface-strong); color: var(--dms-text);
        font-size: 12.5px; font-weight: 700; text-decoration: none;
    }
    .tab-status:hover { border-color: var(--dms-primary); color: var(--dms-primary); }
    .tab-status.aktif { background: var(--dms-primary); border-color: var(--dms-primary); color: #fff; }
    .tab-bil { min-width: 20px; padding: 1px 6px; border-radius: 10px; background: var(--dms-danger); color: #fff; font-size: 10.5px; text-align: center; }

    .btn-aksi {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 6px 12px; border-radius: 8px; border: 1px solid; background: transparent;
        font-size: 12px; font-weight: 700; cursor: pointer;
    }
    .btn-aksi.lulus { color: var(--dms-mint); border-color: var(--dms-mint); }
    .btn-aksi.lulus:hover { background: rgba(37,169,121,.1); }
    .btn-aksi.tolak { color: var(--dms-danger); border-color: var(--dms-danger); }
    .btn-aksi.tolak:hover { background: rgba(220,53,69,.08); }

    .role-pilih {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 11px; border: 1px solid var(--dms-line); border-radius: 8px;
        font-size: 12px; cursor: pointer;
    }
    .role-pilih:has(input:checked) { border-color: var(--dms-primary); background: rgba(23,105,210,.06); }
</style>
@endpush

@push('scripts')
<script>
    // Isi URL tindakan & nama pemohon ke dalam modal Lulus / Tolak
    ['modalLulus', 'modalTolak'].forEach((idModal) => {
        const modal = document.getElementById(idModal);
        modal.addEventListener('show.bs.modal', (event) => {
            const butang = event.relatedTarget;
            if (!butang) return;
            modal.querySelector('form').action = butang.dataset.url;
            modal.querySelector('[data-isi="nama"]').textContent = butang.dataset.nama;
        });
        // Elak klik berganda semasa emel sedang dihantar
        modal.querySelector('form').addEventListener('submit', (event) => {
            const hantar = event.target.querySelector('[data-hantar]');
            hantar.disabled = true;
            hantar.textContent = 'Menghantar emel...';
        });
    });
</script>
@endpush
