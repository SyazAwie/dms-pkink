<?php

namespace Tests\Feature;

use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * OCR Pantas & Tepat P4–P6: AI turut mengisi Tarikh Dokumen & Perkara, prompt meminta
 * JSON padat, dan skrin semak memaparkan masa pemprosesan.
 */
class AiMedanAsasTest extends TestCase
{
    use SkemaUjian;

    private function medan()
    {
        $f = new DokumenField(['nama_field' => 'Daripada', 'kod_field' => 'daripada', 'jenis_data' => 'teks']);
        $f->dokumen_field_id = 1;

        return collect([$f]);
    }

    public function test_ekstrak_tarikh_dan_perkara_disahkan(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => json_encode([
            'daripada' => 'Ketua Unit ICT',
            '_tarikh_dokumen' => '2026-10-26',
            '_perkara' => "  PENYELENGGARAAN BERJADUAL\n PELAYAN FAIL ",
        ])])]);

        $hasil = app(OllamaExtractionService::class)->ekstrak('MEMO ...', $this->medan(), true);

        $this->assertSame([
            1 => 'Ketua Unit ICT',
            OllamaExtractionService::TARIKH_DOKUMEN => '2026-10-26',
            OllamaExtractionService::PERKARA => 'PENYELENGGARAAN BERJADUAL PELAYAN FAIL',
        ], $hasil);
        Http::assertSent(fn (Request $r) => str_contains($r['prompt'], '"_tarikh_dokumen"')
            && str_contains($r['prompt'], 'JSON padat'));
    }

    public function test_tarikh_tidak_sah_dibuang(): void
    {
        foreach (['2026-02-30', '26/10/2026', '1890-01-01', '2999-01-01', 'Perkara'] as $nilai) {
            Http::fake(['*/api/generate' => Http::response(['response' => json_encode([
                '_tarikh_dokumen' => $nilai, '_perkara' => 'Perkara',
            ])])]);

            $hasil = app(OllamaExtractionService::class)->ekstrak('teks', $this->medan(), true);

            $this->assertNull($hasil[OllamaExtractionService::TARIKH_DOKUMEN], $nilai);
            $this->assertNull($hasil[OllamaExtractionService::PERKARA], 'label "Perkara" bukan nilai');
        }
    }

    public function test_label_borang_disalin_sebagai_nilai_dibuang(): void
    {
        $nama = new DokumenField(['nama_field' => 'Nama Ketua Jabatan', 'kod_field' => 'nama', 'jenis_data' => 'teks']);
        $nama->dokumen_field_id = 1;
        $jabatan = new DokumenField(['nama_field' => 'Jabatan / Bahagian', 'kod_field' => 'jabatan', 'jenis_data' => 'teks']);
        $jabatan->dokumen_field_id = 2;
        Http::fake(['*/api/generate' => Http::response(['response' => json_encode([
            'nama' => 'Jabatan/Bahagian:', 'jabatan' => 'Bahagian Digital',
        ])])]);

        $hasil = app(OllamaExtractionService::class)->ekstrak('teks', collect([$nama, $jabatan]));

        $this->assertSame([1 => null, 2 => 'Bahagian Digital'], $hasil);
    }

    public function test_tanpa_medan_asas_tiada_kunci_tambahan(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => '{"daripada":"Ali"}'])]);

        $this->assertSame([1 => 'Ali'], app(OllamaExtractionService::class)->ekstrak('teks', $this->medan()));
        Http::assertSent(fn (Request $r) => !str_contains($r['prompt'], '_tarikh_dokumen'));
    }

    public function test_skrin_semak_isi_tarikh_perkara_dan_masa(): void
    {
        $this->sediakanSkema();
        Storage::fake('local');
        $kerani = $this->buatPengguna(['KERANI']);
        $jenis = JenisDokumen::create(['kod_dokumen' => 'MEMO', 'nama_dokumen' => 'Memo Dalaman']);
        $medan = DokumenField::create([
            'jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'nama_field' => 'Daripada',
            'kod_field' => 'daripada', 'jenis_data' => 'teks', 'is_required' => false, 'susunan' => 1,
        ]);
        $this->mock(OcrService::class, fn (MockInterface $m) => $m
            ->shouldReceive('proses')->andReturn(['teks' => 'MEMO DALAMAN', 'skor' => 91.3]));
        $this->mock(OllamaExtractionService::class, fn (MockInterface $m) => $m
            ->shouldReceive('ekstrak')->withArgs(fn ($teks, $m, $asas) => $asas === true)->andReturn([
                $medan->dokumen_field_id => 'Ketua Unit ICT',
                OllamaExtractionService::TARIKH_DOKUMEN => '2026-10-26',
                OllamaExtractionService::PERKARA => 'PENYELENGGARAAN PELAYAN',
            ]));

        $res = $this->actingAs($kerani)->post(route('imbas-arkib.analisis'), [
            'jenis_dokumen_id' => $jenis->jenis_dokumen_id,
            'fail_scan' => UploadedFile::fake()->create('memo.pdf', 10, 'application/pdf'),
        ]);
        $token = basename(parse_url($res->headers->get('Location'), PHP_URL_PATH));

        $this->actingAs($kerani)->get(route('imbas-arkib.semak', $token))
            ->assertOk()
            ->assertSee('name="tarikh_dokumen" class="dms-input " value="2026-10-26"', false)
            ->assertSee('value="PENYELENGGARAAN PELAYAN"', false)
            ->assertSee('Diproses dalam')
            ->assertDontSee('Tarikh dokumen tidak dikesan');
    }
}
