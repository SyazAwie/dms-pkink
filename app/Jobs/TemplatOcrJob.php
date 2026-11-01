<?php

namespace App\Jobs;

use App\Models\TemplatBorang;
use App\Services\TemplatBorangService;
use App\Support\Demo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** OCR templat borang kosong (Fasa TB) dalam worker queue OCR. */
class TemplatOcrJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $templatId, public ?string $modDemo = null) {}

    public function handle(TemplatBorangService $servis): void
    {
        Demo::dalamMod($this->modDemo, function () use ($servis) {
            if ($templat = TemplatBorang::find($this->templatId)) {
                $servis->jalankanOcr($templat);
            }
        });
    }

    public function failed(?\Throwable $e): void
    {
        Demo::dalamMod($this->modDemo, fn () => TemplatBorang::whereKey($this->templatId)
            ->whereIn('status_ocr', ['Dalam Giliran', 'Sedang Diproses'])->update(['status_ocr' => 'Ralat']));
    }
}
