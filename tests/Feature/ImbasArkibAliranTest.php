<?php

namespace Tests\Feature;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenField;
use App\Models\DokumenOcr;
use App\Models\DokumenScan;
use App\Models\JenisDokumen;
use App\Models\User;
use App\Services\OcrService;
use App\Services\OllamaExtractionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * Aliran token Imbas & Arkib (#1, #9/K-04, #10/K-12, #11):
 * hasil OCR/AI disimpan di pelayan dalam ocr_sementara/{token}/, pelayar
 * hanya memegang token, dan simpan tidak mempercayai medan tersembunyi.
 */
class ImbasArkibAliranTest extends TestCase
{
    use SkemaUjian;

    private const TEKS_OCR = 'BORANG PERMOHONAN No Siri: ABC123';

    private User $kerani;
    private JenisDokumen $jenis;
    private DokumenField $medanSiri;
    private DokumenField $medanJumlah;
    private MockInterface $ocr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('local');

        $bahagian = $this->buatBahagian();
        $this->kerani = $this->buatPengguna(['KERANI'], ['bahagian_id' => $bahagian->bahagian_id]);

        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'BPK', 'nama_dokumen' => 'Borang Pohon Komputer']);
        $this->medanSiri = DokumenField::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'No. Siri',
            'kod_field' => 'no_siri', 'jenis_data' => 'teks', 'is_required' => true, 'susunan' => 1,
        ]);
        $this->medanJumlah = DokumenField::create([
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'Jumlah',
            'kod_field' => 'jumlah', 'jenis_data' => 'nombor', 'is_required' => false, 'susunan' => 2,
        ]);

        $this->ocr = $this->spy(OcrService::class);
        $this->ocr->shouldReceive('proses')->andReturn(['teks' => self::TEKS_OCR, 'skor' => 88.5]);
        $this->mock(OllamaExtractionService::class, fn (MockInterface $m) => $m
            ->shouldReceive('ekstrak')->andReturn([$this->medanSiri->dokumen_field_id => 'ABC123']));
    }

    /** Jalankan langkah analisis dan pulangkan token daripada redirect. */
    private function analisis(?User $user = null): string
    {
        $res = $this->actingAs($user ?? $this->kerani)->post(route('imbas-arkib.analisis'), [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id,
            'fail_scan' => UploadedFile::fake()->create('Borang Asal.pdf', 50, 'application/pdf'),
        ]);
        $res->assertRedirect();

        return basename(parse_url($res->headers->get('Location'), PHP_URL_PATH));
    }

    private function dataSah(array $tambahan = []): array
    {
        return array_merge([
            'tarikh_dokumen' => '2025-03-01',
            'perkara' => 'Permohonan komputer riba',
            'medan_data' => [
                $this->medanSiri->dokumen_field_id => 'ABC123',
                $this->medanJumlah->dokumen_field_id => '2',
            ],
        ], $tambahan);
    }

    public function test_analisis_simpan_folder_token_dan_redirect_ke_skrin_semak(): void
    {
        $token = $this->analisis();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $token);
        $disk = Storage::disk('local');
        $disk->assertExists("ocr_sementara/{$token}/fail.pdf");
        $disk->assertExists("ocr_sementara/{$token}/meta.json");

        $meta = json_decode($disk->get("ocr_sementara/{$token}/meta.json"), true);
        $this->assertSame($this->kerani->user_id, $meta['user_id']);
        $this->assertSame('Borang Asal.pdf', $meta['nama_asal']);
        $this->assertSame(self::TEKS_OCR, $meta['teks_ocr']);

        $this->actingAs($this->kerani)->get(route('imbas-arkib.semak', $token))
            ->assertOk()
            ->assertSee('Borang Asal.pdf')
            ->assertSee('value="ABC123"', false)
            ->assertSee(route('imbas-arkib.simpan', $token))
            ->assertDontSee('name="laluan_sementara"', false)
            ->assertDontSee('name="teks_ocr"', false)
            ->assertDontSee('name="jenis_dokumen_id"', false);
    }

    public function test_muat_semula_skrin_semak_tidak_jalankan_ocr_semula(): void
    {
        $token = $this->analisis();

        $this->actingAs($this->kerani)->get(route('imbas-arkib.semak', $token))->assertOk();
        $this->actingAs($this->kerani)->get(route('imbas-arkib.semak', $token))->assertOk();

        $this->ocr->shouldHaveReceived('proses')->once();
    }

    public function test_simpan_guna_meta_dan_abaikan_medan_tersembunyi_palsu(): void
    {
        $disk = Storage::disk('local');
        $disk->put('dokumen_scan/dokumen_lain/rahsia.pdf', 'fail dokumen lain');
        $jenisLain = JenisDokumen::create(['kod_dokumen' => 'LAIN', 'nama_dokumen' => 'Jenis Lain']);

        $token = $this->analisis();

        $res = $this->actingAs($this->kerani)->post(route('imbas-arkib.simpan', $token), $this->dataSah([
            'laluan_sementara' => 'dokumen_scan/dokumen_lain/rahsia.pdf',
            'jenis_dokumen_id' => $jenisLain->jenis_dokumen_id,
            'nama_asal' => 'palsu.pdf',
            'teks_ocr' => 'TEKS PALSU',
            'skor_ocr' => '100',
        ]));

        $dokumen = Dokumen::sole();
        $res->assertRedirect(route('dokumen.show', $dokumen->dokumen_id));

        $this->assertSame($this->jenis->jenis_dokumen_id, $dokumen->jenis_dokumen_id);
        $this->assertSame('BPK/2025/0001', $dokumen->no_rujukan);

        $scan = DokumenScan::sole();
        $this->assertSame('Borang Asal.pdf', $scan->nama_fail);
        $this->assertMatchesRegularExpression('#^dokumen_scan/bpk20250001/[A-Za-z0-9]{40}\.pdf$#', $scan->lokasi_fail);
        $disk->assertExists($scan->lokasi_fail);
        $this->assertSame(self::TEKS_OCR, DokumenOcr::sole()->teks_dibaca);

        // Fail dokumen lain tidak disentuh; folder token dibersihkan selepas commit
        $disk->assertExists('dokumen_scan/dokumen_lain/rahsia.pdf');
        $disk->assertMissing("ocr_sementara/{$token}");

        $borang = Borang::sole();
        $this->assertSame('Diluluskan', $borang->status_permohonan);
        $this->assertNull($borang->pegawai_penyokong_id);
        $this->assertDatabaseHas('dokumen_data', [
            'dokumen_id' => $dokumen->dokumen_id,
            'dokumen_field_id' => $this->medanSiri->dokumen_field_id,
            'nilai_data' => 'ABC123',
        ]);
    }

    public function test_ralat_validasi_kembali_ke_skrin_semak_dengan_nilai_dan_token_kekal(): void
    {
        $token = $this->analisis();
        $urlSemak = route('imbas-arkib.semak', $token);

        $this->actingAs($this->kerani)->from($urlSemak)
            ->post(route('imbas-arkib.simpan', $token), $this->dataSah([
                'perkara' => 'Perkara yang ditaip pengguna',
                'medan_data' => [$this->medanSiri->dokumen_field_id => ''],
            ]))
            ->assertRedirect($urlSemak)
            ->assertSessionHasErrors("medan_data.{$this->medanSiri->dokumen_field_id}");

        Storage::disk('local')->assertExists("ocr_sementara/{$token}/meta.json");
        $this->assertSame(0, Dokumen::count());

        $this->actingAs($this->kerani)->get($urlSemak)
            ->assertOk()
            ->assertSee('Perkara yang ditaip pengguna');
    }

    public function test_token_milik_pengguna_lain_memberi_404(): void
    {
        $token = $this->analisis();
        $keraniLain = $this->buatPengguna(['KERANI']);

        $this->actingAs($keraniLain)->get(route('imbas-arkib.semak', $token))->assertNotFound();
        $this->actingAs($keraniLain)->post(route('imbas-arkib.simpan', $token), $this->dataSah())->assertNotFound();

        Storage::disk('local')->assertExists("ocr_sementara/{$token}/meta.json");
        $this->assertSame(0, Dokumen::count());
    }

    public function test_token_format_rosak_memberi_404(): void
    {
        $this->actingAs($this->kerani)->get('/imbas-arkib/semak/abc')->assertNotFound();
        $this->actingAs($this->kerani)->get('/imbas-arkib/semak/' . str_repeat('a', 39) . '.')->assertNotFound();
        $this->actingAs($this->kerani)->post('/imbas-arkib/simpan/..%2F..%2Fdokumen_scan')->assertNotFound();
    }

    public function test_token_tamat_tempoh_dipadam_dan_redirect_dengan_mesej(): void
    {
        $token = $this->analisis();

        $this->travel(121)->minutes();

        $this->actingAs($this->kerani)->get(route('imbas-arkib.semak', $token))
            ->assertRedirect(route('imbas-arkib.create'))
            ->assertSessionHasErrors('token');
        Storage::disk('local')->assertMissing("ocr_sementara/{$token}");
    }

    public function test_token_tidak_wujud_redirect_dengan_mesej(): void
    {
        $this->actingAs($this->kerani)->get(route('imbas-arkib.semak', str_repeat('x', 40)))
            ->assertRedirect(route('imbas-arkib.create'))
            ->assertSessionHasErrors('token');

        $this->actingAs($this->kerani)->followingRedirects()
            ->get(route('imbas-arkib.semak', str_repeat('x', 40)))
            ->assertOk()
            ->assertSee('Sesi semakan ini telah tamat tempoh atau sudah disimpan');
    }

    public function test_simpan_dua_kali_hanya_cipta_satu_dokumen(): void
    {
        $token = $this->analisis();

        $this->actingAs($this->kerani)->post(route('imbas-arkib.simpan', $token), $this->dataSah())
            ->assertRedirect(route('dokumen.show', Dokumen::sole()->dokumen_id));

        $this->actingAs($this->kerani)->post(route('imbas-arkib.simpan', $token), $this->dataSah())
            ->assertRedirect(route('imbas-arkib.create'))
            ->assertSessionHasErrors('token');

        $this->assertSame(1, Dokumen::count());
        $this->assertSame(1, DokumenScan::count());
    }

    public function test_token_dituntut_sebelum_transaksi_supaya_permintaan_serentak_ditolak(): void
    {
        $token = $this->analisis();
        $disk = Storage::disk('local');

        // Semasa transaksi berjalan, permintaan kedua (serentak) mesti tidak lagi
        // menjumpai meta.json, jadi ia mendapat "sesi tamat" dan bukan dokumen kedua.
        $semasaTransaksi = null;
        Dokumen::creating(function () use ($disk, $token, &$semasaTransaksi) {
            $semasaTransaksi = [
                'meta' => $disk->exists("ocr_sementara/{$token}/meta.json"),
                'diproses' => $disk->exists("ocr_sementara/{$token}/meta.json.diproses"),
            ];
        });

        $this->actingAs($this->kerani)->post(route('imbas-arkib.simpan', $token), $this->dataSah())
            ->assertRedirect();

        $this->assertSame(['meta' => false, 'diproses' => true], $semasaTransaksi);
    }

    public function test_transaksi_gagal_pulihkan_token_tanpa_fail_tertinggal(): void
    {
        $token = $this->analisis();
        Borang::creating(fn () => throw new \RuntimeException('Simulasi DB gagal'));

        $this->actingAs($this->kerani)->post(route('imbas-arkib.simpan', $token), $this->dataSah())
            ->assertStatus(500);

        $disk = Storage::disk('local');
        $this->assertSame(0, Dokumen::withTrashed()->count());
        $this->assertSame(0, DokumenScan::count());
        $this->assertSame([], $disk->allFiles('dokumen_scan'));
        $disk->assertExists("ocr_sementara/{$token}/meta.json");
        $disk->assertMissing("ocr_sementara/{$token}/meta.json.diproses");
        $disk->assertExists("ocr_sementara/{$token}/fail.pdf");
    }

    public function test_cuba_semula_bila_no_rujukan_bertembung_tanpa_salinan_berganda(): void
    {
        $token = $this->analisis();

        $kali = 0;
        DokumenScan::creating(function () use (&$kali) {
            if (++$kali === 1) {
                $pdo = new \PDOException("Duplicate entry 'BPK/2025/0001' for key 'no_rujukan'");
                $pdo->errorInfo = ['23000', 1062, 'Duplicate entry'];
                throw new QueryException('mysql', 'insert into dokumen_scan ...', [], $pdo);
            }
        });

        $this->actingAs($this->kerani)->post(route('imbas-arkib.simpan', $token), $this->dataSah())
            ->assertRedirect(route('dokumen.show', Dokumen::sole()->dokumen_id));

        $this->assertSame(2, $kali);
        $this->assertSame(1, DokumenScan::count());
        $this->assertCount(1, Storage::disk('local')->allFiles('dokumen_scan'));
    }
}
