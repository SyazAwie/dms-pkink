<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\User;
use App\Services\OllamaExtractionService;
use App\Support\JenisMedan;
use App\Support\MedanJadual;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Langkah 4 medan EAV: jadual baris berulang (Borang Peralatan ICT, minit
 * mesyuarat) dan jadual baris tetap (Tindakan BD dalam Borang Pendaftaran Emel).
 */
class MedanEavJadualTest extends TestCase
{
    use SkemaUjian;

    private User $admin;
    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->admin = $this->buatPengguna(['ADMIN'], ['bahagian_id' => $this->buatBahagian()->bahagian_id]);

        $this->actingAs($this->admin)->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => 'ICT',
            'nama_dokumen' => 'Borang Permohonan Peralatan Komputer/ICT',
            'medan' => [
                [
                    'nama_field' => 'Senarai Peralatan', 'kod_field' => 'peralatan', 'jenis_data' => 'jadual', 'is_required' => '1',
                    'lajur' => [
                        ['nama' => 'Jenis Peralatan', 'jenis' => 'teks'],
                        ['nama' => 'Kuantiti', 'jenis' => 'nombor'],
                        ['nama' => 'Harga Seunit', 'jenis' => 'wang'],
                        ['nama' => 'Keutamaan', 'jenis' => 'dropdown', 'pilihan' => 'Tinggi, Rendah'],
                        ['nama' => '', 'jenis' => 'teks'],
                    ],
                ],
                [
                    'nama_field' => 'Tindakan BD', 'kod_field' => 'tindakan_bd', 'jenis_data' => 'jadual',
                    'lajur' => [['nama' => 'Tarikh', 'jenis' => 'tarikh'], ['nama' => 'Catatan', 'jenis' => 'teks']],
                    'baris_tetap' => 'Emel, Intranet, Makluman',
                ],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('jenis-dokumen.index'));

        $this->jenis = JenisDokumen::with('fields')->where('kod_dokumen', 'ICT')->sole();
    }

    private function medan(string $kod): DokumenField
    {
        return $this->jenis->fields->firstWhere('kod_field', $kod);
    }

    private function id(string $kod): int
    {
        return $this->medan($kod)->dokumen_field_id;
    }

    public function test_pembina_simpan_lajur_dan_baris_tetap(): void
    {
        $this->assertSame([
            'lajur' => [
                ['kod' => 'jenis_peralatan', 'nama' => 'Jenis Peralatan', 'jenis' => 'teks'],
                ['kod' => 'kuantiti', 'nama' => 'Kuantiti', 'jenis' => 'nombor'],
                ['kod' => 'harga_seunit', 'nama' => 'Harga Seunit', 'jenis' => 'wang'],
                ['kod' => 'keutamaan', 'nama' => 'Keutamaan', 'jenis' => 'dropdown', 'pilihan' => ['Tinggi', 'Rendah']],
            ],
        ], $this->medan('peralatan')->tetapan);
        $this->assertSame(['Emel', 'Intranet', 'Makluman'], $this->medan('tindakan_bd')->tetapan['baris']);
    }

    public function test_pembina_kekalkan_kod_lajur_bila_nama_ditukar_dan_tolak_jadual_tanpa_lajur(): void
    {
        $medan = $this->medan('peralatan');
        $this->actingAs($this->admin)->put(route('jenis-dokumen.update', $this->jenis->jenis_dokumen_id), [
            'kod_dokumen' => 'ICT', 'nama_dokumen' => 'Borang ICT',
            'medan' => [[
                'id' => $medan->dokumen_field_id, 'nama_field' => 'Senarai Peralatan', 'kod_field' => 'peralatan', 'jenis_data' => 'jadual',
                'lajur' => [['kod' => 'kuantiti', 'nama' => 'Bilangan Unit', 'jenis' => 'nombor'], ['nama' => 'Bilangan Unit', 'jenis' => 'teks']],
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            [['kod' => 'kuantiti', 'nama' => 'Bilangan Unit', 'jenis' => 'nombor'], ['kod' => 'bilangan_unit', 'nama' => 'Bilangan Unit', 'jenis' => 'teks']],
            $medan->fresh()->tetapan['lajur']
        );

        $this->actingAs($this->admin)->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => 'KOSONG', 'nama_dokumen' => 'Jadual Kosong',
            'medan' => [['nama_field' => 'Jadual', 'kod_field' => 'jadual', 'jenis_data' => 'jadual', 'lajur' => [['nama' => '']]]],
        ])->assertSessionHasErrors(['medan' => 'Medan jadual "Jadual" mesti ada sekurang-kurangnya satu lajur.']);
    }

    public function test_peraturan_sel_dan_wajib_satu_baris(): void
    {
        $medan = $this->medan('peralatan');
        $id = $medan->dokumen_field_id;
        $sah = fn (array $baris) => Validator::make(['medan_data' => [$id => $baris]], JenisMedan::peraturan($medan));

        $this->assertTrue($sah([['jenis_peralatan' => 'Laptop', 'kuantiti' => '2', 'harga_seunit' => '3500', 'keutamaan' => 'Tinggi']])->passes());

        $ralat = $sah([['jenis_peralatan' => 'Laptop', 'kuantiti' => 'dua', 'harga_seunit' => '1.999', 'keutamaan' => 'Sederhana']])->errors();
        $this->assertEqualsCanonicalizing(
            ["medan_data.{$id}.0.kuantiti", "medan_data.{$id}.0.harga_seunit", "medan_data.{$id}.0.keutamaan"],
            $ralat->keys()
        );

        $kosong = $sah([['jenis_peralatan' => '', 'kuantiti' => '']]);
        $this->assertSame('Sila isi sekurang-kurangnya satu baris Senarai Peralatan.', $kosong->errors()->first("medan_data.{$id}"));

        $tetap = $this->medan('tindakan_bd');
        $this->assertFalse(Validator::make(
            ['medan_data' => [$tetap->dokumen_field_id => [['_baris' => 'Bukan Label', 'catatan' => 'x']]]],
            JenisMedan::peraturan($tetap)
        )->passes(), 'Label baris tetap mesti dalam senarai');
    }

    public function test_simpanan_borang_dan_paparan(): void
    {
        $medan = $this->medan('peralatan');

        $json = JenisMedan::untukSimpan($medan, [
            5 => ['jenis_peralatan' => 'Laptop', 'kuantiti' => '2', 'harga_seunit' => '3500', 'keutamaan' => 'Tinggi'],
            7 => ['jenis_peralatan' => '', 'kuantiti' => ''],
            9 => ['jenis_peralatan' => 'Pencetak <b>', 'kuantiti' => '1'],
        ]);
        $this->assertSame(
            '[{"jenis_peralatan":"Laptop","kuantiti":"2","harga_seunit":"3500.00","keutamaan":"Tinggi"},'
            . '{"jenis_peralatan":"Pencetak <b>","kuantiti":"1","harga_seunit":null,"keutamaan":null}]',
            $json
        );

        [$baris] = JenisMedan::untukBorang($medan, $json);
        $this->assertCount(2, $baris);
        $this->assertSame([[]], JenisMedan::untukBorang($medan, null)[0], 'Satu baris kosong bila tiada nilai');

        $html = (string) JenisMedan::papar($medan, $json);
        $this->assertStringContainsString('<th>Jenis Peralatan</th>', $html);
        $this->assertStringContainsString('<td>RM 3,500.00</td>', $html);
        $this->assertStringContainsString('Pencetak &lt;b&gt;', $html);
        $this->assertStringNotContainsString('<b>', $html);

        // Baris tetap: satu baris bagi setiap label, ikut susunan tetapan
        $tetap = $this->medan('tindakan_bd');
        $jsonTetap = JenisMedan::untukSimpan($tetap, [
            ['_baris' => 'Emel', 'tarikh' => '2026-10-25', 'catatan' => 'Siap'],
            ['_baris' => 'Intranet', 'tarikh' => '', 'catatan' => ''],
            ['_baris' => 'Makluman', 'tarikh' => '', 'catatan' => 'Dihantar'],
        ]);
        $this->assertSame('[{"_baris":"Emel","tarikh":"2026-10-25","catatan":"Siap"},{"_baris":"Makluman","tarikh":null,"catatan":"Dihantar"}]', $jsonTetap);
        $this->assertSame(
            [['_baris' => 'Emel', 'tarikh' => '2026-10-25', 'catatan' => 'Siap'], ['_baris' => 'Intranet'], ['_baris' => 'Makluman', 'tarikh' => null, 'catatan' => 'Dihantar']],
            MedanJadual::untukBorang($tetap, $jsonTetap)
        );
    }

    public function test_pembersihan_ai_dan_prompt(): void
    {
        $medan = $this->medan('peralatan');

        $this->assertSame(
            '[{"jenis_peralatan":"Laptop","kuantiti":"2","harga_seunit":"3500.00","keutamaan":null}]',
            JenisMedan::bersihkanAi($medan, [['jenis_peralatan' => 'Laptop', 'Kuantiti' => '2', 'harga_seunit' => 'RM 3,500', 'keutamaan' => 'Segera']])
        );
        $this->assertNull(JenisMedan::bersihkanAi($medan, 'bukan json'));
        $this->assertSame(
            '[{"_baris":"Emel","tarikh":"2026-10-25","catatan":null}]',
            JenisMedan::bersihkanAi($this->medan('tindakan_bd'), ['_baris' => 'emel', 'tarikh' => '2026-10-25'])
        );

        Http::fake(['*/api/generate' => Http::response(['response' => json_encode(['peralatan' => [['jenis_peralatan' => 'Tetikus', 'kuantiti' => 3]]])])]);
        $hasil = app(OllamaExtractionService::class)->ekstrak('teks', $this->jenis->fields);
        $this->assertSame('[{"jenis_peralatan":"Tetikus","kuantiti":"3","harga_seunit":null,"keutamaan":null}]', $hasil[$this->id('peralatan')]);
        Http::assertSent(fn ($req) => str_contains($req['prompt'], '"kuantiti" (Kuantiti: nombor, angka sahaja)')
            && str_contains($req['prompt'], 'kunci "_baris" dengan salah satu label baris [Emel, Intranet, Makluman]'));
    }

    public function test_muat_naik_edit_dan_butiran(): void
    {
        Storage::fake('local');

        // Ralat sel: kembali dengan nilai dan mesej BM ikut nombor baris (kunci baris tidak berturutan)
        $this->actingAs($this->admin)->from(route('dokumen.create'))->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'tarikh_dokumen' => '2026-10-25',
            'medan_data' => [$this->id('peralatan') => [
                0 => ['jenis_peralatan' => 'Laptop', 'kuantiti' => '2'],
                3 => ['jenis_peralatan' => 'Pencetak', 'kuantiti' => 'satu'],
            ]],
            'fail_scan' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ])->assertRedirect(route('dokumen.create'));
        // Nota: jangan panggil assertSessionHasErrors() di sini; dengan session.serialization=json
        // ia menyebabkan ralat tidak lagi dikongsi dengan permintaan GET seterusnya dalam ujian.

        $this->actingAs($this->admin)->get(route('dokumen.create'))
            ->assertOk()
            ->assertSee('value="Pencetak"', false)
            ->assertSee('Baris 2, Kuantiti: nilai tidak sah.')
            ->assertSee('name="medan_data[' . $this->id('peralatan') . '][3][kuantiti]" value="satu" class="dms-input is-invalid"', false)
            ->assertSee('data-seterusnya="4"', false)
            ->assertSee('<template class="medan-jadual-templat">', false);

        $this->actingAs($this->admin)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'tarikh_dokumen' => '2026-10-25',
            'medan_data' => [
                $this->id('peralatan') => [['jenis_peralatan' => 'Laptop', 'kuantiti' => '2', 'harga_seunit' => '3500.5']],
                $this->id('tindakan_bd') => [['_baris' => 'Emel', 'tarikh' => '2026-10-26', 'catatan' => 'Akaun dicipta'], ['_baris' => 'Intranet'], ['_baris' => 'Makluman']],
            ],
            'fail_scan' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $dokumen = Dokumen::sole();
        $this->assertSame(
            '[{"_baris":"Emel","tarikh":"2026-10-26","catatan":"Akaun dicipta"}]',
            DokumenData::where('dokumen_field_id', $this->id('tindakan_bd'))->value('nilai_data')
        );

        $this->actingAs($this->admin)->get(route('dokumen.edit', $dokumen->dokumen_id))
            ->assertOk()
            ->assertSee('value="3500.50"', false)
            ->assertSee('value="Akaun dicipta"', false)
            ->assertSee('name="medan_data[' . $this->id('tindakan_bd') . '][2][_baris]" value="Makluman"', false);

        $this->actingAs($this->admin)->get(route('dokumen.show', $dokumen->dokumen_id))
            ->assertOk()
            ->assertSee('medan-baris medan-baris-blok', false)
            ->assertSee('<td>RM 3,500.50</td>', false)
            ->assertSee('<td class="medan-jadual-label">Emel</td>', false);
    }
}
