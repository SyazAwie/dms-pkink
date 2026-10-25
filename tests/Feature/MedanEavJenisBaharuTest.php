<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\User;
use App\Support\JenisMedan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Langkah 2 medan EAV: jenis format (wang, masa, emel, URL, ...), radio, ya/tidak,
 * teks bantuan dan "Lain-lain (nyatakan)", dari pembina Kategori hingga paparan.
 */
class MedanEavJenisBaharuTest extends TestCase
{
    use SkemaUjian;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->admin = $this->buatPengguna(['ADMIN'], ['bahagian_id' => $this->buatBahagian()->bahagian_id]);
    }

    /** Kategori "Borang Sewaan" dengan pelbagai jenis medan, dicipta melalui pembina. */
    private function ciptaKategoriSewaan(): JenisDokumen
    {
        $this->actingAs($this->admin)->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => 'SEWA',
            'nama_dokumen' => 'Borang Penggunaan/Sewaan Peralatan',
            'medan' => [
                ['nama_field' => 'Kegunaan', 'kod_field' => 'kegunaan', 'jenis_data' => 'radio', 'pilihan' => 'Individu, Bahagian/ Jabatan', 'lain' => '1', 'is_required' => '1'],
                ['nama_field' => 'Tarikh & Masa Diperlukan', 'kod_field' => 'masa_diperlukan', 'jenis_data' => 'tarikh_masa'],
                ['nama_field' => 'Caj Sewaan', 'kod_field' => 'caj', 'jenis_data' => 'wang', 'bantuan' => 'Untuk sewaan sahaja'],
                ['nama_field' => 'Emel Pemohon', 'kod_field' => 'emel', 'jenis_data' => 'emel'],
                ['nama_field' => 'No. Telefon', 'kod_field' => 'telefon', 'jenis_data' => 'telefon'],
                ['nama_field' => 'Pautan', 'kod_field' => 'pautan', 'jenis_data' => 'url'],
                ['nama_field' => 'Maklumat Terkini Ada?', 'kod_field' => 'ada_maklumat', 'jenis_data' => 'ya_tidak'],
                ['nama_field' => 'Catatan', 'kod_field' => 'catatan', 'jenis_data' => 'teks', 'lain' => '1', 'bantuan' => '   '],
            ],
        ])->assertRedirect(route('jenis-dokumen.index'));

        return JenisDokumen::with('fields')->where('kod_dokumen', 'SEWA')->sole();
    }

    private function medan(JenisDokumen $jenis, string $kod): DokumenField
    {
        return $jenis->fields->firstWhere('kod_field', $kod);
    }

    public function test_pembina_simpan_jenis_baharu_dan_tetapan(): void
    {
        $jenis = $this->ciptaKategoriSewaan();

        $kegunaan = $this->medan($jenis, 'kegunaan');
        $this->assertSame('radio', $kegunaan->jenis_data);
        $this->assertSame(['Individu', 'Bahagian/ Jabatan'], $kegunaan->pilihan);
        $this->assertSame(['lain' => true], $kegunaan->tetapan);

        $this->assertSame(['bantuan' => 'Untuk sewaan sahaja'], $this->medan($jenis, 'caj')->tetapan);
        // "lain" diabaikan bagi teks, bantuan kosong tidak disimpan
        $this->assertNull($this->medan($jenis, 'catatan')->tetapan);
        $this->assertNull($this->medan($jenis, 'caj')->pilihan);
    }

    public function test_kemas_kini_medan_sedia_ada_simpan_pilihan_dan_tetapan(): void
    {
        $jenis = $this->ciptaKategoriSewaan();
        $kegunaan = $this->medan($jenis, 'kegunaan');

        $this->actingAs($this->admin)->put(route('jenis-dokumen.update', $jenis->jenis_dokumen_id), [
            'kod_dokumen' => 'SEWA',
            'nama_dokumen' => 'Borang Penggunaan/Sewaan Peralatan',
            'medan' => [[
                'id' => $kegunaan->dokumen_field_id, 'nama_field' => 'Kegunaan', 'kod_field' => 'kegunaan',
                'jenis_data' => 'dropdown', 'pilihan' => 'Individu, Bahagian, Syarikat', 'bantuan' => 'Pilih satu',
            ]],
        ])->assertRedirect(route('jenis-dokumen.index'));

        $kegunaan->refresh();
        $this->assertSame('dropdown', $kegunaan->jenis_data);
        $this->assertSame(['Individu', 'Bahagian', 'Syarikat'], $kegunaan->pilihan);
        $this->assertSame(['bantuan' => 'Pilih satu'], $kegunaan->tetapan);
    }

    public function test_pembina_pulihkan_baris_selepas_ralat_validasi(): void
    {
        $this->actingAs($this->admin)->from(route('jenis-dokumen.create'))->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => '',
            'nama_dokumen' => 'Borang Tanpa Kod',
            'medan' => [['nama_field' => 'Nama Syarikat', 'kod_field' => 'nama_syarikat', 'jenis_data' => 'teks', 'bantuan' => 'Nama penuh syarikat']],
        ])->assertRedirect(route('jenis-dokumen.create'));

        $this->actingAs($this->admin)->get(route('jenis-dokumen.create'))
            ->assertOk()
            ->assertSee('"nama_field":"Nama Syarikat"', false)
            ->assertSee('"bantuan":"Nama penuh syarikat"', false);
    }

    public function test_muat_naik_simpan_nilai_dalam_bentuk_piawai(): void
    {
        Storage::fake('local');
        $jenis = $this->ciptaKategoriSewaan();
        $id = fn (string $kod) => $this->medan($jenis, $kod)->dokumen_field_id;

        $this->actingAs($this->admin)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $jenis->jenis_dokumen_id,
            'tarikh_dokumen' => '2026-10-25',
            'medan_data' => [
                $id('kegunaan') => JenisMedan::NILAI_LAIN,
                $id('masa_diperlukan') => '2026-10-27T09:00',
                $id('caj') => '50.5',
                $id('emel') => 'ali@pkink.gov.my',
                $id('telefon') => '09-748 1234',
                $id('pautan') => 'https://www.pkink.gov.my',
                $id('ada_maklumat') => 'Ya',
            ],
            'medan_lain' => [$id('kegunaan') => 'Syarikat Anak'],
            'fail_scan' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors()->assertRedirect(route('dokumen.index'));

        $nilai = DokumenData::pluck('nilai_data', 'dokumen_field_id');
        $this->assertSame('Lain-lain: Syarikat Anak', $nilai[$id('kegunaan')]);
        $this->assertSame('2026-10-27 09:00', $nilai[$id('masa_diperlukan')]);
        $this->assertSame('50.50', $nilai[$id('caj')]);
        $this->assertSame('Ya', $nilai[$id('ada_maklumat')]);
    }

    public function test_muat_naik_tolak_nilai_tidak_sah_dan_lain_lain_kosong(): void
    {
        $jenis = $this->ciptaKategoriSewaan();
        $id = fn (string $kod) => $this->medan($jenis, $kod)->dokumen_field_id;

        $this->actingAs($this->admin)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $jenis->jenis_dokumen_id,
            'tarikh_dokumen' => '2026-10-25',
            'medan_data' => [
                $id('kegunaan') => JenisMedan::NILAI_LAIN,
                $id('emel') => 'bukan emel',
                $id('pautan') => 'javascript:alert(1)',
                $id('caj') => '10.999',
                $id('ada_maklumat') => 'Mungkin',
            ],
            'fail_scan' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ])->assertSessionHasErrors([
            "medan_lain.{$id('kegunaan')}", "medan_data.{$id('emel')}", "medan_data.{$id('pautan')}",
            "medan_data.{$id('caj')}", "medan_data.{$id('ada_maklumat')}",
        ]);

        $this->assertSame(0, Dokumen::count());
    }

    public function test_edit_dan_butiran_papar_jenis_baharu(): void
    {
        $jenis = $this->ciptaKategoriSewaan();
        $id = fn (string $kod) => $this->medan($jenis, $kod)->dokumen_field_id;
        $dokumen = Dokumen::create([
            'jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'no_rujukan' => 'SEWA/2026/0001', 'tarikh_dokumen' => '2026-10-25',
            'bahagian_id' => $this->admin->bahagian_id, 'created_by' => $this->admin->user_id,
        ]);
        foreach ([
            'kegunaan' => 'Lain-lain: Syarikat Anak', 'masa_diperlukan' => '2026-10-27 09:00', 'caj' => '1500.50',
            'emel' => 'ali@pkink.gov.my', 'pautan' => 'https://www.pkink.gov.my', 'ada_maklumat' => 'Ya',
        ] as $kod => $nilai) {
            DokumenData::create(['dokumen_id' => $dokumen->dokumen_id, 'dokumen_field_id' => $id($kod), 'nilai_data' => $nilai]);
        }

        $this->actingAs($this->admin)->get(route('dokumen.edit', $dokumen->dokumen_id))
            ->assertOk()
            ->assertSee('value="' . JenisMedan::NILAI_LAIN . '" checked', false)
            ->assertSee('name="medan_lain[' . $id('kegunaan') . ']"', false)
            ->assertSee('value="Syarikat Anak"', false)
            ->assertSee('type="datetime-local"', false)
            ->assertSee('value="2026-10-27T09:00"', false)
            ->assertSee('value="Ya" checked', false)
            ->assertSee('Untuk sewaan sahaja');

        $this->actingAs($this->admin)->get(route('dokumen.show', $dokumen->dokumen_id))
            ->assertOk()
            ->assertSee('RM 1,500.50')
            ->assertSee('27/10/2026, 09:00')
            ->assertSee('Lain-lain: Syarikat Anak')
            ->assertSee('<a href="mailto:ali@pkink.gov.my">', false)
            ->assertSee('<a href="https://www.pkink.gov.my" target="_blank" rel="noopener noreferrer">', false);
    }
}
