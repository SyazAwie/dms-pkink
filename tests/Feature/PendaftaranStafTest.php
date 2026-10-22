<?php

namespace Tests\Feature;

use App\Models\LogAudit;
use App\Models\PendaftaranStaf;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PendaftaranDiluluskan;
use App\Notifications\PendaftaranDitolak;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Pendaftaran staf baharu: permohonan awam, pengesahan admin, emel.
 */
class PendaftaranStafTest extends TestCase
{
    use SkemaUjian;

    private int $bahagianId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->bahagianId = $this->buatBahagian('KEW', 'Bahagian Kewangan')->bahagian_id;
        foreach (['SUPERADMIN', 'ADMIN', 'STAFF', 'PENYOKONG'] as $kod) {
            Role::firstOrCreate(['kod_roles' => $kod], ['nama_roles' => ucfirst(strtolower($kod))]);
        }
    }

    private function dataMohon(array $ganti = []): array
    {
        return array_merge([
            'ic_pekerja' => '950505035555',
            'nama_staff' => 'Aminah binti Ali',
            'email' => 'aminah@pkink.test',
            'bahagian_id' => $this->bahagianId,
        ], $ganti);
    }

    private function buatPermohonan(array $ganti = []): PendaftaranStaf
    {
        return PendaftaranStaf::create($this->dataMohon($ganti));
    }

    /** Ambil kata laluan sementara daripada kandungan emel kelulusan. */
    private function kataLaluanDalamEmel(PendaftaranDiluluskan $notifikasi, $notifiable): ?string
    {
        foreach ($notifikasi->toMail($notifiable)->introLines as $baris) {
            if (preg_match('/Kata Laluan Sementara:\*\* (\S+)/', $baris, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    // ---------------------------------------------------------------- Mohon

    public function test_halaman_log_masuk_papar_butang_dan_modal_daftar(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Daftar Akaun Baharu')
            ->assertSee('Bahagian Kewangan');
    }

    public function test_permohonan_sah_disimpan_menunggu_dan_dilog(): void
    {
        $this->post(route('pendaftaran.simpan'), $this->dataMohon())
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $p = PendaftaranStaf::first();
        $this->assertSame('Menunggu', $p->status);
        $this->assertSame('950505035555', $p->ic_pekerja);
        $this->assertSame(0, User::count());

        $log = LogAudit::where('tindakan', 'Mohon Pendaftaran')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
    }

    public function test_permohonan_ditolak_jika_ic_atau_emel_sudah_wujud(): void
    {
        $this->buatPengguna(['STAFF'], ['ic_pekerja' => '950505035555']);
        $this->from('/')->post(route('pendaftaran.simpan'), $this->dataMohon())
            ->assertSessionHasErrorsIn('pendaftaran', ['ic_pekerja']);

        $this->buatPermohonan(['ic_pekerja' => '950505036666', 'email' => 'menunggu@pkink.test']);
        $this->from('/')->post(route('pendaftaran.simpan'), $this->dataMohon(['ic_pekerja' => '950505037777', 'email' => 'menunggu@pkink.test']))
            ->assertSessionHasErrorsIn('pendaftaran', ['email']);

        $this->assertSame(1, PendaftaranStaf::count());
    }

    public function test_validasi_medan_dengan_mesej_bm(): void
    {
        $this->from('/')->post(route('pendaftaran.simpan'), $this->dataMohon(['ic_pekerja' => '95-0505', 'email' => 'bukan-emel', 'bahagian_id' => 999]))
            ->assertSessionHasErrorsIn('pendaftaran', [
                'ic_pekerja' => 'No. Kad Pengenalan mesti 12 digit nombor tanpa sengkang (-).',
                'email' => 'Format alamat emel tidak sah.',
                'bahagian_id' => 'Bahagian yang dipilih tidak sah.',
            ]);
    }

    public function test_honeypot_diisi_tidak_disimpan(): void
    {
        $this->post(route('pendaftaran.simpan'), $this->dataMohon(['laman_web' => 'http://spam.test']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertSame(0, PendaftaranStaf::count());
    }

    public function test_had_tiga_permohonan_bagi_setiap_ip(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->post(route('pendaftaran.simpan'), $this->dataMohon(['ic_pekerja' => "95050503000{$i}", 'email' => "staf{$i}@pkink.test"]))
                ->assertSessionHas('success');
        }

        $this->from('/')->post(route('pendaftaran.simpan'), $this->dataMohon(['ic_pekerja' => '950505030004', 'email' => 'staf4@pkink.test']))
            ->assertSessionHasErrorsIn('pendaftaran', ['pendaftaran']);

        $this->assertSame(3, PendaftaranStaf::count());
    }

    // ------------------------------------------------------------- Akses

    public function test_hanya_superadmin_dan_admin_boleh_buka_pengesahan(): void
    {
        $this->buatPermohonan();

        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('pendaftaran.index'))->assertForbidden();
        $this->actingAs($this->buatPengguna(['PENYOKONG']))->get(route('pendaftaran.index'))->assertForbidden();

        $this->actingAs($this->buatPengguna(['ADMIN']))
            ->get(route('pendaftaran.index'))
            ->assertOk()
            ->assertSee('Aminah binti Ali')
            ->assertSee('Pendaftaran staf'); // item dalam dropdown loceng
    }

    // ------------------------------------------------------------- Lulus

    public function test_superadmin_lulus_dengan_peranan_dipilih(): void
    {
        Notification::fake();
        $p = $this->buatPermohonan();
        $idPenyokong = Role::where('kod_roles', 'PENYOKONG')->value('roles_id');

        $this->actingAs($this->buatPengguna(['SUPERADMIN']))
            ->post(route('pendaftaran.lulus', $p->pendaftaran_staf_id), ['roles' => [$idPenyokong]])
            ->assertSessionHas('success');

        $user = User::where('ic_pekerja', '950505035555')->first();
        $this->assertNotNull($user);
        $this->assertSame(['PENYOKONG'], $user->kodPeranan());
        $this->assertTrue($user->perlu_tukar_kata_laluan);
        $this->assertTrue($user->kata_laluan_tamat_pada->between(now()->addHours(71), now()->addHours(73)));
        $this->assertSame($this->bahagianId, (int) $user->bahagian_id);

        $p->refresh();
        $this->assertSame('Diluluskan', $p->status);
        $this->assertSame($user->user_id, (int) $p->user_id);
        $this->assertTrue(LogAudit::where('tindakan', 'Lulus Pendaftaran')->exists());

        Notification::assertSentOnDemand(PendaftaranDiluluskan::class, function ($notifikasi, $saluran, $notifiable) use ($user) {
            $kataLaluan = $this->kataLaluanDalamEmel($notifikasi, $notifiable);

            return $notifiable->routes['mail'] === 'aminah@pkink.test'
                && $kataLaluan !== null
                && Hash::check($kataLaluan, $user->password);
        });
    }

    public function test_superadmin_mesti_pilih_sekurang_kurangnya_satu_peranan(): void
    {
        Notification::fake();
        $p = $this->buatPermohonan();

        $this->actingAs($this->buatPengguna(['SUPERADMIN']))
            ->from(route('pendaftaran.index'))
            ->post(route('pendaftaran.lulus', $p->pendaftaran_staf_id), [])
            ->assertSessionHasErrors(['roles' => 'Pilih sekurang-kurangnya satu peranan.']);

        $this->assertSame('Menunggu', $p->fresh()->status);
    }

    public function test_admin_lulus_sentiasa_beri_staff(): void
    {
        Notification::fake();
        $p = $this->buatPermohonan();
        $idSuperadmin = Role::where('kod_roles', 'SUPERADMIN')->value('roles_id');

        $this->actingAs($this->buatPengguna(['ADMIN']))
            ->post(route('pendaftaran.lulus', $p->pendaftaran_staf_id), ['roles' => [$idSuperadmin]])
            ->assertSessionHas('success');

        $user = User::where('ic_pekerja', '950505035555')->first();
        $this->assertSame(['STAFF'], $user->kodPeranan());
    }

    public function test_emel_gagal_batalkan_kelulusan(): void
    {
        // SMTP ke port yang tiada pendengar: penghantaran gagal serta-merta
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 2,
            'logging.default' => 'null',
        ]);
        $p = $this->buatPermohonan();

        $this->actingAs($this->buatPengguna(['SUPERADMIN']))
            ->post(route('pendaftaran.lulus', $p->pendaftaran_staf_id), ['roles' => [Role::where('kod_roles', 'STAFF')->value('roles_id')]])
            ->assertSessionHas('error');

        $this->assertNull(User::where('ic_pekerja', '950505035555')->first());
        $this->assertSame('Menunggu', $p->fresh()->status);
        $this->assertFalse(LogAudit::where('tindakan', 'Lulus Pendaftaran')->exists());
    }

    public function test_permohonan_yang_sudah_diproses_tidak_boleh_dilulus_semula(): void
    {
        Notification::fake();
        $p = $this->buatPermohonan();
        $p->status = 'Ditolak';
        $p->save();

        $this->actingAs($this->buatPengguna(['ADMIN']))
            ->post(route('pendaftaran.lulus', $p->pendaftaran_staf_id))
            ->assertSessionHas('error', 'Permohonan ini telah Ditolak dan tidak boleh diproses lagi.');

        $this->assertSame(1, User::count()); // admin sahaja
        Notification::assertNothingSent();
    }

    // ------------------------------------------------------------- Tolak

    public function test_tolak_wajib_sebab_dan_emel_mengandungi_sebab(): void
    {
        Notification::fake();
        $p = $this->buatPermohonan();
        $admin = $this->buatPengguna(['ADMIN']);

        $this->actingAs($admin)
            ->from(route('pendaftaran.index'))
            ->post(route('pendaftaran.tolak', $p->pendaftaran_staf_id), ['sebab_tolak' => ''])
            ->assertSessionHasErrors(['sebab_tolak' => 'Sila nyatakan sebab penolakan.']);
        $this->assertSame('Menunggu', $p->fresh()->status);

        $this->actingAs($admin)
            ->post(route('pendaftaran.tolak', $p->pendaftaran_staf_id), ['sebab_tolak' => 'Bukan kakitangan PKINK.'])
            ->assertSessionHas('success');

        $p->refresh();
        $this->assertSame('Ditolak', $p->status);
        $this->assertSame('Bukan kakitangan PKINK.', $p->sebab_tolak);
        $this->assertSame($admin->user_id, (int) $p->disemak_oleh);
        $this->assertSame(1, User::count()); // tiada akaun baharu

        Notification::assertSentOnDemand(PendaftaranDitolak::class, function ($notifikasi, $saluran, $notifiable) {
            $kandungan = implode(' ', $notifikasi->toMail($notifiable)->introLines);

            return $notifiable->routes['mail'] === 'aminah@pkink.test'
                && str_contains($kandungan, 'Bukan kakitangan PKINK.');
        });
    }
}
