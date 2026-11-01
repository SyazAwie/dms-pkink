<?php

namespace App\Support;

/**
 * Tanda visual pada borang yang tidak dapat dibaca oleh Tesseract (T6): perkataan yang
 * DIPOTONG ("disokong/ tidak sokong" dengan "tidak sokong" dipotong) dan KOTAK pilihan
 * bertanda ✓ atau kosong. Hasilnya ditulis sebagai penanda dalam teks OCR supaya AI boleh
 * memilih nilai yang betul dan membiarkan medan kosong:
 *
 *   ~~tidak sokong~~        perkataan dipotong (pilihan yang ditolak)
 *   [✓] Emel                kotak bertanda di sebelah label
 *   [□] Pertukaran Maklumat kotak kosong
 *
 * Penanda dipilih supaya tidak boleh terhasil daripada Tesseract sendiri ("[ ]" dan "~~~~"
 * boleh): ✓ dan □ tiada dalam set aksara OCR, dan potongan mesti mengandungi teks.
 *
 * Generik (tiada koordinat templat bagi setiap borang): menggunakan kedudukan perkataan
 * daripada TSV Tesseract dan analisis piksel GD, jadi kategori baharu berfungsi tanpa
 * konfigurasi dan imbasan yang beralih/berskala tetap dikesan.
 */
final class TandaVisual
{
    /** Penanda dalam teks OCR (OllamaExtractionService menggunakan corak yang sama). */
    public const CORAK_PENANDA = '/~~[^\s~][^~]*~~|\[✓\]|\[□\]/u';

    private const AMBANG_GELAP = 150;          // kecerahan 0-255 (selepas kelabu)

    /** Imej lebih besar dilangkau (cth. foto kamera 48MP); A4 300dpi ~8.7 juta piksel. */
    private const MAKS_PIKSEL = 25_000_000;

    /** Anggaran memori GD (imej penuh + separuh saiz) bagi setiap piksel, termasuk ruang selamat. */
    private const BAIT_SETIAP_PIKSEL = 12;

    // Potongan: garisan berterusan merentasi tengah perkataan
    private const POTONG_LIPUTAN = 0.97;       // pecahan lebar perkataan yang dilalui garisan

    private const POTONG_TINGGI_MAKS = 1.6;    // x median tinggi perkataan halaman

    private const POTONG_GELAP_MAKS = 0.55;    // perkataan lebih gelap = teks songsang, abaikan

    // Kotak: dikesan pada skala kecil (75dpi bagi imej 300dpi)
    private const SKALA_KOTAK = 4;

    // Garisan 1-2px menjadi kelabu cerah selepas dikecilkan 1/4 (bilinear): ambang lebih tinggi
    private const AMBANG_GELAP_KECIL = 225;

    private const KOTAK_MIN = 8;               // sisi (px skala kecil) ~2.7mm

    private const KOTAK_MAKS = 40;             // ~13.5mm

    private const KOTAK_ISI_MIN = 0.55;        // luas komponen / luas segi empat batasnya

    private const GARIS_MIN = 7;               // larian piksel gelap yang dikira garisan kotak/jadual

    private const TANDA_NISBAH = 0.02;         // dakwat dalam kotak (tidak termasuk tepi) = bertanda

    /**
     * @param  list<array{teks: string, x: int, y: int, w: int, h: int, conf: float, baris: string}>  $kata
     * @return list<array{teks: string, x: int, y: int, w: int, h: int, conf: float, baris: string}>
     *                                                                                               kata dengan penanda; sampah OCR di dalam kotak dibuang
     */
    public static function anotasi(string $laluanImej, array $kata, ?array $kotak = null): array
    {
        if (! $kata) {
            return $kata;
        }
        $im = self::muat($laluanImej);
        if ($im === null) {
            return $kata;
        }
        imagefilter($im, IMG_FILTER_GRAYSCALE);

        try {
            $kata = self::tandaPotongan($im, $kata);

            return self::tandaKotak($im, $kata, $kotak ?? self::cariKotak($im));
        } finally {
            imagedestroy($im);
        }
    }

    private static function gelap(\GdImage $im, int $x, int $y): bool
    {
        return (imagecolorat($im, $x, $y) & 0xFF) < self::AMBANG_GELAP;
    }

    /**
     * Perkataan (sekurang-kurangnya 2 huruf/angka) yang dipotong: wujud garisan lurus (boleh
     * sedikit condong) dalam jalur tengah perkataan yang BERTERUSAN merentasi hampir seluruh
     * lebarnya, termasuk celah antara huruf. Huruf biasa (walaupun tebal) sentiasa ada celah
     * (liputan <= 0.88 pada borang ujian), garisan potongan meliputi semuanya (1.00).
     *
     * Perkataan yang jauh lebih tinggi daripada biasa diabaikan: bbox itu termasuk tandatangan,
     * lengkung tangan atau tepi kotak huruf, bukan teks sebaris.
     */
    private static function tandaPotongan(\GdImage $im, array $kata): array
    {
        $W = imagesx($im);
        $H = imagesy($im);
        $tinggi = array_map(fn ($k) => $k['h'], $kata);
        sort($tinggi);
        $hadTinggi = self::POTONG_TINGGI_MAKS * ($tinggi[intdiv(count($tinggi), 2)] ?? 0);

        foreach ($kata as &$k) {
            if (preg_match_all('/[\p{L}\p{N}]/u', $k['teks']) < 2 || $k['h'] < 8 || $k['h'] > $hadTinggi) {
                continue;
            }
            $x0 = max(0, $k['x'] + (int) round($k['w'] * 0.06));
            $x1 = min($W - 1, $k['x'] + $k['w'] - 1 - (int) round($k['w'] * 0.06));
            $lebar = $x1 - $x0 + 1;
            if ($lebar < 8) {
                continue;
            }
            // Matriks gelap perkataan (sekali), kemudian uji garisan dalam tatasusunan
            $g = [];
            for ($y = 0; $y < $k['h'] && $H > $k['y'] + $y; $y++) {
                for ($c = 0; $c < $lebar; $c++) {
                    $g[$y][$c] = self::gelap($im, $x0 + $c, $k['y'] + $y);
                }
            }
            // Teks putih atas latar gelap (sepanduk kepala surat): setiap baris "gelap"
            $piksel = array_sum(array_map('count', $g));
            if ($piksel > 0 && array_sum(array_map('array_sum', $g)) / $piksel > self::POTONG_GELAP_MAKS) {
                continue;
            }
            $k['dipotong'] = self::liputanGarisan($g, $k['h'], $lebar) >= self::POTONG_LIPUTAN;
        }
        unset($k);

        // Penanda: perkataan dipotong berturut-turut dalam baris yang sama dicantum: ~~tidak sokong~~
        foreach ($kata as $i => &$k) {
            if (empty($k['dipotong'])) {
                continue;
            }
            $sebelum = $kata[$i - 1] ?? null;
            $selepas = $kata[$i + 1] ?? null;
            $mula = ! ($sebelum && ! empty($sebelum['dipotong']) && $sebelum['baris'] === $k['baris']);
            $akhir = ! ($selepas && ! empty($selepas['dipotong']) && $selepas['baris'] === $k['baris']);
            $k['teks'] = ($mula ? '~~' : '').$k['teks'].($akhir ? '~~' : '');
        }
        unset($k);

        return $kata;
    }

    /**
     * Liputan terbaik (0-1) sebarang garisan lurus yang kedua-dua hujungnya dalam jalur tengah
     * [25%, 75%] tinggi perkataan: pecahan lajur yang ada piksel gelap dalam ±2px garisan itu.
     *
     * @param  array<int, array<int, bool>>  $g
     */
    public static function liputanGarisan(array $g, int $tinggi, int $lebar): float
    {
        $ya0 = (int) round($tinggi * 0.25);
        $ya1 = (int) round($tinggi * 0.75);
        $terbaik = 0.0;
        for ($ya = $ya0; $ya <= $ya1; $ya += 2) {
            for ($yb = $ya0; $yb <= $ya1; $yb += 2) {
                $n = 0;
                for ($c = 0; $c < $lebar; $c++) {
                    $yc = (int) round($ya + ($yb - $ya) * $c / max(1, $lebar - 1));
                    if (($g[$yc][$c] ?? false) || ($g[$yc - 1][$c] ?? false) || ($g[$yc + 1][$c] ?? false)
                        || ($g[$yc - 2][$c] ?? false) || ($g[$yc + 2][$c] ?? false)) {
                        $n++;
                    }
                }
                $terbaik = max($terbaik, $n / $lebar);
                if ($terbaik >= 1.0) {
                    return 1.0;
                }
            }
        }

        return $terbaik;
    }

    /**
     * Kotak pilihan: kawasan putih kecil hampir segi empat yang dikelilingi garisan, di sebelah
     * label perkataan. Kotak yang mengandungi huruf dikenali (kotak nama "S Y A Z M I") diabaikan.
     */
    private static function tandaKotak(\GdImage $im, array $kata, array $kotak): array
    {
        $buang = [];
        foreach ($kotak as $j => $b) {
            // Kotak huruf (nama, no. telefon) datang berderet; kotak pilihan berdiri sendiri
            foreach ($kotak as $j2 => $b2) {
                if ($j2 !== $j && abs($b2['y'] - $b['y']) < $b['h'] * 0.3
                    && min(abs($b2['x'] - ($b['x'] + $b['w'])), abs($b['x'] - ($b2['x'] + $b2['w']))) < $b['w'] * 0.6) {
                    continue 2;
                }
            }
            // Perkataan di dalam kotak pilihan ialah tanda itu sendiri dibaca sebagai huruf
            // ("/", "JS", "Ny"): dibuang daripada teks, diganti penanda [✓]
            $dalam = [];
            foreach ($kata as $i => $k) {
                $cx = $k['x'] + $k['w'] / 2;
                $cy = $k['y'] + $k['h'] / 2;
                if ($cx >= $b['x'] && $cx <= $b['x'] + $b['w'] && $cy >= $b['y'] && $cy <= $b['y'] + $b['h']) {
                    // Sel kandungan (lajur "Bil" 1, 2, 3...; huruf dibaca sangat yakin), bukan kotak pilihan
                    if (preg_match('/^\(?\d+[.)]?$/', $k['teks']) || ($k['conf'] >= 90 && preg_match('/[\p{L}\p{N}]/u', $k['teks']))) {
                        continue 2;
                    }
                    $dalam[] = $i;
                }
            }

            $label = self::label($b, $kata, $dalam);
            if ($label === null) {
                continue;
            }
            [$i, $sisi] = $label;
            $penanda = self::bertanda($im, $b) ? '[✓]' : '[□]';
            $kata[$i]['teks'] = $sisi === 'kanan' ? $penanda.' '.$kata[$i]['teks'] : $kata[$i]['teks'].' '.$penanda;
            array_push($buang, ...$dalam);
        }

        return array_values(array_diff_key($kata, array_flip($buang)));
    }

    /** Kotak pilihan dalam fail imej (dipanggil semasa Tesseract masih berjalan). */
    public static function cariKotakFail(string $laluanImej): array
    {
        $im = self::muat($laluanImej);
        if ($im === null) {
            return [];
        }
        imagefilter($im, IMG_FILTER_GRAYSCALE);
        try {
            return self::cariKotak($im);
        } finally {
            imagedestroy($im);
        }
    }

    /**
     * Muat imej (truecolor) jika saiznya munasabah. Ralat kehabisan memori PHP tidak boleh
     * ditangkap, jadi imej terlalu besar (foto kamera beresolusi tinggi) dilangkau dan had
     * memori dinaikkan secukupnya sebelum GD dimuat (corak sama dengan BuangGarisan).
     */
    private static function muat(string $laluanImej): ?\GdImage
    {
        $saiz = @getimagesize($laluanImej);
        if ($saiz === false || $saiz[0] * $saiz[1] > self::MAKS_PIKSEL) {
            return null;
        }
        $had = ini_get('memory_limit');
        $perlu = memory_get_usage(true) + $saiz[0] * $saiz[1] * self::BAIT_SETIAP_PIKSEL + 32 * 1048576;
        if ($had !== '-1' && self::keBait((string) $had) < $perlu) {
            ini_set('memory_limit', (string) $perlu);
        }
        $im = @imagecreatefromstring((string) @file_get_contents($laluanImej));
        if ($im === false) {
            return null;
        }
        if (! imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }

        return $im;
    }

    private static function keBait(string $had): int
    {
        $nilai = (int) $had;

        return match (strtoupper(substr(trim($had), -1))) {
            'G' => $nilai * 1073741824,
            'M' => $nilai * 1048576,
            'K' => $nilai * 1024,
            default => $nilai,
        };
    }

    /**
     * Kotak (koordinat imej asal): kawasan bukan-garisan kecil, hampir segi empat, tertutup.
     *
     * @return list<array{x: int, y: int, w: int, h: int}>
     */
    public static function cariKotak(\GdImage $im): array
    {
        $s = self::SKALA_KOTAK;
        $w = intdiv(imagesx($im), $s);
        $h = intdiv(imagesy($im), $s);
        if ($w < 50 || $h < 50) {
            return [];
        }
        // Kecilkan separuh, kabur 3x3, kemudian kecilkan ke skala kotak: garisan nipis (0.5pt
        // Word, 1px) melebar dahulu, jika tidak ia hilang dan kotak "bocor" ke latar.
        // Imej separuh saiz (~9MB) dan bukan salinan 300dpi (~35MB) supaya muat had memori PHP.
        $separuh = imagescale($im, intdiv(imagesx($im), 2), intdiv(imagesy($im), 2), IMG_BILINEAR_FIXED);
        if ($separuh === false) {
            return [];
        }
        imageconvolution($separuh, [[1, 1, 1], [1, 1, 1], [1, 1, 1]], 9, 0);
        $kecil = imagescale($separuh, $w, $h, IMG_BILINEAR_FIXED);
        imagedestroy($separuh);
        if ($kecil === false) {
            return [];
        }

        // Peta (rentetan bait, jimat memori): "\1" = gelap
        $gelap = str_repeat("\0", $w * $h);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ((imagecolorat($kecil, $x, $y) & 0xFF) < self::AMBANG_GELAP_KECIL) {
                    $gelap[$y * $w + $x] = "\1";
                }
            }
        }
        imagedestroy($kecil);

        // Peta garisan: piksel gelap dalam larian mendatar/menegak panjang (tanda ✓ & huruf pendek)
        $garis = str_repeat("\0", $w * $h);
        for ($y = 0; $y < $h; $y++) {
            $run = 0;
            for ($x = 0; $x <= $w; $x++) {
                if ($x < $w && $gelap[$y * $w + $x] === "\1") {
                    $run++;

                    continue;
                }
                if ($run >= self::GARIS_MIN) {
                    for ($i = $x - $run; $i < $x; $i++) {
                        $garis[$y * $w + $i] = "\1";
                    }
                }
                $run = 0;
            }
        }
        for ($x = 0; $x < $w; $x++) {
            $run = 0;
            for ($y = 0; $y <= $h; $y++) {
                if ($y < $h && $gelap[$y * $w + $x] === "\1") {
                    $run++;

                    continue;
                }
                if ($run >= self::GARIS_MIN) {
                    for ($i = $y - $run; $i < $y; $i++) {
                        $garis[$i * $w + $x] = "\1";
                    }
                }
                $run = 0;
            }
        }

        // Komponen bukan-garisan (4-jiran) melalui larian baris + union-find: memori ikut
        // bilangan larian, bukan piksel (latar halaman ialah satu komponen yang sangat besar)
        $induk = [];
        $statistik = [];   // [minX, maxX, minY, maxY, luas, tepi]
        $cari = function (int $a) use (&$induk): int {
            while ($induk[$a] !== $a) {
                $induk[$a] = $induk[$induk[$a]];
                $a = $induk[$a];
            }

            return $a;
        };
        $larianLepas = [];
        $id = 0;
        for ($y = 0; $y < $h; $y++) {
            $larian = [];
            $x = 0;
            while ($x < $w) {
                if ($garis[$y * $w + $x] === "\1") {
                    $x++;

                    continue;
                }
                $x0 = $x;
                while ($x < $w && $garis[$y * $w + $x] !== "\1") {
                    $x++;
                }
                $larian[] = [$x0, $x - 1, $id];
                $induk[$id] = $id;
                $statistik[$id] = [$x0, $x - 1, $y, $y, $x - $x0, $x0 === 0 || $x === $w || $y === 0 || $y === $h - 1];
                $id++;
            }
            // Cantum dengan larian bertindih dalam baris sebelumnya (dua penunjuk)
            $j = 0;
            foreach ($larian as [$a0, $a1, $aid]) {
                while ($j < count($larianLepas) && $larianLepas[$j][1] < $a0) {
                    $j++;
                }
                for ($k = $j; $k < count($larianLepas) && $larianLepas[$k][0] <= $a1; $k++) {
                    $ra = $cari($aid);
                    $rb = $cari($larianLepas[$k][2]);
                    if ($ra !== $rb) {
                        $induk[$rb] = $ra;
                        $sa = $statistik[$ra];
                        $sb = $statistik[$rb];
                        $statistik[$ra] = [min($sa[0], $sb[0]), max($sa[1], $sb[1]), min($sa[2], $sb[2]), max($sa[3], $sb[3]), $sa[4] + $sb[4], $sa[5] || $sb[5]];
                        unset($statistik[$rb]);
                    }
                }
            }
            $larianLepas = $larian;
        }
        unset($garis, $induk, $larianLepas);

        // Calon: komponen kecil yang tidak menyentuh tepi halaman
        $calon = [];
        foreach ($statistik as [$minX, $maxX, $minY, $maxY, $luas, $tepi]) {
            if (! $tepi && $maxX - $minX < self::KOTAK_MAKS && $maxY - $minY < self::KOTAK_MAKS) {
                $calon[] = [$minX, $maxX, $minY, $maxY, $luas];
            }
        }
        // Tanda ✓ yang tebal & curam dikira "garisan" dan membelah sel kepada kepingan yang
        // segi empat batasnya BERTINDIH (garisan condong); kotak huruf berderet tidak bertindih
        // (dipisahkan garisan tegak). Cantumkan kepingan bertindih.
        do {
            $dicantum = false;
            foreach ($calon as $i => $a) {
                foreach ($calon as $j => $b) {
                    if ($j <= $i || $a[1] < $b[0] || $b[1] < $a[0] || $a[3] < $b[2] || $b[3] < $a[2]) {
                        continue;
                    }
                    $calon[$i] = [min($a[0], $b[0]), max($a[1], $b[1]), min($a[2], $b[2]), max($a[3], $b[3]), $a[4] + $b[4]];
                    unset($calon[$j]);
                    $dicantum = true;

                    continue 3;
                }
            }
        } while ($dicantum);

        $kotak = [];
        foreach ($calon as [$minX, $maxX, $minY, $maxY, $luas]) {
            $kw = $maxX - $minX + 1;
            $kh = $maxY - $minY + 1;
            if ($kw < self::KOTAK_MIN || $kh < self::KOTAK_MIN || $kw > self::KOTAK_MAKS || $kh > self::KOTAK_MAKS
                || $kw > 2 * $kh || $kh > 2 * $kw || $luas / ($kw * $kh) < self::KOTAK_ISI_MIN) {
                continue;
            }
            // Kotak sebenar dikelilingi kertas/sel putih di luar garisannya; huruf putih dalam
            // sepanduk gelap dikelilingi latar gelap
            if (! self::gelangCerah($gelap, $w, $h, $minX, $maxX, $minY, $maxY)) {
                continue;
            }
            $kotak[] = ['x' => $minX * $s, 'y' => $minY * $s, 'w' => $kw * $s, 'h' => $kh * $s];
        }
        unset($gelap);
        usort($kotak, fn ($a, $b) => [$a['y'], $a['x']] <=> [$b['y'], $b['x']]);

        return $kotak;
    }

    /** Pecahan piksel cerah pada gelang 3px di luar segi empat (skala kecil) >= 50%. */
    private static function gelangCerah(string $gelap, int $w, int $h, int $minX, int $maxX, int $minY, int $maxY): bool
    {
        $d = 3;
        $cerah = 0;
        $jumlah = 0;
        $semak = function (int $x, int $y) use ($gelap, $w, $h, &$cerah, &$jumlah): void {
            if ($x >= 0 && $y >= 0 && $x < $w && $y < $h) {
                $jumlah++;
                $cerah += $gelap[$y * $w + $x] === "\1" ? 0 : 1;
            }
        };
        for ($x = $minX - $d; $x <= $maxX + $d; $x++) {
            $semak($x, $minY - $d);
            $semak($x, $maxY + $d);
        }
        for ($y = $minY - $d + 1; $y < $maxY + $d; $y++) {
            $semak($minX - $d, $y);
            $semak($maxX + $d, $y);
        }

        return $jumlah > 0 && $cerah / $jumlah >= 0.5;
    }

    /**
     * Label kotak: perkataan sebaris paling hampir di KANAN kotak (kotak sebelum label), atau
     * jika tiada, perkataan di KIRI yang berakhir dengan ":" (label sebelum kotak).
     *
     * @return array{0: int, 1: string}|null indeks kata dan sisi kotak relatif kepada label
     */
    private static function label(array $b, array $kata, array $dalam): ?array
    {
        $tengahY = $b['y'] + $b['h'] / 2;
        $terbaik = null;
        $jarakTerbaik = PHP_INT_MAX;
        foreach (['kanan', 'kiri'] as $sisi) {
            foreach ($kata as $i => $k) {
                if (in_array($i, $dalam, true) || ! preg_match('/\p{L}{2,}/u', $k['teks'])) {
                    continue;
                }
                if ($tengahY < $k['y'] - $k['h'] * 0.6 || $tengahY > $k['y'] + $k['h'] * 1.6) {
                    continue; // bukan sebaris
                }
                if ($sisi === 'kanan') {
                    $jarak = $k['x'] - ($b['x'] + $b['w']);
                    $had = 3 * $b['w'];
                } else {
                    $jarak = $b['x'] - ($k['x'] + $k['w']);
                    $had = 12 * $b['w'];
                    if (! str_ends_with($k['teks'], ':')) {
                        continue;
                    }
                }
                if ($jarak >= -$b['w'] * 0.2 && $jarak <= $had && $jarak < $jarakTerbaik) {
                    $terbaik = [$i, $sisi];
                    $jarakTerbaik = $jarak;
                }
            }
            if ($terbaik) {
                return $terbaik;
            }
        }

        return null;
    }

    /** Dakwat di bahagian dalam kotak (tepi 18% diabaikan supaya garisan kotak tidak dikira). */
    private static function bertanda(\GdImage $im, array $b): bool
    {
        $mx = (int) round($b['w'] * 0.18);
        $my = (int) round($b['h'] * 0.18);
        $n = 0;
        $jumlah = 0;
        for ($y = $b['y'] + $my; $y < $b['y'] + $b['h'] - $my; $y++) {
            for ($x = $b['x'] + $mx; $x < $b['x'] + $b['w'] - $mx; $x++) {
                $jumlah++;
                $n += self::gelap($im, $x, $y) ? 1 : 0;
            }
        }

        return $jumlah > 0 && $n / $jumlah >= self::TANDA_NISBAH;
    }
}
