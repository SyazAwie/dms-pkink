<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BahagianController;
use App\Http\Controllers\BorangController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DelegasiController;
use App\Http\Controllers\DuaFaktorController;
use App\Http\Controllers\DokumenController;
use App\Http\Controllers\ImbasArkibController;
use App\Http\Controllers\ImbasHantarController;
use App\Http\Controllers\IsiBorangController;
use App\Http\Controllers\JenisDokumenController;
use App\Http\Controllers\KeselamatanController;
use App\Http\Controllers\LogAuditController;
use App\Http\Controllers\LogMasukController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PelupusanController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\PendaftaranStafController;
use App\Http\Controllers\ProfilController;
use App\Http\Controllers\SlipKelulusanController;
use App\Http\Controllers\StatusSistemController;
use App\Http\Controllers\TongSampahController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Pengesahan slip kelulusan (P6): AWAM, dibuka oleh kod QR pada slip; kod HMAC + had kadar
Route::get('/sahkan-slip/{borang}/{kod}', [SlipKelulusanController::class, 'sahkan'])
    ->whereNumber('borang')->where('kod', '[A-Za-z0-9]{1,32}')->middleware('throttle:30,1')->name('slip.sahkan');

// Mod Demo (Fasa D): halaman tayangan QR + log masuk butang akaun demo; 404 bila mod demo mati
Route::get('/demo', [DemoController::class, 'halaman'])->name('demo.halaman');
Route::post('/demo/masuk', [DemoController::class, 'masuk'])->middleware(['guest', 'throttle:30,1'])->name('demo.masuk');

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

    // Kod 2FA selepas kata laluan betul (P13); sesi "menunggu" sah 5 minit
    Route::get('/log-masuk/dua-faktor', [AuthController::class, 'cabaranDuaFaktor'])->name('dua-faktor.cabaran');
    Route::post('/log-masuk/dua-faktor', [AuthController::class, 'sahkanDuaFaktor'])->name('dua-faktor.sahkan');

    // Permohonan pendaftaran staf baharu (modal "Daftar Akaun Baharu" di halaman log masuk)
    Route::post('/daftar', [PendaftaranStafController::class, 'simpan'])->name('pendaftaran.simpan');
});

// Kumpulan laluan yang dilindungi (Hanya boleh diakses jika dah login).
// 'wajib.tukar': pengguna dengan kata laluan sementara dibawa ke skrin tukar kata laluan dahulu.
Route::middleware(['auth', 'wajib.tukar', 'wajib.dua.faktor'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ---- Tukar kata laluan sementara (dikecualikan oleh middleware wajib.tukar) ----
    Route::get('/tukar-kata-laluan', [ProfilController::class, 'paparWajibTukar'])->name('kata-laluan.tukar');
    Route::put('/tukar-kata-laluan', [ProfilController::class, 'simpanWajibTukar'])->name('kata-laluan.simpan');

    // ---- Profil pengguna sendiri (nama, emel, kata laluan) ----
    Route::get('/profil', [ProfilController::class, 'index'])->name('profil.index');
    Route::put('/profil', [ProfilController::class, 'kemaskiniMaklumat'])->name('profil.kemaskini');
    Route::put('/profil/kata-laluan', [ProfilController::class, 'kemaskiniKataLaluan'])->name('profil.kata-laluan');
    // Pengesahan dua faktor (P13)
    Route::post('/profil/dua-faktor/mula', [DuaFaktorController::class, 'mula'])->name('profil.dua-faktor.mula');
    Route::post('/profil/dua-faktor/sahkan', [DuaFaktorController::class, 'sahkan'])->name('profil.dua-faktor.sahkan');
    Route::post('/profil/dua-faktor/matikan', [DuaFaktorController::class, 'matikan'])->name('profil.dua-faktor.matikan');
    Route::post('/profil/dua-faktor/kod-pemulihan', [DuaFaktorController::class, 'janaKodPemulihan'])->name('profil.dua-faktor.kod');
    // ---- Notifikasi dalam sistem (loceng topbar), semua peranan ----
    Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
    Route::get('/notifikasi/ringkasan', [NotifikasiController::class, 'ringkasan'])->name('notifikasi.ringkasan');
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
    Route::get('/notifikasi/{id}/buka', [NotifikasiController::class, 'buka'])->name('notifikasi.buka');
    // Langganan notifikasi peranti (Web Push) bagi pelayar/peranti semasa
    Route::post('/notifikasi/langganan', [NotifikasiController::class, 'langgan'])->name('notifikasi.langgan');
    Route::delete('/notifikasi/langganan', [NotifikasiController::class, 'hentiLanggan'])->name('notifikasi.henti-langgan');

    // Tukar peranan aktif bagi pengguna berbilang peranan (menu nama di topbar)
    Route::post('/peranan/tukar', [ProfilController::class, 'tukarPeranan'])->name('peranan.tukar');

    // ---- Endpoint HTML: input medan EAV bagi satu Jenis Dokumen (borang muat naik dokumen) ----
    Route::get('/jenis-dokumen/{id}/medan', [JenisDokumenController::class, 'medanBorang'])
        ->middleware('peranan:SUPERADMIN,ADMIN,KERANI,PENGURUS,STAFF')
        ->name('jenis-dokumen.medan');

    // ---- Modul pentadbiran: SUPERADMIN & ADMIN sahaja ----
    // Tiada halaman butiran (show) bagi modul ini, jadi laluan show tidak didaftarkan.
    // ---- KERANI boleh lihat senarai Kategori Dokumen & Bahagian (baca sahaja) ----
    Route::middleware('peranan:SUPERADMIN,ADMIN,KERANI')->group(function () {
        Route::get('/jenis-dokumen', [JenisDokumenController::class, 'index'])->name('jenis-dokumen.index');
        Route::get('/bahagian', [BahagianController::class, 'index'])->name('bahagian.index');
    });

    Route::middleware('peranan:SUPERADMIN,ADMIN')->group(function () {
        Route::resource('jenis-dokumen', JenisDokumenController::class)->except('show', 'index');   // Kategori Dokumen
        Route::resource('bahagian', BahagianController::class)->except('show', 'index');            // Pengurusan Bahagian
        Route::resource('users', UserController::class)->except('show');                   // Pengurusan Kakitangan

        // Pengesahan Pendaftaran staf baharu
        Route::get('/pendaftaran', [PendaftaranStafController::class, 'index'])->name('pendaftaran.index');
        Route::post('/pendaftaran/{id}/lulus', [PendaftaranStafController::class, 'lulus'])->name('pendaftaran.lulus');
        Route::post('/pendaftaran/{id}/tolak', [PendaftaranStafController::class, 'tolak'])->name('pendaftaran.tolak');

        // Tong Sampah: rekod yang di-soft delete + pemulihan (tiada padam kekal)
        Route::get('/tong-sampah', [TongSampahController::class, 'index'])->name('tong-sampah.index');
        Route::post('/tong-sampah/{jenis}/{id}/pulih', [TongSampahController::class, 'pulih'])
            ->whereIn('jenis', ['dokumen', 'kategori', 'bahagian', 'kakitangan'])->whereNumber('id')->name('tong-sampah.pulih');

        // Tempoh simpanan & pelupusan (P12)
        Route::get('/pelupusan', [PelupusanController::class, 'index'])->name('pelupusan.index');
        Route::post('/pelupusan/{id}/kekalkan', [PelupusanController::class, 'kekalkan'])->whereNumber('id')->name('pelupusan.kekalkan');
        Route::post('/pelupusan/{id}/lupus', [PelupusanController::class, 'lupus'])->whereNumber('id')->name('pelupusan.lupus');

        // Pusat Keselamatan (Fasa Keselamatan): ancaman, pengerasan, butang kecemasan
        Route::get('/keselamatan', [KeselamatanController::class, 'index'])->name('keselamatan.index');
        Route::get('/keselamatan/pelan-insiden', [KeselamatanController::class, 'pelan'])->name('keselamatan.pelan');
        Route::post('/keselamatan/log-keluar', [KeselamatanController::class, 'logKeluar'])->name('keselamatan.log-keluar');
        Route::post('/keselamatan/log-keluar-semua', [KeselamatanController::class, 'logKeluarSemua'])->name('keselamatan.log-keluar-semua');
        Route::post('/keselamatan/kunci', [KeselamatanController::class, 'kunci'])->name('keselamatan.kunci');
        Route::post('/keselamatan/buka-kunci', [KeselamatanController::class, 'bukaKunci'])->name('keselamatan.buka-kunci');
        Route::post('/keselamatan/sekat-ip', [KeselamatanController::class, 'sekatIp'])->name('keselamatan.sekat-ip');
        Route::post('/keselamatan/sekat-ip/{sekatan}/buka', [KeselamatanController::class, 'bukaIp'])->name('keselamatan.buka-ip');
        Route::post('/keselamatan/kecemasan', [KeselamatanController::class, 'kecemasan'])->name('keselamatan.kecemasan');

        // Status Sistem: semakan OCR/AI, sandaran, penjadual, storan
        Route::get('/status-sistem', [StatusSistemController::class, 'index'])->name('status-sistem.index');
    });

    // ---- Dokumen: kebenaran dikawal oleh DokumenPolicy dalam controller (ikut peranan & skop) ----
    // Eksport CSV (P9): sebelum resource supaya tidak ditafsir sebagai dokumen/{id}
    Route::get('/dokumen/eksport', [DokumenController::class, 'eksport'])->name('dokumen.eksport');
    Route::resource('dokumen', DokumenController::class);
    Route::get('/dokumen/scan/{id}/papar', [DokumenController::class, 'paparScan'])->name('dokumen.scan.papar');
    // Salinan bertera air (P11): nama pemuat turun + tarikh pada setiap muka surat, direkod dalam log audit
    Route::get('/dokumen/scan/{id}/muat-turun', [DokumenController::class, 'muatTurunScan'])->name('dokumen.scan.muat-turun');
    Route::post('/dokumen/scan/{id}/ocr', [DokumenController::class, 'jalankanOcr'])->name('dokumen.scan.ocr');
    // Semakan teks OCR oleh staf: tandakan disemak / betulkan teks (kebenaran edit dokumen)
    Route::post('/dokumen/scan/{id}/ocr/semak', [DokumenController::class, 'semakOcr'])->name('dokumen.scan.ocr.semak');
    Route::put('/dokumen/scan/{id}/ocr/teks', [DokumenController::class, 'betulkanOcr'])->name('dokumen.scan.ocr.betulkan');

    // ---- Imbas & Arkib: dokumen fizikal SEDIA LULUS, OCR+AI isi borang ----
    // Hanya KERANI/ADMIN/SUPERADMIN: dokumen terus "Diluluskan" tanpa Penyokong/Pelulus,
    // jadi STAFF & PENGURUS mesti guna Muat Naik Dokumen biasa (S-01).
    Route::middleware('peranan:SUPERADMIN,ADMIN,KERANI')->group(function () {
        Route::get('/imbas-arkib', [ImbasArkibController::class, 'create'])->name('imbas-arkib.create');
        Route::post('/imbas-arkib/analisis', [ImbasArkibController::class, 'analisis'])->name('imbas-arkib.analisis');
        // {token}: folder ocr_sementara/{token}/ (40 aksara alfanumerik sahaja, elak path traversal)
        Route::get('/imbas-arkib/semak/{token}', [ImbasArkibController::class, 'semak'])
            ->where('token', '[A-Za-z0-9]{40}')->name('imbas-arkib.semak');
        Route::post('/imbas-arkib/simpan/{token}', [ImbasArkibController::class, 'simpan'])
            ->where('token', '[A-Za-z0-9]{40}')->name('imbas-arkib.simpan');
        Route::get('/imbas-arkib/pratonton/{token}', [ImbasArkibController::class, 'pratonton'])
            ->where('token', '[A-Za-z0-9]{40}')->name('imbas-arkib.pratonton');
        Route::post('/imbas-arkib/semak/{token}/baca-semula', [ImbasArkibController::class, 'bacaSemula'])
            ->where('token', '[A-Za-z0-9]{40}')->name('imbas-arkib.baca-semula');
    });

    // ---- Isi Borang + Imbas & Hantar: staf isi borang fizikal sendiri, OCR+AI isi borang digital,
    // staf sahkan, kemudian hantar untuk kelulusan (Dalam Proses). Semua peranan muat naik.
    Route::middleware('peranan:SUPERADMIN,ADMIN,KERANI,PENGURUS,STAFF')->group(function () {
        Route::get('/isi-borang', [IsiBorangController::class, 'index'])->name('isi-borang.index');
        // Muat model AI lebih awal (halaman Imbas & Arkib / Imbas & Hantar)
        Route::post('/imbas/panaskan-ai', [ImbasHantarController::class, 'panaskanAi'])->name('imbas.panaskan-ai');
        Route::get('/imbas-hantar', [ImbasHantarController::class, 'create'])->name('imbas-hantar.create');
        Route::post('/imbas-hantar/analisis', [ImbasHantarController::class, 'analisis'])->name('imbas-hantar.analisis');
        Route::get('/imbas-hantar/semak/{token}', [ImbasHantarController::class, 'semak'])
            ->where('token', '[A-Za-z0-9]{40}')->name('imbas-hantar.semak');
        Route::post('/imbas-hantar/simpan/{token}', [ImbasHantarController::class, 'simpan'])
            ->where('token', '[A-Za-z0-9]{40}')->name('imbas-hantar.simpan');
        Route::get('/imbas-hantar/pratonton/{token}', [ImbasHantarController::class, 'pratonton'])
            ->where('token', '[A-Za-z0-9]{40}')->name('imbas-hantar.pratonton');
        Route::post('/imbas-hantar/semak/{token}/baca-semula', [ImbasHantarController::class, 'bacaSemula'])
            ->where('token', '[A-Za-z0-9]{40}')->name('imbas-hantar.baca-semula');
    });

    // ---- Aliran Kelulusan (versi asas — lihat nota dalam BorangController) ----
    Route::post('/dokumen/{id}/kelulusan/hantar', [BorangController::class, 'hantar'])->name('kelulusan.hantar');
    Route::post('/dokumen/{id}/kelulusan/sokong', [BorangController::class, 'sokong'])->name('kelulusan.sokong');
    Route::post('/dokumen/{id}/kelulusan/lulus', [BorangController::class, 'lulus'])->name('kelulusan.lulus');
    Route::post('/dokumen/{id}/kelulusan/tolak', [BorangController::class, 'tolak'])->name('kelulusan.tolak');
    Route::get('/dokumen/{id}/kelulusan/slip', [SlipKelulusanController::class, 'muatTurun'])->name('kelulusan.slip');

    // Delegasi kelulusan semasa cuti (P5): pegawai lantik pengganti; pentadbir urus semua
    Route::middleware('peranan:SUPERADMIN,ADMIN,PENYOKONG,PELULUS')->group(function () {
        Route::get('/delegasi', [DelegasiController::class, 'index'])->name('delegasi.index');
        Route::post('/delegasi', [DelegasiController::class, 'store'])->name('delegasi.store');
        Route::post('/delegasi/{delegasi}/batal', [DelegasiController::class, 'batal'])->name('delegasi.batal');
    });
    Route::get('/kelulusan', [BorangController::class, 'index'])
        ->middleware('peranan:SUPERADMIN,ADMIN,PENYOKONG,PELULUS')
        ->name('kelulusan.index');

    // ---- Log Audit: Juruaudit & Super Admin sahaja ----
    Route::get('/log-audit', [LogAuditController::class, 'index'])
        ->middleware('peranan:SUPERADMIN,AUDIT')
        ->name('log-audit.index');
    Route::get('/log-audit/eksport', [LogAuditController::class, 'eksport'])
        ->middleware('peranan:SUPERADMIN,AUDIT')
        ->name('log-audit.eksport');
    // Sejarah log masuk (P13)
    Route::get('/log-masuk', [LogMasukController::class, 'index'])
        ->middleware('peranan:SUPERADMIN,AUDIT')
        ->name('log-masuk.index');

});
