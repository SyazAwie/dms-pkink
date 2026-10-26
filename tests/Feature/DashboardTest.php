<?php

namespace Tests\Feature;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\User;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Papan pemuka ikut peranan: setiap peranan nampak bahagiannya sendiri sahaja.
 */
class DashboardTest extends TestCase
{
    use SkemaUjian;

    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'MOHON', 'nama_dokumen' => 'Borang Permohonan']);
    }

    private function dokumen(User $oleh, ?string $status = null, array $atribut = []): Dokumen
    {
        static $n = 0;
        $n++;
        $d = Dokumen::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => sprintf('MOHON/2026/%04d', $n),
            'tarikh_dokumen' => '2026-10-26', 'bahagian_id' => $oleh->bahagian_id, 'created_by' => $oleh->user_id,
        ] + $atribut);
        if ($status) {
            Borang::create(['dokumen_id' => $d->dokumen_id, 'pemohon_id' => $oleh->user_id,
                'status_permohonan' => $status, 'tarikh_permohonan' => now()]);
        }

        return $d;
    }

    public function test_staf_nampak_permohonan_sendiri_dan_tindakan_pantas(): void
    {
        $staf = $this->buatPengguna(['STAFF'], ['nama_staff' => 'Siti Aminah binti Yusof']);
        $lain = $this->buatPengguna(['STAFF']);
        $this->dokumen($staf, 'Ditolak');
        $this->dokumen($staf, 'Dalam Proses');
        $this->dokumen($staf, 'Disokong');
        $this->dokumen($staf, 'Diluluskan');
        $this->dokumen($staf);                       // belum dihantar
        $this->dokumen($lain, 'Ditolak');           // milik orang lain: tidak dikira

        $res = $this->actingAs($staf)->get(route('dashboard'))->assertOk();

        $res->assertSee(', Siti Aminah</h2>', false)
            ->assertSee('Permohonan Saya')
            ->assertSee(route('isi-borang.index'))
            ->assertSee(route('imbas-hantar.create'))
            ->assertDontSee(route('imbas-arkib.create'))
            ->assertSeeInOrder(['1', 'Perlu Tindakan', '2', 'Sedang Diproses', '1', 'Diluluskan', '1', 'Belum Dihantar'])
            ->assertSee('Semak ulasan &amp; hantar semula', false)
            ->assertSee('Belum dihantar untuk kelulusan')
            // Kad lama yang tidak berkaitan dengan staf tidak lagi dipaparkan
            ->assertDontSee('Fail Menunggu OCR');
    }

    public function test_staf_tanpa_dokumen_nampak_keadaan_kosong(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Anda belum mengisi sebarang borang.');
    }
}
