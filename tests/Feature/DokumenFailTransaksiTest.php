<?php

namespace Tests\Feature;

use App\Models\Dokumen;
use App\Models\DokumenField;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use App\Models\User;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * K-12: Muat Naik / Edit Dokumen tidak meninggalkan fail yatim dalam storan
 * bila transaksi DB gagal (termasuk percubaan semula bila no_rujukan bertembung).
 */
class DokumenFailTransaksiTest extends TestCase
{
    use SkemaUjian;

    private User $kerani;
    private JenisDokumen $jenis;
    private DokumenField $medan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('local');

        $bahagian = $this->buatBahagian();
        $this->kerani = $this->buatPengguna(['KERANI'], ['bahagian_id' => $bahagian->bahagian_id]);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'INV', 'nama_dokumen' => 'Invois']);
        $this->medan = DokumenField::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'No. Invois',
            'kod_field' => 'no_invois', 'jenis_data' => 'teks', 'is_required' => true, 'susunan' => 1,
        ]);
    }

    private function dataMuatNaik(int $bilFail = 2): array
    {
        return [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
            'tarikh_dokumen' => '2025-05-01',
            'medan_data' => [$this->medan->dokumen_field_id => 'INV-001'],
            'fail_scan' => array_map(
                fn ($i) => UploadedFile::fake()->create("muka{$i}.pdf", 20, 'application/pdf'),
                range(1, $bilFail)
            ),
        ];
    }

    private function gagalPadaScanKe(int $ke, \Closure $buatRalat): void
    {
        $kali = 0;
        DokumenScan::creating(function () use (&$kali, $ke, $buatRalat) {
            if (++$kali === $ke) {
                throw $buatRalat();
            }
        });
    }

    private static function ralat1062(): QueryException
    {
        $pdo = new \PDOException("Duplicate entry 'INV/2025/0001' for key 'no_rujukan'");
        $pdo->errorInfo = ['23000', 1062, 'Duplicate entry'];

        return new QueryException('mysql', 'insert ...', [], $pdo);
    }

    public function test_muat_naik_berjaya_simpan_semua_fail(): void
    {
        $this->actingAs($this->kerani)->post(route('dokumen.store'), $this->dataMuatNaik())
            ->assertRedirect(route('dokumen.index'));

        $this->assertSame(2, DokumenScan::count());
        $this->assertCount(2, Storage::disk('local')->allFiles('dokumen_scan'));
    }

    public function test_muat_naik_gagal_padam_fail_yang_sempat_ditulis(): void
    {
        // Fail pertama sudah ditulis dan direkod; rekod fail kedua gagal
        $this->gagalPadaScanKe(2, fn () => new \RuntimeException('Simulasi DB gagal'));

        $this->actingAs($this->kerani)->post(route('dokumen.store'), $this->dataMuatNaik())
            ->assertStatus(500);

        $this->assertSame(0, Dokumen::withTrashed()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('dokumen_scan'));
    }

    public function test_cuba_semula_1062_tidak_tinggalkan_fail_percubaan_pertama(): void
    {
        $this->gagalPadaScanKe(2, fn () => self::ralat1062());

        // Tiru keadaan sebenar 1062: muat naik lain sudah mengambil INV/2025/0001,
        // jadi percubaan semula dapat 0002 dan fail ditulis ke folder BERBEZA.
        $pesaing = false;
        Event::listen(TransactionRolledBack::class, function () use (&$pesaing) {
            if (!$pesaing) {
                $pesaing = true;
                Dokumen::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => 'INV/2025/0001']);
            }
        });

        $this->actingAs($this->kerani)->post(route('dokumen.store'), $this->dataMuatNaik())
            ->assertRedirect(route('dokumen.index'));

        $this->assertSame('INV/2025/0002', Dokumen::latest('dokumen_id')->value('no_rujukan'));
        $this->assertSame(2, DokumenScan::count());
        $fail = Storage::disk('local')->allFiles('dokumen_scan');
        $this->assertEqualsCanonicalizing(DokumenScan::pluck('lokasi_fail')->all(), $fail);
        $this->assertSame([], Storage::disk('local')->allFiles('dokumen_scan/inv20250001'));
    }

    public function test_edit_gagal_padam_fail_baharu_dan_kekalkan_fail_lama(): void
    {
        $disk = Storage::disk('local');
        $dokumen = Dokumen::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => 'INV/2025/0001',
            'tarikh_dokumen' => '2025-05-01', 'status' => 'Archived',
            'bahagian_id' => $this->kerani->bahagian_id, 'created_by' => $this->kerani->user_id,
        ]);
        $disk->put('dokumen_scan/inv20250001/lama.pdf', 'fail lama');
        $scanLama = DokumenScan::create([
            'dokumen_id' => $dokumen->dokumen_id, 'nama_fail' => 'lama.pdf', 'lokasi_fail' => 'dokumen_scan/inv20250001/lama.pdf',
        ]);

        $this->gagalPadaScanKe(1, fn () => new \RuntimeException('Simulasi DB gagal'));

        $this->actingAs($this->kerani)->put(route('dokumen.update', $dokumen->dokumen_id), [
            'tarikh_dokumen' => '2025-05-02',
            'medan_data' => [$this->medan->dokumen_field_id => 'INV-002'],
            'hapus_scan' => [$scanLama->dokumen_scan_id],
            'fail_scan' => [UploadedFile::fake()->create('baharu.pdf', 20, 'application/pdf')],
        ])->assertStatus(500);

        // Transaksi dibatalkan: rekod & fail lama kekal, fail baharu tidak tertinggal
        $this->assertSame([$scanLama->dokumen_scan_id], DokumenScan::pluck('dokumen_scan_id')->all());
        $this->assertSame(['dokumen_scan/inv20250001/lama.pdf'], $disk->allFiles('dokumen_scan'));
        $this->assertSame('2025-05-01', $dokumen->fresh()->tarikh_dokumen->toDateString());
    }
}
