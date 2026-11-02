<?php

namespace App\Services;

use App\Models\DokumenField;
use App\Models\PembetulanAi;
use App\Support\JenisMedan;
use App\Support\TandaVisual;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ekstrak nilai medan EAV daripada teks OCR, guna model AI TEMPATAN melalui
 * Ollama (http://127.0.0.1:11434) — bukan API berbayar. Reka bentuk ini
 * automatik serasi dengan APA-APA Jenis Dokumen yang admin cipta kemudian,
 * sebab senarai medan dihantar sebagai sebahagian prompt, bukan dikodkan
 * keras (hardcode) per jenis dokumen.
 *
 * PRASYARAT (bukan kod — pasang berasingan, lihat README):
 *   1. Ollama dipasang & servisnya berjalan (ikon system tray)
 *   2. Model ditarik (`ollama pull ...`) — lihat OLLAMA_MODEL dalam .env
 */
class OllamaExtractionService
{
    private string $urlAsas;

    private string $model;

    public function __construct()
    {
        $this->urlAsas = rtrim(config('services.ollama.url', 'http://127.0.0.1:11434'), '/');
        $this->model = config('services.ollama.model', 'sea-lion-local');
    }

    /** Kunci medan asas dokumen (bukan EAV) dalam hasil ekstrak() bila $medanAsas = true. */
    public const TARIKH_DOKUMEN = '_tarikh_dokumen';

    public const PERKARA = '_perkara';

    /**
     * @param  string  $teksOcr  Teks mentah hasil OcrService::proses()
     * @param  Collection<DokumenField>  $medan
     * @param  bool  $medanAsas  true = turut ekstrak Tarikh Dokumen & Perkara (kunci TARIKH_DOKUMEN/PERKARA)
     * @param  array<int|string, array>  $beza  TB4 BezaTemplatService::hasil(): dakwat & bacaan semula setiap medan
     * @return array<int|string, mixed> dokumen_field_id => nilai (null jika tak dijumpai/tak sah)
     *
     * @throws \RuntimeException jika Ollama tidak dapat dihubungi atau pulangkan JSON tak sah
     */
    public function ekstrak(string $teksOcr, Collection $medan, bool $medanAsas = false, array $beza = []): array
    {
        // Tajuk seksyen tiada nilai, jadi tidak dihantar kepada AI
        $medan = $medan->filter(fn ($f) => JenisMedan::adaNilai($f))->values();

        if (($medan->isEmpty() && ! $medanAsas) || trim($teksOcr) === '') {
            return $medan->mapWithKeys(fn ($f) => [$f->dokumen_field_id => null])->all()
                + ($medanAsas ? [self::TARIKH_DOKUMEN => null, self::PERKARA => null] : []);
        }

        $badan = [
            'model' => $this->model,
            'prompt' => $this->binaPrompt(self::normalTahun($teksOcr), $medan, $medanAsas, $beza),
            'format' => 'json',
            'stream' => false,
            'keep_alive' => config('services.ollama.keep_alive', '30m'),
            // Suhu 0 + seed tetap: hasil sama bagi dokumen sama (suhu 0.1 memberi nilai berbeza antara larian)
            'options' => $this->opsyen() + [
                'temperature' => 0,
                'seed' => 42,
                'num_predict' => (int) config('services.ollama.num_predict', 2048),
            ],
        ];
        $respons = $this->hantar($badan);

        // Model gagal dimuat sepenuhnya ke GPU (cth. VRAM digunakan aplikasi lain):
        // ulang sekali dengan pembahagian GPU/CPU lalai Ollama
        if ($respons->failed() && isset($badan['options']['num_gpu'])) {
            unset($badan['options']['num_gpu']);
            $respons = $this->hantar($badan);
        }

        if ($respons->failed()) {
            throw new \RuntimeException(
                "Ollama tidak dapat dihubungi (pastikan servis Ollama berjalan): HTTP {$respons->status()} — ".$respons->body()
            );
        }

        $teksJson = $respons->json('response');
        $data = json_decode((string) $teksJson, true);

        if (! is_array($data)) {
            throw new \RuntimeException('Model AI tidak pulangkan JSON yang sah: '.substr((string) $teksJson, 0, 300));
        }

        return $this->bersihkanNilai($data, $medan, $teksOcr, $beza)
            + ($medanAsas ? $this->bersihkanAsas($data) : []);
    }

    /**
     * Muatkan model ke memori GPU lebih awal (prompt kosong), supaya imbasan pertama tidak
     * menunggu model dimuat. Dipanggil apabila halaman Imbas & Arkib / Imbas & Hantar dibuka.
     * Opsyen mesti sama dengan ekstrak(): num_ctx/num_gpu berbeza menyebabkan Ollama memuat semula.
     */
    public function panaskan(): bool
    {
        $badan = [
            'model' => $this->model,
            'prompt' => '',
            'stream' => false,
            'keep_alive' => config('services.ollama.keep_alive', '30m'),
            'options' => $this->opsyen(),
        ];
        $respons = $this->hantar($badan);
        if ($respons->failed() && isset($badan['options']['num_gpu'])) {
            unset($badan['options']['num_gpu']);
            $respons = $this->hantar($badan);
        }

        return $respons->successful();
    }

    /** True jika model sudah dimuat dalam memori Ollama (semakan pantas, /api/ps). */
    public function sudahDimuat(): bool
    {
        $model = Http::timeout(3)->get("{$this->urlAsas}/api/ps")->json('models') ?? [];

        return collect($model)->contains(fn ($m) => in_array($this->model, [$m['name'] ?? null, $m['model'] ?? null], true)
            || str_starts_with((string) ($m['name'] ?? ''), $this->model.':'));
    }

    /** Opsyen prestasi Ollama yang menentukan cara model dimuat (config services.ollama). */
    private function opsyen(): array
    {
        $numGpu = config('services.ollama.num_gpu');

        return ['num_ctx' => (int) config('services.ollama.num_ctx', 4096)]
            + ($numGpu !== null && $numGpu !== '' ? ['num_gpu' => (int) $numGpu] : []);
    }

    private function hantar(array $badan): Response
    {
        return Http::timeout((int) config('services.ollama.had_masa', 180))
            ->post("{$this->urlAsas}/api/generate", $badan);
    }

    private function binaPrompt(string $teksOcr, Collection $medan, bool $medanAsas = false, array $beza = []): string
    {
        // Nama medan + panduan format ikut jenis data (App\Support\JenisMedan)
        $senaraiMedan = $medan
            ->map(fn ($f) => "- kod \"{$f->kod_field}\" ({$f->nama_field}): ".JenisMedan::panduanAi($f))
            ->when($medanAsas, fn ($s) => $s->push(
                '- kod "'.self::TARIKH_DOKUMEN.'" (Tarikh Dokumen): tarikh dokumen itu sendiri (tarikh surat, memo atau mesyuarat, atau tarikh borang diisi oleh pemohon; BUKAN tarikh dalam bahagian pengesahan/sokongan/kegunaan pejabat), format YYYY-MM-DD',
                '- kod "'.self::PERKARA.'" (Perkara): tajuk utama dokumen seperti tertulis (perkara surat/memo, tajuk penuh minit mesyuarat, atau nama borang), maksimum 200 aksara'
            ))
            ->implode("\n");
        $contoh = $this->blokPembetulan($medan).self::blokBeza($medan, $beza);
        // T6: peraturan penanda tanda visual hanya bila teks mengandunginya, supaya prompt
        // dokumen lain (memo, surat, minit, tulisan tangan melalui VLM) kekal sama seperti dahulu
        $peraturanTanda = preg_match(TandaVisual::CORAK_PENANDA, $teksOcr)
            ? "- ~~teks~~ ialah teks yang DIPOTONG (pilihan ditolak): \"disokong/ ~~tidak sokong~~\" = Disokong\n"
                ."- [✓] = kotak bertanda (dipilih); [□] = kotak kosong (tidak dipilih); nombor/teks lain pada baris yang sama tetap nilai medan berkaitan (cth. \"[✓] Makmal 10\" = bilangan 10)\n"
            : '';
        // Fasa TB: teks bertanda isian berbanding templat borang kosong
        if (str_contains($teksOcr, '«')) {
            $peraturanTanda .= "- Teks dalam «…» ialah TULISAN PENGISI; teks lain ialah cetakan borang kosong. Nilai medan diambil daripada «…» (kecuali pilihan yang ditanda/dipotong); label tanpa «…» selepasnya = null\n";
        }

        // JSON padat (satu baris) mengurangkan token jawapan, jadi jawapan lebih pantas
        return <<<PROMPT
Anda pembantu mengekstrak data daripada teks hasil OCR sebuah dokumen rasmi Bahasa Melayu
(borang, memo, surat atau minit mesyuarat).

Diberi teks OCR dan senarai medan, PULANGKAN HANYA satu objek JSON (tiada penjelasan,
tiada markdown) memetakan setiap KOD medan kepada nilai yang DIISI dalam dokumen.
Jika tiada, guna null.

Peraturan ketat:
- Ambil hanya nilai yang diisi/ditulis bagi medan itu, BUKAN teks bercetak borang: label,
  arahan, perakuan/syarat, tajuk seksyen atau nota kaki (cth. "Disediakan oleh ...", nama fail .doc)
- Jangan reka, teka atau simpulkan nilai yang tidak tertulis dalam dokumen; guna null
- Ikut format setiap medan (tarikh YYYY-MM-DD; nombor/wang angka sahaja)
- Medan dengan senarai pilihan: pilih SATU nilai TEPAT dari senarai hanya jika dokumen
  menunjukkan pilihan itu (ditanda, ditulis, atau pilihan lain dipotong); jika tidak, null
- Huruf dalam kotak mungkin dipisahkan ruang atau "|" (cth. "S Y A Z M I"): cantumkan
- Jadual: satu objek bagi setiap baris yang diisi sahaja; abaikan baris kosong
- Teks selepas tanda ":" dalam Senarai Medan ialah ARAHAN format, bukan nilai
{$peraturanTanda}
Senarai Medan:
{$senaraiMedan}
{$contoh}
Teks OCR:
\"\"\"
{$teksOcr}
\"\"\"

Jawab dengan JSON padat dalam SATU baris (tanpa baris baharu atau ruang tambahan),
contoh bentuk: {"kod_medan_1":"nilai","kod_medan_2":null}
PROMPT;
    }

    /**
     * P16 few-shot: pembetulan staf terkini bagi kategori ini (satu bagi setiap medan,
     * maks PembetulanAi::HAD_CONTOH). Kosong jika tiada pembetulan, dimatikan
     * (OLLAMA_FEW_SHOT=false) atau DB gagal: pengekstrakan tidak boleh gagal kerananya.
     */
    private function blokPembetulan(Collection $medan): string
    {
        if (! config('services.ollama.few_shot', true) || $medan->isEmpty()) {
            return '';
        }

        try {
            $contoh = PembetulanAi::contohTerkini((int) $medan->first()->jenis_dokumen_id, $medan->pluck('dokumen_field_id')->all());
        } catch (\Throwable $e) {
            Log::warning('Contoh pembetulan AI tidak dapat dibaca: '.$e->getMessage());

            return '';
        }
        if ($contoh->isEmpty()) {
            return '';
        }

        // Hanya nilai SALAH yang disenaraikan, bukan nilai betul: ujian dengan borang 4-b
        // menunjukkan model menyalin nilai betul dokumen lama ke dokumen baharu walaupun
        // diarah tidak, jadi nilai sebenar satu dokumen tidak boleh masuk ke prompt lain.
        $kod = $medan->pluck('kod_field', 'dokumen_field_id');
        $baris = $contoh->filter(fn ($c) => trim((string) $c->nilai_ai) !== '')
            ->map(fn ($c) => "- kod \"{$kod[$c->dokumen_field_id]}\": "
                .json_encode(mb_substr($c->nilai_ai, 0, 80), JSON_UNESCAPED_UNICODE).' adalah SALAH'
                .(trim((string) $c->nilai_disimpan) === '' ? ' (medan ini biasanya kosong; guna null jika tiada nilai jelas)' : ''));
        if ($baris->isEmpty()) {
            return '';
        }

        return "\nKesilapan lalu bagi jenis dokumen ini (dibetulkan oleh staf). Jangan ulang nilai ini;\n"
            ."jika teks yang sama muncul, ia bukan nilai medan itu:\n"
            .$baris->implode("\n")
            ."\n";
    }

    /**
     * Tarikh bertahun 2 digit (dd/mm/yy, lazim dalam borang tulisan tangan) ditukar kepada 4 digit
     * sebelum dihantar kepada AI, kerana AI kerap salah teka abad (cth. "20/9/26" → 2021).
     * yy <= tahun semasa + 1 → 20yy, selainnya 19yy.
     */
    public static function normalTahun(string $teks): string
    {
        $had = (int) date('y') + 1;

        return preg_replace_callback(
            '#(?<![\d/])(0?[1-9]|[12]\d|3[01])\s*([/.-])\s*(0?[1-9]|1[0-2])\s*\2\s*(\d{2})(?![\d/])#',
            fn ($m) => "{$m[1]}/{$m[3]}/".((int) $m[4] <= $had ? '20' : '19').$m[4],
            $teks
        );
    }

    /** Tarikh Dokumen (tarikh sah, tahun munasabah) & Perkara (teks, maks 255) daripada AI. */
    private function bersihkanAsas(array $data): array
    {
        $tarikh = self::normalTarikhAi($data[self::TARIKH_DOKUMEN] ?? null);
        $sah = is_string($tarikh)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarikh)
            && checkdate((int) substr($tarikh, 5, 2), (int) substr($tarikh, 8, 2), (int) substr($tarikh, 0, 4))
            && (int) substr($tarikh, 0, 4) >= 1950 && (int) substr($tarikh, 0, 4) <= (int) date('Y') + 1;

        $perkara = $data[self::PERKARA] ?? null;
        $perkara = is_scalar($perkara) ? trim(preg_replace('/\s+/u', ' ', (string) $perkara)) : '';

        return [
            self::TARIKH_DOKUMEN => $sah ? $tarikh : null,
            self::PERKARA => $perkara !== '' && mb_strtolower($perkara) !== 'perkara' ? mb_substr($perkara, 0, 255) : null,
        ];
    }

    /**
     * Sahkan setiap nilai ikut jenis_data medan (peraturan sama dengan borang);
     * nilai tak sah jadi null. Nilai dipulangkan dalam bentuk simpanan.
     */
    private function bersihkanNilai(array $data, Collection $medan, string $teksOcr = '', array $beza = []): array
    {
        // Label borang yang disalin sebagai nilai (cth. "Jabatan/ Bahagian" bagi medan nama) dibuang
        $label = $medan->map(fn ($f) => self::normalLabel($f->nama_field))->filter()->flip();
        $digitOcr = preg_replace('/\D+/', '', $teksOcr);

        return $medan
            ->mapWithKeys(function ($f) use ($data, $label, $digitOcr, $teksOcr, $beza) {
                $nilai = $data[$f->kod_field] ?? null;
                if (in_array($f->jenis_data, ['tarikh', 'tarikh_masa'], true)) {
                    $nilai = self::normalTarikhAi($nilai);
                } elseif ($f->jenis_data === 'jadual' && is_array($nilai)) {
                    // Sel tarikh dalam jadual: format Malaysia yang AI salin ditukar juga
                    $nilai = array_map(fn ($baris) => is_array($baris) ? array_map(fn ($sel) => self::normalTarikhAi($sel), $baris) : $baris, $nilai);
                }
                if (is_string($nilai) && ($label->has(self::normalLabel($nilai)) || self::sebahagianLabel($nilai, $f)
                    // T6: penanda tanda visual dalam nilai = baris bercetak disalin (cth. komen
                    // "Permohonan disokong/ ~~tidak sokong~~"), bukan tulisan pengisi
                    || preg_match(TandaVisual::CORAK_PENANDA, $nilai)
                    || (in_array($f->jenis_data, ['teks', 'textarea'], true) && self::teksBercetak($nilai, $teksOcr, $f->nama_field)))) {
                    $nilai = null;
                }
                // Akhiran bercetak yang melekat pada isian (cth. "syazmi" + "@pkink.gov.my" bercetak)
                if (is_string($nilai) && $f->jenis_data === 'teks') {
                    $nilai = self::buangAkhiranBercetak($nilai, $teksOcr);
                }
                $nilai = JenisMedan::bersihkanAi($f, $nilai);

                // Fasa TB: nilai teks yang langsung tiada dalam tulisan pengisi «…» disalin daripada cetakan borang
                if ($nilai !== null && in_array($f->jenis_data, ['teks', 'textarea', 'emel', 'telefon'], true)
                    && ! self::adaDalamIsian((string) $nilai, $teksOcr, $f->jenis_data === 'telefon')) {
                    $nilai = null;
                }

                // T6: pilihan yang hanya wujud dalam "X/ tidak X" bercetak tanpa potongan belum diputuskan
                if ($nilai !== null && in_array($f->jenis_data, ['radio', 'dropdown'], true) && self::pilihanBelumDibuat((string) $nilai, $teksOcr)) {
                    $nilai = null;
                }

                // T6: nombor/wang yang digitnya tiada langsung dalam teks OCR ialah rekaan AI
                // (cth. "Caj adalah RM ____" kosong diisi 1500.50)
                if ($nilai !== null && in_array($f->jenis_data, ['nombor', 'wang'], true) && ! self::nomborWujud((string) $nilai, $teksOcr, $digitOcr)) {
                    $nilai = null;
                }

                // TB4: kotak medan pada imbasan tiada dakwat pengisi langsung = kosong pasti
                if ($nilai !== null && ($beza[$f->dokumen_field_id]['kosong'] ?? false)
                    && in_array($f->jenis_data, BezaTemplatService::JENIS_DINILAI, true)) {
                    $nilai = null;
                }

                return [$f->dokumen_field_id => $nilai];
            })
            ->all();
    }

    /**
     * TB4: bacaan semula keratan dakwat (tanpa cetakan borang) bagi medan yang yakin dibaca,
     * sebagai petunjuk ejaan/digit. Kosong (prompt tidak berubah) bila tiada.
     */
    private static function blokBeza(Collection $medan, array $beza): string
    {
        $min = (float) config('services.ocr.beza_min_yakin', 70);
        $baris = $medan
            ->filter(fn ($f) => ($h = $beza[$f->dokumen_field_id] ?? null) && ! $h['kosong'] && $h['teks'] !== null && ($h['conf'] ?? 0) >= $min)
            ->map(fn ($f) => "- kod \"{$f->kod_field}\": ".json_encode($beza[$f->dokumen_field_id]['teks'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->implode('
');

        return $baris === '' ? '' : "
Bacaan semula tulisan pengisi bagi medan berikut (imej ruang medan itu sahaja, tanpa cetakan borang).
Guna untuk membetulkan ejaan/digit nilai yang kabur dalam Teks OCR; jangan salin label:
{$baris}
";
    }

    /**
     * Teks bercetak borang yang melekat pada hujung isian tanpa ruang, cth. AI memulangkan
     * "SYAZMI@pkink.gov.my" bagi username kerana borang mencetak "@pkink.gov.my" selepas
     * kotak. Dibuang hanya jika (1) teks bertanda isian «…» (ada templat), (2) akhiran bermula
     * dengan tanda baca dan tiada ruang, (3) tiada perkataan akhiran dalam isian, dan (4) akhiran
     * wujud dalam teks bercetak. Nilai lain dipulangkan tanpa perubahan.
     */
    public static function buangAkhiranBercetak(string $nilai, string $teksOcr): string
    {
        if (! preg_match_all('/«([^»]*)»/u', $teksOcr, $m)
            || ! preg_match('/^(.*?[\p{L}\p{N}])([@.\/_\-][\p{L}\p{N}@.\/_\-]*)$/u', trim($nilai), $bahagian)) {
            return $nilai;
        }
        $isian = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(implode(' ', $m[1])), -1, PREG_SPLIT_NO_EMPTY);
        $token = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($bahagian[2]), -1, PREG_SPLIT_NO_EMPTY);
        if (! $token || array_intersect($token, $isian)) {
            return $nilai;
        }
        $cetakan = self::normalLabel(preg_replace('/«[^»]*»/u', ' ', $teksOcr));

        return str_contains($cetakan, self::normalLabel($bahagian[2])) ? trim($bahagian[1]) : $nilai;
    }

    /**
     * Tarikh yang AI salin seperti tertulis (format Malaysia hari dahulu: "21/09/2026",
     * "21-9-2026 10:00") ditukar kepada YYYY-MM-DD [HH:MM]; tarikh mustahil ditolak oleh
     * pengesahan seterusnya. Nilai lain dipulangkan tanpa perubahan.
     */
    public static function normalTarikhAi(mixed $nilai): mixed
    {
        if (! is_string($nilai) || ! preg_match('#^\s*(\d{1,2})\s*[/.-]\s*(\d{1,2})\s*[/.-]\s*(\d{4})(?:[ ,T]+(\d{1,2}):(\d{2}))?\s*$#', $nilai, $m)) {
            return $nilai;
        }
        $tarikh = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);

        return isset($m[4]) && $m[4] !== '' ? $tarikh.sprintf(' %02d:%02d', $m[4], $m[5]) : $tarikh;
    }

    /**
     * Fasa TB: nilai mempunyai asas dalam tulisan pengisi «…». Benar jika teks tidak bertanda
     * (tiada templat), atau nilai (tanpa ruang/tanda baca) terkandung dalam isian yang
     * dicantum (huruf dalam kotak "S Y A Z M I"), atau sekurang-kurangnya satu perkataan nilai
     * (>= 2 aksara) wujud dalam isian. Sempit dengan sengaja: hanya nilai yang SEMUANYA
     * cetakan borang dibuang.
     */
    public static function adaDalamIsian(string $nilai, string $teksOcr, bool $telefon = false): bool
    {
        if (! preg_match_all('/«([^»]*)»/u', $teksOcr, $m)) {
            return true;
        }
        $isian = mb_strtolower(implode(' ', $m[1]));
        if ($telefon) {
            // Digit sahaja; ralat OCR biasa O→0, l/I→1 dibetulkan AI ("O9-748 1234" → 09-7481234)
            $digit = fn (string $t) => preg_replace('/\D+/', '', strtr($t, ['o' => '0', 'O' => '0', 'l' => '1', 'I' => '1', 'i' => '1']));
            $d = $digit($nilai);

            return $d === '' || str_contains($digit($isian), $d);
        }
        $n = self::normalLabel($nilai);
        if ($n === '' || str_contains(self::normalLabel($isian), $n)) {
            return true;
        }
        $tokenIsian = preg_split('/[^\p{L}\p{N}]+/u', $isian, -1, PREG_SPLIT_NO_EMPTY);
        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($nilai), -1, PREG_SPLIT_NO_EMPTY) as $t) {
            if (mb_strlen($t) < 2) {
                continue;
            }
            // Kabur seperti penjajaran templat: AI membetulkan ralat OCR ("Keranl" → "Kerani")
            foreach ($tokenIsian as $ti) {
                if (TemplatBorangService::sama($t, $ti)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * T6: nombor/wang benar-benar tertulis dalam dokumen: rentetan digit penuh nilai (integer +
     * pecahan, tanpa sifar hujung) wujud dalam digit teks OCR, ATAU dokumen menulis nombor dengan
     * perkataan ("seribu lima ratus", "RM1.5 juta"), yang tidak dapat disemak secara digit.
     * Sifar mesti tertulis sebagai nombor sendiri ("RM 0.00"), bukan ruang kosong "RM ____".
     */
    public static function nomborWujud(string $nilai, string $teksOcr, string $digitOcr): bool
    {
        // Perkataan skala sahaja: perkataan satu digit ("dua", "lima") terlalu biasa dalam
        // dokumen, dan nilai satu digit hampir sentiasa lulus semakan digit (tarikh dsb.)
        if (preg_match('/(?:ribu|ratus|juta|bilion|puluh|belas)\b|\bse(?:ratus|ribu|juta|puluh)\b/iu', $teksOcr)) {
            return true;
        }
        [$integer, $pecahan] = array_pad(explode('.', $nilai, 2), 2, '');
        $digit = ltrim(preg_replace('/\D+/', '', $integer), '0').rtrim(preg_replace('/\D+/', '', $pecahan), '0');
        if ($digit === '') {
            return (bool) preg_match('/(?<![\d.,])0(?:[.,]0+)?(?![\d.,])/', $teksOcr);
        }

        return str_contains($digitOcr, $digit);
    }

    /**
     * T6: nilai teks yang disalin daripada teks bercetak, bukan isian:
     *  - sama dengan SATU segmen penuh teks bercetak di antara penanda pada baris bertanda,
     *    cth. komen "Permohonan disokong" daripada "Permohonan disokong/ ~~tidak sokong~~".
     *    Tulisan pada baris yang sama (cth. "[✓] Lain-lain: Bengkel keusahawanan") dikekalkan;
     *  - nama organisasi sendiri (kepala bercetak setiap borang) bagi medan jabatan/bahagian/
     *    agensi/organisasi. Kepala surat pihak lain (surat masuk) dan medan lain (cth. pengirim
     *    surat keluar) tidak ditapis.
     */
    public static function teksBercetak(string $nilai, string $teksOcr, string $namaMedan = ''): bool
    {
        $n = self::normalLabel($nilai);
        if (mb_strlen($n) < 6) {
            return false;
        }
        $organisasi = self::normalLabel((string) config('app.nama_organisasi'));
        if ($organisasi !== '' && in_array($n, [$organisasi, $organisasi.'pkink'], true)
            && preg_match('/jabatan|bahagian|agensi|organisasi|unit/iu', $namaMedan)) {
            return true;
        }
        foreach (preg_split('/\R/u', $teksOcr) as $baris) {
            if (! preg_match(TandaVisual::CORAK_PENANDA, $baris)) {
                continue;
            }
            foreach (preg_split(TandaVisual::CORAK_PENANDA, $baris) as $segmen) {
                if (self::normalLabel($segmen) === $n) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * T6: perkataan utama pilihan (perkataan terakhir, cth. "disokong" bagi "Tidak Disokong")
     * hanya muncul dalam pasangan pilihan bercetak "X/ tidak X" yang TIDAK dipotong langsung
     * (cth. "Permohonan: Disokong/ Tidak Sokong" kosong): AI menyalin teks bercetak, bukan
     * pilihan pengisi. Pasangan yang ada ~~potongan~~, atau perkataan yang muncul di luar
     * pasangan (ditulis/ditanda), dikekalkan.
     *
     * Pertukaran yang disengajakan: pilihan yang dibulatkan tangan ("Lulus / Tidak Lulus"
     * dengan bulatan) tidak dikesan sebagai tanda, jadi dibiarkan kosong untuk diisi staf;
     * lebih selamat daripada AI meneka pilihan.
     */
    public static function pilihanBelumDibuat(string $nilai, string $teksOcr): bool
    {
        $perkataan = preg_split('/[^\p{L}]+/u', mb_strtolower($nilai), -1, PREG_SPLIT_NO_EMPTY);
        $utama = end($perkataan);
        if (! $utama || mb_strlen($utama) < 4) {
            return false;
        }
        $jumpa = false;
        foreach (preg_split('/\R/u', mb_strtolower($teksOcr)) as $baris) {
            if (! str_contains($baris, $utama)) {
                continue;
            }
            $jumpa = true;
            $pasanganUtuh = ! preg_match(TandaVisual::CORAK_PENANDA, $baris)
                && preg_match('/\p{L}+\s*\/\s*tidak[\s\-]+\p{L}+/u', $baris);
            if (! $pasanganUtuh) {
                return false;
            }
        }

        return $jumpa;
    }

    /**
     * T6: kod bercetak dalam label medan sendiri disalin sebagai nilai (cth. "KEW.PA-9" bagi
     * "No. Daftar KEW.PA-9"). Hanya nilai berbentuk kod (ada digit atau . - /) dan bukan medan
     * pilihan: label yang menyenaraikan pilihan ("Jantina (Lelaki/Perempuan)") tidak terjejas.
     */
    private static function sebahagianLabel(string $nilai, DokumenField $medan): bool
    {
        if (in_array($medan->jenis_data, ['radio', 'dropdown', 'kotak_semak', 'ya_tidak'], true)
            || ! preg_match('/[\d.\-\/]/u', $nilai)) {
            return false;
        }
        $n = self::normalLabel($nilai);

        return mb_strlen($n) >= 4 && str_contains(self::normalLabel($medan->nama_field), $n);
    }

    /** Bentuk perbandingan label: huruf kecil, tanpa ruang/tanda baca (cth. "Jabatan/ Bahagian:" → "jabatanbahagian"). */
    private static function normalLabel(string $teks): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($teks));
    }
}
