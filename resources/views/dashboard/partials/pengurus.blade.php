{{-- PENGURUS: bahagian sendiri (DashboardController::dataPengurus) --}}
@php $kelasStatus = ['Dalam Proses' => 'proses', 'Disokong' => 'sokong', 'Diluluskan' => 'lulus', 'Ditolak' => 'tolak']; @endphp
<section class="pp-bahagian" aria-labelledby="tajuk-pengurus">
    <h2 class="pp-bahagian-tajuk" id="tajuk-pengurus"><i class="bi bi-building"></i> {{ $namaBahagian ?? 'Bahagian Saya' }}</h2>

    <div class="pp-kpi">
        @include('dashboard.partials.kad-kpi', ['label' => 'Dokumen Bulan Ini', 'nilai' => $dokumenBulanIni, 'ikon' => 'bi-file-earmark-plus',
            'warna' => 'biru', 'nota' => "Jumlah keseluruhan: {$jumlahDokumen}", 'url' => route('dokumen.index')])
        @include('dashboard.partials.kad-kpi', ['label' => 'Menunggu Kelulusan', 'nilai' => $menunggu, 'ikon' => 'bi-hourglass-split',
            'warna' => 'kuning', 'nota' => 'Permohonan bahagian sedang diproses'])
        @include('dashboard.partials.kad-kpi', ['label' => 'Diluluskan Bulan Ini', 'nilai' => $diluluskanBulanIni, 'ikon' => 'bi-patch-check', 'warna' => 'hijau'])
        @include('dashboard.partials.kad-kpi', ['label' => 'Ditolak', 'nilai' => $ditolak, 'ikon' => 'bi-x-circle',
            'warna' => $ditolak ? 'merah' : 'kelabu', 'nota' => 'Perlu dihantar semula oleh pemohon'])
    </div>

    <div class="pp-dua">
        <div class="glass-panel pp-panel">
            <div class="pp-panel-kepala">
                <h3>Permohonan Bahagian Terkini</h3>
                <a href="{{ route('dokumen.index') }}">Lihat dokumen <i class="bi bi-arrow-right"></i></a>
            </div>
            @if($permohonanTerkini->isEmpty())
                <div class="pp-kosong"><i class="bi bi-inbox"></i><div>Belum ada permohonan daripada bahagian ini.</div></div>
            @else
                <table class="pp-jadual">
                    <thead><tr><th>Dokumen</th><th>Pemohon</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach($permohonanTerkini as $b)
                            <tr>
                                <td class="pp-sel-dokumen">
                                    <span class="pp-rujukan">{{ $b->dokumen->no_rujukan ?? '-' }}</span>
                                    <span class="pp-dok-nama">{{ $b->dokumen->jenisDokumen->nama_dokumen ?? '-' }}</span>
                                </td>
                                <td data-label="Pemohon">{{ $b->pemohon->nama_staff ?? '-' }}
                                    <span class="d-block pp-kecil">{{ $b->tarikh_permohonan?->format('d/m/Y') }}</span></td>
                                <td data-label="Status"><span class="pp-status pp-status-{{ $kelasStatus[$b->status_permohonan] ?? 'belum' }}">{{ $b->status_permohonan }}</span></td>
                                <td class="pp-sel-tindakan text-end">
                                    <a href="{{ route('dokumen.show', $b->dokumen_id) }}" class="pp-aksi" title="Lihat"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="glass-panel pp-panel">
            <div class="pp-panel-kepala"><h3>Kategori Teratas</h3></div>
            @include('dashboard.partials.bar-senarai', ['item' => $kategoriTeratas, 'kosong' => 'Belum ada dokumen dalam bahagian ini.'])
        </div>
    </div>
</section>
