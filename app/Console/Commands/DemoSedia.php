<?php

namespace App\Console\Commands;

use App\Support\Demo;
use App\Support\PemulihanSandaran;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Sediakan pangkalan data & fail demo (Fasa D2):
 * 1. mysqldump DB sebenar (BACA sahaja) → cipta semula DB demo → import;
 * 2. bersihkan salinan: kata laluan akaun bukan demo dirawakkan, 2FA/kunci akaun demo
 *    dibuang, sesi, langganan Web Push dan sekatan IP dikosongkan;
 * 3. salin fail imbasan ke storage/app/demo/fail;
 * 4. simpan keadaan asas (asas.sql + asas-fail/) untuk demo:set-semula.
 *
 * DB sebenar tidak pernah ditulis: semua penulisan melalui sambungan `demo_sedia` ke DB demo.
 */
class DemoSedia extends Command
{
    /** Fail dalam disk `local` yang tidak disalin ke demo. */
    private const DIKECUALIKAN = ['ocr_sementara', 'pulih_sementara', 'keselamatan.json'];

    /** Jadual yang dikosongkan dalam salinan (jika wujud). */
    private const DIKOSONGKAN = ['sessions', 'push_subscriptions', 'ip_disekat', 'cache', 'cache_locks', 'jobs', 'failed_jobs'];

    protected $signature = 'demo:sedia {--paksa : Ganti DB demo sedia ada tanpa bertanya}';

    protected $description = 'Salin pangkalan data & fail sebenar ke persekitaran demo yang berasingan';

    public function handle(): int
    {
        $demoDb = Demo::namaDb();
        $sambungan = config('database.connections.'.config('database.default'));
        $sebenarDb = $sambungan['database'];

        if (Demo::penandaWujud()) {
            $this->error('Mod demo sedang hidup. Jalankan `php artisan demo:mati` dahulu.');

            return self::FAILURE;
        }
        $wujud = DB::selectOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$demoDb]);
        if ($wujud && ! $this->option('paksa') && ! $this->confirm("DB `{$demoDb}` sudah wujud dan akan diganti. Teruskan?")) {
            return self::FAILURE;
        }

        File::ensureDirectoryExists(Demo::folder());
        $sumber = Demo::folder('sumber_'.Str::random(8).'.sql');
        $mula = microtime(true);

        try {
            $this->line("1/4 Menyalin `{$sebenarDb}` → `{$demoDb}` ...");
            self::dump($sebenarDb, $sumber);
            DB::statement("DROP DATABASE IF EXISTS `{$demoDb}`");
            DB::statement("CREATE DATABASE `{$demoDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            PemulihanSandaran::importSql($sumber, $demoDb);

            $this->line('2/4 Membersihkan salinan demo ...');
            [$demo, $lain] = $this->bersihkan($demoDb);

            $this->line('3/4 Menyalin fail imbasan ...');
            $bilFail = $this->salinFail(config('filesystems.disks.local.root'), Demo::folder('fail'));

            $this->line('4/4 Menyimpan keadaan asas ...');
            self::dump($demoDb, Demo::folder('asas.sql'));
            File::deleteDirectory(Demo::folder('asas-fail'));
            File::copyDirectory(Demo::folder('fail'), Demo::folder('asas-fail'));
        } catch (\Throwable $e) {
            $this->error('Penyediaan demo gagal: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            File::delete($sumber);
            DB::purge('demo_sedia');
        }

        $this->info(sprintf('Demo sedia dalam %.1f s: %d akaun demo, %d akaun lain dikunci (kata laluan rawak), %d fail.',
            microtime(true) - $mula, $demo, $lain, $bilFail));
        $this->line('Hidupkan dengan: php artisan demo:hidup');

        return self::SUCCESS;
    }

    /** mysqldump satu DB ke fail (kata laluan melalui persekitaran, tidak dalam senarai proses). */
    public static function dump(string $namaDb, string $fail): void
    {
        $db = config('database.connections.'.config('database.default'));
        $hasil = Process::timeout((int) config('services.sandaran.had_masa', 900))
            ->env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])
            ->run([
                config('services.sandaran.mysqldump_bin', 'mysqldump'),
                '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
                '--single-transaction', '--routines', '--triggers',
                '--default-character-set=utf8mb4', '--result-file='.$fail, $namaDb,
            ]);

        if ($hasil->failed() || ! File::exists($fail) || File::size($fail) === 0) {
            throw new \RuntimeException('mysqldump gagal: '.trim($hasil->errorOutput() ?: $hasil->output() ?: 'tiada output'));
        }
    }

    /** @return array{0: int, 1: int} [bil akaun demo, bil akaun lain] */
    private function bersihkan(string $demoDb): array
    {
        $nama = config('database.default');
        config(['database.connections.demo_sedia' => array_merge(config("database.connections.{$nama}"), ['database' => $demoDb])]);
        $db = DB::connection('demo_sedia');
        $skema = Schema::connection('demo_sedia');

        foreach (self::DIKOSONGKAN as $jadual) {
            if ($skema->hasTable($jadual)) {
                $db->table($jadual)->delete();
            }
        }

        $akaunDemo = Demo::akaun();
        $lajur = fn (string $l) => $skema->hasColumn('users', $l);

        // Akaun bukan demo: kata laluan rawak (tiada siapa tahu), log masuk "ingat saya" dibatalkan
        $lain = $db->table('users')->whereNotIn('ic_pekerja', $akaunDemo)->pluck('user_id');
        foreach ($lain as $id) {
            $db->table('users')->where('user_id', $id)->update(['password' => Hash::make(Str::random(40)), 'remember_token' => null]);
        }

        // Akaun demo: boleh dimasuki terus melalui butang (tanpa 2FA, kunci atau paksaan tukar kata laluan)
        $kosongkan = array_filter([
            'remember_token' => null,
            'dua_faktor_rahsia' => $lajur('dua_faktor_rahsia') ? null : false,
            'dua_faktor_kod_pemulihan' => $lajur('dua_faktor_kod_pemulihan') ? null : false,
            'dua_faktor_disahkan_pada' => $lajur('dua_faktor_disahkan_pada') ? null : false,
            'dikunci_hingga' => $lajur('dikunci_hingga') ? null : false,
            'sebab_kunci' => $lajur('sebab_kunci') ? null : false,
            'perlu_tukar_kata_laluan' => $lajur('perlu_tukar_kata_laluan') ? 0 : false,
            'kata_laluan_tamat_pada' => $lajur('kata_laluan_tamat_pada') ? null : false,
        ], fn ($v) => $v !== false);
        $db->table('users')->whereIn('ic_pekerja', $akaunDemo)->update($kosongkan);

        return [$db->table('users')->whereIn('ic_pekerja', $akaunDemo)->count(), $lain->count()];
    }

    private function salinFail(string $dari, string $ke): int
    {
        File::deleteDirectory($ke);
        File::ensureDirectoryExists($ke);
        $bil = 0;
        foreach (File::allFiles($dari) as $f) {
            $relatif = str_replace('\\', '/', $f->getRelativePathname());
            if (in_array(strtok($relatif, '/'), self::DIKECUALIKAN, true) || $f->getFilename() === '.gitignore') {
                continue;
            }
            File::ensureDirectoryExists(dirname("{$ke}/{$relatif}"));
            File::copy($f->getPathname(), "{$ke}/{$relatif}");
            $bil++;
        }

        return $bil;
    }
}
