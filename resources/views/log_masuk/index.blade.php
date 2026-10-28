@extends('layouts.app')

@section('title', 'Sejarah Log Masuk')

@section('content')

<div class="page-intro">
    <div>
        <h2>Sejarah Log Masuk</h2>
        <p>Setiap cubaan log masuk, berjaya atau gagal, dengan alamat IP dan pelayar. No. KP yang dicuba tidak disimpan.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="glass-panel kad-ringkas"><span>Berjaya hari ini</span><strong>{{ $ringkasan['berjaya'] }}</strong></div></div>
    <div class="col-md-4"><div class="glass-panel kad-ringkas"><span>Gagal hari ini</span><strong class="{{ $ringkasan['gagal'] ? 'teks-gagal' : '' }}">{{ $ringkasan['gagal'] }}</strong></div></div>
    <div class="col-md-4"><div class="glass-panel kad-ringkas"><span>Gagal 7 hari</span><strong>{{ $ringkasan['gagal7'] }}</strong></div></div>
</div>

<div class="glass-panel filter-panel mb-4">
    <form action="{{ route('log-masuk.index') }}" method="GET">
        <div class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-6">
                <label class="dms-label" for="q">Nama Pengguna</label>
                <input type="text" name="q" id="q" value="{{ request('q') }}" class="dms-input" maxlength="100" placeholder="Cari nama">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="dms-label" for="status">Status</label>
                <select name="status" id="status" class="dms-input">
                    <option value="">Semua</option>
                    <option value="berjaya" @selected(request('status') === 'berjaya')>Berjaya</option>
                    <option value="gagal" @selected(request('status') === 'gagal')>Gagal</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="dms-label" for="dari">Dari</label>
                <input type="date" name="dari" id="dari" value="{{ request('dari') }}" class="dms-input @error('dari') is-invalid @enderror">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="dms-label" for="hingga">Hingga</label>
                <input type="date" name="hingga" id="hingga" value="{{ request('hingga') }}" class="dms-input @error('hingga') is-invalid @enderror">
                @error('hingga') <div class="dms-error">{{ $message }}</div> @enderror
            </div>
            <div class="col-lg-3 d-flex gap-2">
                <button type="submit" class="btn text-white save-btn flex-grow-1 justify-content-center"><i class="bi bi-funnel me-2"></i> Tapis</button>
                @if(request()->hasAny(['q', 'status', 'dari', 'hingga']))
                    <a href="{{ route('log-masuk.index') }}" class="outline-button text-decoration-none" title="Set semula"><i class="bi bi-x-circle"></i></a>
                @endif
            </div>
        </div>
    </form>
</div>

<div class="glass-panel overflow-hidden">
    <div class="table-responsive">
        <table class="table dms-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Masa</th>
                    <th>Pengguna</th>
                    <th>Status</th>
                    <th>Alamat IP</th>
                    <th class="pe-4">Pelayar</th>
                </tr>
            </thead>
            <tbody>
                @forelse($senarai as $l)
                    <tr>
                        <td class="ps-4 text-nowrap">{{ $l->created_at?->translatedFormat('d M Y, h:i:s A') }}</td>
                        <td class="boleh-balut">{{ $l->pengguna->nama_staff ?? 'Tidak berdaftar' }}</td>
                        <td>
                            <span class="badge st-badge {{ $l->berjaya ? 'st-berjaya' : 'st-gagal' }}">{{ $l->berjaya ? 'Berjaya' : 'Gagal' }}</span>
                            @if($l->sebab)<div class="dms-hint">{{ $l->sebab }}</div>@endif
                        </td>
                        <td class="text-nowrap">{{ $l->alamat_ip }}</td>
                        <td class="pe-4 boleh-balut dms-hint" title="{{ $l->peranti_pengguna }}">{{ \Illuminate\Support\Str::limit($l->peranti_pengguna, 70) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-5 dms-hint"><i class="bi bi-door-open d-block mb-2" style="font-size: 26px;" aria-hidden="true"></i>Tiada rekod log masuk.</td></tr>
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
    .filter-panel { padding: 20px; }
    .dms-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--dms-text); margin-bottom: 6px; }
    .dms-input { width: 100%; padding: 10px 12px; border: 1px solid var(--dms-line); border-radius: 10px; background: var(--dms-surface-strong); font-size: 13px; color: var(--dms-text); }
    .dms-input.is-invalid { border-color: var(--dms-danger); }
    .dms-error { font-size: 11.5px; color: var(--dms-danger); font-weight: 600; margin-top: 6px; }
    .dms-hint { font-size: 12px; color: var(--dms-muted); }
    .save-btn { background: linear-gradient(90deg, var(--dms-brand), var(--dms-primary)); border: none; border-radius: 10px; font-weight: 700; }
    .save-btn:hover { color: #fff; }
    .kad-ringkas { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; font-size: 12.5px; color: var(--dms-muted); font-weight: 700; }
    .kad-ringkas strong { color: var(--dms-brand); font-size: 22px; font-weight: 800; }
    .kad-ringkas .teks-gagal { color: var(--dms-danger); }
    .dms-table { font-size: 13px; }
    .dms-table thead th { color: var(--dms-muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
    .st-badge { font-weight: 700; padding: 5px 9px; border-radius: 6px; font-size: 10.5px; }
    .st-berjaya { background: rgba(37, 169, 121, .12); color: var(--dms-mint); }
    .st-gagal { background: rgba(220, 53, 69, .1); color: var(--dms-danger); }
</style>
@endpush
