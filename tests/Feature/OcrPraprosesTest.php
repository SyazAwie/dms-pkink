<?php

namespace Tests\Feature;

use App\Services\OcrService;
use App\Support\BuangGarisan;
use Tests\TestCase;

/**
 * OCR Pantas & Tepat P3: buang garisan kotak/jadual sebelum OCR, dan teks OCR
 * mengekalkan baris asal (untuk AI).
 */
class OcrPraprosesTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = sys_get_temp_dir() . '/dms_ujian_ocr_' . uniqid();
        mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->folder . '/*') ?: []);
        @rmdir($this->folder);
        parent::tearDown();
    }

    private function gelap($im, int $x, int $y): bool
    {
        return (imagecolorat($im, $x, $y) & 0xFF) < 140;
    }

    public function test_garisan_nipis_dibuang_blok_tebal_dan_huruf_kekal(): void
    {
        // Imej "A4 300dpi" kecil: lebar 2480 supaya ambang tidak diskala
        $im = imagecreatetruecolor(2480, 600);
        $putih = imagecolorallocate($im, 255, 255, 255);
        $hitam = imagecolorallocate($im, 0, 0, 0);
        imagefill($im, 0, 0, $putih);
        imagesetthickness($im, 3);
        imagerectangle($im, 100, 100, 700, 180, $hitam);          // kotak huruf (garisan nipis)
        imageline($im, 400, 100, 400, 180, $hitam);                // sisi kotak dalam
        imagefilledrectangle($im, 1000, 50, 2400, 250, $hitam);   // kepala surat gelap (blok tebal)
        imagefilledrectangle($im, 200, 120, 215, 160, $hitam);    // "huruf" di dalam kotak
        $masuk = "{$this->folder}/masuk.png";
        $keluar = "{$this->folder}/keluar.png";
        imagepng($im, $masuk);

        $this->assertTrue(BuangGarisan::proses($masuk, $keluar));
        $hasil = imagecreatefrompng($keluar);

        $this->assertFalse($this->gelap($hasil, 300, 100), 'garisan atas kotak dibuang');
        $this->assertFalse($this->gelap($hasil, 100, 140), 'sisi kiri kotak dibuang');
        $this->assertFalse($this->gelap($hasil, 400, 140), 'sisi dalam kotak dibuang');
        $this->assertTrue($this->gelap($hasil, 1700, 150), 'blok tebal kekal');
        $this->assertTrue($this->gelap($hasil, 207, 140), 'huruf kekal');
    }

    public function test_fail_bukan_imej_dilangkau(): void
    {
        file_put_contents("{$this->folder}/bukan.png", 'bukan imej');

        $this->assertFalse(BuangGarisan::proses("{$this->folder}/bukan.png", "{$this->folder}/keluar.png"));
    }

    public function test_had_memori_dipulihkan(): void
    {
        $asal = ini_get('memory_limit');
        $im = imagecreatetruecolor(800, 300);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagepng($im, "{$this->folder}/kecil.png");

        BuangGarisan::proses("{$this->folder}/kecil.png", "{$this->folder}/keluar.png");

        $this->assertSame($asal, ini_get('memory_limit'));
    }

    public function test_teks_ocr_kekal_ikut_baris(): void
    {
        $tsv = "{$this->folder}/ujian.tsv";
        file_put_contents($tsv, implode("\n", [
            "level\tpage_num\tblock_num\tpar_num\tline_num\tword_num\tleft\ttop\twidth\theight\tconf\ttext",
            "4\t1\t1\t1\t1\t0\t0\t0\t0\t0\t-1\t",
            "5\t1\t1\t1\t1\t1\t0\t0\t0\t0\t95\tNama",
            "5\t1\t1\t1\t1\t2\t0\t0\t0\t0\t90\tPemohon:",
            "5\t1\t1\t1\t1\t3\t0\t0\t0\t0\t85\tALI",
            "5\t1\t1\t1\t2\t1\t0\t0\t0\t0\t80\tTarikh:",
            "5\t1\t1\t1\t2\t2\t0\t0\t0\t0\t70\t12/8/2026",
            "5\t1\t2\t1\t1\t1\t0\t0\t0\t0\t60\tPerakuan",
        ]));

        [$teks, $skor] = (fn () => $this->uraiTsv($tsv))->call(new OcrService());

        $this->assertSame("Nama Pemohon: ALI\nTarikh: 12/8/2026\nPerakuan", $teks);
        $this->assertSame(80.0, $skor);
    }
}
