<?php

namespace App\Services;

use App\Models\Dokumen;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\TemplatBorang;
use App\Support\JenisMedan;
use App\Support\MedanJadual;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Borang siap untuk dimuat turun (TB6): templat borang kosong kategori diisi dengan nilai
 * EAV dokumen pada kedudukan yang disusun pentadbir (App\Support\KedudukanMedan).
 *
 * Setiap muka surat templat dirasterkan sekali (150dpi, dicache ikut versi templat) dan
 * dijadikan latar FPDF; nilai ditulis sebagai teks PDF (tajam) berwarna dakwat biru,
 * pilihan ditanda ✓ (garisan vektor) atau digaris potong, dan cop status semasa (+ QR
 * pengesahan P6 bila Diluluskan) serta jalur "salinan terkawal" ditambah.
 *
 * Fon teras FPDF hanya Latin-1 (Windows-1252): teks Melayu/Inggeris tidak terjejas, aksara
 * lain (cth. Jawi) diganti hampiran.
 */
class BorangSiapService
{
    private const DPI = 150;

    private const DAKWAT = [25, 55, 150];

    /** Saiz fon lalai nilai (pt) bila kotak cukup tinggi; seragam supaya borang kelihatan kemas. */
    private const SAIZ_LALAI = 10;

    private const SAIZ_MIN = 6;

    /** Ketebalan garisan tanda ✓ (mm). */
    public const TEBAL_TANDA = 0.55;

    /** Kedudukan cop lalai (pecahan halaman) jika pentadbir belum meletakkannya: penjuru kanan atas. */
    private const COP_LALAI = ['muka' => 1, 'x' => 0.63, 'y' => 0.012, 'w' => 0.35, 'h' => 0.075];

    /**
     * Templat aktif terkini kategori yang sudah disusun dan failnya wujud. Null juga jika
     * lajur kedudukan_medan belum ditambah (SQL TB6 belum dijalankan pada DB itu, cth. DB
     * demo lama) supaya halaman Butiran Dokumen tidak ranap.
     */
    public function templatUntuk(?int $jenisDokumenId): ?TemplatBorang
    {
        if (! $jenisDokumenId) {
            return null;
        }

        try {
            return TemplatBorang::where('jenis_dokumen_id', $jenisDokumenId)->where('aktif', true)
                ->whereNotNull('kedudukan_medan')->orderByDesc('versi')->get()
                ->first(fn (TemplatBorang $t) => $t->disusun() && Storage::disk(TemplatBorangService::DISK)->exists($t->lokasi_fail));
        } catch (QueryException $e) {
            Log::warning('Borang siap: templat_borang.kedudukan_medan tidak dapat dibaca (SQL TB6 belum dijalankan?): '.$e->getMessage());

            return null;
        }
    }

    /**
     * Imej latar setiap muka surat templat (JPEG), dicache dalam disk local templat_cache/.
     *
     * @return list<array{fail: string, lebar: float, tinggi: float}> lebar/tinggi dalam mm
     */
    public function latar(TemplatBorang $templat): array
    {
        $local = Storage::disk('local');
        $folder = 'templat_cache/'.$templat->templat_borang_id.'_'.substr($templat->hash_sha256 ?: md5($templat->lokasi_fail), 0, 12);
        if ($local->exists("{$folder}/meta.json")) {
            $meta = json_decode((string) $local->get("{$folder}/meta.json"), true);
            if (is_array($meta) && $meta && collect($meta)->every(fn ($m) => $local->exists("{$folder}/{$m['fail']}"))) {
                return array_map(fn ($m) => ['fail' => $local->path("{$folder}/{$m['fail']}")] + $m, $meta);
            }
        }

        // Jana dalam folder sementara, kemudian tukar nama: dua permintaan serentak pada cache
        // kosong tidak menulis ganti JPEG yang sedang dibaca oleh permintaan lain
        $sementara = $folder.'_'.Str::random(8);
        $local->makeDirectory($sementara);
        try {
            $sumber = Storage::disk(TemplatBorangService::DISK)->path($templat->lokasi_fail);
            $mime = $templat->jenis_mime ?: (@mime_content_type($sumber) ?: '');
            $meta = $mime === 'application/pdf'
                ? $this->rasterPdf($sumber, $local->path($sementara))
                : $this->rasterImej($sumber, $local->path($sementara));
            $local->put("{$sementara}/meta.json", json_encode($meta));
            if (! $local->exists("{$folder}/meta.json")) {
                $local->deleteDirectory($folder);
                if (@rename($local->path($sementara), $local->path($folder))) {
                    $sementara = null;
                }
            }
        } finally {
            if ($sementara !== null) {
                $local->deleteDirectory($sementara);
            }
        }
        if (! $local->exists("{$folder}/meta.json")) {
            throw new \RuntimeException('Cache latar templat tidak dapat disediakan.');
        }

        return array_map(fn ($m) => ['fail' => $local->path("{$folder}/{$m['fail']}")] + $m, $meta);
    }

    /**
     * Padam cache latar templat ini, dan cache yatim bagi templat yang sudah tiada / dipadam
     * (folder templat_cache/{id}_{hash}). Dipanggil bila templat dipadam.
     */
    public function bersihCache(?TemplatBorang $templat = null): int
    {
        $local = Storage::disk('local');
        $hidup = TemplatBorang::pluck('templat_borang_id')->map(fn ($id) => (int) $id)->flip();
        $dipadam = 0;
        foreach ($local->directories('templat_cache') as $folder) {
            $id = (int) strtok(basename($folder), '_');
            if (($templat && $id === (int) $templat->templat_borang_id) || ! $hidup->has($id)) {
                $local->deleteDirectory($folder);
                $dipadam++;
            }
        }

        return $dipadam;
    }

    /**
     * Jana PDF borang siap.
     *
     * @param  array<int|string, string|null>  $nilai  dokumen_field_id => nilai_data
     * @param  array|null  $cop  daripada cop() (null = tanpa cop)
     * @param  array<string, string|null>  $asas  medan asas (KedudukanMedan::ASAS) => teks
     */
    public function jana(TemplatBorang $templat, array $nilai, ?array $cop, ?string $jalur, array $asas = []): string
    {
        $kedudukan = $templat->kedudukan_medan ?? [];
        $medanIkutId = ($templat->jenisDokumen?->fields ?? collect())->keyBy('dokumen_field_id');
        $muka = $this->latar($templat);
        $sementara = [];
        $lampiran = [];

        try {
            $pdf = new \FPDF('P', 'mm', 'A4');
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0);
            $pdf->SetCreator('Sistem Arkib Digital PKINK');
            $pdf->SetTitle($this->t('Borang siap'));

            foreach ($muka as $i => $m) {
                $no = $i + 1;
                $pdf->AddPage($m['lebar'] > $m['tinggi'] ? 'L' : 'P', [$m['lebar'], $m['tinggi']]);
                $pdf->Image($m['fail'], 0, 0, $m['lebar'], $m['tinggi'], 'JPG');
                $pdf->SetTextColor(...self::DAKWAT);
                $pdf->SetDrawColor(...self::DAKWAT);

                foreach ($kedudukan['medan'] ?? [] as $id => $k) {
                    $medan = $medanIkutId->get((int) $id);
                    $v = $nilai[$id] ?? $nilai[(int) $id] ?? null;
                    if (! $medan || $v === null || $v === '') {
                        continue;
                    }
                    if ($medan->jenis_data === 'jadual') {
                        if (($k['baris']['muka'] ?? 1) === $no && ($lebih = $this->jadual($pdf, $m, $medan, $k, $v))) {
                            $lampiran[] = [$medan, $lebih];
                        }

                        continue;
                    }
                    if (isset($k['kotak']) && $k['kotak']['muka'] === $no) {
                        $this->tulis($pdf, $this->mm($k['kotak'], $m), $this->teksNilai($medan, $v, $k['kotak']['tanpa_unit'] ?? false),
                            $medan->jenis_data === 'textarea', $k['kotak']['saiz'] ?? null);
                    }
                    if (isset($k['pilihan'])) {
                        $this->pilihan($pdf, $m, $no, $medan, $k, $v);
                    }
                }
                foreach ($kedudukan['asas'] ?? [] as $kunci => $kotak) {
                    if ($kotak['muka'] === $no && ($asas[$kunci] ?? '') !== '') {
                        $this->tulis($pdf, $this->mm($kotak, $m), (string) $asas[$kunci], $kunci === 'perkara', $kotak['saiz'] ?? null);
                    }
                }

                $kotakCop = $kedudukan['cop'] ?? self::COP_LALAI;
                if ($cop && ($kotakCop['muka'] ?? 1) === $no) {
                    $this->lukisCop($pdf, $this->mm($kotakCop, $m), $cop, $sementara);
                }
                if ($jalur !== null) {
                    $this->jalur($pdf, $m, $jalur);
                }
            }

            if ($lampiran) {
                $this->lampiran($pdf, $lampiran, $jalur);
            }

            return $pdf->Output('S');
        } finally {
            foreach ($sementara as $f) {
                @unlink($f);
            }
        }
    }

    /** Nilai dokumen untuk jana(): dokumen_field_id => nilai_data. */
    public function nilaiDokumen(Dokumen $dokumen): array
    {
        return $dokumen->data->pluck('nilai_data', 'dokumen_field_id')->all();
    }

    /** Medan asas dokumen untuk jana(). */
    public function asasDokumen(Dokumen $dokumen): array
    {
        return [
            'tarikh_dokumen' => $dokumen->tarikh_dokumen?->format('d/m/Y'),
            'no_rujukan' => $dokumen->no_rujukan,
            'perkara' => $dokumen->perkara,
        ];
    }

    public function asasContoh(): array
    {
        return ['tarikh_dokumen' => now()->format('d/m/Y'), 'no_rujukan' => 'CONTOH/2026/0001', 'perkara' => '[Perkara]'];
    }

    /** Nilai contoh bagi pratonton susunan: nama medan dalam kurungan, format sebenar bagi jenis lain. */
    public function nilaiContoh(JenisDokumen $jenis): array
    {
        $hasil = [];
        foreach ($jenis->fields as $medan) {
            $pilihan = array_values(array_filter($medan->pilihan ?? [], 'is_string'));
            $hasil[$medan->dokumen_field_id] = match ($medan->jenis_data) {
                'seksyen' => null,
                'nombor' => '3',
                'wang' => '1250.00',
                'tarikh' => now()->format('Y-m-d'),
                'tarikh_masa' => now()->format('Y-m-d H:i'),
                'masa' => '09:30',
                'emel' => 'contoh@pkink.gov.my',
                'telefon' => '012-345 6789',
                'url' => 'https://www.pkink.gov.my',
                'ya_tidak' => 'Ya',
                'dropdown', 'radio' => $pilihan[0] ?? null,
                'kotak_semak' => $pilihan ? json_encode(array_slice($pilihan, 0, 2), JSON_UNESCAPED_UNICODE) : null,
                'jadual' => $this->jadualContoh($medan),
                'textarea' => "[{$medan->nama_field}] Contoh teks panjang yang dibalut ke baris seterusnya dalam kotak.",
                default => "[{$medan->nama_field}]",
            };
        }

        return $hasil;
    }

    /**
     * Cop status semasa dokumen. QR pengesahan P6 hanya bila Diluluskan.
     *
     * @return array{label: string, warna: array{0:int,1:int,2:int}, baris: list<string>, qr: ?string, kod: ?string}
     */
    public function cop(Dokumen $dokumen): array
    {
        $borang = $dokumen->borang;
        $status = $borang?->status_permohonan;
        [$label, $warna] = match ($status) {
            'Diluluskan' => ['DILULUSKAN', [37, 140, 101]],
            'Disokong' => ['DISOKONG', [23, 105, 210]],
            'Dalam Proses' => ['DALAM PROSES', [180, 83, 9]],
            'Ditolak' => ['DITOLAK', [200, 35, 51]],
            default => ['DIISI', [104, 121, 141]],
        };
        $tarikh = fn ($t) => $t ? $t->format('d/m/Y') : '-';

        $baris = ['No. Rujukan: '.$dokumen->no_rujukan];
        if (! $borang) {
            $baris[] = 'Belum dihantar untuk kelulusan';
        } else {
            if ($borang->pemohon) {
                $baris[] = 'Dihantar: '.$borang->pemohon->nama_staff.' · '.$tarikh($borang->tarikh_permohonan);
            }
            if ($borang->penyokong && $borang->tarikh_sokongan) {
                $baris[] = 'Disokong: '.$borang->penyokong->nama_staff.' · '.$tarikh($borang->tarikh_sokongan);
            }
            if ($status === 'Diluluskan') {
                $baris[] = 'Diluluskan: '.($borang->pelulus->nama_staff ?? 'Diarkib terus').' · '.$tarikh($borang->tarikh_kelulusan);
            } elseif ($status === 'Ditolak') {
                $baris[] = 'Ditolak: '.($borang->penolak->nama_staff ?? '-').' · '.$tarikh($borang->tarikh_tolak);
            }
        }
        $kod = $borang?->kodPengesahan();

        return [
            'label' => $label,
            'warna' => $warna,
            'baris' => $baris,
            'qr' => $kod ? route('slip.sahkan', ['borang' => $borang->borang_id, 'kod' => $kod]) : null,
            'kod' => $kod,
        ];
    }

    // ------------------------------------------------------------------ lukisan

    /** Teks satu baris (dikecilkan supaya muat) atau berbilang baris (dibalut). */
    private function tulis(\FPDF $pdf, array $k, string $teks, bool $berbilang, ?float $saiz = null): void
    {
        $teks = $this->t(trim($teks));
        if ($teks === '') {
            return;
        }
        $pad = min(0.8, $k['w'] * 0.05);
        $lebar = max(1, $k['w'] - 2 * $pad);
        $mula = $saiz ?? min(self::SAIZ_LALAI, max(self::SAIZ_MIN, $k['h'] / 0.3528 * 0.85));

        for ($s = $mula; $s >= self::SAIZ_MIN; $s -= 0.5) {
            $pdf->SetFont('Helvetica', '', $s);
            if (! $berbilang && ! str_contains($teks, "\n") && $pdf->GetStringWidth($teks) <= $lebar) {
                $pdf->SetXY($k['x'] + $pad, $k['y']);
                $pdf->Cell($lebar, $k['h'], $teks, 0, 0, 'L');

                return;
            }
            $tinggiBaris = $s * 0.3528 * 1.15;
            $baris = $this->balut($pdf, $teks, $lebar);
            if (count($baris) * $tinggiBaris <= $k['h'] + 0.01) {
                $this->barisTeks($pdf, $k['x'] + $pad, $k['y'], $lebar, $tinggiBaris, $baris, $k['h']);

                return;
            }
        }

        // Tidak muat walaupun fon terkecil: tulis baris yang muat, baris akhir diakhiri "..."
        $pdf->SetFont('Helvetica', '', self::SAIZ_MIN);
        $tinggiBaris = self::SAIZ_MIN * 0.3528 * 1.15;
        $muat = max(1, (int) floor($k['h'] / $tinggiBaris));
        $baris = $this->balut($pdf, $teks, $lebar);
        if (count($baris) > $muat) {
            $baris = array_slice($baris, 0, $muat);
            $baris[$muat - 1] = rtrim($baris[$muat - 1]).'...';
        }
        $this->barisTeks($pdf, $k['x'] + $pad, $k['y'], $lebar, $tinggiBaris, $baris, max($k['h'], $tinggiBaris));
    }

    private function barisTeks(\FPDF $pdf, float $x, float $y, float $lebar, float $tinggiBaris, array $baris, float $tinggiKotak): void
    {
        // Satu baris dalam kotak tinggi: tengah menegak; berbilang baris: mula dari atas
        $mula = count($baris) === 1 ? $y + ($tinggiKotak - $tinggiBaris) / 2 : $y;
        foreach ($baris as $i => $b) {
            $pdf->SetXY($x, $mula + $i * $tinggiBaris);
            $pdf->Cell($lebar, $tinggiBaris, $b, 0, 0, 'L');
        }
    }

    /** Balut teks (sudah Windows-1252) ikut lebar; perkataan terlalu panjang dipecah ikut aksara. */
    private function balut(\FPDF $pdf, string $teks, float $lebar): array
    {
        $hasil = [];
        foreach (preg_split('/\r\n|\r|\n/', $teks) as $perenggan) {
            $baris = '';
            foreach (preg_split('/ +/', $perenggan) as $kata) {
                while ($kata !== '' && $pdf->GetStringWidth($kata) > $lebar) {
                    if ($baris !== '') {
                        $hasil[] = $baris;
                        $baris = '';
                    }
                    $n = strlen($kata);
                    while ($n > 1 && $pdf->GetStringWidth(substr($kata, 0, $n)) > $lebar) {
                        $n--;
                    }
                    $hasil[] = substr($kata, 0, $n);
                    $kata = substr($kata, $n);
                }
                $cuba = $baris === '' ? $kata : "{$baris} {$kata}";
                if ($baris !== '' && $pdf->GetStringWidth($cuba) > $lebar) {
                    $hasil[] = $baris;
                    $baris = $kata;
                } else {
                    $baris = $cuba;
                }
            }
            $hasil[] = $baris;
        }

        return $hasil;
    }

    /** Radio/dropdown/kotak semak/ya-tidak: ✓ pada pilihan dipilih, atau garis potong pilihan lain. */
    private function pilihan(\FPDF $pdf, array $muka, int $no, DokumenField $medan, array $k, string $nilai): void
    {
        [$dipilih, $teksLain] = $this->pilihanDipilih($medan, $nilai);
        // Nilai yang tiada kotak pilihan (cth. Lain-lain tanpa kotak): jangan potong semua pilihan
        if (! $dipilih || ! array_intersect($dipilih, array_map('strval', array_keys($k['pilihan'])))) {
            return;
        }
        foreach ($k['pilihan'] as $pilihan => $kotak) {
            if ($kotak['muka'] !== $no) {
                continue;
            }
            $b = $this->mm($kotak, $muka);
            $terpilih = in_array((string) $pilihan, $dipilih, true);
            if (($k['gaya'] ?? 'tanda') === 'potong') {
                if (! $terpilih) {
                    $pdf->SetLineWidth(0.45);
                    $pdf->Line($b['x'], $b['y'] + $b['h'] / 2, $b['x'] + $b['w'], $b['y'] + $b['h'] / 2);
                }
            } elseif ($terpilih) {
                $this->tanda($pdf, $b);
            }
            if ($terpilih && $pilihan === JenisMedan::NILAI_LAIN && $teksLain !== '') {
                // Teks "Lain-lain (nyatakan)" ditulis dalam baki kotak selepas tanda
                $ruang = min($b['w'] * 0.4, $b['h'] * 1.1);
                $this->tulis($pdf, ['x' => $b['x'] + $ruang, 'y' => $b['y'], 'w' => max(1, $b['w'] - $ruang), 'h' => $b['h']], $teksLain, false);
            }
        }
    }

    /** @return array{0: list<string>, 1: string} pilihan dipilih (Lain-lain = __lain__) & teks Lain-lain */
    private function pilihanDipilih(DokumenField $medan, string $nilai): array
    {
        $senarai = $medan->jenis_data === 'kotak_semak' ? JenisMedan::senaraiNilai($nilai) : [$nilai];
        $dipilih = [];
        $teksLain = '';
        foreach ($senarai as $v) {
            if (str_starts_with($v, JenisMedan::AWALAN_LAIN)) {
                $dipilih[] = JenisMedan::NILAI_LAIN;
                $teksLain = substr($v, strlen(JenisMedan::AWALAN_LAIN));
            } else {
                $dipilih[] = $v;
            }
        }

        return [$dipilih, $teksLain];
    }

    /**
     * ✓ di hujung kiri kotak, tengah menegak. Dilukis sebagai garisan vektor (bukan aksara
     * ZapfDingbats) supaya kelihatan dalam semua pemapar PDF, termasuk yang tiada fon itu.
     */
    private function tanda(\FPDF $pdf, array $b): void
    {
        $saiz = max(2.5, min(6, $b['h'] * 0.9, $b['w']));
        $x = $b['x'] + max(0, min($b['w'], $saiz * 1.2) - $saiz) / 2;
        $y = $b['y'] + ($b['h'] - $saiz) / 2;
        $pdf->SetLineWidth(self::TEBAL_TANDA);
        $pdf->Line($x + 0.1 * $saiz, $y + 0.55 * $saiz, $x + 0.4 * $saiz, $y + 0.85 * $saiz);
        $pdf->Line($x + 0.4 * $saiz, $y + 0.85 * $saiz, $x + 0.95 * $saiz, $y + 0.15 * $saiz);
    }

    /**
     * Jadual: baris ke-i pada y = baris.y + i × baris.h, lajur ikut x/w setiap lajur.
     * Baris melebihi `bilangan` baris pada borang dipulangkan untuk lampiran.
     */
    private function jadual(\FPDF $pdf, array $muka, DokumenField $medan, array $k, string $nilai): array
    {
        $semua = array_values(array_filter(MedanJadual::untukBorang($medan, $nilai), fn ($b) => is_array($b)));
        // Baris yang benar-benar muat sebelum tepi bawah halaman; selebihnya ke lampiran
        $muatHalaman = (int) floor((1 - $k['baris']['y']) / max(0.001, $k['baris']['h']) + 0.001);
        $bilangan = max(1, min((int) ($k['baris']['bilangan'] ?? count($semua)), $muatHalaman));
        $lajurIkutKod = collect(MedanJadual::lajur($medan))->keyBy('kod');

        foreach (array_slice($semua, 0, $bilangan) as $i => $baris) {
            foreach ($k['lajur'] as $kod => $kl) {
                $lajur = $lajurIkutKod->get($kod);
                $sel = $baris[$kod] ?? null;
                if (! $lajur || $sel === null || $sel === '' || ! is_scalar($sel)) {
                    continue;
                }
                $kotak = $this->mm(['x' => $kl['x'], 'w' => $kl['w'], 'y' => $k['baris']['y'] + $i * $k['baris']['h'], 'h' => $k['baris']['h']], $muka);
                if (($lajur['jenis'] ?? 'teks') === 'ya_tidak') {
                    if ($sel === 'Ya') {
                        $this->tanda($pdf, ['x' => $kotak['x'] + max(0, ($kotak['w'] - $kotak['h']) / 2), 'w' => min($kotak['w'], $kotak['h'])] + $kotak);
                    }

                    continue;
                }
                $this->tulis($pdf, $kotak, $this->teksNilai(MedanJadual::medanLajur($lajur), (string) $sel, $kl['tanpa_unit'] ?? false), true, $kl['saiz'] ?? null);
            }
        }

        return array_slice($semua, $bilangan);
    }

    /** Muka surat lampiran (A4, berbilang jika perlu) bagi baris jadual yang tidak muat atas borang. */
    private function lampiran(\FPDF $pdf, array $lampiran, ?string $jalur): void
    {
        $a4 = ['lebar' => 210.0, 'tinggi' => 297.0];
        $halamanBaharu = function () use ($pdf, $jalur, $a4) {
            $pdf->AddPage('P', 'A4');
            if ($jalur !== null) {
                $this->jalur($pdf, $a4, $jalur);
            }
            $pdf->SetTextColor(28, 48, 72);
            $pdf->SetXY(18, 18);
        };
        // Pastikan ruang $tinggi mm sebelum jalur bawah; jika tidak, sambung di muka surat baharu
        $ruang = function (float $tinggi) use ($pdf, $halamanBaharu) {
            if ($pdf->GetY() + $tinggi > 297.0 - 14) {
                $halamanBaharu();
            }
        };

        $halamanBaharu();
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->Cell(0, 8, $this->t('LAMPIRAN: sambungan jadual'), 0, 1);
        foreach ($lampiran as [$medan, $baris]) {
            $ruang(14);
            $pdf->SetX(18);
            $pdf->SetFont('Helvetica', 'B', 10.5);
            $pdf->Cell(0, 8, $this->t($medan->nama_field), 0, 1);
            $lajur = MedanJadual::lajur($medan);
            foreach ($baris as $b) {
                $teks = collect($lajur)->map(function ($l) use ($b) {
                    $v = $b[$l['kod']] ?? null;

                    return $v === null || $v === '' || ! is_scalar($v) ? null : $l['nama'].': '.$this->teksNilai(MedanJadual::medanLajur($l), (string) $v);
                })->filter()->implode('; ');
                $label = isset($b[MedanJadual::KUNCI_BARIS]) && is_scalar($b[MedanJadual::KUNCI_BARIS]) ? $b[MedanJadual::KUNCI_BARIS].': ' : '';
                $pdf->SetFont('Helvetica', '', 9.5);
                foreach ($this->balut($pdf, $this->t('- '.$label.$teks), 170) as $i => $bt) {
                    $ruang(5);
                    $pdf->SetFont('Helvetica', '', 9.5);
                    $pdf->SetXY($i ? 25 : 22, $pdf->GetY());
                    $pdf->Cell(170, 5, $bt, 0, 1);
                }
            }
        }
    }

    /** Cop status: kotak berwarna ikut status, butiran tindakan, QR + kod bila Diluluskan. */
    private function lukisCop(\FPDF $pdf, array $k, array $cop, array &$sementara): void
    {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(...$cop['warna']);
        $pdf->SetLineWidth(0.6);
        $pdf->Rect($k['x'], $k['y'], $k['w'], $k['h'], 'DF');

        $pad = 1.5;
        $lebarTeks = $k['w'] - 2 * $pad;
        if ($cop['qr'] && ($qr = SlipKelulusanService::qrPng($cop['qr']))) {
            $sementara[] = $qr;
            $saizQr = max(8, min($k['h'] - 2 * $pad, 26));
            $pdf->Image($qr, $k['x'] + $k['w'] - $pad - $saizQr, $k['y'] + $pad, $saizQr, $saizQr, 'PNG');
            $lebarTeks -= $saizQr + $pad;
        }

        $baris = $cop['baris'];
        if ($cop['kod']) {
            $baris[] = 'Kod sah: '.$cop['kod'];
        }
        $tinggiDalam = $k['h'] - 2 * $pad;
        // Cari saiz fon terbesar yang memuatkan tajuk + semua baris
        for ($s = 7.5; $s >= 4.5; $s -= 0.5) {
            $tajuk = min(14, $s * 1.7);
            $pdf->SetFont('Helvetica', 'B', $tajuk);
            $muat = $pdf->GetStringWidth($this->t($cop['label'])) <= $lebarTeks;
            $pdf->SetFont('Helvetica', '', $s);
            foreach ($baris as $b) {
                $muat = $muat && $pdf->GetStringWidth($this->t($b)) <= $lebarTeks;
            }
            if ($muat && $tajuk * 0.3528 * 1.2 + count($baris) * $s * 0.3528 * 1.25 <= $tinggiDalam) {
                break;
            }
        }
        $s = max(4.5, $s);
        $tajuk = min(14, $s * 1.7);

        $y = $k['y'] + $pad;
        $pdf->SetTextColor(...$cop['warna']);
        $pdf->SetFont('Helvetica', 'B', $tajuk);
        $pdf->SetXY($k['x'] + $pad, $y);
        $pdf->Cell($lebarTeks, $tajuk * 0.3528 * 1.2, $this->t($cop['label']), 0, 0, 'L');
        $y += $tajuk * 0.3528 * 1.2;
        $pdf->SetTextColor(28, 48, 72);
        $pdf->SetFont('Helvetica', '', $s);
        foreach ($baris as $b) {
            $pdf->SetXY($k['x'] + $pad, $y);
            $pdf->Cell($lebarTeks, $s * 0.3528 * 1.25, $this->t($b), 0, 0, 'L');
            $y += $s * 0.3528 * 1.25;
        }
        $pdf->SetTextColor(...self::DAKWAT);
        $pdf->SetDrawColor(...self::DAKWAT);
    }

    /** Jalur "salinan terkawal" di bawah setiap muka surat. */
    private function jalur(\FPDF $pdf, array $muka, string $teks): void
    {
        $tinggi = 4.5;
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0, $muka['tinggi'] - $tinggi, $muka['lebar'], $tinggi, 'F');
        $pdf->SetTextColor(120, 20, 20);
        $pdf->SetFont('Helvetica', '', 6.5);
        $pdf->SetXY(4, $muka['tinggi'] - $tinggi);
        $pdf->Cell($muka['lebar'] - 8, $tinggi, $this->t($teks), 0, 0, 'C');
        $pdf->SetTextColor(...self::DAKWAT);
    }

    // ------------------------------------------------------------------ bantuan

    /** Kotak pecahan → mm atas muka surat. */
    private function mm(array $k, array $muka): array
    {
        return [
            'x' => $k['x'] * $muka['lebar'], 'y' => $k['y'] * $muka['tinggi'],
            'w' => $k['w'] * $muka['lebar'], 'h' => $k['h'] * $muka['tinggi'],
        ];
    }

    /**
     * Nilai tersimpan → teks paparan atas borang (seperti JenisMedan::papar, tanpa HTML).
     * $tanpaUnit: medan wang tanpa "RM" (borang sudah mencetaknya sebelum ruang isian).
     */
    public function teksNilai(DokumenField $medan, string $nilai, bool $tanpaUnit = false): string
    {
        return match ($medan->jenis_data) {
            'wang' => is_numeric($nilai) ? ($tanpaUnit ? '' : 'RM ').number_format((float) $nilai, 2) : $nilai,
            'tarikh' => $this->formatTarikh($nilai, 'd/m/Y'),
            'tarikh_masa' => $this->formatTarikh($nilai, 'd/m/Y, H:i'),
            'kotak_semak' => implode(', ', JenisMedan::senaraiNilai($nilai)),
            default => $nilai,
        };
    }

    private function formatTarikh(string $nilai, string $format): string
    {
        try {
            return Carbon::parse($nilai)->format($format);
        } catch (\Throwable) {
            return $nilai;
        }
    }

    private function jadualContoh(DokumenField $medan): ?string
    {
        $lajur = MedanJadual::lajur($medan);
        if (! $lajur) {
            return null;
        }
        $tetap = MedanJadual::barisTetap($medan);
        $baris = [];
        foreach ($tetap ?: [1, 2] as $i => $label) {
            $b = $tetap ? [MedanJadual::KUNCI_BARIS => $label] : [];
            foreach ($lajur as $l) {
                $b[$l['kod']] = match ($l['jenis'] ?? 'teks') {
                    'ya_tidak' => 'Ya',
                    'nombor' => (string) ($i + 1),
                    'wang' => '100.00',
                    'tarikh' => now()->format('Y-m-d'),
                    'dropdown' => $l['pilihan'][0] ?? null,
                    default => "[{$l['nama']}]",
                };
            }
            $baris[] = $b;
        }

        return json_encode($baris, JSON_UNESCAPED_UNICODE);
    }

    /** Teks UTF-8 → Windows-1252 untuk fon teras FPDF. */
    private function t(string $teks): string
    {
        return (string) (@iconv('UTF-8', 'windows-1252//TRANSLIT', $teks) ?: $teks);
    }

    /** @return list<array{fail: string, lebar: float, tinggi: float}> */
    private function rasterPdf(string $sumber, string $folder): array
    {
        $proses = new Process([config('services.ocr.pdftoppm_bin', 'pdftoppm'), '-jpeg', '-jpegopt', 'quality=85',
            '-r', (string) self::DPI, '-l', '20', $sumber, $folder.DIRECTORY_SEPARATOR.'m']);
        $proses->setTimeout((int) config('services.ocr.had_masa', 300));
        $proses->run();
        $fail = glob($folder.DIRECTORY_SEPARATOR.'m*.jpg') ?: [];
        natsort($fail);
        if (! $proses->isSuccessful() || ! $fail) {
            throw new \RuntimeException('Templat PDF tidak dapat dirasterkan: '.trim($proses->getErrorOutput()));
        }

        return array_values(array_map(function ($f) {
            [$w, $h] = getimagesize($f);

            return ['fail' => basename($f), 'lebar' => round($w / self::DPI * 25.4, 2), 'tinggi' => round($h / self::DPI * 25.4, 2)];
        }, $fail));
    }

    /**
     * Templat imej: diratakan ke JPEG dan dikecilkan kepada lebar setara A4 150dpi (1240px)
     * supaya imbasan 600dpi tidak menghabiskan memori GD. Lebar dianggap A4 (210mm).
     */
    private function rasterImej(string $sumber, string $folder): array
    {
        $saiz = @getimagesize($sumber);
        if (! $saiz || $saiz[0] < 1 || $saiz[1] < 1) {
            throw new \RuntimeException('Templat imej tidak dapat dibaca.');
        }
        [$w, $h] = $saiz;
        // ~4 bait/piksel + imej rata; tolak imej yang terlalu besar sebelum dinyahkod
        if ($w * $h > 60_000_000) {
            throw new \RuntimeException("Templat imej terlalu besar ({$w}x{$h} piksel).");
        }
        // Imbasan A4 600dpi ≈ 35 juta piksel ≈ 140MB dalam GD: lebih daripada memory_limit lalai (128MB).
        // Dinaikkan sementara bagi penyahkodan sekali ini sahaja (hasilnya dicache).
        $hadAsal = ini_get('memory_limit');
        if ($hadAsal !== '-1' && $this->bait((string) $hadAsal) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
        $imej = @imagecreatefromstring((string) file_get_contents($sumber));
        if ($imej === false) {
            throw new \RuntimeException('Templat imej tidak dapat dibaca.');
        }
        $lebar = min($w, (int) round(210 / 25.4 * self::DPI));
        $tinggi = max(1, (int) round($h * $lebar / $w));
        $rata = imagecreatetruecolor($lebar, $tinggi);
        imagefill($rata, 0, 0, imagecolorallocate($rata, 255, 255, 255));
        imagecopyresampled($rata, $imej, 0, 0, 0, 0, $lebar, $tinggi, $w, $h);
        imagedestroy($imej);
        imagejpeg($rata, $folder.DIRECTORY_SEPARATOR.'m-1.jpg', 85);
        imagedestroy($rata);

        return [['fail' => 'm-1.jpg', 'lebar' => 210.0, 'tinggi' => round(210 * $h / $w, 2)]];
    }

    /** Nilai memory_limit ("128M", "1G", "-1") → bait. */
    private function bait(string $nilai): int
    {
        $n = (int) $nilai;

        return match (strtoupper(substr(trim($nilai), -1))) {
            'G' => $n * 1024 ** 3,
            'M' => $n * 1024 ** 2,
            'K' => $n * 1024,
            default => $n,
        };
    }
}
