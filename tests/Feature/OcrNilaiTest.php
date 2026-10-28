<?php

namespace Tests\Feature;

use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * P15: php artisan ocr:nilai — ketepatan setiap medan terhadap dokumen berlabel.
 */
class OcrNilaiTest extends TestCase
{
    use SkemaUjian;

    private string $folder;

    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('local');
        $this->folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ocr_nilai_'.uniqid();
        File::makeDirectory($this->folder);

        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon Komputer']);
        $siri = DokumenField::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'No. Siri', 'kod_field' => 'no_siri', 'jenis_data' => 'teks']);
        $nama = DokumenField::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'Nama', 'kod_field' => 'nama', 'jenis_data' => 'teks']);

        $this->mock(OcrService::class, fn (MockInterface $m) => $m->shouldReceive('proses')->andReturn(['teks' => 'BORANG', 'skor' => 88.0]));
        $this->mock(OllamaExtractionService::class, fn (MockInterface $m) => $m->shouldReceive('ekstrak')->andReturn([
            $siri->dokumen_field_id => 'ABC123', $nama->dokumen_field_id => 'ERASED BN',
            OllamaExtractionService::TARIKH_DOKUMEN => '2026-10-07', OllamaExtractionService::PERKARA => 'Borang Pohon',
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    public function test_nilai_ketepatan_label_disahkan_sahaja(): void
    {
        File::put("{$this->folder}/1-borang.pdf", '%PDF');
        File::put("{$this->folder}/1-borang.label.json", json_encode(['kategori' => 'BPK', 'disahkan' => true,
            'tarikh_dokumen' => '2026-10-07', 'medan' => ['no_siri' => 'abc123', 'nama' => null]]));
        File::put("{$this->folder}/2-draf.pdf", '%PDF');
        File::put("{$this->folder}/2-draf.label.json", json_encode(['kategori' => 'BPK', 'disahkan' => false, 'medan' => []]));
        $laporan = "{$this->folder}/laporan.json";

        $this->artisan('ocr:nilai', ['folder' => $this->folder, '--simpan' => $laporan])
            ->expectsOutputToContain('Label belum disahkan, dilangkau: 2-draf.pdf')
            ->expectsOutputToContain('Keseluruhan: 2/3 medan betul (66.7%), 1 dokumen')
            ->assertSuccessful();

        $json = json_decode(File::get($laporan), true);
        $this->assertFalse($json['dokumen'][0]['medan']['nama']['betul']);
        $this->assertSame('ERASED BN', $json['dokumen'][0]['medan']['nama']['ai']);
        $this->assertSame([], Storage::disk('local')->allFiles('ocr_sementara'), 'Fail sementara dibersihkan');
    }

    public function test_jana_draf_label_untuk_disemak(): void
    {
        File::put("{$this->folder}/3-baharu.pdf", '%PDF');

        $this->artisan('ocr:nilai', ['folder' => $this->folder, '--jana-label' => 'BPK'])
            ->expectsOutputToContain('Draf label dijana: 3-baharu.label.json')->assertSuccessful();

        $label = json_decode(File::get("{$this->folder}/3-baharu.label.json"), true);
        $this->assertFalse($label['disahkan']);
        $this->assertSame(['no_siri' => 'ABC123', 'nama' => 'ERASED BN'], $label['medan']);
        $this->assertSame('2026-10-07', $label['tarikh_dokumen']);
    }
}
