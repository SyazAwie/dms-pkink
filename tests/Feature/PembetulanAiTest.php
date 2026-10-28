<?php

namespace Tests\Feature;

use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\PembetulanAi;
use App\Models\User;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use Illuminate\Http\Client\Request as PermintaanHttp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * P16: rekod pembetulan staf ke atas cadangan AI, contoh few-shot dalam prompt,
 * dan statistik ketepatan di Status Sistem.
 */
class PembetulanAiTest extends TestCase
{
    use SkemaUjian;

    private User $kerani;

    private JenisDokumen $jenis;

    private DokumenField $siri;

    private DokumenField $jumlah;

    private DokumenField $catatan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->kerani = $this->buatPengguna(['KERANI'], ['bahagian_id' => $this->buatBahagian()->bahagian_id]);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon Komputer']);
        $medan = fn ($nama, $kod, $jenis, $susunan) => DokumenField::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
            'nama_field' => $nama, 'kod_field' => $kod, 'jenis_data' => $jenis, 'susunan' => $susunan]);
        $this->siri = $medan('No. Siri', 'no_siri', 'teks', 1);
        $this->jumlah = $medan('Jumlah', 'jumlah', 'nombor', 2);
        $this->catatan = $medan('Catatan', 'catatan', 'teks', 3);
    }

    public function test_simpan_imbas_arkib_rekod_nilai_ai_berbanding_nilai_staf(): void
    {
        Storage::fake('local');
        $this->mock(OcrService::class, fn (MockInterface $m) => $m->shouldReceive('proses')->andReturn(['teks' => 'BORANG', 'skor' => 90]));
        $this->mock(OllamaExtractionService::class, fn (MockInterface $m) => $m->shouldReceive('ekstrak')->andReturn([
            $this->siri->dokumen_field_id => 'ABC123', $this->jumlah->dokumen_field_id => '5', $this->catatan->dokumen_field_id => null,
        ]));

        $res = $this->actingAs($this->kerani)->post(route('imbas-arkib.analisis'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
            'fail_scan' => UploadedFile::fake()->create('borang.pdf', 20, 'application/pdf'),
        ]);
        $token = basename(parse_url($res->headers->get('Location'), PHP_URL_PATH));

        $this->actingAs($this->kerani)->post(route('imbas-arkib.simpan', $token), [
            'tarikh_dokumen' => '2026-10-01', 'perkara' => 'Permohonan',
            'medan_data' => [$this->siri->dokumen_field_id => 'abc123 ', $this->jumlah->dokumen_field_id => '2'],
        ])->assertRedirect();

        // Catatan: AI & staf kedua-duanya kosong → tiada baris; siri sama (huruf/ruang diabaikan)
        $rekod = PembetulanAi::orderBy('dokumen_field_id')->get();
        $this->assertSame([[$this->siri->dokumen_field_id, 'ABC123', true], [$this->jumlah->dokumen_field_id, '5', false]],
            $rekod->map(fn ($r) => [$r->dokumen_field_id, $r->nilai_ai, $r->sama])->all());
        $this->assertSame('2', $rekod[1]->nilai_disimpan);
        $this->assertSame($this->kerani->user_id, $rekod[1]->user_id);
        $this->assertSame(config('services.ollama.model'), $rekod[1]->model_ai);
    }

    private function promptDihantar(): string
    {
        $prompt = '';
        Http::fake(function (PermintaanHttp $r) use (&$prompt) {
            $prompt = $r['prompt'] ?? '';

            return Http::response(['response' => '{}']);
        });
        app(OllamaExtractionService::class)->ekstrak('BORANG', $this->jenis->fields()->get());

        return $prompt;
    }

    public function test_prompt_mengandungi_contoh_pembetulan_kategori_sahaja(): void
    {
        $this->assertStringNotContainsString('Kesilapan lalu', $this->promptDihantar());

        $buat = fn ($jenisId, $medanId, $ai, $betul, $sama = false) => PembetulanAi::create(['dokumen_id' => 1, 'jenis_dokumen_id' => $jenisId,
            'dokumen_field_id' => $medanId, 'nilai_ai' => $ai, 'nilai_disimpan' => $betul, 'sama' => $sama]);
        $buat($this->jenis->jenis_dokumen_id, $this->catatan->dokumen_field_id, 'ERASED BN', null);
        $buat($this->jenis->jenis_dokumen_id, $this->jumlah->dokumen_field_id, '5', '2 RAHSIA');
        $buat($this->jenis->jenis_dokumen_id, $this->siri->dokumen_field_id, 'X1', 'X1', true);
        $buat($this->jenis->jenis_dokumen_id + 1, $this->siri->dokumen_field_id, 'KATEGORI LAIN', 'Y');

        $prompt = $this->promptDihantar();
        $this->assertStringContainsString('Kesilapan lalu bagi jenis dokumen ini', $prompt);
        $this->assertStringContainsString('- kod "catatan": "ERASED BN" adalah SALAH (medan ini biasanya kosong', $prompt);
        $this->assertStringContainsString('- kod "jumlah": "5" adalah SALAH', $prompt);
        // Nilai betul dokumen lama tidak pernah masuk ke prompt dokumen lain (model menyalinnya)
        $this->assertStringNotContainsString('2 RAHSIA', $prompt);
        $this->assertStringNotContainsString('X1', $prompt);
        $this->assertStringNotContainsString('KATEGORI LAIN', $prompt);

        config(['services.ollama.few_shot' => false]);
        $this->assertStringNotContainsString('Kesilapan lalu', $this->promptDihantar());
    }

    public function test_status_sistem_papar_ketepatan_ai(): void
    {
        Process::fake();
        Http::fake(['*' => Http::response(['models' => []])]);
        $admin = $this->buatPengguna(['ADMIN']);
        $this->actingAs($admin)->get(route('status-sistem.index'))->assertOk()->assertSee('Belum ada data.');

        foreach ([[$this->siri, true], [$this->jumlah, false], [$this->catatan, true], [$this->catatan, true]] as $i => [$m, $sama]) {
            PembetulanAi::create(['dokumen_id' => $i < 3 ? 1 : 2, 'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
                'dokumen_field_id' => $m->dokumen_field_id, 'nilai_ai' => 'a', 'nilai_disimpan' => $sama ? 'a' : 'b', 'sama' => $sama]);
        }

        $this->actingAs($admin)->get(route('status-sistem.index'))->assertOk()
            ->assertSee('Borang Pohon Komputer')->assertSee('75%')
            ->assertSee('Jumlah')->assertSee('1 / 1 dibetulkan')->assertDontSee('0 / 2 dibetulkan');
    }
}
