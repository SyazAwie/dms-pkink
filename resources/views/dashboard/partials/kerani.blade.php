{{-- KERANI: kerja mengarkib (DashboardController::dataKerani) --}}
<section class="pp-bahagian" aria-labelledby="tajuk-kerani">
    <h2 class="pp-bahagian-tajuk" id="tajuk-kerani"><i class="bi bi-archive"></i> Arkib Dokumen</h2>

    <div class="pp-kpi">
        @include('dashboard.partials.kad-kpi', ['label' => 'Dimuat Naik Bulan Ini', 'nilai' => $dimuatNaikBulanIni, 'ikon' => 'bi-cloud-upload',
            'warna' => 'biru', 'nota' => 'Oleh anda'])
        @include('dashboard.partials.kad-kpi', ['label' => 'Diarkib Bulan Ini', 'nilai' => $diarkibBulanIni, 'ikon' => 'bi-magic',
            'warna' => 'ungu', 'nota' => 'Melalui Imbas & Arkib', 'url' => route('imbas-arkib.create')])
        @include('dashboard.partials.kad-kpi', ['label' => 'Fail Belum Di-OCR', 'nilai' => $belumOcr, 'ikon' => 'bi-file-earmark-text',
            'warna' => $belumOcr ? 'kuning' : 'kelabu', 'nota' => 'Jalankan OCR dari halaman dokumen'])
        @include('dashboard.partials.kad-kpi', ['label' => 'Jumlah Dokumen', 'nilai' => $jumlahDokumen, 'ikon' => 'bi-folder2-open',
            'warna' => 'hijau', 'url' => route('dokumen.index')])
    </div>

    <div class="glass-panel pp-panel">
        <div class="pp-panel-kepala">
            <h3>Dokumen Terkini</h3>
            <a href="{{ route('dokumen.index') }}">Lihat semua <i class="bi bi-arrow-right"></i></a>
        </div>
        @if($dokumenTerkini->isEmpty())
            <div class="pp-kosong">
                <i class="bi bi-archive"></i>
                <div>Belum ada dokumen dalam arkib.</div>
                <a href="{{ route('imbas-arkib.create') }}">Mula dengan Imbas &amp; Arkib <i class="bi bi-arrow-right"></i></a>
            </div>
        @else
            <table class="pp-jadual">
                <thead><tr><th>Dokumen</th><th>Dimuat Naik Oleh</th><th>Tarikh</th><th></th></tr></thead>
                <tbody>
                    @foreach($dokumenTerkini as $d)
                        <tr>
                            <td class="pp-sel-dokumen">
                                <span class="pp-rujukan">{{ $d->no_rujukan }}</span>
                                <span class="pp-dok-nama">{{ $d->jenisDokumen->nama_dokumen ?? '-' }}</span>
                            </td>
                            <td data-label="Oleh">{{ $d->pemuatNaik->nama_staff ?? '-' }}</td>
                            <td data-label="Tarikh" class="pp-kecil">{{ $d->tarikh_dokumen?->format('d/m/Y') }}</td>
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
