<?php

namespace Tests\Feature;

use App\Models\Bahagian;
use App\Models\LogAudit;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Integriti log audit (Fasa T): pelaku sebenar, bukan user_id 1 secara lalai.
 */
class AuditFasaTTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
    }

    public function test_tindakan_tanpa_log_masuk_tidak_dikreditkan_kepada_user_1(): void
    {
        $this->buatPengguna(['SUPERADMIN']); // user_id 1 wujud

        // Cth. arahan konsol / penjadual: tiada pengguna log masuk
        $bahagian = Bahagian::create(['kod_bahagian' => 'SIS', 'nama_bahagian' => 'Bahagian Sistem']);

        $log = LogAudit::where('nama_jadual', 'bahagian')->where('rekod_id', $bahagian->bahagian_id)->sole();
        $this->assertNull($log->user_id);
    }

    public function test_tindakan_pengguna_log_masuk_direkod_atas_namanya(): void
    {
        $this->buatPengguna(['SUPERADMIN']);
        $admin = $this->buatPengguna(['ADMIN']);
        $this->actingAs($admin);

        $bahagian = Bahagian::create(['kod_bahagian' => 'KEW', 'nama_bahagian' => 'Bahagian Kewangan']);

        $this->assertSame($admin->user_id, LogAudit::where('nama_jadual', 'bahagian')->where('rekod_id', $bahagian->bahagian_id)->sole()->user_id);
    }

    public function test_log_audit_papar_sistem_bagi_log_tanpa_pengguna(): void
    {
        $audit = $this->buatPengguna(['AUDIT']);
        LogAudit::create(['user_id' => null, 'tindakan' => 'Cipta', 'nama_jadual' => 'bahagian', 'rekod_id' => 99, 'created_at' => now()]);

        $this->actingAs($audit)->get(route('log-audit.index'))->assertOk()
            ->assertSee('Sistem / tetamu');
    }
}
