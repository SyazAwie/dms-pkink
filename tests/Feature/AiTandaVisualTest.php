<?php

namespace Tests\Feature;

use App\Models\DokumenField;
use App\Services\OllamaExtractionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * T6: AI tidak mengisi medan kosong dengan teks bercetak borang. Penapis berketentuan
 * selepas AI (model 8B tidak mematuhi peraturan prompt dengan konsisten) + peraturan penanda
 * dalam prompt hanya bila teks OCR mengandungi penanda.
 */
class AiTandaVisualTest extends TestCase
{
    /** Jawapan JSON AI palsu bagi panggilan seterusnya (Http::fake berulang tidak menggantikan stub pertama). */
    private array $jawapan = [];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*/api/generate' => fn () => Http::response(['response' => json_encode($this->jawapan)])]);
    }

    private function medan(string $kod, string $nama, string $jenis, ?array $pilihan = null, int $id = 1): DokumenField
    {
        $f = new DokumenField(['kod_field' => $kod, 'nama_field' => $nama, 'jenis_data' => $jenis, 'pilihan' => $pilihan]);
        $f->dokumen_field_id = $id;

        return $f;
    }

    /** Jalankan ekstrak dengan jawapan AI palsu; pulangkan nilai medan id 1. */
    private function ekstrak(DokumenField $medan, mixed $jawapanAi, string $teksOcr): mixed
    {
        $this->jawapan = [$medan->kod_field => $jawapanAi];

        return app(OllamaExtractionService::class)->ekstrak($teksOcr, collect([$medan]))[1];
    }

    public function test_pilihan_bercetak_tanpa_potongan_belum_diputuskan(): void
    {
        $status = $this->medan('status_sokongan', 'Status Sokongan', 'radio', ['Disokong', 'Tidak Disokong']);

        $this->assertNull($this->ekstrak($status, 'Disokong', "Sokongan Pengurus:\nPermohonan: Disokong/ Tidak Sokong\nTarikh:"));
        $this->assertSame('Disokong', $this->ekstrak($status, 'Disokong', "Permohonan disokong/ ~~tidak-sokong—~~\nKomen/Ulasan:"));
        $this->assertSame('Disokong', $this->ekstrak($status, 'Disokong', "Status: Disokong\nTarikh: 1/1/2026"), 'ditulis sendiri');
        // Potongan tangan yang tidak dikesan (teks OCR rosak): perkataan di luar pasangan utuh, kekal
        $keputusan = $this->medan('keputusan', 'Keputusan', 'radio', ['Diluluskan', 'Tidak Diluluskan']);
        $this->assertSame('Diluluskan', $this->ekstrak($keputusan, 'Diluluskan', 'dipertimbangkan dan _diluluskan/ +Hetak~eitetyskam.'));
    }

    public function test_komen_disalin_daripada_baris_pilihan_dibuang(): void
    {
        $komen = $this->medan('komen_bd', 'Komen / Ulasan Bahagian Digital', 'textarea');
        $teks = "Permohonan disokong/ ~~tidak-sokong—~~\nKomen/Ulasan:";

        $this->assertNull($this->ekstrak($komen, 'Permohonan disokong', $teks));
        $this->assertNull($this->ekstrak($komen, 'Permohonan disokong/ ~~tidak-sokong—~~', $teks), 'penanda dalam nilai');
        $this->assertSame('Sila teruskan', $this->ekstrak($komen, 'Sila teruskan', $teks."\nSila teruskan"));
    }

    public function test_nama_organisasi_sendiri_bukan_nilai_tetapi_kepala_surat_pengirim_dikekalkan(): void
    {
        config(['app.nama_organisasi' => 'Perbadanan Kemajuan Iktisad Negeri Kelantan']);
        $jabatan = $this->medan('jabatan', 'Jabatan / Bahagian', 'teks');
        $pengirim = $this->medan('pengirim', 'Pengirim', 'teks');

        $this->assertNull($this->ekstrak($jabatan, 'PERBADANAN KEMAJUAN IKTISAD NEGERI KELANTAN', "PERBADANAN KEMAJUAN IKTISAD NEGERI KELANTAN\nJabatan/ Bahagian:"));
        $this->assertSame('Teknologi Cahaya Timur Sdn. Bhd.',
            $this->ekstrak($pengirim, 'Teknologi Cahaya Timur Sdn. Bhd.', "TEKNOLOGI CAHAYA TIMUR SDN. BHD.\nNo. 12, Jalan Contoh"));
    }

    public function test_nombor_dan_wang_rekaan_dibuang(): void
    {
        $caj = $this->medan('caj', 'Caj Sewaan', 'wang');

        $this->assertNull($this->ekstrak($caj, '1500.50', 'Caj adalah RM ____ (Sewaan Sahaja)'), 'tiada 1500 dalam teks');
        $this->assertNull($this->ekstrak($caj, '0.00', 'Caj adalah RM ____ Tarikh: 12/8/2026'), 'sifar tidak tertulis');
        $this->assertSame('150.00', $this->ekstrak($caj, '150', 'Caj adalah RM 150 (Sewaan Sahaja)'));
        $this->assertSame('0.00', $this->ekstrak($caj, '0', 'Caj adalah RM 0.00 (percuma)'));
    }

    public function test_nilai_sebahagian_label_medan_dibuang(): void
    {
        $kewpa = $this->medan('no_daftar', 'No. Daftar KEW.PA-9', 'teks');

        $this->assertNull($this->ekstrak($kewpa, 'KEW.PA-9', 'Daftar KEW.PA-9: [□]'));
        $this->assertSame('KPA-2026-77', $this->ekstrak($kewpa, 'KPA-2026-77', 'Daftar KEW.PA-9: KPA-2026-77'));
    }

    /** Kes daripada semakan kod: nilai sebenar yang TIDAK boleh dibuang. */
    public function test_nilai_sebenar_dikekalkan(): void
    {
        $wang = $this->medan('jumlah', 'Jumlah', 'wang');
        $this->assertSame('0.50', $this->ekstrak($wang, '0.50', 'Caj RM0.50 sehari'), 'kurang daripada 1');
        $this->assertSame('1500.00', $this->ekstrak($wang, '1500', 'Ringgit Malaysia Seribu Lima Ratus sahaja'), 'ditulis dengan perkataan');
        $this->assertSame('1500000.00', $this->ekstrak($wang, '1500000', 'Peruntukan RM1.5 juta'), 'berskala');
        $this->assertSame('1500.00', $this->ekstrak($wang, '1500', 'Jumlah RM1,500.00'));

        $lain = $this->medan('lain_lain', 'Lain-lain (nyatakan)', 'teks');
        $this->assertSame('Bengkel keusahawanan', $this->ekstrak($lain, 'Bengkel keusahawanan', '[✓] Lain-lain: Bengkel keusahawanan'));
        $nama = $this->medan('nama', 'Nama', 'teks');
        $this->assertSame('Ahmad bin Ali', $this->ekstrak($nama, 'Ahmad bin Ali', 'Nama: Ahmad bin Ali [□] Lelaki [✓] Perempuan'));

        $kenderaan = $this->medan('kenderaan', 'Jenis Kenderaan (Kereta/Motosikal)', 'teks');
        $this->assertSame('Kereta', $this->ekstrak($kenderaan, 'Kereta', 'Jenis Kenderaan: Kereta'));
        $jantina = $this->medan('jantina', 'Jantina (Lelaki/Perempuan)', 'radio', ['Lelaki', 'Perempuan']);
        $this->assertSame('Lelaki', $this->ekstrak($jantina, 'Lelaki', 'Jantina: Lelaki'));

        // "[ ]" dan "~~~~" yang dihasilkan Tesseract sendiri bukan penanda
        $this->assertSame('Ahmad bin Ali', $this->ekstrak($nama, 'Ahmad bin Ali', "Nama: Ahmad bin Ali [ ]\nTarikh: ~~~~~~~~"));
        Http::assertSent(fn (Request $r) => ! str_contains($r['prompt'], 'DIPOTONG'));

        config(['app.nama_organisasi' => 'Perbadanan Kemajuan Iktisad Negeri Kelantan']);
        $pengirim = $this->medan('pengirim', 'Pengirim', 'teks');
        $this->assertSame('Perbadanan Kemajuan Iktisad Negeri Kelantan',
            $this->ekstrak($pengirim, 'Perbadanan Kemajuan Iktisad Negeri Kelantan', 'SURAT KELUAR daripada Perbadanan Kemajuan Iktisad Negeri Kelantan'), 'surat keluar');
    }

    public function test_peraturan_penanda_dalam_prompt_hanya_bila_ada_penanda(): void
    {
        $medan = $this->medan('nama', 'Nama', 'teks');

        $this->ekstrak($medan, 'Ali', 'MEMO DALAMAN Daripada: Ali');
        Http::assertSent(fn (Request $r) => ! str_contains($r['prompt'], 'DIPOTONG') && ! str_contains($r['prompt'], 'kotak bertanda'));

        $this->ekstrak($medan, 'Ali', "Nama: Ali\n[✓] Emel [□] Intranet");
        Http::assertSent(fn (Request $r) => str_contains($r['prompt'], 'DIPOTONG') && str_contains($r['prompt'], 'kotak bertanda'));
    }
}
