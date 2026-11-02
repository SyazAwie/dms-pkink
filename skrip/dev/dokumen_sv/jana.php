<?php

/**
 * Jana PDF Carta Alir Sistem dan PDF ERD untuk penyelia / penilai (alat pembangun; tidak
 * dimasukkan ke dalam pakej pemasangan).
 *
 *   php skrip/dev/dokumen_sv/jana.php [nama_db] [folder_output]
 *
 * Lalai: nama_db = dms_pkink_pemasangan (skema pemasangan, BACA SAHAJA),
 *        folder_output = folder induk repositori (../).
 * Output: Carta_Alir_Sistem_DMS_PKINK.pdf, ERD_Sistem_DMS_PKINK.pdf
 *
 * Keperluan: Google Chrome / Microsoft Edge (atau CHROME_BIN), internet (Mermaid & viz.js
 * dimuat daripada jsDelivr), MySQL tempatan (root tanpa kata laluan, seperti XAMPP).
 * Nombor halaman senarai kandungan ditulis tetap dalam HTML (pemalar HLM dalam erd_templat.html,
 * span .hlm dalam carta_alir.html); kemas kini jika susunan halaman berubah.
 */
$folder = __DIR__;
$db = $argv[1] ?? 'dms_pkink_pemasangan';
$output = rtrim($argv[2] ?? dirname($folder, 4), '/\\');

$chrome = null;
foreach (array_filter([getenv('CHROME_BIN') ?: null, 'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', '/usr/bin/google-chrome', '/usr/bin/chromium']) as $c) {
    if (is_file($c)) {
        $chrome = $c;
        break;
    }
}
if (! $chrome) {
    fwrite(STDERR, "Chrome / Edge tidak dijumpai. Tetapkan CHROME_BIN.\n");
    exit(1);
}

function cetak(string $chrome, string $html, string $pdf): void
{
    @unlink($pdf);
    $url = 'file:///'.ltrim(str_replace('\\', '/', realpath($html)), '/');
    $arahan = [$chrome, '--headless=new', '--disable-gpu', '--no-pdf-header-footer', '--allow-file-access-from-files',
        '--virtual-time-budget=60000', '--run-all-compositor-stages-before-draw', '--print-to-pdf='.$pdf, $url];
    exec(implode(' ', array_map('escapeshellarg', $arahan)).' 2>&1', $o, $kod);
    if (! is_file($pdf) || filesize($pdf) === 0) {
        fwrite(STDERR, "Gagal menjana {$pdf}\n");
        exit(1);
    }
    printf("%-36s %7.1f KB\n", basename($pdf), filesize($pdf) / 1024);
}

// 1. Carta alir (HTML statik + Mermaid)
cetak($chrome, $folder.'/carta_alir.html', $output.DIRECTORY_SEPARATOR.'Carta_Alir_Sistem_DMS_PKINK.pdf');

// 2. ERD: skema dibaca terus daripada pangkalan data, kemudian disuntik ke templat
passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($folder.'/ekstrak_skema.php').' '.escapeshellarg($db), $kod);
if ($kod !== 0 || ! is_file($folder.'/skema.json')) {
    fwrite(STDERR, "Gagal membaca skema {$db}\n");
    exit(1);
}
$sementara = $folder.'/_erd_cetak.html';
file_put_contents($sementara, str_replace('__SKEMA__', (string) file_get_contents($folder.'/skema.json'), (string) file_get_contents($folder.'/erd_templat.html')));
try {
    cetak($chrome, $sementara, $output.DIRECTORY_SEPARATOR.'ERD_Sistem_DMS_PKINK.pdf');
} finally {
    @unlink($sementara);
    @unlink($folder.'/skema.json');
}
