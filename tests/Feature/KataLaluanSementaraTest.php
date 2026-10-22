<?php

namespace Tests\Feature;

use App\Models\LogAudit;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Kata laluan sementara & wajib tukar (ciri pendaftaran staf, langkah 2).
 */
class KataLaluanSementaraTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
    }

    public function test_pengguna_wajib_tukar_dibawa_ke_skrin_tukar_kata_laluan(): void
    {
        $user = $this->buatPengguna(['STAFF'], ['perlu_tukar_kata_laluan' => true]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('kata-laluan.tukar'));
        $this->actingAs($user)->get('/dokumen')->assertRedirect(route('kata-laluan.tukar'));
        $this->actingAs($user)->get(route('kata-laluan.tukar'))->assertOk()->assertSee('Tukar Kata Laluan');
    }

    public function test_pengguna_biasa_tidak_perlu_skrin_tukar(): void
    {
        $user = $this->buatPengguna();

        $this->actingAs($user)->get(route('kata-laluan.tukar'))->assertRedirect(route('dashboard'));
    }

    public function test_kata_laluan_baharu_mesti_ikut_polisi(): void
    {
        $user = $this->buatPengguna(['STAFF'], ['perlu_tukar_kata_laluan' => true]);

        // Senarai pasangan (bukan kunci array) supaya '12345678' kekal string
        $kes = [
            ['Ab1', 'Kata laluan mesti sekurang-kurangnya 8 aksara.'],
            ['abcdefgh', 'Kata laluan mesti mengandungi sekurang-kurangnya satu nombor.'],
            ['12345678', 'Kata laluan mesti mengandungi sekurang-kurangnya satu huruf.'],
            ['Lama1234', 'Kata laluan baharu mesti berbeza daripada kata laluan sementara.'],
        ];

        foreach ($kes as [$kataLaluan, $mesej]) {
            $this->actingAs($user)
                ->from(route('kata-laluan.tukar'))
                ->put(route('kata-laluan.simpan'), ['kata_laluan' => $kataLaluan, 'kata_laluan_confirmation' => $kataLaluan])
                ->assertSessionHasErrors(['kata_laluan' => $mesej]);
        }

        $this->actingAs($user)
            ->put(route('kata-laluan.simpan'), ['kata_laluan' => 'Baharu123', 'kata_laluan_confirmation' => 'Lain12345'])
            ->assertSessionHasErrors(['kata_laluan' => 'Pengesahan kata laluan tidak sepadan.']);

        $this->assertTrue($user->fresh()->perlu_tukar_kata_laluan);
    }

    public function test_tukar_kata_laluan_sah_kosongkan_bendera_dan_dilog(): void
    {
        $user = $this->buatPengguna(['STAFF'], [
            'perlu_tukar_kata_laluan' => true,
            'kata_laluan_tamat_pada' => now()->addHours(72),
        ]);

        $this->actingAs($user)
            ->put(route('kata-laluan.simpan'), ['kata_laluan' => 'Baharu123', 'kata_laluan_confirmation' => 'Baharu123'])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->perlu_tukar_kata_laluan);
        $this->assertNull($user->kata_laluan_tamat_pada);
        $this->assertTrue(Hash::check('Baharu123', $user->password));
        $this->assertTrue(LogAudit::where('tindakan', 'Tukar Kata Laluan')->where('rekod_id', $user->user_id)->exists());
        $this->assertFalse(LogAudit::where('data_baharu', 'like', '%Baharu123%')->exists());
    }

    public function test_kata_laluan_sementara_tamat_tempoh_tidak_boleh_log_masuk(): void
    {
        $this->buatPengguna(['STAFF'], [
            'ic_pekerja' => '900101030001',
            'perlu_tukar_kata_laluan' => true,
            'kata_laluan_tamat_pada' => now()->subMinute(),
        ]);

        $this->from('/')
            ->post('/login', ['ic_pekerja' => '900101030001', 'password' => 'Lama1234'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('ic_pekerja');

        $this->assertGuest();
    }

    public function test_kata_laluan_sementara_masih_sah_boleh_log_masuk_lalu_wajib_tukar(): void
    {
        $this->buatPengguna(['STAFF'], [
            'ic_pekerja' => '900101030002',
            'perlu_tukar_kata_laluan' => true,
            'kata_laluan_tamat_pada' => now()->addHours(10),
        ]);

        $this->post('/login', ['ic_pekerja' => '900101030002', 'password' => 'Lama1234'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $this->get('/dashboard')->assertRedirect(route('kata-laluan.tukar'));
    }

    public function test_kata_laluan_ditetapkan_admin_wajib_ditukar(): void
    {
        $admin = $this->buatPengguna(['SUPERADMIN']);
        $bahagian = $this->buatBahagian();
        Role::firstOrCreate(['kod_roles' => 'STAFF'], ['nama_roles' => 'Staf']);

        // Tambah kakitangan baharu
        $this->actingAs($admin)->post(route('users.store'), [
            'ic_pekerja' => '900101030003',
            'nama_staff' => 'Staf Baharu',
            'email' => 'staf.baharu@pkink.test',
            'bahagian_id' => $bahagian->bahagian_id,
            'password' => 'rahsia1',
        ])->assertRedirect(route('users.index'));

        $baharu = \App\Models\User::where('ic_pekerja', '900101030003')->first();
        $this->assertTrue($baharu->perlu_tukar_kata_laluan);
        $this->assertNull($baharu->kata_laluan_tamat_pada);

        // Admin tukar kata laluan orang lain: wajib tukar
        $lain = $this->buatPengguna(['STAFF'], ['bahagian_id' => $bahagian->bahagian_id]);
        $this->actingAs($admin)->put(route('users.update', $lain->user_id), [
            'ic_pekerja' => $lain->ic_pekerja,
            'nama_staff' => $lain->nama_staff,
            'email' => $lain->email,
            'bahagian_id' => $bahagian->bahagian_id,
            'password' => 'rahsia2',
        ])->assertRedirect(route('users.index'));
        $this->assertTrue($lain->fresh()->perlu_tukar_kata_laluan);

        // Admin tukar kata laluan sendiri: tidak dipaksa
        $this->actingAs($admin)->put(route('users.update', $admin->user_id), [
            'ic_pekerja' => $admin->ic_pekerja,
            'nama_staff' => $admin->nama_staff,
            'email' => $admin->email,
            'bahagian_id' => $bahagian->bahagian_id,
            'password' => 'rahsia3',
        ])->assertRedirect(route('users.index'));
        $this->assertFalse($admin->fresh()->perlu_tukar_kata_laluan);
    }

    public function test_tetapan_semula_melalui_emel_ikut_polisi_dan_kosongkan_bendera(): void
    {
        $user = $this->buatPengguna(['STAFF'], [
            'perlu_tukar_kata_laluan' => true,
            'kata_laluan_tamat_pada' => now()->subDay(),
        ]);
        $token = Password::createToken($user);

        $this->from('/reset-kata-laluan/'.$token)->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors(['password' => 'Kata laluan mesti sekurang-kurangnya 8 aksara.']);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Baharu123',
            'password_confirmation' => 'Baharu123',
        ])->assertRedirect('/');

        $user->refresh();
        $this->assertTrue(Hash::check('Baharu123', $user->password));
        $this->assertFalse($user->perlu_tukar_kata_laluan);
        $this->assertNull($user->kata_laluan_tamat_pada);
    }
}
