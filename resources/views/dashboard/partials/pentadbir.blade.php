{{-- SUPERADMIN / ADMIN: gambaran seluruh sistem (DashboardController::dataPentadbir) --}}
@php
    $kelasStatus = ['Dalam Proses' => 'proses', 'Disokong' => 'sokong', 'Diluluskan' => 'lulus', 'Ditolak' => 'tolak'];
    $aktif = $statusPermohonan['Dalam Proses'] + $statusPermohonan['Disokong'];
    $jumlahPermohonan = max(1, array_sum($statusPermohonan));
    // Purata kelulusan dalam unit yang mudah dibaca: jam (< 1 hari) atau hari
    [$purataNilai, $purataUnit] = match (true) {
        $purataJamKelulusan === null => ['-', null],
        $purataJamKelulusan < 24 => [number_format($purataJamKelulusan, 1), 'jam'],
        default => [number_format($purataJamKelulusan / 24, 1), 'hari'],
    };
    $maksTrend = max(1, ...array_column($trend, 'nilai'));
@endphp
<section class="pp-bahagian" aria-labelledby="tajuk-pentadbir">

    {{-- 1. Perlu perhatian: hanya perkara yang ada --}}
    <div class="glass-panel pp-perhatian {{ $perhatian ? '' : 'pp-perhatian-ok' }}">
        @if($perhatian)
            <h2 class="pp-bahagian-tajuk m-0" id="tajuk-pentadbir"><i class="bi bi-exclamation-circle"></i> Perlu Perhatian</h2>
            <ul>
                @foreach($perhatian as $p)
                    <li>
                        <span class="pp-perhatian-bil warna-{{ $p['tahap'] }}">{{ $p['bil'] }}</span>
                        <span class="pp-perhatian-teks">{{ $p['teks'] }}</span>
                        <a href="{{ $p['url'] }}">{{ $p['butang'] }} <i class="bi bi-arrow-right"></i></a>
                    </li>
                @endforeach
            </ul>
        @else
            <h2 class="pp-bahagian-tajuk m-0" id="tajuk-pentadbir"><i class="bi bi-check2-circle"></i> Tiada perkara memerlukan perhatian</h2>
        @endif
    </div>

    {{-- 2. Empat KPI utama --}}
    <div class="pp-kpi">
        @include('dashboard.partials.kad-kpi', ['label' => 'Jumlah Dokumen', 'nilai' => $jumlahDokumen, 'ikon' => 'bi-file-earmark-text',
            'warna' => 'biru', 'nota' => "+{$dokumenBulanIni} bulan ini", 'url' => route('dokumen.index')])
        @include('dashboard.partials.kad-kpi', ['label' => 'Permohonan Aktif', 'nilai' => $aktif, 'ikon' => 'bi-hourglass-split', 'warna' => 'kuning',
            'nota' => "{$statusPermohonan['Dalam Proses']} dalam proses · {$statusPermohonan['Disokong']} disokong", 'url' => route('kelulusan.index')])
        @include('dashboard.partials.kad-kpi', ['label' => 'Purata Kelulusan', 'nilai' => $purataNilai, 'unit' => $purataUnit,
            'ikon' => 'bi-stopwatch', 'warna' => 'hijau', 'nota' => $purataUnit ? 'Dari dihantar hingga diluluskan' : 'Belum ada kelulusan'])
        @include('dashboard.partials.kad-kpi', ['label' => 'Kakitangan', 'nilai' => $jumlahKakitangan, 'ikon' => 'bi-people',
            'warna' => 'ungu', 'nota' => "{$jumlahBahagian} bahagian", 'url' => route('users.index')])
    </div>

    {{-- 3. Aliran kelulusan & trend --}}
    <div class="pp-dua pp-dua-sama mb-3">
        <div class="glass-panel pp-panel">
            <div class="pp-panel-kepala"><h3>Aliran Kelulusan</h3><a href="{{ route('kelulusan.index') }}">Kelulusan <i class="bi bi-arrow-right"></i></a></div>
            <div class="pp-aliran">
                <div class="pp-aliran-bar" role="img" aria-label="Pecahan status permohonan">
                    @foreach($statusPermohonan as $s => $n)
                        @if($n)<span class="pp-status-{{ $kelasStatus[$s] }}" style="width: {{ 100 * $n / $jumlahPermohonan }}%"></span>@endif
                    @endforeach
                </div>
                <ul class="pp-aliran-legenda">
                    @foreach($statusPermohonan as $s => $n)
                        <li><span class="pp-titik pp-status-{{ $kelasStatus[$s] }}"></span> {{ $s }} <strong>{{ $n }}</strong></li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="glass-panel pp-panel">
            <div class="pp-panel-kepala"><h3>Dokumen Baharu (6 Bulan)</h3></div>
            <div class="pp-trend" role="img" aria-label="Bilangan dokumen baharu 6 bulan terakhir">
                @foreach($trend as $t)
                    <div class="pp-trend-lajur">
                        <span class="pp-trend-nilai">{{ $t['nilai'] }}</span>
                        <div class="pp-trend-bar" style="height: {{ max(2, round(100 * $t['nilai'] / $maksTrend)) }}%"></div>
                        <span class="pp-trend-label">{{ $t['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- 4. Analitik terperinci (berlipat) --}}
    <details class="glass-panel pp-lipat mb-3">
        <summary><i class="bi bi-cpu"></i> Prestasi Imbas &amp; AI dan Analitik Dokumen <span class="pp-kecil">(klik untuk buka)</span></summary>
        <div class="pp-lipat-isi">
            <div class="pp-kpi">
                @include('dashboard.partials.kad-kpi', ['label' => 'Diarkib Terus (AI)', 'nilai' => $arkibTerusAi, 'ikon' => 'bi-magic', 'warna' => 'ungu',
                    'nota' => 'Melalui Imbas & Arkib', 'url' => route('imbas-arkib.create')])
                @include('dashboard.partials.kad-kpi', ['label' => 'Fail Diimbas', 'nilai' => $jumlahFail, 'ikon' => 'bi-files', 'warna' => 'biru'])
                @include('dashboard.partials.kad-kpi', ['label' => 'Purata Keyakinan OCR', 'nilai' => $purataOcr ?? '-', 'unit' => $purataOcr !== null ? '%' : null,
                    'ikon' => 'bi-eye', 'warna' => 'hijau', 'nota' => 'Semua fail yang di-OCR'])
                @include('dashboard.partials.kad-kpi', ['label' => 'Kategori Dokumen', 'nilai' => $jumlahKategori, 'ikon' => 'bi-folder2-open',
                    'warna' => 'kuning', 'url' => route('jenis-dokumen.index')])
            </div>
            <div class="pp-dua pp-dua-sama">
                <div class="glass-panel pp-panel">
                    <div class="pp-panel-kepala"><h3>Kategori Teratas</h3></div>
                    @include('dashboard.partials.bar-senarai', ['item' => $kategoriTeratas, 'kosong' => 'Belum ada dokumen.'])
                </div>
                <div class="glass-panel pp-panel">
                    <div class="pp-panel-kepala"><h3>Dokumen Ikut Bahagian</h3></div>
                    @include('dashboard.partials.bar-senarai', ['item' => $bahagianTeratas, 'kosong' => 'Perbandingan dipaparkan apabila dokumen datang daripada sekurang-kurangnya 2 bahagian.'])
                </div>
            </div>
        </div>
    </details>

    {{-- 5. Dokumen terkini --}}
    <div class="glass-panel pp-panel">
        <div class="pp-panel-kepala">
            <h3>Dokumen Terkini</h3>
            <a href="{{ route('dokumen.index') }}">Lihat semua <i class="bi bi-arrow-right"></i></a>
        </div>
        @if($dokumenTerkini->isEmpty())
            <div class="pp-kosong"><i class="bi bi-folder2-open"></i><div>Belum ada dokumen dalam sistem.</div></div>
        @else
            <table class="pp-jadual">
                <thead><tr><th>Dokumen</th><th>Dimuat Naik Oleh</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach($dokumenTerkini as $d)
                        @php $status = $d->borang?->status_permohonan; @endphp
                        <tr>
                            <td class="pp-sel-dokumen">
                                <span class="pp-rujukan">{{ $d->no_rujukan }}</span>
                                <span class="pp-dok-nama">{{ $d->jenisDokumen->nama_dokumen ?? '-' }}</span>
                            </td>
                            <td data-label="Oleh">{{ $d->pemuatNaik->nama_staff ?? '-' }}
                                <span class="d-block pp-kecil">{{ $d->created_at?->format('d/m/Y') }}</span></td>
                            <td data-label="Status"><span class="pp-status pp-status-{{ $kelasStatus[$status] ?? 'belum' }}">{{ $status ?? 'Tiada permohonan' }}</span></td>
                            <td class="pp-sel-tindakan text-end">
                                <a href="{{ route('dokumen.show', $d->dokumen_id) }}" class="pp-aksi" title="Lihat {{ $d->no_rujukan }}"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>
