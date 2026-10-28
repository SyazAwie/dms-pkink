<?php

namespace Tests\Feature;

use App\Models\IpDisekat;
use App\Models\User;
use App\Support\KeadaanKeselamatan;
use App\Support\Keselamatan;
use Illuminate\Support\Facades\Cache;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Fasa Keselamatan S3: kunci akaun, log keluar paksa, sekat IP, mod kecemasan.
 */
class ButangKecemasanTest extends TestCase
{
    use SkemaUjian;

    private User $super;

    private User $admin;

    private User $staf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Cache::flush();
        $this->super = $this->buatPengguna(['SUPERADMIN'], ['nama_staff' => 'Super Utama']);
        $this->admin = $this->buatPengguna(['ADMIN'], ['nama_staff' => 'Pentadbir Biasa']);
        $this->staf = $this->buatPengguna(['STAFF'], ['nama_staff' => 'Staf Disyaki']);
    }

    private function logMasuk(User $u, string $ip = '10.0.0.5')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login.proses'), ['ic_pekerja' => $u->ic_pekerja, 'password' => 'Lama1234']);
    }

    public function test_kunci_akaun_tamatkan_sesi_dan_halang_log_masuk(): void
    {
        $this->logMasuk($this->staf)->assertRedirect(route('dashboard'));
        $sesiStaf = session()->all();

        $this->actingAs($this->admin)->post(route('keselamatan.kunci'), ['user_id' => $this->staf->user_id, 'tempoh' => '24j', 'sebab' => 'Kata laluan bocor'])
            ->assertSessionHas('success');
        $this->assertTrue($this->staf->fresh()->dikunci());
        $this->assertDatabaseHas('log_audit', ['tindakan' => 'Kunci Akaun', 'rekod_id' => $this->staf->user_id]);

        // Sesi sedia ada staf ditamatkan pada permintaan seterusnya
        $this->actingAs($this->staf->fresh())->withSession($sesiStaf)->get(route('dashboard'))
            ->assertRedirect(route('login'))->assertSessionHasErrors('ic_pekerja');
        $this->assertGuest();

        // Log masuk baharu ditolak walaupun kata laluan betul
        $this->logMasuk($this->staf)->assertSessionHasErrors(['ic_pekerja' => 'Akaun anda telah dikunci oleh pentadbir sistem. Sila hubungi Pentadbir Sistem.']);
        $this->assertGuest();

        $this->actingAs($this->admin)->post(route('keselamatan.buka-kunci'), ['user_id' => $this->staf->user_id]);
        $this->post(route('logout'));
        $this->logMasuk($this->staf)->assertRedirect(route('dashboard'));
    }

    public function test_had_kebenaran_tindakan_akaun(): void
    {
        $this->actingAs($this->admin)->post(route('keselamatan.kunci'), ['user_id' => $this->super->user_id, 'tempoh' => '1j', 'sebab' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('keselamatan.log-keluar'), ['user_id' => $this->admin->user_id])->assertForbidden();
        $this->actingAs($this->staf)->post(route('keselamatan.log-keluar'), ['user_id' => $this->admin->user_id])->assertForbidden();
        $this->actingAs($this->admin)->post(route('keselamatan.log-keluar-semua'), ['kata_laluan' => 'Lama1234'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('keselamatan.kecemasan'), ['tindakan' => 'hidup', 'kata_laluan' => 'Lama1234'])->assertForbidden();
    }

    public function test_log_keluar_paksa_satu_dan_semua_pengguna(): void
    {
        $this->logMasuk($this->staf);
        $sesiStaf = session()->all();
        $this->post(route('logout'));
        $this->logMasuk($this->admin);
        $sesiAdmin = session()->all();
        $this->travel(2)->seconds();

        $this->actingAs($this->super)->post(route('keselamatan.log-keluar'), ['user_id' => $this->staf->user_id])->assertSessionHas('success');
        $this->actingAs($this->staf)->withSession($sesiStaf)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->actingAs($this->admin)->withSession($sesiAdmin)->get(route('dashboard'))->assertOk();

        // Semua: perlu kata laluan betul; pelaku kekal log masuk
        $this->actingAs($this->super)->withSession(['masa_log_masuk' => now()->timestamp])
            ->post(route('keselamatan.log-keluar-semua'), ['kata_laluan' => 'salah'])->assertSessionHasErrorsIn('kecemasan', 'kata_laluan');
        $this->travel(2)->seconds();
        $this->post(route('keselamatan.log-keluar-semua'), ['kata_laluan' => 'Lama1234'])->assertSessionHas('success');
        $this->get(route('keselamatan.index'))->assertOk();
        $this->actingAs($this->admin)->withSession($sesiAdmin)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_sekat_ip_manual_dan_automatik(): void
    {
        $this->actingAs($this->admin)->post(route('keselamatan.sekat-ip'), ['alamat_ip' => '127.0.0.1', 'tempoh' => 'kekal'])->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('keselamatan.sekat-ip'), ['alamat_ip' => '198.51.100.7', 'tempoh' => 'kekal', 'sebab' => 'Imbasan port'])
            ->assertSessionHas('success');
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->get(route('login'))->assertForbidden()->assertSee('Akses Disekat');

        // Automatik selepas lonjakan gagal: 1 jam (sebagai tetamu; IP disekat tadi juga tidak boleh log keluar)
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->post(route('logout'));
        for ($i = 0; $i < Keselamatan::AMBANG_GAGAL_IP; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->post(route('login.proses'), ['ic_pekerja' => '000', 'password' => 'x']);
        }
        $auto = IpDisekat::where('alamat_ip', '203.0.113.9')->first();
        $this->assertTrue($auto->automatik);
        $this->assertTrue($auto->tamat_pada->between(now()->addMinutes(59), now()->addMinutes(61)));
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get(route('login'))->assertForbidden();
        $this->assertStringContainsString('disekat automatik', $this->admin->fresh()->notifications->first()->data['mesej']);

        // IP dipercayai (cth. NAT pejabat) tidak pernah disekat, automatik atau manual
        config(['services.keselamatan.ip_dipercayai' => '198.51.100.50, 198.51.100.51']);
        for ($i = 0; $i < Keselamatan::AMBANG_GAGAL_IP; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.51'])->post(route('login.proses'), ['ic_pekerja' => '000', 'password' => 'x']);
        }
        $this->assertFalse(IpDisekat::where('alamat_ip', '198.51.100.51')->exists());
        $this->actingAs($this->admin)->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->post(route('keselamatan.sekat-ip'), ['alamat_ip' => '198.51.100.50', 'tempoh' => '1j'])->assertSessionHas('error');

        // Buka sekatan
        $this->actingAs($this->admin)->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])->post(route('keselamatan.buka-ip', $auto->ip_disekat_id))->assertSessionHas('success');
        $this->post(route('logout'));
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get(route('login'))->assertOk();
    }

    public function test_mod_kecemasan_hanya_superadmin_dan_baca_sahaja(): void
    {
        $this->logMasuk($this->admin);
        $sesiAdmin = session()->all();

        $this->actingAs($this->super)->post(route('keselamatan.kecemasan'), ['tindakan' => 'hidup', 'sebab' => 'Disyaki pencerobohan', 'kata_laluan' => 'Lama1234'])
            ->assertSessionHas('success');
        $this->assertNotNull(KeadaanKeselamatan::kecemasan());

        // Pentadbir lain dilog keluar & tidak boleh log masuk; tetamu nampak halaman ditutup
        $this->actingAs($this->admin)->withSession($sesiAdmin)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->post(route('logout'));
        $this->logMasuk($this->admin)->assertSessionHasErrors('ic_pekerja');
        $this->get(route('password.request'))->assertStatus(503)->assertSee('Sistem Ditutup Sementara');
        $this->get(route('login'))->assertOk();

        // Super Admin: boleh membaca, tidak boleh mengubah data
        $this->actingAs($this->super)->get(route('dashboard'))->assertOk()->assertSee('MOD KECEMASAN AKTIF');
        $this->actingAs($this->super)->post(route('bahagian.store'), ['kod_bahagian' => 'X', 'nama_bahagian' => 'Bahagian X'])
            ->assertSessionHas('error');
        $this->assertDatabaseMissing('bahagian', ['kod_bahagian' => 'X']);

        // Dimatikan dari konsol pelayan (bila Super Admin terkunci di luar)
        $this->artisan('keselamatan:kecemasan', ['tindakan' => 'off'])->expectsOutputToContain('tidak aktif')->assertSuccessful();
        $this->assertNull(KeadaanKeselamatan::kecemasan());
        $this->assertDatabaseHas('log_audit', ['tindakan' => 'Mod Kecemasan', 'peranti_pengguna' => 'artisan']);
    }
}
