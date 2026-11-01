<?php

namespace App\Jobs;

use App\Models\DokumenScan;
use App\Models\User;
use App\Services\OcrFailService;
use App\Support\Demo;
use App\Support\Notifikasi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** OCR satu fail imbasan dokumen (butang "Jalankan OCR") dalam worker queue OCR (T2). */
class OcrFailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** @param  array{user_id: int|null, ip: string|null, ua: string|null}  $pelaku */
    public function __construct(public int $scanId, public array $pelaku, public ?string $modDemo = null) {}

    public function handle(OcrFailService $servis): void
    {
        Demo::dalamMod($this->modDemo, function () use ($servis) {
            // Tuntut secara atomik: hanya fail yang masih "Dalam Giliran" (bukan yang sudah
            // diproses segerak selepas worker mati, atau oleh kerja lain)
            $dituntut = DokumenScan::whereKey($this->scanId)->where('status_ocr', OcrFailService::DALAM_GILIRAN)
                ->update(['status_ocr' => OcrFailService::SEDANG_DIPROSES, 'updated_at' => now()]);
            $scan = DokumenScan::with('dokumen')->find($this->scanId);
            if (! $dituntut || ! $scan || ! $scan->dokumen) {
                return; // fail/dokumen dipadam, atau sudah diproses dengan cara lain
            }
            $ralat = $servis->jalankan($scan, $this->pelaku);

            // Pengguna mungkin telah meninggalkan halaman: maklumkan melalui loceng/peranti
            if ($user = User::find($this->pelaku['user_id'] ?? null)) {
                Notifikasi::ocrFailSelesai($user, $scan, $ralat === null);
            }
        });
    }

    public function failed(?\Throwable $e): void
    {
        Demo::dalamMod($this->modDemo, function () {
            DokumenScan::whereKey($this->scanId)
                ->whereIn('status_ocr', [OcrFailService::DALAM_GILIRAN, OcrFailService::SEDANG_DIPROSES])
                ->update(['status_ocr' => 'Ralat']);
        });
    }
}
