<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\User;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Input medan EAV dirender oleh satu partial (dokumen.partials.medan-input) untuk
 * Muat Naik, Kemaskini dan Imbas & Arkib; borang Muat Naik memulihkan nilai
 * selepas ralat validasi (#12).
 */
class MedanEavBorangTest extends TestCase
{
    use SkemaUjian;

    private User $kerani;
    private JenisDokumen $jenis;
    /** @var array<string, DokumenField> */
    private array $medan = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();

        $bahagian = $this->buatBahagian();
        $this->kerani = $this->buatPengguna(['KERANI'], ['bahagian_id' => $bahagian->bahagian_id]);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'INV', 'nama_dokumen' => 'Invois']);

        $definisi = [
            ['No. Invois', 'no_invois', 'teks', true, null],
            ['Jumlah', 'jumlah', 'nombor', true, null],
            ['Tarikh Invois', 'tarikh_invois', 'tarikh', false, null],
            ['Status', 'status', 'dropdown', false, ['Belum Bayar', 'Sudah Bayar']],
            ['Catatan', 'catatan', 'textarea', false, null],
        ];
        foreach ($definisi as $i => [$nama, $kod, $jenis, $wajib, $pilihan]) {
            $this->medan[$kod] = DokumenField::create([
                'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => $nama, 'kod_field' => $kod,
                'jenis_data' => $jenis, 'is_required' => $wajib, 'pilihan' => $pilihan, 'susunan' => $i + 1,
            ]);
        }
    }

    private function id(string $kod): int
    {
        return $this->medan[$kod]->dokumen_field_id;
    }

    public function test_endpoint_medan_pulangkan_input_html_ikut_jenis_data(): void
    {
        $res = $this->actingAs($this->kerani)
            ->get(route('jenis-dokumen.medan', $this->jenis->jenis_dokumen_id))
            ->assertOk();

        $res->assertSee('type="text" name="medan_data[' . $this->id('no_invois') . ']"', false)
            ->assertSee('type="number" step="0.01" name="medan_data[' . $this->id('jumlah') . ']"', false)
            ->assertSee('type="date" name="medan_data[' . $this->id('tarikh_invois') . ']"', false)
            ->assertSee('<select name="medan_data[' . $this->id('status') . ']"', false)
            ->assertSee('<option value="Sudah Bayar"', false)
            ->assertSee('<textarea name="medan_data[' . $this->id('catatan') . ']"', false)
            ->assertSee('No. Invois');
    }

    public function test_muat_naik_pulihkan_nilai_dan_papar_ralat_medan_selepas_validasi_gagal(): void
    {
        $this->actingAs($this->kerani)->from(route('dokumen.create'))
            ->post(route('dokumen.store'), [
                'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
                'tarikh_dokumen' => '2025-05-01',
                'medan_data' => [
                    $this->id('no_invois') => 'INV-777',
                    $this->id('jumlah') => 'bukan nombor',
                    $this->id('status') => 'Sudah Bayar',
                    $this->id('catatan') => 'Catatan yang ditaip',
                ],
                // fail_scan sengaja tiada
            ])
            ->assertRedirect(route('dokumen.create'));

        $this->actingAs($this->kerani)->get(route('dokumen.create'))
            ->assertOk()
            ->assertSee('value="INV-777"', false)
            ->assertSee('<option value="Sudah Bayar" selected>', false)
            ->assertSee('Catatan yang ditaip')
            ->assertSee('dms-input is-invalid', false)
            ->assertSee('medan data.' . $this->id('jumlah'));
    }

    public function test_muat_naik_tanpa_ralat_tidak_render_medan_di_pelayan(): void
    {
        $this->actingAs($this->kerani)->get(route('dokumen.create'))
            ->assertOk()
            ->assertDontSee('name="medan_data[', false)
            ->assertSee(json_encode(route('jenis-dokumen.medan', '__ID__')), false);
    }

    public function test_kemaskini_papar_nilai_sedia_ada_melalui_partial(): void
    {
        $dokumen = Dokumen::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => 'INV/2025/0001',
            'tarikh_dokumen' => '2025-05-01', 'bahagian_id' => $this->kerani->bahagian_id, 'created_by' => $this->kerani->user_id,
        ]);
        DokumenData::create(['dokumen_id' => $dokumen->dokumen_id, 'dokumen_field_id' => $this->id('no_invois'), 'nilai_data' => 'INV-001']);
        DokumenData::create(['dokumen_id' => $dokumen->dokumen_id, 'dokumen_field_id' => $this->id('status'), 'nilai_data' => 'Nilai Lama']);

        $this->actingAs($this->kerani)->get(route('dokumen.edit', $dokumen->dokumen_id))
            ->assertOk()
            ->assertSee('value="INV-001"', false)
            // Nilai dropdown yang tiada lagi dalam senarai pilihan masih dipaparkan
            ->assertSee('Nilai Lama (nilai lama)');
    }

    public function test_simpan_muat_naik_guna_nilai_medan_kategori_sahaja(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $this->actingAs($this->kerani)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
            'tarikh_dokumen' => '2025-05-01',
            'medan_data' => [
                $this->id('no_invois') => 'INV-123',
                $this->id('jumlah') => '1500.50',
                $this->id('catatan') => '',
                99999 => 'medan palsu',
            ],
            'fail_scan' => [\Illuminate\Http\UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ])->assertRedirect(route('dokumen.index'));

        $this->assertEqualsCanonicalizing(
            [$this->id('no_invois') => 'INV-123', $this->id('jumlah') => '1500.50'],
            DokumenData::pluck('nilai_data', 'dokumen_field_id')->all()
        );
    }

    public function test_pembina_medan_kategori_guna_senarai_jenis_dari_jenis_medan(): void
    {
        $this->actingAs($this->buatPengguna(['ADMIN']))->get(route('jenis-dokumen.create'))
            ->assertOk()
            ->assertSee(json_encode(\App\Support\JenisMedan::SENARAI), false);
    }
}
