<?php

namespace Tests\Feature;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * OCR 3 Situasi, Fasa A2: borang diisi secara digital (fail imbasan pilihan)
 * dan butang "Simpan & Hantar untuk Kelulusan".
 */
class IsiBorangDigitalTest extends TestCase
{
    use SkemaUjian;

    private User $staf;
    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('local');
        $bahagian = $this->buatBahagian();
        $admin = $this->buatPengguna(['ADMIN'], ['bahagian_id' => $bahagian->bahagian_id]);
        $this->staf = $this->buatPengguna(['STAFF'], ['bahagian_id' => $bahagian->bahagian_id]);

        $this->actingAs($admin)->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => 'MOHON', 'nama_dokumen' => 'Borang Permohonan',
            'medan' => [['nama_field' => 'Tujuan', 'kod_field' => 'tujuan', 'jenis_data' => 'teks', 'is_required' => '1']],
        ])->assertSessionHasNoErrors();

        $this->jenis = JenisDokumen::with('fields')->sole();
    }

    private function muatNaik(array $tambahan = [])
    {
        return $this->actingAs($this->staf)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'tarikh_dokumen' => '2026-10-26',
            'medan_data' => [$this->jenis->fields->first()->dokumen_field_id => 'Kursus'],
        ] + $tambahan);
    }

    public function test_simpan_tanpa_fail_imbasan(): void
    {
        $this->muatNaik(['tindakan' => 'simpan'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('dokumen.index'));

        $dokumen = Dokumen::sole();
        $this->assertSame(0, DokumenScan::count());
        $this->assertSame(0, Borang::count());

        $this->actingAs($this->staf)->get(route('dokumen.show', $dokumen->dokumen_id))
            ->assertOk()
            ->assertSee('Diisi secara digital (tiada fail imbasan).');
    }

    public function test_simpan_dan_hantar_cipta_borang_dalam_proses(): void
    {
        $res = $this->muatNaik([
            'tindakan' => 'hantar',
            'fail_scan' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $dokumen = Dokumen::sole();
        $res->assertRedirect(route('dokumen.show', $dokumen->dokumen_id));

        $borang = Borang::sole();
        $this->assertSame($dokumen->dokumen_id, $borang->dokumen_id);
        $this->assertSame($this->staf->user_id, $borang->pemohon_id);
        $this->assertSame('Dalam Proses', $borang->status_permohonan);
        $this->assertNotNull($borang->tarikh_permohonan);
        $this->assertSame(1, DokumenScan::count());

        $log = LogAudit::where('tindakan', 'Hantar untuk Kelulusan')->sole();
        $this->assertSame('borang', $log->nama_jadual);
        $this->assertSame($borang->borang_id, (int) $log->rekod_id);
    }

    public function test_tindakan_tidak_sah_ditolak(): void
    {
        $this->muatNaik(['tindakan' => 'lulus'])->assertSessionHasErrors('tindakan');
        $this->assertSame(0, Dokumen::count());
    }

    public function test_dokumen_berfail_tidak_boleh_dikosongkan(): void
    {
        $this->muatNaik(['fail_scan' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])->assertSessionHasNoErrors();
        $dokumen = Dokumen::sole();

        $this->actingAs($this->staf)->put(route('dokumen.update', $dokumen->dokumen_id), [
            'tarikh_dokumen' => '2026-10-26',
            'medan_data' => [$this->jenis->fields->first()->dokumen_field_id => 'Kursus'],
            'hapus_scan' => [DokumenScan::sole()->dokumen_scan_id],
        ])->assertSessionHasErrors('fail_scan');

        $this->assertSame(1, DokumenScan::count());
    }

    public function test_dokumen_digital_boleh_dikemas_kini_tanpa_fail(): void
    {
        $this->muatNaik()->assertSessionHasNoErrors();
        $dokumen = Dokumen::sole();

        $this->actingAs($this->staf)->put(route('dokumen.update', $dokumen->dokumen_id), [
            'tarikh_dokumen' => '2026-10-27',
            'medan_data' => [$this->jenis->fields->first()->dokumen_field_id => 'Bengkel'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-10-27', $dokumen->fresh()->tarikh_dokumen->format('Y-m-d'));
    }
}
