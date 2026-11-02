@echo off
rem ==========================================================================
rem Pekerja latar OCR/AI - Sistem Arkib Digital (DMS) PKINK
rem
rem Memproses analisis Imbas & Arkib / Imbas & Hantar dan butang "Jalankan OCR"
rem satu demi satu (GPU hanya memuatkan satu model AI pada satu masa).
rem Dimulakan semula secara automatik selepas `php artisan queue:restart`
rem (selepas kemas kini kod) atau jika proses berhenti.
rem
rem Penggunaan:  worker-ocr.cmd "C:\laluan\ke\php.exe"
rem Dijalankan oleh tugas Task Scheduler "DMS PKINK Worker OCR"
rem (daftar dengan skrip\pasang-tugas-windows.ps1; lihat Panduan Pemasangan).
rem Tanpa pekerja latar ini, OCR/AI masih berfungsi tetapi secara segerak (pengguna menunggu).
rem ==========================================================================
setlocal
set "PHP=%~1"
if "%PHP%"=="" set "PHP=php"
cd /d "%~dp0.."

:ulang
"%PHP%" artisan queue:work --queue=ocr --sleep=2 --tries=1 --timeout=0 --memory=512 >> "storage\logs\worker-ocr.log" 2>&1
timeout /t 5 /nobreak >nul
goto ulang
