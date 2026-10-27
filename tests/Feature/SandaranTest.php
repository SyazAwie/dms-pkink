<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * php artisan sandaran:jalan: mysqldump dipalsukan (pangkalan data sebenar tidak disentuh),
 * fail imbasan daripada Storage::fake, folder sandaran sementara.
 */
class SandaranTest extends TestCase
{
    private string $folder;
    private string $salinan;

    protected function setUp(): void
    {
        parent::setUp();
        $asas = sys_get_temp_dir() . '/sandaran_ujian_' . uniqid();
        $this->folder = "{$asas}/utama";
        $this->salinan = "{$asas}/salinan";

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'dms_ujian',
            'database.connections.mysql.password' => 'rahsia-db',
            'services.sandaran.folder' => $this->folder,
            'services.sandaran.salinan' => $this->salinan,
            'services.sandaran.simpan_hari' => 14,
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('dokumen/borang.pdf', '%PDF ujian');
        Storage::disk('local')->put('ocr_sementara/' . str_repeat('a', 40) . '/fail.pdf', 'sementara');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->folder));
        parent::tearDown();
    }

    /** Palsukan mysqldump: tulis fail --result-file seperti mysqldump sebenar. */
    private function palsuMysqldump(bool $berjaya = true): void
    {
        Process::fake(function (PendingProcess $proses) use ($berjaya) {
            if (! $berjaya) {
                return Process::result(output: '', errorOutput: 'Access denied for user', exitCode: 2);
            }
            foreach ((array) $proses->command as $arg) {
                if (str_starts_with($arg, '--result-file=')) {
                    file_put_contents(substr($arg, 14), "-- dump ujian\nCREATE TABLE dokumen (id INT);\n");
                }
            }

            return Process::result();
        });
    }

    private function zipTerbaru(string $folder): string
    {
        $zip = File::glob("{$folder}/sandaran_*.zip");
        $this->assertCount(1, $zip);

        return $zip[0];
    }

    public function test_sandaran_mengandungi_pangkalan_data_dan_fail_imbasan(): void
    {
        $this->palsuMysqldump();

        $this->artisan('sandaran:jalan')->assertSuccessful();

        $arkib = new ZipArchive;
        $this->assertTrue($arkib->open($this->zipTerbaru($this->folder)));
        $this->assertStringContainsString('CREATE TABLE dokumen', $arkib->getFromName('pangkalan_data/dms_ujian.sql'));
        $this->assertSame('%PDF ujian', $arkib->getFromName('fail/dokumen/borang.pdf'));
        $this->assertFalse($arkib->locateName('fail/ocr_sementara/' . str_repeat('a', 40) . '/fail.pdf'));
        $arkib->close();

        // Salinan kedua, fail .sql sementara dibuang, status ditulis
        $this->zipTerbaru($this->salinan);
        $this->assertSame([], File::glob("{$this->folder}/*.sql"));
        $status = json_decode(File::get("{$this->folder}/terakhir.json"), true);
        $this->assertTrue($status['berjaya']);
        $this->assertSame(1, $status['bil_fail']);
    }

    public function test_kata_laluan_db_melalui_persekitaran_bukan_baris_arahan(): void
    {
        $this->palsuMysqldump();

        $this->artisan('sandaran:jalan')->assertSuccessful();

        Process::assertRan(fn (PendingProcess $p) => ($p->environment['MYSQL_PWD'] ?? null) === 'rahsia-db'
            && ! str_contains(implode(' ', (array) $p->command), 'rahsia-db'));
    }

    public function test_sandaran_lama_dipadam_ikut_tempoh_simpanan(): void
    {
        $this->palsuMysqldump();
        foreach ([$this->folder, $this->salinan] as $f) {
            File::ensureDirectoryExists($f);
            File::put("{$f}/sandaran_20260901_123000.zip", 'lama');
            touch("{$f}/sandaran_20260901_123000.zip", now()->subDays(20)->getTimestamp());
            File::put("{$f}/sandaran_20261020_123000.zip", 'baharu');
            touch("{$f}/sandaran_20261020_123000.zip", now()->subDays(7)->getTimestamp());
        }

        $this->artisan('sandaran:jalan')->assertSuccessful();

        foreach ([$this->folder, $this->salinan] as $f) {
            $this->assertFileDoesNotExist("{$f}/sandaran_20260901_123000.zip");
            $this->assertFileExists("{$f}/sandaran_20261020_123000.zip");
        }
    }

    public function test_mysqldump_gagal_direkod_tanpa_zip(): void
    {
        $this->palsuMysqldump(berjaya: false);

        $this->artisan('sandaran:jalan')->assertFailed();

        $this->assertSame([], File::glob("{$this->folder}/sandaran_*.zip"));
        $status = json_decode(File::get("{$this->folder}/terakhir.json"), true);
        $this->assertFalse($status['berjaya']);
        $this->assertStringContainsString('Access denied', $status['ralat']);
    }

    public function test_dijadualkan_harian_jam_1230(): void
    {
        $acara = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command ?? '', 'sandaran:jalan'));

        $this->assertNotNull($acara);
        $this->assertSame('30 12 * * *', $acara->expression);
    }
}
