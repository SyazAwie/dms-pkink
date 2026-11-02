<?php

namespace Tests\Feature;

use App\Support\KeadaanKeselamatan;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Bahagian berlipat (komponen lipat) supaya halaman panjang mudah digunakan di telefon:
 * tertutup secara lalai, tetapi dibuka sendiri bila pengguna perlu bertindak (ralat, 2FA wajib,
 * mod kecemasan, penapis aktif).
 */
class BahagianBerlipatTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
    }

    /** null = tiada bahagian berlipat dengan id itu; true/false = terbuka/tertutup. */
    private function terbuka(string $html, string $id): ?bool
    {
        if (! preg_match('/<details\b[^>]*\bid="'.preg_quote($id, '/').'"[^>]*>/', $html, $m)) {
            return null;
        }

        return (bool) preg_match('/\sopen\s*>$/', $m[0]);
    }

    public function test_profil_kata_laluan_2fa_dan_log_masuk_tertutup_secara_lalai(): void
    {
        $html = $this->actingAs($this->buatPengguna(['STAFF']))->get(route('profil.index'))->assertOk()
            ->assertSee('Tidak aktif')                 // status 2FA kekal kelihatan bila tertutup
            ->getContent();

        $this->assertFalse($this->terbuka($html, 'tukar-kata-laluan'));
        $this->assertFalse($this->terbuka($html, 'dua-faktor'));
        $this->assertFalse($this->terbuka($html, 'log-masuk-terkini'));
    }

    public function test_tukar_kata_laluan_dibuka_bila_ada_ralat(): void
    {
        $html = $this->actingAs($this->buatPengguna(['STAFF']))->from(route('profil.index'))->followingRedirects()
            ->put(route('profil.kata-laluan'), ['kata_laluan_lama' => 'Salah1234', 'kata_laluan' => 'Baharu123', 'kata_laluan_confirmation' => 'Baharu123'])
            ->assertOk()->assertSee('Kata laluan semasa tidak betul.')->getContent();

        $this->assertTrue($this->terbuka($html, 'tukar-kata-laluan'));
        $this->assertFalse($this->terbuka($html, 'dua-faktor'));
    }

    public function test_2fa_dibuka_bila_wajib_tetapi_belum_aktif(): void
    {
        config(['services.dua_faktor.wajib' => true]);

        $html = $this->actingAs($this->buatPengguna(['ADMIN']))->get(route('profil.index'))->assertOk()
            ->assertSee('2FA wajib bagi akaun pentadbir')->getContent();

        $this->assertTrue($this->terbuka($html, 'dua-faktor'));
        $this->assertFalse($this->terbuka($html, 'tukar-kata-laluan'));
    }

    public function test_pusat_keselamatan_berlipat_dan_kecemasan_dibuka_bila_mod_aktif(): void
    {
        $super = $this->buatPengguna(['SUPERADMIN']);

        $html = $this->actingAs($super)->get(route('keselamatan.index'))->assertOk()->getContent();
        $this->assertFalse($this->terbuka($html, 'tindakan-kecemasan'));
        $this->assertFalse($this->terbuka($html, 'semakan-pengerasan'));
        $this->assertFalse($this->terbuka($html, 'tindakan-sensitif'));

        KeadaanKeselamatan::hidupkanKecemasan($super->nama_staff, 'Ujian');
        $html = $this->actingAs($super)->get(route('keselamatan.index'))->assertOk()
            ->assertSee('Mod kecemasan AKTIF')       // lencana pada tajuk berlipat
            ->getContent();
        $this->assertTrue($this->terbuka($html, 'tindakan-kecemasan'));
    }

    public function test_penapis_log_masuk_dan_log_audit_dibuka_bila_digunakan(): void
    {
        $admin = $this->buatPengguna(['SUPERADMIN']);

        $this->assertFalse($this->terbuka($this->actingAs($admin)->get(route('log-masuk.index'))->assertOk()->getContent(), 'penapis-log-masuk'));
        $html = $this->actingAs($admin)->get(route('log-masuk.index', ['status' => 'gagal']))->assertOk()->assertSee('Penapis aktif')->getContent();
        $this->assertTrue($this->terbuka($html, 'penapis-log-masuk'));

        $this->assertFalse($this->terbuka($this->actingAs($admin)->get(route('log-audit.index'))->assertOk()->getContent(), 'penapis-log'));
        $html = $this->actingAs($admin)->get(route('log-audit.index', ['tindakan' => 'Tambah']))->assertOk()->assertSee('Penapis aktif')->getContent();
        $this->assertTrue($this->terbuka($html, 'penapis-log'));
    }
}
