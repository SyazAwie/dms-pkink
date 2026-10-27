<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\User;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Tukar peranan aktif tanpa log masuk semula (menu nama di topbar). Lalai: semua peranan.
 * Ketiga-tiga lapisan RBAC (middleware, DokumenPolicy, scope) ikut peranan aktif.
 */
class PerananAktifTest extends TestCase
{
    use SkemaUjian;

    private User $demo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->demo = $this->buatPengguna(['ADMIN', 'STAFF'], ['nama_staff' => 'Demo Pelbagai Peranan']);
    }

    private function dokumenOrangLain(): Dokumen
    {
        $lain = $this->buatPengguna(['STAFF']);
        $jenis = JenisDokumen::create(['kod_dokumen' => 'MOHON', 'nama_dokumen' => 'Borang Permohonan']);

        return Dokumen::create(['jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'no_rujukan' => 'MOHON/2026/0001',
            'tarikh_dokumen' => '2026-10-27', 'created_by' => $lain->user_id]);
    }

    public function test_lalai_semua_peranan_dan_menu_tukar_dipaparkan(): void
    {
        $this->actingAs($this->demo)->get(route('dashboard'))->assertOk()
            ->assertSee('Semua peranan (2)')
            ->assertSee('Tukar peranan')
            ->assertSee('name="peranan" value="STAFF"', false)
            ->assertSee('name="peranan" value="ADMIN"', false);

        $this->actingAs($this->demo)->get(route('users.index'))->assertOk();
    }

    public function test_tukar_ke_staf_hadkan_semua_lapisan_rbac(): void
    {
        $dokumen = $this->dokumenOrangLain();
        $this->actingAs($this->demo)->get(route('dokumen.show', $dokumen->dokumen_id))->assertOk(); // ADMIN nampak semua

        $this->actingAs($this->demo)->post(route('peranan.tukar'), ['peranan' => 'STAFF'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('peranan_aktif', 'STAFF');

        // Papan pemuka hanya bahagian Staf (mesej flash dipapar pada permintaan pertama)
        $this->get(route('dashboard'))->assertOk()
            ->assertSee('Kini menggunakan peranan')
            ->assertSee('Permohonan Saya')
            ->assertDontSee('Perlu Perhatian');

        // Middleware peranan:, DokumenPolicy dan scope senarai kini ikut STAFF
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('dokumen.show', $dokumen->dokumen_id))->assertForbidden();
        $this->get(route('dokumen.index'))->assertOk()->assertDontSee('MOHON/2026/0001');

        // Kembali ke semua peranan
        $this->post(route('peranan.tukar'), ['peranan' => ''])->assertSessionMissing('peranan_aktif');
        $this->get(route('users.index'))->assertOk();
    }

    public function test_tidak_boleh_pilih_peranan_yang_bukan_milik(): void
    {
        $this->actingAs($this->demo)->post(route('peranan.tukar'), ['peranan' => 'SUPERADMIN'])
            ->assertSessionHasErrors(['peranan' => 'Anda tidak mempunyai peranan tersebut.'])
            ->assertSessionMissing('peranan_aktif');

        // Nilai sesi yang dipalsukan diabaikan
        $this->withSession(['peranan_aktif' => 'SUPERADMIN'])->get(route('users.index'))->assertOk();
        $this->assertSame(['ADMIN', 'STAFF'], collect($this->demo->fresh()->kodPeranan())->sort()->values()->all());
    }

    public function test_peranan_aktif_tidak_menjejaskan_pengguna_lain(): void
    {
        $pentadbirLain = $this->buatPengguna(['ADMIN', 'STAFF']);

        $this->actingAs($this->demo)->withSession(['peranan_aktif' => 'STAFF']);
        $this->get(route('dashboard'));

        $this->assertTrue($pentadbirLain->hasRole('ADMIN'));
    }

    public function test_pengguna_satu_peranan_tiada_menu_tukar(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('dashboard'))->assertOk()
            ->assertDontSee('Tukar peranan');
    }
}
