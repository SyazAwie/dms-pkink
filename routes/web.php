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

// Paparan utama akan terus pergi ke halaman login
Route::get('/', [AuthController::class, 'paparkanLogin'])->name('login');
Route::post('/login', [AuthController::class, 'prosesLogin'])->name('login.proses');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/lupa-kata-laluan', [PasswordResetController::class, 'paparBorangRequest'])->name('password.request');
Route::post('/lupa-kata-laluan', [PasswordResetController::class, 'hantarPautanReset'])->name('password.email');

// Reset kata laluan MESTI di luar middleware 'auth': pengguna yang terlupa
// kata laluan belum log masuk, jadi pautan dari emel perlu boleh dibuka terus.
Route::get('/reset-kata-laluan/{token}', [PasswordResetController::class, 'paparBorangReset'])->name('password.reset');
Route::post('/reset-kata-laluan', [PasswordResetController::class, 'kemaskiniKataLaluan'])->name('password.update');

// Kumpulan laluan yang dilindungi (Hanya boleh diakses jika dah login)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ---- Endpoint JSON: senarai medan EAV bagi satu Jenis Dokumen (borang muat naik dokumen) ----
    Route::get('/jenis-dokumen/{id}/medan', [JenisDokumenController::class, 'medanJson'])
        ->middleware('peranan:SUPERADMIN,ADMIN,KERANI,PENGURUS,STAFF')
        ->name('jenis-dokumen.medan');

    // ---- Modul pentadbiran: SUPERADMIN & ADMIN sahaja ----
    Route::middleware('peranan:SUPERADMIN,ADMIN')->group(function () {
        Route::resource('jenis-dokumen', JenisDokumenController::class);   // Kategori Dokumen
        Route::resource('bahagian', BahagianController::class);            // Pengurusan Bahagian
        Route::resource('users', UserController::class);                   // Pengurusan Kakitangan
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