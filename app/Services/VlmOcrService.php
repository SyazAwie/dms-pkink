<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Baca teks daripada imej menggunakan model penglihatan (VLM) TEMPATAN melalui Ollama,
 * lalai GLM-OCR (0.9B, lesen MIT). Digunakan oleh OcrService sebagai "naik taraf" bagi
 * muka surat yang Tesseract baca dengan keyakinan rendah, terutamanya TULISAN TANGAN
 * (ujian 2026-10-26: borang tulisan tangan yang Tesseract baca sebagai sampah dibaca
 * hampir sepenuhnya betul). Tesseract kekal enjin utama kerana lebih baik untuk huruf
 * dalam kotak, kepala surat berwarna dan lebih pantas.
 *
 * GLM-OCR tidak memberi skor keyakinan; skor Tesseract dikekalkan sebagai penunjuk.
 */
class VlmOcrService
{
    /** Lebar maksimum imej dihantar (px): ~A4 150dpi; imej lebih besar menambah token & masa tanpa faedah. */
    private const LEBAR_MAKS = 1240;

    public function aktif(): bool
    {
        return (bool) config('services.ocr.vlm_aktif', true) && (string) config('services.ocr.vlm_model') !== '';
    }

    /**
     * @return string teks yang dibaca (kosong jika model tidak memulangkan teks)
     * @throws \RuntimeException jika Ollama gagal
     */
    public function baca(string $laluanImej): string
    {
        $badan = [
            'model' => config('services.ocr.vlm_model', 'glm-ocr:q8_0'),
            'prompt' => 'Text Recognition:',
            'images' => [base64_encode($this->imejDikecilkan($laluanImej))],
            'stream' => false,
            'keep_alive' => config('services.ollama.keep_alive', '30m'),
            'options' => ['temperature' => 0, 'num_ctx' => 8192, 'num_predict' => 2048]
                + ($this->numGpu() !== null ? ['num_gpu' => $this->numGpu()] : []),
        ];
        $respons = Http::timeout((int) config('services.ollama.had_masa', 180))
            ->post(rtrim(config('services.ollama.url'), '/') . '/api/generate', $badan);

        if ($respons->failed()) {
            throw new \RuntimeException('Model penglihatan (' . $badan['model'] . ') gagal: HTTP ' . $respons->status());
        }

        return trim((string) $respons->json('response'));
    }

    private function numGpu(): ?int
    {
        $n = config('services.ollama.num_gpu');

        return $n === null || $n === '' ? null : (int) $n;
    }

    /** Kecilkan imej besar (cth. PDF 300dpi atau gambar telefon) kepada LEBAR_MAKS, format PNG. */
    private function imejDikecilkan(string $laluanImej): string
    {
        $kandungan = (string) file_get_contents($laluanImej);
        $saiz = @getimagesize($laluanImej);
        if ($saiz === false || $saiz[0] <= self::LEBAR_MAKS) {
            return $kandungan;
        }

        $im = @imagecreatefromstring($kandungan);
        if ($im === false) {
            return $kandungan;
        }
        $kecil = imagescale($im, self::LEBAR_MAKS);
        imagedestroy($im);
        ob_start();
        imagepng($kecil, null, 1);
        imagedestroy($kecil);

        return (string) ob_get_clean();
    }
}
