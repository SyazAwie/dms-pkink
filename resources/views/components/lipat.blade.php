{{--
    Bahagian berlipat (klik untuk buka) supaya halaman panjang tidak perlu ditatal jauh, terutama di telefon.
    Gaya & skrip (buka bila #id dalam URL menghala ke dalamnya) dalam layouts/app.blade.php.

    Guna: komponen x-lipat dengan atribut id, class, tajuk, ikon (cth. bi-key), sub (penerangan, disembunyikan di
    telefon) dan :buka (true = terbuka semasa dimuat, cth. bila ada ralat). Slot bernama "lencana" (pilihan) ialah
    status yang kekal kelihatan bila tertutup. AWAS: jangan tulis tag komponen dalam komen CSS/HTML biasa;
    Blade tetap mengkompilnya.
--}}
@props(['tajuk', 'ikon' => null, 'sub' => null, 'buka' => false])

<details {{ $attributes->class(['glass-panel', 'lipat']) }} @if($buka) open @endif>
    <summary>
        <span class="lipat-kepala">
            <span class="lipat-tajuk">@if($ikon)<i class="bi {{ $ikon }}" aria-hidden="true"></i> @endif{{ $tajuk }}</span>
            @if($sub)<span class="lipat-sub">{{ $sub }}</span>@endif
        </span>
        @isset($lencana)<span class="lipat-lencana">{{ $lencana }}</span>@endisset
    </summary>
    <div class="lipat-isi">{{ $slot }}</div>
</details>
