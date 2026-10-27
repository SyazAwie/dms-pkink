<?php

namespace Tests\Feature;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\User;
use App\Notifications\NotifikasiDms;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Fasa P kumpulan 1: Tong Sampah (P3), Status Sistem (P1), emel (P2), penapis Kelulusan (P10),
 * eksport CSV (P9/P14), amaran padam beramai (P14), carian teks OCR (P7).
 */
class FasaPKumpulan1Test extends TestCase
{
    use SkemaUjian;

    private User $admin;

    private User $staf;

    private JenisDokumen $jenis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        $this->admin = $this->buatPengguna(['ADMIN'], ['nama_staff' => 'Pentadbir Utama']);
        $this->staf = $this->buatPengguna(['STAFF'], ['nama_staff' => 'Ahmad Hakim']);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon Komputer']);
    }

    private function dokumen(string $rujukan, ?User $pemilik = null, array $atribut = []): Dokumen
    {
        return Dokumen::create(array_merge(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => $rujukan,
            'tarikh_dokumen' => '2026-10-27', 'perkara' => 'Ujian', 'created_by' => ($pemilik ?? $this->staf)->user_id], $atribut));
    }

    // ---------- P3 Tong Sampah ----------

    public function test_tong_sampah_senarai_dan_pulih_dokumen(): void
    {
        $d = $this->dokumen('BPK/2026/0001');
        $this->actingAs($this->admin);
        $d->delete();

        $this->get(route('tong-sampah.index'))->assertOk()->assertSee('BPK/2026/0001')->assertSee('Pentadbir Utama');

        $this->post(route('tong-sampah.pulih', ['dokumen', $d->dokumen_id]))->assertRedirect()
            ->assertSessionHas('success', 'Dokumen "BPK/2026/0001" telah dipulihkan.');
        $this->assertNull($d->fresh()->deleted_at);
        $this->assertSame(1, LogAudit::where('tindakan', 'Pulihkan')->where('nama_jadual', 'dokumen')->count());
        // restoreQuietly: tiada log "Kemaskini" berganda daripada observer
        $this->assertSame(0, LogAudit::where('tindakan', 'like', 'Kemaskini%')->where('nama_jadual', 'dokumen')->count());
    }

    public function test_tong_sampah_hanya_untuk_pentadbir(): void
    {
        $this->actingAs($this->staf)->get(route('tong-sampah.index'))->assertForbidden();
        $d = $this->dokumen('BPK/2026/0002');
        $d->delete();
        $this->actingAs($this->staf)->post(route('tong-sampah.pulih', ['dokumen', $d->dokumen_id]))->assertForbidden();
        $this->assertNotNull($d->fresh()->deleted_at);
    }

    public function test_admin_tidak_boleh_pulih_akaun_super_admin(): void
    {
        $super = $this->buatPengguna(['SUPERADMIN']);
        $super->delete();

        $this->actingAs($this->admin)->post(route('tong-sampah.pulih', ['kakitangan', $super->user_id]))->assertForbidden();
        $this->assertNotNull(User::withTrashed()->find($super->user_id)->deleted_at);
    }

    public function test_pulih_kategori_turut_memulihkan_medannya(): void
    {
        $f = DokumenField::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'Nama', 'kod_field' => 'nama']);
        $this->actingAs($this->admin);
        $this->jenis->delete();
        $f->delete();

        $this->post(route('tong-sampah.pulih', ['kategori', $this->jenis->jenis_dokumen_id]))->assertRedirect();
        $this->assertNull($this->jenis->fresh()->deleted_at);
        $this->assertNull($f->fresh()->deleted_at);
    }

    // ---------- P1 Status Sistem ----------

    public function test_status_sistem_dipaparkan_kepada_pentadbir(): void
    {
        Process::fake([
            '*--version*' => Process::result('tesseract v5.4.0'),
            '*--list-langs*' => Process::result("List of available languages:\r\neng\r\nmsa\r\n"), // CRLF seperti Windows
            '*' => Process::result('', 'pdftoppm version 24.08.0'),
        ]);
        Http::fake(['*/api/tags' => Http::response(['models' => [['name' => config('services.ollama.model')]]]),
            '*/api/ps' => Http::response(['models' => []])]);

        $this->actingAs($this->admin)->get(route('status-sistem.index'))->assertOk()
            ->assertSee('Status Sistem')->assertSee('Pangkalan data')->assertSee('v5.4.0')->assertSee('v24.08.0')
            ->assertSee('Penjadual (Task Scheduler)')->assertSee('Notifikasi emel')->assertDontSee('Pek bahasa tiada');

        $this->actingAs($this->staf)->get(route('status-sistem.index'))->assertForbidden();
    }

    public function test_status_sistem_lapor_ollama_tidak_berjalan(): void
    {
        Process::fake();
        Http::fake(fn () => throw new ConnectionException('ditolak'));

        $this->actingAs($this->admin)->get(route('status-sistem.index'))->assertOk()->assertSee('Tidak berjalan');
    }

    // ---------- P2 Emel ----------

    public function test_emel_hanya_bila_dihidupkan(): void
    {
        $n = new NotifikasiDms('ujian', 'Tajuk', 'Mesej', 'http://localhost');

        config(['services.notifikasi.emel' => false]);
        $this->assertNotContains('mail', $n->via($this->staf));

        config(['services.notifikasi.emel' => true]);
        $this->assertContains('mail', $n->via($this->staf));
        $mel = $n->toMail($this->staf);
        $this->assertSame('DMS PKINK: Tajuk', $mel->subject);
        $this->assertSame('http://localhost', $mel->actionUrl);
    }

    // ---------- P10 Penapis Kelulusan ----------

    public function test_penapis_status_dan_carian_kelulusan(): void
    {
        $a = $this->dokumen('BPK/2026/0010');
        $b = $this->dokumen('BPK/2026/0011');
        Borang::create(['dokumen_id' => $a->dokumen_id, 'pemohon_id' => $this->staf->user_id, 'status_permohonan' => 'Dalam Proses', 'tarikh_permohonan' => now()]);
        Borang::create(['dokumen_id' => $b->dokumen_id, 'pemohon_id' => $this->staf->user_id, 'status_permohonan' => 'Diluluskan', 'tarikh_permohonan' => now()]);

        $this->actingAs($this->admin)->get(route('kelulusan.index', ['status' => 'Diluluskan']))->assertOk()
            ->assertSee('BPK/2026/0011')->assertDontSee('BPK/2026/0010');
        $this->get(route('kelulusan.index', ['q' => '0010']))->assertOk()
            ->assertSee('BPK/2026/0010')->assertDontSee('BPK/2026/0011');
        $this->get(route('kelulusan.index', ['status' => 'Palsu']))->assertSessionHasErrors('status');
    }

    // ---------- P9 / P14 Eksport CSV ----------

    public function test_eksport_dokumen_ikut_skop_peranan(): void
    {
        $this->dokumen('BPK/2026/0020');
        $this->dokumen('BPK/2026/0021', $this->admin);

        $csv = $this->actingAs($this->staf)->get(route('dokumen.eksport'))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('No. Rujukan', $csv);
        $this->assertStringContainsString('BPK/2026/0020', $csv);
        $this->assertStringNotContainsString('BPK/2026/0021', $csv); // STAFF: dokumen sendiri sahaja
        $this->assertSame(1, LogAudit::where('tindakan', 'Eksport CSV')->where('nama_jadual', 'dokumen')->count());
    }

    public function test_eksport_log_audit_untuk_juruaudit_sahaja(): void
    {
        $audit = $this->buatPengguna(['AUDIT']);
        $this->actingAs($this->staf)->get(route('log-audit.eksport'))->assertForbidden();

        $csv = $this->actingAs($audit)->get(route('log-audit.eksport'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Tindakan', $csv);
    }

    // ---------- P14 Amaran padam beramai ----------

    public function test_amaran_sekali_bila_banyak_dokumen_dipadam(): void
    {
        $this->actingAs($this->admin);
        foreach (range(1, 6) as $i) {
            $this->dokumen(sprintf('BPK/2026/%04d', 30 + $i))->delete();
        }

        $amaran = DatabaseNotification::where('data->jenis', 'amaran_sistem')->get();
        $this->assertCount(1, $amaran); // tepat pada ambang, bukan setiap padam selepasnya
        $this->assertStringContainsString('Pentadbir Utama telah memadam 5 rekod', $amaran->first()->data['mesej']);
    }

    // ---------- P7 Carian teks OCR ----------

    public function test_carian_merangkumi_teks_ocr(): void
    {
        $d = $this->dokumen('BPK/2026/0040');
        $scan = $d->scans()->create(['nama_fail' => 'imbasan.pdf', 'lokasi_fail' => 'x/imbasan.pdf']);
        $scan->ocr()->create(['teks_dibaca' => 'Permohonan pinjaman komputer riba jenama Lenovo']);
        $this->dokumen('BPK/2026/0041');

        $this->actingAs($this->admin)->get(route('dokumen.index', ['q' => 'Lenovo']))->assertOk()
            ->assertSee('BPK/2026/0040')->assertDontSee('BPK/2026/0041');
    }
}
