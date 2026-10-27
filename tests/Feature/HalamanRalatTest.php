<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Halaman ralat dalam BM, berdiri sendiri (tanpa log masuk / pangkalan data).
 */
class HalamanRalatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);

        Route::get('/_ujian/ralat/{kod}', fn (int $kod) => abort($kod, $kod === 409 ? 'Permohonan ini kini berstatus "Disokong".' : ''));
        Route::get('/_ujian/pengecualian', fn () => throw new \RuntimeException('SQLSTATE rahsia dalaman'));
    }

    public function test_404_dalam_bm_untuk_tetamu(): void
    {
        $this->get('/halaman-yang-tiada')->assertNotFound()
            ->assertSee('Halaman Tidak Dijumpai')
            ->assertSee('<html lang="ms">', false);
    }

    public function test_405_dalam_bm(): void
    {
        // Laluan logout hanya POST
        $this->get('/logout')->assertStatus(405)->assertSee('Tindakan Tidak Dibenarkan');
    }

    public function test_419_429_503_dalam_bm(): void
    {
        $this->get('/_ujian/ralat/419')->assertStatus(419)->assertSee('Sesi Tamat Tempoh');
        $this->get('/_ujian/ralat/429')->assertStatus(429)->assertSee('Terlalu Banyak Permintaan');
        $this->get('/_ujian/ralat/503')->assertStatus(503)->assertSee('Sistem Dalam Penyelenggaraan');
    }

    public function test_409_papar_mesej_kelulusan(): void
    {
        $this->get('/_ujian/ralat/409')->assertStatus(409)
            ->assertSee('Status Telah Berubah')
            ->assertSee('Permohonan ini kini berstatus &quot;Disokong&quot;.', false);
    }

    public function test_kod_lain_guna_fallback(): void
    {
        $this->get('/_ujian/ralat/418')->assertStatus(418)->assertSee('Permintaan Tidak Dapat Diproses');
        $this->get('/_ujian/ralat/502')->assertStatus(502)->assertSee('Ralat Sistem');
    }

    public function test_500_tidak_dedah_butiran(): void
    {
        $this->get('/_ujian/pengecualian')->assertStatus(500)
            ->assertSee('Ralat Sistem')
            ->assertDontSee('SQLSTATE rahsia dalaman');
    }
}
