<?php

namespace App\Services;

use App\Models\Borang;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

/**
 * Slip kelulusan PDF (P6) dengan kod QR yang membuka halaman pengesahan awam
 * (slip.sahkan). Dijana dengan FPDF; fon teras FPDF hanya Latin-1, jadi teks
 * ditukar ke Windows-1252 (aksara lain diganti hampiran).
 *
 * Imej (logo, QR) dihantar ke FPDF sebagai PNG tanpa saluran alfa kerana FPDF
 * tidak menyokong PNG beralfa.
 */
class SlipKelulusanService
{
    public function jana(Borang $borang, string $urlSahkan, string $dijanaOleh): string
    {
        $dokumen = $borang->dokumen;
        $sementara = [];

        try {
            $pdf = new \FPDF('P', 'mm', 'A4');
            $pdf->SetMargins(20, 18, 20);
            $pdf->SetAutoPageBreak(true, 20);
            $pdf->SetCreator('Sistem Arkib Digital PKINK');
            $pdf->SetTitle($this->t('Slip Kelulusan '.$dokumen->no_rujukan));
            $pdf->AddPage();

            // Kepala: logo + nama perbadanan. Logo_PKINK_Slip.png = salinan 600px tanpa alfa
            // (logo asal 2954px memerlukan ~30MB memori GD bagi setiap slip)
            $logo = public_path('images/Logo_PKINK_Slip.png');
            if (is_file($logo)) {
                $pdf->Image($logo, 20, 14, 0, 24, 'PNG');
            }
            $pdf->SetXY(50, 17);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetTextColor(24, 63, 104);
            $pdf->Cell(0, 6, $this->t('PERBADANAN KEMAJUAN IKTISAD NEGERI KELANTAN'), 0, 2);
            $pdf->SetFont('Helvetica', '', 9.5);
            $pdf->SetTextColor(104, 121, 141);
            $pdf->Cell(0, 5, $this->t('Sistem Arkib Digital (DMS) PKINK'), 0, 2);

            $pdf->SetDrawColor(220, 229, 239);
            $pdf->Line(20, 42, 190, 42);

            $pdf->SetXY(20, 50);
            $pdf->SetFont('Helvetica', 'B', 18);
            $pdf->SetTextColor(24, 63, 104);
            $pdf->Cell(0, 9, 'SLIP KELULUSAN', 0, 1);
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->SetTextColor(37, 140, 101);
            $pdf->Cell(0, 6, $this->t('Permohonan ini telah DILULUSKAN melalui sistem.'), 0, 1);
            $pdf->Ln(4);

            $baris = [
                ['No. Rujukan', $dokumen->no_rujukan],
                ['Kategori', $dokumen->jenisDokumen->nama_dokumen ?? '-'],
                ['Perkara', $dokumen->perkara ?: '-'],
                ['Bahagian', $dokumen->bahagian->nama_bahagian ?? '-'],
                ['Pemohon', $borang->pemohon->nama_staff ?? '-'],
                ['Tarikh Permohonan', $this->tarikh($borang->tarikh_permohonan)],
            ];
            if ($borang->penyokong) {
                $baris[] = ['Disokong Oleh', $borang->penyokong->nama_staff.' ('.$this->tarikh($borang->tarikh_sokongan).')'];
            }
            $baris[] = ['Diluluskan Oleh', $borang->pelulus->nama_staff ?? 'Diarkibkan terus (Imbas & Arkib)'];
            $baris[] = ['Tarikh Kelulusan', $this->tarikh($borang->tarikh_kelulusan)];
            if ($borang->ulasan_pelulus) {
                $baris[] = ['Ulasan Pelulus', $borang->ulasan_pelulus];
            }

            foreach ($baris as [$label, $nilai]) {
                $y = $pdf->GetY();
                $pdf->SetFont('Helvetica', '', 10);
                $pdf->SetTextColor(104, 121, 141);
                $pdf->Cell(45, 7, $this->t($label));
                $pdf->SetFont('Helvetica', 'B', 10);
                $pdf->SetTextColor(28, 48, 72);
                $pdf->MultiCell(125, 7, $this->t((string) $nilai));
                $pdf->Line(20, max($pdf->GetY(), $y + 7), 190, max($pdf->GetY(), $y + 7));
            }

            // Kod QR pengesahan
            $qr = $this->pngTanpaAlfa((new Writer(new GDLibRenderer(400, 2)))->writeString($urlSahkan));
            $sementara[] = $qr;
            if ($pdf->GetY() > 210) {
                $pdf->AddPage();
            }
            $yQr = $pdf->GetY() + 12;
            $pdf->Image($qr, 20, $yQr, 38, 38, 'PNG');
            $pdf->SetXY(64, $yQr + 4);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(24, 63, 104);
            $pdf->Cell(0, 6, $this->t('Pengesahan slip'), 0, 2);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(28, 48, 72);
            $pdf->MultiCell(126, 5, $this->t('Imbas kod QR ini untuk menyemak status semasa permohonan dalam sistem. Slip ini tidak sah jika halaman pengesahan menyatakan sebaliknya.'));
            $pdf->SetX(64);
            $pdf->SetFont('Courier', 'B', 11);
            $pdf->Cell(0, 7, $this->t('Kod: '.$borang->kodPengesahan()), 0, 2);

            $pdf->SetY(-24);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(104, 121, 141);
            $pdf->MultiCell(0, 4, $this->t('Slip ini dijana oleh komputer dan tidak memerlukan tandatangan. Dijana oleh '
                .$dijanaOleh.' pada '.now()->format('d/m/Y H:i').'.'), 0, 'C');

            return $pdf->Output('S');
        } finally {
            foreach ($sementara as $f) {
                @unlink($f);
            }
        }
    }

    /** Teks UTF-8 → Windows-1252 untuk fon teras FPDF. */
    private function t(string $teks): string
    {
        return (string) (@iconv('UTF-8', 'windows-1252//TRANSLIT', $teks) ?: $teks);
    }

    private function tarikh($tarikh): string
    {
        return $tarikh ? $tarikh->format('d/m/Y, h:i A') : '-';
    }

    /** Ratakan PNG (kod QR) atas latar putih tanpa alfa dan simpan ke fail sementara. */
    private function pngTanpaAlfa(string $data): ?string
    {
        $asal = @imagecreatefromstring($data);
        if ($asal === false) {
            return null;
        }
        $w = imagesx($asal);
        $h = imagesy($asal);
        $rata = imagecreatetruecolor($w, $h);
        imagefill($rata, 0, 0, imagecolorallocate($rata, 255, 255, 255));
        imagecopy($rata, $asal, 0, 0, 0, 0, $w, $h);

        $laluan = sys_get_temp_dir().DIRECTORY_SEPARATOR.'slip_'.Str::random(20).'.png';
        imagepng($rata, $laluan);
        imagedestroy($asal);
        imagedestroy($rata);

        return $laluan;
    }
}
