<?php

namespace Tests\Feature;

use App\Models\JenisDokumen;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * KERANI boleh lihat senarai Kategori Dokumen & Bahagian (baca sahaja);
 * tambah/edit/padam kekal untuk SUPERADMIN/ADMIN.
 */
class KeraniBacaSahajaTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->bahagian = $this->buatBahagian('KEW', 'Bahagian Kewangan');
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'MOHON', 'nama_dokumen' => 'Borang Permohonan']);
    }

    private $bahagian;
    private $jenis;

    public function test_kerani_lihat_senarai_tanpa_butang_urus(): void
    {
        $kerani = $this->buatPengguna(['KERANI']);

        $this->actingAs($kerani)->get(route('jenis-dokumen.index'))->assertOk()
            ->assertSee('Borang Permohonan')
            ->assertSee('Paparan sahaja')
            ->assertDontSee(route('jenis-dokumen.create'))
            ->assertDontSee(route('jenis-dokumen.edit', $this->jenis->jenis_dokumen_id))
            ->assertDontSee('id="confirmDeleteModal"', false);

        $this->actingAs($kerani)->get(route('bahagian.index'))->assertOk()
            ->assertSee('Bahagian Kewangan')
            ->assertDontSee(route('bahagian.create'))
            ->assertDontSee(route('bahagian.edit', $this->bahagian->bahagian_id));

        // Menu sidebar dipaparkan
        $this->actingAs($kerani)->get(route('dashboard'))
            ->assertSee(route('jenis-dokumen.index'))
            ->assertSee(route('bahagian.index'));
    }

    public function test_kerani_tidak_boleh_tambah_ubah_padam(): void
    {
        $kerani = $this->buatPengguna(['KERANI']);
        $this->actingAs($kerani);

        $this->get(route('jenis-dokumen.create'))->assertForbidden();
        $this->get(route('jenis-dokumen.edit', $this->jenis->jenis_dokumen_id))->assertForbidden();
        $this->post(route('jenis-dokumen.store'), ['kod_dokumen' => 'BARU', 'nama_dokumen' => 'Baru'])->assertForbidden();
        $this->delete(route('jenis-dokumen.destroy', $this->jenis->jenis_dokumen_id))->assertForbidden();
        $this->get(route('bahagian.create'))->assertForbidden();
        $this->delete(route('bahagian.destroy', $this->bahagian->bahagian_id))->assertForbidden();

        $this->assertNotSoftDeleted($this->jenis);
        $this->assertNotSoftDeleted($this->bahagian);
    }

    public function test_admin_masih_nampak_butang_urus_dan_staf_disekat(): void
    {
        $this->actingAs($this->buatPengguna(['ADMIN']))->get(route('jenis-dokumen.index'))->assertOk()
            ->assertSee(route('jenis-dokumen.create'))
            ->assertDontSee('Paparan sahaja');

        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('jenis-dokumen.index'))->assertForbidden();
    }
}
