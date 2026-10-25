<?php

namespace Tests\Feature;

use App\Models\LogAudit;
use Illuminate\Support\Facades\Hash;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Profil pengguna: ubah nama & emel, tukar kata laluan.
 */
class ProfilTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
    }

    public function test_tetamu_tidak_boleh_buka_profil(): void
    {
        $this->get(route('profil.index'))->assertRedirect(route('login'));
    }

    public function test_profil_papar_maklumat_akaun(): void
    {
        $bahagian = $this->buatBahagian('KEW', 'Bahagian Kewangan');
        $user = $this->buatPengguna(['STAFF'], ['ic_pekerja' => '880808038888', 'bahagian_id' => $bahagian->bahagian_id]);

        $this->actingAs($user)->get(route('profil.index'))
            ->assertOk()
            ->assertSee('880808038888')
            ->assertSee('Bahagian Kewangan')
            ->assertSee($user->nama_staff);
    }

    public function test_ubah_nama_tanpa_kata_laluan_dan_ic_bahagian_diabaikan(): void
    {
        $bahagian = $this->buatBahagian();
        $lain = $this->buatBahagian('KEW', 'Bahagian Kewangan');
        $user = $this->buatPengguna(['PENGURUS'], ['ic_pekerja' => '880808031111', 'bahagian_id' => $bahagian->bahagian_id]);

        $this->actingAs($user)->put(route('profil.kemaskini'), [
            'nama_staff' => 'Nama Baharu',
            'email' => $user->email,
            'ic_pekerja' => '000000000000',          // cubaan ubah IC
            'bahagian_id' => $lain->bahagian_id,    // cubaan pindah bahagian
        ])->assertRedirect(route('profil.index'))->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Nama Baharu', $user->nama_staff);
        $this->assertSame('880808031111', $user->ic_pekerja);
        $this->assertSame($bahagian->bahagian_id, (int) $user->bahagian_id);
    }

    public function test_nama_profil_ikut_peraturan_nama(): void
    {
        $user = $this->buatPengguna();

        $this->actingAs($user)->from(route('profil.index'))->put(route('profil.kemaskini'), [
            'nama_staff' => '[Klik](https://evil.test)',
            'email' => $user->email,
        ])->assertSessionHasErrorsIn('maklumat', ['nama_staff']);

        $this->actingAs($user)->put(route('profil.kemaskini'), [
            'nama_staff' => 'Siti a/p Muthu',
            'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Siti a/p Muthu', $user->fresh()->nama_staff);
    }

    public function test_tukar_emel_wajib_kata_laluan_semasa_yang_betul(): void
    {
        $user = $this->buatPengguna(['STAFF'], ['email' => 'lama@pkink.test']);

        $this->actingAs($user)->from(route('profil.index'))->put(route('profil.kemaskini'), [
            'nama_staff' => $user->nama_staff,
            'email' => 'baharu@pkink.test',
        ])->assertSessionHasErrorsIn('maklumat', ['kata_laluan_semasa' => 'Sila masukkan kata laluan semasa untuk menukar emel.']);

        $this->actingAs($user)->from(route('profil.index'))->put(route('profil.kemaskini'), [
            'nama_staff' => $user->nama_staff,
            'email' => 'baharu@pkink.test',
            'kata_laluan_semasa' => 'Salah1234',
        ])->assertSessionHasErrorsIn('maklumat', ['kata_laluan_semasa' => 'Kata laluan semasa tidak betul.']);

        $this->assertSame('lama@pkink.test', $user->fresh()->email);

        $this->actingAs($user)->put(route('profil.kemaskini'), [
            'nama_staff' => $user->nama_staff,
            'email' => 'baharu@pkink.test',
            'kata_laluan_semasa' => 'Lama1234',
        ])->assertSessionHas('success');

        $this->assertSame('baharu@pkink.test', $user->fresh()->email);
    }

    public function test_emel_yang_sudah_digunakan_ditolak(): void
    {
        $this->buatPengguna(['STAFF'], ['email' => 'diguna@pkink.test']);
        $user = $this->buatPengguna();

        $this->actingAs($user)->from(route('profil.index'))->put(route('profil.kemaskini'), [
            'nama_staff' => $user->nama_staff,
            'email' => 'diguna@pkink.test',
            'kata_laluan_semasa' => 'Lama1234',
        ])->assertSessionHasErrorsIn('maklumat', ['email' => 'Emel ini sudah digunakan oleh akaun lain.']);
    }

    public function test_tukar_kata_laluan_dari_profil(): void
    {
        $user = $this->buatPengguna();

        $this->actingAs($user)->from(route('profil.index'))->put(route('profil.kata-laluan'), [
            'kata_laluan_lama' => 'Salah1234',
            'kata_laluan' => 'Baharu123',
            'kata_laluan_confirmation' => 'Baharu123',
        ])->assertSessionHasErrorsIn('kataLaluan', ['kata_laluan_lama' => 'Kata laluan semasa tidak betul.']);

        $this->actingAs($user)->from(route('profil.index'))->put(route('profil.kata-laluan'), [
            'kata_laluan_lama' => 'Lama1234',
            'kata_laluan' => 'pendekk',
            'kata_laluan_confirmation' => 'pendekk',
        ])->assertSessionHasErrorsIn('kataLaluan', ['kata_laluan' => 'Kata laluan mesti sekurang-kurangnya 8 aksara.']);

        $this->actingAs($user)->from(route('profil.index'))->put(route('profil.kata-laluan'), [
            'kata_laluan_lama' => 'Lama1234',
            'kata_laluan' => 'Lama1234',
            'kata_laluan_confirmation' => 'Lama1234',
        ])->assertSessionHasErrorsIn('kataLaluan', ['kata_laluan' => 'Kata laluan baharu mesti berbeza daripada kata laluan semasa.']);

        $this->assertTrue(Hash::check('Lama1234', $user->fresh()->password));

        $this->actingAs($user)->put(route('profil.kata-laluan'), [
            'kata_laluan_lama' => 'Lama1234',
            'kata_laluan' => 'Baharu123',
            'kata_laluan_confirmation' => 'Baharu123',
        ])->assertRedirect(route('profil.index'))->assertSessionHas('success');

        $this->assertTrue(Hash::check('Baharu123', $user->fresh()->password));
        $this->assertTrue(LogAudit::where('tindakan', 'Tukar Kata Laluan')->where('rekod_id', $user->user_id)->exists());
    }
}
