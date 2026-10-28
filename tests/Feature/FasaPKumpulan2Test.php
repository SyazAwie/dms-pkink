<?php

namespace Tests\Feature;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenField;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Fasa P kumpulan 2: penapis berstruktur EAV (P8), slip kelulusan ber-QR (P6),
 * pratonton & muat turun bertera air (P11).
 */
class FasaPKumpulan2Test extends TestCase
{
    use SkemaUjian;

    private User $admin;

    private JenisDokumen $jenis;

    private DokumenField $harga;

    private DokumenField $tarikh;

    private DokumenField $jenama;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->admin = $this->buatPengguna(['ADMIN']);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'INV', 'nama_dokumen' => 'Invois']);
        $medan = fn ($nama, $kod, $jenis, $pilihan = null) => DokumenField::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
            'nama_field' => $nama, 'kod_field' => $kod, 'jenis_data' => $jenis, 'pilihan' => $pilihan]);
        $this->harga = $medan('Jumlah', 'jumlah', 'wang');
        $this->tarikh = $medan('Tarikh Invois', 'tarikh_invois', 'tarikh');
        $this->jenama = $medan('Jenama', 'jenama', 'dropdown', ['Dell', 'Lenovo', 'HP']);
        $medan('Catatan', 'catatan', 'teks');
    }

    private function invois(string $rujukan, string $jumlah, string $tarikh, string $jenama): Dokumen
    {
        $d = Dokumen::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => $rujukan,
            'tarikh_dokumen' => '2026-10-27', 'created_by' => $this->admin->user_id]);
        foreach ([[$this->harga, $jumlah], [$this->tarikh, $tarikh], [$this->jenama, $jenama]] as [$m, $v]) {
            DokumenData::create(['dokumen_id' => $d->dokumen_id, 'dokumen_field_id' => $m->dokumen_field_id, 'nilai_data' => $v]);
        }

        return $d;
    }

    private function senarai(array $medan): TestResponse
    {
        return $this->actingAs($this->admin)->get(route('dokumen.index', ['jenis' => $this->jenis->jenis_dokumen_id, 'medan' => $medan]));
    }

    public function test_penapis_julat_nombor_secara_numerik(): void
    {
        $this->invois('INV-SATU', '950.00', '2026-01-10', 'Dell');
        $this->invois('INV-DUA', '1200.00', '2026-03-05', 'Lenovo');
        $this->invois('INV-TIGA', '15000.00', '2026-06-20', 'HP');

        // "950" > "15000" sebagai teks; mesti dibanding sebagai nombor
        $this->senarai([$this->harga->dokumen_field_id => ['dari' => '1000', 'hingga' => '2000']])->assertOk()
            ->assertSee('INV-DUA')->assertDontSee('INV-SATU')->assertDontSee('INV-TIGA')->assertSee('Jumlah: 1000 – 2000');
    }

    public function test_penapis_julat_tarikh_dan_pilihan(): void
    {
        $this->invois('INV-SATU', '950.00', '2026-01-10', 'Dell');
        $this->invois('INV-DUA', '1200.00', '2026-03-05', 'Lenovo');
        $this->invois('INV-TIGA', '15000.00', '2026-06-20', 'Lenovo');

        $this->senarai([$this->tarikh->dokumen_field_id => ['dari' => '2026-02-01']])
            ->assertSee('INV-DUA')->assertSee('INV-TIGA')->assertDontSee('INV-SATU');
        $this->senarai([$this->tarikh->dokumen_field_id => ['dari' => '2026-02-01'], $this->jenama->dokumen_field_id => ['sama' => 'Lenovo']])
            ->assertSee('INV-DUA')->assertSee('INV-TIGA');
        $this->senarai([$this->jenama->dokumen_field_id => ['sama' => 'Dell']])
            ->assertSee('INV-SATU')->assertDontSee('INV-DUA')->assertDontSee('INV-TIGA');
    }

    public function test_panel_hanya_medan_boleh_tapis_dan_validasi(): void
    {
        $this->senarai([])->assertSee('Penapis medan: Invois')->assertSee('Tarikh Invois')->assertDontSee('name="medan['.($this->jenama->dokumen_field_id + 1).']', false);

        $this->senarai([$this->harga->dokumen_field_id => ['dari' => 'abc']])->assertSessionHasErrors('medan.'.$this->harga->dokumen_field_id.'.dari');
        $this->senarai([$this->harga->dokumen_field_id => ['dari' => '500', 'hingga' => '100']])->assertSessionHasErrors('medan.'.$this->harga->dokumen_field_id.'.hingga');
    }

    public function test_eksport_ikut_penapis_medan(): void
    {
        $this->invois('INV-SATU', '950.00', '2026-01-10', 'Dell');
        $this->invois('INV-DUA', '1200.00', '2026-03-05', 'Lenovo');

        $csv = $this->actingAs($this->admin)->get(route('dokumen.eksport', ['jenis' => $this->jenis->jenis_dokumen_id,
            'medan' => [$this->jenama->dokumen_field_id => ['sama' => 'Lenovo']]]))->streamedContent();
        $this->assertStringContainsString('INV-DUA', $csv);
        $this->assertStringNotContainsString('INV-SATU', $csv);
    }

    private function scan(string $nama, string $kandungan, string $mime): DokumenScan
    {
        Storage::disk('local')->put("dokumen_scan/{$nama}", $kandungan);
        $d = $this->invois('INV-FAIL', '10.00', '2026-01-10', 'Dell');

        return DokumenScan::create(['dokumen_id' => $d->dokumen_id, 'nama_fail' => $nama,
            'lokasi_fail' => "dokumen_scan/{$nama}", 'jenis_mime' => $mime]);
    }

    private function png(): string
    {
        $imej = imagecreatetruecolor(600, 800);
        imagefill($imej, 0, 0, imagecolorallocate($imej, 255, 255, 255));
        ob_start();
        imagepng($imej);

        return (string) ob_get_clean();
    }

    public function test_muat_turun_imej_bertera_air_dan_direkod(): void
    {
        Storage::fake('local');
        $asal = $this->png();
        $scan = $this->scan('borang.png', $asal, 'image/png');

        $respons = $this->actingAs($this->admin)->get(route('dokumen.scan.muat-turun', $scan->dokumen_scan_id));
        $respons->assertOk()->assertHeader('Content-Type', 'image/png')->assertDownload('borang_salinan.png');

        $salinan = $respons->streamedContent();
        $this->assertNotSame($asal, $salinan);
        $this->assertSame([600, 800], array_slice(getimagesizefromstring($salinan), 0, 2));
        $this->assertSame($asal, Storage::disk('local')->get('dokumen_scan/borang.png'), 'Fail asal tidak boleh berubah');
        $this->assertDatabaseHas('log_audit', ['tindakan' => 'Muat Turun Fail', 'nama_jadual' => 'dokumen_scan',
            'rekod_id' => $scan->dokumen_scan_id, 'user_id' => $this->admin->user_id]);
    }

    public function test_muat_turun_ikut_dasar_lihat_dokumen(): void
    {
        Storage::fake('local');
        $scan = $this->scan('borang.png', $this->png(), 'image/png');

        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('dokumen.scan.muat-turun', $scan->dokumen_scan_id))->assertForbidden();
        $this->assertSame(0, LogAudit::where('tindakan', 'Muat Turun Fail')->count());
    }

    public function test_muat_turun_pdf_gagal_dengan_mesej_tanpa_log(): void
    {
        Storage::fake('local');
        config(['services.ocr.pdftoppm_bin' => 'tiada-pdftoppm-ujian.exe', 'app.debug' => false]);
        $scan = $this->scan('surat.pdf', "%PDF-1.4\n%%EOF", 'application/pdf');

        $this->actingAs($this->admin)->from(route('dokumen.show', $scan->dokumen_id))
            ->get(route('dokumen.scan.muat-turun', $scan->dokumen_scan_id))
            ->assertRedirect(route('dokumen.show', $scan->dokumen_id))
            ->assertSessionHas('error', 'Salinan "surat.pdf" tidak dapat dijana. Sila hubungi Pentadbir Sistem.');
        $this->assertSame(0, LogAudit::where('tindakan', 'Muat Turun Fail')->count());
    }

    public function test_butiran_papar_pratonton_dan_pautan_muat_turun(): void
    {
        Storage::fake('local');
        $pdf = $this->scan('surat.pdf', "%PDF-1.4\n%%EOF", 'application/pdf');
        $imej = DokumenScan::create(['dokumen_id' => $pdf->dokumen_id, 'nama_fail' => 'lampiran.png',
            'lokasi_fail' => 'dokumen_scan/lampiran.png', 'jenis_mime' => 'image/png']);

        $this->actingAs($this->admin)->get(route('dokumen.show', $pdf->dokumen_id))->assertOk()
            ->assertSee('<iframe class="pratonton-pdf"', false)
            ->assertSee('src="'.route('dokumen.scan.papar', $pdf->dokumen_scan_id).'#view=FitH"', false)
            ->assertSee('data-src="'.route('dokumen.scan.papar', $imej->dokumen_scan_id).'"', false)
            ->assertSee(route('dokumen.scan.muat-turun', $pdf->dokumen_scan_id))
            ->assertSee(route('dokumen.scan.muat-turun', $imej->dokumen_scan_id));
    }

    private function borangLulus(): Borang
    {
        $d = $this->invois('INV-LULUS', '10.00', '2026-01-10', 'Dell');
        $pemohon = $this->buatPengguna(['STAFF']);
        $pelulus = $this->buatPengguna(['PELULUS']);

        return Borang::create(['dokumen_id' => $d->dokumen_id, 'pemohon_id' => $pemohon->user_id,
            'pegawai_pelulus_id' => $pelulus->user_id, 'status_permohonan' => 'Diluluskan',
            'tarikh_permohonan' => now()->subDay(), 'tarikh_kelulusan' => now()->startOfMinute()]);
    }

    public function test_slip_kelulusan_pdf_dijana_dan_direkod(): void
    {
        $borang = $this->borangLulus();

        $this->actingAs($this->admin)->get(route('dokumen.show', $borang->dokumen_id))
            ->assertSee(route('kelulusan.slip', $borang->dokumen_id));

        $respons = $this->actingAs($this->admin)->get(route('kelulusan.slip', $borang->dokumen_id));
        $respons->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertDownload('Slip_Kelulusan_INV-LULUS.pdf');
        $this->assertStringStartsWith('%PDF', $respons->streamedContent());
        $this->assertDatabaseHas('log_audit', ['tindakan' => 'Jana Slip Kelulusan', 'rekod_id' => $borang->borang_id]);
    }

    public function test_slip_hanya_bagi_permohonan_diluluskan_dan_ikut_dasar(): void
    {
        $borang = $this->borangLulus();
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('kelulusan.slip', $borang->dokumen_id))->assertForbidden();

        $borang->update(['status_permohonan' => 'Disokong']);
        $this->actingAs($this->admin)->get(route('dokumen.show', $borang->dokumen_id))
            ->assertDontSee(route('kelulusan.slip', $borang->dokumen_id));
        $this->actingAs($this->admin)->from(route('dokumen.show', $borang->dokumen_id))
            ->get(route('kelulusan.slip', $borang->dokumen_id))
            ->assertRedirect(route('dokumen.show', $borang->dokumen_id))
            ->assertSessionHas('error', 'Slip kelulusan hanya boleh dijana bagi permohonan yang telah diluluskan.');
    }

    public function test_pengesahan_awam_slip_sah_dan_tidak_sah(): void
    {
        $borang = $this->borangLulus();
        $kod = $borang->kodPengesahan();
        $this->assertMatchesRegularExpression('/^[0-9A-F]{12}$/', $kod);

        // Tanpa log masuk
        $this->get(route('slip.sahkan', [$borang->borang_id, $kod]))->assertOk()
            ->assertSee('Slip Sah')->assertSee('INV-LULUS')->assertSee($borang->pemohon->nama_staff);
        $this->get(route('slip.sahkan', [$borang->borang_id, strtolower($kod)]))->assertSee('Slip Sah');
        $this->get(route('slip.sahkan', [$borang->borang_id, 'AAAAAAAAAAAA']))->assertOk()
            ->assertSee('Slip Tidak Sah')->assertDontSee('INV-LULUS');
        $this->get(route('slip.sahkan', [999, $kod]))->assertSee('Slip Tidak Sah');

        // Diluluskan semula (tarikh baharu): slip lama tidak sah lagi
        $borang->update(['tarikh_kelulusan' => now()->addHour()]);
        $this->get(route('slip.sahkan', [$borang->borang_id, $kod]))->assertSee('Slip Tidak Sah');

        // Dokumen dipadam: tidak sah
        $kodBaharu = $borang->fresh()->kodPengesahan();
        $borang->dokumen->delete();
        $this->get(route('slip.sahkan', [$borang->borang_id, $kodBaharu]))->assertSee('Slip Tidak Sah');
    }
}
