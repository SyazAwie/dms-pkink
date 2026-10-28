<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\User;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Fasa P kumpulan 2: penapis berstruktur EAV (P8) dan seterusnya.
 */
class FasaPKumpulan2Test extends TestCase
{
    use SkemaUjian;

    private User $admin;

    private JenisDokumen $jenis;

    private DokumenField $harga;

    private DokumenField $tarikh;

    private DokumenField $jenama;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->admin = $this->buatPengguna(['ADMIN']);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'INV', 'nama_dokumen' => 'Invois']);
        $medan = fn ($nama, $kod, $jenis, $pilihan = null) => DokumenField::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
            'nama_field' => $nama, 'kod_field' => $kod, 'jenis_data' => $jenis, 'pilihan' => $pilihan]);
        $this->harga = $medan('Jumlah', 'jumlah', 'wang');
        $this->tarikh = $medan('Tarikh Invois', 'tarikh_invois', 'tarikh');
        $this->jenama = $medan('Jenama', 'jenama', 'dropdown', ['Dell', 'Lenovo', 'HP']);
        $medan('Catatan', 'catatan', 'teks');
    }

    private function invois(string $rujukan, string $jumlah, string $tarikh, string $jenama): Dokumen
    {
        $d = Dokumen::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => $rujukan,
            'tarikh_dokumen' => '2026-10-27', 'created_by' => $this->admin->user_id]);
        foreach ([[$this->harga, $jumlah], [$this->tarikh, $tarikh], [$this->jenama, $jenama]] as [$m, $v]) {
            DokumenData::create(['dokumen_id' => $d->dokumen_id, 'dokumen_field_id' => $m->dokumen_field_id, 'nilai_data' => $v]);
        }

        return $d;
    }

    private function senarai(array $medan): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->get(route('dokumen.index', ['jenis' => $this->jenis->jenis_dokumen_id, 'medan' => $medan]));
    }

    public function test_penapis_julat_nombor_secara_numerik(): void
    {
        $this->invois('INV-SATU', '950.00', '2026-01-10', 'Dell');
        $this->invois('INV-DUA', '1200.00', '2026-03-05', 'Lenovo');
        $this->invois('INV-TIGA', '15000.00', '2026-06-20', 'HP');

        // "950" > "15000" sebagai teks; mesti dibanding sebagai nombor
        $this->senarai([$this->harga->dokumen_field_id => ['dari' => '1000', 'hingga' => '2000']])->assertOk()
            ->assertSee('INV-DUA')->assertDontSee('INV-SATU')->assertDontSee('INV-TIGA')->assertSee('Jumlah: 1000 – 2000');
    }

    public function test_penapis_julat_tarikh_dan_pilihan(): void
    {
        $this->invois('INV-SATU', '950.00', '2026-01-10', 'Dell');
        $this->invois('INV-DUA', '1200.00', '2026-03-05', 'Lenovo');
        $this->invois('INV-TIGA', '15000.00', '2026-06-20', 'Lenovo');

        $this->senarai([$this->tarikh->dokumen_field_id => ['dari' => '2026-02-01']])
            ->assertSee('INV-DUA')->assertSee('INV-TIGA')->assertDontSee('INV-SATU');
        $this->senarai([$this->tarikh->dokumen_field_id => ['dari' => '2026-02-01'], $this->jenama->dokumen_field_id => ['sama' => 'Lenovo']])
            ->assertSee('INV-DUA')->assertSee('INV-TIGA');
        $this->senarai([$this->jenama->dokumen_field_id => ['sama' => 'Dell']])
            ->assertSee('INV-SATU')->assertDontSee('INV-DUA')->assertDontSee('INV-TIGA');
    }

    public function test_panel_hanya_medan_boleh_tapis_dan_validasi(): void
    {
        $this->senarai([])->assertSee('Penapis medan: Invois')->assertSee('Tarikh Invois')->assertDontSee('name="medan['.($this->jenama->dokumen_field_id + 1).']', false);

        $this->senarai([$this->harga->dokumen_field_id => ['dari' => 'abc']])->assertSessionHasErrors('medan.'.$this->harga->dokumen_field_id.'.dari');
        $this->senarai([$this->harga->dokumen_field_id => ['dari' => '500', 'hingga' => '100']])->assertSessionHasErrors('medan.'.$this->harga->dokumen_field_id.'.hingga');
    }

    public function test_eksport_ikut_penapis_medan(): void
    {
        $this->invois('INV-SATU', '950.00', '2026-01-10', 'Dell');
        $this->invois('INV-DUA', '1200.00', '2026-03-05', 'Lenovo');

        $csv = $this->actingAs($this->admin)->get(route('dokumen.eksport', ['jenis' => $this->jenis->jenis_dokumen_id,
            'medan' => [$this->jenama->dokumen_field_id => ['sama' => 'Lenovo']]]))->streamedContent();
        $this->assertStringContainsString('INV-DUA', $csv);
        $this->assertStringNotContainsString('INV-SATU', $csv);
    }
}
