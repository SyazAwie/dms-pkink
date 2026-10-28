<?php

namespace App\Services;

use Symfony\Component\Process\Process;

/**
 * Pemeriksaan keselamatan fail muat naik (Fasa Keselamatan S1), sebelum fail disimpan:
 * 1. "Bom": imej melebihi had piksel, PDF melebihi had muka surat (fail kecil yang
 *    mengembang menjadi gigabait semasa OCR/pratonton).
 * 2. PDF aktif: /Launch (melancarkan program) dan fail terbenam DITOLAK; JavaScript
 *    dibenarkan (borang PDF boleh-isi) kerana pratonton dihidang dalam CSP sandbox.
 *    Nama /Launch dalam aliran objek termampat tidak kelihatan dalam bait mentah,
 *    jadi semakan itu usaha terbaik; antivirus ialah lapisan seterusnya.
 * 3. Antivirus: Windows Defender (MpCmdRun) atau ClamAV (clamscan/clamdscan).
 *    Jika pengimbas tiada/gagal: dibenarkan + amaran, atau ditolak bila ANTIVIRUS_WAJIB.
 *
 * Fail tidak pernah dijalankan oleh pelayan; ini melindungi pelayan (cakera, CPU,
 * pembaca PDF) dan pengguna lain yang memuat turun fail tersebut.
 */
class PemeriksaFail
{
    public const BERSIH = 'bersih';

    public const DITOLAK = 'ditolak';

    public const BERBAHAYA = 'berbahaya';

    /**
     * @return array{status: string, mesej: string|null, amaran: string|null}
     *                                                                        status bersih (mungkin dengan amaran), ditolak (had/format), berbahaya (virus/PDF aktif)
     */
    public function periksa(string $laluan, string $sambungan): array
    {
        $sambungan = strtolower($sambungan);

        $ralat = $sambungan === 'pdf' ? $this->periksaPdf($laluan) : $this->periksaImej($laluan);
        if ($ralat !== null) {
            return $ralat;
        }

        return $this->imbasAntivirus($laluan);
    }

    /** Status pengimbas untuk Status Sistem / Pusat Keselamatan. */
    public function statusAntivirus(): array
    {
        $bin = $this->binAntivirus();
        if ($bin === null) {
            return ['aktif' => false, 'nama' => null, 'nota' => 'Tiada pengimbas antivirus dikesan (Windows Defender / ClamAV).'];
        }

        return ['aktif' => true, 'nama' => $this->jenis() === 'clamav' ? 'ClamAV' : 'Windows Defender', 'nota' => $bin];
    }

    private function periksaImej(string $laluan): ?array
    {
        $saiz = @getimagesize($laluan);
        if ($saiz === false) {
            return $this->tolak('Fail imej rosak atau bukan imej sebenar.');
        }

        $maks = (int) config('services.keselamatan.maks_piksel', 40_000_000);
        if ($maks < $saiz[0] * $saiz[1]) {
            return $this->tolak(sprintf('Imej terlalu besar (%s × %s piksel). Had: %s juta piksel; imbas semula pada resolusi lebih rendah (cth. 300dpi).',
                number_format($saiz[0]), number_format($saiz[1]), number_format($maks / 1_000_000)));
        }

        return null;
    }

    private function periksaPdf(string $laluan): ?array
    {
        $kandungan = (string) file_get_contents($laluan);
        if (! str_starts_with(ltrim(substr($kandungan, 0, 1024)), '%PDF-')) {
            return $this->tolak('Fail ini bukan PDF yang sah.');
        }

        // Tindakan berbahaya dalam bait mentah (nama PDF boleh dikod #xx, cth. /L#61unch)
        $nama = preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn ($m) => chr(hexdec($m[1])), $kandungan);
        if (preg_match('#/Launch\b#', $nama)) {
            return $this->berbahaya('PDF ini mengandungi arahan untuk melancarkan program (/Launch) dan ditolak atas sebab keselamatan.');
        }
        if (preg_match('#/EmbeddedFiles?\b#', $nama)) {
            return $this->berbahaya('PDF ini mengandungi fail terbenam dan ditolak atas sebab keselamatan.');
        }

        // Bilangan muka surat (pdfinfo, Poppler): PDF kecil beratus muka surat = beban OCR/cakera
        $maks = (int) config('services.keselamatan.maks_muka_surat', 50);
        $proses = new Process([$this->binPoppler('pdfinfo'), $laluan]);
        $proses->setTimeout(30);
        try {
            $proses->run();
        } catch (\Throwable) {
            return null; // pdfinfo tiada: had muka surat masih dikuatkuasakan semasa OCR (-l)
        }
        if (! $proses->isSuccessful()) {
            return str_contains(strtolower($proses->getErrorOutput()), 'not recognized') || $proses->getExitCode() === 127
                ? null
                : $this->tolak('PDF rosak atau tidak dapat dibaca. Simpan semula sebagai PDF dan cuba lagi.');
        }
        if (preg_match('/^Pages:\s+(\d+)/m', $proses->getOutput(), $m) && (int) $m[1] > $maks) {
            return $this->tolak("PDF ini ada {$m[1]} muka surat. Had: {$maks} muka surat; pecahkan kepada beberapa fail.");
        }

        return null;
    }

    private function imbasAntivirus(string $laluan): array
    {
        $bin = $this->binAntivirus();
        if ($bin === null) {
            return $this->tanpaImbasan('Tiada pengimbas antivirus dikesan.');
        }

        $arahan = $this->jenis() === 'clamav'
            ? [$bin, '--no-summary', $laluan]
            : [$bin, '-Scan', '-ScanType', '3', '-File', $laluan, '-DisableRemediation'];
        $proses = new Process($arahan);
        $proses->setTimeout((int) config('services.keselamatan.antivirus_had_masa', 60));

        try {
            $proses->run();
        } catch (\Throwable $e) {
            return $this->tanpaImbasan('Pengimbas antivirus gagal dijalankan: '.$e->getMessage());
        }

        // Defender: 0 bersih, 2 ancaman ditemui. ClamAV: 0 bersih, 1 dijangkiti, 2 ralat.
        $kod = $proses->getExitCode();
        $dijangkiti = $this->jenis() === 'clamav' ? $kod === 1 : $kod === 2;
        if ($dijangkiti) {
            return $this->berbahaya('Fail ini dikesan mengandungi virus atau perisian hasad oleh antivirus dan telah ditolak.');
        }
        if ($kod !== 0) {
            return $this->tanpaImbasan("Pengimbas antivirus memulangkan kod {$kod}.");
        }

        return ['status' => self::BERSIH, 'mesej' => null, 'amaran' => null];
    }

    private function tanpaImbasan(string $sebab): array
    {
        if (config('services.keselamatan.antivirus_wajib')) {
            return $this->tolak('Fail tidak dapat diimbas untuk virus buat masa ini. Sila cuba lagi atau hubungi Pentadbir Sistem.');
        }

        return ['status' => self::BERSIH, 'mesej' => null, 'amaran' => $sebab];
    }

    public function binAntivirus(): ?string
    {
        $calon = config('services.keselamatan.antivirus_bin')
            ?: (PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\Windows Defender\\MpCmdRun.exe' : '/usr/bin/clamdscan');

        return is_file($calon) ? $calon : null;
    }

    private function jenis(): string
    {
        return str_contains(strtolower((string) $this->binAntivirus()), 'clam') ? 'clamav' : 'defender';
    }

    /** pdfinfo/pdfdetach terletak dalam folder yang sama dengan pdftoppm (Poppler). */
    private function binPoppler(string $nama): string
    {
        $pdftoppm = (string) config('services.ocr.pdftoppm_bin', 'pdftoppm');

        return str_ireplace('pdftoppm', $nama, $pdftoppm);
    }

    private function tolak(string $mesej): array
    {
        return ['status' => self::DITOLAK, 'mesej' => $mesej, 'amaran' => null];
    }

    private function berbahaya(string $mesej): array
    {
        return ['status' => self::BERBAHAYA, 'mesej' => $mesej, 'amaran' => null];
    }
}
