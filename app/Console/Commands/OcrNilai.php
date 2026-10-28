<?php

namespace App\Console\Commands;

use App\Models\JenisDokumen;
use App\Models\PembetulanAi;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Nilai ketepatan OCR + AI terhadap set dokumen berlabel (P15), untuk angka pembentangan
 * dan untuk mengukur sebelum/selepas setiap perubahan OCR/AI (README 5.11).
 *
 * Setiap dokumen X.pdf/.jpg/.png dalam folder mempunyai label X.label.json:
 *   {"kategori": "BORANG-ICT", "disahkan": true,
 *    "tarikh_dokumen": "2026-10-07", "perkara": "...",
 *    "medan": {"kod_field": "nilai betul", "kod_lain": null}}
 * Hanya label "disahkan": true dinilai (draf yang belum disemak manusia akan mengelirukan).
 *
 * --jana-label=KOD: jana draf label daripada hasil AI semasa bagi fail tanpa label, untuk
 * disemak & dibetulkan manusia (tukar "disahkan" kepada true). Arahan ini membaca DB sahaja.
 */
class OcrNilai extends Command
{
    protected $signature = 'ocr:nilai
        {folder? : Folder dokumen berlabel (lalai: ../Ujian_Borang_Diisi)}
        {--jana-label= : Kod kategori; jana draf label bagi fail yang belum berlabel}
        {--tanpa-few-shot : Matikan contoh pembetulan staf (P16) dalam prompt}
        {--simpan= : Tulis laporan penuh (JSON) ke fail ini}';

    protected $description = 'Ukur ketepatan OCR + AI setiap medan terhadap dokumen berlabel';

    private const SAMBUNGAN = ['pdf', 'jpg', 'jpeg', 'png'];

    public function handle(): int
    {
        $folder = $this->argument('folder') ?? base_path('../Ujian_Borang_Diisi');
        if (! File::isDirectory($folder)) {
            $this->error("Folder tidak dijumpai: {$folder}");

            return self::FAILURE;
        }
        if ($this->option('tanpa-few-shot')) {
            config(['services.ollama.few_shot' => false]);
        }
        set_time_limit(0);

        $fail = collect(File::files($folder))->filter(fn ($f) => in_array(strtolower($f->getExtension()), self::SAMBUNGAN, true))
            ->sortBy(fn ($f) => $f->getFilename(), SORT_NATURAL)->values();
        $laporan = [];
        $baris = [];

        foreach ($fail as $f) {
            $laluanLabel = preg_replace('/\.[^.]+$/', '.label.json', $f->getPathname());
            // BOM UTF-8 dibuang: Notepad/PowerShell 5.1 menambahnya dan json_decode menolaknya
            $label = File::exists($laluanLabel) ? json_decode(preg_replace('/^\xEF\xBB\xBF/', '', File::get($laluanLabel)), true) : null;
            if (File::exists($laluanLabel) && ! is_array($label)) {
                $this->error('Label rosak (JSON tidak sah): '.basename($laluanLabel));

                continue;
            }

            if ($label === null) {
                if ($kod = $this->option('jana-label')) {
                    $this->janaLabel($f->getPathname(), $laluanLabel, $kod);
                } else {
                    $this->line("<comment>Tiada label:</comment> {$f->getFilename()} (guna --jana-label=KOD untuk draf)");
                }

                continue;
            }
            if (empty($label['disahkan'])) {
                $this->line("<comment>Label belum disahkan, dilangkau:</comment> {$f->getFilename()}");

                continue;
            }

            $hasil = $this->nilai($f->getPathname(), $label);
            if ($hasil === null) {
                continue;
            }
            $laporan[] = ['fail' => $f->getFilename()] + $hasil;
            $baris[] = [$f->getFilename(), $label['kategori'], "{$hasil['betul']}/{$hasil['jumlah']}",
                $hasil['jumlah'] ? round($hasil['betul'] / $hasil['jumlah'] * 100).'%' : '-',
                $hasil['skor_ocr'] !== null ? $hasil['skor_ocr'].'%' : '-', "{$hasil['masa_ocr']} s", "{$hasil['masa_ai']} s"];
        }

        if ($laporan === []) {
            $this->warn('Tiada dokumen berlabel yang disahkan untuk dinilai.');

            return self::SUCCESS;
        }

        $this->table(['Fail', 'Kategori', 'Medan betul', 'Ketepatan', 'Skor OCR', 'Masa OCR', 'Masa AI'], $baris);
        $betul = array_sum(array_column($laporan, 'betul'));
        $jumlah = array_sum(array_column($laporan, 'jumlah'));
        $this->info(sprintf('Keseluruhan: %d/%d medan betul (%s%%), %d dokumen, purata AI %.1f s.',
            $betul, $jumlah, $jumlah ? round($betul / $jumlah * 100, 1) : 0, count($laporan),
            array_sum(array_column($laporan, 'masa_ai')) / count($laporan)));

        $salah = collect($laporan)->flatMap(fn ($l) => collect($l['medan'])->reject(fn ($m) => $m['betul'])
            ->map(fn ($m, $kod) => [$l['fail'], $kod, Str::limit((string) $m['dijangka'], 40), Str::limit((string) $m['ai'], 40)]))->values();
        if ($salah->isNotEmpty()) {
            $this->newLine();
            $this->table(['Fail', 'Medan salah', 'Dijangka', 'AI'], $salah->all());
        }

        if ($simpan = $this->option('simpan')) {
            File::put($simpan, json_encode(['masa' => now()->toIso8601String(), 'model' => config('services.ollama.model'),
                'few_shot' => (bool) config('services.ollama.few_shot'), 'dokumen' => $laporan], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Laporan disimpan: {$simpan}");
        }

        return self::SUCCESS;
    }

    /** @return array{jumlah: int, betul: int, skor_ocr: float|null, masa_ocr: float, masa_ai: float, medan: array}|null */
    private function nilai(string $laluan, array $label): ?array
    {
        $jenis = $this->jenis($label['kategori'] ?? '');
        if (! $jenis) {
            return null;
        }

        [$ocr, $ekstrak, $masaOcr, $masaAi] = $this->jalankan($laluan, $jenis);
        $medan = $jenis->fields->keyBy('kod_field');
        $keputusan = [];

        foreach ($label['medan'] ?? [] as $kod => $dijangka) {
            if (! $medan->has($kod)) {
                $this->line("<comment>Medan tiada dalam kategori, diabaikan:</comment> {$kod}");

                continue;
            }
            $ai = $ekstrak[$medan[$kod]->dokumen_field_id] ?? null;
            $keputusan[$kod] = ['dijangka' => $this->teks($dijangka), 'ai' => $ai, 'betul' => $this->sama($dijangka, $ai)];
        }
        foreach (['tarikh_dokumen' => OllamaExtractionService::TARIKH_DOKUMEN, 'perkara' => OllamaExtractionService::PERKARA] as $kunci => $kodAi) {
            if (array_key_exists($kunci, $label)) {
                $ai = $ekstrak[$kodAi] ?? null;
                $keputusan["_{$kunci}"] = ['dijangka' => $label[$kunci], 'ai' => $ai, 'betul' => $this->sama($label[$kunci], $ai)];
            }
        }

        return [
            'jumlah' => count($keputusan),
            'betul' => count(array_filter($keputusan, fn ($k) => $k['betul'])),
            'skor_ocr' => $ocr['skor'] ?? null,
            'masa_ocr' => $masaOcr,
            'masa_ai' => $masaAi,
            'medan' => $keputusan,
        ];
    }

    private function janaLabel(string $laluan, string $laluanLabel, string $kod): void
    {
        $jenis = $this->jenis($kod);
        if (! $jenis) {
            return;
        }

        [, $ekstrak] = $this->jalankan($laluan, $jenis);
        $medan = $jenis->fields->filter(fn ($f) => array_key_exists($f->dokumen_field_id, $ekstrak))
            ->mapWithKeys(fn ($f) => [$f->kod_field => $ekstrak[$f->dokumen_field_id]]);

        File::put($laluanLabel, json_encode([
            'kategori' => $jenis->kod_dokumen,
            'disahkan' => false,
            'tarikh_dokumen' => $ekstrak[OllamaExtractionService::TARIKH_DOKUMEN] ?? null,
            'perkara' => $ekstrak[OllamaExtractionService::PERKARA] ?? null,
            'medan' => $medan,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info('Draf label dijana: '.basename($laluanLabel).' (semak setiap nilai, kemudian tetapkan "disahkan": true)');
    }

    /** OCR + AI bagi satu fail (disalin sementara ke disk local kerana OcrService membaca dari situ). */
    private function jalankan(string $laluan, JenisDokumen $jenis): array
    {
        $sementara = 'ocr_sementara/nilai_'.Str::random(16).'.'.strtolower(pathinfo($laluan, PATHINFO_EXTENSION));
        Storage::disk('local')->put($sementara, File::get($laluan));

        try {
            $mula = microtime(true);
            $ocr = app(OcrService::class)->proses($sementara);
            $masaOcr = round(microtime(true) - $mula, 1);

            $mula = microtime(true);
            $ekstrak = $ocr['teks'] === '' ? [] : app(OllamaExtractionService::class)->ekstrak($ocr['teks'], $jenis->fields, true);
            $masaAi = round(microtime(true) - $mula, 1);
        } finally {
            Storage::disk('local')->delete($sementara);
        }

        return [$ocr, $ekstrak, $masaOcr, $masaAi];
    }

    private function jenis(string $kod): ?JenisDokumen
    {
        $jenis = JenisDokumen::with('fields')->where('kod_dokumen', $kod)->first();
        if (! $jenis) {
            $this->error("Kategori tidak dijumpai: {$kod}");
        }

        return $jenis;
    }

    /** Label jadual boleh ditulis sebagai tatasusunan JSON; bandingkan dalam bentuk simpanan. */
    private function teks(mixed $nilai): ?string
    {
        return is_array($nilai) ? json_encode($nilai, JSON_UNESCAPED_UNICODE) : ($nilai === null ? null : (string) $nilai);
    }

    private function sama(mixed $dijangka, ?string $ai): bool
    {
        $normal = function ($v) {
            $v = $this->teks($v);
            $json = $v !== null ? json_decode($v, true) : null;

            return is_array($json)
                ? PembetulanAi::normal(json_encode(array_map(fn ($b) => is_array($b) ? array_map(fn ($x) => PembetulanAi::normal((string) $x), $b) : $b, $json), JSON_UNESCAPED_UNICODE))
                : PembetulanAi::normal($v);
        };

        return $normal($dijangka) === $normal($ai);
    }
}
