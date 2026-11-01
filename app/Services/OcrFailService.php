<?php

namespace App\Services;

use App\Models\DokumenOcr;
use App\Models\DokumenScan;
use App\Models\LogAudit;
use Illuminate\Support\Facades\Log;

/**
 * OCR bagi SATU fail imbasan dokumen sedia ada (butang "Jalankan OCR" di Butiran Dokumen).
 * Dijalankan oleh OcrFailJob (worker queue OCR, T2) atau segerak bila worker tidak berjalan.
 * Kegagalan direkod sebagai status `Ralat`, tidak pernah menjejaskan dokumen induk.
 */
class OcrFailService
{
    public const DALAM_GILIRAN = 'Dalam Giliran';

    public const SEDANG_DIPROSES = 'Sedang Diproses';

    /**
     * @param  array{user_id: int|null, ip: string|null, ua: string|null}  $pelaku  untuk log audit
     * @return \Throwable|null ralat (null = berjaya, termasuk "Tiada Teks Dikesan")
     */
    public function jalankan(DokumenScan $scan, array $pelaku): ?\Throwable
    {
        // touch(): updated_at disegarkan walaupun status sudah "Sedang Diproses" (cuba semula
        // selepas tersangkut), supaya pengguna lain tidak melihat butang Cuba Semula
        $scan->forceFill(['status_ocr' => self::SEDANG_DIPROSES])->save();
        $scan->touch();

        try {
            $hasil = app(OcrService::class)->proses($scan->lokasi_fail);

            DokumenOcr::updateOrCreate(
                ['dokumen_scan_id' => $scan->dokumen_scan_id],
                [
                    'teks_dibaca' => $hasil['teks'] !== '' ? $hasil['teks'] : null,
                    'skor_padanan' => $hasil['skor'],
                    'status_semakan' => 'Belum Disemak',
                ]
            );

            $scan->update(['status_ocr' => $hasil['teks'] !== '' ? 'Selesai' : 'Tiada Teks Dikesan']);
            $this->log($scan, $pelaku, ['status_ocr' => $scan->status_ocr, 'skor' => $hasil['skor'], 'enjin' => $hasil['enjin'] ?? []]);

            return null;
        } catch (\Throwable $e) {
            Log::warning("OCR gagal untuk dokumen_scan_id {$scan->dokumen_scan_id}: ".$e->getMessage());
            $scan->update(['status_ocr' => 'Ralat']);
            $this->log($scan, $pelaku, ['status_ocr' => 'Ralat']);

            return $e;
        }
    }

    /** Log audit bagi OCR manual (#21): siapa menjalankan OCR atas fail mana, dan hasilnya. */
    private function log(DokumenScan $scan, array $pelaku, array $hasil): void
    {
        LogAudit::create([
            'user_id' => $pelaku['user_id'] ?? null,
            'tindakan' => 'Jalankan OCR',
            'nama_jadual' => 'dokumen_scan',
            'rekod_id' => $scan->dokumen_scan_id,
            'data_lama' => null,
            'data_baharu' => ['dokumen' => $scan->dokumen?->no_rujukan, 'nama_fail' => $scan->nama_fail] + $hasil,
            'alamat_ip' => $pelaku['ip'] ?? null,
            'peranti_pengguna' => $pelaku['ua'] ?? null,
            'created_at' => now(),
        ]);
    }
}
