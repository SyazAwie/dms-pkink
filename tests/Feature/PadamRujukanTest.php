<?php

namespace Tests\Feature;

use App\Models\Bahagian;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\PendaftaranStaf;
use App\Models\User;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * #15: Bahagian yang masih ada kakitangan aktif / pendaftaran menunggu tidak boleh dipadam;
 * Bahagian/Kategori yang hanya dirujuk oleh dokumen boleh dipadam dengan amaran.
 */
class PadamRujukanTest extends TestCase
{
    use SkemaUjian;

    private User $admin;
    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->admin = $this->buatPengguna(['ADMIN']);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'MOHON', 'nama_dokumen' => 'Borang Permohonan']);
    }

    private function dokumenDalam(?Bahagian $bahagian = null): Dokumen
    {
        static $n = 0;
        $n++;

        return Dokumen::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => sprintf('MOHON/2026/%04d', $n),
            'tarikh_dokumen' => '2026-10-27', 'bahagian_id' => $bahagian?->bahagian_id, 'created_by' => $this->admin->user_id]);
    }

    public function test_bahagian_dengan_kakitangan_aktif_tidak_boleh_dipadam(): void
    {
        $bahagian = $this->buatBahagian('KEW', 'Bahagian Kewangan');
        $this->buatPengguna(['STAFF'], ['bahagian_id' => $bahagian->bahagian_id]);

        $this->actingAs($this->admin)->delete(route('bahagian.destroy', $bahagian->bahagian_id))
            ->assertRedirect(route('bahagian.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, '1 kakitangan aktif'));

        $this->assertNotSoftDeleted($bahagian);
    }

    public function test_bahagian_dengan_pendaftaran_menunggu_tidak_boleh_dipadam(): void
    {
        $bahagian = $this->buatBahagian('KEW', 'Bahagian Kewangan');
        (new PendaftaranStaf)->forceFill(['nama_staff' => 'Pemohon Baharu', 'ic_pekerja' => '990101019999',
            'email' => 'baharu@pkink.test', 'bahagian_id' => $bahagian->bahagian_id, 'status' => PendaftaranStaf::MENUNGGU])->save();

        $this->actingAs($this->admin)->delete(route('bahagian.destroy', $bahagian->bahagian_id))
            ->assertSessionHas('error', fn ($m) => str_contains($m, '1 permohonan pendaftaran yang menunggu'));

        $this->assertNotSoftDeleted($bahagian);
    }

    public function test_bahagian_yang_hanya_ada_dokumen_dipadam_dengan_nota(): void
    {
        $bahagian = $this->buatBahagian('LAMA', 'Bahagian Lama');
        $dokumen = $this->dokumenDalam($bahagian);

        $this->actingAs($this->admin)->delete(route('bahagian.destroy', $bahagian->bahagian_id))
            ->assertSessionHas('success', 'Bahagian telah dipadam. 1 dokumen sedia ada kekal dalam arkib dengan nama bahagian ini.');

        $this->assertSoftDeleted($bahagian);
        $this->assertSame('Bahagian Lama', $dokumen->fresh()->bahagian->nama_bahagian);
    }

    public function test_kategori_dengan_dokumen_dipadam_dengan_nota(): void
    {
        $this->dokumenDalam();
        $this->dokumenDalam();

        $this->actingAs($this->admin)->delete(route('jenis-dokumen.destroy', $this->jenis->jenis_dokumen_id))
            ->assertSessionHas('success', 'Kategori Dokumen telah dipadam. 2 dokumen sedia ada kekal dalam arkib dengan nama kategori ini.');

        $this->assertSoftDeleted($this->jenis);
    }

    public function test_senarai_hantar_bilangan_rujukan_ke_modal_padam(): void
    {
        $bahagian = $this->buatBahagian('KEW', 'Bahagian Kewangan');
        $this->buatPengguna(['STAFF'], ['bahagian_id' => $bahagian->bahagian_id]);
        $this->dokumenDalam($bahagian);

        $this->actingAs($this->admin)->get(route('bahagian.index'))->assertOk()
            ->assertSee('data-bil-staf="1"', false)
            ->assertSee('data-bil-dokumen="1"', false)
            ->assertSee('id="padamSekat"', false);

        $this->actingAs($this->admin)->get(route('jenis-dokumen.index'))->assertOk()
            ->assertSee('data-bil-dokumen="1"', false)
            ->assertSee('id="padamAmaran"', false);
    }
}
