<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JenisDokumenController;
use App\Http\Controllers\BahagianController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\DokumenController;
use App\Http\Controllers\LogAuditController;
use App\Http\Controllers\BorangController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImbasArkibController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\PendaftaranStafController;

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Laluan tetamu: pengguna yang sudah log masuk akan dibawa ke papan pemuka
Route::middleware('guest')->group(function () {
    // Paparan utama akan terus pergi ke halaman login
    Route::get('/', [AuthController::class, 'paparkanLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'prosesLogin'])->name('login.proses');
    Route::get('/lupa-kata-laluan', [PasswordResetController::class, 'paparBorangRequest'])->name('password.request');
    Route::post('/lupa-kata-laluan', [PasswordResetController::class, 'hantarPautanReset'])->name('password.email');

    // Reset kata laluan MESTI di luar middleware 'auth': pengguna yang terlupa
    // kata laluan belum log masuk, jadi pautan dari emel perlu boleh dibuka terus.
    Route::get('/reset-kata-laluan/{token}', [PasswordResetController::class, 'paparBorangReset'])->name('password.reset');
    Route::post('/reset-kata-laluan', [PasswordResetController::class, 'kemaskiniKataLaluan'])->name('password.update');

    // Permohonan pendaftaran staf baharu (modal "Daftar Akaun Baharu" di halaman log masuk)
    Route::post('/daftar', [PendaftaranStafController::class, 'simpan'])->name('pendaftaran.simpan');
});

// Kumpulan laluan yang dilindungi (Hanya boleh diakses jika dah login).
// 'wajib.tukar': pengguna dengan kata laluan sementara dibawa ke skrin tukar kata laluan dahulu.
Route::middleware(['auth', 'wajib.tukar'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ---- Tukar kata laluan sementara (dikecualikan oleh middleware wajib.tukar) ----
    Route::get('/tukar-kata-laluan', [ProfilController::class, 'paparWajibTukar'])->name('kata-laluan.tukar');
    Route::put('/tukar-kata-laluan', [ProfilController::class, 'simpanWajibTukar'])->name('kata-laluan.simpan');

    // ---- Profil pengguna sendiri (nama, emel, kata laluan) ----
    Route::get('/profil', [ProfilController::class, 'index'])->name('profil.index');
    Route::put('/profil', [ProfilController::class, 'kemaskiniMaklumat'])->name('profil.kemaskini');
    Route::put('/profil/kata-laluan', [ProfilController::class, 'kemaskiniKataLaluan'])->name('profil.kata-laluan');

    // ---- Endpoint JSON: senarai medan EAV bagi satu Jenis Dokumen (borang muat naik dokumen) ----
    Route::get('/jenis-dokumen/{id}/medan', [JenisDokumenController::class, 'medanJson'])
        ->middleware('peranan:SUPERADMIN,ADMIN,KERANI,PENGURUS,STAFF')
        ->name('jenis-dokumen.medan');

    // ---- Modul pentadbiran: SUPERADMIN & ADMIN sahaja ----
    // Tiada halaman butiran (show) bagi modul ini, jadi laluan show tidak didaftarkan.
    Route::middleware('peranan:SUPERADMIN,ADMIN')->group(function () {
        Route::resource('jenis-dokumen', JenisDokumenController::class)->except('show');   // Kategori Dokumen
        Route::resource('bahagian', BahagianController::class)->except('show');            // Pengurusan Bahagian
        Route::resource('users', UserController::class)->except('show');                   // Pengurusan Kakitangan

        // Pengesahan Pendaftaran staf baharu
        Route::get('/pendaftaran', [PendaftaranStafController::class, 'index'])->name('pendaftaran.index');
        Route::post('/pendaftaran/{id}/lulus', [PendaftaranStafController::class, 'lulus'])->name('pendaftaran.lulus');
        Route::post('/pendaftaran/{id}/tolak', [PendaftaranStafController::class, 'tolak'])->name('pendaftaran.tolak');
    });

    // ---- Dokumen: kebenaran dikawal oleh DokumenPolicy dalam controller (ikut peranan & skop) ----
    Route::resource('dokumen', DokumenController::class);
    Route::get('/dokumen/scan/{id}/papar', [DokumenController::class, 'paparScan'])->name('dokumen.scan.papar');
    Route::post('/dokumen/scan/{id}/ocr', [DokumenController::class, 'jalankanOcr'])->name('dokumen.scan.ocr');

    // ---- Imbas & Arkib: dokumen fizikal SEDIA LULUS, OCR+AI isi borang ----
    Route::get('/imbas-arkib', [ImbasArkibController::class, 'create'])->name('imbas-arkib.create');
    Route::post('/imbas-arkib/analisis', [ImbasArkibController::class, 'analisis'])->name('imbas-arkib.analisis');
    Route::post('/imbas-arkib/simpan', [ImbasArkibController::class, 'simpan'])->name('imbas-arkib.simpan');

    // ---- Aliran Kelulusan (versi asas — lihat nota dalam BorangController) ----
    Route::post('/dokumen/{id}/kelulusan/hantar', [BorangController::class, 'hantar'])->name('kelulusan.hantar');
    Route::post('/dokumen/{id}/kelulusan/sokong', [BorangController::class, 'sokong'])->name('kelulusan.sokong');
    Route::post('/dokumen/{id}/kelulusan/lulus', [BorangController::class, 'lulus'])->name('kelulusan.lulus');
    Route::post('/dokumen/{id}/kelulusan/tolak', [BorangController::class, 'tolak'])->name('kelulusan.tolak');
    Route::get('/kelulusan', [BorangController::class, 'index'])
        ->middleware('peranan:SUPERADMIN,ADMIN,PENYOKONG,PELULUS')
        ->name('kelulusan.index');

    // ---- Log Audit: Juruaudit & Super Admin sahaja ----
    Route::get('/log-audit', [LogAuditController::class, 'index'])
        ->middleware('peranan:SUPERADMIN,AUDIT')
        ->name('log-audit.index');

});