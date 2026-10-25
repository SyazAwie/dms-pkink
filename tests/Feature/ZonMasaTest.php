<?php

namespace Tests\Feature;

use App\Models\LogAudit;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Zon masa aplikasi mesti waktu Malaysia (isu #39), selari dengan zon sesi
 * MySQL, nilai lalai CURRENT_TIMESTAMP dan HeidiSQL.
 */
class ZonMasaTest extends TestCase
{
    use SkemaUjian;

    public function test_zon_masa_aplikasi_ialah_waktu_malaysia(): void
    {
        $this->assertSame('Asia/Kuala_Lumpur', config('app.timezone'));
        $this->assertSame('+08:00', now()->format('P'));
    }

    public function test_masa_disimpan_dalam_waktu_malaysia(): void
    {
        $this->sediakanSkema();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-22 05:00:00', 'UTC'));

        $log = LogAudit::create([
            'tindakan' => 'Ujian',
            'nama_jadual' => 'ujian',
            'created_at' => now(),
        ]);

        // 05:00 UTC = 13:00 waktu Malaysia
        $this->assertSame('2026-10-22 13:00:00', $log->getRawOriginal('created_at'));
    }
}
