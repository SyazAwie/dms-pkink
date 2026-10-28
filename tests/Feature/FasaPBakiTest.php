<?php

namespace Tests\Feature;

use App\Models\Bahagian;
use App\Models\Borang;
use App\Models\BorangSejarah;
use App\Models\DelegasiKelulusan;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\LogMasuk;
use App\Models\User;
use App\Support\DuaFaktor;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Fasa P baki: sejarah borang (P4), skop bahagian & delegasi kelulusan (P5).
 */
class FasaPBakiTest extends TestCase
{
    use SkemaUjian;

    private Bahagian $digital;

    private Bahagian $kewangan;

    private User $staf;

    private User $penyokongDigital;

    private User $pelulus;

    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->digital = $this->buatBahagian('DIG', 'Bahagian Digital');
        $this->kewangan = $this->buatBahagian('KEW', 'Bahagian Kewangan');
        $this->staf = $this->buatPengguna(['STAFF'], ['bahagian_id' => $this->digital->bahagian_id]);
        $this->penyokongDigital = $this->buatPengguna(['PENYOKONG'], ['nama_staff' => 'Nor Hidayah', 'bahagian_id' => $this->digital->bahagian_id]);
        $this->pelulus = $this->buatPengguna(['PELULUS'], ['bahagian_id' => $this->kewangan->bahagian_id]);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon Komputer']);
    }

    private function dokumen(?Bahagian $bahagian, string $rujukan = 'BPK/2026/0001', ?User $pemohon = null): Dokumen
    {
        return Dokumen::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => $rujukan,
            'tarikh_dokumen' => '2026-10-27', 'bahagian_id' => $bahagian?->bahagian_id, 'created_by' => ($pemohon ?? $this->staf)->user_id]);
    }

    private function hantar(Dokumen $d, ?User $pemohon = null): void
    {
        $this->actingAs($pemohon ?? $this->staf)->post(route('kelulusan.hantar', $d->dokumen_id))->assertRedirect();
    }

    // ---------------- P4: sejarah ----------------

    public function test_sejarah_merekod_setiap_tindakan_termasuk_hantar_semula(): void
    {
        $d = $this->dokumen($this->digital);
        $this->hantar($d);
        $this->actingAs($this->penyokongDigital)->post(route('kelulusan.sokong', $d->dokumen_id), ['ulasan_penyokong' => 'Disokong.']);
        $this->actingAs($this->pelulus)->post(route('kelulusan.tolak', $d->dokumen_id), ['ulasan_pelulus' => 'Tiada bajet.']);
        $this->hantar($d);
        $this->actingAs($this->penyokongDigital)->post(route('kelulusan.sokong', $d->dokumen_id));
        $this->actingAs($this->pelulus)->post(route('kelulusan.lulus', $d->dokumen_id), ['ulasan_pelulus' => 'Lulus.']);

        $sejarah = Borang::firstWhere('dokumen_id', $d->dokumen_id)->sejarah;
        $this->assertSame(['Hantar', 'Sokong', 'Tolak', 'Hantar Semula', 'Sokong', 'Lulus'], $sejarah->pluck('tindakan')->all());
        $this->assertSame([null, 'Disokong.', 'Tiada bajet.', null, null, 'Lulus.'], $sejarah->pluck('ulasan')->all());
        $this->assertSame($this->pelulus->user_id, $sejarah[2]->user_id);
        $this->assertSame('Ditolak', $sejarah[2]->status_selepas);

        // Ulasan penolakan lama hilang dari `borang` tetapi kekal dalam sejarah & Butiran
        $this->actingAs($this->staf)->get(route('dokumen.show', $d->dokumen_id))->assertOk()
            ->assertSee('Sejarah Permohonan (6)')->assertSee('"Tiada bajet."', false);
    }

    public function test_arkib_terus_direkod_dan_status_lain_tidak_menambah_sejarah(): void
    {
        $d = $this->dokumen($this->digital);
        $this->actingAs($this->staf);
        $borang = Borang::create(['dokumen_id' => $d->dokumen_id, 'pemohon_id' => $this->staf->user_id, 'status_permohonan' => 'Diluluskan',
            'tarikh_permohonan' => now(), 'tarikh_kelulusan' => now(), 'ulasan_pelulus' => 'Diarkibkan terus.']);
        $borang->update(['ulasan_pelulus' => 'Diubah tanpa tukar status']);

        $this->assertSame([['Arkib Terus', 'Diarkibkan terus.']],
            BorangSejarah::all()->map(fn ($s) => [$s->tindakan, $s->ulasan])->all());
    }

    // ---------------- P5: skop bahagian ----------------

    public function test_penyokong_hanya_bahagian_sendiri_jika_bahagian_lain_ada_penyokong(): void
    {
        $penyokongKewangan = $this->buatPengguna(['PENYOKONG'], ['bahagian_id' => $this->kewangan->bahagian_id]);
        $stafKewangan = $this->buatPengguna(['STAFF'], ['bahagian_id' => $this->kewangan->bahagian_id]);
        $dDigital = $this->dokumen($this->digital, 'BPK/2026/0001');
        $dKewangan = $this->dokumen($this->kewangan, 'BPK/2026/0002', $stafKewangan);
        $this->hantar($dDigital);
        $this->hantar($dKewangan, $stafKewangan);

        $this->actingAs($this->penyokongDigital)->get(route('kelulusan.index'))->assertOk()
            ->assertSee('BPK/2026/0001')->assertDontSee('BPK/2026/0002');
        $this->actingAs($this->penyokongDigital)->get(route('dokumen.show', $dKewangan->dokumen_id))->assertOk()
            ->assertDontSee(route('kelulusan.sokong', $dKewangan->dokumen_id));
        $this->actingAs($this->penyokongDigital)->get(route('dokumen.show', $dDigital->dokumen_id))
            ->assertSee(route('kelulusan.sokong', $dDigital->dokumen_id));
        $this->actingAs($this->penyokongDigital)->post(route('kelulusan.sokong', $dKewangan->dokumen_id))->assertForbidden();
        $this->actingAs($this->penyokongDigital)->post(route('kelulusan.tolak', $dKewangan->dokumen_id), ['ulasan_penyokong' => 'x'])->assertForbidden();
        $this->actingAs($penyokongKewangan)->post(route('kelulusan.sokong', $dKewangan->dokumen_id))->assertRedirect();

        // Notifikasi "menunggu sokongan" hanya kepada PENYOKONG bahagian dokumen
        $this->assertSame(1, $this->penyokongDigital->fresh()->notifications->where('data.jenis', 'permohonan_baharu')->count());
        $this->assertSame(1, $penyokongKewangan->fresh()->notifications->where('data.jenis', 'permohonan_baharu')->count());
    }

    public function test_bahagian_tanpa_penyokong_disokong_oleh_semua_penyokong(): void
    {
        $stafKewangan = $this->buatPengguna(['STAFF'], ['bahagian_id' => $this->kewangan->bahagian_id]);
        $d = $this->dokumen($this->kewangan, 'BPK/2026/0002', $stafKewangan);
        $this->hantar($d, $stafKewangan);

        $this->assertSame(1, $this->penyokongDigital->fresh()->notifications->count());
        $this->actingAs($this->penyokongDigital)->get(route('kelulusan.index'))->assertSee('BPK/2026/0002');
        $this->actingAs($this->penyokongDigital)->post(route('kelulusan.sokong', $d->dokumen_id))->assertRedirect();
        $this->assertSame('Disokong', Borang::firstWhere('dokumen_id', $d->dokumen_id)->status_permohonan);
    }

    // ---------------- P5: delegasi ----------------

    public function test_delegasi_beri_peranan_sementara_dan_rekod_bagi_pihak(): void
    {
        $pengganti = $this->buatPengguna(['STAFF'], ['nama_staff' => 'Pengganti Cuti', 'bahagian_id' => $this->kewangan->bahagian_id]);
        $this->assertFalse($pengganti->hasRole('PENYOKONG'));

        $this->actingAs($this->penyokongDigital)->post(route('delegasi.store'), [
            'pemberi_id' => $this->penyokongDigital->user_id, 'kod_peranan' => 'PENYOKONG', 'penerima_id' => $pengganti->user_id,
            'tarikh_mula' => today()->toDateString(), 'tarikh_tamat' => today()->addDays(5)->toDateString(), 'sebab' => 'Cuti tahunan',
        ])->assertRedirect(route('delegasi.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('log_audit', ['tindakan' => 'Cipta Delegasi']);
        $this->assertSame(['delegasi'], $pengganti->fresh()->notifications->pluck('data.jenis')->all());

        $pengganti = $pengganti->fresh();
        $this->assertTrue($pengganti->hasRole('PENYOKONG'));
        $this->assertSame(['STAFF'], $pengganti->kodPerananSendiri());

        // Pengganti bertindak dengan skop bahagian pemberi (Digital), bukan bahagiannya sendiri
        $d = $this->dokumen($this->digital);
        $this->hantar($d);
        $this->actingAs($pengganti)->get(route('kelulusan.index'))->assertOk()->assertSee('BPK/2026/0001');
        $this->actingAs($pengganti)->post(route('kelulusan.sokong', $d->dokumen_id))->assertRedirect();

        $sokong = BorangSejarah::where('tindakan', 'Sokong')->first();
        $this->assertSame($pengganti->user_id, $sokong->user_id);
        $this->assertSame($this->penyokongDigital->user_id, $sokong->bagi_pihak_id);
        $this->actingAs($this->staf)->get(route('dokumen.show', $d->dokumen_id))->assertSee('bagi pihak Nor Hidayah');
    }

    public function test_delegasi_tamat_atau_dibatalkan_tidak_beri_peranan(): void
    {
        $pengganti = $this->buatPengguna(['STAFF']);
        $buat = fn ($mula, $tamat) => DelegasiKelulusan::create(['pemberi_id' => $this->penyokongDigital->user_id, 'penerima_id' => $pengganti->user_id,
            'kod_peranan' => 'PENYOKONG', 'tarikh_mula' => $mula, 'tarikh_tamat' => $tamat]);

        $buat(today()->subDays(10), today()->subDay());
        $buat(today()->addDay(), today()->addDays(3));
        $this->assertFalse($pengganti->fresh()->hasRole('PENYOKONG'));

        $aktif = $buat(today(), today()->addDay());
        $this->assertTrue($pengganti->fresh()->hasRole('PENYOKONG'));

        $this->actingAs($this->penyokongDigital)->post(route('delegasi.batal', $aktif->delegasi_id))->assertRedirect();
        $this->assertNotNull($aktif->fresh()->dibatalkan_pada);
        $this->assertFalse($pengganti->fresh()->hasRole('PENYOKONG'));
    }

    public function test_validasi_dan_kebenaran_delegasi(): void
    {
        $lain = $this->buatPengguna(['STAFF']);
        $sah = ['kod_peranan' => 'PENYOKONG', 'penerima_id' => $lain->user_id,
            'tarikh_mula' => today()->toDateString(), 'tarikh_tamat' => today()->addDays(2)->toDateString()];

        // Bukan pentadbir: hanya untuk diri sendiri
        $this->actingAs($this->penyokongDigital)->post(route('delegasi.store'), $sah + ['pemberi_id' => $this->pelulus->user_id])->assertForbidden();
        // Peranan yang tidak dipegang
        $this->actingAs($this->penyokongDigital)->post(route('delegasi.store'), ['kod_peranan' => 'PELULUS', 'pemberi_id' => $this->penyokongDigital->user_id] + $sah)
            ->assertSessionHasErrors('kod_peranan');
        // Diri sendiri sebagai pengganti
        $this->actingAs($this->penyokongDigital)->post(route('delegasi.store'), ['penerima_id' => $this->penyokongDigital->user_id, 'pemberi_id' => $this->penyokongDigital->user_id] + $sah)
            ->assertSessionHasErrors('penerima_id');
        // Bertindih
        $this->actingAs($this->penyokongDigital)->post(route('delegasi.store'), $sah + ['pemberi_id' => $this->penyokongDigital->user_id])->assertSessionHasNoErrors();
        $this->actingAs($this->penyokongDigital)->post(route('delegasi.store'), $sah + ['pemberi_id' => $this->penyokongDigital->user_id])
            ->assertSessionHasErrors('tarikh_mula');
        // STAFF tiada akses halaman; pentadbir boleh untuk sesiapa
        $this->actingAs($this->staf)->get(route('delegasi.index'))->assertForbidden();
        $admin = $this->buatPengguna(['ADMIN']);
        $this->actingAs($admin)->post(route('delegasi.store'), ['kod_peranan' => 'PELULUS', 'pemberi_id' => $this->pelulus->user_id] + $sah)
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->get(route('delegasi.index'))->assertOk()->assertSee('Nor Hidayah')->assertSee('Pelulus');
    }

    // ---------------- P12: tempoh simpanan & pelupusan ----------------

    private function dokumenBertarikh(string $rujukan, string $tarikh): Dokumen
    {
        return Dokumen::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => $rujukan,
            'tarikh_dokumen' => $tarikh, 'created_by' => $this->staf->user_id]);
    }

    public function test_tarikh_tamat_simpanan_dikira_dan_dikira_semula_ikut_kategori(): void
    {
        $tanpaTempoh = $this->dokumenBertarikh('BPK/2020/0001', '2020-01-15');
        $this->assertNull($tanpaTempoh->tarikh_tamat_simpanan);

        $this->jenis->update(['tempoh_simpanan_tahun' => 5]);
        $d = $this->dokumenBertarikh('BPK/2020/0002', '2020-01-15');
        $this->assertSame('2025-01-15', $d->fresh()->tarikh_tamat_simpanan->toDateString());
        $d->update(['tarikh_dokumen' => '2021-03-01']);
        $this->assertSame('2026-03-01', $d->fresh()->tarikh_tamat_simpanan->toDateString());

        // Dokumen yang dikekalkan secara manual tidak disentuh bila tempoh kategori berubah
        $kekal = $this->dokumenBertarikh('BPK/2020/0003', '2020-01-15');
        $kekal->forceFill(['status_pelupusan' => 'Dikekalkan', 'tarikh_tamat_simpanan' => '2040-01-01'])->saveQuietly();

        $admin = $this->buatPengguna(['ADMIN']);
        $this->actingAs($admin)->put(route('jenis-dokumen.update', $this->jenis->jenis_dokumen_id), [
            'kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon Komputer',
            'tempoh_simpanan_tahun' => 10, 'tindakan_tamat_tempoh' => 'Serah Arkib Negara',
        ])->assertRedirect(route('jenis-dokumen.index'))->assertSessionHasNoErrors();

        $this->assertSame('Serah Arkib Negara', $this->jenis->fresh()->tindakan_tamat_tempoh);
        $this->assertSame('2031-03-01', $d->fresh()->tarikh_tamat_simpanan->toDateString());
        $this->assertSame('2030-01-15', $tanpaTempoh->fresh()->tarikh_tamat_simpanan->toDateString());
        $this->assertSame('2040-01-01', $kekal->fresh()->tarikh_tamat_simpanan->toDateString());
    }

    public function test_pelupusan_kekalkan_lupus_dan_pulihkan(): void
    {
        $this->jenis->update(['tempoh_simpanan_tahun' => 1]);
        $tamat = $this->dokumenBertarikh('BPK/2020/0001', '2020-01-15');
        $tamatJuga = $this->dokumenBertarikh('BPK/2020/0002', '2020-06-01');
        $aktif = $this->dokumenBertarikh('BPK/2026/0009', today()->toDateString());
        $admin = $this->buatPengguna(['ADMIN'], ['nama_staff' => 'Pentadbir Arkib']);

        $this->actingAs($this->staf)->get(route('pelupusan.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('pelupusan.index'))->assertOk()
            ->assertSee('BPK/2020/0001')->assertSee('BPK/2020/0002')->assertDontSee('BPK/2026/0009');

        // Kekalkan 2 tahun dari hari ini (tarikh tamat sudah lepas)
        $this->actingAs($admin)->post(route('pelupusan.kekalkan', $tamat->dokumen_id), ['tahun' => 2, 'catatan' => 'Kes audit berjalan'])
            ->assertSessionHasNoErrors();
        $tamat = $tamat->fresh();
        $this->assertSame('Dikekalkan', $tamat->status_pelupusan);
        $this->assertSame(today()->addYears(2)->toDateString(), $tamat->tarikh_tamat_simpanan->toDateString());
        $this->assertDatabaseHas('log_audit', ['tindakan' => 'Kekalkan Dokumen', 'rekod_id' => $tamat->dokumen_id]);

        // Lupus: catatan wajib; tidak boleh bagi dokumen belum tamat
        $this->actingAs($admin)->post(route('pelupusan.lupus', $tamatJuga->dokumen_id), ['kaedah' => 'Dilupuskan'])->assertSessionHasErrors('catatan');
        $this->actingAs($admin)->post(route('pelupusan.lupus', $aktif->dokumen_id), ['kaedah' => 'Dilupuskan', 'catatan' => 'x'])->assertNotFound();
        $this->actingAs($admin)->post(route('pelupusan.lupus', $tamatJuga->dokumen_id), ['kaedah' => 'Diserah Arkib Negara', 'catatan' => 'Surat ANM 12/2026'])
            ->assertSessionHasNoErrors();

        $dilupus = Dokumen::withTrashed()->find($tamatJuga->dokumen_id);
        $this->assertTrue($dilupus->trashed());
        $this->assertSame('Diserah Arkib Negara', $dilupus->status_pelupusan);
        $this->assertSame($admin->user_id, $dilupus->pelupusan_oleh);
        $this->assertDatabaseHas('log_audit', ['tindakan' => 'Lupus Dokumen', 'rekod_id' => $dilupus->dokumen_id]);
        $this->actingAs($admin)->get(route('pelupusan.index', ['tab' => 'selesai']))
            ->assertSee('BPK/2020/0002')->assertSee('Surat ANM 12/2026')->assertSee('Pentadbir Arkib');

        // Dipulihkan dari Tong Sampah: kembali aktif dan perlu keputusan semula
        $this->actingAs($admin)->post(route('tong-sampah.pulih', ['dokumen', $dilupus->dokumen_id]))->assertRedirect();
        $dilupus = $dilupus->fresh();
        $this->assertFalse($dilupus->trashed());
        $this->assertNull($dilupus->status_pelupusan);
        $this->actingAs($admin)->get(route('pelupusan.index'))->assertSee('BPK/2020/0002');
    }

    // ---------------- P13: 2FA & sejarah log masuk ----------------

    private function aktifkan2fa(User $user): string
    {
        $rahsia = DuaFaktor::rahsiaBaharu();
        $user->forceFill(['dua_faktor_rahsia' => $rahsia, 'dua_faktor_kod_pemulihan' => ['AAAA-BBBB', 'CCCC-DDDD'],
            'dua_faktor_disahkan_pada' => now()])->saveQuietly();

        return $rahsia;
    }

    public function test_log_masuk_direkod_berjaya_dan_gagal_tanpa_simpan_no_kp(): void
    {
        $this->post(route('login.proses'), ['ic_pekerja' => $this->staf->ic_pekerja, 'password' => 'Salah123']);
        $this->post(route('login.proses'), ['ic_pekerja' => '000000000000', 'password' => 'Salah123']);
        $this->post(route('login.proses'), ['ic_pekerja' => $this->staf->ic_pekerja, 'password' => 'Lama1234'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->staf);

        $this->assertSame([
            [$this->staf->user_id, false, 'Kata laluan salah'],
            [null, false, 'No. KP tidak berdaftar'],
            [$this->staf->user_id, true, null],
        ], LogMasuk::orderBy('log_masuk_id')->get()->map(fn ($l) => [$l->user_id, $l->berjaya, $l->sebab])->all());

        $audit = $this->buatPengguna(['AUDIT']);
        $this->actingAs($audit)->get(route('log-masuk.index', ['status' => 'gagal']))->assertOk()
            ->assertSee('No. KP tidak berdaftar')->assertDontSee('000000000000');
        $this->actingAs($this->staf)->get(route('log-masuk.index'))->assertForbidden();
    }

    public function test_daftar_2fa_dari_profil_dan_rahsia_disulitkan(): void
    {
        $this->actingAs($this->staf)->post(route('profil.dua-faktor.mula'))->assertRedirect();
        $rahsia = session(\App\Http\Controllers\DuaFaktorController::SESI_RAHSIA_BAHARU);
        $this->actingAs($this->staf)->get(route('profil.index'))->assertOk()->assertSee('<svg', false)->assertSee('Sahkan &amp; Aktifkan', false);

        $this->actingAs($this->staf)->post(route('profil.dua-faktor.sahkan'), ['kod' => '000000'])->assertSessionHasErrorsIn('duaFaktor', 'kod');
        $this->assertFalse($this->staf->fresh()->duaFaktorAktif());

        $this->actingAs($this->staf)->post(route('profil.dua-faktor.sahkan'), ['kod' => (new Google2FA)->getCurrentOtp($rahsia)])
            ->assertSessionHas('kod_pemulihan', fn ($k) => count($k) === DuaFaktor::BIL_KOD_PEMULIHAN);
        $user = $this->staf->fresh();
        $this->assertTrue($user->duaFaktorAktif());
        $this->assertSame($rahsia, $user->dua_faktor_rahsia);
        $this->assertNotSame($rahsia, DB::table('users')->where('user_id', $user->user_id)->value('dua_faktor_rahsia'));
        $this->assertDatabaseHas('log_audit', ['tindakan' => 'Hidupkan 2FA', 'rekod_id' => $user->user_id]);
        $this->assertDatabaseMissing('log_audit', ['nama_jadual' => 'users', 'tindakan' => 'Kemaskini', 'rekod_id' => $user->user_id]);

        // Matikan perlu kata laluan betul
        $this->actingAs($user)->post(route('profil.dua-faktor.matikan'), ['kata_laluan' => 'Salah'])->assertSessionHasErrorsIn('duaFaktor', 'kata_laluan');
        $this->actingAs($user)->post(route('profil.dua-faktor.matikan'), ['kata_laluan' => 'Lama1234'])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->duaFaktorAktif());
    }

    public function test_log_masuk_ber_2fa_perlu_kod_dan_kod_pemulihan_sekali_guna(): void
    {
        $rahsia = $this->aktifkan2fa($this->staf);
        $masuk = fn () => $this->post(route('login.proses'), ['ic_pekerja' => $this->staf->ic_pekerja, 'password' => 'Lama1234']);

        $masuk()->assertRedirect(route('dua-faktor.cabaran'));
        $this->assertGuest();
        $this->get(route('dua-faktor.cabaran'))->assertOk()->assertSee('Pengesahan Dua Faktor');
        $this->post(route('dua-faktor.sahkan'), ['kod' => '000000'])->assertSessionHasErrors('kod');
        $this->assertGuest();
        $this->post(route('dua-faktor.sahkan'), ['kod' => (new Google2FA)->getCurrentOtp($rahsia)])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->staf);
        $this->post(route('logout'));

        // Kod pemulihan: sah sekali sahaja (huruf kecil diterima)
        $masuk();
        $this->post(route('dua-faktor.sahkan'), ['kod' => 'aaaa-bbbb'])->assertRedirect(route('dashboard'))->assertSessionHas('warning');
        $this->assertSame(['CCCC-DDDD'], $this->staf->fresh()->dua_faktor_kod_pemulihan);
        $this->post(route('logout'));
        $masuk();
        $this->post(route('dua-faktor.sahkan'), ['kod' => 'AAAA-BBBB'])->assertSessionHasErrors('kod');
        $this->assertGuest();

        $this->assertSame(['Kod 2FA salah', 'Kod pemulihan digunakan'], LogMasuk::where('sebab', '!=', null)->orderBy('log_masuk_id')->pluck('sebab')->unique()->values()->all());

        // Sesi menunggu tamat selepas 5 minit
        $this->travel(6)->minutes();
        $this->get(route('dua-faktor.cabaran'))->assertRedirect(route('login'));
    }

    public function test_2fa_wajib_untuk_pentadbir_bila_dihidupkan(): void
    {
        $admin = $this->buatPengguna(['ADMIN']);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        config(['services.dua_faktor.wajib' => true]);
        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('profil.index').'#dua-faktor');
        $this->actingAs($admin)->get(route('profil.index'))->assertOk()->assertSee('2FA wajib bagi akaun pentadbir');
        $this->actingAs($this->staf)->get(route('dashboard'))->assertOk();

        $this->aktifkan2fa($admin);
        $this->actingAs($admin->fresh())->get(route('dashboard'))->assertOk();
        $this->actingAs($admin->fresh())->post(route('profil.dua-faktor.matikan'), ['kata_laluan' => 'Lama1234'])
            ->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->duaFaktorAktif());
    }
}
