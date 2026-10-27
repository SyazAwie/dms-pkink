<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * S7: metadata fail imbasan (saiz, MIME, SHA-256) + arahan fail:integriti.
 */
class IntegritiFailTest extends TestCase
{
    use SkemaUjian;

    private Dokumen $dokumen;
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('local');
        $this->folder = sys_get_temp_dir() . '/integriti_ujian_' . uniqid();
        config(['services.sandaran.folder' => $this->folder]);

        $staf = $this->buatPengguna(['STAFF']);
        $jenis = JenisDokumen::create(['kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon']);
        $this->dokumen = Dokumen::create(['jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'no_rujukan' => 'BPK/2026/0001',
            'tarikh_dokumen' => '2026-10-27', 'created_by' => $staf->user_id]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    private function scan(string $laluan, ?string $kandungan): DokumenScan
    {
        if ($kandungan !== null) {
            Storage::disk('local')->put($laluan, $kandungan);
        }

        return DokumenScan::create(['dokumen_id' => $this->dokumen->dokumen_id, 'nama_fail' => basename($laluan), 'lokasi_fail' => $laluan]);
    }

    public function test_fail_baharu_diisi_saiz_mime_dan_hash(): void
    {
        $scan = $this->scan('dokumen_scan/a.pdf', '%PDF-1.4 ujian');

        $this->assertSame(14, $scan->saiz_fail);
        $this->assertSame(hash('sha256', '%PDF-1.4 ujian'), $scan->hash_sha256);
        $this->assertNotNull($scan->jenis_mime);
        $this->assertSame('1 KB', $scan->saizMudahDibaca());
    }

    public function test_rekod_tanpa_fail_tidak_menghalang_simpanan(): void
    {
        $scan = $this->scan('dokumen_scan/tiada.pdf', null);

        $this->assertNull($scan->hash_sha256);
        $this->assertNull($scan->saizMudahDibaca());
    }

    public function test_arahan_isi_metadata_lama_dan_lapor_fail_hilang_atau_berubah(): void
    {
        $lama = $this->scan('dokumen_scan/lama.pdf', 'fail lama');
        $lama->forceFill(['saiz_fail' => null, 'jenis_mime' => null, 'hash_sha256' => null])->saveQuietly(); // rekod sebelum S7
        $berubah = $this->scan('dokumen_scan/berubah.pdf', 'asal');
        Storage::disk('local')->put('dokumen_scan/berubah.pdf', 'telah diubah');
        $hilang = $this->scan('dokumen_scan/hilang.pdf', 'akan hilang');
        Storage::disk('local')->delete('dokumen_scan/hilang.pdf');
        $this->scan('dokumen_scan/utuh.pdf', 'utuh');

        $this->artisan('fail:integriti')->assertFailed();

        $this->assertSame(hash('sha256', 'fail lama'), $lama->fresh()->hash_sha256);
        $status = json_decode(File::get("{$this->folder}/integriti.json"), true);
        $this->assertFalse($status['utuh']);
        $this->assertSame([4, 1, 1, 1], [$status['disemak'], $status['diisi'], $status['hilang'], $status['berubah']]);
        $this->assertEqualsCanonicalizing(
            [['Hilang', $hilang->dokumen_scan_id], ['Berubah', $berubah->dokumen_scan_id]],
            array_map(fn ($m) => [$m['status'], $m['id']], $status['masalah'])
        );
    }

    public function test_semua_utuh_berjaya(): void
    {
        $this->scan('dokumen_scan/a.pdf', 'a');
        $this->scan('dokumen_scan/b.pdf', 'b');

        $this->artisan('fail:integriti')->assertSuccessful();
        $this->assertTrue(json_decode(File::get("{$this->folder}/integriti.json"), true)['utuh']);
    }

    public function test_dijadualkan_setiap_isnin(): void
    {
        $acara = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'fail:integriti'));

        $this->assertNotNull($acara);
        $this->assertSame('45 12 * * 1', $acara->expression);
    }
}
