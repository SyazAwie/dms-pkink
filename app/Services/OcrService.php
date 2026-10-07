<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Bungkusan OCR guna Tesseract (tempatan) + Poppler (pdftoppm, untuk baca PDF).
 * Kedua-dua mesti dipasang berasingan pada mesin ini — lihat README.md >
 * "OCR (Pengecaman Teks)".
 *
 * PENTING (Windows/Herd): guna LALUAN PENUH ke tesseract.exe/pdftoppm.exe
 * (ditetapkan dalam .env), BUKAN bergantung pada System PATH. Proses
 * php-fpm/nginx yang dijalankan Herd kerap tidak nampak kemaskini PATH
 * sistem sehingga Herd dimulakan semula sepenuhnya — laluan penuh
 * mengelakkan isu ini terus.
 *
 * Reka bentuk sengaja mudah: panggil terus CLI tesseract/pdftoppm guna
 * Symfony Process, baca output TSV untuk dapatkan teks SEKALI GUS skor
 * keyakinan purata (lajur `conf`), tanpa perlukan pustaka Composer tambahan.
 */
class OcrService
{
    /** Kod bahasa Tesseract. msa = Bahasa Melayu. Gabung dengan eng sebab
     *  dokumen rasmi kerap bercampur istilah Inggeris. */
    private string $bahasa;

    /** Laluan PENUH ke tesseract.exe / pdftoppm.exe. Tetapkan dalam .env:
     *  OCR_TESSERACT_BIN="C:\\Program Files\\Tesseract-OCR\\tesseract.exe"
     *  OCR_PDFTOPPM_BIN="C:\\poppler\\Library\\bin\\pdftoppm.exe"
     *  Jika tidak ditetapkan, jatuh balik kepada nama arahan ringkas
     *  (bergantung System PATH — tidak disyorkan pada Windows/Herd). */
    private string $tesseractBin;
    private string $pdftoppmBin;

    public function __construct()
    {
        $this->bahasa = config('services.ocr.bahasa', 'msa+eng');
        $this->tesseractBin = config('services.ocr.tesseract_bin', 'tesseract');
        $this->pdftoppmBin = config('services.ocr.pdftoppm_bin', 'pdftoppm');
    }

    /**
     * @return array{teks: string, skor: float|null}
     * @throws \RuntimeException jika tesseract/pdftoppm gagal atau tiada dalam PATH
     */
    public function proses(string $laluanRelatifDiskLocal): array
    {
        $laluanPenuh = Storage::disk('local')->path($laluanRelatifDiskLocal);

        if (!is_file($laluanPenuh)) {
            throw new \RuntimeException("Fail sumber tidak wujud: {$laluanRelatifDiskLocal}");
        }

        $sambungan = strtolower(pathinfo($laluanPenuh, PATHINFO_EXTENSION));
        $folderSementara = sys_get_temp_dir() . '/dms_ocr_' . Str::random(10);
        mkdir($folderSementara, 0777, true);

        try {
            $failImej = $sambungan === 'pdf'
                ? $this->pdfKeImej($laluanPenuh, $folderSementara)
                : [$laluanPenuh];

            $semuaTeks = [];
            $semuaSkor = [];

            foreach ($failImej as $imej) {
                [$teks, $skor] = $this->ocrSatuImej($imej, $folderSementara);

                if ($teks !== '') {
                    $semuaTeks[] = $teks;
                }
                if ($skor !== null) {
                    $semuaSkor[] = $skor;
                }
            }

            return [
                'teks' => trim(implode("\n\n--- Muka Surat Baharu ---\n\n", $semuaTeks)),
                'skor' => $semuaSkor ? round(array_sum($semuaSkor) / count($semuaSkor), 2) : null,
            ];
        } finally {
            $this->bersihkanFolder($folderSementara);
        }
    }

    /** Tukar setiap muka surat PDF kepada PNG (300dpi) guna pdftoppm (Poppler). */
    private function pdfKeImej(string $laluanPdf, string $folderSementara): array
    {
        $awalan = $folderSementara . DIRECTORY_SEPARATOR . 'muka';

        $proses = new Process([$this->pdftoppmBin, '-png', '-r', '300', $laluanPdf, $awalan]);
        $proses->setTimeout(120);
        $proses->run();

        if (!$proses->isSuccessful()) {
            throw new \RuntimeException(
                "pdftoppm gagal (semak OCR_PDFTOPPM_BIN dalam .env — laluan semasa: {$this->pdftoppmBin}): " . $proses->getErrorOutput()
            );
        }

        $failImej = glob($awalan . '*.png');
        sort($failImej);

        if (empty($failImej)) {
            throw new \RuntimeException('pdftoppm tidak menghasilkan sebarang imej daripada PDF ini.');
        }

        return $failImej;
    }

    /** Jalankan tesseract atas SATU imej, output format TSV (teks + skor keyakinan sekali). */
    private function ocrSatuImej(string $laluanImej, string $folderSementara): array
    {
        $awalanOutput = $folderSementara . DIRECTORY_SEPARATOR . Str::random(8);

        $proses = new Process([$this->tesseractBin, $laluanImej, $awalanOutput, '-l', $this->bahasa, 'tsv']);
        $proses->setTimeout(90);
        $proses->run();

        if (!$proses->isSuccessful()) {
            throw new \RuntimeException(
                "tesseract gagal (semak OCR_TESSERACT_BIN dalam .env — laluan semasa: {$this->tesseractBin}): " . $proses->getErrorOutput()
            );
        }

        $laluanTsv = $awalanOutput . '.tsv';
        if (!is_file($laluanTsv)) {
            return ['', null];
        }

        return $this->uraiTsv($laluanTsv);
    }

    /**
     * Urai output TSV Tesseract: lajur 'text' (perkataan demi perkataan) dan
     * 'conf' (keyakinan 0-100, -1 bermakna bukan teks/garisan struktur).
     */
    private function uraiTsv(string $laluanTsv): array
    {
        $baris = file($laluanTsv, FILE_IGNORE_NEW_LINES);
        if (!$baris) {
            return ['', null];
        }

        $header = explode("\t", array_shift($baris));
        $idxTeks = array_search('text', $header);
        $idxConf = array_search('conf', $header);

        if ($idxTeks === false) {
            return ['', null];
        }

        $perkataan = [];
        $skor = [];

        foreach ($baris as $b) {
            $lajur = explode("\t", $b);
            $teks = trim($lajur[$idxTeks] ?? '');
            if ($teks === '') {
                continue;
            }

            $perkataan[] = $teks;

            $conf = (float) ($lajur[$idxConf] ?? -1);
            if ($conf >= 0) {
                $skor[] = $conf;
            }
        }

        return [
            implode(' ', $perkataan),
            $skor ? round(array_sum($skor) / count($skor), 2) : null,
        ];
    }

    private function bersihkanFolder(string $folder): void
    {
        foreach (glob($folder . DIRECTORY_SEPARATOR . '*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($folder);
    }
}