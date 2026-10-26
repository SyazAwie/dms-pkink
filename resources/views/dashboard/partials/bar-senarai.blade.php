{{--
    Senarai bar mendatar (HTML, tanpa carta canvas): mudah dibaca pada telefon, label panjang dibalut.
    Parameter: $item = [['label' => ..., 'nilai' => int], ...], $kosong (mesej jika tiada data)
--}}
@php $maks = max(1, ...array_column($item ?: [['nilai' => 1]], 'nilai')); @endphp
@if(empty($item))
    <div class="pp-kosong"><i class="bi bi-bar-chart"></i><div>{{ $kosong ?? 'Belum ada data.' }}</div></div>
@else
    <ul class="pp-bar">
        @foreach($item as $i)
            <li>
                <div class="pp-bar-teks"><span>{{ $i['label'] }}</span><strong>{{ $i['nilai'] }}</strong></div>
                <div class="pp-bar-trek"><div class="pp-bar-isi" style="width: {{ round(100 * $i['nilai'] / $maks) }}%"></div></div>
            </li>
        @endforeach
    </ul>
@endif
