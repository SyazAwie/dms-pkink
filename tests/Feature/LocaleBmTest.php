<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Locale aplikasi ialah BM: mesej validasi lalai Laravel, nama medan dan
 * tarikh (translatedFormat / diffForHumans) dipaparkan dalam Bahasa Melayu.
 */
class LocaleBmTest extends TestCase
{
    use SkemaUjian;

    public function test_locale_lalai_ialah_ms(): void
    {
        $this->assertSame('ms', app()->getLocale());
    }

    public function test_mesej_validasi_lalai_dalam_bm(): void
    {
        $this->sediakanSkema();
        $staf = $this->buatPengguna(['STAFF']);

        // Peraturan after_or_equal tanpa mesej khusus dalam DokumenController::index
        $this->actingAs($staf)->from(route('dokumen.index'))
            ->get(route('dokumen.index', ['dari' => '2026-10-27', 'hingga' => '2026-10-01']))
            ->assertSessionHasErrors(['hingga' => 'Medan tarikh hingga mesti tarikh yang sama atau selepas tarikh dari.']);
    }

    public function test_tarikh_dalam_bm(): void
    {
        Carbon::setTestNow('2026-10-27 09:00:00');

        $this->assertSame('27 Oktober 2026', now()->translatedFormat('d F Y'));
        $this->assertSame('2 hari yang lepas', now()->subDays(2)->diffForHumans());
    }
}
