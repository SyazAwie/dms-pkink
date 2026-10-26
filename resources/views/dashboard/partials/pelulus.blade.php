{{-- PELULUS: giliran kelulusan (permohonan "Disokong") --}}
@include('dashboard.partials.giliran', [
    'id' => 'pelulus', 'tajuk' => 'Giliran Kelulusan', 'ikon' => 'bi-patch-check',
    'labelMenunggu' => 'Menunggu Kelulusan', 'labelSelesai' => 'Diluluskan Bulan Ini',
    'kosong' => 'Tiada permohonan menunggu kelulusan anda. Semua sudah diuruskan.',
])
