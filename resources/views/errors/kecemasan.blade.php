@extends('errors.rangka')

@section('kod', '503')
@section('ikon', 'bi-shield-exclamation')
@section('gaya', '--warna: #b45309; --latar: rgba(245, 158, 11, .14);')
@section('tajuk', 'Sistem Ditutup Sementara')
@section('mesej', 'Sistem Arkib Digital ditutup sementara oleh pentadbir atas sebab keselamatan.')
@section('petunjuk', 'Sila cuba lagi kemudian. Data anda selamat dan tidak terjejas.')

@section('butang')
    <a href="{{ route('login') }}" class="butang-kedua"><i class="bi bi-box-arrow-in-right"></i> Log Masuk (Super Admin)</a>
@endsection
