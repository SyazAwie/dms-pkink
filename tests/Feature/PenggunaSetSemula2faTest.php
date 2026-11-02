<?php

namespace Tests\Feature;

use App\Models\LogAudit;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Fasa H: set semula 2FA dari konsol pelayan bila pengguna kehilangan telefon dan kod pemulihan.
 */
class PenggunaSetSemula2faTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
    }

    public function test_set_semula_2fa_dan_direkod_dalam_log_audit(): void
    {
        $admin = $this->buatPengguna(['ADMIN']);
        $admin->forceFill(['dua_faktor_rahsia' => encrypt('ABCDEFGHIJKLMNOP'), 'dua_faktor_kod_pemulihan' => encrypt('[]'), 'dua_faktor_disahkan_pada' => now()])->saveQuietly();

        $this->artisan('pengguna:set-semula-2fa', ['no_kp' => $admin->ic_pekerja])
            ->expectsConfirmation("Set semula 2FA bagi {$admin->nama_staff} ({$admin->email})?", 'yes')
            ->assertSuccessful();

        $admin->refresh();
        $this->assertNull($admin->dua_faktor_rahsia);
        $this->assertNull($admin->dua_faktor_disahkan_pada);
        $this->assertSame(1, LogAudit::where('tindakan', 'Set Semula 2FA')->where('rekod_id', $admin->user_id)->count());
    }

    public function test_no_kp_tiada_gagal_dan_tanpa_2fa_tiada_perubahan(): void
    {
        $staf = $this->buatPengguna(['STAFF']);

        $this->artisan('pengguna:set-semula-2fa', ['no_kp' => '000000000000'])->assertFailed();
        $this->artisan('pengguna:set-semula-2fa', ['no_kp' => $staf->ic_pekerja])
            ->expectsOutputToContain('belum mendaftar 2FA')->assertSuccessful();
        $this->assertSame(0, LogAudit::where('tindakan', 'Set Semula 2FA')->count());
    }
}
