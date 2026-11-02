<?php

namespace App\Services;

use App\Jobs\TemplatOcrJob;
use App\Models\JenisDokumen;
use App\Models\TemplatBorang;
use App\Support\AntrianOcr;
use App\Support\Demo;
use App\Support\TandaVisual;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Templat borang kosong (Fasa TB): bezakan teks BERCETAK borang dengan tulisan PENGISI.
 *
 * Teks OCR templat (borang kosong) dan teks OCR imbasan dijajarkan perkataan demi perkataan
 * (LCS ikut susunan, bertoleransi ralat OCR). Perkataan imbasan yang sepadan dengan templat
 * ialah teks bercetak; selebihnya ialah isian dan dibalut «…» dalam teks yang dihantar kepada
 * AI. Kategori tanpa templat aktif tidak terjejas.
 */
class TemplatBorangService
{
    public const DISK = 'templat';

    /** Had sel jadual LCS (token imbasan × token templat) supaya memori/masa terkawal. */
    private const MAKS_SEL = 1_500_000;

    /** Padanan minimum (pecahan token templat yang dijumpai) untuk dianggap borang yang sama. */
    public const PADANAN_MIN = 0.5;

    /**
     * Daftar fail (sudah dalam disk templat) sebagai versi baharu templat kategori, kemudian
     * jalankan OCR templat (queue OCR jika worker berjalan, jika tidak segerak).
     */
    public function daftar(JenisDokumen $jenis, string $lokasi, string $namaAsal, ?int $userId, ?string $catatan = null): TemplatBorang
    {
        $disk = Storage::disk(self::DISK);
        $laluan = $disk->path($lokasi);

        // TB6: versi baharu bermula dengan susunan versi sebelumnya sebagai CADANGAN (belum disahkan):
        // borang siap terus menggunakan versi lama sehingga pentadbir menyemak & menyimpan susunan ini
        $sebelum = TemplatBorang::where('jenis_dokumen_id', $jenis->jenis_dokumen_id)->orderByDesc('versi')->first();
        $warisan = $sebelum?->kedudukan_medan ? ['kedudukan_medan' => ['disahkan' => false] + $sebelum->kedudukan_medan] : [];

        $templat = TemplatBorang::create($warisan + [
            'jenis_dokumen_id' => $jenis->jenis_dokumen_id,
            'versi' => (int) TemplatBorang::withTrashed()->where('jenis_dokumen_id', $jenis->jenis_dokumen_id)->max('versi') + 1,
            'nama_asal' => $namaAsal,
            'lokasi_fail' => $lokasi,
            'saiz_fail' => @filesize($laluan) ?: null,
            'jenis_mime' => @mime_content_type($laluan) ?: null,
            'hash_sha256' => @hash_file('sha256', $laluan) ?: null,
            'status_ocr' => 'Pending',
            'aktif' => true,
            'catatan' => $catatan,
            'created_by' => $userId,
        ]);

        $this->mulakanOcr($templat);

        return $templat->fresh();
    }

    /** Simpan fail yang dimuat naik ke Templat/{KOD}/ (nama rawak) dan daftar sebagai versi baharu. */
    public function simpanMuatNaik(JenisDokumen $jenis, UploadedFile $fail, ?int $userId, ?string $catatan = null): TemplatBorang
    {
        $folder = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $jenis->kod_dokumen) ?: 'kategori';
        $lokasi = $fail->storeAs($folder, Str::random(24).'.'.$fail->extension(), self::DISK);
        if ($lokasi === false) {
            throw new \RuntimeException('Fail templat tidak dapat disimpan dalam folder Templat.');
        }

        return $this->daftar($jenis, $lokasi, $fail->getClientOriginalName(), $userId, $catatan);
    }

    public function mulakanOcr(TemplatBorang $templat): void
    {
        if (AntrianOcr::workerHidup()) {
            try {
                $templat->update(['status_ocr' => 'Dalam Giliran']);
                AntrianOcr::hantar(new TemplatOcrJob($templat->templat_borang_id, Demo::mod()));

                return;
            } catch (\Throwable $e) {
                Log::warning('Queue OCR: OCR templat gagal dihantar, diproses segerak: '.$e->getMessage());
            }
        }
        set_time_limit((int) config('services.ocr.had_masa', 300));
        $this->jalankanOcr($templat);
    }

    /**
     * OCR templat tanpa tanda visual (penanda [□] dalam borang kosong bukan teks bercetak) dan
     * tanpa model penglihatan (borang kosong bertaip; Tesseract sentiasa digunakan).
     */
    public function jalankanOcr(TemplatBorang $templat): void
    {
        $asal = ['services.ocr.tanda_visual' => config('services.ocr.tanda_visual'), 'services.ocr.vlm_aktif' => config('services.ocr.vlm_aktif')];
        config(['services.ocr.tanda_visual' => false, 'services.ocr.vlm_aktif' => false]);
        try {
            $templat->update(['status_ocr' => 'Sedang Diproses']);
            $hasil = app(OcrService::class)->proses($templat->lokasi_fail, false, self::DISK);
            $templat->update([
                'ocr_json' => ['teks' => $hasil['teks'], 'skor' => $hasil['skor'], 'token' => self::tokenTeks($hasil['teks'])],
                'bilangan_muka' => count($hasil['enjin'] ?? []) ?: null,
                'status_ocr' => $hasil['teks'] !== '' ? 'Selesai' : 'Tiada Teks Dikesan',
            ]);
        } catch (\Throwable $e) {
            Log::warning("OCR templat {$templat->templat_borang_id} gagal: ".$e->getMessage());
            $templat->update(['status_ocr' => 'Ralat']);
        } finally {
            config($asal);
        }
    }

    /** Token perbandingan: huruf kecil, dipisah pada setiap aksara bukan huruf/angka. */
    public static function tokenTeks(string $teks): array
    {
        $teks = preg_replace(TandaVisual::CORAK_PENANDA, ' ', $teks) ?? $teks;

        return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($teks), -1, PREG_SPLIT_NO_EMPTY);
    }

    /**
     * Teks untuk AI: isian pengisi dibalut «…» berdasarkan templat aktif kategori yang paling
     * sepadan. Tiada templat / padanan rendah (borang lain) → teks asal tanpa perubahan.
     *
     * @return array{teks: string, padanan: float|null, templat: TemplatBorang|null}
     */
    public function tandaIsian(string $teks, ?JenisDokumen $jenis): array
    {
        $hasil = ['teks' => $teks, 'padanan' => null, 'templat' => null];
        if (! $jenis || trim($teks) === '') {
            return $hasil;
        }
        $terbaik = null;
        foreach ($this->templatAktif($jenis->jenis_dokumen_id) as $templat) {
            $jajaran = $this->jajar($teks, $templat->token());
            if ($jajaran && (! $terbaik || $jajaran['padanan'] > $terbaik[1]['padanan'])) {
                $terbaik = [$templat, $jajaran];
            }
        }
        if (! $terbaik) {
            return $hasil;
        }
        [$templat, $jajaran] = $terbaik;
        $hasil['padanan'] = $jajaran['padanan'];
        if ($jajaran['padanan'] < self::PADANAN_MIN) {
            return $hasil; // borang lain / versi tidak dikenali: jangan tanda apa-apa
        }

        return ['teks' => $jajaran['teks'], 'padanan' => $jajaran['padanan'], 'templat' => $templat];
    }

    /**
     * TB5: kategori yang templatnya paling sepadan dengan teks imbasan.
     *
     * @return array{jenis_dokumen_id: int, padanan: float}|null
     */
    public function padanKategori(string $teks): ?array
    {
        $terbaik = null;
        $tokenImbasan = self::tokenTeks($teks);
        $setImbasan = array_flip($tokenImbasan);
        $templatAktif = TemplatBorang::with('jenisDokumen')->where('aktif', true)->where('status_ocr', 'Selesai')->get()
            ->reject(fn ($t) => ! $t->jenisDokumen || $t->jenisDokumen->trashed());
        foreach ($templatAktif as $templat) {
            // Tapis pantas O(n+m): had atas padanan = pecahan token templat yang wujud dalam imbasan
            $tokenTemplat = $templat->token();
            $hadAtas = $tokenTemplat ? count(array_filter($tokenTemplat, fn ($t) => isset($setImbasan[$t]))) / count($tokenTemplat) : 0;
            if ($hadAtas < self::PADANAN_MIN * 0.8 || ($terbaik && $hadAtas <= $terbaik['padanan'])) {
                continue;
            }
            $padanan = $this->nisbahTemplat($tokenImbasan, $tokenTemplat);
            if ($padanan !== null && (! $terbaik || $padanan > $terbaik['padanan'])) {
                $terbaik = ['jenis_dokumen_id' => (int) $templat->jenis_dokumen_id, 'padanan' => $padanan];
            }
        }

        return $terbaik && $terbaik['padanan'] >= self::PADANAN_MIN ? $terbaik : null;
    }

    /** @return Collection<int, TemplatBorang> */
    private function templatAktif(int $jenisDokumenId)
    {
        return TemplatBorang::where('jenis_dokumen_id', $jenisDokumenId)->where('aktif', true)
            ->where('status_ocr', 'Selesai')->orderByDesc('versi')->get();
    }

    /** Pecahan token templat yang dijumpai dalam imbasan (ikut susunan). */
    private function nisbahTemplat(array $tokenImbasan, array $tokenTemplat): ?float
    {
        if (! $tokenTemplat || ! $tokenImbasan || count($tokenImbasan) * count($tokenTemplat) > self::MAKS_SEL) {
            return null;
        }
        [$sepadan] = self::lcs($tokenImbasan, $tokenTemplat);

        return round(count($sepadan) / count($tokenTemplat), 3);
    }

    /**
     * Jajarkan teks imbasan dengan token templat; balut perkataan isian dengan «…».
     *
     * @return array{teks: string, padanan: float}|null
     */
    private function jajar(string $teks, array $tokenTemplat): ?array
    {
        // Senarai perkataan imbasan (ikut baris) dan token setiap perkataan
        $baris = preg_split('/\R/u', $teks);
        $perkataan = [];   // [baris, teks asal, [indeks token...]]
        $token = [];
        foreach ($baris as $b => $isi) {
            foreach (preg_split('/[ \t]+/u', trim($isi), -1, PREG_SPLIT_NO_EMPTY) as $p) {
                $indeks = [];
                $bersih = preg_match(TandaVisual::CORAK_PENANDA, $p) ? '' : $p;
                foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($bersih), -1, PREG_SPLIT_NO_EMPTY) as $t) {
                    $indeks[] = count($token);
                    $token[] = $t;
                }
                $perkataan[] = [$b, $p, $indeks];
            }
        }
        if (! $token || ! $tokenTemplat) {
            return null;
        }
        if (count($token) * count($tokenTemplat) > self::MAKS_SEL) {
            Log::info('Templat borang: dokumen terlalu besar untuk dibandingkan ('.count($token).' x '.count($tokenTemplat).' token); isian tidak ditanda.');

            return null;
        }

        [$sepadanTemplat, $sepadanImbasan] = self::lcs($token, $tokenTemplat);
        $sepadanImbasan = array_flip($sepadanImbasan);

        // Bina semula baris; perkataan isian berturut-turut dibalut sekali «…»
        $keluar = array_fill(0, count($baris), []);
        $isianTerbuka = array_fill(0, count($baris), false);
        foreach ($perkataan as [$b, $p, $indeks]) {
            $isian = false;
            foreach ($indeks as $i) {
                if (! isset($sepadanImbasan[$i])) {
                    $isian = true;
                    break;
                }
            }
            if ($isian && ! $isianTerbuka[$b]) {
                $keluar[$b][] = '«'.$p;
                $isianTerbuka[$b] = true;
            } elseif ($isian) {
                $keluar[$b][] = $p;
            } else {
                if ($isianTerbuka[$b]) {
                    $keluar[$b][count($keluar[$b]) - 1] .= '»';
                    $isianTerbuka[$b] = false;
                }
                $keluar[$b][] = $p;
            }
        }
        foreach ($keluar as $b => $kata) {
            if ($isianTerbuka[$b] && $kata) {
                $keluar[$b][count($kata) - 1] .= '»';
            }
        }

        return [
            'teks' => implode("\n", array_map(fn ($k) => implode(' ', $k), $keluar)),
            'padanan' => round(count($sepadanTemplat) / count($tokenTemplat), 3),
        ];
    }

    /**
     * LCS dua senarai token dengan padanan bertoleransi ralat OCR.
     *
     * @return array{0: list<int>, 1: list<int>} indeks token templat & imbasan yang sepadan
     */
    private static function lcs(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        // Jadual panjang LCS dalam satu tatasusunan rata (baris n+1, lajur m+1)
        $l = array_fill(0, ($n + 1) * ($m + 1), 0);
        $w = $m + 1;
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $l[$i * $w + $j] = self::sama($a[$i], $b[$j])
                    ? $l[($i + 1) * $w + $j + 1] + 1
                    : max($l[($i + 1) * $w + $j], $l[$i * $w + $j + 1]);
            }
        }
        $sepadanB = [];
        $sepadanA = [];
        // Jejak balik: jika token imbasan i boleh dilangkau tanpa memendekkan LCS, langkau ia.
        // Perkataan bercetak dipadankan dengan kejadian KEMUDIAN, jadi isian yang kebetulan sama
        // dengan perkataan bercetak seterusnya ("Bilangan unit: 3" lalu "3. PENGESAHAN";
        // "Jawatan: Pegawai" lalu "Pegawai Penyokong") kekal sebagai isian.
        for ($i = 0, $j = 0; $i < $n && $j < $m;) {
            if ($l[($i + 1) * $w + $j] === $l[$i * $w + $j]) {
                $i++;
            } elseif (self::sama($a[$i], $b[$j]) && $l[$i * $w + $j] === $l[($i + 1) * $w + $j + 1] + 1) {
                $sepadanA[] = $i;
                $sepadanB[] = $j;
                $i++;
                $j++;
            } else {
                $j++;
            }
        }

        return [$sepadanB, $sepadanA];
    }

    /** Token sama, atau beza ralat OCR kecil (1 aksara bagi >= 4 huruf, 2 bagi >= 8). */
    public static function sama(string $x, string $y): bool
    {
        if ($x === $y) {
            return true;
        }
        $panjang = min(strlen($x), strlen($y));
        if ($panjang < 4 || abs(strlen($x) - strlen($y)) > 2) {
            return false;
        }

        return levenshtein($x, $y) <= ($panjang >= 8 ? 2 : 1);
    }
}
