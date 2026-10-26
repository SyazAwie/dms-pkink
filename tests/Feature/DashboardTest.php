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

    public function test_pengurus_nampak_bahagian_sendiri_sahaja(): void
    {
        $dig = $this->buatBahagian('DIG', 'Bahagian Digital');
        $kew = $this->buatBahagian('KEW', 'Bahagian Kewangan');
        $pengurus = $this->buatPengguna(['PENGURUS'], ['bahagian_id' => $dig->bahagian_id]);
        $stafDig = $this->buatPengguna(['STAFF'], ['nama_staff' => 'Pemohon Digital', 'bahagian_id' => $dig->bahagian_id]);
        $stafKew = $this->buatPengguna(['STAFF'], ['nama_staff' => 'Pemohon Kewangan', 'bahagian_id' => $kew->bahagian_id]);
        $this->dokumen($stafDig, 'Dalam Proses');
        $this->dokumen($stafDig, 'Disokong');
        $this->dokumen($stafDig, 'Ditolak');
        $this->dokumen($stafKew, 'Dalam Proses');   // bahagian lain: tidak dikira

        $this->actingAs($pengurus)->get(route('dashboard'))->assertOk()
            ->assertSee('Bahagian Digital')
            ->assertSeeInOrder(['3', 'Dokumen Bulan Ini', '2', 'Menunggu Kelulusan', '0', 'Diluluskan Bulan Ini', '1', 'Ditolak'])
            ->assertSee('Pemohon Digital')
            ->assertDontSee('Pemohon Kewangan')
            ->assertSee('Kategori Teratas')
            ->assertSee('Borang Permohonan')
            ->assertDontSee('Fail Menunggu OCR');
    }

    public function test_kerani_nampak_kerja_arkib(): void
    {
        $kerani = $this->buatPengguna(['KERANI']);
        $staf = $this->buatPengguna(['STAFF']);
        $arkib = $this->dokumen($kerani);
        Borang::create(['dokumen_id' => $arkib->dokumen_id, 'pemohon_id' => $kerani->user_id,
            'status_permohonan' => 'Diluluskan', 'tarikh_permohonan' => now(), 'tarikh_kelulusan' => now()]);
        $this->dokumen($staf, 'Dalam Proses');
        \App\Models\DokumenScan::create(['dokumen_id' => $arkib->dokumen_id, 'nama_fail' => 'a.pdf', 'lokasi_fail' => 'x/a.pdf', 'status_ocr' => 'Pending']);

        $this->actingAs($kerani)->get(route('dashboard'))->assertOk()
            ->assertSee('Arkib Dokumen')
            // Tindakan pantas: Imbas & Arkib dahulu sebagai butang utama
            ->assertSeeInOrder(['pp-pantas', 'href="' . route('imbas-arkib.create') . '" class="utama"', route('isi-borang.index')], false)
            ->assertSeeInOrder(['1', 'Dimuat Naik Bulan Ini', '1', 'Diarkib Bulan Ini', '1', 'Fail Belum Di-OCR', '2', 'Jumlah Dokumen'])
            ->assertSee('Dokumen Terkini')
            ->assertDontSee('Permohonan Saya');
    }

    public function test_penyokong_nampak_giliran_paling_lama_dahulu(): void
    {
        $penyokong = $this->buatPengguna(['PENYOKONG']);
        $staf = $this->buatPengguna(['STAFF'], ['nama_staff' => 'Pemohon Lama']);
        $lama = $this->dokumen($staf, 'Dalam Proses');
        Borang::where('dokumen_id', $lama->dokumen_id)->update(['tarikh_permohonan' => now()->subDays(5)]);
        $this->dokumen($staf, 'Dalam Proses');
        $this->dokumen($staf, 'Disokong');                 // giliran Pelulus: tidak dikira

        $this->actingAs($penyokong)->get(route('dashboard'))->assertOk()
            ->assertSee('Giliran Sokongan')
            ->assertSeeInOrder(['2', 'Menunggu Sokongan', '1', 'Tertunggak &gt; 3 Hari', '5', 'hari', 'Paling Lama Menunggu'], false)
            ->assertSeeInOrder([$lama->no_rujukan, '5 hari', 'Hari ini'])
            ->assertSee(route('kelulusan.index'))
            ->assertDontSee('Giliran Kelulusan')
            ->assertDontSee('Permohonan Saya');
    }

    public function test_pelulus_tanpa_giliran_nampak_keadaan_kosong(): void
    {
        $this->actingAs($this->buatPengguna(['PELULUS']))->get(route('dashboard'))->assertOk()
            ->assertSee('Giliran Kelulusan')
            ->assertSee('Tiada permohonan menunggu kelulusan anda.');
    }

    public function test_audit_nampak_aktiviti_dan_tindakan_padam(): void
    {
        $audit = $this->buatPengguna(['AUDIT']);
        $admin = $this->buatPengguna(['ADMIN'], ['nama_staff' => 'Pentadbir Ujian']);
        foreach (['Cipta', 'Kemaskini', 'Padam (Soft Delete)'] as $t) {
            \App\Models\LogAudit::create(['user_id' => $admin->user_id, 'tindakan' => $t, 'nama_jadual' => 'dokumen',
                'rekod_id' => 1, 'created_at' => now()]);
        }

        $this->actingAs($audit)->get(route('dashboard'))->assertOk()
            ->assertSee('Aktiviti Sistem')
            // Bilangan log termasuk log UserObserver semasa pengguna ujian dicipta
            ->assertSeeInOrder(['Log Hari Ini', 'Log 7 Hari', '<div class="pp-kad-nilai">1', 'Tindakan Padam', 'Pengguna Aktif'], false)
            ->assertSee('Pentadbir Ujian')
            ->assertSee('Padam (Soft Delete)')
            ->assertSee(route('log-audit.index'));
    }

    public function test_staf_tanpa_dokumen_nampak_keadaan_kosong(): void
    {
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Anda belum mengisi sebarang borang.');
    }
}
