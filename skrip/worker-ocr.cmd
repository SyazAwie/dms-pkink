@echo off
rem ==========================================================================
rem Worker queue OCR/AI (T2) - Sistem Arkib Digital PKINK
rem
rem Memproses analisis Imbas & Arkib / Imbas & Hantar dan butang "Jalankan OCR"
rem satu demi satu (GPU 6GB hanya muat satu model pada satu masa).
rem Berulang sendiri: worker dimulakan semula selepas `php artisan queue:restart`
rem (selepas kemas kini kod) atau jika ia ranap.
rem
rem Penggunaan:  worker-ocr.cmd "C:\laluan\ke\php.exe"
rem Dijalankan oleh Task Scheduler "DMS PKINK Worker OCR" semasa log masuk (README 10, langkah 13).
rem Tanpa worker ini, OCR/AI masih berfungsi tetapi segerak (pengguna menunggu).
rem ==========================================================================
setlocal
set "PHP=%~1"
if "%PHP%"=="" set "PHP=php"
cd /d "%~dp0.."

:ulang
"%PHP%" artisan queue:work --queue=ocr --sleep=2 --tries=1 --timeout=0 --memory=512 >> "storage\logs\worker-ocr.log" 2>&1
timeout /t 5 /nobreak >nul
goto ulang
