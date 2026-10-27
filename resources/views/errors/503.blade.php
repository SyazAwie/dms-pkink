@extends('errors.rangka')

@section('kod', '503')
@section('ikon', 'bi-tools')
@section('tajuk', 'Sistem Dalam Penyelenggaraan')
@section('mesej', 'Sistem Arkib Digital sedang diselenggara buat sementara waktu.')
@section('petunjuk', 'Sila cuba lagi sebentar lagi. Harap maaf atas kesulitan.')

@section('butang')
    <a href="{{ url('/') }}" class="butang-utama"><i class="bi bi-arrow-clockwise"></i> Cuba Lagi</a>
@endsection
