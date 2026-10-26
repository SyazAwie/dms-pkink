<?php

namespace Tests\Feature;

use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\User;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * OCR 3 Situasi, Fasa C2: halaman "Isi Borang" (kad kategori → Isi Digital /
 * Imbas & Hantar dengan kategori dipilih dahulu melalui ?jenis=ID).
 */
class IsiBorangTest extends TestCase
{
    use SkemaUjian;

    private User $staf;
    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->staf = $this->buatPengguna(['STAFF'], ['nama_staff' => 'Siti Aminah']);

        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'BD-SEWA', 'nama_dokumen' => 'Borang Sewaan Peralatan', 'kategori' => 'Digital']);
        JenisDokumen::create(['kod_dokumen' => 'MEMO', 'nama_dokumen' => 'Memo Dalaman', 'kategori' => 'Memo']);
        DokumenField::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'Nama Pemohon',
            'kod_field' => 'nama_pemohon', 'jenis_data' => 'teks', 'is_required' => true, 'susunan' => 1,
            'tetapan' => ['auto_isi' => 'nama'],
        ]);
        DokumenField::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'Urusan BD',
            'kod_field' => 'urusan_bd', 'jenis_data' => 'seksyen', 'is_required' => false, 'susunan' => 2,
        ]);
    }

    public function test_staf_nampak_kad_kategori_dengan_dua_pilihan(): void
    {
        $id = $this->jenis->jenis_dokumen_id;

        $this->actingAs($this->staf)->get(route('isi-borang.index'))
            ->assertOk()
            ->assertSeeInOrder(['Digital', 'Borang Sewaan Peralatan', 'Memo', 'Memo Dalaman'])
            ->assertSee('1 medan')                           // tajuk seksyen tidak dikira
            ->assertSee('1 diisi daripada profil')
            ->assertSee(route('dokumen.create', ['jenis' => $id]))
            ->assertSee(route('imbas-hantar.create', ['jenis' => $id]))
            // Menu sidebar: "Isi Borang" menggantikan "Imbas & Hantar"
            ->assertSee('<span>Isi Borang</span>', false)
            ->assertDontSee('href="' . route('imbas-hantar.create') . '" class="nav-link', false);
    }

    public function test_peranan_tanpa_muat_naik_tidak_boleh_buka(): void
    {
        $this->actingAs($this->buatPengguna(['PENYOKONG']))->get(route('isi-borang.index'))->assertForbidden();
        $this->actingAs($this->buatPengguna(['AUDIT']))->get(route('isi-borang.index'))->assertForbidden();
    }

    public function test_isi_digital_kategori_dipilih_dan_medan_dirender(): void
    {
        $this->actingAs($this->staf)->get(route('dokumen.create', ['jenis' => $this->jenis->jenis_dokumen_id]))
            ->assertOk()
            ->assertSee('<option value="' . $this->jenis->jenis_dokumen_id . '" selected>', false)
            ->assertSee('value="Siti Aminah" required readonly', false);

        // ID tidak wujud: tiada kategori dipilih, tiada ralat
        $this->actingAs($this->staf)->get(route('dokumen.create', ['jenis' => 99999]))
            ->assertOk()
            ->assertDontSee('selected>', false);
    }

    public function test_imbas_hantar_kategori_dipilih(): void
    {
        $this->actingAs($this->staf)->get(route('imbas-hantar.create', ['jenis' => $this->jenis->jenis_dokumen_id]))
            ->assertOk()
            ->assertSee('<option value="' . $this->jenis->jenis_dokumen_id . '" selected>', false);
    }
}
