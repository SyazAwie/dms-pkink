@props(['logo' => null])
<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            DMS PKINK - Sistem Arkib Digital
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer --}}
    <x-slot:footer>
        <x-mail::footer>
Perbadanan Kemajuan Iktisad Negeri Kelantan (PKINK)
Emel ini dijana secara automatik oleh Sistem Arkib Digital PKINK. Sila jangan balas emel ini.
© {{ date('Y') }} PKINK. Hak cipta terpelihara.
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
