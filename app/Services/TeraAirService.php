<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Tera air pada salinan muat turun fail imbasan (P11): teks pepenjuru berulang
 * "SALINAN TERKAWAL PKINK" + jalur bawah (nama pemuat turun, tarikh/masa, no. rujukan).
 * Fail asal dalam storan TIDAK diubah, jadi hash SHA-256 integriti kekal sah.
 *
 * - Imej (JPG/PNG): dilukis terus dengan GD.
 * - PDF: setiap muka surat dirasterkan dengan pdftoppm (150dpi), ditera dengan GD,
 *   kemudian disusun semula sebagai PDF baharu dengan FPDF. FPDI (import PDF asli)
 *   tidak digunakan kerana versi percumanya menolak PDF 1.5+ ber-xref stream, iaitu
 *   kebanyakan PDF hasil pengimbas. Teks dalam PDF salinan tidak boleh dipilih lagi;
 *   itu disengajakan (salinan terkawal).
 *
 * Fon TrueType (TERA_AIR_FON) digunakan jika ada; jika tiada, fon bitmap GD dibesarkan.
 */
class TeraAirService
{
    private const DPI = 150;

    /** Had muka surat PDF; PDF lebih panjang ditolak supaya permintaan tidak terlalu lama. */
    private const MAKS_MUKA_SURAT = 60;

    /**
     * Hasilkan kandungan salinan bertera air.
     *
     * @return array{kandungan: string, mime: string, sambungan: string}
     *
     * @throws \RuntimeException jika fail tidak disokong atau pdftoppm gagal
     */
    public function jana(string $laluanPenuh, ?string $mime, string $teksUtama, string $teksBawah): array
    {
        $mime = $mime ?: (mime_content_type($laluanPenuh) ?: '');

        if ($mime === 'application/pdf') {
            return ['kandungan' => $this->pdf($laluanPenuh, $teksUtama, $teksBawah), 'mime' => 'application/pdf', 'sambungan' => 'pdf'];
        }

        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $imej = $this->bukaImej($laluanPenuh);
            $this->tera($imej, $teksUtama, $teksBawah);

            ob_start();
            $mime === 'image/png' ? imagepng($imej, null, 6) : imagejpeg($imej, null, 90);
            imagedestroy($imej);

            return ['kandungan' => (string) ob_get_clean(), 'mime' => $mime, 'sambungan' => $mime === 'image/png' ? 'png' : 'jpg'];
        }

        throw new \RuntimeException("Jenis fail tidak disokong untuk tera air: {$mime}");
    }

    private function pdf(string $laluanPdf, string $teksUtama, string $teksBawah): string
    {
        $folder = storage_path('app/private/tera_air_sementara/'.Str::uuid());
        mkdir($folder, 0775, true);

        try {
            $proses = new Process([config('services.ocr.pdftoppm_bin', 'pdftoppm'), '-png', '-r', (string) self::DPI,
                '-l', (string) self::MAKS_MUKA_SURAT, $laluanPdf, $folder.DIRECTORY_SEPARATOR.'m']);
            $proses->setTimeout((int) config('services.ocr.had_masa', 300));
            $proses->run();
            if (! $proses->isSuccessful()) {
                throw new \RuntimeException('pdftoppm gagal: '.$proses->getErrorOutput());
            }

            $mukaSurat = glob($folder.DIRECTORY_SEPARATOR.'m*.png') ?: [];
            natsort($mukaSurat);
            if ($mukaSurat === []) {
                throw new \RuntimeException('pdftoppm tidak menghasilkan sebarang muka surat.');
            }

            $pdf = new \FPDF('P', 'mm', 'A4');
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0);
            $pdf->SetCreator('Sistem Arkib Digital PKINK');
            $pdf->SetTitle('Salinan terkawal');

            foreach ($mukaSurat as $i => $png) {
                $imej = $this->bukaImej($png);
                $this->tera($imej, $teksUtama, $teksBawah);
                $jpg = $folder.DIRECTORY_SEPARATOR."t{$i}.jpg";
                imagejpeg($imej, $jpg, 85);

                $lebar = imagesx($imej) / self::DPI * 25.4;
                $tinggi = imagesy($imej) / self::DPI * 25.4;
                imagedestroy($imej);

                $pdf->AddPage($lebar > $tinggi ? 'L' : 'P', [$lebar, $tinggi]);
                $pdf->Image($jpg, 0, 0, $lebar, $tinggi, 'JPG');
            }

            return $pdf->Output('S');
        } finally {
            foreach (glob($folder.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($folder);
        }
    }

    private function bukaImej(string $laluan): \GdImage
    {
        $imej = @imagecreatefromstring((string) file_get_contents($laluan));
        if ($imej === false) {
            throw new \RuntimeException('Imej tidak dapat dibaca.');
        }
        imagepalettetotruecolor($imej);
        imagealphablending($imej, true);

        return $imej;
    }

    /** Lukis teks pepenjuru berulang + jalur maklumat di bawah. */
    private function tera(\GdImage $imej, string $teksUtama, string $teksBawah): void
    {
        $w = imagesx($imej);
        $h = imagesy($imej);
        $fon = $this->fon();
        $warna = imagecolorallocatealpha($imej, 200, 30, 30, 95); // merah, ~25% legap

        $saiz = max(10, (int) round($w / 26));
        $jarakBaris = $saiz * 7;
        for ($y = -$h; $y < $h * 2; $y += $jarakBaris) {
            for ($x = -$w; $x < $w; $x += (int) ($saiz * strlen($teksUtama) * 0.9)) {
                $this->teks($imej, $fon, $saiz, 30, $x, $y, $warna, $teksUtama);
            }
        }

        $saizBawah = max(8, (int) round($w / 85));
        $tinggiJalur = (int) ($saizBawah * 2.6);
        imagefilledrectangle($imej, 0, $h - $tinggiJalur, $w, $h, imagecolorallocatealpha($imej, 255, 255, 255, 20));
        $this->teks($imej, $fon, $saizBawah, 0, (int) ($saizBawah * 1.2), $h - (int) ($tinggiJalur * 0.32),
            imagecolorallocate($imej, 120, 20, 20), $teksBawah);
    }

    private function teks(\GdImage $imej, ?string $fon, int $saiz, int $sudut, int $x, int $y, int $warna, string $teks): void
    {
        if ($fon !== null) {
            imagettftext($imej, $saiz, $sudut, $x, $y, $warna, $fon, $teks);

            return;
        }

        // Tiada fon TrueType: lukis fon bitmap GD (9x15px) pada kanvas kecil, besarkan & putar
        $kecil = imagecreatetruecolor(max(1, strlen($teks) * 9), 15);
        imagealphablending($kecil, false);
        imagefill($kecil, 0, 0, imagecolorallocatealpha($kecil, 0, 0, 0, 127));
        $rgba = imagecolorsforindex($imej, $warna);
        imagestring($kecil, 5, 0, 0, $teks, imagecolorallocatealpha($kecil, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']));
        $skala = $saiz / 11;
        // Jiran terdekat: interpolasi lain mencampur alfa latar lut sinar dan meninggalkan kotak kelabu
        $besar = imagescale($kecil, max(1, (int) (imagesx($kecil) * $skala)), max(1, (int) (15 * $skala)), IMG_NEAREST_NEIGHBOUR);
        imagedestroy($kecil);
        if ($sudut !== 0) {
            imagesetinterpolation($besar, IMG_NEAREST_NEIGHBOUR);
            $diputar = imagerotate($besar, $sudut, imagecolorallocatealpha($besar, 0, 0, 0, 127));
            imagedestroy($besar);
            $besar = $diputar;
        }
        imagecopy($imej, $besar, $x, $y - imagesy($besar), 0, 0, imagesx($besar), imagesy($besar));
        imagedestroy($besar);
    }

    private function fon(): ?string
    {
        foreach ([config('services.tera_air.fon'), 'C:\\Windows\\Fonts\\arialbd.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'] as $calon) {
            if ($calon && function_exists('imagettftext') && is_file($calon)) {
                return $calon;
            }
        }

        return null;
    }
}
