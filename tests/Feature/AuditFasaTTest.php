<?php

namespace Tests\Feature;

use App\Models\Bahagian;
use App\Models\LogAudit;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Integriti log audit (Fasa T): pelaku sebenar, bukan user_id 1 secara lalai.
 */
class AuditFasaTTest extends TestCase
{
    use SkemaUjian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
    }

    public function test_tindakan_tanpa_log_masuk_tidak_dikreditkan_kepada_user_1(): void
    {
        $this->buatPengguna(['SUPERADMIN']); // user_id 1 wujud

        // Cth. arahan konsol / penjadual: tiada pengguna log masuk
        $bahagian = Bahagian::create(['kod_bahagian' => 'SIS', 'nama_bahagian' => 'Bahagian Sistem']);

        $log = LogAudit::where('nama_jadual', 'bahagian')->where('rekod_id', $bahagian->bahagian_id)->sole();
        $this->assertNull($log->user_id);
    }

    public function test_tindakan_pengguna_log_masuk_direkod_atas_namanya(): void
    {
        $this->buatPengguna(['SUPERADMIN']);
        $admin = $this->buatPengguna(['ADMIN']);
        $this->actingAs($admin);

        $bahagian = Bahagian::create(['kod_bahagian' => 'KEW', 'nama_bahagian' => 'Bahagian Kewangan']);

        $this->assertSame($admin->user_id, LogAudit::where('nama_jadual', 'bahagian')->where('rekod_id', $bahagian->bahagian_id)->sole()->user_id);
    }

    public function test_perubahan_medan_borang_dilog_satu_baris_setiap_medan(): void
    {
        $admin = $this->buatPengguna(['ADMIN']);
        $this->actingAs($admin)->post(route('jenis-dokumen.store'), [
            'kod_dokumen' => 'MOHON', 'nama_dokumen' => 'Borang Permohonan',
            'medan' => [
                ['nama_field' => 'Nama Pemohon', 'kod_field' => 'nama_pemohon', 'jenis_data' => 'teks', 'is_required' => '1'],
                ['nama_field' => 'Catatan', 'kod_field' => 'catatan', 'jenis_data' => 'textarea'],
            ],
        ])->assertRedirect(route('jenis-dokumen.index'));

        $logMedan = fn (string $tindakan) => LogAudit::where('nama_jadual', 'dokumen_field')->where('tindakan', $tindakan);
        $this->assertSame(2, $logMedan('Cipta')->count());
        $this->assertSame($admin->user_id, $logMedan('Cipta')->first()->user_id);

        $jenis = \App\Models\JenisDokumen::sole();
        $nama = $jenis->fields()->where('kod_field', 'nama_pemohon')->sole();
        $catatan = $jenis->fields()->where('kod_field', 'catatan')->sole();

        // Tukar label satu medan, padam medan kedua
        $this->actingAs($admin)->put(route('jenis-dokumen.update', $jenis->jenis_dokumen_id), [
            'kod_dokumen' => 'MOHON', 'nama_dokumen' => 'Borang Permohonan',
            'medan' => [['id' => $nama->dokumen_field_id, 'nama_field' => 'Nama Penuh Pemohon', 'kod_field' => 'nama_pemohon',
                'jenis_data' => 'teks', 'is_required' => '1']],
            'medan_dipadam' => (string) $catatan->dokumen_field_id,
        ])->assertRedirect(route('jenis-dokumen.index'));

        $kemaskini = $logMedan('Kemaskini')->sole();
        $this->assertSame($nama->dokumen_field_id, $kemaskini->rekod_id);
        $this->assertSame(['nama_field' => 'Nama Pemohon'], $kemaskini->data_lama);
        $this->assertSame(['nama_field' => 'Nama Penuh Pemohon'], $kemaskini->data_baharu);

        $padam = $logMedan('Padam (Soft Delete)')->sole();
        $this->assertSame($catatan->dokumen_field_id, $padam->rekod_id);

        // Simpan semula tanpa perubahan: tiada log baharu
        $bil = LogAudit::where('nama_jadual', 'dokumen_field')->count();
        $this->actingAs($admin)->put(route('jenis-dokumen.update', $jenis->jenis_dokumen_id), [
            'kod_dokumen' => 'MOHON', 'nama_dokumen' => 'Borang Permohonan',
            'medan' => [['id' => $nama->dokumen_field_id, 'nama_field' => 'Nama Penuh Pemohon', 'kod_field' => 'nama_pemohon',
                'jenis_data' => 'teks', 'is_required' => '1']],
        ]);
        $this->assertSame($bil, LogAudit::where('nama_jadual', 'dokumen_field')->count());
    }

    public function test_kemaskini_hanya_rekod_lajur_yang_berubah(): void
    {
        $this->actingAs($this->buatPengguna(['SUPERADMIN']));
        $logKemaskini = fn (string $jadual) => LogAudit::where('nama_jadual', $jadual)->where('tindakan', 'Kemaskini')->sole();

        $bahagian = Bahagian::create(['kod_bahagian' => 'KEW', 'nama_bahagian' => 'Bahagian Kewangan']);
        $bahagian->update(['nama_bahagian' => 'Bahagian Kewangan & Akaun']);
        $log = $logKemaskini('bahagian');
        $this->assertSame(['nama_bahagian' => 'Bahagian Kewangan'], $log->data_lama);
        $this->assertSame(['nama_bahagian' => 'Bahagian Kewangan & Akaun'], $log->data_baharu);

        $jenis = \App\Models\JenisDokumen::create(['kod_dokumen' => 'MEMO', 'nama_dokumen' => 'Memo']);
        $jenis->update(['nama_dokumen' => 'Memo Dalaman']);
        $this->assertSame(['nama_dokumen' => 'Memo'], $logKemaskini('jenis_dokumen')->data_lama);

        // Pengguna: lajur sensitif kekal ditapis
        $staf = $this->buatPengguna(['STAFF'], ['nama_staff' => 'Nama Lama']);
        $staf->update(['nama_staff' => 'Nama Baharu', 'password' => 'Baharu123']);
        $log = $logKemaskini('users');
        $this->assertSame(['nama_staff' => 'Nama Lama'], $log->data_lama);
        $this->assertSame(['nama_staff' => 'Nama Baharu'], $log->data_baharu);

        // Simpan tanpa perubahan bermakna: tiada log baharu
        $bahagian->touch();
        $this->assertSame(1, LogAudit::where('nama_jadual', 'bahagian')->where('tindakan', 'Kemaskini')->count());
    }

    public function test_log_audit_papar_sistem_bagi_log_tanpa_pengguna(): void
    {
        $audit = $this->buatPengguna(['AUDIT']);
        LogAudit::create(['user_id' => null, 'tindakan' => 'Cipta', 'nama_jadual' => 'bahagian', 'rekod_id' => 99, 'created_at' => now()]);

        $this->actingAs($audit)->get(route('log-audit.index'))->assertOk()
            ->assertSee('Sistem / tetamu');
    }
}
