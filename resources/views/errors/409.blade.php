@extends('errors.rangka')

{{-- 409 dicetuskan oleh aliran kelulusan (status permohonan sudah berubah); mesejnya dalam BM --}}
@section('kod', '409')
@section('ikon', 'bi-arrow-repeat')
@section('gaya', '--warna: #b7791f; --latar: rgba(214, 158, 46, .14);')
@section('tajuk', 'Status Telah Berubah')
@section('mesej', $exception->getMessage() ?: 'Rekod ini telah dikemas kini oleh pengguna lain.')
@section('petunjuk', 'Sila kembali dan muat semula halaman untuk melihat status terkini.')
