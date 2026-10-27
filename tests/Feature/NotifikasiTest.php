<?php

namespace Tests\Feature;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\User;
use App\Support\Notifikasi;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * P-C: notifikasi dalam sistem ikut peranan (loceng topbar, halaman Notifikasi).
 */
class NotifikasiTest extends TestCase
{
    use SkemaUjian;

    private User $staf;
    private User $penyokong;
    private User $pelulus;
    private Dokumen $dokumen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->staf = $this->buatPengguna(['STAFF'], ['nama_staff' => 'Ahmad Hakim']);
        $this->penyokong = $this->buatPengguna(['PENYOKONG'], ['nama_staff' => 'Nor Hidayah']);
        $this->pelulus = $this->buatPengguna(['PELULUS'], ['nama_staff' => 'Hazwan Abdullah']);
        $jenis = JenisDokumen::create(['kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon Komputer']);
        $this->dokumen = Dokumen::create(['jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'no_rujukan' => 'BPK/2026/0001',
            'tarikh_dokumen' => '2026-10-27', 'created_by' => $this->staf->user_id]);
    }

    private function jenisNotifikasi(User $user): array
    {
        return $user->fresh()->notifications->pluck('data.jenis')->sort()->values()->all();
    }

    public function test_aliran_kelulusan_penuh_memaklumkan_peranan_yang_betul(): void
    {
        // Hantar: penyokong dimaklumkan, bukan pemohon atau pelulus
        $this->actingAs($this->staf)->post(route('kelulusan.hantar', $this->dokumen->dokumen_id))->assertRedirect();
        $this->assertSame(['permohonan_baharu'], $this->jenisNotifikasi($this->penyokong));
        $this->assertSame([], $this->jenisNotifikasi($this->staf));
        $this->assertSame([], $this->jenisNotifikasi($this->pelulus));
        $this->assertSame(['PENYOKONG'], $this->penyokong->notifications->first()->data['peranan']);

        // Sokong: pemohon & pelulus dimaklumkan
        $this->actingAs($this->penyokong)->post(route('kelulusan.sokong', $this->dokumen->dokumen_id))->assertRedirect();
        $this->assertSame(['disokong'], $this->jenisNotifikasi($this->staf));
        $this->assertSame(['menunggu_kelulusan'], $this->jenisNotifikasi($this->pelulus));
        $this->assertStringContainsString('oleh Nor Hidayah', $this->staf->fresh()->notifications->first()->data['mesej']);

        // Lulus: pemohon dimaklumkan
        $this->actingAs($this->pelulus)->post(route('kelulusan.lulus', $this->dokumen->dokumen_id))->assertRedirect();
        $this->assertSame(['diluluskan', 'disokong'], $this->jenisNotifikasi($this->staf));
        $lulus = $this->staf->fresh()->notifications->firstWhere('data.jenis', 'diluluskan');
        $this->assertSame('Permohonan anda diluluskan', $lulus->data['tajuk']);
        $this->assertSame(route('dokumen.show', $this->dokumen->dokumen_id), $lulus->data['url']);
    }

    public function test_tolak_maklumkan_pemohon_dengan_ulasan(): void
    {
        Borang::create(['dokumen_id' => $this->dokumen->dokumen_id, 'pemohon_id' => $this->staf->user_id,
            'status_permohonan' => 'Dalam Proses', 'tarikh_permohonan' => now()]);

        $this->actingAs($this->penyokong)->post(route('kelulusan.tolak', $this->dokumen->dokumen_id), ['ulasan_penyokong' => 'Lampiran tidak lengkap'])
            ->assertRedirect();

        $tolak = $this->staf->fresh()->notifications->firstWhere('data.jenis', 'ditolak');
        $this->assertStringContainsString('"Lampiran tidak lengkap"', $tolak->data['mesej']);
        $this->assertNull($tolak->data['peranan']); // peribadi: sentiasa kelihatan
    }

    public function test_arkib_terus_tidak_menghantar_notifikasi(): void
    {
        // Imbas & Arkib: borang dicipta terus "Diluluskan" tanpa pelulus
        Borang::create(['dokumen_id' => $this->dokumen->dokumen_id, 'pemohon_id' => $this->staf->user_id,
            'status_permohonan' => 'Diluluskan', 'tarikh_permohonan' => now(), 'tarikh_kelulusan' => now()]);

        $this->assertSame(0, \Illuminate\Notifications\DatabaseNotification::count());
    }

    public function test_pendaftaran_awam_maklumkan_pentadbir(): void
    {
        $admin = $this->buatPengguna(['ADMIN']);
        $bahagian = $this->buatBahagian('KEW', 'Bahagian Kewangan');

        $this->post(route('pendaftaran.simpan'), ['ic_pekerja' => '990101019999', 'nama_staff' => 'Pemohon Baharu',
            'email' => 'baharu@pkink.test', 'bahagian_id' => $bahagian->bahagian_id])->assertRedirect(route('login'));

        $this->assertSame(['pendaftaran_baharu'], $this->jenisNotifikasi($admin));
        $this->assertSame([], $this->jenisNotifikasi($this->staf));
    }

    public function test_loceng_ringkasan_buka_dan_baca_semua(): void
    {
        Notifikasi::amaranSistem('Ujian amaran', 'Mesej ujian');
        $admin = $this->buatPengguna(['ADMIN']);
        Notifikasi::amaranSistem('Sandaran gagal', 'Cakera penuh');

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('Sandaran gagal')
            ->assertSee('Notifikasi: 1 belum dibaca');

        $ringkasan = $this->actingAs($admin)->getJson(route('notifikasi.ringkasan'))->assertOk()->json();
        $this->assertSame(1, $ringkasan['belum_dibaca']);
        $this->assertFalse($ringkasan['terkini'][0]['dibaca']);

        $id = $admin->notifications->first()->id;
        $this->actingAs($admin)->get(route('notifikasi.buka', $id))->assertRedirect(route('dashboard'));
        $this->assertNotNull($admin->fresh()->notifications->first()->read_at);

        Notifikasi::amaranSistem('Kedua', 'x');
        $this->actingAs($admin)->post(route('notifikasi.baca-semua'))->assertRedirect();
        $this->assertSame(0, $admin->fresh()->unreadNotifications()->count());

        // Notifikasi pengguna lain tidak boleh dibuka
        $this->actingAs($this->staf)->get(route('notifikasi.buka', $id))->assertNotFound();
    }

    public function test_notifikasi_ditapis_ikut_peranan_aktif(): void
    {
        $dua = $this->buatPengguna(['STAFF', 'PENYOKONG']);
        $lain = Dokumen::create(['jenis_dokumen_id' => $this->dokumen->jenis_dokumen_id, 'no_rujukan' => 'BPK/2026/0002',
            'tarikh_dokumen' => '2026-10-27', 'created_by' => $this->staf->user_id]);
        Borang::create(['dokumen_id' => $lain->dokumen_id, 'pemohon_id' => $this->staf->user_id,
            'status_permohonan' => 'Dalam Proses', 'tarikh_permohonan' => now()]); // -> PENYOKONG

        $this->actingAs($dua)->get(route('notifikasi.index'))->assertOk()->assertSee('Permohonan menunggu sokongan');

        $this->actingAs($dua)->post(route('peranan.tukar'), ['peranan' => 'STAFF']);
        $this->get(route('notifikasi.index'))->assertOk()->assertDontSee('Permohonan menunggu sokongan');
        $this->assertSame(0, $this->getJson(route('notifikasi.ringkasan'))->json('belum_dibaca'));
    }
}
