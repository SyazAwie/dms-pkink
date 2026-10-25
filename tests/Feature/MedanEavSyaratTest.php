<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\User;
use App\Support\JenisMedan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Langkah 7 medan EAV: medan bersyarat ("tunjuk jika", cth. Nama Syarikat Khidmat
 * Luar hanya bila Diselenggara Oleh = Khidmat Luar) dan amaran tukar jenis data (#16).
 */
class MedanEavSyaratTest extends TestCase
{
    use SkemaUjian;

    private User $admin;
    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('local');
        $this->admin = $this->buatPengguna(['ADMIN'], ['bahagian_id' => $this->buatBahagian()->bahagian_id]);

        $this->actingAs($this->admin)->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => 'WEB-KUMP', 'nama_dokumen' => 'Laman Web Kumpulan',
            'medan' => $this->barisAsas(),
        ])->assertSessionHasNoErrors();

        $this->jenis = JenisDokumen::with('fields')->sole();
    }

    private function barisAsas(array $ubah = []): array
    {
        return array_replace_recursive([
            ['nama_field' => 'Diselenggara Oleh', 'kod_field' => 'diselenggara_oleh', 'jenis_data' => 'radio', 'pilihan' => 'Sendiri, Khidmat Luar', 'is_required' => '1'],
            ['nama_field' => 'Nama Syarikat Khidmat Luar', 'kod_field' => 'syarikat_luar', 'jenis_data' => 'teks', 'is_required' => '1',
                'tunjuk_kod' => 'diselenggara_oleh', 'tunjuk_nilai' => 'Khidmat Luar'],
            ['nama_field' => 'Catatan', 'kod_field' => 'catatan', 'jenis_data' => 'teks'],
        ], $ubah);
    }

    private function medan(string $kod): DokumenField
    {
        return $this->jenis->fields->firstWhere('kod_field', $kod);
    }

    private function id(string $kod): int
    {
        return $this->medan($kod)->dokumen_field_id;
    }

    private function muatNaik(array $medanData)
    {
        return $this->actingAs($this->admin)->post(route('dokumen.store'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'tarikh_dokumen' => '2026-10-25',
            'medan_data' => $medanData,
            'fail_scan' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
        ]);
    }

    public function test_pembina_simpan_syarat(): void
    {
        $this->assertSame(['kod' => 'diselenggara_oleh', 'nilai' => 'Khidmat Luar'], $this->medan('syarikat_luar')->tetapan['tunjuk_jika']);
        $this->assertNull($this->medan('catatan')->tetapan);
    }

    public function test_pembina_tolak_syarat_tidak_sah(): void
    {
        $kes = [
            'kod tiada' => [[1 => ['tunjuk_kod' => 'tiada_medan']], 'kod medan "tiada_medan" tidak wujud'],
            'diri sendiri' => [[1 => ['tunjuk_kod' => 'syarikat_luar']], 'tidak boleh bergantung pada dirinya sendiri'],
            'sumber bukan pilihan' => [[1 => ['tunjuk_kod' => 'catatan']], 'mesti jenis Dropdown, Radio, Kotak Semak atau Ya/Tidak'],
            'nilai bukan pilihan' => [[1 => ['tunjuk_nilai' => 'Vendor']], 'nilai "Vendor" bukan salah satu pilihan'],
        ];

        foreach ($kes as $nama => [$ubah, $mesej]) {
            $res = $this->actingAs($this->admin)->post(route('jenis-dokumen.store'), [
                'kod_dokumen' => 'X' . crc32($nama), 'nama_dokumen' => 'Ujian', 'medan' => $this->barisAsas($ubah),
            ]);
            $res->assertSessionHasErrors('medan');
            $this->assertStringContainsString($mesej, session('errors')->first('medan'), $nama);
        }
    }

    public function test_medan_tersembunyi_tidak_disahkan_dan_tidak_disimpan(): void
    {
        // "Sendiri": medan syarikat (wajib) tersembunyi, jadi kosong pun lulus; nilai yang dihantar diabaikan
        $this->muatNaik([
            $this->id('diselenggara_oleh') => 'Sendiri',
            $this->id('syarikat_luar') => 'Nilai yang sepatutnya tersembunyi',
        ])->assertSessionHasNoErrors();

        $this->assertNull(DokumenData::where('dokumen_field_id', $this->id('syarikat_luar'))->value('nilai_data'));

        // "Khidmat Luar": medan dipaparkan, jadi wajib diisi
        $this->muatNaik([$this->id('diselenggara_oleh') => 'Khidmat Luar'])
            ->assertSessionHasErrors("medan_data.{$this->id('syarikat_luar')}");

        $this->muatNaik([$this->id('diselenggara_oleh') => 'Khidmat Luar', $this->id('syarikat_luar') => 'Syarikat ABC Sdn Bhd'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Syarikat ABC Sdn Bhd', DokumenData::where('dokumen_field_id', $this->id('syarikat_luar'))->value('nilai_data'));
    }

    public function test_kemas_kini_sembunyikan_medan_buang_nilai_lama(): void
    {
        $this->muatNaik([$this->id('diselenggara_oleh') => 'Khidmat Luar', $this->id('syarikat_luar') => 'Syarikat ABC'])->assertSessionHasNoErrors();
        $dokumen = Dokumen::sole();

        $this->actingAs($this->admin)->put(route('dokumen.update', $dokumen->dokumen_id), [
            'tarikh_dokumen' => '2026-10-25',
            'medan_data' => [$this->id('diselenggara_oleh') => 'Sendiri'],
        ])->assertSessionHasNoErrors();

        $this->assertFalse(DokumenData::where('dokumen_field_id', $this->id('syarikat_luar'))->exists());
    }

    public function test_borang_papar_atribut_syarat_dan_kelihatan_untuk_kotak_semak(): void
    {
        $this->actingAs($this->admin)->get(route('jenis-dokumen.medan', $this->jenis->jenis_dokumen_id))
            ->assertOk()
            ->assertSee('data-kod="syarikat_luar" data-tunjuk-kod="diselenggara_oleh" data-tunjuk-nilai="Khidmat Luar"', false)
            ->assertSee('data-kod="diselenggara_oleh"', false);

        // Sumber kotak semak: dipaparkan jika nilai syarat antara yang ditanda
        $sumber = new DokumenField(['kod_field' => 'kategori', 'jenis_data' => 'kotak_semak']);
        $sumber->dokumen_field_id = 1;
        $bergantung = new DokumenField(['kod_field' => 'lain', 'jenis_data' => 'teks', 'tetapan' => ['tunjuk_jika' => ['kod' => 'kategori', 'nilai' => 'Galeri']]]);
        $bergantung->dokumen_field_id = 2;

        $this->assertTrue(JenisMedan::kelihatan($bergantung, [$sumber, $bergantung], [1 => ['Berita', 'Galeri']]));
        $this->assertFalse(JenisMedan::kelihatan($bergantung, [$sumber, $bergantung], [1 => ['Berita']]));
        $this->assertTrue(JenisMedan::kelihatan($bergantung, [$bergantung], []), 'Sumber tiada: sentiasa dipaparkan');
    }

    public function test_tukar_jenis_dengan_nilai_tidak_serasi_perlu_pengesahan(): void
    {
        $this->muatNaik([$this->id('diselenggara_oleh') => 'Sendiri', $this->id('catatan') => 'bukan nombor'])->assertSessionHasNoErrors();
        $catatan = $this->medan('catatan');

        // Halaman edit memberi bilangan nilai kepada pembina
        $this->actingAs($this->admin)->get(route('jenis-dokumen.edit', $this->jenis->jenis_dokumen_id))
            ->assertOk()
            ->assertSee('"bil_nilai":1,"jenis_asal":"teks"', false);

        $baris = $this->barisAsas();
        foreach ($baris as $i => $b) {
            $baris[$i]['id'] = $this->id($b['kod_field']);
        }
        $baris[2]['jenis_data'] = 'nombor';
        $hantar = fn (array $medan) => $this->actingAs($this->admin)->put(route('jenis-dokumen.update', $this->jenis->jenis_dokumen_id), [
            'kod_dokumen' => 'WEB-KUMP', 'nama_dokumen' => 'Laman Web Kumpulan', 'medan' => $medan,
        ]);

        $hantar($baris)->assertSessionHasErrors('medan');
        $this->assertStringContainsString('Medan "Catatan": 1 nilai sedia ada tidak serasi dengan jenis "Nombor"', session('errors')->first('medan'));
        $this->assertSame('teks', $catatan->fresh()->jenis_data);

        $baris[2]['sahkan_tukar'] = '1';
        $hantar($baris)->assertSessionHasNoErrors();
        $this->assertSame('nombor', $catatan->fresh()->jenis_data);
        $this->assertSame('bukan nombor', DokumenData::where('dokumen_field_id', $catatan->dokumen_field_id)->value('nilai_data'), 'Nilai lama tidak dipadam');
    }

    public function test_tukar_jenis_serasi_tidak_perlu_pengesahan(): void
    {
        $this->muatNaik([$this->id('diselenggara_oleh') => 'Khidmat Luar', $this->id('syarikat_luar') => 'ABC'])->assertSessionHasNoErrors();

        $baris = $this->barisAsas();
        foreach ($baris as $i => $b) {
            $baris[$i]['id'] = $this->id($b['kod_field']);
        }
        // Radio → kotak semak: nilai "Khidmat Luar" kekal sah sebagai satu pilihan
        $baris[0]['jenis_data'] = 'kotak_semak';

        $this->actingAs($this->admin)->put(route('jenis-dokumen.update', $this->jenis->jenis_dokumen_id), [
            'kod_dokumen' => 'WEB-KUMP', 'nama_dokumen' => 'Laman Web Kumpulan', 'medan' => $baris,
        ])->assertSessionHasNoErrors();

        $this->assertSame('kotak_semak', $this->medan('diselenggara_oleh')->fresh()->jenis_data);
    }
}
