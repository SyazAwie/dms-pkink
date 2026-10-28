<?php

namespace Tests\Feature;

use App\Console\Commands\SandaranJalan;
use App\Support\PemulihanSandaran;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/**
 * Fasa Keselamatan S4: simpanan bertingkat, zip disulitkan AES-256, salinan luar talian.
 * (sandaran:uji & sandaran:pulih memerlukan MySQL sebenar; diuji secara manual.)
 */
class SandaranRansomwareTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sandaran_ujian_'.uniqid();
        File::ensureDirectoryExists($this->folder);
        config(['services.sandaran.folder' => $this->folder]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    public function test_simpanan_bertingkat_harian_mingguan_bulanan(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 28)->setTime(13, 0));
        $fail = [];
        for ($h = 0; $h < 220; $h++) {
            $fail[] = 'x/sandaran_'.now()->subDays($h)->format('Ymd').'_123000.zip';
        }

        $dipadam = SandaranJalan::untukDipadam($fail, 14);
        $disimpan = array_values(array_diff($fail, $dipadam));

        // 14 harian, kemudian satu setiap minggu hingga 8 minggu, satu setiap bulan hingga 6 bulan
        $this->assertCount(14, array_filter($disimpan, fn ($f) => $f >= 'x/sandaran_20261015'));
        $mingguan = array_filter($disimpan, fn ($f) => $f < 'x/sandaran_20261015' && $f >= 'x/sandaran_20260902');
        $this->assertGreaterThanOrEqual(6, count($mingguan));
        $this->assertLessThanOrEqual(8, count($mingguan)); // minggu ISO separa di kedua-dua hujung tetingkap
        $this->assertSame([], array_filter($disimpan, fn ($f) => $f < 'x/sandaran_20260428'), 'Lebih 6 bulan dipadam');
        $this->assertContains('x/sandaran_20260531_123000.zip', $disimpan, 'Bulanan (terbaharu bulan Mei) disimpan');
        $this->assertNotContains('x/sandaran_20260530_123000.zip', $disimpan);
        $this->assertCount(1, array_filter($disimpan, fn ($f) => str_starts_with($f, 'x/sandaran_202606')), 'Satu bagi bulan Jun');
    }

    public function test_zip_disulitkan_perlu_kata_laluan_betul(): void
    {
        $zip = "{$this->folder}/sandaran_20261028_123000.zip";
        $a = new ZipArchive;
        $a->open($zip, ZipArchive::CREATE);
        $a->addFromString('pangkalan_data/dms.sql', 'CREATE TABLE t (id INT);');
        $a->setEncryptionName('pangkalan_data/dms.sql', ZipArchive::EM_AES_256, 'Rahsia-Sandaran-9');
        $a->addFromString('fail/dokumen_scan/a.pdf', '%PDF');
        $a->setEncryptionName('fail/dokumen_scan/a.pdf', ZipArchive::EM_AES_256, 'Rahsia-Sandaran-9');
        $a->close();

        config(['services.sandaran.kata_laluan' => 'salah']);
        try {
            PemulihanSandaran::buka($zip);
            $this->fail('Kata laluan salah sepatutnya gagal');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('SANDARAN_KATA_LALUAN', $e->getMessage());
        }

        config(['services.sandaran.kata_laluan' => 'Rahsia-Sandaran-9']);
        [$folder, $sql] = PemulihanSandaran::buka($zip);
        $this->assertSame('CREATE TABLE t (id INT);', File::get($sql));
        $this->assertFileExists("{$folder}/fail/dokumen_scan/a.pdf");
        File::deleteDirectory($folder);
    }

    public function test_salinan_luar_talian_disahkan_dan_direkod(): void
    {
        $this->artisan('sandaran:luar-talian', ['destinasi' => "{$this->folder}/usb"])->assertFailed();

        File::put("{$this->folder}/sandaran_20261028_123000.zip", 'kandungan sandaran');
        $this->artisan('sandaran:luar-talian', ['destinasi' => "{$this->folder}/usb"])
            ->expectsOutputToContain('CABUT cakera luaran')->assertSuccessful();

        $this->assertFileEquals("{$this->folder}/sandaran_20261028_123000.zip", "{$this->folder}/usb/sandaran_20261028_123000.zip");
        $status = json_decode(File::get("{$this->folder}/luar_talian.json"), true);
        $this->assertSame(hash('sha256', 'kandungan sandaran'), $status['sha256']);
    }
}
