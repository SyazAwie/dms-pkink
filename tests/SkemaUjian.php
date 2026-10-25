<?php

namespace Tests;

use App\Models\Bahagian;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Skema minimum jadual domain untuk ujian (SQLite :memory:).
 *
 * Pangkalan data sebenar diurus manual dalam HeidiSQL dan tiada Laravel
 * migration, jadi ujian yang menyentuh model domain mencipta jadual yang
 * diperlukan sendiri di sini. Lajur mengikut skema MySQL sebenar setakat
 * yang diperlukan oleh ujian (tanpa lajur janaan / FK).
 */
trait SkemaUjian
{
    protected function sediakanSkema(): void
    {
        Schema::create('bahagian', function (Blueprint $t) {
            $t->id('bahagian_id');
            $t->string('kod_bahagian', 50);
            $t->string('nama_bahagian');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id('user_id');
            $t->string('ic_pekerja', 20);
            $t->string('nama_staff');
            $t->string('email');
            $t->string('password');
            $t->boolean('perlu_tukar_kata_laluan')->default(false);
            $t->timestamp('kata_laluan_tamat_pada')->nullable();
            $t->unsignedBigInteger('bahagian_id')->nullable();
            $t->rememberToken();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->id('roles_id');
            $t->string('kod_roles');
            $t->string('nama_roles');
            $t->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $t) {
            $t->id('user_roles_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('role_id');
        });

        Schema::create('log_audit', function (Blueprint $t) {
            $t->id('log_audit_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('tindakan', 100);
            $t->string('nama_jadual', 100);
            $t->unsignedBigInteger('rekod_id')->nullable();
            $t->longText('data_lama')->nullable();
            $t->longText('data_baharu')->nullable();
            $t->string('alamat_ip', 45)->nullable();
            $t->text('peranti_pengguna')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('pendaftaran_staf', function (Blueprint $t) {
            $t->id('pendaftaran_staf_id');
            $t->string('ic_pekerja', 20);
            $t->string('nama_staff');
            $t->string('email');
            $t->unsignedBigInteger('bahagian_id');
            $t->string('status', 20)->default('Menunggu');
            $t->text('sebab_tolak')->nullable();
            $t->unsignedBigInteger('disemak_oleh')->nullable();
            $t->timestamp('disemak_pada')->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('alamat_ip', 45)->nullable();
            $t->timestamps();
        });

        // Diperlukan oleh loceng dalam layout (kiraan giliran kelulusan)
        Schema::create('dokumen', function (Blueprint $t) {
            $t->id('dokumen_id');
            $t->unsignedBigInteger('jenis_dokumen_id')->nullable();
            $t->string('no_rujukan')->nullable();
            $t->unsignedBigInteger('bahagian_id')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('borang', function (Blueprint $t) {
            $t->id('borang_id');
            $t->unsignedBigInteger('dokumen_id');
            $t->string('status_permohonan')->default('Dalam Proses');
            $t->timestamps();
        });
    }

    protected function buatBahagian(string $kod = 'DIG', string $nama = 'Bahagian Digital'): Bahagian
    {
        return Bahagian::create(['kod_bahagian' => $kod, 'nama_bahagian' => $nama]);
    }

    /**
     * Cipta pengguna dengan peranan (kod_roles). Kata laluan lalai: Lama1234.
     */
    protected function buatPengguna(array $peranan = ['STAFF'], array $atribut = []): User
    {
        static $kiraan = 0;
        $kiraan++;

        // Akhiran huruf (A, B, ... AA) supaya nama lulus PeraturanNama (tiada digit)
        $akhiran = '';
        for ($n = $kiraan; $n > 0; $n = intdiv($n - 1, 26)) {
            $akhiran = chr(65 + ($n - 1) % 26) . $akhiran;
        }

        $user = new User(array_merge([
            'ic_pekerja' => str_pad((string) $kiraan, 12, '9', STR_PAD_LEFT),
            'nama_staff' => "Pengguna Ujian {$akhiran}",
            'email' => "ujian{$kiraan}@pkink.test",
            'password' => Hash::make('Lama1234'),
        ], array_intersect_key($atribut, array_flip(['ic_pekerja', 'nama_staff', 'email', 'password', 'bahagian_id']))));

        foreach (['perlu_tukar_kata_laluan', 'kata_laluan_tamat_pada'] as $lajur) {
            if (array_key_exists($lajur, $atribut)) {
                $user->{$lajur} = $atribut[$lajur];
            }
        }
        $user->save();

        $idPeranan = collect($peranan)->map(fn ($kod) => Role::firstOrCreate(
            ['kod_roles' => $kod],
            ['nama_roles' => ucfirst(strtolower($kod))]
        )->roles_id)->all();
        $user->roles()->attach($idPeranan);

        return $user->fresh();
    }
}
