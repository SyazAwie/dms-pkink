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
            $t->string('jawatan', 150)->nullable();
            $t->string('email');
            $t->string('no_telefon', 20)->nullable();
            $t->string('password');
            $t->boolean('perlu_tukar_kata_laluan')->default(false);
            $t->timestamp('kata_laluan_tamat_pada')->nullable();
            $t->text('dua_faktor_rahsia')->nullable();
            $t->text('dua_faktor_kod_pemulihan')->nullable();
            $t->timestamp('dua_faktor_disahkan_pada')->nullable();
            $t->timestamp('dikunci_hingga')->nullable();
            $t->string('sebab_kunci')->nullable();
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

        // Diperlukan oleh loceng dalam layout (kiraan giliran kelulusan) dan Imbas & Arkib
        Schema::create('dokumen', function (Blueprint $t) {
            $t->id('dokumen_id');
            $t->unsignedBigInteger('jenis_dokumen_id')->nullable();
            $t->string('no_rujukan')->nullable();
            $t->date('tarikh_dokumen')->nullable();
            $t->string('perkara')->nullable();
            $t->string('status', 50)->nullable();
            $t->date('tarikh_tamat_simpanan')->nullable();
            $t->string('status_pelupusan', 30)->nullable();
            $t->dateTime('tarikh_pelupusan')->nullable();
            $t->unsignedBigInteger('pelupusan_oleh')->nullable();
            $t->string('catatan_pelupusan')->nullable();
            $t->unsignedBigInteger('bahagian_id')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        // Fasa P baki: sejarah borang (P4), delegasi (P5), log masuk (P13), pembetulan AI (P16)
        Schema::create('borang_sejarah', function (Blueprint $t) {
            $t->id('borang_sejarah_id');
            $t->unsignedBigInteger('borang_id');
            $t->string('tindakan', 30);
            $t->string('status_selepas', 50);
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unsignedBigInteger('bagi_pihak_id')->nullable();
            $t->text('ulasan')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('delegasi_kelulusan', function (Blueprint $t) {
            $t->id('delegasi_id');
            $t->unsignedBigInteger('pemberi_id');
            $t->unsignedBigInteger('penerima_id');
            $t->string('kod_peranan', 20);
            $t->date('tarikh_mula');
            $t->date('tarikh_tamat');
            $t->string('sebab')->nullable();
            $t->unsignedBigInteger('dicipta_oleh')->nullable();
            $t->timestamp('dibatalkan_pada')->nullable();
            $t->timestamps();
        });

        Schema::create('ip_disekat', function (Blueprint $t) {
            $t->id('ip_disekat_id');
            $t->string('alamat_ip', 45);
            $t->string('sebab')->nullable();
            $t->boolean('automatik')->default(false);
            $t->unsignedBigInteger('disekat_oleh')->nullable();
            $t->timestamp('tamat_pada')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('log_masuk', function (Blueprint $t) {
            $t->id('log_masuk_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->boolean('berjaya')->default(false);
            $t->string('sebab', 50)->nullable();
            $t->string('alamat_ip', 45)->nullable();
            $t->text('peranti_pengguna')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        Schema::create('pembetulan_ai', function (Blueprint $t) {
            $t->id('pembetulan_ai_id');
            $t->unsignedBigInteger('dokumen_id');
            $t->unsignedBigInteger('jenis_dokumen_id');
            $t->unsignedBigInteger('dokumen_field_id');
            $t->text('nilai_ai')->nullable();
            $t->text('nilai_disimpan')->nullable();
            $t->boolean('sama')->default(false);
            $t->string('model_ai', 100)->nullable();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        // Notifikasi dalam sistem (loceng topbar)
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->string('notifiable_type');
            $t->unsignedBigInteger('notifiable_id');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });

        // Langganan notifikasi peranti (Web Push)
        Schema::create('push_subscriptions', function (Blueprint $t) {
            $t->id();
            $t->morphs('subscribable');
            $t->string('endpoint', 1024)->unique();
            $t->string('public_key')->nullable();
            $t->string('auth_token')->nullable();
            $t->string('content_encoding')->nullable();
            $t->timestamps();
        });

        Schema::create('borang', function (Blueprint $t) {
            $t->id('borang_id');
            $t->unsignedBigInteger('dokumen_id');
            $t->unsignedBigInteger('pemohon_id')->nullable();
            $t->unsignedBigInteger('pegawai_penyokong_id')->nullable();
            $t->unsignedBigInteger('pegawai_pelulus_id')->nullable();
            $t->unsignedBigInteger('pegawai_penolak_id')->nullable();
            $t->string('status_permohonan')->default('Dalam Proses');
            $t->timestamp('tarikh_permohonan')->nullable();
            $t->timestamp('tarikh_sokongan')->nullable();
            $t->timestamp('tarikh_kelulusan')->nullable();
            $t->timestamp('tarikh_tolak')->nullable();
            $t->text('ulasan_penyokong')->nullable();
            $t->text('ulasan_pelulus')->nullable();
            $t->timestamps();
        });

        Schema::create('dokumen_data', function (Blueprint $t) {
            $t->id('dokumen_data_id');
            $t->unsignedBigInteger('dokumen_id');
            $t->unsignedBigInteger('dokumen_field_id');
            $t->text('nilai_data')->nullable();
            $t->timestamps();
        });

        Schema::create('dokumen_scan', function (Blueprint $t) {
            $t->id('dokumen_scan_id');
            $t->unsignedBigInteger('dokumen_id');
            $t->string('nama_fail');
            $t->string('lokasi_fail');
            $t->unsignedBigInteger('saiz_fail')->nullable();
            $t->string('jenis_mime', 100)->nullable();
            $t->char('hash_sha256', 64)->nullable();
            $t->timestamp('tarikh_scan')->nullable();
            $t->string('status_ocr', 30)->default('Pending');
            $t->timestamps();
        });

        Schema::create('dokumen_ocr', function (Blueprint $t) {
            $t->id('dokumen_ocr_id');
            $t->unsignedBigInteger('dokumen_scan_id');
            $t->longText('teks_dibaca')->nullable();
            $t->decimal('skor_padanan', 5, 2)->nullable();
            $t->string('status_semakan', 30)->default('Belum Disemak');
            $t->timestamps();
        });

        // Kategori dokumen (EAV): diperlukan oleh borang Imbas & Arkib
        Schema::create('jenis_dokumen', function (Blueprint $t) {
            $t->id('jenis_dokumen_id');
            $t->string('kod_dokumen', 50);
            $t->string('nama_dokumen');
            $t->string('kategori')->nullable();
            $t->unsignedSmallInteger('tempoh_simpanan_tahun')->nullable();
            $t->string('tindakan_tamat_tempoh', 30)->default('Semak Semula');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('dokumen_field', function (Blueprint $t) {
            $t->id('dokumen_field_id');
            $t->unsignedBigInteger('jenis_dokumen_id');
            $t->string('nama_field');
            $t->string('kod_field', 100);
            $t->string('jenis_data', 20)->default('teks');
            $t->text('pilihan')->nullable();
            $t->longText('tetapan')->nullable();
            $t->boolean('is_required')->default(false);
            $t->integer('susunan')->default(0);
            $t->timestamps();
            $t->softDeletes();
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
        ], array_intersect_key($atribut, array_flip(['ic_pekerja', 'nama_staff', 'jawatan', 'email', 'no_telefon', 'password', 'bahagian_id']))));

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
