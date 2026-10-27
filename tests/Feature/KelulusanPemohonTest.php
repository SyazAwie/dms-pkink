<?php

namespace Tests\Feature;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\User;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Pemohon tidak boleh menyokong, meluluskan atau menolak permohonannya sendiri
 * (termasuk SUPERADMIN/ADMIN), dan permohonan sendiri bukan giliran tindakannya.
 */
class KelulusanPemohonTest extends TestCase
{
    use SkemaUjian;

    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'MOHON', 'nama_dokumen' => 'Borang Permohonan']);
    }

    private function permohonan(User $pemohon, string $status): Dokumen
    {
        static $n = 0;
        $n++;
        $d = Dokumen::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => sprintf('MOHON/2026/%04d', $n),
            'tarikh_dokumen' => '2026-10-27', 'bahagian_id' => $pemohon->bahagian_id, 'created_by' => $pemohon->user_id,
        ]);
        Borang::create(['dokumen_id' => $d->dokumen_id, 'pemohon_id' => $pemohon->user_id,
            'status_permohonan' => $status, 'tarikh_permohonan' => now()]);

        return $d;
    }

    public function test_penyokong_tidak_boleh_sokong_permohonan_sendiri(): void
    {
        $penyokong = $this->buatPengguna(['PENYOKONG', 'STAFF']);
        $d = $this->permohonan($penyokong, 'Dalam Proses');

        $this->actingAs($penyokong)->post(route('kelulusan.sokong', $d->dokumen_id))
            ->assertForbidden();

        $this->assertSame('Dalam Proses', Borang::sole()->status_permohonan);
        $this->assertNull(Borang::sole()->pegawai_penyokong_id);
    }

    public function test_admin_tidak_boleh_lulus_permohonan_sendiri(): void
    {
        $admin = $this->buatPengguna(['ADMIN']);
        $d = $this->permohonan($admin, 'Disokong');

        $this->actingAs($admin)->post(route('kelulusan.lulus', $d->dokumen_id))
            ->assertForbidden();

        $this->assertSame('Disokong', Borang::sole()->status_permohonan);
    }

    public function test_pemohon_tidak_boleh_tolak_permohonan_sendiri(): void
    {
        $pelulus = $this->buatPengguna(['PELULUS', 'STAFF']);
        $d = $this->permohonan($pelulus, 'Disokong');

        $this->actingAs($pelulus)->post(route('kelulusan.tolak', $d->dokumen_id), ['ulasan_pelulus' => 'Tolak sendiri'])
            ->assertForbidden();

        $this->assertSame('Disokong', Borang::sole()->status_permohonan);
    }

    public function test_penyokong_masih_boleh_sokong_permohonan_orang_lain(): void
    {
        $penyokong = $this->buatPengguna(['PENYOKONG']);
        $d = $this->permohonan($this->buatPengguna(['STAFF']), 'Dalam Proses');

        $this->actingAs($penyokong)->post(route('kelulusan.sokong', $d->dokumen_id))
            ->assertRedirect();

        $this->assertSame('Disokong', Borang::sole()->status_permohonan);
        $this->assertSame($penyokong->user_id, (int) Borang::sole()->pegawai_penyokong_id);
    }

    public function test_tolak_rekod_pegawai_penolak_dan_hantar_semula_mengosongkannya(): void
    {
        $staf = $this->buatPengguna(['STAFF']);
        $penyokong = $this->buatPengguna(['PENYOKONG'], ['nama_staff' => 'Pegawai Penyokong Ujian']);
        $d = $this->permohonan($staf, 'Dalam Proses');

        $this->actingAs($penyokong)->post(route('kelulusan.tolak', $d->dokumen_id), ['ulasan_penyokong' => 'Lampiran tidak lengkap'])
            ->assertRedirect();

        $borang = Borang::sole();
        $this->assertSame('Ditolak', $borang->status_permohonan);
        $this->assertSame($penyokong->user_id, (int) $borang->pegawai_penolak_id);
        $this->assertNotNull($borang->tarikh_tolak);

        // Pemohon nampak siapa yang menolak
        $this->actingAs($staf)->get(route('dokumen.show', $d->dokumen_id))->assertOk()
            ->assertSeeInOrder(['Ditolak Oleh', 'Pegawai Penyokong Ujian', 'Tarikh Ditolak']);

        // Hantar semula: rekod penolakan lama dikosongkan
        $this->actingAs($staf)->post(route('kelulusan.hantar', $d->dokumen_id))->assertRedirect();
        $borang->refresh();
        $this->assertSame('Dalam Proses', $borang->status_permohonan);
        $this->assertNull($borang->pegawai_penolak_id);
        $this->assertNull($borang->tarikh_tolak);
    }

    public function test_halaman_dokumen_sembunyi_butang_bagi_pemohon(): void
    {
        $admin = $this->buatPengguna(['ADMIN']);
        $sendiri = $this->permohonan($admin, 'Dalam Proses');
        $lain = $this->permohonan($this->buatPengguna(['STAFF']), 'Dalam Proses');

        $this->actingAs($admin)->get(route('dokumen.show', $sendiri->dokumen_id))->assertOk()
            ->assertDontSee(route('kelulusan.sokong', $sendiri->dokumen_id))
            ->assertSee('Anda pemohon permohonan ini, jadi sokongan perlu dibuat oleh pegawai lain.');

        $this->actingAs($admin)->get(route('dokumen.show', $lain->dokumen_id))->assertOk()
            ->assertSee(route('kelulusan.sokong', $lain->dokumen_id))
            ->assertDontSee('Anda pemohon permohonan ini');
    }

    public function test_permohonan_sendiri_bukan_dalam_giliran(): void
    {
        $penyokong = $this->buatPengguna(['PENYOKONG']);
        $sendiri = $this->permohonan($penyokong, 'Dalam Proses');
        $lain = $this->permohonan($this->buatPengguna(['STAFF']), 'Dalam Proses');

        // Senarai Kelulusan
        $this->actingAs($penyokong)->get(route('kelulusan.index'))->assertOk()
            ->assertSee($lain->no_rujukan)
            ->assertDontSee($sendiri->no_rujukan);

        // Loceng topbar & giliran papan pemuka hanya mengira permohonan orang lain
        $this->actingAs($penyokong)->get(route('dashboard'))->assertOk()
            ->assertSee('1 perkara menunggu tindakan anda')
            ->assertSee($lain->no_rujukan)
            ->assertDontSee($sendiri->no_rujukan);
    }
}
