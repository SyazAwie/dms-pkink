<?php

namespace Tests\Feature;

use App\Support\TandaVisual;
use Tests\TestCase;

/**
 * T6: tanda visual borang (perkataan dipotong, kotak pilihan ✓) ditulis sebagai penanda dalam
 * teks OCR. Imej sintetik GD: teks fon terbina dalam dibesarkan 4x (kira-kira saiz 300dpi).
 */
class TandaVisualTest extends TestCase
{
    private const SKALA = 4;

    /** @var list<string> */
    private array $fail = [];

    protected function tearDown(): void
    {
        foreach ($this->fail as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    /**
     * Lukis pada kanvas kecil (koordinat /4) kemudian besarkan 4x tanpa licin.
     *
     * @param  callable(\GdImage, int, int): void  $lukis  (imej, hitam, putih)
     */
    private function imej(callable $lukis, int $lebar = 300, int $tinggi = 225): string
    {
        $im = imagecreatetruecolor($lebar, $tinggi);
        $putih = imagecolorallocate($im, 255, 255, 255);
        $hitam = imagecolorallocate($im, 0, 0, 0);
        imagefill($im, 0, 0, $putih);
        $lukis($im, $hitam, $putih);
        $besar = imagescale($im, $lebar * self::SKALA, $tinggi * self::SKALA, IMG_NEAREST_NEIGHBOUR);
        $laluan = tempnam(sys_get_temp_dir(), 'tv').'.png';
        imagepng($besar, $laluan);
        $this->fail[] = $laluan;

        return $laluan;
    }

    /** Perkataan teks fon 5 GD (9x15 px setiap aksara) pada koordinat kanvas kecil. */
    private function kata(string $teks, int $x, int $y, string $baris = '1.1.1', float $conf = 95.0): array
    {
        $s = self::SKALA;

        return ['teks' => $teks, 'x' => $x * $s, 'y' => $y * $s, 'w' => strlen($teks) * 9 * $s, 'h' => 15 * $s, 'conf' => $conf, 'baris' => $baris];
    }

    private function teks(array $kata): string
    {
        return implode(' ', array_column($kata, 'teks'));
    }

    public function test_perkataan_dipotong_ditanda_yang_tidak_dipotong_kekal(): void
    {
        $imej = $this->imej(function ($im, $hitam) {
            imagestring($im, 5, 20, 20, 'disokong/', $hitam);
            imagestring($im, 5, 120, 20, 'tidak', $hitam);
            imagestring($im, 5, 175, 20, 'sokong', $hitam);
            // Garisan potongan merentasi "tidak sokong" di tengah tinggi huruf kecil
            imagefilledrectangle($im, 118, 28, 232, 28, $hitam);
        });

        $hasil = TandaVisual::anotasi($imej, [
            $this->kata('disokong/', 20, 20), $this->kata('tidak', 120, 20), $this->kata('sokong', 175, 20),
        ]);

        $this->assertSame('disokong/ ~~tidak sokong~~', $this->teks($hasil));
    }

    public function test_teks_putih_atas_latar_gelap_bukan_potongan(): void
    {
        // Sepanduk gelap: huruf putih kecil di separuh bawah bbox, jadi jalur tengah mengandungi
        // baris yang gelap sepenuhnya (seperti kepala surat sebenar: liputan 1.00 tanpa penapis)
        $imej = $this->imej(function ($im, $hitam, $putih) {
            imagefilledrectangle($im, 0, 0, 299, 60, $hitam);
            imagestring($im, 1, 22, 28, 'TEKNOLOGI', $putih);
            imagestring($im, 5, 20, 100, 'Biasa', $hitam);
        });

        $hasil = TandaVisual::anotasi($imej, [$this->kata('TEKNOLOGI', 20, 20), $this->kata('Biasa', 20, 100, '1.1.2')]);

        $this->assertSame('TEKNOLOGI Biasa', $this->teks($hasil));
    }

    public function test_kotak_bertanda_dan_kosong_di_sebelah_label(): void
    {
        $imej = $this->imej(function ($im, $hitam) {
            // Kotak bertanda (tanda ✓) sebelum "Emel"; tanda keluar sedikit dari kotak
            imagerectangle($im, 20, 20, 35, 35, $hitam);
            imageline($im, 23, 27, 27, 33, $hitam);
            imageline($im, 27, 33, 38, 15, $hitam);
            imagestring($im, 5, 45, 21, 'Emel', $hitam);
            // Kotak kosong sebelum "Intranet"
            imagerectangle($im, 20, 60, 35, 75, $hitam);
            imagestring($im, 5, 45, 61, 'Intranet', $hitam);
            // Label diikuti kotak kosong
            imagestring($im, 5, 20, 101, 'Lain-lain:', $hitam);
            imagerectangle($im, 140, 100, 155, 115, $hitam);
        });

        $hasil = TandaVisual::anotasi($imej, [
            $this->kata('JS', 22, 21, '1.1.1', 79.0), // tanda ✓ dibaca sebagai huruf
            $this->kata('Emel', 45, 21, '1.1.1'),
            $this->kata('Intranet', 45, 61, '1.1.2'),
            $this->kata('Lain-lain:', 20, 101, '1.1.3'),
        ]);

        $this->assertSame('[✓] Emel [□] Intranet Lain-lain: [□]', $this->teks($hasil));
    }

    public function test_kotak_huruf_berderet_dan_sel_bernombor_bukan_kotak_pilihan(): void
    {
        $imej = $this->imej(function ($im, $hitam) {
            // Kotak huruf nama (berderet, berkongsi sisi)
            foreach ([0, 1, 2] as $i) {
                imagerectangle($im, 20 + $i * 16, 20, 36 + $i * 16, 36, $hitam);
            }
            imagestring($im, 5, 24, 21, 'A', $hitam);
            imagestring($im, 5, 90, 21, 'Nama', $hitam);
            // Sel lajur "Bil" berisi nombor
            imagerectangle($im, 20, 70, 36, 86, $hitam);
            imagestring($im, 5, 24, 71, '1', $hitam);
            imagestring($im, 5, 45, 71, 'Ahmad', $hitam);
        });

        $hasil = TandaVisual::anotasi($imej, [
            $this->kata('A', 24, 21, '1.1.1', 96.0), $this->kata('Nama', 90, 21, '1.1.1'),
            $this->kata('1', 24, 71, '1.1.2', 96.0), $this->kata('Ahmad', 45, 71, '1.1.2'),
        ]);

        $this->assertSame('A Nama 1 Ahmad', $this->teks($hasil));
    }

    public function test_imej_terlalu_besar_dilangkau_tanpa_dimuat(): void
    {
        // Pengepala PNG 6000x6000 (36 juta piksel > had) tanpa data piksel: getimagesize sahaja
        // membacanya; jika GD cuba memuatkannya, imej rosak memulangkan kata asal juga, jadi
        // semak juga cariKotakFail tidak memuatkan apa-apa.
        $ihdr = 'IHDR'.pack('NNCCCCC', 6000, 6000, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));
        $laluan = tempnam(sys_get_temp_dir(), 'tv').'.png';
        file_put_contents($laluan, $png);
        $this->fail[] = $laluan;
        $kata = [$this->kata('Emel', 10, 10)];

        $this->assertSame([6000, 6000], array_slice(getimagesize($laluan), 0, 2));
        $this->assertSame($kata, TandaVisual::anotasi($laluan, $kata));
        $this->assertSame([], TandaVisual::cariKotakFail($laluan));
    }

    public function test_imej_rosak_pulangkan_kata_asal(): void
    {
        $laluan = tempnam(sys_get_temp_dir(), 'tv');
        file_put_contents($laluan, 'bukan imej');
        $this->fail[] = $laluan;
        $kata = [$this->kata('Emel', 10, 10)];

        $this->assertSame($kata, TandaVisual::anotasi($laluan, $kata));
    }
}
