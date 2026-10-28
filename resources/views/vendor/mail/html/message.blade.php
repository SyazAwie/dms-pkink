@props(['logo' => null])
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')" :logo="$logo">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
**Perbadanan Kemajuan Iktisad Negeri Kelantan (PKINK)**<br>
Emel ini dijana secara automatik oleh Sistem Arkib Digital PKINK. Sila jangan balas emel ini.<br>
© {{ date('Y') }} PKINK. Hak cipta terpelihara.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
