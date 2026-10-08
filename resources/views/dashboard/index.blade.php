@extends('layouts.app')

@section('title', 'Papan Pemuka')

@section('content')

@php
    $u = Auth::user();
    $labelStatus = ['Dalam Proses' => 'kel-proses', 'Disokong' => 'kel-sokong', 'Diluluskan' => 'kel-lulus', 'Ditolak' => 'kel-tolak'];

    // Satu sahaja antara dua (STAFF: permohonan sendiri / ADMIN: pecahan keseluruhan)
    $statusData = $permohonanSaya ?? $permohonanBreakdown ?? null;
    $statusTajuk = isset($permohonanSaya) ? 'Status Permohonan Saya' : 'Status Semua Permohonan Kelulusan';

    $adaCartaKategori = isset($pecahanKategori) && count($pecahanKategori['label']);
    $adaCartaTindakan = isset($pecahanTindakan);
    $adaCartaTrend = isset($trendBulanan);
    $adaCartaBahagian = isset($pecahanBahagian) && count($pecahanBahagian['label']);
    $bilCarta = (int) $adaCartaKategori + (int) $adaCartaTindakan + (int) $adaCartaTrend + (int) $adaCartaBahagian;
@endphp

<!-- Bahagian Pengenalan Halaman -->
<div class="page-intro">
    <div>
        <h2>Papan Pemuka Utama</h2>
        <p>Ringkasan statistik dokumen untuk Sistem Arkib Digital PKINK.</p>
    </div>
    <div class="date-chip">
        <i class="bi bi-calendar3"></i>
        {{ now()->translatedFormat('d F Y') }}
    </div>
</div>

{{-- ============================================================ --}}
{{-- Satu grid kad sahaja — semua kad relevan ikut peranan diisi di sini --}}
{{-- ============================================================ --}}
@if($u->hasAnyRole('STAFF', 'PENGURUS', 'KERANI', 'ADMIN', 'SUPERADMIN', 'PENYOKONG', 'PELULUS', 'AUDIT'))
<div class="summary-grid mb-4">

    @if($u->hasAnyRole('STAFF', 'PENGURUS', 'KERANI', 'ADMIN', 'SUPERADMIN'))
    <div class="summary-card glass-panel">
        <div class="summary-head">
            <div>
                <div class="summary-label">
                    @if($u->hasRole('STAFF') && !$u->hasAnyRole('PENGURUS', 'KERANI', 'ADMIN', 'SUPERADMIN'))
                        Dokumen Saya
                    @elseif($u->hasRole('PENGURUS') && !$u->hasAnyRole('KERANI', 'ADMIN', 'SUPERADMIN'))
                        Dokumen Bahagian
                    @else
                        Jumlah Dokumen
                    @endif
                </div>
                <div class="summary-value">{{ $jumlahDokumen }}</div>
            </div>
            <div class="summary-icon"><i class="bi bi-file-earmark-text"></i></div>
        </div>
        <div class="summary-detail"><a href="{{ route('dokumen.index') }}">Lihat senarai <i class="bi bi-arrow-right"></i></a></div>
    </div>

    <div class="summary-card glass-panel">
        <div class="summary-head">
            <div>
                <div class="summary-label">Fail Menunggu OCR</div>
                <div class="summary-value">{{ $ocrPending }}</div>
            </div>
            <div class="summary-icon summary-icon-amber"><i class="bi bi-exclamation-triangle"></i></div>
        </div>
        <div class="summary-detail text-muted">daripada {{ $jumlahFailDiimbas }} jumlah fail diimbas</div>
    </div>
    @endif

    @if($u->hasAnyRole('ADMIN', 'SUPERADMIN'))
        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Kategori Dokumen</div>
                    <div class="summary-value">{{ $jumlahKategori }}</div>
                </div>
                <div class="summary-icon summary-icon-mint"><i class="bi bi-folder2-open"></i></div>
            </div>
            <div class="summary-detail"><a href="{{ route('jenis-dokumen.index') }}">Urus kategori <i class="bi bi-arrow-right"></i></a></div>
        </div>

        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Jumlah Kakitangan</div>
                    <div class="summary-value">{{ $jumlahKakitangan }}</div>
                </div>
                <div class="summary-icon summary-icon-gold"><i class="bi bi-people"></i></div>
            </div>
            <div class="summary-detail"><a href="{{ route('users.index') }}">Urus kakitangan <i class="bi bi-arrow-right"></i></a></div>
        </div>

        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Log Audit Hari Ini</div>
                    <div class="summary-value">{{ $logHariIni }}</div>
                </div>
                <div class="summary-icon"><i class="bi bi-shield-check"></i></div>
            </div>
            <div class="summary-detail"><a href="{{ route('log-audit.index') }}">Lihat log <i class="bi bi-arrow-right"></i></a></div>
        </div>

        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Purata Masa Kelulusan</div>
                    <div class="summary-value">{{ $purataMasaKelulusan !== null ? $purataMasaKelulusan . ' j' : '-' }}</div>
                </div>
                <div class="summary-icon summary-icon-mint"><i class="bi bi-stopwatch"></i></div>
            </div>
            <div class="summary-detail text-muted">{{ $purataMasaKelulusan !== null ? 'Hantar hingga diluluskan' : 'Belum ada kelulusan' }}</div>
        </div>

        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Diarkibkan Terus (AI)</div>
                    <div class="summary-value">{{ $arkibTerusAi }}</div>
                </div>
                <div class="summary-icon summary-icon-ai"><i class="bi bi-magic"></i></div>
            </div>
            <div class="summary-detail"><a href="{{ route('imbas-arkib.create') }}">Guna Imbas &amp; Arkib <i class="bi bi-arrow-right"></i></a></div>
        </div>

        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Kelulusan Biasa</div>
                    <div class="summary-value">{{ $kelulusanBiasa }}</div>
                </div>
                <div class="summary-icon summary-icon-primary"><i class="bi bi-signpost-split"></i></div>
            </div>
            <div class="summary-detail text-muted">Melalui Sokong &amp; Lulus</div>
        </div>

        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Purata Keyakinan OCR</div>
                    <div class="summary-value">{{ $purataSkorOcr !== null ? $purataSkorOcr . '%' : '-' }}</div>
                </div>
                <div class="summary-icon summary-icon-mint"><i class="bi bi-patch-question"></i></div>
            </div>
            <div class="summary-detail text-muted">{{ $purataSkorOcr !== null ? 'Purata semua fail di-OCR' : 'Belum ada fail di-OCR' }}</div>
        </div>
    @endif

    @if($u->hasAnyRole('PENYOKONG', 'SUPERADMIN', 'ADMIN') && isset($menungguSokongan))
        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Menunggu Sokongan Anda</div>
                    <div class="summary-value">{{ $menungguSokongan }}</div>
                </div>
                <div class="summary-icon summary-icon-primary"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="summary-detail"><a href="{{ route('kelulusan.index') }}">Semak senarai <i class="bi bi-arrow-right"></i></a></div>
        </div>
        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Disokong Bulan Ini</div>
                    <div class="summary-value">{{ $disokongBulanIni }}</div>
                </div>
                <div class="summary-icon summary-icon-mint"><i class="bi bi-check2-circle"></i></div>
            </div>
            <div class="summary-detail text-muted">Oleh anda</div>
        </div>
    @endif

    @if($u->hasAnyRole('PELULUS', 'SUPERADMIN', 'ADMIN') && isset($menungguKelulusan))
        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Menunggu Kelulusan Anda</div>
                    <div class="summary-value">{{ $menungguKelulusan }}</div>
                </div>
                <div class="summary-icon summary-icon-primary"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="summary-detail"><a href="{{ route('kelulusan.index') }}">Semak senarai <i class="bi bi-arrow-right"></i></a></div>
        </div>
        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Diluluskan Bulan Ini</div>
                    <div class="summary-value">{{ $diluluskanBulanIni }}</div>
                </div>
                <div class="summary-icon summary-icon-mint"><i class="bi bi-patch-check"></i></div>
            </div>
            <div class="summary-detail text-muted">Oleh anda</div>
        </div>
    @endif

    @if($u->hasRole('AUDIT'))
        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Log Audit Hari Ini</div>
                    <div class="summary-value">{{ $logHariIni }}</div>
                </div>
                <div class="summary-icon"><i class="bi bi-shield-check"></i></div>
            </div>
            <div class="summary-detail"><a href="{{ route('log-audit.index') }}">Lihat log <i class="bi bi-arrow-right"></i></a></div>
        </div>
        <div class="summary-card glass-panel">
            <div class="summary-head">
                <div>
                    <div class="summary-label">Log Audit 7 Hari</div>
                    <div class="summary-value">{{ $logMingguIni }}</div>
                </div>
                <div class="summary-icon summary-icon-gold"><i class="bi bi-calendar-week"></i></div>
            </div>
            <div class="summary-detail text-muted">Seminggu terakhir</div>
        </div>
    @endif

</div>
@endif

{{-- ============================================================ --}}
{{-- Status permohonan (pill) — STAFF sendiri ATAU ADMIN keseluruhan --}}
{{-- ============================================================ --}}
@if($statusData !== null)
<div class="glass-panel mb-4">
    <div class="section-header px-4 pt-3 pb-3"><h3>{{ $statusTajuk }}</h3></div>
    <div class="px-4 py-3">
        <div class="chip-row">
            @foreach(['Dalam Proses', 'Disokong', 'Diluluskan', 'Ditolak'] as $s)
                <span class="badge kel-badge {{ $labelStatus[$s] }}">{{ $s }} &middot; {{ $statusData[$s] ?? 0 }}</span>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- ============================================================ --}}
{{-- Carta — padat, bersebelahan jika dua, satu lajur jika satu    --}}
{{-- ============================================================ --}}
@if($bilCarta > 0)
<div class="row g-3 mb-4">
    @if($adaCartaKategori)
    <div class="{{ $bilCarta > 1 ? 'col-lg-6' : 'col-12' }}">
        <div class="glass-panel chart-panel h-100">
            <div class="section-header px-4 pt-3 pb-3"><h3>Pecahan Dokumen Ikut Kategori</h3></div>
            <div class="chart-body"><canvas id="cartaKategori"></canvas></div>
        </div>
    </div>
    @endif

    @if($adaCartaTrend)
    <div class="{{ $bilCarta > 1 ? 'col-lg-6' : 'col-12' }}">
        <div class="glass-panel chart-panel h-100">
            <div class="section-header px-4 pt-3 pb-3"><h3>Trend Muat Naik (6 Bulan)</h3></div>
            <div class="chart-body"><canvas id="cartaTrend"></canvas></div>
        </div>
    </div>
    @endif

    @if($adaCartaBahagian)
    <div class="col-12">
        <div class="glass-panel chart-panel">
            <div class="section-header px-4 pt-3 pb-3"><h3>Dokumen Mengikut Bahagian</h3></div>
            <div class="chart-body chart-body-wide"><canvas id="cartaBahagian"></canvas></div>
        </div>
    </div>
    @endif

    @if($adaCartaTindakan)
    <div class="col-12">
        <div class="glass-panel chart-panel">
            <div class="section-header px-4 pt-3 pb-3"><h3>Pecahan Tindakan (7 Hari)</h3></div>
            <div class="chart-body chart-body-wide"><canvas id="cartaTindakan"></canvas></div>
        </div>
    </div>
    @endif
</div>
@endif

{{-- ============================================================ --}}
{{-- Jadual: menunggu tindakan (Penyokong/Pelulus)                 --}}
{{-- ============================================================ --}}
@if(isset($senaraiMenunggu) && $senaraiMenunggu->count())
<div class="glass-panel mb-4">
    <div class="section-header px-4 pt-3 pb-3"><h3>Menunggu Tindakan Anda</h3></div>
    <div class="table-responsive">
        <table class="table dms-table align-middle mb-0">
            <thead><tr><th>No. Rujukan</th><th>Jenis</th><th>Pemohon</th><th class="text-end">Tindakan</th></tr></thead>
            <tbody>
                @foreach($senaraiMenunggu as $b)
                <tr>
                    <td><span class="badge kod-badge">{{ $b->dokumen->no_rujukan ?? '-' }}</span></td>
                    <td>{{ $b->dokumen->jenisDokumen->nama_dokumen ?? '-' }}</td>
                    <td>{{ $b->pemohon->nama_staff ?? '-' }}</td>
                    <td class="text-end">
                        <a href="{{ route('dokumen.show', $b->dokumen_id) }}" class="aksi-btn" title="Lihat"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3"><a href="{{ route('kelulusan.index') }}" class="dms-hint">Lihat semua <i class="bi bi-arrow-right"></i></a></div>
</div>
@endif

{{-- ============================================================ --}}
{{-- Jadual: dokumen terkini                                       --}}
{{-- ============================================================ --}}
@if(isset($dokumenTerkini) && $dokumenTerkini->count())
<div class="glass-panel">
    <div class="section-header px-4 pt-3 pb-3"><h3>Dokumen Terkini</h3></div>
    <div class="table-responsive">
        <table class="table dms-table align-middle mb-0">
            <thead><tr><th>No. Rujukan</th><th>Jenis Dokumen</th><th>Dimuat Naik Oleh</th><th>Tarikh</th><th class="text-end">Tindakan</th></tr></thead>
            <tbody>
                @foreach($dokumenTerkini as $d)
                <tr>
                    <td><span class="badge kod-badge">{{ $d->no_rujukan }}</span></td>
                    <td>{{ $d->jenisDokumen->nama_dokumen ?? '-' }}</td>
                    <td>{{ $d->pemuatNaik->nama_staff ?? '-' }}</td>
                    <td>{{ $d->tarikh_dokumen?->format('d/m/Y') }}</td>
                    <td class="text-end">
                        <a href="{{ route('dokumen.show', $d->dokumen_id) }}" class="aksi-btn" title="Lihat"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3"><a href="{{ route('dokumen.index') }}" class="dms-hint">Lihat semua dokumen <i class="bi bi-arrow-right"></i></a></div>
</div>
@elseif(isset($dokumenTerkini))
<div class="glass-panel px-4 py-5 text-center dms-hint">Belum ada dokumen dimuat naik.</div>
@endif

@endsection

@push('styles')
<style>
    /* ---- Kad ringkasan: varian warna ikon dikongsi (asas sudah ada dalam layout) ---- */
    .summary-icon-mint { color: var(--dms-mint); background: rgba(37, 169, 121, .1); }
    .summary-icon-amber { color: var(--dms-danger); background: rgba(196, 61, 78, .1); }
    .summary-icon-gold { color: #b45309; background: rgba(234, 179, 8, .12); }
    .summary-icon-primary { color: var(--dms-primary); background: rgba(23, 105, 210, .1); }
    .summary-icon-ai { color: #7c3aed; background: rgba(124, 58, 237, .1); }

    .summary-detail a { color: var(--dms-primary); text-decoration: none; font-weight: 600; }
    .summary-detail a:hover { text-decoration: underline; }

    /* ---- Panel header, dikongsi seluruh halaman ---- */
    .section-header { border-bottom: 1px solid var(--dms-line); }
    .section-header h3 { margin: 0; color: var(--dms-brand); font-size: 15px; font-weight: 800; }

    /* ---- Status permohonan (chip) ---- */
    .chip-row { display: flex; flex-wrap: wrap; gap: 8px; }
    .kel-badge { font-weight: 700; padding: 7px 12px; border-radius: 999px; font-size: 11.5px; }
    .kel-proses { background: rgba(245, 158, 11, .14); color: #b45309; }
    .kel-sokong { background: rgba(23, 105, 210, .1); color: var(--dms-primary); }
    .kel-lulus { background: rgba(37, 169, 121, .12); color: var(--dms-mint); }
    .kel-tolak { background: rgba(220, 53, 69, .1); color: var(--dms-danger); }

    /* ---- Carta: padat & terkawal, elak melebar/tinggi sangka-sangka ---- */
    .chart-panel { padding: 0; }
    .chart-body { position: relative; height: 190px; padding: 4px 16px 16px; }
    .chart-body-wide { height: 160px; }
    @media (max-width: 576px) {
        .chart-body { height: 170px; }
    }

    /* ---- Jadual ---- */
    .dms-table { font-size: 13px; }
    .dms-table thead th {
        font-size: 11px; text-transform: uppercase; letter-spacing: .4px;
        color: var(--dms-muted); border-bottom: 1px solid var(--dms-line); padding: 12px 16px;
    }
    .dms-table tbody td { padding: 11px 16px; border-bottom: 1px solid var(--dms-line); }
    .kod-badge { background: rgba(23, 105, 210, .1); color: var(--dms-primary); font-weight: 700; padding: 5px 10px; border-radius: 6px; font-size: 11.5px; }
    .dms-hint { font-size: 12px; color: var(--dms-muted); text-decoration: none; }
    .dms-hint:hover { color: var(--dms-primary); }

    .aksi-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border: 1px solid var(--dms-line); border-radius: 8px;
        background: var(--dms-surface-strong); color: var(--dms-primary); text-decoration: none;
        transition: background .2s ease, border-color .2s ease;
    }
    .aksi-btn:hover { background: rgba(23, 105, 210, .08); border-color: var(--dms-primary); }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(() => {
    const palet = ['#1769d2', '#25a979', '#eab308', '#c43d4e', '#7c3aed', '#0891b2'];
    const fontKecil = { size: 10.5 };

    @if($adaCartaKategori)
    new Chart(document.getElementById('cartaKategori'), {
        type: 'doughnut',
        data: {
            labels: @json($pecahanKategori['label']),
            datasets: [{ data: @json($pecahanKategori['nilai']), backgroundColor: palet, borderWidth: 0 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: fontKecil, padding: 10 } } }
        }
    });
    @endif

    @if($adaCartaTrend)
    new Chart(document.getElementById('cartaTrend'), {
        type: 'line',
        data: {
            labels: @json($trendBulanan['label']),
            datasets: [{
                label: 'Dokumen',
                data: @json($trendBulanan['nilai']),
                borderColor: '#1769d2',
                backgroundColor: 'rgba(23,105,210,.1)',
                tension: 0.35,
                pointRadius: 3,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, font: fontKecil } },
                x: { ticks: { font: fontKecil } }
            }
        }
    });
    @endif

    @if($adaCartaBahagian)
    new Chart(document.getElementById('cartaBahagian'), {
        type: 'bar',
        data: {
            labels: @json($pecahanBahagian['label']),
            datasets: [{ data: @json($pecahanBahagian['nilai']), backgroundColor: palet, borderRadius: 4 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1, font: fontKecil } },
                y: { ticks: { font: fontKecil } }
            }
        }
    });
    @endif

    @if($adaCartaTindakan)
    new Chart(document.getElementById('cartaTindakan'), {
        type: 'bar',
        data: {
            labels: @json($pecahanTindakan['label']),
            datasets: [{ data: @json($pecahanTindakan['nilai']), backgroundColor: palet, borderRadius: 4 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { stepSize: 1, font: fontKecil } },
                y: { ticks: { font: fontKecil } }
            }
        }
    });
    @endif
})();
</script>
@endpush