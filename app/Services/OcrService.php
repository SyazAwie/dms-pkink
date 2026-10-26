<?php

namespace App\Services;

use App\Support\BuangGarisan;
use Illuminate\Support\Facades\Log;
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
     * Laluan hibrid: Tesseract dahulu bagi setiap muka surat; jika skor keyakinannya di bawah
     * OCR_VLM_AMBANG (cth. tulisan tangan) atau $paksaVlm, muka surat itu dibaca semula oleh
     * model penglihatan (VlmOcrService, GLM-OCR). Jika model penglihatan gagal, teks Tesseract kekal.
     *
     * @return array{teks: string, skor: float|null, enjin: list<string>} enjin bagi setiap muka surat:
     *         'tesseract' atau 'vlm'
     * @throws \RuntimeException jika tesseract/pdftoppm gagal atau tiada dalam PATH
     */
    public function proses(string $laluanRelatifDiskLocal, bool $paksaVlm = false): array
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
            $enjin = [];
            $vlm = app(VlmOcrService::class);
            $ambang = (float) config('services.ocr.vlm_ambang', 80);

            foreach ($failImej as $imej) {
                [$teks, $skor] = $this->ocrTerbaik($imej, $folderSementara);
                $enjinMuka = 'tesseract';

                if ($vlm->aktif() && ($paksaVlm || $skor === null || $skor < $ambang)) {
                    try {
                        $teksVlm = $vlm->baca($imej);
                        if ($teksVlm !== '') {
                            $teks = $teksVlm;
                            $enjinMuka = 'vlm';
                        }
                    } catch (\Throwable $e) {
                        Log::warning('OCR penglihatan gagal, guna teks Tesseract: ' . $e->getMessage());
                    }
                }

                if ($teks !== '') {
                    $semuaTeks[] = $teks;
                }
                if ($skor !== null) {
                    $semuaSkor[] = $skor;
                }
                $enjin[] = $enjinMuka;
            }

            return [
                'teks' => trim(implode("\n\n--- Muka Surat Baharu ---\n\n", $semuaTeks)),
                'skor' => $semuaSkor ? round(array_sum($semuaSkor) / count($semuaSkor), 2) : null,
                'enjin' => $enjin,
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

    /**
     * OCR satu muka surat. Jika OCR_BUANG_GARISAN aktif, Tesseract dijalankan SERENTAK atas
     * imej asal dan imej tanpa garisan kotak/jadual (App\Support\BuangGarisan); hasil dengan
     * skor keyakinan lebih tinggi dipilih, jadi dokumen tanpa kotak tidak menjadi lebih teruk.
     *
     * @return array{0: string, 1: float|null}
     */
    protected function ocrTerbaik(string $laluanImej, string $folderSementara): array
    {
        $asal = $this->mulaTesseract($laluanImej, $folderSementara);
        if (!config('services.ocr.buang_garisan', true)) {
            return $this->selesaiTesseract(...$asal);
        }

        // Tesseract imej asal berjalan semasa garisan dibuang
        $laluanBersih = $folderSementara . DIRECTORY_SEPARATOR . Str::random(8) . '_bersih.png';
        $bersih = BuangGarisan::proses($laluanImej, $laluanBersih)
            ? $this->mulaTesseract($laluanBersih, $folderSementara)
            : null;

        $hasilAsal = $this->selesaiTesseract(...$asal);
        if ($bersih === null) {
            return $hasilAsal;
        }
        $hasilBersih = $this->selesaiTesseract(...$bersih);

        return ($hasilBersih[1] ?? -1) > ($hasilAsal[1] ?? -1) ? $hasilBersih : $hasilAsal;
    }

    /** Mulakan tesseract (tak segerak) atas SATU imej, output TSV (teks + skor keyakinan sekali). */
    private function mulaTesseract(string $laluanImej, string $folderSementara): array
    {
        $awalanOutput = $folderSementara . DIRECTORY_SEPARATOR . Str::random(8);

        $proses = new Process([$this->tesseractBin, $laluanImej, $awalanOutput, '-l', $this->bahasa, 'tsv']);
        $proses->setTimeout(90);
        $proses->start();

        return [$proses, $awalanOutput];
    }

    private function selesaiTesseract(Process $proses, string $awalanOutput): array
    {
        $proses->wait();

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
     * Perkataan dikumpul ikut baris asal (block/par/line), supaya pasangan
     * "Label: nilai" dan baris jadual kekal pada baris sendiri untuk AI.
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
        $idxBaris = array_map(fn ($k) => array_search($k, $header), ['block_num', 'par_num', 'line_num']);

        if ($idxTeks === false) {
            return ['', null];
        }

        $barisTeks = [];
        $skor = [];

        foreach ($baris as $b) {
            $lajur = explode("\t", $b);
            $teks = trim($lajur[$idxTeks] ?? '');
            if ($teks === '') {
                continue;
            }

            $kunci = implode('.', array_map(fn ($i) => $i === false ? '0' : ($lajur[$i] ?? '0'), $idxBaris));
            $barisTeks[$kunci][] = $teks;

            $conf = (float) ($lajur[$idxConf] ?? -1);
            if ($conf >= 0) {
                $skor[] = $conf;
            }
        }

        return [
            implode("\n", array_map(fn ($p) => implode(' ', $p), $barisTeks)),
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