{{--
    Giliran kelulusan (DashboardController::dataGiliran), dikongsi PENYOKONG & PELULUS.
    Parameter tambahan: $id, $tajuk, $ikon, $labelMenunggu, $labelSelesai, $kosong
--}}
@php $hadHari = \App\Http\Controllers\DashboardController::HARI_TERTUNGGAK; @endphp
<section class="pp-bahagian" aria-labelledby="tajuk-{{ $id }}">
    <h2 class="pp-bahagian-tajuk" id="tajuk-{{ $id }}"><i class="bi {{ $ikon }}"></i> {{ $tajuk }}</h2>

    <div class="pp-kpi">
        @include('dashboard.partials.kad-kpi', ['label' => $labelMenunggu, 'nilai' => $menunggu, 'ikon' => 'bi-inbox',
            'warna' => $menunggu ? 'biru' : 'kelabu', 'url' => route('kelulusan.index')])
        @include('dashboard.partials.kad-kpi', ['label' => "Tertunggak > {$hadHari} Hari", 'nilai' => $tertunggak, 'ikon' => 'bi-alarm',
            'warna' => $tertunggak ? 'merah' : 'kelabu', 'penting' => $tertunggak > 0, 'nota' => 'Perlu diutamakan'])
        @include('dashboard.partials.kad-kpi', ['label' => 'Paling Lama Menunggu', 'nilai' => $palingLama ?? '-', 'unit' => $palingLama !== null ? 'hari' : null,
            'ikon' => 'bi-hourglass-split', 'warna' => 'kuning'])
        @include('dashboard.partials.kad-kpi', ['label' => $labelSelesai, 'nilai' => $selesaiBulanIni, 'ikon' => 'bi-check2-circle',
            'warna' => 'hijau', 'nota' => 'Oleh anda'])
    </div>

    <div class="glass-panel pp-panel">
        <div class="pp-panel-kepala">
            <h3>Giliran Anda <span class="pp-kecil">(paling lama dahulu)</span></h3>
            <a href="{{ route('kelulusan.index') }}">Lihat semua <i class="bi bi-arrow-right"></i></a>
        </div>
        @if($senarai->isEmpty())
            <div class="pp-kosong">
                <i class="bi bi-check2-all"></i>
                <div>{{ $kosong }}</div>
            </div>
        @else
            <table class="pp-jadual">
                <thead><tr><th>Dokumen</th><th>Pemohon</th><th>Menunggu</th><th></th></tr></thead>
                <tbody>
                    @foreach($senarai as $b)
                        @php $hari = $b->{$lajurMasuk} ? (int) $b->{$lajurMasuk}->diffInDays(now()) : null; @endphp
                        <tr>
                            <td class="pp-sel-dokumen">
                                <span class="pp-rujukan">{{ $b->dokumen->no_rujukan ?? '-' }}</span>
                                <span class="pp-dok-nama">{{ $b->dokumen->jenisDokumen->nama_dokumen ?? '-' }}</span>
                            </td>
                            <td data-label="Pemohon">{{ $b->pemohon->nama_staff ?? '-' }}</td>
                            <td data-label="Menunggu">
                                <span class="pp-status {{ $hari !== null && $hari > $hadHari ? 'pp-status-tolak' : 'pp-status-belum' }}">
                                    {{ $hari === null ? '-' : ($hari === 0 ? 'Hari ini' : "{$hari} hari") }}
                                </span>
                            </td>
                            <td class="pp-sel-tindakan text-end">
                                <a href="{{ route('dokumen.show', $b->dokumen_id) }}" class="pp-aksi" title="Semak {{ $b->dokumen->no_rujukan ?? '' }}"><i class="bi bi-arrow-right"></i></a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</section>
