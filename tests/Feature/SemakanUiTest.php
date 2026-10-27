<?php

namespace Tests\Feature;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\PendaftaranStaf;
use Illuminate\Pagination\Paginator;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Semakan UI 2026-10-27: paginasi Bootstrap, kad ringkasan bermakna, lajur
 * Peranan/Medan/Kakitangan, status kelulusan dalam senarai dokumen dan
 * kategori tanpa medan di halaman Isi Borang.
 */
class SemakanUiTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
    }

    public function test_paginasi_guna_templat_bootstrap(): void
    {
        $this->assertSame('pagination::bootstrap-5', Paginator::$defaultView);
    }

    public function test_senarai_kakitangan_papar_peranan_dan_pendaftaran_menunggu(): void
    {
        $admin = $this->buatPengguna(['ADMIN'], ['nama_staff' => 'Pentadbir Ujian']);
        (new PendaftaranStaf)->forceFill(['nama_staff' => 'Pemohon Baharu', 'ic_pekerja' => '990101019999',
            'email' => 'baharu@pkink.test', 'bahagian_id' => $this->buatBahagian()->bahagian_id,
            'status' => PendaftaranStaf::MENUNGGU])->save();

        $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->assertSee('PERANAN')
            ->assertSee('<span class="badge peranan-badge">Admin</span>', false)
            ->assertSeeInOrder(['MENUNGGU PENGESAHAN', '1'])
            ->assertDontSee('STATUS SISTEM');
    }

    public function test_kategori_tanpa_medan_ditanda(): void
    {
        $admin = $this->buatPengguna(['ADMIN']);
        $ada = JenisDokumen::create(['kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon']);
        DokumenField::create(['jenis_dokumen_id' => $ada->jenis_dokumen_id, 'nama_field' => 'Nama',
            'kod_field' => 'nama', 'jenis_data' => 'teks', 'is_required' => true, 'susunan' => 1]);
        JenisDokumen::create(['kod_dokumen' => 'INV', 'nama_dokumen' => 'Invois Syarikat']);

        $this->actingAs($admin)->get(route('jenis-dokumen.index'))->assertOk()
            ->assertSeeInOrder(['TANPA MEDAN', '1', 'Kategori belum ada medan borang'])
            ->assertSee('Tiada medan')
            ->assertDontSee('STATUS SISTEM');

        // Isi Borang: kategori tanpa medan hanya muat naik fail, tiada Imbas & Hantar
        $this->actingAs($admin)->get(route('isi-borang.index'))->assertOk()
            ->assertSee('Tiada medan, muat naik fail sahaja')
            ->assertSee(route('imbas-hantar.create', ['jenis' => $ada->jenis_dokumen_id]))
            ->assertDontSee(route('imbas-hantar.create', ['jenis' => JenisDokumen::where('kod_dokumen', 'INV')->value('jenis_dokumen_id')]));
    }

    public function test_senarai_dokumen_papar_status_kelulusan(): void
    {
        $staf = $this->buatPengguna(['STAFF']);
        $jenis = JenisDokumen::create(['kod_dokumen' => 'MOHON', 'nama_dokumen' => 'Borang Permohonan']);
        $buat = fn (string $no) => Dokumen::create(['jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'no_rujukan' => $no,
            'tarikh_dokumen' => '2026-10-27', 'created_by' => $staf->user_id, 'status' => 'Archived']);
        $buat('MOHON/2026/0001');
        $dihantar = $buat('MOHON/2026/0002');
        Borang::create(['dokumen_id' => $dihantar->dokumen_id, 'pemohon_id' => $staf->user_id,
            'status_permohonan' => 'Dalam Proses', 'tarikh_permohonan' => now()]);

        $this->actingAs($staf)->get(route('dokumen.index'))->assertOk()
            ->assertSee('Belum Dihantar')
            ->assertSee('Dalam Proses')
            ->assertDontSee('Archived');
    }

    public function test_imbas_hantar_kembali_ke_isi_borang(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('imbas-hantar.create'))->assertOk()
            ->assertSee(route('isi-borang.index'))
            ->assertSee('Kembali ke Isi Borang');
    }
}
