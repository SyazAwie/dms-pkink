@extends('errors.rangka')

{{-- Fallback bagi kod 5xx yang tiada halaman khusus --}}
@section('kod', $exception->getStatusCode())
@section('ikon', 'bi-exclamation-octagon')
@section('gaya', '--warna: #c43d4e; --latar: rgba(220, 53, 69, .1);')
@section('tajuk', 'Ralat Sistem')
@section('mesej', 'Berlaku ralat pada pelayan semasa memproses permintaan anda.')
@section('petunjuk', 'Sila cuba lagi sebentar lagi. Jika masalah berterusan, hubungi Pentadbir Sistem.')
