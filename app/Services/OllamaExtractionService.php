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

    /** Kunci medan asas dokumen (bukan EAV) dalam hasil ekstrak() bila $medanAsas = true. */
    public const TARIKH_DOKUMEN = '_tarikh_dokumen';
    public const PERKARA = '_perkara';

    /**
     * @param string $teksOcr Teks mentah hasil OcrService::proses()
     * @param Collection<\App\Models\DokumenField> $medan
     * @param bool $medanAsas true = turut ekstrak Tarikh Dokumen & Perkara (kunci TARIKH_DOKUMEN/PERKARA)
     * @return array<int|string, mixed> dokumen_field_id => nilai (null jika tak dijumpai/tak sah)
     *
     * @throws \RuntimeException jika Ollama tidak dapat dihubungi atau pulangkan JSON tak sah
     */
    public function ekstrak(string $teksOcr, Collection $medan, bool $medanAsas = false): array
    {
        // Tajuk seksyen tiada nilai, jadi tidak dihantar kepada AI
        $medan = $medan->filter(fn ($f) => JenisMedan::adaNilai($f))->values();

        if (($medan->isEmpty() && !$medanAsas) || trim($teksOcr) === '') {
            return $medan->mapWithKeys(fn ($f) => [$f->dokumen_field_id => null])->all()
                + ($medanAsas ? [self::TARIKH_DOKUMEN => null, self::PERKARA => null] : []);
        }

        $badan = [
            'model' => $this->model,
            'prompt' => $this->binaPrompt($teksOcr, $medan, $medanAsas),
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

        return $this->bersihkanNilai($data, $medan)
            + ($medanAsas ? $this->bersihkanAsas($data) : []);
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

    private function binaPrompt(string $teksOcr, Collection $medan, bool $medanAsas = false): string
    {
        // Nama medan + panduan format ikut jenis data (App\Support\JenisMedan)
        $senaraiMedan = $medan
            ->map(fn ($f) => "- kod \"{$f->kod_field}\" ({$f->nama_field}): " . JenisMedan::panduanAi($f))
            ->when($medanAsas, fn ($s) => $s->push(
                '- kod "' . self::TARIKH_DOKUMEN . '" (Tarikh Dokumen): tarikh dokumen itu sendiri (tarikh surat, memo, borang atau mesyuarat), format YYYY-MM-DD',
                '- kod "' . self::PERKARA . '" (Perkara): tajuk utama dokumen seperti tertulis (perkara surat/memo, tajuk penuh minit mesyuarat, atau nama borang), maksimum 200 aksara'
            ))
            ->implode("\n");

        // JSON padat (satu baris) mengurangkan token jawapan, jadi jawapan lebih pantas
        return <<<PROMPT
Anda pembantu mengekstrak data daripada teks hasil OCR sebuah dokumen rasmi Bahasa Melayu
(borang, memo, surat atau minit mesyuarat).

Diberi teks OCR dan senarai medan, PULANGKAN HANYA satu objek JSON (tiada penjelasan,
tiada markdown) memetakan setiap KOD medan kepada nilai yang DIISI dalam dokumen.
Jika tiada, guna null.

Peraturan ketat:
- Ambil hanya nilai yang diisi/ditulis bagi medan itu, BUKAN teks bercetak borang: label,
  arahan, perakuan/syarat, tajuk seksyen atau nota kaki (cth. "Disediakan oleh ...", nama fail .doc)
- Jangan reka, teka atau simpulkan nilai yang tidak tertulis dalam dokumen; guna null
- Ikut format setiap medan (tarikh YYYY-MM-DD; nombor/wang angka sahaja)
- Medan dengan senarai pilihan: pilih SATU nilai TEPAT dari senarai hanya jika dokumen
  menunjukkan pilihan itu (ditanda, ditulis, atau pilihan lain dipotong); jika tidak, null
- Huruf dalam kotak mungkin dipisahkan ruang atau "|" (cth. "S Y A Z M I"): cantumkan
- Jadual: satu objek bagi setiap baris yang diisi sahaja; abaikan baris kosong
- Teks selepas tanda ":" dalam Senarai Medan ialah ARAHAN format, bukan nilai

Senarai Medan:
{$senaraiMedan}

Teks OCR:
\"\"\"
{$teksOcr}
\"\"\"

Jawab dengan JSON padat dalam SATU baris (tanpa baris baharu atau ruang tambahan),
contoh bentuk: {"kod_medan_1":"nilai","kod_medan_2":null}
PROMPT;
    }

    /** Tarikh Dokumen (tarikh sah, tahun munasabah) & Perkara (teks, maks 255) daripada AI. */
    private function bersihkanAsas(array $data): array
    {
        $tarikh = $data[self::TARIKH_DOKUMEN] ?? null;
        $sah = is_string($tarikh)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarikh)
            && checkdate((int) substr($tarikh, 5, 2), (int) substr($tarikh, 8, 2), (int) substr($tarikh, 0, 4))
            && (int) substr($tarikh, 0, 4) >= 1950 && (int) substr($tarikh, 0, 4) <= (int) date('Y') + 1;

        $perkara = $data[self::PERKARA] ?? null;
        $perkara = is_scalar($perkara) ? trim(preg_replace('/\s+/u', ' ', (string) $perkara)) : '';

        return [
            self::TARIKH_DOKUMEN => $sah ? $tarikh : null,
            self::PERKARA => $perkara !== '' && mb_strtolower($perkara) !== 'perkara' ? mb_substr($perkara, 0, 255) : null,
        ];
    }

    /**
     * Sahkan setiap nilai ikut jenis_data medan (peraturan sama dengan borang);
     * nilai tak sah jadi null. Nilai dipulangkan dalam bentuk simpanan.
     */
    private function bersihkanNilai(array $data, Collection $medan): array
    {
        // Label borang yang disalin sebagai nilai (cth. "Jabatan/ Bahagian" bagi medan nama) dibuang
        $label = $medan->map(fn ($f) => self::normalLabel($f->nama_field))->filter()->flip();

        return $medan
            ->mapWithKeys(function ($f) use ($data, $label) {
                $nilai = $data[$f->kod_field] ?? null;
                if (is_string($nilai) && $label->has(self::normalLabel($nilai))) {
                    $nilai = null;
                }

                return [$f->dokumen_field_id => JenisMedan::bersihkanAi($f, $nilai)];
            })
            ->all();
    }

    /** Bentuk perbandingan label: huruf kecil, tanpa ruang/tanda baca (cth. "Jabatan/ Bahagian:" → "jabatanbahagian"). */
    private static function normalLabel(string $teks): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($teks));
    }
}