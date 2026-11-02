<?php

namespace Tests\Feature;

use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\TemplatBorang;
use App\Services\BezaTemplatService;
use App\Services\OllamaExtractionService;
use Illuminate\Support\Facades\Http;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * TB4: beza imej templat borang kosong dengan imbasan (jajaran afin, dakwat pengisi setiap
 * medan), dan penggunaannya oleh AI (medan kosong pasti, bacaan semula sebagai petunjuk).
 * Juga penapis akhiran bercetak ("syazmi" + "@pkink.gov.my").
 */
class BezaTemplatTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
    }

    private static function kata(array $titik): array
    {
        return array_map(fn ($t) => ['teks' => $t[0], 'x' => $t[1], 'y' => $t[2]], $titik);
    }

    public function test_jajaran_afin_daripada_perkataan_unik_dan_titik_terpencil_dibuang(): void
    {
        $templat = self::kata([['Borang', 0.1, 0.1], ['Nama', 0.1, 0.3], ['Pemohon', 0.3, 0.3], ['Jabatan', 0.1, 0.5],
            ['Tarikh', 0.6, 0.7], ['Tandatangan', 0.2, 0.9], ['Pengesahan', 0.7, 0.85], ['Catatan', 0.5, 0.6], ['dan', 0.4, 0.4], ['dan', 0.5, 0.5]]);
        // Imbasan: berskala 0.98, beralih (+0.02, +0.01); satu perkataan salah padan (terpencil)
        $imbasan = array_map(fn ($k) => ['teks' => $k['teks'], 'x' => 0.98 * $k['x'] + 0.02, 'y' => 0.98 * $k['y'] + 0.01], $templat);
        $imbasan[5]['x'] += 0.2;

        $j = BezaTemplatService::jajar($templat, $imbasan);

        $this->assertNotNull($j);
        $this->assertSame(7, $j['bil'], 'perkataan berulang ("dan") tidak dipakai, titik terpencil dibuang');
        $this->assertEqualsWithDelta(0.98, $j['x'][0], 1e-6);
        $this->assertEqualsWithDelta(0.02, $j['x'][2], 1e-6);
        $this->assertEqualsWithDelta(0.01, $j['y'][2], 1e-6);
    }

    public function test_jajaran_banyak_titik_dengan_titik_pertama_terpencil(): void
    {
        $templat = [];
        for ($i = 0; $i < 60; $i++) {
            $templat[] = ['teks' => "kata{$i}x", 'x' => 0.05 + ($i % 10) * 0.09, 'y' => 0.05 + intdiv($i, 10) * 0.15];
        }
        $imbasan = array_map(fn ($k) => ['teks' => $k['teks'], 'x' => 1.01 * $k['x'] - 0.01, 'y' => 1.01 * $k['y'] + 0.02], $templat);
        $imbasan[0]['y'] += 0.3;
        $imbasan[1]['x'] += 0.25;

        $j = BezaTemplatService::jajar($templat, $imbasan);

        $this->assertSame(58, $j['bil']);
        $this->assertEqualsWithDelta(1.01, $j['x'][0], 1e-6);
        $this->assertEqualsWithDelta(0.02, $j['y'][2], 1e-6);
    }

    public function test_jajaran_ditolak_bila_titik_terlalu_sedikit(): void
    {
        $t = self::kata([['Borang', 0.1, 0.1], ['Nama', 0.1, 0.3], ['Jabatan', 0.1, 0.5]]);

        $this->assertNull(BezaTemplatService::jajar($t, $t));
    }

    public function test_dakwat_hanya_tulisan_pengisi_bukan_cetakan_borang(): void
    {
        // Templat 200x100: garisan isian bercetak di y=60. Imbasan 400x200 (2x) dengan garisan
        // yang sama + tulisan pengisi di atasnya; kotak medan kedua tiada tulisan.
        $templat = imagecreatetruecolor(200, 100);
        imagefill($templat, 0, 0, imagecolorallocate($templat, 255, 255, 255));
        imageline($templat, 10, 60, 190, 60, imagecolorallocate($templat, 0, 0, 0));
        $imbasan = imagecreatetruecolor(400, 200);
        imagefill($imbasan, 0, 0, imagecolorallocate($imbasan, 255, 255, 255));
        $hitam = imagecolorallocate($imbasan, 0, 0, 0);
        imagesetthickness($imbasan, 2);
        imageline($imbasan, 20, 120, 380, 120, $hitam);
        imagefilledrectangle($imbasan, 40, 95, 120, 112, $hitam); // "tulisan"

        $topeng = (new \ReflectionMethod(BezaTemplatService::class, 'topengTemplat'))->invoke(null, $templat);
        $identiti = ['x' => [1.0, 0.0, 0.0], 'y' => [0.0, 1.0, 0.0]];

        $isi = BezaTemplatService::dakwat($imbasan, 400, 200, $topeng, 200, 100, $identiti, ['x' => 0.05, 'y' => 0.45, 'w' => 0.4, 'h' => 0.17]);
        $kosong = BezaTemplatService::dakwat($imbasan, 400, 200, $topeng, 200, 100, $identiti, ['x' => 0.55, 'y' => 0.45, 'w' => 0.4, 'h' => 0.17]);

        $this->assertGreaterThan(1000, $isi['piksel']);
        $this->assertFalse(BezaTemplatService::kosong($isi));
        $this->assertSame(0, $kosong['piksel'], 'garisan isian bercetak bukan dakwat');
        $this->assertTrue(BezaTemplatService::kosong($kosong));
    }

    public function test_dimatikan_atau_tiada_templat_disusun_tiada_pengumpul(): void
    {
        $jenis = JenisDokumen::create(['kod_dokumen' => 'BD', 'nama_dokumen' => 'Borang']);
        TemplatBorang::create(['jenis_dokumen_id' => $jenis->jenis_dokumen_id, 'versi' => 1, 'nama_asal' => 'k.pdf', 'lokasi_fail' => 'X/k.pdf',
            'status_ocr' => 'Selesai', 'aktif' => true, 'ocr_json' => ['teks' => 'Nama', 'skor' => 90, 'token' => ['nama']]]);

        config(['services.ocr.beza_templat' => false]);
        $this->assertNull(BezaTemplatService::untuk($jenis));
        config(['services.ocr.beza_templat' => true]);
        $this->assertNull(BezaTemplatService::untuk($jenis->load('fields')), 'templat belum disusun (TB6)');
    }

    private function medan(int $id, string $kod, string $jenis = 'teks'): DokumenField
    {
        $m = new DokumenField(['kod_field' => $kod, 'nama_field' => ucfirst($kod), 'jenis_data' => $jenis]);
        $m->dokumen_field_id = $id;

        return $m;
    }

    public function test_ai_medan_tanpa_dakwat_dibuang_dan_bacaan_semula_dalam_prompt(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => json_encode(['nama' => 'ALI', 'faks' => '09-7481234', 'status' => 'Lulus'])])]);
        $medan = collect([$this->medan(1, 'nama'), $this->medan(2, 'faks', 'telefon'), $this->medan(3, 'status', 'teks')]);
        $beza = [
            1 => ['dakwat' => 0.05, 'piksel' => 900, 'kosong' => false, 'teks' => 'ALI BIN ABU', 'conf' => 85.0],
            2 => ['dakwat' => 0.0, 'piksel' => 0, 'kosong' => true, 'teks' => null, 'conf' => null],
            3 => ['dakwat' => 0.04, 'piksel' => 800, 'kosong' => false, 'teks' => 'Lu|us', 'conf' => 30.0],
        ];

        $hasil = app(OllamaExtractionService::class)->ekstrak('Nama: ALI  Faks: 09-7481234  Status: Lulus', $medan, false, $beza);

        $this->assertSame('ALI', $hasil[1]);
        $this->assertNull($hasil[2], 'kotak faks tiada dakwat pengisi');
        $this->assertSame('Lulus', $hasil[3]);
        Http::assertSent(fn ($r) => str_contains($r['prompt'], 'Bacaan semula tulisan pengisi')
            && str_contains($r['prompt'], 'kod "nama": "ALI BIN ABU"')
            && ! str_contains($r['prompt'], 'Lu|us')); // keyakinan rendah tidak diberi
    }

    public function test_prompt_tidak_berubah_tanpa_data_beza(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => '{"nama":null}'])]);
        $medan = collect([$this->medan(1, 'nama')]);

        app(OllamaExtractionService::class)->ekstrak('Nama: x', $medan);
        app(OllamaExtractionService::class)->ekstrak('Nama: x', $medan, false, []);

        $prompt = Http::recorded()->map(fn ($p) => $p[0]['prompt'])->all();
        $this->assertSame($prompt[0], $prompt[1]);
        $this->assertStringNotContainsString('Bacaan semula', $prompt[0]);
    }

    public function test_akhiran_bercetak_melekat_pada_isian_dibuang(): void
    {
        $teks = "Username: «S Y A Z M I» @pkink.gov.my\nPilihan kedua: «Sg SYAMSUL» @pkink.gov.my\nAlamat: «Jalan 3/4»";

        $this->assertSame('SYAZMI', OllamaExtractionService::buangAkhiranBercetak('SYAZMI@pkink.gov.my', $teks));
        $this->assertSame('Sg SYAMSUL', OllamaExtractionService::buangAkhiranBercetak('Sg SYAMSUL@pkink.gov.my', $teks));
        $this->assertSame('Jalan 3/4', OllamaExtractionService::buangAkhiranBercetak('Jalan 3/4', $teks), 'akhiran ialah isian');
        $this->assertSame('ali@gmail.com', OllamaExtractionService::buangAkhiranBercetak('ali@gmail.com', $teks), 'akhiran tiada dalam cetakan');
        $this->assertSame('SYAZMI@pkink.gov.my', OllamaExtractionService::buangAkhiranBercetak('SYAZMI@pkink.gov.my', 'Username: SYAZMI @pkink.gov.my'), 'tanpa templat (tiada «»)');
    }

    public function test_akhiran_bercetak_dibuang_dalam_ekstrak_bagi_medan_teks_sahaja(): void
    {
        Http::fake(['*/api/generate' => Http::response(['response' => json_encode(['user' => 'SYAZMI@pkink.gov.my', 'emel' => 'syazmi@pkink.gov.my'])])]);
        $medan = collect([$this->medan(1, 'user'), $this->medan(2, 'emel', 'emel')]);

        $hasil = app(OllamaExtractionService::class)->ekstrak("Username: «S Y A Z M I» @pkink.gov.my\nEmel: «syazmi» @pkink.gov.my", $medan);

        $this->assertSame('SYAZMI', $hasil[1]);
        $this->assertSame('syazmi@pkink.gov.my', $hasil[2], 'medan emel kekal alamat penuh');
    }
}
