{{-- AUDIT: aktiviti sistem (DashboardController::dataAudit) --}}
<section class="pp-bahagian" aria-labelledby="tajuk-audit">
    <h2 class="pp-bahagian-tajuk" id="tajuk-audit"><i class="bi bi-shield-check"></i> Aktiviti Sistem</h2>

    <div class="pp-kpi">
        @include('dashboard.partials.kad-kpi', ['label' => 'Log Hari Ini', 'nilai' => $logHariIni, 'ikon' => 'bi-journal-text',
            'warna' => 'biru', 'url' => route('log-audit.index')])
        @include('dashboard.partials.kad-kpi', ['label' => 'Log 7 Hari', 'nilai' => $logMingguIni, 'ikon' => 'bi-calendar-week', 'warna' => 'hijau'])
        @include('dashboard.partials.kad-kpi', ['label' => 'Tindakan Padam', 'nilai' => $padamMingguIni, 'ikon' => 'bi-trash3',
            'warna' => $padamMingguIni ? 'merah' : 'kelabu', 'penting' => $padamMingguIni > 0, 'nota' => '7 hari terakhir'])
        @include('dashboard.partials.kad-kpi', ['label' => 'Pengguna Aktif', 'nilai' => $penggunaAktif, 'ikon' => 'bi-people',
            'warna' => 'kuning', 'nota' => 'Hari ini'])
    </div>

    <div class="pp-dua">
        <div class="glass-panel pp-panel">
            <div class="pp-panel-kepala">
                <h3>Log Terkini</h3>
                <a href="{{ route('log-audit.index') }}">Buka Log Audit <i class="bi bi-arrow-right"></i></a>
            </div>
            @if($logTerkini->isEmpty())
                <div class="pp-kosong"><i class="bi bi-journal"></i><div>Belum ada log direkodkan.</div></div>
            @else
                <table class="pp-jadual">
                    <thead><tr><th>Masa</th><th>Pengguna</th><th>Tindakan</th></tr></thead>
                    <tbody>
                        @foreach($logTerkini as $l)
                            <tr>
                                <td data-label="Masa" class="pp-kecil">{{ $l->created_at?->format('d/m/Y, H:i') }}</td>
                                <td data-label="Pengguna">{{ $l->pengguna->nama_staff ?? 'Sistem / tetamu' }}</td>
                                <td data-label="Tindakan">
                                    <span class="pp-status {{ str_starts_with((string) $l->tindakan, 'Padam') ? 'pp-status-tolak' : 'pp-status-belum' }}">{{ $l->tindakan }}</span>
                                    <span class="d-block pp-kecil">{{ $l->nama_jadual }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="glass-panel pp-panel">
            <div class="pp-panel-kepala"><h3>Pecahan Tindakan (7 Hari)</h3></div>
            @include('dashboard.partials.bar-senarai', ['item' => $pecahanTindakan, 'kosong' => 'Tiada aktiviti dalam 7 hari terakhir.'])
        </div>
    </div>
</section>
