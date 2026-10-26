{{--
    Satu kad KPI padat.
    Parameter: $label, $nilai, $ikon (bi-*), $warna (biru|hijau|kuning|merah|ungu|kelabu),
               $url (pilihan: seluruh kad menjadi pautan), $nota (pilihan), $unit (pilihan),
               $penting (pilihan: true = tanda merah di tepi, cth. ada perkara perlu tindakan)
--}}
@php $tag = !empty($url) ? 'a' : 'div'; @endphp
<{{ $tag }} @if(!empty($url)) href="{{ $url }}" @endif class="glass-panel pp-kad {{ !empty($penting) ? 'penting' : '' }}">
    <div class="pp-kad-ikon warna-{{ $warna ?? 'biru' }}"><i class="bi {{ $ikon }}" aria-hidden="true"></i></div>
    <div class="pp-kad-teks">
        <div class="pp-kad-nilai">{{ $nilai }}@if(!empty($unit))<small>{{ $unit }}</small>@endif</div>
        <div class="pp-kad-label">{{ $label }}</div>
        @if(!empty($nota))<div class="pp-kad-nota">{{ $nota }}</div>@endif
    </div>
</{{ $tag }}>
