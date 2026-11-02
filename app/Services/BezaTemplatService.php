<?php

namespace App\Services;

use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\TemplatBorang;
use App\Support\JenisMedan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * TB4: beza imej templat borang kosong dengan imbasan.
 *
 *  1. Jajar: perkataan bercetak yang sepadan (unik pada kedua-dua muka surat) memberi pasangan
 *     titik; transformasi afin (kuasa dua terkecil, titik terpencil dibuang) memetakan pecahan
 *     halaman templat kepada pecahan halaman imbasan. Imbasan yang beralih, berskala atau
 *     condong sedikit tetap dijajar.
 *  2. Dakwat: bagi setiap medan yang ada kotak kedudukan (TB6), piksel gelap imbasan yang TIDAK
 *     gelap pada templat (dikembangkan beberapa piksel untuk ralat jajaran) = tulisan pengisi.
 *     Garisan kotak/titik isian dan label bercetak hilang.
 *  3. (a) Medan tanpa dakwat = kosong pasti (nilai AI dibuang). (b) Keratan dakwat sahaja dibaca
 *     semula oleh Tesseract (huruf dalam kotak tanpa garisan kotak) sebagai petunjuk kepada AI.
 *
 * Bantuan sahaja: sebarang kegagalan (tiada templat disusun, jajaran lemah) → tiada hasil, dan
 * analisis diteruskan seperti biasa. Dihidupkan dengan OCR_BEZA_TEMPLAT.
 */
class BezaTemplatService
{
    /** Jenis medan satu kotak teks yang dinilai (pilihan & jadual tidak: kotaknya tempat tanda). */
    public const JENIS_DINILAI = ['teks', 'textarea', 'emel', 'telefon', 'nombor', 'wang', 'tarikh', 'tarikh_masa', 'masa', 'url'];

    private const AMBANG_GELAP_IMBASAN = 150;

    private const AMBANG_GELAP_TEMPLAT = 170;

    /** Pengembangan topeng templat (piksel templat 150dpi ≈ 0.17mm setiap piksel). */
    private const KEMBANG = 3;

    private const MIN_TITIK = 6;

    /** Median ralat jajaran maksimum (pecahan lebar/tinggi halaman). */
    private const RALAT_MAKS = 0.006;

    /** Jarak maksimum titik dalaman RANSAC (pecahan halaman, ~2.5mm pada A4). */
    private const TOLERANSI = 0.012;

    /** Had bilangan tiga titik yang dicuba (n titik → n·(n-1)·(n-2)/6 gabungan). */
    private const MAKS_CUBAAN = 3000;

    /** @var array<int, array{templat: TemplatBorang, kata: array, latar: array}> */
    private array $calon = [];

    /** @var array<int|string, array> hasil setiap dokumen_field_id */
    private array $hasil = [];

    private array $diagnostik = [];

    /** Nyahpepijat (ocr:nilai --beza-simpan): simpan keratan dakwat setiap medan ke folder ini. */
    public ?string $folderNyahpepijat = null;

    /** @var Collection<int, DokumenField> medan kategori ikut dokumen_field_id */
    private $medan;

    /**
     * Pengumpul bagi satu analisis: null jika ciri dimatikan atau kategori tiada templat aktif
     * yang sudah disusun (kedudukan_medan disahkan).
     */
    public static function untuk(?JenisDokumen $jenis): ?self
    {
        if (! $jenis || ! config('services.ocr.beza_templat', false)) {
            return null;
        }
        try {
            $templat = TemplatBorang::where('jenis_dokumen_id', $jenis->jenis_dokumen_id)
                ->where('aktif', true)->where('status_ocr', 'Selesai')->orderByDesc('versi')->get()
                ->filter(fn ($t) => ! empty($t->kedudukan_medan['medan']) && ($t->kedudukan_medan['disahkan'] ?? true));
        } catch (\Throwable) {
            return null; // DB tanpa lajur kedudukan_medan
        }
        if ($templat->isEmpty()) {
            return null;
        }
        $servis = new self;
        $servis->medan = $jenis->fields->keyBy('dokumen_field_id');
        foreach ($templat as $t) {
            $servis->calon[$t->templat_borang_id] = ['templat' => $t, 'kata' => null, 'latar' => null];
        }

        return $servis;
    }

    /**
     * Satu muka surat imbasan (dipanggil oleh OcrService semasa imej masih wujud).
     *
     * @param  list<array{teks: string, x: int, y: int, w: int, h: int}>  $kataImbasan  TSV Tesseract
     */
    public function muka(int $indeks, string $imej, array $kataImbasan): void
    {
        try {
            $this->prosesMuka($indeks + 1, $imej, $kataImbasan);
        } catch (\Throwable $e) {
            Log::warning('Beza templat (TB4) gagal pada muka '.($indeks + 1).': '.$e->getMessage());
            $this->diagnostik[] = ['muka' => $indeks + 1, 'ralat' => $e->getMessage()];
        }
    }

    /** @return array<int|string, array{dakwat: float, kosong: bool, teks: ?string, conf: ?float}> */
    public function hasil(): array
    {
        return $this->hasil;
    }

    public function diagnostik(): array
    {
        return $this->diagnostik;
    }

    private function prosesMuka(int $noMuka, string $imej, array $kataImbasan): void
    {
        $saiz = @getimagesize($imej);
        if (! $saiz || ! $kataImbasan) {
            return;
        }
        [$lebar, $tinggi] = $saiz;
        $titikImbasan = array_map(fn ($k) => ['teks' => $k['teks'], 'x' => ($k['x'] + $k['w'] / 2) / $lebar, 'y' => ($k['y'] + $k['h'] / 2) / $tinggi], $kataImbasan);

        // Templat (versi) yang paling baik dijajar dengan muka ini
        $terbaik = null;
        foreach ($this->calon as $id => &$c) {
            $c['kata'] ??= $this->kataTemplat($c['templat']);
            $j = self::jajar($c['kata'][$noMuka] ?? [], $titikImbasan);
            if ($j && (! $terbaik || $j['bil'] > $terbaik[1]['bil'])) {
                $terbaik = [$id, $j];
            }
        }
        unset($c);
        if (! $terbaik) {
            $this->diagnostik[] = ['muka' => $noMuka, 'jajaran' => null];

            return;
        }
        [$id, $jajaran] = $terbaik;
        $templat = $this->calon[$id]['templat'];
        $this->diagnostik[] = ['muka' => $noMuka, 'templat' => $id, 'titik' => $jajaran['bil'], 'ralat' => round($jajaran['ralat'], 5)];

        $kotakMedan = [];
        foreach ($templat->kedudukan_medan['medan'] ?? [] as $fid => $k) {
            $medan = $this->medan->get((int) $fid);
            if ($medan && in_array($medan->jenis_data, self::JENIS_DINILAI, true) && JenisMedan::adaNilai($medan)
                && isset($k['kotak']) && (int) ($k['kotak']['muka'] ?? 1) === $noMuka) {
                $kotakMedan[(int) $fid] = $k['kotak'];
            }
        }
        if (! $kotakMedan) {
            return;
        }

        $latar = app(BorangSiapService::class)->latar($templat)[$noMuka - 1] ?? null;
        if (! $latar) {
            return;
        }
        $imejTemplat = @imagecreatefromjpeg($latar['fail']);
        $imejImbasan = self::muat($imej);
        if (! $imejTemplat || ! $imejImbasan) {
            return;
        }
        $topeng = self::topengTemplat($imejTemplat);
        $tw = imagesx($imejTemplat);
        $th = imagesy($imejTemplat);
        imagedestroy($imejTemplat);

        $folder = storage_path('app/private/ocr_sementara/beza_'.Str::random(12));
        @mkdir($folder, 0775, true);
        try {
            $proses = [];
            foreach ($kotakMedan as $fid => $kotak) {
                $d = self::dakwat($imejImbasan, $lebar, $tinggi, $topeng, $tw, $th, $jajaran, $kotak);
                $this->hasil[$fid] = ['dakwat' => $d['nisbah'], 'piksel' => $d['piksel'], 'kosong' => self::kosong($d), 'teks' => null, 'conf' => null];
                if (! $this->hasil[$fid]['kosong'] && $d['imej']) {
                    $fail = $folder.DIRECTORY_SEPARATOR."m{$fid}.png";
                    imagepng($d['imej'], $fail);
                    $psm = $this->medan[$fid]->jenis_data === 'textarea' ? '6' : '7';
                    $p = new Process([config('services.ocr.tesseract_bin', 'tesseract'), $fail, $folder.DIRECTORY_SEPARATOR."m{$fid}",
                        '-l', config('services.ocr.bahasa', 'eng'), '--psm', $psm, 'tsv']);
                    $p->setTimeout(60);
                    $p->start();
                    $proses[$fid] = $p;
                }
                if ($d['imej'] && $this->folderNyahpepijat) {
                    @mkdir($this->folderNyahpepijat, 0775, true);
                    imagepng($d['imej'], $this->folderNyahpepijat.DIRECTORY_SEPARATOR."muka{$noMuka}_medan{$fid}.png");
                }
                if ($d['imej']) {
                    imagedestroy($d['imej']);
                }
            }
            foreach ($proses as $fid => $p) {
                $p->wait();
                [$teks, $conf] = self::bacaTsv($folder.DIRECTORY_SEPARATOR."m{$fid}.tsv");
                $this->hasil[$fid]['teks'] = $teks !== '' ? $teks : null;
                $this->hasil[$fid]['conf'] = $conf;
            }
        } finally {
            imagedestroy($imejImbasan);
            foreach (glob($folder.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($folder);
        }
    }

    /** Medan kosong: hampir tiada dakwat (sisa jajaran / bintik imbasan sahaja). */
    public static function kosong(array $d): bool
    {
        return $d['piksel'] < (int) config('services.ocr.beza_min_piksel', 60)
            || $d['nisbah'] < (float) config('services.ocr.beza_min_nisbah', 0.003);
    }

    /**
     * Perkataan templat setiap muka surat (pecahan halaman), daripada Tesseract atas imej latar
     * 150dpi (TB6), dicache bersama latar dalam templat_cache/.
     *
     * @return array<int, list<array{teks: string, x: float, y: float}>>
     */
    public function kataTemplat(TemplatBorang $templat): array
    {
        $latar = app(BorangSiapService::class)->latar($templat);
        if (! $latar) {
            return [];
        }
        $cache = dirname($latar[0]['fail']).DIRECTORY_SEPARATOR.'kata.json';
        if (is_file($cache) && is_array($simpan = json_decode((string) file_get_contents($cache), true))) {
            return $simpan;
        }
        $hasil = [];
        foreach ($latar as $i => $m) {
            $saiz = @getimagesize($m['fail']);
            if (! $saiz) {
                continue;
            }
            $hasil[$i + 1] = array_map(fn ($k) => ['teks' => $k['teks'], 'x' => ($k['x'] + $k['w'] / 2) / $saiz[0], 'y' => ($k['y'] + $k['h'] / 2) / $saiz[1]],
                self::tesseractKata($m['fail']));
        }
        @file_put_contents($cache, json_encode($hasil));

        return $hasil;
    }

    /**
     * Afin templat → imbasan daripada perkataan unik yang sepadan. Null jika titik terlalu
     * sedikit atau ralat median terlalu besar (borang lain / imbasan rosak).
     *
     * @return array{x: array{0: float, 1: float, 2: float}, y: array{0: float, 1: float, 2: float}, bil: int, ralat: float}|null
     */
    public static function jajar(array $kataTemplat, array $kataImbasan): ?array
    {
        $unik = function (array $kata): array {
            $kira = [];
            foreach ($kata as $k) {
                $t = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $k['teks']));
                if (mb_strlen($t) >= 3 && ! preg_match('/^\d+$/', $t)) {
                    $kira[$t][] = $k;
                }
            }

            return array_map(fn ($s) => $s[0], array_filter($kira, fn ($s) => count($s) === 1));
        };
        $t = $unik($kataTemplat);
        $s = $unik($kataImbasan);
        $pasangan = [];
        foreach (array_intersect_key($t, $s) as $kunci => $k) {
            $pasangan[] = [$k['x'], $k['y'], $s[$kunci]['x'], $s[$kunci]['y']];
        }

        $n = count($pasangan);
        if ($n < self::MIN_TITIK) {
            return null;
        }
        $ralat = fn (array $ax, array $ay, array $p) => hypot($ax[0] * $p[0] + $ax[1] * $p[1] + $ax[2] - $p[2], $ay[0] * $p[0] + $ay[1] * $p[1] + $ay[2] - $p[3]);
        $dalamanBagi = fn (array $ax, array $ay) => array_values(array_filter($pasangan, fn ($p) => $ralat($ax, $ay, $p) <= self::TOLERANSI));

        // RANSAC deterministik: afin tepat daripada tiga titik, pilih yang paling banyak titik
        // dalaman (titik terpencil besar tidak memesongkan pilihan seperti kuasa dua terkecil)
        // Semua gabungan bila sedikit; jika tidak, sampel rawak dengan benih tetap (hasil sama setiap kali)
        $gabungan = [];
        if ($n * ($n - 1) * ($n - 2) / 6 <= self::MAKS_CUBAAN) {
            for ($i = 0; $i < $n - 2; $i++) {
                for ($j = $i + 1; $j < $n - 1; $j++) {
                    for ($k = $j + 1; $k < $n; $k++) {
                        $gabungan[] = [$i, $j, $k];
                    }
                }
            }
        } else {
            $benih = 12345;
            $rawak = function () use (&$benih, $n): int {
                $benih = ($benih * 1103515245 + 12345) & 0x7FFFFFFF;

                return $benih % $n;
            };
            while (count($gabungan) < self::MAKS_CUBAAN) {
                $tiga = array_unique([$rawak(), $rawak(), $rawak()]);
                if (count($tiga) === 3) {
                    $gabungan[] = array_values($tiga);
                }
            }
        }
        $terbaik = [];
        foreach ($gabungan as [$i, $j, $k]) {
            $tiga = [$pasangan[$i], $pasangan[$j], $pasangan[$k]];
            $ax = self::kuasaDuaTerkecil($tiga, 2);
            $ay = self::kuasaDuaTerkecil($tiga, 3);
            if ($ax && $ay && count($d = $dalamanBagi($ax, $ay)) > count($terbaik)) {
                $terbaik = $d;
                if (count($terbaik) === $n) {
                    break;
                }
            }
        }

        // Perhalusi dengan kuasa dua terkecil atas titik dalaman
        $dalaman = $terbaik;
        for ($ulang = 0; $ulang < 3 && count($dalaman) >= self::MIN_TITIK; $ulang++) {
            $ax = self::kuasaDuaTerkecil($dalaman, 2);
            $ay = self::kuasaDuaTerkecil($dalaman, 3);
            if (! $ax || ! $ay) {
                return null;
            }
            $dalaman = $dalamanBagi($ax, $ay);
        }
        if (count($dalaman) < self::MIN_TITIK) {
            return null;
        }
        $baki = array_map(fn ($p) => $ralat($ax, $ay, $p), $dalaman);
        sort($baki);
        $median = $baki[intdiv(count($baki), 2)];

        return $median <= self::RALAT_MAKS ? ['x' => $ax, 'y' => $ay, 'bil' => count($dalaman), 'ralat' => $median] : null;
    }

    /** Pekali [a, b, c] bagi sasaran = a·xt + b·yt + c (persamaan normal 3×3, penghapusan Gauss). */
    private static function kuasaDuaTerkecil(array $pasangan, int $lajur): ?array
    {
        $m = array_fill(0, 3, array_fill(0, 4, 0.0));
        foreach ($pasangan as $p) {
            $v = [$p[0], $p[1], 1.0];
            for ($i = 0; $i < 3; $i++) {
                for ($j = 0; $j < 3; $j++) {
                    $m[$i][$j] += $v[$i] * $v[$j];
                }
                $m[$i][3] += $v[$i] * $p[$lajur];
            }
        }
        for ($i = 0; $i < 3; $i++) {
            $pivot = $i;
            for ($r = $i + 1; $r < 3; $r++) {
                if (abs($m[$r][$i]) > abs($m[$pivot][$i])) {
                    $pivot = $r;
                }
            }
            [$m[$i], $m[$pivot]] = [$m[$pivot], $m[$i]];
            if (abs($m[$i][$i]) < 1e-12) {
                return null;
            }
            for ($r = 0; $r < 3; $r++) {
                if ($r !== $i) {
                    $f = $m[$r][$i] / $m[$i][$i];
                    for ($c = $i; $c < 4; $c++) {
                        $m[$r][$c] -= $f * $m[$i][$c];
                    }
                }
            }
        }

        return [$m[0][3] / $m[0][0], $m[1][3] / $m[1][1], $m[2][3] / $m[2][2]];
    }

    /** Topeng gelap templat yang dikembangkan KEMBANG piksel (rentetan 0/1, satu bait setiap piksel). */
    private static function topengTemplat(\GdImage $im): string
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $gelap = str_repeat("\0", $w * $h);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                if (((($c >> 16) & 0xFF) * 299 + (($c >> 8) & 0xFF) * 587 + ($c & 0xFF) * 114) < self::AMBANG_GELAP_TEMPLAT * 1000) {
                    $gelap[$y * $w + $x] = "\1";
                }
            }
        }
        // Kembang berasingan: mendatar, kemudian menegak
        $r = self::KEMBANG;
        $mendatar = str_repeat("\0", $w * $h);
        for ($y = 0; $y < $h; $y++) {
            $baris = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                if ($gelap[$baris + $x] === "\1") {
                    for ($d = max(0, $x - $r), $hujung = min($w - 1, $x + $r); $d <= $hujung; $d++) {
                        $mendatar[$baris + $d] = "\1";
                    }
                }
            }
        }
        $hasil = str_repeat("\0", $w * $h);
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                if ($mendatar[$y * $w + $x] === "\1") {
                    for ($d = max(0, $y - $r), $hujung = min($h - 1, $y + $r); $d <= $hujung; $d++) {
                        $hasil[$d * $w + $x] = "\1";
                    }
                }
            }
        }

        return $hasil;
    }

    /**
     * Dakwat pengisi dalam kotak medan: piksel gelap imbasan yang dipetakan ke kawasan bukan gelap
     * templat. Imej dakwat sahaja (putih + hitam, beserta jidar) untuk Tesseract.
     *
     * @return array{nisbah: float, piksel: int, imej: ?\GdImage}
     */
    public static function dakwat(\GdImage $imbasan, int $lebar, int $tinggi, string $topeng, int $tw, int $th, array $jajaran, array $kotak): array
    {
        [$a, $b, $c] = $jajaran['x'];
        [$d, $e, $f] = $jajaran['y'];
        $penentu = $a * $e - $b * $d;
        if (abs($penentu) < 1e-9) {
            return ['nisbah' => 0.0, 'piksel' => 0, 'imej' => null];
        }
        // Bucu kotak (pecahan templat) → kotak sempadan dalam piksel imbasan
        $xs = [];
        $ys = [];
        foreach ([[$kotak['x'], $kotak['y']], [$kotak['x'] + $kotak['w'], $kotak['y']], [$kotak['x'], $kotak['y'] + $kotak['h']], [$kotak['x'] + $kotak['w'], $kotak['y'] + $kotak['h']]] as [$px, $py]) {
            $xs[] = ($a * $px + $b * $py + $c) * $lebar;
            $ys[] = ($d * $px + $e * $py + $f) * $tinggi;
        }
        $x1 = max(0, (int) floor(min($xs)));
        $x2 = min($lebar - 1, (int) ceil(max($xs)));
        $y1 = max(0, (int) floor(min($ys)));
        $y2 = min($tinggi - 1, (int) ceil(max($ys)));
        if ($x2 <= $x1 || $y2 <= $y1) {
            return ['nisbah' => 0.0, 'piksel' => 0, 'imej' => null];
        }

        $jidar = 12;
        $iw = $x2 - $x1 + 1;
        $ih = $y2 - $y1 + 1;
        $out = imagecreatetruecolor($iw + 2 * $jidar, $ih + 2 * $jidar);
        $putih = imagecolorallocate($out, 255, 255, 255);
        $hitam = imagecolorallocate($out, 0, 0, 0);
        imagefill($out, 0, 0, $putih);

        $piksel = 0;
        for ($y = $y1; $y <= $y2; $y++) {
            $fy = $y / $tinggi;
            for ($x = $x1; $x <= $x2; $x++) {
                $cc = imagecolorat($imbasan, $x, $y);
                if (((($cc >> 16) & 0xFF) * 299 + (($cc >> 8) & 0xFF) * 587 + ($cc & 0xFF) * 114) >= self::AMBANG_GELAP_IMBASAN * 1000) {
                    continue;
                }
                // Songsang afin: pecahan imbasan → pecahan templat → piksel templat
                $sx = $x / $lebar - $c;
                $sy = $fy - $f;
                $tx = (int) round((($e * $sx - $b * $sy) / $penentu) * $tw);
                $ty = (int) round((($a * $sy - $d * $sx) / $penentu) * $th);
                if ($tx >= 0 && $ty >= 0 && $tx < $tw && $ty < $th && $topeng[$ty * $tw + $tx] === "\1") {
                    continue; // cetakan borang
                }
                $piksel++;
                imagesetpixel($out, $x - $x1 + $jidar, $y - $y1 + $jidar, $hitam);
            }
        }

        return ['nisbah' => $piksel / ($iw * $ih), 'piksel' => $piksel, 'imej' => $out];
    }

    /** @return array{0: string, 1: ?float} teks & purata keyakinan */
    private static function bacaTsv(string $laluan): array
    {
        $kata = [];
        $conf = [];
        foreach (is_file($laluan) ? array_slice(file($laluan, FILE_IGNORE_NEW_LINES), 1) : [] as $b) {
            $l = explode("\t", $b);
            if (count($l) >= 12 && (float) $l[10] >= 0 && trim($l[11]) !== '') {
                $kata[] = trim($l[11]);
                $conf[] = (float) $l[10];
            }
        }

        return [implode(' ', $kata), $conf ? round(array_sum($conf) / count($conf), 1) : null];
    }

    /** @return list<array{teks: string, x: int, y: int, w: int, h: int}> */
    private static function tesseractKata(string $imej): array
    {
        $folder = storage_path('app/private/ocr_sementara/beza_t_'.Str::random(12));
        @mkdir($folder, 0775, true);
        try {
            $p = new Process([config('services.ocr.tesseract_bin', 'tesseract'), $imej, $folder.DIRECTORY_SEPARATOR.'k', '-l', config('services.ocr.bahasa', 'eng'), 'tsv']);
            $p->setTimeout(120);
            $p->run();
            $kata = [];
            foreach (is_file($folder.DIRECTORY_SEPARATOR.'k.tsv') ? array_slice(file($folder.DIRECTORY_SEPARATOR.'k.tsv', FILE_IGNORE_NEW_LINES), 1) : [] as $b) {
                $l = explode("\t", $b);
                if (count($l) >= 12 && (float) $l[10] >= 0 && preg_match('/[\p{L}\p{N}]/u', $l[11])) {
                    $kata[] = ['teks' => trim($l[11]), 'x' => (int) $l[6], 'y' => (int) $l[7], 'w' => (int) $l[8], 'h' => (int) $l[9]];
                }
            }

            return $kata;
        } finally {
            foreach (glob($folder.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($folder);
        }
    }

    private static function muat(string $imej): ?\GdImage
    {
        $data = @file_get_contents($imej);
        $im = $data !== false ? @imagecreatefromstring($data) : false;

        return $im ?: null;
    }
}
