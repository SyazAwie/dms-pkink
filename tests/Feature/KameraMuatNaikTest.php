<?php

namespace Tests\Feature;

use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * P-B: modal kamera dan input aplikasi kamera tersedia pada halaman muat naik;
 * kamera dibenarkan untuk laman sendiri (Permissions-Policy).
 */
class KameraMuatNaikTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
    }

    public function test_halaman_muat_naik_ada_modal_kamera(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('dokumen.create'))->assertOk()
            ->assertSee('id="modalKamera"', false)
            ->assertSee('id="kameraVideo" autoplay playsinline muted', false)
            ->assertSee('id="kameraFallback" accept="image/*" capture="environment"', false)
            ->assertSee('accept=".pdf,.jpg,.jpeg,.png"', false)
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()');
    }

    public function test_imbas_hantar_ada_modal_kamera(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('imbas-hantar.create'))->assertOk()
            ->assertSee('id="modalKamera"', false);
    }
}
