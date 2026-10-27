@extends('errors.rangka')

{{-- Fallback bagi kod 4xx yang tiada halaman khusus --}}
@section('kod', $exception->getStatusCode())
@section('ikon', 'bi-exclamation-circle')
@section('tajuk', 'Permintaan Tidak Dapat Diproses')
@section('mesej', 'Permintaan anda tidak dapat diproses oleh sistem.')
@section('petunjuk', 'Sila kembali dan cuba lagi. Jika masalah berterusan, hubungi Pentadbir Sistem.')
