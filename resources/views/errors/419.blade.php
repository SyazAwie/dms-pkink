@extends('errors.rangka')

@section('kod', '419')
@section('ikon', 'bi-hourglass-bottom')
@section('gaya', '--warna: #b7791f; --latar: rgba(214, 158, 46, .14);')
@section('tajuk', 'Sesi Tamat Tempoh')
@section('mesej', 'Sesi anda telah tamat tempoh kerana halaman dibiarkan terlalu lama.')
@section('petunjuk', 'Maklumat yang baru dihantar tidak disimpan. Sila muat semula halaman dan cuba lagi.')

@section('butang')
    <a href="javascript:history.back()" class="butang-kedua"><i class="bi bi-arrow-left"></i> Kembali</a>
    <a href="{{ url('/') }}" class="butang-utama"><i class="bi bi-box-arrow-in-right"></i> Log Masuk Semula</a>
@endsection
