<?php

namespace Tests\Feature;

use App\Models\Borang;
use App\Models\Dokumen;
use App\Models\DokumenData;
use App\Models\DokumenField;
use App\Models\JenisDokumen;
use App\Models\LogAudit;
use App\Models\TemplatBorang;
use App\Models\User;
use App\Services\BorangSiapService;
use App\Services\OcrService;
use App\Services\TemplatBorangService;
use App\Support\KedudukanMedan;
use Illuminate\Support\Facades\Storage;
use Tests\SkemaUjian;
use Tests\TestCase;

/**
 * TB6: borang siap = templat borang kosong diisi nilai dokumen pada kedudukan yang disusun
 * pentadbir, + cop status semasa dan jalur salinan terkawal. Templat imej (PNG) digunakan
 * supaya ujian tidak bergantung pada pdftoppm.
 */
class BorangSiapTest extends TestCase
{
    use SkemaUjian;

    private User $admin;

    private JenisDokumen $jenis;

    private DokumenField $nama;

    private DokumenField $status;

    private DokumenField $jadual;

    private DokumenField $catatan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sediakanSkema();
        Storage::fake('templat');
        Storage::fake('local');
        $this->admin = $this->buatPengguna(['ADMIN']);
        $this->jenis = JenisDokumen::create(['kod_dokumen' => 'BD-SEWA', 'nama_dokumen' => 'Borang Guna Sewa']);
        $medan = fn (array $a) => DokumenField::create($a + ['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'is_required' => false]);
        $this->nama = $medan(['nama_field' => 'Nama Pemohon', 'kod_field' => 'nama', 'jenis_data' => 'teks', 'susunan' => 1]);
        $this->status = $medan(['nama_field' => 'Status Sokongan', 'kod_field' => 'status', 'jenis_data' => 'radio', 'susunan' => 2,
            'pilihan' => ['Disokong', 'Tidak Disokong']]);
        $this->jadual = $medan(['nama_field' => 'Peralatan', 'kod_field' => 'peralatan', 'jenis_data' => 'jadual', 'susunan' => 3,
            'tetapan' => ['lajur' => [['kod' => 'jenis', 'nama' => 'Jenis', 'jenis' => 'teks'], ['kod' => 'kuantiti', 'nama' => 'Kuantiti', 'jenis' => 'nombor']]]]);
        $this->catatan = $medan(['nama_field' => 'Catatan', 'kod_field' => 'catatan', 'jenis_data' => 'textarea', 'susunan' => 4]);
    }

    private function templat(array $atribut = []): TemplatBorang
    {
        $imej = imagecreatetruecolor(620, 877);
        imagefill($imej, 0, 0, imagecolorallocate($imej, 255, 255, 255));
        imageline($imej, 100, 200, 500, 200, imagecolorallocate($imej, 0, 0, 0));
        ob_start();
        imagepng($imej);
        Storage::disk('templat')->put('BD-SEWA/kosong.png', (string) ob_get_clean());
        imagedestroy($imej);

        return TemplatBorang::create($atribut + [
            'jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'versi' => 1, 'nama_asal' => 'kosong.png',
            'lokasi_fail' => 'BD-SEWA/kosong.png', 'jenis_mime' => 'image/png', 'status_ocr' => 'Selesai', 'aktif' => true,
            'kedudukan_medan' => $this->susunan(),
        ]);
    }

    private function susunan(): array
    {
        return KedudukanMedan::bersihkan([
            'medan' => [
                $this->nama->dokumen_field_id => ['kotak' => ['muka' => 1, 'x' => 0.2, 'y' => 0.2, 'w' => 0.6, 'h' => 0.03]],
                $this->status->dokumen_field_id => ['gaya' => 'tanda', 'pilihan' => [
                    'Disokong' => ['muka' => 1, 'x' => 0.2, 'y' => 0.3, 'w' => 0.1, 'h' => 0.02],
                    'Tidak Disokong' => ['muka' => 1, 'x' => 0.35, 'y' => 0.3, 'w' => 0.15, 'h' => 0.02],
                ]],
                $this->jadual->dokumen_field_id => [
                    'baris' => ['muka' => 1, 'x' => 0.1, 'y' => 0.4, 'w' => 0.8, 'h' => 0.03, 'bilangan' => 2],
                    'lajur' => ['jenis' => ['muka' => 1, 'x' => 0.1, 'y' => 0.4, 'w' => 0.5, 'h' => 0.03],
                        'kuantiti' => ['muka' => 1, 'x' => 0.6, 'y' => 0.4, 'w' => 0.2, 'h' => 0.03]],
                ],
                $this->catatan->dokumen_field_id => ['kotak' => ['muka' => 1, 'x' => 0.1, 'y' => 0.6, 'w' => 0.8, 'h' => 0.1]],
            ],
            'asas' => ['tarikh_dokumen' => ['muka' => 1, 'x' => 0.2, 'y' => 0.15, 'w' => 0.3, 'h' => 0.02], 'bukan_asas' => ['muka' => 1, 'x' => 0, 'y' => 0, 'w' => 0.1, 'h' => 0.1]],
            'cop' => ['muka' => 1, 'x' => 0.6, 'y' => 0.02, 'w' => 0.38, 'h' => 0.08],
        ], $this->jenis->fields);
    }

    private function dokumen(User $pemilik, ?string $status = null): Dokumen
    {
        $dokumen = Dokumen::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'no_rujukan' => 'BD-SEWA/2026/0001',
            'tarikh_dokumen' => '2026-11-02', 'created_by' => $pemilik->user_id]);
        foreach ([
            $this->nama->dokumen_field_id => 'Siti Aminah binti Yusof',
            $this->status->dokumen_field_id => 'Disokong',
            $this->jadual->dokumen_field_id => json_encode([['jenis' => 'Laptop', 'kuantiti' => '2'], ['jenis' => 'Pencetak', 'kuantiti' => '1'], ['jenis' => 'Projektor', 'kuantiti' => '1']]),
            $this->catatan->dokumen_field_id => 'Untuk kursus dalaman minggu hadapan.',
        ] as $id => $nilai) {
            DokumenData::create(['dokumen_id' => $dokumen->dokumen_id, 'dokumen_field_id' => $id, 'nilai_data' => $nilai]);
        }
        if ($status) {
            Borang::create(['dokumen_id' => $dokumen->dokumen_id, 'pemohon_id' => $pemilik->user_id, 'status_permohonan' => $status,
                'tarikh_permohonan' => now(), 'tarikh_kelulusan' => $status === 'Diluluskan' ? now() : null,
                'pegawai_pelulus_id' => $status === 'Diluluskan' ? $this->admin->user_id : null]);
        }

        return $dokumen;
    }

    /**
     * Teks dalam aliran kandungan PDF (FPDF memampatkan setiap aliran dengan zlib). Aliran
     * dibaca ikut /Length yang tepat: data termampat boleh berakhir dengan bait \r.
     */
    private function teksPdf(string $pdf): string
    {
        preg_match_all('#/Length (\d+)[^>]*>>\s*stream\n#', $pdf, $m, PREG_OFFSET_CAPTURE);
        $aliran = [];
        foreach ($m[0] as $i => [$kepala, $kedudukan]) {
            $data = substr($pdf, $kedudukan + strlen($kepala), (int) $m[1][$i][0]);
            $aliran[] = @gzuncompress($data) ?: $data;
        }

        return implode("\n", $aliran);
    }

    // ---- KedudukanMedan ----

    public function test_susunan_dibersihkan_ikut_medan_kategori(): void
    {
        $hasil = KedudukanMedan::bersihkan([
            'medan' => [
                $this->nama->dokumen_field_id => ['kotak' => ['muka' => 1, 'x' => -0.5, 'y' => 0.5, 'w' => 3, 'h' => 0.02, 'saiz' => 99]],
                $this->status->dokumen_field_id => ['gaya' => 'jahat', 'pilihan' => ['Disokong' => ['muka' => 1, 'x' => 0.1, 'y' => 0.1, 'w' => 0.1, 'h' => 0.02],
                    'Tiada Pilihan Ini' => ['muka' => 1, 'x' => 0.1, 'y' => 0.1, 'w' => 0.1, 'h' => 0.02]]],
                $this->jadual->dokumen_field_id => ['baris' => ['muka' => 1, 'x' => 0, 'y' => 0.4, 'w' => 1, 'h' => 0.03, 'bilangan' => 500],
                    'lajur' => ['jenis' => ['muka' => 1, 'x' => 0.1, 'y' => 0.4, 'w' => 0.2, 'h' => 0.03], 'tiada' => ['muka' => 1, 'x' => 0, 'y' => 0, 'w' => 0.1, 'h' => 0.1]]],
                $this->catatan->dokumen_field_id => ['kotak' => ['muka' => 2, 'x' => 0.1, 'y' => 0.1, 'w' => 0.1, 'h' => 0.1]],   // muka tiada
                99999 => ['kotak' => ['muka' => 1, 'x' => 0.1, 'y' => 0.1, 'w' => 0.1, 'h' => 0.1]],                              // medan lain
            ],
            'cop' => ['muka' => 1, 'x' => 'abc', 'y' => 0, 'w' => 0.1, 'h' => 0.1],
        ], $this->jenis->fields);

        $nama = $hasil['medan'][(string) $this->nama->dokumen_field_id]['kotak'];
        $this->assertSame(['muka' => 1, 'x' => 0.0, 'y' => 0.5, 'w' => 1.0, 'h' => 0.02, 'saiz' => 20.0], $nama);
        $status = $hasil['medan'][(string) $this->status->dokumen_field_id];
        $this->assertSame(['Disokong'], array_keys($status['pilihan']));
        $this->assertSame('tanda', $status['gaya']);
        $jadual = $hasil['medan'][(string) $this->jadual->dokumen_field_id];
        $this->assertSame(['jenis'], array_keys($jadual['lajur']));
        $this->assertSame(100, $jadual['baris']['bilangan']);
        $this->assertArrayNotHasKey((string) $this->catatan->dokumen_field_id, $hasil['medan']);
        $this->assertArrayNotHasKey('99999', $hasil['medan']);
        $this->assertArrayNotHasKey('cop', $hasil);
    }

    // ---- Penjana PDF ----

    public function test_pdf_mengandungi_nilai_tanda_cop_jalur_dan_lampiran_jadual(): void
    {
        $templat = $this->templat();
        $dokumen = $this->dokumen($this->admin, 'Diluluskan')->load('data', 'borang.pemohon', 'borang.pelulus');
        $servis = app(BorangSiapService::class);

        $pdf = $servis->jana($templat, $servis->nilaiDokumen($dokumen), $servis->cop($dokumen), 'Salinan terkawal | ujian', $servis->asasDokumen($dokumen));
        $teks = $this->teksPdf($pdf);

        $this->assertSame(['tarikh_dokumen'], array_keys($templat->kedudukan_medan['asas']), 'kunci asas tidak dikenali dibuang');
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('(02/11/2026) Tj', $teks, 'Tarikh Dokumen (medan asas)');
        $this->assertStringContainsString('(Siti Aminah binti Yusof) Tj', $teks);
        $this->assertStringContainsString('(Laptop) Tj', $teks);
        $this->assertStringContainsString('(Pencetak) Tj', $teks);
        // ✓ dilukis sebagai garisan vektor dengan ketebalan TEBAL_TANDA (mm → pt dalam aliran PDF)
        $this->assertStringContainsString(sprintf('%.2F w', BorangSiapService::TEBAL_TANDA * 72 / 25.4), $teks, 'tanda ✓ pada pilihan Disokong');
        $this->assertStringContainsString('(DILULUSKAN) Tj', $teks);
        $this->assertStringContainsString('Kod sah: '.$dokumen->borang->kodPengesahan(), $teks);
        $this->assertStringContainsString('(Salinan terkawal | ujian) Tj', $teks);
        // Baris ke-3 jadual tidak muat (bilangan 2) → lampiran
        $this->assertStringContainsString('LAMPIRAN', $teks);
        $this->assertStringContainsString('Jenis: Projektor; Kuantiti: 1', $teks);
    }

    public function test_lampiran_berbilang_muka_dan_baris_melepasi_tepi_halaman(): void
    {
        $templat = $this->templat();
        $k = $templat->kedudukan_medan;
        // Baris pertama hampir di bawah halaman: hanya satu baris muat walaupun bilangan = 5
        $k['medan'][$this->jadual->dokumen_field_id]['baris'] = ['muka' => 1, 'x' => 0.1, 'y' => 0.95, 'w' => 0.8, 'h' => 0.03, 'bilangan' => 5];
        $templat->update(['kedudukan_medan' => $k]);
        $baris = array_map(fn ($i) => ['jenis' => "Item {$i}", 'kuantiti' => (string) $i], range(1, 100));
        $nilai = [$this->jadual->dokumen_field_id => json_encode($baris)];

        $pdf = app(BorangSiapService::class)->jana($templat->fresh(), $nilai, null, 'jalur');
        $teks = $this->teksPdf($pdf);

        $this->assertStringContainsString('(Item 1) Tj', $teks, 'baris pertama atas borang');
        $this->assertStringContainsString('Jenis: Item 2; Kuantiti: 2', $teks, 'baris kedua ke lampiran (tidak muat atas halaman)');
        $this->assertStringContainsString('Jenis: Item 100; Kuantiti: 100', $teks, 'baris terakhir tidak hilang');
        $this->assertGreaterThanOrEqual(3, preg_match_all('#/Type /Page\b(?!s)#', $pdf), 'lampiran bersambung ke muka surat seterusnya');
        $this->assertGreaterThanOrEqual(3, substr_count($teks, '(jalur) Tj'), 'jalur pada setiap muka surat');
    }

    public function test_cop_kekal_nama_pegawai_yang_akaunnya_dipadam(): void
    {
        $this->templat();
        $pelulus = $this->buatPengguna(['PELULUS'], ['nama_staff' => 'Pelulus Bersara']);
        $dokumen = $this->dokumen($this->admin, 'Diluluskan');
        $dokumen->borang->update(['pegawai_pelulus_id' => $pelulus->user_id]);
        $pelulus->delete();

        $respons = $this->actingAs($this->admin)->get(route('dokumen.borang-siap', $dokumen->dokumen_id))->assertOk();
        $this->assertStringContainsString('Diluluskan: Pelulus Bersara', $this->teksPdf($respons->streamedContent()));
    }

    public function test_templat_imej_resolusi_tinggi_dikecilkan(): void
    {
        $imej = imagecreatetruecolor(2480, 3508);   // A4 300dpi
        imagefill($imej, 0, 0, imagecolorallocate($imej, 255, 255, 255));
        ob_start();
        imagepng($imej);
        Storage::disk('templat')->put('BD-SEWA/besar.png', (string) ob_get_clean());
        imagedestroy($imej);
        $templat = $this->templat(['lokasi_fail' => 'BD-SEWA/besar.png']);

        $muka = app(BorangSiapService::class)->latar($templat);

        $this->assertSame([1240, 1754], array_slice(getimagesize($muka[0]['fail']), 0, 2));
        $this->assertEqualsWithDelta(297.0, $muka[0]['tinggi'], 0.1);
    }

    public function test_medan_wang_tanpa_rm_bila_borang_sudah_mencetak_rm(): void
    {
        $caj = DokumenField::create(['jenis_dokumen_id' => $this->jenis->jenis_dokumen_id, 'nama_field' => 'Caj Sewaan', 'kod_field' => 'caj',
            'jenis_data' => 'wang', 'is_required' => false, 'susunan' => 5]);
        $kotak = ['muka' => 1, 'x' => 0.5, 'y' => 0.8, 'w' => 0.3, 'h' => 0.02];
        $templat = $this->templat();
        $servis = app(BorangSiapService::class);

        $kedudukan = KedudukanMedan::bersihkan(['medan' => [$caj->dokumen_field_id => ['kotak' => $kotak + ['tanpa_unit' => true]]]], $this->jenis->fresh()->fields);
        $this->assertTrue($kedudukan['medan'][$caj->dokumen_field_id]['kotak']['tanpa_unit']);
        $templat->update(['kedudukan_medan' => $kedudukan]);
        $teks = $this->teksPdf($servis->jana($templat->fresh('jenisDokumen.fields'), [$caj->dokumen_field_id => '1250.00'], null, null));
        $this->assertStringContainsString('(1,250.00) Tj', $teks);
        $this->assertStringNotContainsString('RM 1,250.00', $teks);

        // Lalai: dengan RM; nilai bukan boolean true diabaikan
        $templat->update(['kedudukan_medan' => KedudukanMedan::bersihkan(['medan' => [$caj->dokumen_field_id => ['kotak' => $kotak + ['tanpa_unit' => 'ya']]]], $this->jenis->fresh()->fields)]);
        $this->assertStringContainsString('(RM 1,250.00) Tj', $this->teksPdf($servis->jana($templat->fresh('jenisDokumen.fields'), [$caj->dokumen_field_id => '1250.00'], null, null)));
    }

    public function test_cop_ikut_status_dan_qr_hanya_bila_diluluskan(): void
    {
        $servis = app(BorangSiapService::class);

        $belum = $servis->cop($this->dokumen($this->admin)->load('borang'));
        $this->assertSame('DIISI', $belum['label']);
        $this->assertNull($belum['qr']);

        Dokumen::query()->forceDelete();
        $proses = $servis->cop($this->dokumen($this->admin, 'Dalam Proses')->load('borang.pemohon'));
        $this->assertSame('DALAM PROSES', $proses['label']);
        $this->assertNull($proses['qr']);

        Dokumen::query()->forceDelete();
        $lulus = $servis->cop($this->dokumen($this->admin, 'Diluluskan')->load('borang.pemohon', 'borang.pelulus'));
        $this->assertSame('DILULUSKAN', $lulus['label']);
        $this->assertStringContainsString('/sahkan-slip/', $lulus['qr']);
    }

    public function test_templat_belum_disusun_atau_tidak_aktif_tidak_digunakan(): void
    {
        $servis = app(BorangSiapService::class);
        $templat = $this->templat(['kedudukan_medan' => null]);
        $this->assertNull($servis->templatUntuk($this->jenis->jenis_dokumen_id));

        $templat->update(['kedudukan_medan' => $this->susunan(), 'aktif' => false]);
        $this->assertNull($servis->templatUntuk($this->jenis->jenis_dokumen_id));

        $templat->update(['aktif' => true]);
        $this->assertTrue($servis->templatUntuk($this->jenis->jenis_dokumen_id)->is($templat));
    }

    // ---- Muat turun ----

    public function test_muat_turun_borang_siap_dilog_dan_ikut_kebenaran_dokumen(): void
    {
        $this->templat();
        $staf = $this->buatPengguna(['STAFF']);
        $dokumen = $this->dokumen($staf, 'Dalam Proses');

        $this->actingAs($staf)->get(route('dokumen.show', $dokumen->dokumen_id))->assertOk()
            ->assertSee(route('dokumen.borang-siap', $dokumen->dokumen_id));

        $respons = $this->actingAs($staf)->get(route('dokumen.borang-siap', $dokumen->dokumen_id))->assertOk();
        $this->assertSame('application/pdf', $respons->headers->get('Content-Type'));
        $this->assertStringContainsString('Borang_BD-SEWA-2026-0001.pdf', $respons->headers->get('Content-Disposition'));
        $this->assertStringContainsString('(DALAM PROSES) Tj', $this->teksPdf($respons->streamedContent()));

        $log = LogAudit::where('tindakan', 'Muat Turun Borang Siap')->sole();
        $this->assertSame($dokumen->dokumen_id, (int) $log->rekod_id);
        $this->assertSame('Dalam Proses', $log->data_baharu['status']);

        // Staf lain tidak boleh melihat dokumen ini
        $this->actingAs($this->buatPengguna(['STAFF']))->get(route('dokumen.borang-siap', $dokumen->dokumen_id))->assertForbidden();
    }

    public function test_tanpa_templat_disusun_tiada_butang_dan_muat_turun_ditolak(): void
    {
        $dokumen = $this->dokumen($this->admin);

        $this->actingAs($this->admin)->get(route('dokumen.show', $dokumen->dokumen_id))->assertOk()
            ->assertDontSee(route('dokumen.borang-siap', $dokumen->dokumen_id));
        $this->actingAs($this->admin)->from(route('dokumen.show', $dokumen->dokumen_id))
            ->get(route('dokumen.borang-siap', $dokumen->dokumen_id))
            ->assertRedirect(route('dokumen.show', $dokumen->dokumen_id))->assertSessionHas('error');
    }

    // ---- Editor susunan ----

    public function test_editor_susun_simpan_dibersihkan_dan_dilog(): void
    {
        $templat = $this->templat(['kedudukan_medan' => null]);

        $this->actingAs($this->admin)->get(route('templat.susun', $templat->templat_borang_id))->assertOk()
            ->assertSee('Susun Kedudukan Medan')->assertSee('Lajur: Kuantiti')->assertSee('Pilihan: Tidak Disokong');
        $this->actingAs($this->admin)->get(route('templat.muka', [$templat->templat_borang_id, 1]))->assertOk();

        $this->actingAs($this->admin)->putJson(route('templat.susun.simpan', $templat->templat_borang_id), ['kedudukan' => [
            'medan' => [$this->nama->dokumen_field_id => ['kotak' => ['muka' => 1, 'x' => 0.2, 'y' => 0.2, 'w' => 0.5, 'h' => 0.03]], 12345 => ['kotak' => []]],
        ]])->assertOk()->assertJsonPath('kedudukan.medan.'.$this->nama->dokumen_field_id.'.kotak.w', 0.5);

        $this->assertSame([$this->nama->dokumen_field_id], array_keys($templat->fresh()->kedudukan_medan['medan']));
        $this->assertTrue(LogAudit::where('tindakan', 'Susun Kedudukan Medan')->where('rekod_id', $templat->templat_borang_id)->exists());

        $pratonton = $this->actingAs($this->admin)->get(route('templat.pratonton', $templat->templat_borang_id))->assertOk();
        $this->assertStringContainsString('([Nama Pemohon]) Tj', $this->teksPdf($pratonton->getContent()));
    }

    public function test_editor_hanya_pentadbir(): void
    {
        $templat = $this->templat();
        foreach (['KERANI', 'STAFF'] as $peranan) {
            $u = $this->buatPengguna([$peranan]);
            $this->actingAs($u)->get(route('templat.susun', $templat->templat_borang_id))->assertForbidden();
            $this->actingAs($u)->putJson(route('templat.susun.simpan', $templat->templat_borang_id), ['kedudukan' => []])->assertForbidden();
            $this->actingAs($u)->getJson(route('templat.cadang', $templat->templat_borang_id))->assertForbidden();
            $this->actingAs($u)->get(route('templat.pratonton', $templat->templat_borang_id))->assertForbidden();
        }
    }

    public function test_versi_templat_baharu_mewarisi_susunan(): void
    {
        $lama = $this->templat();
        $this->mock(OcrService::class)->shouldReceive('proses')->andReturn(['teks' => 'BORANG', 'skor' => 90, 'enjin' => ['tesseract']]);
        Storage::disk('templat')->put('BD-SEWA/v2.png', Storage::disk('templat')->get('BD-SEWA/kosong.png'));

        $baharu = app(TemplatBorangService::class)->daftar($this->jenis, 'BD-SEWA/v2.png', 'v2.png', $this->admin->user_id);

        $this->assertSame(2, $baharu->versi);
        $this->assertSame($lama->kedudukan_medan['medan'], $baharu->kedudukan_medan['medan']);
        // Diwarisi sebagai cadangan: versi 2 belum digunakan, borang siap kekal pada versi 1
        $this->assertFalse($baharu->disusun());
        $this->assertTrue(app(BorangSiapService::class)->templatUntuk($this->jenis->jenis_dokumen_id)->is($lama));

        // Selepas pentadbir menyimpan susunan, versi 2 digunakan
        $this->actingAs($this->admin)->putJson(route('templat.susun.simpan', $baharu->templat_borang_id), ['kedudukan' => $baharu->kedudukan_medan])->assertOk();
        $this->assertTrue(app(BorangSiapService::class)->templatUntuk($this->jenis->jenis_dokumen_id)->is($baharu->fresh()));
    }
}
