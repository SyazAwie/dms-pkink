<?php

/**
 * Jana PDF dokumentasi pakej pemasangan (alat pembangun; tidak dimasukkan ke dalam pakej).
 *
 *   php skrip/dev/jana_pdf_dokumen.php
 *
 * Output (di luar repositori):
 *   ../Pakej_DMS_PKINK/pdf/MANUAL_PENGGUNA_DMS_PKINK.pdf   docs/pakej/manual/MANUAL_PENGGUNA.html
 *   ../Pakej_DMS_PKINK/pdf/PANDUAN_PEMASANGAN.pdf          docs/pakej/PANDUAN_PEMASANGAN.md
 *   ../Pakej_DMS_PKINK/pdf/PANDUAN_UJIAN_KOMPUTER_RIBA.pdf docs/pakej/PANDUAN_UJIAN_KOMPUTER_RIBA.md
 *   ../Pakej_DMS_PKINK/pdf/MANUAL_TEMPLAT_BORANG.pdf       docs/MANUAL_TEMPLAT_BORANG.md
 *   ../Pakej_DMS_PKINK/pdf/PELAN_TINDAK_BALAS_INSIDEN.pdf  docs/PELAN_TINDAK_BALAS_INSIDEN.md
 *   ../Pakej_DMS_PKINK/rahsia/AKAUN_UAT.pdf                ../Pakej_DMS_PKINK/rahsia/AKAUN_UAT.md (jika ada)
 *
 * Keperluan: Google Chrome / Microsoft Edge (atau CHROME_BIN), Poppler (pdftotext, dari
 * OCR_PDFTOPPM_BIN dalam .env) untuk nombor halaman senarai kandungan manual.
 */

use League\CommonMark\GithubFlavoredMarkdownConverter;

$akar = dirname(__DIR__, 2);
require $akar.'/vendor/autoload.php';

$luar = dirname($akar).'/Pakej_DMS_PKINK';
$folderPdf = $luar.'/pdf';
@mkdir($folderPdf, 0755, true);

$chrome = cariChrome();
$pdftotext = cariPdftotext($akar);
$css = urlFail($akar.'/docs/pakej/manual/gaya_cetak.css');
$logo = urlFail($akar.'/public/images/Logo_PKINK_Tulisan_Jawi_Bawah.png');
$tarikh = bulanTahun();

// ---------------------------------------------------------------- Manual pengguna (HTML)
$folderManual = $akar.'/docs/pakej/manual';
$sumber = (string) file_get_contents($folderManual.'/MANUAL_PENGGUNA.html');
$sementara = $folderManual.'/_cetak_sementara.html';
$pdfManual = $folderPdf.'/MANUAL_PENGGUNA_DMS_PKINK.pdf';

preg_match_all('#<li><a href="\#(b\d+)">([^<]+)</a></li>#', $sumber, $bab, PREG_SET_ORDER);
$halaman = [];
for ($laluan = 1; $laluan <= 3; $laluan++) {
    $html = preg_replace_callback('#<li><a href="\#(b\d+)">([^<]+)</a></li>#', function ($m) use ($halaman) {
        $no = $halaman[$m[1]] ?? '';

        return "<li><a href=\"#{$m[1]}\">{$m[2]}</a><span class=\"hlm\">{$no}</span></li>";
    }, $sumber);
    file_put_contents($sementara, $html);
    cetak($chrome, $sementara, $pdfManual);

    $baharu = cariHalamanBab($pdftotext, $pdfManual, $bab);
    if ($baharu === $halaman) {
        break;
    }
    $halaman = $baharu;
}
@unlink($sementara);
if (count($halaman) !== count($bab)) {
    fwrite(STDERR, 'Amaran: tidak semua bab ditemui dalam PDF: '.json_encode($halaman)."\n");
}
lapor($pdfManual, 'halaman bab '.json_encode($halaman));

// ---------------------------------------------------------------- Dokumen Markdown
$dokumen = [
    ['docs/pakej/PANDUAN_PEMASANGAN.md', $folderPdf.'/PANDUAN_PEMASANGAN.pdf', 'Pentadbir pelayan dan Bahagian Digital', 'Dalaman', ''],
    ['docs/pakej/PANDUAN_UJIAN_KOMPUTER_RIBA.md', $folderPdf.'/PANDUAN_UJIAN_KOMPUTER_RIBA.pdf', 'Pegawai penilai sistem', 'Dalaman', ''],
    ['docs/MANUAL_TEMPLAT_BORANG.md', $folderPdf.'/MANUAL_TEMPLAT_BORANG.pdf', 'Super Admin dan Pentadbir Sistem', 'Dalaman', ''],
    ['docs/PELAN_TINDAK_BALAS_INSIDEN.md', $folderPdf.'/PELAN_TINDAK_BALAS_INSIDEN.pdf', 'Pengurusan, ICTSO dan Pentadbir Sistem', 'Terhad', ''],
];
if (is_file($luar.'/rahsia/AKAUN_UAT.md')) {
    $dokumen[] = [$luar.'/rahsia/AKAUN_UAT.md', $luar.'/rahsia/AKAUN_UAT.pdf', 'Pentadbir Sistem (penyelaras UAT)', 'Sulit', <<<'MD'

## Arahan penggunaan

1. Kata laluan dalam jadual di atas ialah **kata laluan sementara**. Sistem mewajibkan setiap pengguna menukar kata laluan pada log masuk pertama.
2. Akaun **Super Admin** dan **Pentadbir Sistem** wajib mendaftar pengesahan dua faktor (2FA) selepas log masuk pertama. Simpan kod pemulihan yang dipaparkan di tempat selamat.
3. Akaun ini disediakan untuk Ujian Penerimaan Pengguna (UAT) sahaja. Selepas UAT, padam atau nyahaktifkan akaun yang tidak diperlukan melalui **Pengurusan Kakitangan**.
4. Dokumen ini **SULIT**. Jangan dikongsi melalui emel atau aplikasi pesanan, dan musnahkan dokumen ini selepas semua kata laluan sementara ditukar.
MD];
}

$penukar = new GithubFlavoredMarkdownConverter(['html_input' => 'allow', 'allow_unsafe_links' => false]);
foreach ($dokumen as [$md, $pdf, $sasaran, $klasifikasi, $tambahan]) {
    $laluanMd = str_starts_with($md, $luar) ? $md : $akar.'/'.$md;
    $teks = (string) file_get_contents($laluanMd).$tambahan;

    // Tajuk H1 menjadi kulit; baris "Pengguna sasaran:" / "Untuk:" dipaparkan dalam kulit
    preg_match('/^#\s+(.+)$/m', $teks, $h1);
    $tajuk = trim($h1[1] ?? basename($md, '.md'));
    $teks = preg_replace('/^#\s+.+\R/m', '', $teks, 1);
    [$tajukUtama, $subtajuk] = array_pad(array_map('trim', explode(':', $tajuk, 2)), 2, '');

    $badan = $penukar->convert($teks)->getContent();
    $folderSementara = dirname($pdf);
    $sementara = $folderSementara.'/_cetak_'.basename($pdf, '.pdf').'.html';
    $kepala = htmlspecialchars($tajukUtama.($subtajuk !== '' ? ': '.$subtajuk : ''), ENT_QUOTES);
    $sistem = str_contains($tajuk, 'DMS') ? '' : 'Sistem Arkib Digital (DMS) PKINK';

    $html = <<<HTML
<!doctype html>
<html lang="ms">
<head>
<meta charset="utf-8">
<title>{$kepala}</title>
<link rel="stylesheet" href="{$css}">
<style>
  @page { @top-right { content: "{$kepala}"; } }
</style>
</head>
<body class="dok">
<section class="kulit">
  <img src="{$logo}" alt="Logo PKINK" class="kulit-logo">
  <div class="kulit-org">PERBADANAN KEMAJUAN IKTISAD NEGERI KELANTAN</div>
  <h1>{$tajukUtama}</h1>
  <div class="kulit-sistem">{$subtajuk}</div>
  <div class="kulit-sub">{$sistem}</div>
  <table class="kulit-info">
    <tr><td>Versi dokumen</td><td>1.0</td></tr>
    <tr><td>Tarikh</td><td>{$tarikh}</td></tr>
    <tr><td>Disediakan untuk</td><td>{$sasaran}</td></tr>
    <tr><td>Klasifikasi</td><td>{$klasifikasi}</td></tr>
  </table>
</section>
<main class="isi-dok">
{$badan}
</main>
</body>
</html>
HTML;
    file_put_contents($sementara, $html);
    try {
        cetak($chrome, $sementara, $pdf);
    } finally {
        @unlink($sementara);
    }
    lapor($pdf, basename($laluanMd));
}

// ================================================================ fungsi

function cetak(string $chrome, string $html, string $pdf): void
{
    @unlink($pdf);
    $arahan = [$chrome, '--headless=new', '--disable-gpu', '--no-pdf-header-footer', '--allow-file-access-from-files',
        '--print-to-pdf='.$pdf, urlFail($html)];
    exec(implode(' ', array_map('escapeshellarg', $arahan)).' 2>&1', $output, $kod);
    if (! is_file($pdf) || filesize($pdf) === 0) {
        fwrite(STDERR, "Gagal menjana {$pdf} (kod {$kod}):\n".implode("\n", $output)."\n");
        exit(1);
    }
}

/** @return array<string,int> id bab => nombor halaman (bermula dari halaman 3: selepas kulit & kandungan) */
function cariHalamanBab(string $pdftotext, string $pdf, array $bab): array
{
    $halaman = [];
    exec(escapeshellarg($pdftotext).' -layout -enc UTF-8 '.escapeshellarg($pdf).' -', $baris);
    $teks = implode("\n", $baris);
    $muka = explode("\f", $teks);
    foreach ($bab as $i => [, $id, $tajuk]) {
        $tajuk = html_entity_decode($tajuk, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $corak = '/^\s*'.($i + 1).'\.\s+'.preg_quote($tajuk, '/').'\s*$/mu';
        foreach ($muka as $n => $isi) {
            if ($n >= 2 && preg_match($corak, $isi)) {
                $halaman[$id] = $n + 1;
                break;
            }
        }
    }

    return $halaman;
}

function cariChrome(): string
{
    $calon = array_filter([
        getenv('CHROME_BIN') ?: null,
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        '/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser',
    ]);
    foreach ($calon as $c) {
        if (is_file($c)) {
            return $c;
        }
    }
    fwrite(STDERR, "Google Chrome / Microsoft Edge tidak dijumpai. Tetapkan CHROME_BIN.\n");
    exit(1);
}

function cariPdftotext(string $akar): string
{
    $env = (string) @file_get_contents($akar.'/.env');
    if (preg_match('/^OCR_PDFTOPPM_BIN=["\']?([^"\'\r\n]+)/m', $env, $m)) {
        $calon = str_ireplace('pdftoppm', 'pdftotext', trim($m[1]));
        if (is_file($calon)) {
            return $calon;
        }
    }

    return 'pdftotext';
}

function urlFail(string $laluan): string
{
    $laluan = str_replace('\\', '/', realpath($laluan) ?: $laluan);

    $url = 'file:///'.ltrim(implode('/', array_map('rawurlencode', explode('/', $laluan))), '/');

    return preg_replace('#^file:///([A-Za-z])%3A/#', 'file:///$1:/', $url);
}

function bulanTahun(): string
{
    $bulan = ['Januari', 'Februari', 'Mac', 'April', 'Mei', 'Jun', 'Julai', 'Ogos', 'September', 'Oktober', 'November', 'Disember'];

    return $bulan[(int) date('n') - 1].' '.date('Y');
}

function lapor(string $pdf, string $nota): void
{
    printf("%-34s %7.1f KB  %s\n", basename($pdf), filesize($pdf) / 1024, $nota);
}
