<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Laluan tetamu (log masuk, lupa & tetap semula kata laluan) dilindungi
 * middleware 'guest' (isu #35).
 *
 * Tiada jadual domain diperlukan: pengguna "log masuk" dibina tanpa disimpan,
 * dan middleware 'guest' hanya menyemak Auth::check().
 */
class LaluanTetamuTest extends TestCase
{
    public function test_tetamu_boleh_buka_halaman_log_masuk_dan_lupa_kata_laluan(): void
    {
        $this->get('/')->assertOk();
        $this->get('/lupa-kata-laluan')->assertOk();
    }

    public function test_pengguna_log_masuk_dibawa_ke_papan_pemuka(): void
    {
        $pengguna = new User(['nama_staff' => 'Pengguna Ujian']);
        $pengguna->user_id = 1;

        foreach (['/', '/lupa-kata-laluan', '/reset-kata-laluan/token-ujian'] as $laluan) {
            $this->actingAs($pengguna)
                ->get($laluan)
                ->assertRedirect(route('dashboard'));
        }

        $this->actingAs($pengguna)
            ->post('/login', ['ic_pekerja' => '000000000000', 'password' => 'salah'])
            ->assertRedirect(route('dashboard'));
    }
}
