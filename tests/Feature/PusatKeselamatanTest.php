<?php

namespace Tests\Feature;

use App\Models\LogMasuk;
use App\Models\User;
use App\Support\Keselamatan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Fasa Keselamatan S2: Pusat Keselamatan dan amaran automatik.
 */
class PusatKeselamatanTest extends TestCase
{
    use SkemaUjian;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Cache::flush();
        config(['services.sandaran.folder' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'tiada_sandaran_'.uniqid(),
            'services.keselamatan.antivirus_bin' => base_path('tiada-antivirus.exe')]);
        $this->admin = $this->buatPengguna(['ADMIN'], ['nama_staff' => 'Pentadbir Utama']);
    }

    private function amaran(User $u): array
    {
        return $u->fresh()->notifications->where('data.jenis', 'amaran_keselamatan')->pluck('data.tajuk')->values()->all();
    }

    public function test_halaman_untuk_pentadbir_sahaja_dan_papar_semakan(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('keselamatan.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('keselamatan.index'))->assertOk()
            ->assertSee('Semakan Pengerasan')->assertSee('Antivirus fail muat naik')->assertSee('Tidak aktif')
            ->assertSee('Salinan luar talian')->assertSee('Belum pernah dibuat')
            ->assertSee('Pentadbir Utama'); // tanpa 2FA
    }

    public function test_pelan_tindak_balas_insiden_boleh_dibuka_pentadbir(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('keselamatan.pelan'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('keselamatan.pelan'))->assertOk()
            ->assertSee('JANGAN bayar wang tebusan')->assertSee('RANSOMWARE / PERAS UGUT')
            ->assertSee('<table>', false)->assertSee('window.print()', false);
    }

    public function test_pentadbir_kata_laluan_lalai_dikesan(): void
    {
        $this->buatPengguna(['SUPERADMIN'], ['nama_staff' => 'Akaun Demo Lemah', 'password' => Hash::make('123456789')]);

        $semak = collect(Keselamatan::senaraiSemak())->firstWhere('nama', 'Kata laluan pentadbir');
        $this->assertSame('ralat', $semak['status']);
        $this->assertStringContainsString('Akaun Demo Lemah', $semak['nilai']);
        $this->assertStringNotContainsString('Pentadbir Utama', $semak['nilai']);
    }

    public function test_lonjakan_log_masuk_gagal_dari_satu_ip_beri_amaran_sekali(): void
    {
        $staf = $this->buatPengguna(['STAFF']);
        $cuba = fn () => $this->withServerVariables(['REMOTE_ADDR' => '10.9.8.7'])
            ->post(route('login.proses'), ['ic_pekerja' => $staf->ic_pekerja, 'password' => 'salah-'.uniqid()]);

        for ($i = 1; $i < Keselamatan::AMBANG_GAGAL_IP; $i++) {
            $cuba();
        }
        $this->assertSame([], $this->amaran($this->admin));

        $cuba();
        $cuba();
        $this->assertSame(['Cubaan teka kata laluan dikesan'], $this->amaran($this->admin));
        $this->assertStringContainsString('10.9.8.7', $this->admin->fresh()->notifications->first()->data['mesej']);
    }

    public function test_pentadbir_log_masuk_dari_ip_baharu_dimaklumkan(): void
    {
        $masuk = fn ($ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login.proses'), ['ic_pekerja' => $this->admin->ic_pekerja, 'password' => 'Lama1234']);

        $masuk('192.168.1.10');  // log masuk pertama direkod: tiada perbandingan
        $this->post(route('logout'));
        $masuk('192.168.1.10');  // IP biasa
        $this->post(route('logout'));
        $this->assertSame([], $this->amaran($this->admin));

        $masuk('203.0.113.5');
        $this->assertSame(['Log masuk pentadbir dari IP baharu'], $this->amaran($this->admin));
        $this->assertSame(3, LogMasuk::where('berjaya', true)->count());
    }

    public function test_sandaran_tertunggak_dimaklumkan_sekali_sehari(): void
    {
        Keselamatan::semakBerkala();
        Keselamatan::semakBerkala();

        $this->assertSame(['Sandaran tertunggak'], $this->amaran($this->admin));
    }
}
