<?php

namespace Tests\Feature;

use App\Models\DokumenField;
use App\Services\OllamaExtractionService;
use App\Support\JenisMedan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Peraturan, simpanan, paparan dan pembersihan AI bagi setiap jenis data
 * medan EAV dalam App\Support\JenisMedan.
 */
class JenisMedanTest extends TestCase
{
    private function medan(string $jenis, array $atribut = []): DokumenField
    {
        $medan = new DokumenField(array_merge([
            'nama_field' => 'Medan Ujian', 'kod_field' => 'medan_ujian', 'jenis_data' => $jenis, 'is_required' => false,
        ], $atribut));
        $medan->dokumen_field_id = 7;

        return $medan;
    }

    private function sah(DokumenField $medan, mixed $nilai, mixed $lain = null): bool
    {
        $input = ['medan_data' => [7 => $nilai], 'medan_lain' => [7 => $lain]];

        return Validator::make($input, JenisMedan::peraturan($medan))->passes();
    }

    public static function nilaiSahDanTidak(): array
    {
        return [
            'emel sah' => ['emel', 'ali@pkink.gov.my', true],
            'emel tidak sah' => ['emel', 'bukan emel', false],
            'telefon bertanda sempang' => ['telefon', '09-748 1234', true],
            'telefon antarabangsa' => ['telefon', '+6019-123 4567', true],
            'telefon huruf' => ['telefon', 'abc123', false],
            'url https' => ['url', 'https://www.pkink.gov.my/borang', true],
            'url javascript ditolak' => ['url', 'javascript:alert(1)', false],
            'url ftp ditolak' => ['url', 'ftp://pkink.gov.my', false],
            'wang 2 titik perpuluhan' => ['wang', '1500.50', true],
            'wang tanpa perpuluhan' => ['wang', '200', true],
            'wang 3 titik perpuluhan' => ['wang', '1.234', false],
            'wang negatif' => ['wang', '-5', false],
            'masa sah' => ['masa', '14:30', true],
            'masa tidak sah' => ['masa', '25:00', false],
            'tarikh_masa dari pelayar' => ['tarikh_masa', '2026-10-25T14:30', true],
            'tarikh_masa simpanan' => ['tarikh_masa', '2026-10-25 14:30', true],
            'tarikh_masa tanpa masa' => ['tarikh_masa', '2026-10-25', false],
            'ya_tidak Ya' => ['ya_tidak', 'Ya', true],
            'ya_tidak lain' => ['ya_tidak', 'Mungkin', false],
            'nombor perpuluhan panjang' => ['nombor', '3.14159', true],
        ];
    }

    #[DataProvider('nilaiSahDanTidak')]
    public function test_peraturan_setiap_jenis(string $jenis, string $nilai, bool $dijangkaSah): void
    {
        $this->assertSame($dijangkaSah, $this->sah($this->medan($jenis), $nilai));
    }

    public function test_radio_dan_lain_lain(): void
    {
        $radio = $this->medan('radio', ['pilihan' => ['Individu', 'Bahagian']]);
        $this->assertTrue($this->sah($radio, 'Individu'));
        $this->assertFalse($this->sah($radio, JenisMedan::NILAI_LAIN), 'Lain-lain tidak dibenarkan tanpa tetapan');

        $denganLain = $this->medan('radio', ['pilihan' => ['Individu'], 'tetapan' => ['lain' => true]]);
        $this->assertTrue($this->sah($denganLain, JenisMedan::NILAI_LAIN, 'Syarikat luar'));
        $this->assertFalse($this->sah($denganLain, JenisMedan::NILAI_LAIN, ''), 'Teks Lain-lain wajib bila dipilih');

        // Tetapan "lain" diabaikan bagi jenis tanpa pilihan
        $this->assertFalse(JenisMedan::adaLain($this->medan('teks', ['tetapan' => ['lain' => true]])));
    }

    public function test_bentuk_simpanan_dan_borang(): void
    {
        $this->assertSame('1500.50', JenisMedan::untukSimpan($this->medan('wang'), '1500.5'));
        $this->assertSame('2026-10-25 14:30', JenisMedan::untukSimpan($this->medan('tarikh_masa'), '2026-10-25T14:30'));
        $this->assertNull(JenisMedan::untukSimpan($this->medan('teks'), ''));

        $lain = $this->medan('dropdown', ['pilihan' => ['LCD Projektor'], 'tetapan' => ['lain' => true]]);
        $this->assertSame('Lain-lain: Pen Drive', JenisMedan::untukSimpan($lain, JenisMedan::NILAI_LAIN, ' Pen Drive '));
        $this->assertSame([JenisMedan::NILAI_LAIN, 'Pen Drive'], JenisMedan::untukBorang($lain, 'Lain-lain: Pen Drive'));
        $this->assertSame(['LCD Projektor', ''], JenisMedan::untukBorang($lain, 'LCD Projektor'));
        $this->assertSame(['2026-10-25T14:30', ''], JenisMedan::untukBorang($this->medan('tarikh_masa'), '2026-10-25 14:30'));
    }

    public function test_paparan_diformat_dan_selamat(): void
    {
        $this->assertSame('RM 1,500.50', (string) JenisMedan::papar($this->medan('wang'), '1500.50'));
        $this->assertSame('25/10/2026', (string) JenisMedan::papar($this->medan('tarikh'), '2026-10-25'));
        $this->assertSame('25/10/2026, 14:30', (string) JenisMedan::papar($this->medan('tarikh_masa'), '2026-10-25 14:30'));
        $this->assertSame('<a href="mailto:ali@pkink.gov.my">ali@pkink.gov.my</a>', (string) JenisMedan::papar($this->medan('emel'), 'ali@pkink.gov.my'));
        $this->assertStringContainsString('rel="noopener noreferrer"', (string) JenisMedan::papar($this->medan('url'), 'https://pkink.gov.my'));

        // Bukan http(s): tidak dijadikan pautan; HTML sentiasa di-escape
        $this->assertSame('javascript:alert(1)', (string) JenisMedan::papar($this->medan('url'), 'javascript:alert(1)'));
        $this->assertSame('&lt;b&gt;baris 1&lt;/b&gt;<br />' . "\n" . 'baris 2', (string) JenisMedan::papar($this->medan('textarea'), "<b>baris 1</b>\nbaris 2"));
        $this->assertSame('&lt;script&gt;', (string) JenisMedan::papar(null, '<script>'));
    }

    public function test_pembersihan_nilai_ai(): void
    {
        $this->assertSame('1500.00', JenisMedan::bersihkanAi($this->medan('wang'), 'RM 1,500.00'));
        $this->assertSame('Ya', JenisMedan::bersihkanAi($this->medan('ya_tidak'), 'yes'));
        $this->assertSame('Tidak', JenisMedan::bersihkanAi($this->medan('ya_tidak'), 'Tiada'));
        $this->assertSame('2026-10-25 14:30', JenisMedan::bersihkanAi($this->medan('tarikh_masa'), '2026-10-25T14:30:00'));
        $this->assertNull(JenisMedan::bersihkanAi($this->medan('emel'), 'bukan emel'));
        $this->assertNull(JenisMedan::bersihkanAi($this->medan('url'), 'javascript:alert(1)'));
        $this->assertNull(JenisMedan::bersihkanAi($this->medan('teks'), ['bukan', 'teks']));

        $dropdown = $this->medan('dropdown', ['pilihan' => ['Disokong', 'Tidak Disokong']]);
        $this->assertSame('Disokong', JenisMedan::bersihkanAi($dropdown, 'Disokong'));
        $this->assertNull(JenisMedan::bersihkanAi($dropdown, 'Mungkin'));

        $denganLain = $this->medan('dropdown', ['pilihan' => ['Emel'], 'tetapan' => ['lain' => true]]);
        $this->assertSame('Lain-lain: Intranet', JenisMedan::bersihkanAi($denganLain, 'Intranet'));
    }

    public function test_prompt_ai_mengandungi_nama_dan_panduan_format(): void
    {
        Http::fake(['*/api/generate' => Http::response([
            'response' => json_encode(['caj_sewaan' => 'RM 50', 'emel_pegawai' => 'bukan-emel', 'kegunaan' => 'Individu']),
        ])]);

        $medan = collect([
            $this->medan('wang', ['kod_field' => 'caj_sewaan', 'nama_field' => 'Caj Sewaan']),
            tap($this->medan('emel', ['kod_field' => 'emel_pegawai', 'nama_field' => 'Emel Pegawai']), fn ($m) => $m->dokumen_field_id = 8),
            tap($this->medan('radio', ['kod_field' => 'kegunaan', 'nama_field' => 'Kegunaan', 'pilihan' => ['Individu', 'Bahagian']]), fn ($m) => $m->dokumen_field_id = 9),
        ]);

        $hasil = app(OllamaExtractionService::class)->ekstrak('teks OCR borang', $medan);

        $this->assertSame([7 => '50.00', 8 => null, 9 => 'Individu'], $hasil);
        Http::assertSent(function ($req) {
            $prompt = $req['prompt'];

            return str_contains($prompt, '- kod "caj_sewaan" (Caj Sewaan): jumlah wang dalam RM')
                && str_contains($prompt, '- kod "kegunaan" (Kegunaan): pilih SATU nilai TEPAT daripada [Individu, Bahagian]');
        });
    }
}
