<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\User;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Tukar peranan aktif tanpa log masuk semula (menu nama di topbar). Satu peranan pada satu
 * masa; lalai = peranan tertinggi (User::SUSUNAN_PERANAN), tiada mod "semua peranan".
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

    public function test_lalai_peranan_tertinggi_tanpa_pilihan_semua_peranan(): void
    {
        $this->actingAs($this->demo)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Semua peranan')
            ->assertSee('Tukar peranan')
            ->assertSee('pp-perhatian', false) // papan pemuka pentadbir sahaja
            ->assertDontSee('id="tajuk-staf"', false)
            ->assertSee('name="peranan" value="STAFF"', false)
            ->assertSee('name="peranan" value="ADMIN"', false);

        $this->actingAs($this->demo)->get(route('users.index'))->assertOk();
        $this->assertSame(['ADMIN'], $this->demo->kodPeranan());
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

        // Tiada lagi "semua peranan": nilai kosong ditolak, peranan kekal STAFF
        $this->post(route('peranan.tukar'), ['peranan' => ''])->assertSessionHasErrors(['peranan' => 'Sila pilih satu peranan.']);
        $this->assertSame('STAFF', session('peranan_aktif'));

        $this->post(route('peranan.tukar'), ['peranan' => 'ADMIN']);
        $this->get(route('users.index'))->assertOk();
    }

    public function test_tidak_boleh_pilih_peranan_yang_bukan_milik(): void
    {
        $this->actingAs($this->demo)->post(route('peranan.tukar'), ['peranan' => 'SUPERADMIN'])
            ->assertSessionHasErrors(['peranan' => 'Anda tidak mempunyai peranan tersebut.'])
            ->assertSessionMissing('peranan_aktif');

        // Nilai sesi yang dipalsukan diabaikan: kembali ke peranan tertinggi yang dimiliki
        $this->withSession(['peranan_aktif' => 'SUPERADMIN'])->get(route('users.index'))->assertOk();
        $this->assertSame(['ADMIN'], $this->demo->fresh()->kodPeranan());
    }

    public function test_peranan_aktif_tidak_menjejaskan_pengguna_lain(): void
    {
        $pentadbirLain = $this->buatPengguna(['ADMIN', 'STAFF']);

        $this->actingAs($this->demo)->withSession(['peranan_aktif' => 'STAFF']);
        $this->get(route('dashboard'));

        $this->assertTrue($pentadbirLain->hasRole('ADMIN'));
        $this->assertTrue($pentadbirLain->hasRole('STAFF')); // pengguna lain: semua peranan
    }

    public function test_peranan_delegasi_boleh_dipilih_dan_lalai_ikut_susunan(): void
    {
        $pemberi = $this->buatPengguna(['PENYOKONG'], ['nama_staff' => 'Pegawai Bercuti']);
        $staf = $this->buatPengguna(['STAFF']);
        \App\Models\DelegasiKelulusan::create(['pemberi_id' => $pemberi->user_id, 'penerima_id' => $staf->user_id,
            'kod_peranan' => 'PENYOKONG', 'tarikh_mula' => today(), 'tarikh_tamat' => today()->addDay()]);

        // PENYOKONG lebih tinggi daripada STAFF dalam susunan: pengganti bermula sebagai Penyokong
        $this->actingAs($staf)->get(route('dashboard'))->assertOk()
            ->assertSee('name="peranan" value="PENYOKONG"', false)->assertSee('(delegasi)', false);
        $this->assertSame(['PENYOKONG'], $staf->kodPeranan());

        $this->post(route('peranan.tukar'), ['peranan' => 'STAFF'])->assertSessionHas('peranan_aktif', 'STAFF');
        $this->assertSame(['STAFF'], $staf->fresh()->kodPeranan());
    }

    public function test_pengguna_satu_peranan_tiada_menu_tukar(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('dashboard'))->assertOk()
            ->assertDontSee('Tukar peranan');
    }
}
