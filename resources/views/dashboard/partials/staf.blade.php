{{-- STAFF: permohonan sendiri (DashboardController::dataStaf) --}}
@php
    use App\Http\Controllers\DashboardController;
    $kelasStatus = ['Dalam Proses' => 'proses', 'Disokong' => 'sokong', 'Diluluskan' => 'lulus', 'Ditolak' => 'tolak'];
@endphp
<section class="pp-bahagian" aria-labelledby="tajuk-staf">
    <h2 class="pp-bahagian-tajuk" id="tajuk-staf"><i class="bi bi-person-lines-fill"></i> Permohonan Saya</h2>

    <div class="pp-kpi">
        @include('dashboard.partials.kad-kpi', ['label' => 'Perlu Tindakan', 'nilai' => $perluTindakan, 'ikon' => 'bi-arrow-counterclockwise',
            'warna' => $perluTindakan ? 'merah' : 'kelabu', 'nota' => 'Ditolak, perlu hantar semula', 'penting' => $perluTindakan > 0,
            'url' => route('dokumen.index')])
        @include('dashboard.partials.kad-kpi', ['label' => 'Sedang Diproses', 'nilai' => $sedangDiproses, 'ikon' => 'bi-hourglass-split',
            'warna' => 'kuning', 'nota' => 'Menunggu Penyokong/Pelulus'])
        @include('dashboard.partials.kad-kpi', ['label' => 'Diluluskan', 'nilai' => $diluluskan, 'ikon' => 'bi-patch-check', 'warna' => 'hijau'])
        @include('dashboard.partials.kad-kpi', ['label' => 'Belum Dihantar', 'nilai' => $belumDihantar, 'ikon' => 'bi-file-earmark',
            'warna' => 'biru', 'nota' => 'Disimpan tanpa kelulusan', 'url' => route('dokumen.index')])
    </div>

    <div class="glass-panel pp-panel">
        <div class="pp-panel-kepala">
            <h3>Dokumen &amp; Permohonan Terkini</h3>
            <a href="{{ route('dokumen.index') }}">Lihat semua <i class="bi bi-arrow-right"></i></a>
        </div>
        @if($terkini->isEmpty())
            <div class="pp-kosong">
                <i class="bi bi-ui-checks-grid"></i>
                <div>Anda belum mengisi sebarang borang.</div>
                <a href="{{ route('isi-borang.index') }}">Isi borang pertama anda <i class="bi bi-arrow-right"></i></a>
            </div>
        @else
            <table class="pp-jadual">
                <thead><tr><th>Dokumen</th><th>Status</th><th>Langkah Seterusnya</th><th></th></tr></thead>
                <tbody>
                    @foreach($terkini as $d)
                        @php $status = $d->borang?->status_permohonan; @endphp
                        <tr>
                            <td class="pp-sel-dokumen">
                                <span class="pp-rujukan">{{ $d->no_rujukan }}</span>
                                <span class="pp-dok-nama">{{ $d->jenisDokumen->nama_dokumen ?? '-' }}</span>
                            </td>
                            <td data-label="Status">
                                <span class="pp-status pp-status-{{ $kelasStatus[$status] ?? 'belum' }}">{{ $status ?? 'Belum Dihantar' }}</span>
                            </td>
                            <td data-label="Seterusnya" class="pp-kecil">{{ DashboardController::langkahSeterusnya($status) }}</td>
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
