<?php

namespace App\Services;

use App\Models\DokumenField;
use App\Models\TemplatBorang;
use App\Support\JenisMedan;
use App\Support\KedudukanMedan;
use App\Support\MedanJadual;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Cadangan automatik kedudukan medan atas templat (TB6, butang "Cadang Automatik").
 *
 * Kotak perkataan Tesseract pada imej latar templat digunakan untuk mencari label setiap medan
 * (padanan kabur ikut susunan medan kategori, jadi label berulang seperti "Komen/Ulasan"
 * dipadankan mengikut aliran borang). Kotak nilai diletak atas garisan isian di sebelah kanan
 * label (dikesan daripada piksel gelap), lajur jadual daripada garisan menegak sel pengepala,
 * baris jadual daripada garisan mendatar di bawahnya, dan pilihan ("disokong/ tidak sokong")
 * atas perkataan pilihan itu. Hasilnya titik permulaan sahaja: pentadbir menyemak & membetulkan.
 */
class CadangKedudukanService
{
    /** Luminans maksimum piksel "gelap" (garisan, teks). */
    private const GELAP = 128;

    public function __construct(private BorangSiapService $borangSiap) {}

    /**
     * @return array{kedudukan: array, dijumpai: list<string>, tidak: list<string>}
     */
    public function cadang(TemplatBorang $templat): array
    {
        // Dikunci ikut nombor muka surat; imej GD dimuat satu demi satu (imej()) supaya templat
        // berbilang muka surat tidak memegang semua bitmap dalam memori serentak
        $halaman = [];
        foreach ($this->borangSiap->latar($templat) as $i => $m) {
            $saiz = @getimagesize($m['fail']);
            if (! $saiz) {
                continue;
            }
            $halaman[$i + 1] = ['no' => $i + 1, 'fail' => $m['fail'], 'w' => $saiz[0], 'h' => $saiz[1], 'baris' => $this->barisTeks($m['fail'])];
        }

        try {
            return $this->susun($templat, $halaman);
        } finally {
            $this->imej(null);
        }
    }

    /** @var array{0: int, 1: \GdImage}|null imej muka surat yang sedang dimuat */
    private ?array $imejSemasa = null;

    /** Imej GD bagi satu muka surat (hanya satu dalam memori); null = bebaskan. */
    private function imej(?array $p): ?\GdImage
    {
        if ($this->imejSemasa && (! $p || $this->imejSemasa[0] !== $p['no'])) {
            imagedestroy($this->imejSemasa[1]);
            $this->imejSemasa = null;
        }
        if ($p && ! $this->imejSemasa) {
            $imej = @imagecreatefromjpeg($p['fail']);
            if ($imej === false) {
                throw new \RuntimeException("Imej templat muka {$p['no']} tidak dapat dibaca.");
            }
            $this->imejSemasa = [$p['no'], $imej];
        }

        return $this->imejSemasa[1] ?? null;
    }

    private function susun(TemplatBorang $templat, array $halaman): array
    {
        $hasil = ['medan' => [], 'asas' => []];
        $dijumpai = [];
        $tidak = [];
        $kursor = ['muka' => 1, 'y' => 0];

        if ($t = $this->cari($halaman, 'Tarikh', $kursor, true)) {
            $hasil['asas']['tarikh_dokumen'] = $this->kotakNilai($halaman, $t, false);
            $dijumpai[] = 'Tarikh Dokumen';
        }

        foreach ($templat->jenisDokumen->fields as $medan) {
            if (! JenisMedan::adaNilai($medan)) {
                // Tajuk seksyen: hanya menggerakkan kursor supaya label berulang dipadankan ikut aliran borang
                if ($t = $this->cari($halaman, $medan->nama_field, $kursor, false, 0.6)) {
                    $kursor = $this->maju($kursor, ['muka' => $t['muka'], 'y' => $t['y1']]);
                }

                continue;
            }

            $data = match (true) {
                $medan->jenis_data === 'jadual' => $this->jadual($halaman, $medan, $kursor),
                (bool) KedudukanMedan::pilihanMedan($medan) => $this->pilihan($halaman, $medan, $kursor),
                default => $this->medanBiasa($halaman, $medan, $kursor),
            };
            if ($data) {
                $hasil['medan'][$medan->dokumen_field_id] = $data['kedudukan'];
                $kursor = $this->maju($kursor, $data['kursor']);
                $dijumpai[] = $medan->nama_field;
            } else {
                $tidak[] = $medan->nama_field;
            }
        }

        return ['kedudukan' => $hasil, 'dijumpai' => $dijumpai, 'tidak' => $tidak];
    }

    /** Kursor hanya bergerak ke hadapan (padanan sebelum kursor ialah sandaran, bukan aliran borang). */
    private function maju(array $kursor, array $baharu): array
    {
        return [$baharu['muka'], $baharu['y']] > [$kursor['muka'], $kursor['y']] ? $baharu : $kursor;
    }

    private function medanBiasa(array $halaman, DokumenField $medan, array $kursor): ?array
    {
        $t = $this->cari($halaman, $medan->nama_field, $kursor);
        if (! $t) {
            return null;
        }

        return ['kedudukan' => ['kotak' => $this->kotakNilai($halaman, $t, $medan->jenis_data === 'textarea')],
            'kursor' => ['muka' => $t['muka'], 'y' => $t['y1']]];
    }

    /**
     * Pilihan: setiap pilihan dicari selepas label (atau kursor). Pilihan sebaris dipisah "/"
     * (cth. "disokong/ tidak sokong") → gaya potong; selainnya ✓ di kiri perkataan pilihan.
     */
    private function pilihan(array $halaman, DokumenField $medan, array $kursor): ?array
    {
        $label = $this->cari($halaman, $medan->nama_field, $kursor);
        $mula = $label ? ['muka' => $label['muka'], 'y' => $label['y1']] : $kursor;
        $jumpa = [];
        foreach (KedudukanMedan::pilihanMedan($medan) as $p) {
            if ($p === JenisMedan::NILAI_LAIN) {
                $p2 = 'Lain-lain';
            } else {
                $p2 = $p;
            }
            // Ya/Tidak terlalu umum: hanya dicari pada baris label
            $t = $medan->jenis_data === 'ya_tidak'
                ? ($label ? $this->cariDalamBaris($halaman, $label, $p2) : null)
                : $this->cari($halaman, $p2, $mula, false, 0.99);
            if ($t) {
                $jumpa[$p] = $t;
            }
        }
        if (! $jumpa) {
            return $label ? $this->medanBiasa($halaman, $medan, $kursor) : null;
        }

        $sebaris = count(array_unique(array_map(fn ($t) => $t['muka'].':'.$t['baris'], $jumpa))) === 1 && count($jumpa) > 1;
        $ikutX = array_values($jumpa);
        usort($ikutX, fn ($a, $b) => $a['x1'] <=> $b['x1']);
        $gaya = $sebaris && str_contains($this->teksAntara($ikutX[0], $ikutX[1], $halaman), '/') ? 'potong' : 'tanda';
        $pilihan = [];
        foreach ($jumpa as $p => $t) {
            $lh = $t['y2'] - $t['y1'];
            $kotak = $gaya === 'potong'
                ? ['x1' => $t['x1'] - 2, 'y1' => $t['y1'] - 2, 'x2' => $t['x2'] + 2, 'y2' => $t['y2'] + 2]
                : ['x1' => $t['x1'] - 1.6 * $lh, 'y1' => $t['y1'] - 0.2 * $lh, 'x2' => $t['x1'] - 0.2 * $lh, 'y2' => $t['y2'] + 0.2 * $lh];
            $pilihan[$p] = $this->pecahan($halaman, $t['muka'], $kotak);
        }
        $akhir = collect($jumpa)->sortBy(fn ($t) => $t['muka'] * 100000 + $t['y1'])->last();

        return ['kedudukan' => ['pilihan' => $pilihan, 'gaya' => $gaya], 'kursor' => ['muka' => $akhir['muka'], 'y' => $akhir['y1']]];
    }

    /** Jadual: baris pengepala yang memuatkan paling banyak nama lajur; sel dikesan daripada garisan. */
    private function jadual(array $halaman, DokumenField $medan, array $kursor): ?array
    {
        $lajur = MedanJadual::lajur($medan);
        $terbaik = null;
        foreach ($halaman as $p) {
            foreach ($p['baris'] as $bi => $b) {
                if ($this->sebelum($p['no'], $b, $kursor)) {
                    continue;
                }
                $sepadan = [];
                foreach ($lajur as $l) {
                    if ($m = $this->padanBaris($b, TemplatBorangService::tokenTeks($l['nama']))) {
                        if ($m['skor'] >= 0.5) {
                            $sepadan[$l['kod']] = $m;
                        }
                    }
                }
                if ($sepadan && (! $terbaik || count($sepadan) > count($terbaik['sepadan']))) {
                    $terbaik = ['p' => $p, 'b' => $b, 'bi' => $bi, 'sepadan' => $sepadan];
                }
            }
        }
        if (! $terbaik || count($terbaik['sepadan']) < max(1, (int) ceil(count($lajur) / 2))) {
            return null;
        }

        ['p' => $p, 'b' => $b, 'sepadan' => $sepadan] = $terbaik;
        $menegak = $this->garisMenegak($p, (int) $b['y1'], (int) $b['y2']);
        $lh = $b['y2'] - $b['y1'];
        $sel = [];
        foreach ($sepadan as $kod => $m) {
            $tengah = ($m['x1'] + $m['x2']) / 2;
            $kiri = collect($menegak)->filter(fn ($x) => $x < $tengah)->max();
            $kanan = collect($menegak)->filter(fn ($x) => $x > $tengah)->min();
            $sel[$kod] = $kiri !== null && $kanan !== null ? [$kiri + 3, $kanan - 3] : [$m['x1'] - 0.5 * $lh, $m['x2'] + 0.5 * $lh];
        }

        // Garisan mendatar di bawah pengepala (merentas lajur pertama hingga terakhir) → tinggi baris
        $xKiri = (int) min(array_column($sel, 0));
        $xKanan = (int) max(array_column($sel, 1));
        // Mula dari tengah teks pengepala: garisan bawah sel pengepala boleh berada di atas garis dasar teks
        $mendatar = $this->garisMendatar($p, $xKiri, $xKanan, (int) (($b['y1'] + $b['y2']) / 2), (int) min($p['h'] - 1, $b['y2'] + 40 * $lh));
        if (count($mendatar) >= 2) {
            $tinggi = $mendatar[1] - $mendatar[0];
            $bilangan = 1;
            for ($i = 2; $i < count($mendatar) && $bilangan < 30; $i++) {
                if (abs(($mendatar[$i] - $mendatar[$i - 1]) - $tinggi) > 0.35 * $tinggi) {
                    break;
                }
                $bilangan++;
            }
            $y1 = $mendatar[0] + 2;
        } else {
            $tinggi = 1.8 * $lh;
            $bilangan = 1;
            $y1 = $b['y2'] + 0.4 * $lh;
        }

        $baris = $this->pecahan($halaman, $p['no'], ['x1' => $xKiri, 'y1' => $y1, 'x2' => $xKanan, 'y2' => $y1 + $tinggi]);
        $baris['bilangan'] = $bilangan;
        $hasilLajur = [];
        foreach ($sel as $kod => [$x1, $x2]) {
            $hasilLajur[$kod] = $this->pecahan($halaman, $p['no'], ['x1' => $x1, 'y1' => $y1, 'x2' => $x2, 'y2' => $y1 + $tinggi]);
        }

        return ['kedudukan' => ['baris' => $baris, 'lajur' => $hasilLajur], 'kursor' => ['muka' => $p['no'], 'y' => $y1 + $bilangan * $tinggi]];
    }

    /**
     * Kotak nilai bagi label: atas garisan isian di sebelah kanan label jika ada, jika tidak
     * selepas label hingga perkataan seterusnya / tepi kandungan.
     */
    private function kotakNilai(array $halaman, array $t, bool $berbilang): array
    {
        $p = $halaman[$t['muka']];
        $lh = max(8, $t['y2'] - $t['y1']);
        $garis = $this->cariGaris($p, (int) ($t['x2'] + 2), (int) $t['y1'], (int) min($p['h'] - 1, $t['y2'] + 1.2 * $lh), $t['y2']);
        if ($garis) {
            $kotak = ['x1' => $garis['x1'], 'y1' => $garis['y'] - 1.7 * $lh, 'x2' => $garis['x2'], 'y2' => $garis['y'] - 1];
            if ($berbilang) {
                // Baris isian kedua di bawah (cth. "Komen/Ulasan: ____" diikuti satu lagi garisan penuh)
                $kedua = $this->cariGaris($p, (int) ($p['w'] * 0.04), (int) ($garis['y'] + 0.5 * $lh), (int) min($p['h'] - 1, $garis['y'] + 2.8 * $lh), $garis['y'] + 1.5 * $lh);
                if ($kedua) {
                    $kotak['y2'] = $kedua['y'] - 1;
                    $kotak['x2'] = max($kotak['x2'], $kedua['x2']);
                }
            }

            return $this->pecahan($halaman, $t['muka'], $kotak);
        }

        $kanan = $this->kananKandungan($p);
        foreach ($p['baris'][$t['baris']]['kata'] as $k) {
            if ($k['x1'] > $t['x2'] + 2 * $lh) {
                $kanan = min($kanan, $k['x1'] - 0.5 * $lh);
                break;
            }
        }
        $x1 = $t['x2'] + 0.6 * $lh;

        return $this->pecahan($halaman, $t['muka'], ['x1' => $x1, 'y1' => $t['y1'] - 0.25 * $lh,
            'x2' => max($x1 + 4 * $lh, $kanan), 'y2' => $t['y2'] + ($berbilang ? 2.0 : 0.25) * $lh]);
    }

    // ------------------------------------------------------------------ carian teks

    /**
     * Padanan label terbaik selepas kursor (jika tiada, di mana-mana). Hasil: muka, indeks baris,
     * kotak span perkataan label (px) dan skor.
     */
    private function cari(array $halaman, string $label, array $kursor, bool $pertama = false, float $min = 0.5): ?array
    {
        $token = TemplatBorangService::tokenTeks($label);
        if (! $token) {
            return null;
        }
        $selepas = [];
        $semua = [];
        foreach ($halaman as $p) {
            foreach ($p['baris'] as $bi => $b) {
                $m = $this->padanBaris($b, $token);
                if (! $m || ! $this->cukup($m, count($token), $min)) {
                    continue;
                }
                $calon = $m + ['muka' => $p['no'], 'baris' => $bi];
                $semua[] = $calon;
                if (! $this->sebelum($p['no'], $b, $kursor)) {
                    $selepas[] = $calon;
                }
            }
        }
        $pilih = $selepas ?: $semua;
        if (! $pilih) {
            return null;
        }
        if ($pertama) {
            return $pilih[0];
        }
        // Skor tertinggi; sama skor → yang terawal (ikut aliran borang)
        usort($pilih, fn ($a, $b) => [$b['skor'], $a['muka'], $a['y1']] <=> [$a['skor'], $b['muka'], $b['y1']]);

        return $pilih[0];
    }

    private function cariDalamBaris(array $halaman, array $label, string $teks): ?array
    {
        $b = $halaman[$label['muka']]['baris'][$label['baris']];
        $m = $this->padanBaris($b, TemplatBorangService::tokenTeks($teks), $label['x2']);

        return $m && $m['skor'] >= 0.99 ? $m + ['muka' => $label['muka'], 'baris' => $label['baris']] : null;
    }

    private function cukup(array $m, int $bilToken, float $min): bool
    {
        return $m['skor'] >= max($min, 0.6) || ($m['padan'] >= 2 && $m['skor'] >= $min) || ($bilToken === 1 && $m['padan'] === 1);
    }

    /**
     * Padanan berturutan token label dalam satu baris (boleh langkau satu perkataan templat atau
     * token label yang tiada). Pulangkan padanan terbaik: skor, bilangan padan, kotak span.
     */
    private function padanBaris(array $baris, array $label, float $xMin = -1): ?array
    {
        $t = $baris['token'];
        $terbaik = null;
        for ($s = 0, $n = count($t); $s < $n; $s++) {
            if ($baris['kata'][$t[$s][1]]['x1'] < $xMin || ! ($this->sama($label[0], $t[$s][0]) || (isset($label[1]) && $this->sama($label[1], $t[$s][0])))) {
                continue;
            }
            $padan = 0;
            $langkau = 0;
            $pertama = $akhir = null;
            for ($i = $s, $j = 0; $j < count($label) && $i < $n;) {
                if ($this->sama($label[$j], $t[$i][0])) {
                    $padan++;
                    $pertama ??= $i;
                    $akhir = $i;
                    $i++;
                    $j++;
                } elseif ($i + 1 < $n && $this->sama($label[$j], $t[$i + 1][0])) {
                    $i++;
                    $langkau++;
                } else {
                    $j++;
                }
            }
            // Perkataan templat yang dilangkau mengurangkan skor: "Jabatan / Bahagian" lebih sepadan
            // dengan baris "Jabatan/ Bahagian:" berbanding "Nama Ketua Jabatan/ Pengurus Bahagian"
            $skor = $padan / count($label) - 0.1 * $langkau;
            if ($padan && (! $terbaik || $skor > $terbaik['skor'])) {
                $kata = array_slice($baris['kata'], $t[$pertama][1], $t[$akhir][1] - $t[$pertama][1] + 1);
                $terbaik = ['skor' => $skor, 'padan' => $padan, 'x1' => min(array_column($kata, 'x1')), 'x2' => max(array_column($kata, 'x2')),
                    'y1' => min(array_column($kata, 'y1')), 'y2' => max(array_column($kata, 'y2')), 'kata_awal' => $t[$pertama][1], 'kata_akhir' => $t[$akhir][1]];
            }
        }

        return $terbaik;
    }

    /** Token sama (toleransi ralat OCR) atau satu mengandungi yang lain (>= 5 huruf: "sokong" ⊂ "disokong"). */
    private function sama(string $a, string $b): bool
    {
        return TemplatBorangService::sama($a, $b)
            || (min(strlen($a), strlen($b)) >= 5 && (str_contains($a, $b) || str_contains($b, $a)));
    }

    private function sebelum(int $muka, array $baris, array $kursor): bool
    {
        return $muka < $kursor['muka'] || ($muka === $kursor['muka'] && $baris['y2'] <= $kursor['y']);
    }

    /**
     * Teks pemisah antara dua pilihan sebaris: perkataan terakhir pilihan pertama (cth. "disokong/")
     * hingga sebelum perkataan pertama pilihan kedua. "/" dalam nama pilihan kedua sendiri
     * ("Bahagian/ Jabatan") tidak dikira.
     */
    private function teksAntara(array $a, array $b, array $halaman): string
    {
        $kata = $halaman[$a['muka']]['baris'][$a['baris']]['kata'];
        $dari = $a['kata_akhir'];
        $hingga = $b['kata_awal'] - 1;

        return $hingga >= $dari ? implode(' ', array_column(array_slice($kata, $dari, $hingga - $dari + 1), 'teks')) : '';
    }

    // ------------------------------------------------------------------ piksel

    /**
     * Garisan isian mendatar (>= 8% lebar, bermula tidak jauh dari $x) dalam julat y yang paling
     * hampir dengan $yRujukan (garis dasar label): garisan baris seterusnya tidak dipilih.
     */
    private function cariGaris(array $p, int $x, int $yDari, int $yHingga, float $yRujukan): ?array
    {
        $imej = $this->imej($p);
        $yHingga = min($yHingga, $p['h'] - 1);
        $min = (int) ($p['w'] * 0.08);
        $terbaik = null;
        $pertimbang = function (int $mula, int $akhir, int $y) use (&$terbaik, $min, $x, $p, $yRujukan) {
            if ($akhir - $mula < $min || $mula > $x + 0.35 * $p['w']) {
                return;
            }
            $jarak = abs($y - $yRujukan);
            if (! $terbaik || $jarak < $terbaik['jarak'] - 1 || (abs($jarak - $terbaik['jarak']) <= 1 && $akhir - $mula > $terbaik['x2'] - $terbaik['x1'])) {
                $terbaik = ['x1' => $mula, 'x2' => $akhir, 'y' => $y, 'jarak' => $jarak];
            }
        };
        for ($y = max(0, $yDari); $y <= $yHingga; $y++) {
            $mula = null;
            $akhir = 0;
            $jurang = 0;
            for ($i = max(0, $x); $i < $p['w']; $i++) {
                if ($this->gelap($imej, $i, $y)) {
                    $mula ??= $i;
                    $jurang = 0;
                    $akhir = $i;
                } elseif ($mula !== null && ++$jurang > 4) {
                    $pertimbang($mula, $akhir, $y);
                    $mula = null;
                }
            }
            if ($mula !== null) {
                $pertimbang($mula, $akhir, $y);
            }
        }

        return $terbaik;
    }

    /** Kedudukan x garisan menegak yang merentas >= 70% tinggi jalur. */
    private function garisMenegak(array $p, int $y1, int $y2): array
    {
        $imej = $this->imej($p);
        [$y1, $y2] = [max(0, $y1), min($y2, $p['h'] - 1)];
        $tinggi = max(1, $y2 - $y1);
        $x = [];
        for ($i = 0; $i < $p['w']; $i++) {
            $kira = 0;
            for ($y = $y1; $y <= $y2; $y++) {
                $kira += $this->gelap($imej, $i, $y) ? 1 : 0;
            }
            if ($kira >= 0.7 * $tinggi) {
                $x[] = $i;
            }
        }

        return $this->kelompok($x);
    }

    /** Kedudukan y garisan mendatar yang merentas >= 70% julat x. */
    private function garisMendatar(array $p, int $x1, int $x2, int $yDari, int $yHingga): array
    {
        $imej = $this->imej($p);
        [$x1, $x2] = [max(0, $x1), min($x2, $p['w'] - 1)];
        $yHingga = min($yHingga, $p['h'] - 1);
        $lebar = max(1, $x2 - $x1);
        $y = [];
        for ($j = $yDari; $j <= $yHingga; $j++) {
            $kira = 0;
            for ($i = $x1; $i <= $x2; $i += 2) {
                $kira += $this->gelap($imej, $i, $j) ? 2 : 0;
            }
            if ($kira >= 0.7 * $lebar) {
                $y[] = $j;
            }
        }

        return $this->kelompok($y);
    }

    /** Kumpul nilai bersebelahan (<= 3px) → nilai tengah setiap kumpulan. */
    private function kelompok(array $nilai): array
    {
        $hasil = [];
        $kumpulan = [];
        foreach ($nilai as $v) {
            if ($kumpulan && $v - end($kumpulan) > 3) {
                $hasil[] = (int) round(array_sum($kumpulan) / count($kumpulan));
                $kumpulan = [];
            }
            $kumpulan[] = $v;
        }
        if ($kumpulan) {
            $hasil[] = (int) round(array_sum($kumpulan) / count($kumpulan));
        }

        return $hasil;
    }

    private function gelap(\GdImage $imej, int $x, int $y): bool
    {
        $c = imagecolorat($imej, $x, $y);

        return ((($c >> 16) & 0xFF) * 0.299 + (($c >> 8) & 0xFF) * 0.587 + ($c & 0xFF) * 0.114) < self::GELAP;
    }

    private function kananKandungan(array $p): float
    {
        $x = [];
        foreach ($p['baris'] as $b) {
            foreach ($b['kata'] as $k) {
                $x[] = $k['x2'];
            }
        }

        return $x ? max($x) : $p['w'] * 0.92;
    }

    /** Kotak px → pecahan halaman (format KedudukanMedan). */
    private function pecahan(array $halaman, int $muka, array $k): array
    {
        $p = $halaman[$muka];
        $x1 = max(0, min($k['x1'], $p['w'] - 1));
        $y1 = max(0, min($k['y1'], $p['h'] - 1));

        return ['muka' => $muka, 'x' => round($x1 / $p['w'], 4), 'y' => round($y1 / $p['h'], 4),
            'w' => round(max(4, min($k['x2'], $p['w']) - $x1) / $p['w'], 4), 'h' => round(max(4, min($k['y2'], $p['h']) - $y1) / $p['h'], 4)];
    }

    // ------------------------------------------------------------------ Tesseract

    /**
     * Perkataan Tesseract dikumpul menjadi baris ikut kedudukan menegak (pengepala jadual dalam
     * sel berasingan menjadi satu baris).
     *
     * @return list<array{y1: float, y2: float, kata: list<array>, token: list<array{0: string, 1: int}>}>
     */
    private function barisTeks(string $imej): array
    {
        $folder = storage_path('app/private/ocr_sementara/cadang_'.Str::random(12));
        @mkdir($folder, 0775, true);
        try {
            $proses = new Process([config('services.ocr.tesseract_bin', 'tesseract'), $imej, $folder.DIRECTORY_SEPARATOR.'k',
                '-l', config('services.ocr.bahasa', 'eng'), 'tsv']);
            $proses->setTimeout(120);
            $proses->run();
            if (! $proses->isSuccessful()) {
                throw new \RuntimeException('Tesseract gagal: '.trim($proses->getErrorOutput()));
            }
            $kata = $this->uraiTsv($folder.DIRECTORY_SEPARATOR.'k.tsv');
        } finally {
            foreach (glob($folder.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($folder);
        }

        usort($kata, fn ($a, $b) => ($a['y1'] + $a['y2']) <=> ($b['y1'] + $b['y2']));
        $baris = [];
        foreach ($kata as $k) {
            $tengah = ($k['y1'] + $k['y2']) / 2;
            $tinggi = $k['y2'] - $k['y1'];
            $masuk = false;
            foreach ($baris as &$b) {
                if (abs($tengah - ($b['y1'] + $b['y2']) / 2) < 0.45 * max($tinggi, $b['y2'] - $b['y1'])) {
                    $b['kata'][] = $k;
                    $b['y1'] = min($b['y1'], $k['y1']);
                    $b['y2'] = max($b['y2'], $k['y2']);
                    $masuk = true;
                    break;
                }
            }
            unset($b);
            if (! $masuk) {
                $baris[] = ['y1' => $k['y1'], 'y2' => $k['y2'], 'kata' => [$k]];
            }
        }
        foreach ($baris as &$b) {
            usort($b['kata'], fn ($p, $q) => $p['x1'] <=> $q['x1']);
            $b['token'] = [];
            foreach ($b['kata'] as $i => $k) {
                foreach (TemplatBorangService::tokenTeks($k['teks']) as $tok) {
                    $b['token'][] = [$tok, $i];
                }
            }
        }
        unset($b);
        usort($baris, fn ($a, $b) => $a['y1'] <=> $b['y1']);

        return $baris;
    }

    private function uraiTsv(string $laluan): array
    {
        $baris = is_file($laluan) ? file($laluan, FILE_IGNORE_NEW_LINES) : [];
        if (! $baris) {
            return [];
        }
        $kepala = array_flip(explode("\t", array_shift($baris)));
        $kata = [];
        foreach ($baris as $b) {
            $l = explode("\t", $b);
            $teks = trim($l[$kepala['text']] ?? '');
            if ($teks === '' || (float) ($l[$kepala['conf']] ?? -1) < 0 || ! preg_match('/[\p{L}\p{N}]/u', $teks)) {
                continue;
            }
            $x = (int) $l[$kepala['left']];
            $y = (int) $l[$kepala['top']];
            $kata[] = ['teks' => $teks, 'x1' => $x, 'y1' => $y, 'x2' => $x + (int) $l[$kepala['width']], 'y2' => $y + (int) $l[$kepala['height']]];
        }

        return $kata;
    }
}
