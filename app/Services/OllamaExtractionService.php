<?php

namespace App\Services;

use App\Support\JenisMedan;
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
        // Tajuk seksyen tiada nilai, jadi tidak dihantar kepada AI
        $medan = $medan->filter(fn ($f) => JenisMedan::adaNilai($f))->values();

        if ($medan->isEmpty() || trim($teksOcr) === '') {
            return $medan->mapWithKeys(fn ($f) => [$f->dokumen_field_id => null])->all();
        }

        $badan = [
            'model' => $this->model,
            'prompt' => $this->binaPrompt($teksOcr, $medan),
            'format' => 'json',
            'stream' => false,
            'keep_alive' => config('services.ollama.keep_alive', '30m'),
            'options' => $this->opsyen() + [
                'temperature' => 0.1,
                'num_predict' => (int) config('services.ollama.num_predict', 2048),
            ],
        ];
        $respons = $this->hantar($badan);

        // Model gagal dimuat sepenuhnya ke GPU (cth. VRAM digunakan aplikasi lain):
        // ulang sekali dengan pembahagian GPU/CPU lalai Ollama
        if ($respons->failed() && isset($badan['options']['num_gpu'])) {
            unset($badan['options']['num_gpu']);
            $respons = $this->hantar($badan);
        }

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

    /**
     * Muatkan model ke memori GPU lebih awal (prompt kosong), supaya imbasan pertama tidak
     * menunggu model dimuat. Dipanggil apabila halaman Imbas & Arkib / Imbas & Hantar dibuka.
     * Opsyen mesti sama dengan ekstrak(): num_ctx/num_gpu berbeza menyebabkan Ollama memuat semula.
     */
    public function panaskan(): bool
    {
        $badan = [
            'model' => $this->model,
            'prompt' => '',
            'stream' => false,
            'keep_alive' => config('services.ollama.keep_alive', '30m'),
            'options' => $this->opsyen(),
        ];
        $respons = $this->hantar($badan);
        if ($respons->failed() && isset($badan['options']['num_gpu'])) {
            unset($badan['options']['num_gpu']);
            $respons = $this->hantar($badan);
        }

        return $respons->successful();
    }

    /** True jika model sudah dimuat dalam memori Ollama (semakan pantas, /api/ps). */
    public function sudahDimuat(): bool
    {
        $model = Http::timeout(3)->get("{$this->urlAsas}/api/ps")->json('models') ?? [];

        return collect($model)->contains(fn ($m) => in_array($this->model, [$m['name'] ?? null, $m['model'] ?? null], true)
            || str_starts_with((string) ($m['name'] ?? ''), $this->model . ':'));
    }

    /** Opsyen prestasi Ollama yang menentukan cara model dimuat (config services.ollama). */
    private function opsyen(): array
    {
        $numGpu = config('services.ollama.num_gpu');

        return ['num_ctx' => (int) config('services.ollama.num_ctx', 4096)]
            + ($numGpu !== null && $numGpu !== '' ? ['num_gpu' => (int) $numGpu] : []);
    }

    private function hantar(array $badan): \Illuminate\Http\Client\Response
    {
        return Http::timeout((int) config('services.ollama.had_masa', 180))
            ->post("{$this->urlAsas}/api/generate", $badan);
    }

    private function binaPrompt(string $teksOcr, Collection $medan): string
    {
        // Nama medan + panduan format ikut jenis data (App\Support\JenisMedan)
        $senaraiMedan = $medan
            ->map(fn ($f) => "- kod \"{$f->kod_field}\" ({$f->nama_field}): " . JenisMedan::panduanAi($f))
            ->implode("\n");

        return <<<PROMPT
Anda pembantu mengekstrak data daripada teks hasil OCR sebuah borang rasmi Bahasa Melayu.

Diberi teks OCR dan senarai medan yang diperlukan, PULANGKAN HANYA satu objek JSON
(tiada penjelasan, tiada markdown, tiada teks lain) memetakan setiap KOD medan kepada
nilai yang dijumpai dalam teks. Jika sesuatu medan tidak dijumpai dalam teks, gunakan null.

Peraturan ketat:
- Ikut format yang dinyatakan bagi setiap medan (tarikh YYYY-MM-DD, nombor/wang angka sahaja)
- Medan dengan senarai pilihan: pilih SATU nilai TEPAT seperti dalam senarai; jika tiada
  yang sepadan rapat, guna null — JANGAN cipta nilai baharu kecuali dibenarkan
- Jangan reka/anggar nilai yang tiada bukti jelas dalam teks
- Teks selepas tanda ":" dalam Senarai Medan ialah ARAHAN format, bukan nilai; jangan salin
  arahan itu ke dalam jawapan
- Borang kosong (tiada jawapan ditulis): guna null, jangan salin label borang sebagai nilai

Senarai Medan:
{$senaraiMedan}

Teks OCR:
\"\"\"
{$teksOcr}
\"\"\"

Jawab dengan JSON sahaja, contoh bentuk: {"kod_medan_1": "nilai", "kod_medan_2": null}
PROMPT;
    }

    /**
     * Sahkan setiap nilai ikut jenis_data medan (peraturan sama dengan borang);
     * nilai tak sah jadi null. Nilai dipulangkan dalam bentuk simpanan.
     */
    private function bersihkanNilai(array $data, Collection $medan): array
    {
        return $medan
            ->mapWithKeys(fn ($f) => [$f->dokumen_field_id => JenisMedan::bersihkanAi($f, $data[$f->kod_field] ?? null)])
            ->all();
    }
}