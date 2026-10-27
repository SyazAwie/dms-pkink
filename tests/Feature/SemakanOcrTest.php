<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenOcr;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\User;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Semakan teks OCR oleh staf: Tandakan Disemak / Betulkan Teks (status_semakan).
 */
class SemakanOcrTest extends TestCase
{
    use SkemaUjian;

    private User $kerani;
    private Dokumen $dokumen;
    private DokumenScan $scan;
    private DokumenOcr $ocr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->kerani = $this->buatPengguna(['KERANI']);
        $jenis = JenisDokumen::create(['kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon']);
        $this->dokumen = Dokumen::create(['jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'no_rujukan' => 'BPK/2026/0001',
            'tarikh_dokumen' => '2026-10-27', 'created_by' => $this->kerani->user_id]);
        $this->scan = DokumenScan::create(['dokumen_id' => $this->dokumen->dokumen_id, 'nama_fail' => 'borang.pdf',
            'lokasi_fail' => 'dokumen/borang.pdf', 'status_ocr' => 'Selesai']);
        $this->ocr = DokumenOcr::create(['dokumen_scan_id' => $this->scan->dokumen_scan_id, 'teks_dibaca' => 'Nama: AHMAD H4KIM',
            'skor_padanan' => 81.5, 'status_semakan' => 'Belum Disemak']);
    }

    public function test_tandakan_disemak(): void
    {
        $this->actingAs($this->kerani)->post(route('dokumen.scan.ocr.semak', $this->scan->dokumen_scan_id))
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame('Disemak', $this->ocr->fresh()->status_semakan);
        $log = LogAudit::where('tindakan', 'Semak Teks OCR')->sole();
        $this->assertSame($this->kerani->user_id, $log->user_id);
        $this->assertSame(['status_semakan' => 'Belum Disemak'], $log->data_lama);
    }

    public function test_betulkan_teks_simpan_teks_asal_dalam_log(): void
    {
        $this->actingAs($this->kerani)->put(route('dokumen.scan.ocr.betulkan', $this->scan->dokumen_scan_id), [
            'teks_dibaca' => "Nama: AHMAD HAKIM\r\nNo. KP: 990101010104",
        ])->assertRedirect()->assertSessionHas('success');

        $ocr = $this->ocr->fresh();
        $this->assertSame("Nama: AHMAD HAKIM\nNo. KP: 990101010104", $ocr->teks_dibaca);
        $this->assertSame('Dibetulkan', $ocr->status_semakan);

        $log = LogAudit::where('tindakan', 'Betulkan Teks OCR')->sole();
        $this->assertSame(['teks_dibaca' => 'Nama: AHMAD H4KIM'], $log->data_lama);
        $this->assertSame("Nama: AHMAD HAKIM\nNo. KP: 990101010104", $log->data_baharu['teks_dibaca']);
    }

    public function test_teks_kosong_ditolak(): void
    {
        $this->actingAs($this->kerani)->put(route('dokumen.scan.ocr.betulkan', $this->scan->dokumen_scan_id), ['teks_dibaca' => ''])
            ->assertSessionHasErrors(['teks_dibaca' => 'Teks OCR tidak boleh dikosongkan.']);

        $this->assertSame('Nama: AHMAD H4KIM', $this->ocr->fresh()->teks_dibaca);
    }

    public function test_tanpa_kebenaran_edit_disekat(): void
    {
        $penyokong = $this->buatPengguna(['PENYOKONG']);

        $this->actingAs($penyokong)->post(route('dokumen.scan.ocr.semak', $this->scan->dokumen_scan_id))->assertForbidden();
        $this->actingAs($penyokong)->put(route('dokumen.scan.ocr.betulkan', $this->scan->dokumen_scan_id), ['teks_dibaca' => 'X'])->assertForbidden();
        $this->assertSame('Belum Disemak', $this->ocr->fresh()->status_semakan);

        // Penyokong nampak status semakan, tetapi tiada butang
        $this->actingAs($penyokong)->get(route('dokumen.show', $this->dokumen->dokumen_id))->assertOk()
            ->assertSee('Belum Disemak')
            ->assertDontSee('Tandakan Disemak');
    }

    public function test_halaman_dokumen_papar_butang_semakan(): void
    {
        $this->actingAs($this->kerani)->get(route('dokumen.show', $this->dokumen->dokumen_id))->assertOk()
            ->assertSee('Tandakan Disemak')
            ->assertSee('Betulkan Teks')
            ->assertSee(route('dokumen.scan.ocr.betulkan', $this->scan->dokumen_scan_id));
    }
}
