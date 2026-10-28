{{--
    Templat notifikasi emel DMS PKINK (salinan templat Laravel, dalam BM).
    $message wujud hanya semasa emel sebenar dihantar: logo dibenam (CID).
    $peringatan (pilihan): kotak peringatan keselamatan, dihantar melalui
    MailMessage::markdown('notifications::email', ['peringatan' => ...]).
--}}
<x-mail::message :logo="\App\Support\LogoEmel::src($message ?? null)">
{{-- Greeting --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# Harap maaf!
@else
# Salam Sejahtera,
@endif
@endif

{{-- Intro Lines --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Peringatan keselamatan --}}
@if (! empty($peringatan))
<x-mail::peringatan>
{{ $peringatan }}
</x-mail::peringatan>
@endif

{{-- Salutation --}}
@if (! empty($salutation))
{{ $salutation }}
@else
Sekian, terima kasih.<br>
Bahagian Digital PKINK
@endif

{{-- Subcopy --}}
@isset($actionText)
<x-slot:subcopy>
Jika butang "{{ $actionText }}" tidak berfungsi, salin dan tampal URL di bawah ke dalam pelayar web anda: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
