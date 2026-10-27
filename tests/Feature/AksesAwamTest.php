<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Persediaan akses awam (demo melalui terowong HTTPS / produksi): header keselamatan
 * dan proksi dipercayai (localhost sahaja).
 */
class AksesAwamTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Route::middleware('web')->get('/_ujian/proksi', fn () => [
            'ip' => request()->ip(),
            'selamat' => request()->isSecure(),
            'url' => url('/dashboard'),
        ]);
    }

    public function test_header_keselamatan_pada_halaman_web(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(), payment=()')
            // HTTP biasa: tiada HSTS
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_terowong_dari_localhost_dipercayai(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'demo.contoh.test', 'X-Forwarded-Port' => '443'])
            ->get('/_ujian/proksi')
            ->assertJson(['ip' => '203.0.113.9', 'selamat' => true, 'url' => 'https://demo.contoh.test/dashboard'])
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_header_proksi_dari_ip_lain_diabaikan(): void
    {
        // Pengguna LAN cuba memalsukan IP untuk mengelak had kadar log masuk
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.50'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
            ->get('/_ujian/proksi')
            ->assertJson(['ip' => '192.168.1.50', 'selamat' => false]);
    }
}
