<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * OCR 3 Situasi, Fasa A1: medan auto-isi daripada profil (tetapan.auto_isi).
 * Medan dikunci dalam borang dan pelayan menggantikan nilai yang dihantar.
 */
class MedanAutoIsiTest extends TestCase
{
    use SkemaUjian;

    private User $admin;
    private User $staf;
    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('local');
        $this->admin = $this->buatPengguna(['ADMIN'], ['bahagian_id' => $this->buatBahagian()->bahagian_id]);
        $this->staf = $this->buatPengguna(['STAFF'], [
            'nama_staff' => 'Siti Aminah',
            'email' => 'siti@pkink.test',
            'bahagian_id' => $this->buatBahagian('KEW', 'Bahagian Kewangan')->bahagian_id,
        ]);

        $this->actingAs($this->admin)->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => 'MOHON', 'nama_dokumen' => 'Borang Permohonan',
            'medan' => [
                ['nama_field' => 'Nama Pemohon', 'kod_field' => 'nama_pemohon', 'jenis_data' => 'teks', 'is_required' => '1', 'auto_isi' => 'nama'],
                ['nama_field' => 'Emel Pemohon', 'kod_field' => 'emel_pemohon', 'jenis_data' => 'emel', 'auto_isi' => 'emel'],
                ['nama_field' => 'Bahagian', 'kod_field' => 'bahagian', 'jenis_data' => 'teks', 'is_required' => '1', 'auto_isi' => 'bahagian'],
                ['nama_field' => 'Tujuan', 'kod_field' => 'tujuan', 'jenis_data' => 'teks'],
                // Tidak serasi: nilai profil tidak lulus validasi jenis ini, jadi tidak disimpan
                ['nama_field' => 'Bilangan', 'kod_field' => 'bilangan', 'jenis_data' => 'nombor', 'auto_isi' => 'nama'],
                ['nama_field' => 'Emel Kedua', 'kod_field' => 'emel_kedua', 'jenis_data' => 'emel', 'auto_isi' => 'nama'],
            ],
        ])->assertSessionHasNoErrors();

        $this->jenis = JenisDokumen::with('fields')->sole();
    }

    private function medan(string $kod): DokumenField
    {
        return $this->jenis->fields->firstWhere('kod_field', $kod);
    }

    private function id(string $kod): int
    {
        return $this->medan($kod)->dokumen_field_id;
    }

    private function nilai(string $kod): ?string
    {
        return DokumenData::where('dokumen_field_id', $this->id($kod))->value('nilai_data');
    }

    private function muatNaik(User $user, array $medanData)
    {
        return $this->actingAs($user)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'tarikh_dokumen' => '2026-10-26',
            'medan_data' => $medanData,
            'fail_scan' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ]);
    }

    public function test_pembina_simpan_auto_isi_yang_serasi_sahaja(): void
    {
        $this->assertSame('nama', $this->medan('nama_pemohon')->tetapan['auto_isi']);
        $this->assertSame('emel', $this->medan('emel_pemohon')->tetapan['auto_isi']);
        $this->assertSame('bahagian', $this->medan('bahagian')->tetapan['auto_isi']);
        $this->assertNull($this->medan('tujuan')->tetapan);
        $this->assertNull($this->medan('bilangan')->tetapan);
        $this->assertNull($this->medan('emel_kedua')->tetapan);
    }

    public function test_borang_muat_naik_papar_nilai_profil_dikunci(): void
    {
        $this->actingAs($this->staf)->get(route('jenis-dokumen.medan', $this->jenis->jenis_dokumen_id))
            ->assertOk()
            ->assertSee('Daripada profil')
            ->assertSee('value="Siti Aminah" required readonly', false)
            ->assertSee('value="Bahagian Kewangan" required readonly', false);
    }

    public function test_nilai_dihantar_diabaikan_dan_diganti_profil(): void
    {
        $this->muatNaik($this->staf, [
            $this->id('nama_pemohon') => 'Nama Palsu',
            $this->id('emel_pemohon') => 'palsu@contoh.com',
            $this->id('bahagian') => 'Bahagian Palsu',
            $this->id('tujuan') => 'Kursus',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Siti Aminah', $this->nilai('nama_pemohon'));
        $this->assertSame('siti@pkink.test', $this->nilai('emel_pemohon'));
        $this->assertSame('Bahagian Kewangan', $this->nilai('bahagian'));
        $this->assertSame('Kursus', $this->nilai('tujuan'));
    }

    public function test_medan_wajib_ditolak_jika_profil_tiada_maklumat(): void
    {
        $tanpaBahagian = $this->buatPengguna(['STAFF']);

        $this->muatNaik($tanpaBahagian, [$this->id('bahagian') => 'Cuba Isi Sendiri'])
            ->assertSessionHasErrors([
                "medan_data.{$this->id('bahagian')}" => 'Medan Bahagian diisi daripada profil, tetapi profil tiada maklumat bahagian. Sila kemas kini profil terlebih dahulu.',
            ]);
        $this->assertSame(0, Dokumen::count());
    }

    public function test_edit_kekal_nilai_pemohon_asal(): void
    {
        $this->muatNaik($this->staf, [])->assertSessionHasNoErrors();
        $dokumen = Dokumen::sole();

        // Admin edit: nilai yang dihantar diabaikan, nilai pemohon asal kekal (bukan profil admin)
        $this->actingAs($this->admin)->get(route('dokumen.edit', $dokumen->dokumen_id))
            ->assertSee('value="Siti Aminah" required readonly', false);

        $this->actingAs($this->admin)->put(route('dokumen.update', $dokumen->dokumen_id), [
            'tarikh_dokumen' => '2026-10-26',
            'medan_data' => [$this->id('nama_pemohon') => 'Admin Tukar', $this->id('bahagian') => 'Bahagian Digital'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Siti Aminah', $this->nilai('nama_pemohon'));
        $this->assertSame('Bahagian Kewangan', $this->nilai('bahagian'));
    }

    public function test_edit_nilai_kosong_diisi_daripada_profil_pemuat_naik(): void
    {
        $this->muatNaik($this->staf, [])->assertSessionHasNoErrors();
        $dokumen = Dokumen::sole();
        // Seperti dokumen lama yang dicipta sebelum medan ditetapkan auto-isi
        DokumenData::where('dokumen_field_id', $this->id('emel_pemohon'))->delete();

        $this->actingAs($this->admin)->put(route('dokumen.update', $dokumen->dokumen_id), [
            'tarikh_dokumen' => '2026-10-26',
        ])->assertSessionHasNoErrors();

        $this->assertSame('siti@pkink.test', $this->nilai('emel_pemohon'));
    }
}
