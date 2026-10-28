@extends('layouts.app')

@section('title', 'Pusat Keselamatan')

@section('content')

<div class="page-intro">
    <div>
        <h2>Pusat Keselamatan</h2>
        <p>Ringkasan ancaman 24 jam, semakan pengerasan sistem dan tindakan sensitif terkini. Amaran automatik dihantar ke loceng & telefon pentadbir bila berlaku cubaan teka kata laluan, log masuk pentadbir dari IP baharu, fail berbahaya atau sandaran tertunggak.</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
@endif

<div class="ks-kpi mb-4">
    <div class="glass-panel ks-kad {{ $ringkasan['gagal24j'] >= \App\Support\Keselamatan::AMBANG_GAGAL_IP ? 'ks-bahaya' : '' }}">
        <i class="bi bi-door-closed" aria-hidden="true"></i><strong>{{ $ringkasan['gagal24j'] }}</strong><span>Log masuk gagal (24 j)</span>
    </div>
    <div class="glass-panel ks-kad"><i class="bi bi-door-open" aria-hidden="true"></i><strong>{{ $ringkasan['berjaya24j'] }}</strong><span>Log masuk berjaya (24 j)</span></div>
    <div class="glass-panel ks-kad {{ $ringkasan['padam24j'] >= 5 ? 'ks-bahaya' : '' }}"><i class="bi bi-trash3" aria-hidden="true"></i><strong>{{ $ringkasan['padam24j'] }}</strong><span>Rekod dipadam (24 j)</span></div>
    <div class="glass-panel ks-kad {{ $ringkasan['failBerbahaya7h'] ? 'ks-bahaya' : '' }}"><i class="bi bi-bug" aria-hidden="true"></i><strong>{{ $ringkasan['failBerbahaya7h'] }}</strong><span>Fail berbahaya ditolak (7 hari)</span></div>
</div>

<div class="glass-panel mb-4 overflow-hidden">
    <div class="section-header px-4 pt-4 pb-3 d-flex justify-content-between align-items-center gap-2">
        <h3>Semakan Pengerasan</h3>
        <span class="badge ks-lencana-bil {{ $bilMasalah ? 'ks-ada' : 'ks-tiada' }}">{{ $bilMasalah ? $bilMasalah.' perlu tindakan' : 'Semua baik' }}</span>
    </div>
    @foreach($senaraiSemak as $s)
        <div class="ss-baris">
            <span class="ss-lencana ss-{{ $s['status'] }}" title="{{ ['ok' => 'Baik', 'amaran' => 'Amaran', 'ralat' => 'Bahaya'][$s['status']] }}">
                <i class="bi {{ ['ok' => 'bi-check-lg', 'amaran' => 'bi-exclamation-lg', 'ralat' => 'bi-x-lg'][$s['status']] }}" aria-hidden="true"></i>
            </span>
            <div class="ss-teks">
                <strong>{{ $s['nama'] }}</strong>
                <span>{{ $s['nilai'] }}</span>
                @if($s['nota'] && $s['status'] !== 'ok')<small>{{ $s['nota'] }}</small>@endif
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="glass-panel overflow-hidden h-100">
            <div class="section-header px-4 pt-4 pb-3"><h3>IP Dengan Log Masuk Gagal (24 j)</h3></div>
            <div class="table-responsive">
                <table class="table dms-table align-middle mb-0">
                    <thead><tr><th class="ps-4">Alamat IP</th><th class="text-end">Gagal</th><th class="pe-4">Terakhir</th></tr></thead>
                    <tbody>
                        @forelse($ringkasan['ipGagal'] as $ip)
                            <tr>
                                <td class="ps-4"><code>{{ $ip->alamat_ip ?? '-' }}</code></td>
                                <td class="text-end"><strong class="{{ $ip->bil >= \App\Support\Keselamatan::AMBANG_GAGAL_IP ? 'text-danger' : '' }}">{{ $ip->bil }}</strong></td>
                                <td class="pe-4 text-nowrap">{{ \Illuminate\Support\Carbon::parse($ip->terakhir)->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center py-4 dms-hint">Tiada log masuk gagal dalam 24 jam.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="glass-panel overflow-hidden h-100">
            <div class="section-header px-4 pt-4 pb-3"><h3>Akaun Paling Disasarkan (24 j)</h3></div>
            <ul class="ks-senarai px-4 py-2 mb-0">
                @forelse($ringkasan['akaunDisasar'] as $a)
                    <li><span>{{ $a->pengguna->nama_staff ?? 'Akaun dipadam' }}</span><strong>{{ $a->bil }} gagal</strong></li>
                @empty
                    <li class="dms-hint">Tiada akaun disasarkan.</li>
                @endforelse
            </ul>
            <div class="px-4 pb-3"><a href="{{ route('log-masuk.index', ['status' => 'gagal']) }}" class="dms-hint">Sejarah Log Masuk penuh →</a></div>
        </div>
    </div>
</div>

<div class="glass-panel overflow-hidden mb-4">
    <div class="section-header px-4 pt-4 pb-3"><h3>Tindakan Sensitif Terkini</h3></div>
    <div class="table-responsive">
        <table class="table dms-table align-middle mb-0">
            <thead><tr><th class="ps-4">Masa</th><th>Pengguna</th><th>Tindakan</th><th class="pe-4">Rekod</th></tr></thead>
            <tbody>
                @forelse($ringkasan['tindakanSensitif'] as $l)
                    <tr>
                        <td class="ps-4 text-nowrap">{{ $l->created_at?->translatedFormat('d M Y, h:i A') }}</td>
                        <td>{{ $l->pengguna->nama_staff ?? 'Sistem / tetamu' }}</td>
                        <td><span class="badge ks-tindakan">{{ $l->tindakan }}</span></td>
                        <td class="pe-4 dms-hint">{{ $l->nama_jadual }}{{ $l->rekod_id ? ' #'.$l->rekod_id : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center py-4 dms-hint">Tiada tindakan sensitif.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('styles')
<style>
    .section-header { border-bottom: 1px solid var(--dms-line); }
    .section-header h3 { margin: 0; color: var(--dms-brand); font-size: 16px; font-weight: 800; }
    .dms-hint { font-size: 12px; color: var(--dms-muted); }
    .ks-kpi { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    @media (min-width: 992px) { .ks-kpi { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .ks-kad { display: grid; grid-template-columns: auto 1fr; grid-template-rows: auto auto; column-gap: 12px; align-items: center; padding: 14px 18px; }
    .ks-kad i { grid-row: span 2; font-size: 24px; color: var(--dms-primary); }
    .ks-kad strong { font-size: 22px; color: var(--dms-brand); line-height: 1; }
    .ks-kad span { color: var(--dms-muted); font-size: 12px; font-weight: 700; }
    .ks-kad.ks-bahaya { box-shadow: inset 3px 0 0 var(--dms-danger); }
    .ks-kad.ks-bahaya i, .ks-kad.ks-bahaya strong { color: var(--dms-danger); }
    .ks-lencana-bil { font-weight: 700; padding: 6px 10px; border-radius: 6px; font-size: 11px; }
    .ks-ada { background: rgba(220, 53, 69, .1); color: var(--dms-danger); }
    .ks-tiada { background: rgba(37, 169, 121, .12); color: var(--dms-mint); }
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
    .dms-table { font-size: 13px; }
    .dms-table thead th { color: var(--dms-muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
    .ks-senarai { list-style: none; }
    .ks-senarai li { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px dashed var(--dms-line); font-size: 13px; }
    .ks-senarai li:last-child { border-bottom: 0; }
    .ks-tindakan { background: rgba(23, 105, 210, .08); color: var(--dms-primary); font-weight: 700; padding: 5px 9px; border-radius: 6px; font-size: 11px; }
    @media (max-width: 575.98px) { .ss-baris { padding: 12px 16px; } .ks-kad { padding: 12px; } }
</style>
@endpush
