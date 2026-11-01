<?php

namespace App\Services;

use App\Models\JenisDokumen;
use App\Support\AntrianOcr;
use App\Support\JenisMedan;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Analisis OCR + AI bagi token Imbas & Arkib / Imbas & Hantar (ocr_sementara/{token}/).
 * Dijalankan oleh AnalisisImbasJob (worker queue OCR, T2) atau segerak dalam permintaan
 * bila worker tidak berjalan.
 *
 * meta.json `status`: menunggu → diproses → siap (meta lama tanpa status = siap).
 */
class AnalisisImbasService
{
    public const FOLDER = 'ocr_sementara';

    public const KUNCI_ANALISIS = 'ocr-ai:analisis';

    public const MENUNGGU = 'menunggu';

    public const DIPROSES = 'diproses';

    public const SIAP = 'siap';

    public static function folder(string $token): string
    {
        return self::FOLDER.'/'.$token;
    }

    /** meta.json tanpa semakan pemilik (worker), atau null jika tiada/rosak. */
    public static function bacaMeta(string $token): ?array
    {
        $kandungan = Storage::disk('local')->get(self::folder($token).'/meta.json');
        $meta = $kandungan === null ? null : json_decode($kandungan, true);

        return is_array($meta) ? $meta : null;
    }

    public static function tulisMeta(string $token, array $meta): void
    {
        Storage::disk('local')->put(self::folder($token).'/meta.json',
            json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
    }

    public static function status(array $meta): string
    {
        return $meta['status'] ?? self::SIAP;
    }

    /**
     * Token sedang dianalisis oleh proses yang masih hidup: fail `proses.kunci` wujud, belum
     * melebihi had satu kerja, dan proses pemiliknya (PID dalam fail) masih berjalan. Kunci
     * daripada worker yang dibunuh di tengah kerja tidak dikira, jadi token boleh disambung segera.
     */
    public static function sedangDituntut(string $token): bool
    {
        $laluan = Storage::disk('local')->path(self::folder($token).'/proses.kunci');
        $masa = @filemtime($laluan);
        if ($masa === false || time() - $masa > AntrianOcr::hadKerja()) {
            return false;
        }
        $pid = (int) trim((string) @file_get_contents($laluan));

        return $pid <= 0 || $pid === getmypid() || AntrianOcr::prosesWujud($pid);
    }

    /**
     * Medan yang diisi dalam mod ini. Imbas & Hantar: pemohon tidak mengisi seksyen
     * pejabat (pengesahan/sokongan/urusan BD), jadi medan itu tidak dihantar kepada AI
     * (elak nilai rekaan), tidak dipaparkan dan tidak disimpan.
     */
    public static function medanUntukMod(JenisDokumen $jenisDokumen, bool $modHantar): Collection
    {
        if (! $modHantar) {
            return $jenisDokumen->fields;
        }
        $idPejabat = JenisMedan::idMedanPejabat($jenisDokumen->fields);

        return $jenisDokumen->fields->reject(fn ($f) => in_array($f->dokumen_field_id, $idPejabat, true))->values();
    }

    /**
     * Jalankan analisis bagi token dan tulis hasil ke meta.json (status siap).
     *
     * Hanya SATU proses boleh menganalisis satu token: tuntutan atomik melalui fail
     * `proses.kunci` (fopen 'x', mengandungi PID pemilik) dalam folder token, kemudian status
     * disemak semula. Worker hanya mengambil token `menunggu`; laluan segerak juga mengambil
     * token `diproses` (selepas mendapat kunci, pemprosesnya yang lalu pasti telah mati).
     * Token yang sudah disimpan/dipadam/siap diabaikan.
     *
     * @param  bool  $segerak  Dalam permintaan web (worker tidak berjalan): satu analisis pada
     *                         satu masa melalui kunci cache, dan had masa PHP dilanjutkan.
     */
    public function jalankanToken(string $token, bool $segerak = false): void
    {
        $kunci = $this->tuntut($token);
        if ($kunci === null) {
            return; // proses lain sedang menganalisis token ini
        }

        try {
            $meta = self::bacaMeta($token);
            $bolehAmbil = $meta !== null && (self::status($meta) === self::MENUNGGU
                || ($segerak && self::status($meta) === self::DIPROSES));
            if (! $bolehAmbil) {
                return;
            }
            $jenisDokumen = JenisDokumen::with('fields')->find($meta['jenis_dokumen_id'] ?? null);
            if (! $jenisDokumen) {
                self::tulisMeta($token, array_merge(['teks_ocr' => '', 'skor_ocr' => null, 'nilai_ekstrak' => []], $meta, [
                    'status' => self::SIAP, 'mesej_amaran' => 'Kategori dokumen tidak lagi wujud. Sila mula semula.']));

                return;
            }

            $meta['status'] = self::DIPROSES;
            $meta['mula_proses'] = now()->toIso8601String();
            self::tulisMeta($token, $meta);

            $hasil = $this->analisis(self::folder($token).'/'.$meta['fail'], $jenisDokumen,
                ($meta['mod'] ?? 'arkib') === 'hantar', (bool) ($meta['paksa_vlm'] ?? false), $segerak);

            // Token mungkin telah dipadam (Mula Semula / tamat tempoh) semasa analisis berjalan:
            // jangan hidupkan semula
            if (! Storage::disk('local')->exists(self::folder($token).'/meta.json')) {
                return;
            }
            unset($meta['paksa_vlm'], $meta['mula_proses']);
            self::tulisMeta($token, array_merge($meta, $hasil, ['status' => self::SIAP]));
        } finally {
            @unlink($kunci);
        }
    }

    /** Tuntut token secara atomik; pulangkan laluan fail kunci, atau null jika sudah dituntut. */
    private function tuntut(string $token): ?string
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::folder($token))) {
            return null;
        }
        $laluan = $disk->path(self::folder($token).'/proses.kunci');

        for ($cubaan = 0; $cubaan < 2; $cubaan++) {
            $h = @fopen($laluan, 'x');
            if ($h !== false) {
                fwrite($h, (string) getmypid());
                fclose($h);

                return $laluan;
            }
            // Kunci daripada proses yang mati (dibunuh, atau melebihi had satu kerja): buang dan cuba sekali lagi
            if (self::sedangDituntut($token)) {
                return null;
            }
            @unlink($laluan);
        }

        return null;
    }

    /** Kerja gagal di luar analisis (cth. worker ralat): buka skrin semak dengan amaran. */
    public static function tandaGagal(string $token): void
    {
        $meta = self::bacaMeta($token);
        if ($meta === null || self::status($meta) === self::SIAP) {
            return;
        }
        unset($meta['paksa_vlm'], $meta['mula_proses']);
        self::tulisMeta($token, array_merge(['teks_ocr' => '', 'skor_ocr' => null, 'nilai_ekstrak' => []], $meta, [
            'status' => self::SIAP,
            'mesej_amaran' => 'OCR/AI gagal diproses automatik — sila isi semua medan secara manual.',
        ]));
    }

    /**
     * OCR (hibrid Tesseract → model penglihatan, lihat OcrService) + pengekstrakan AI.
     * Kegagalan OCR/AI tidak menghalang semakan: amaran dipaparkan dan medan diisi manual.
     *
     * @return array{teks_ocr: string, skor_ocr: float|null, enjin: list<string>, nilai_ekstrak: array,
     *               mesej_amaran: string|null, masa: array{ocr: float|null, ai: float|null}}
     */
    public function analisis(string $laluanFail, JenisDokumen $jenisDokumen, bool $modHantar, bool $paksaVlm = false, bool $kunci = false): array
    {
        $hasil = ['teks_ocr' => '', 'skor_ocr' => null, 'enjin' => [], 'nilai_ekstrak' => [], 'mesej_amaran' => null,
            // Masa setiap langkah (saat), dipaparkan di skrin semak
            'masa' => ['ocr' => null, 'ai' => null]];

        // Segerak (worker tidak berjalan): satu analisis pada satu masa (Fasa D4); permintaan
        // serentak menunggu giliran. Kunci tamat sendiri jika proses mati di tengah jalan.
        $kunciCache = null;
        if ($kunci) {
            $kunciCache = Cache::lock(self::KUNCI_ANALISIS, (int) config('services.ocr.had_masa', 300) + 30);
            try {
                $kunciCache->block((int) config('services.ocr.tunggu_giliran', 120));
            } catch (LockTimeoutException) {
                $hasil['mesej_amaran'] = 'Sistem sedang sibuk menganalisis dokumen lain. Sila isi medan secara manual, atau cuba semula sebentar lagi.';

                return $hasil;
            }
        }

        // Segerak: masa menunggu kunci dikira dalam had masa PHP (Windows: masa dinding),
        // jadi mulakan semula had masa selepas giliran diperoleh
        if ($kunci) {
            set_time_limit((int) config('services.ocr.had_masa', 300) + 30);
        }

        try {
            $mula = microtime(true);
            $ocr = app(OcrService::class)->proses($laluanFail, $paksaVlm);
            $hasil['masa']['ocr'] = round(microtime(true) - $mula, 1);
            $hasil['teks_ocr'] = $ocr['teks'];
            $hasil['skor_ocr'] = $ocr['skor'];
            $hasil['enjin'] = $ocr['enjin'] ?? [];

            if ($ocr['teks'] === '') {
                $hasil['mesej_amaran'] = 'OCR tidak kesan sebarang teks pada fail ini. Sila isi semua medan secara manual.';
            } else {
                $mula = microtime(true);
                // Turut ekstrak Tarikh Dokumen & Perkara (medan asas, bukan EAV)
                $hasil['nilai_ekstrak'] = app(OllamaExtractionService::class)->ekstrak($ocr['teks'], self::medanUntukMod($jenisDokumen, $modHantar), true);
                $hasil['masa']['ai'] = round(microtime(true) - $mula, 1);
            }
        } catch (\Throwable $e) {
            Log::warning('Imbas & Arkib: OCR/AI gagal: '.$e->getMessage());
            $hasil['mesej_amaran'] = 'OCR/AI gagal diproses automatik'.(config('app.debug') ? ' ('.$e->getMessage().')' : '').' — sila isi semua medan secara manual.';
        } finally {
            $kunciCache?->release();
        }

        return $hasil;
    }
}
