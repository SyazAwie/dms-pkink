{{-- PENYOKONG: giliran sokongan (permohonan "Dalam Proses") --}}
@include('dashboard.partials.giliran', [
    'id' => 'penyokong', 'tajuk' => 'Giliran Sokongan', 'ikon' => 'bi-hand-thumbs-up',
    'labelMenunggu' => 'Menunggu Sokongan', 'labelSelesai' => 'Disokong Bulan Ini',
    'kosong' => 'Tiada permohonan menunggu sokongan anda. Semua sudah diuruskan.',
])
