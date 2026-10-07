<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Collection;

/**
 * Ekstrak nilai medan EAV daripada teks OCR, guna model AI TEMPATAN melalui
 * Ollama (http://127.0.0.1:11434) — bukan API berbayar. Reka bentuk ini
 * automatik serasi dengan APA-APA Jenis Dokumen yang admin cipta kemudian,
 * sebab senarai medan dihantar sebagai sebahagian prompt, bukan dikodkan
 * keras (hardcode) per jenis dokumen.
 *
 * PRASYARAT (bukan kod — pasang berasingan, lihat README):
 *   1. Ollama dipasang & servisnya berjalan (ikon system tray)
 *   2. Model ditarik (`ollama pull ...`) — lihat OLLAMA_MODEL dalam .env
 */
class OllamaExtractionService
{
    private string $urlAsas;
    private string $model;

    public function __construct()
    {
        $this->urlAsas = rtrim(config('services.ollama.url', 'http://127.0.0.1:11434'), '/');
        $this->model = config('services.ollama.model', 'aisingapore/llama-sea-lion-v3.5-8b-r');
    }

    /**
     * @param string $teksOcr Teks mentah hasil OcrService::proses()
     * @param Collection<\App\Models\DokumenField> $medan
     * @return array<int, mixed> dokumen_field_id => nilai (null jika tak dijumpai/tak sah)
     *
     * @throws \RuntimeException jika Ollama tidak dapat dihubungi atau pulangkan JSON tak sah
     */
    public function ekstrak(string $teksOcr, Collection $medan): array
    {
        if ($medan->isEmpty() || trim($teksOcr) === '') {
            return $medan->mapWithKeys(fn ($f) => [$f->dokumen_field_id => null])->all();
        }

        $respons = Http::timeout(120)->post("{$this->urlAsas}/api/generate", [
            'model' => $this->model,
            'prompt' => $this->binaPrompt($teksOcr, $medan),
            'format' => 'json',
            'stream' => false,
            'options' => ['temperature' => 0.1],
        ]);

        if ($respons->failed()) {
            throw new \RuntimeException(
                "Ollama tidak dapat dihubungi (pastikan servis Ollama berjalan): HTTP {$respons->status()} — " . $respons->body()
            );
        }

        $teksJson = $respons->json('response');
        $data = json_decode((string) $teksJson, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Model AI tidak pulangkan JSON yang sah: ' . substr((string) $teksJson, 0, 300));
        }

        return $this->bersihkanNilai($data, $medan);
    }

    private function binaPrompt(string $teksOcr, Collection $medan): string
    {
        $senaraiMedan = $medan->map(function ($f) {
            $baris = "- kod \"{$f->kod_field}\" ({$f->jenis_data})";
            if ($f->jenis_data === 'dropdown' && $f->pilihan) {
                $baris .= ': pilihan = [' . implode(', ', $f->pilihan) . ']';
            }
            return $baris;
        })->implode("\n");

        return <<<PROMPT
Anda pembantu mengekstrak data daripada teks hasil OCR sebuah borang rasmi Bahasa Melayu.

Diberi teks OCR dan senarai medan yang diperlukan, PULANGKAN HANYA satu objek JSON
(tiada penjelasan, tiada markdown, tiada teks lain) memetakan setiap KOD medan kepada
nilai yang dijumpai dalam teks. Jika sesuatu medan tidak dijumpai dalam teks, gunakan null.

Peraturan ketat:
- Medan jenis "tarikh": format WAJIB YYYY-MM-DD
- Medan jenis "nombor": angka sahaja, tanpa simbol mata wang/ruang/koma
- Medan jenis "dropdown": pilih SATU nilai TEPAT seperti dalam senarai pilihan diberi;
  jika tiada yang sepadan rapat, guna null — JANGAN cipta nilai baharu
- Jangan reka/anggar nilai yang tiada bukti jelas dalam teks

Senarai Medan:
{$senaraiMedan}

Teks OCR:
\"\"\"
{$teksOcr}
\"\"\"

Jawab dengan JSON sahaja, contoh bentuk: {"kod_medan_1": "nilai", "kod_medan_2": null}
PROMPT;
    }

    /** Sahkan setiap nilai ikut jenis_data medan; buang nilai tak sah jadi null. */
    private function bersihkanNilai(array $data, Collection $medan): array
    {
        $bersih = [];

        foreach ($medan as $f) {
            $nilai = $data[$f->kod_field] ?? null;

            if ($nilai === null || $nilai === '') {
                $bersih[$f->dokumen_field_id] = null;
                continue;
            }

            $bersih[$f->dokumen_field_id] = match ($f->jenis_data) {
                'nombor' => $this->sahkanNombor($nilai),
                'tarikh' => $this->sahkanTarikh($nilai),
                'dropdown' => in_array((string) $nilai, $f->pilihan ?? [], true) ? (string) $nilai : null,
                default => (string) $nilai,
            };
        }

        return $bersih;
    }

    private function sahkanNombor($nilai): ?float
    {
        $bersih = str_replace([',', ' ', 'RM'], '', (string) $nilai);
        return is_numeric($bersih) ? (float) $bersih : null;
    }

    private function sahkanTarikh($nilai): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $nilai) ? (string) $nilai : null;
    }
}