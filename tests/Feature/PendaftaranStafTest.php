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
use Illuminate\Support\Facades\RateLimiter;
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

    public function test_respons_sama_untuk_permohonan_baharu_dan_pendua(): void
    {
        $this->buatPengguna(['STAFF'], ['ic_pekerja' => '950505031111', 'email' => 'akaun@pkink.test']);
        $this->buatPermohonan(['ic_pekerja' => '950505032222', 'email' => 'menunggu@pkink.test']);

        $kes = [
            'baharu' => $this->dataMohon(['ic_pekerja' => '950505033333', 'email' => 'baharu@pkink.test']),
            'pendua_akaun_ic' => $this->dataMohon(['ic_pekerja' => '950505031111', 'email' => 'lain1@pkink.test']),
            'pendua_akaun_emel' => $this->dataMohon(['ic_pekerja' => '950505034444', 'email' => 'akaun@pkink.test']),
            'pendua_menunggu' => $this->dataMohon(['ic_pekerja' => '950505035555', 'email' => 'menunggu@pkink.test']),
        ];

        $respons = [];
        foreach ($kes as $nama => $data) {
            $r = $this->from('/')->post(route('pendaftaran.simpan'), $data);
            $r->assertRedirect(route('login'))->assertSessionHasNoErrors();
            $respons[$nama] = session('success');
        }

        // Mesej berjaya sama tepat untuk semua kes: tiada petunjuk IC/emel berdaftar
        $this->assertCount(1, array_unique($respons));
        $this->assertStringContainsString('Permohonan pendaftaran anda telah dihantar', reset($respons));

        // Baharu + 2 pendua akaun disimpan (untuk ditanda kepada admin); pendua Menunggu tidak
        $this->assertSame(4, PendaftaranStaf::count());
        $this->assertSame(1, PendaftaranStaf::where('email', 'menunggu@pkink.test')->count());
        $this->assertTrue(LogAudit::where('tindakan', 'Mohon Pendaftaran (Pendua Menunggu)')->exists());
    }

    public function test_senarai_admin_tanda_pendua_dan_nyahaktif_lulus(): void
    {
        $this->buatPengguna(['STAFF'], ['ic_pekerja' => '950505031111', 'email' => 'akaun@pkink.test']);
        $penduaIc = $this->buatPermohonan(['ic_pekerja' => '950505031111', 'email' => 'lain@pkink.test', 'nama_staff' => 'Pendua IC']);
        $penduaEmel = $this->buatPermohonan(['ic_pekerja' => '950505032222', 'email' => 'AKAUN@pkink.test', 'nama_staff' => 'Pendua Emel']);
        $biasa = $this->buatPermohonan(['ic_pekerja' => '950505033333', 'email' => 'biasa@pkink.test', 'nama_staff' => 'Pemohon Biasa']);

        $html = $this->actingAs($this->buatPengguna(['ADMIN']))
            ->get(route('pendaftaran.index'))
            ->assertOk()
            ->assertSee('No. KP sudah berdaftar')
            ->assertSee('Emel sudah berdaftar')
            ->getContent();

        // Butang Lulus aktif (dengan URL) hanya untuk permohonan biasa
        $this->assertStringContainsString(route('pendaftaran.lulus', $biasa->pendaftaran_staf_id), $html);
        $this->assertStringNotContainsString(route('pendaftaran.lulus', $penduaIc->pendaftaran_staf_id), $html);
        $this->assertStringNotContainsString(route('pendaftaran.lulus', $penduaEmel->pendaftaran_staf_id), $html);
        $this->assertSame(2, substr_count($html, 'No. KP / emel sudah berdaftar. Sila tolak permohonan ini.'));
    }

    public function test_pelayan_tetap_tolak_kelulusan_pendua(): void
    {
        Notification::fake();
        $this->buatPengguna(['STAFF'], ['ic_pekerja' => '950505031111']);
        $p = $this->buatPermohonan(['ic_pekerja' => '950505031111']);

        $this->actingAs($this->buatPengguna(['ADMIN']))
            ->post(route('pendaftaran.lulus', $p->pendaftaran_staf_id))
            ->assertSessionHas('error');

        $this->assertSame('Menunggu', $p->fresh()->status);
        Notification::assertNothingSent();
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

    public function test_nama_sah_diterima_dan_nama_berbahaya_ditolak(): void
    {
        $sah = ['Ahmad bin Ali', 'Siti a/p Muthu', 'Lee @ Ali', "O'Brien", 'Nur’Aini binti Abu', 'Mohd. Faiz Al-Amin'];
        foreach ($sah as $i => $nama) {
            RateLimiter::clear('daftar-staf|127.0.0.1'); // ujian validasi nama, bukan had kadar
            $this->post(route('pendaftaran.simpan'), $this->dataMohon([
                'ic_pekerja' => '95050503100' . $i, 'email' => "sah{$i}@pkink.test", 'nama_staff' => $nama,
            ]))->assertSessionHasNoErrors();
        }
        $this->assertSame(count($sah), PendaftaranStaf::count());

        RateLimiter::clear('daftar-staf|127.0.0.1');
        $mesej = "Nama hanya boleh mengandungi huruf, ruang dan aksara ' . - @ /";
        foreach (['[Klik di sini](https://evil.test)', 'Ali 123', '<b>Ali</b>'] as $nama) {
            $this->from('/')->post(route('pendaftaran.simpan'), $this->dataMohon(['nama_staff' => $nama]))
                ->assertSessionHasErrorsIn('pendaftaran', ['nama_staff' => $mesej]);
        }
        $this->assertSame(count($sah), PendaftaranStaf::count());
    }

    public function test_emel_tidak_mengandungi_pautan_suntikan_markdown(): void
    {
        // Rekod lama / sebab tolak ditulis admin: escape mesti berlaku walaupun validasi dilangkau
        $p = $this->buatPermohonan(['nama_staff' => "O'Brien [Klik](https://evil.test)"]);
        $p->sebab_tolak = "Tidak lengkap.\n[Sahkan semula](https://evil.test/sah)";
        $notifiable = Notification::route('mail', $p->email);

        $htmlLulus = (string) (new PendaftaranDiluluskan($p, 'Sementara12', 72))->toMail($notifiable)->render();
        $htmlTolak = (string) (new PendaftaranDitolak($p))->toMail($notifiable)->render();

        foreach ([$htmlLulus, $htmlTolak] as $html) {
            $this->assertStringNotContainsString('href="https://evil.test', $html);
            $this->assertStringContainsString('[Klik](https://evil.test)', html_entity_decode($html, ENT_QUOTES));
            $this->assertStringContainsString("O'Brien", html_entity_decode($html, ENT_QUOTES));
        }
        $this->assertStringContainsString('[Sahkan semula](https://evil.test/sah)', html_entity_decode($htmlTolak, ENT_QUOTES));
    }

    public function test_honeypot_diisi_tidak_disimpan(): void
    {
        $this->post(route('pendaftaran.simpan'), $this->dataMohon(['laman_web' => 'http://spam.test']))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertSame(0, PendaftaranStaf::count());
    }

    public function test_had_lima_cubaan_termasuk_yang_gagal_validasi(): void
    {
        // 4 cubaan gagal validasi format + 1 permohonan sah = 5 cubaan
        for ($i = 1; $i <= 4; $i++) {
            $this->from('/')->post(route('pendaftaran.simpan'), $this->dataMohon(['ic_pekerja' => '123']))
                ->assertSessionHasErrorsIn('pendaftaran', ['ic_pekerja']);
        }
        $this->post(route('pendaftaran.simpan'), $this->dataMohon(['ic_pekerja' => '950505030001', 'email' => 'staf1@pkink.test']))
            ->assertSessionHas('success');

        // Cubaan ke-6 disekat walaupun data sah
        $this->from('/')->post(route('pendaftaran.simpan'), $this->dataMohon(['ic_pekerja' => '950505030002', 'email' => 'staf2@pkink.test']))
            ->assertSessionHasErrorsIn('pendaftaran', ['pendaftaran']);

        $this->assertSame(1, PendaftaranStaf::count());
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
            // Semak HTML yang dirender (teks mentah kini mengandungi escape Markdown)
            $kandungan = html_entity_decode((string) $notifikasi->toMail($notifiable)->render(), ENT_QUOTES);

            return $notifiable->routes['mail'] === 'aminah@pkink.test'
                && str_contains($kandungan, 'Bukan kakitangan PKINK.');
        });
    }
}
