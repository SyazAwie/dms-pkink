<?php

namespace Tests\Feature;

use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\User;
use App\Support\JenisMedan;
use App\Support\MedanJadual;
use App\Support\TemplatKategori;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Langkah 5 medan EAV: templat permulaan (memo, surat, minit mesyuarat, borang),
 * salin medan kategori sedia ada dan cadangan Kategori Kumpulan.
 */
class TemplatKategoriTest extends TestCase
{
    use SkemaUjian;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->admin = $this->buatPengguna(['ADMIN']);
    }

    public static function senaraiTemplat(): array
    {
        return array_map(fn ($kod) => [$kod], array_combine(array_keys(TemplatKategori::senarai()), array_keys(TemplatKategori::senarai())));
    }

    #[DataProvider('senaraiTemplat')]
    public function test_setiap_templat_boleh_disimpan_melalui_pembina(string $kod): void
    {
        $templat = TemplatKategori::senarai()[$kod];

        // Hantar seperti pembina: baris templat terus sebagai medan[]
        $this->actingAs($this->admin)->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => strtoupper(substr($kod, 0, 10)),
            'nama_dokumen' => $templat['nama'],
            'kategori' => $templat['kategori'],
            'medan' => $templat['medan'],
        ])->assertSessionHasNoErrors()->assertRedirect(route('jenis-dokumen.index'));

        $medan = JenisDokumen::with('fields')->sole()->fields;
        $this->assertCount(count($templat['medan']), $medan);

        foreach ($medan as $i => $f) {
            $this->assertSame($templat['medan'][$i]['jenis_data'], $f->jenis_data, "Jenis medan {$f->nama_field}");
            $this->assertContains($f->jenis_data, JenisMedan::kod());
            if ($f->jenis_data === 'jadual') {
                $this->assertCount(count($templat['medan'][$i]['lajur']), MedanJadual::lajur($f), "Lajur {$f->nama_field}");
            }
            if (in_array($f->jenis_data, JenisMedan::ADA_PILIHAN, true)) {
                $this->assertNotEmpty($f->pilihan, "Pilihan {$f->nama_field}");
            }
        }

        $kod = $medan->pluck('kod_field');
        $this->assertSame($kod->count(), $kod->unique()->count(), 'Kod medan unik');
    }

    public function test_halaman_cipta_papar_templat_salinan_dan_cadangan_kategori(): void
    {
        $sedia = JenisDokumen::create(['kod_dokumen' => 'BRG-EMEL', 'nama_dokumen' => 'Borang Pendaftaran Emel', 'kategori' => 'Digital Kumpulan']);
        DokumenField::create([
            'jenis_dokumen_id' => $sedia->jenis_dokumen_id, 'nama_field' => 'Pendaftaran', 'kod_field' => 'pendaftaran',
            'jenis_data' => 'kotak_semak', 'pilihan' => ['Emel', 'Intranet'], 'tetapan' => ['lain' => true], 'susunan' => 1,
        ]);

        $res = $this->actingAs($this->admin)->get(route('jenis-dokumen.create'))->assertOk();

        $res->assertSee('<option value="templat:minit_mesyuarat">Minit Mesyuarat (12 medan)</option>', false)
            ->assertSee('<option value="templat:memo">', false)
            ->assertSee('<option value="salin:' . $sedia->jenis_dokumen_id . '">Borang Pendaftaran Emel (BRG-EMEL, 1 medan)</option>', false)
            ->assertSee('<datalist id="senaraiKategori">', false)
            ->assertSee('<option value="Minit Mesyuarat"></option>', false)
            ->assertSee('<option value="Digital Kumpulan"></option>', false);

        // Salinan tanpa id medan (disimpan sebagai medan baharu), tetapan dibawa bersama
        $salinan = collect(TemplatKategori::salinanKategori())->firstWhere('id', $sedia->jenis_dokumen_id);
        $this->assertSame(
            [['nama_field' => 'Pendaftaran', 'kod_field' => 'pendaftaran', 'jenis_data' => 'kotak_semak', 'is_required' => false, 'pilihan' => ['Emel', 'Intranet'], 'tetapan' => ['lain' => true]]],
            $salinan['medan']
        );

        $this->actingAs($this->admin)->get(route('jenis-dokumen.edit', $sedia->jenis_dokumen_id))
            ->assertOk()
            ->assertSee('<datalist id="senaraiKategori">', false);
    }
}
