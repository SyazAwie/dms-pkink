<#
==============================================================================
 Sistem Arkib Digital (DMS) PKINK - daftar tugas latar Windows

 Mencipta dua tugas Task Scheduler:
   1. "DMS PKINK Penjadual"  : php artisan schedule:run setiap minit
                               (sandaran harian, pembersihan, semakan keselamatan, degupan)
   2. "DMS PKINK Worker OCR" : pekerja latar OCR/AI (skrip\worker-ocr.cmd), bermula semasa
                               pelayan dihidupkan dan berulang sendiri jika berhenti

 Jalankan dalam PowerShell SEBAGAI PENTADBIR (Run as Administrator):
   powershell -ExecutionPolicy Bypass -File skrip\pasang-tugas-windows.ps1 -Php "C:\php\php.exe"

 Pilihan:
   -Php      laluan penuh php.exe (wajib)
   -Akaun    akaun Windows yang menjalankan tugas (lalai: SYSTEM). Gunakan akaun perkhidmatan
             khas jika Ollama/Tesseract dipasang untuk pengguna tertentu sahaja.
   -Buang    alih keluar kedua-dua tugas
==============================================================================
#>
param(
    [string]$Php,
    [string]$Akaun = 'SYSTEM',
    [switch]$Buang
)

$ErrorActionPreference = 'Stop'
$folderSistem = Split-Path -Parent $PSScriptRoot
$namaPenjadual = 'DMS PKINK Penjadual'
$namaWorker = 'DMS PKINK Worker OCR'

if ($Buang) {
    foreach ($n in @($namaPenjadual, $namaWorker)) {
        if (Get-ScheduledTask -TaskName $n -ErrorAction SilentlyContinue) {
            Unregister-ScheduledTask -TaskName $n -Confirm:$false
            Write-Host "Dialih keluar: $n"
        }
    }
    exit 0
}

if (-not $Php -or -not (Test-Path $Php)) {
    Write-Error "Laluan php.exe tidak sah. Contoh: -Php 'C:\php\php.exe'"
}
if (-not (Test-Path (Join-Path $folderSistem 'artisan'))) {
    Write-Error "Fail artisan tidak dijumpai dalam $folderSistem"
}

$tetapan = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew -StartWhenAvailable
$prinsipal = New-ScheduledTaskPrincipal -UserId $Akaun -LogonType ServiceAccount -RunLevel Highest

# 1. Penjadual: setiap minit, tanpa had masa
$tindakanPenjadual = New-ScheduledTaskAction -Execute $Php -Argument "`"$folderSistem\artisan`" schedule:run" -WorkingDirectory $folderSistem
$pencetusPenjadual = New-ScheduledTaskTrigger -Once -At (Get-Date).Date -RepetitionInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName $namaPenjadual -Action $tindakanPenjadual -Trigger $pencetusPenjadual `
    -Settings $tetapan -Principal $prinsipal -Force | Out-Null
Write-Host "Didaftar: $namaPenjadual (setiap minit)"

# 2. Pekerja latar OCR: bermula semasa pelayan dihidupkan; skrip dimulakan semula secara automatik
$tindakanWorker = New-ScheduledTaskAction -Execute "$folderSistem\skrip\worker-ocr.cmd" -Argument "`"$Php`"" -WorkingDirectory $folderSistem
$pencetusWorker = New-ScheduledTaskTrigger -AtStartup
Register-ScheduledTask -TaskName $namaWorker -Action $tindakanWorker -Trigger $pencetusWorker `
    -Settings $tetapan -Principal $prinsipal -Force | Out-Null
Start-ScheduledTask -TaskName $namaWorker
Write-Host "Didaftar & dimulakan: $namaWorker"

Write-Host ''
Write-Host 'Semak dalam sistem: Status Sistem -> "Penjadual" dan "Pekerja Latar OCR/AI" (hijau dalam 1-2 minit).'
Write-Host 'Selepas mengemas kini kod: php artisan queue:restart (pekerja latar dimulakan semula secara automatik).'
