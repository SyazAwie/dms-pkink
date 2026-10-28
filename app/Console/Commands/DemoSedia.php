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
 * Sediakan pangkalan data & fail bagi mod demo atau percubaan (Fasa D2):
 * 1. mysqldump DB sebenar (BACA sahaja) → cipta semula DB mod → import;
 * 2. bersihkan salinan (sesi, sekatan IP, token, Web Push) dan kunci akaun:
 *    demo       akaun bukan demo dapat kata laluan rawak; akaun demo tanpa 2FA/kunci;
 *    percubaan  SEMUA akaun dapat kata laluan rawak kecuali --kekal (akaun pentadbir yang
 *               meluluskan pendaftaran peserta), kerana sistem penuh dibuka ke Internet;
 * 3. salin fail imbasan ke storage/app/{mod}/fail;
 * 4. simpan keadaan asas (asas.sql + asas-fail/) untuk demo:set-semula.
 *
 * DB sebenar tidak pernah ditulis: semua penulisan melalui sambungan `demo_sedia` ke DB mod.
 */
class DemoSedia extends Command
{
    /** Fail dalam disk `local` yang tidak disalin. */
    private const DIKECUALIKAN = ['ocr_sementara', 'pulih_sementara', 'keselamatan.json'];

    /** Jadual yang dikosongkan dalam salinan (jika wujud). */
    private const DIKOSONGKAN = ['sessions', 'ip_disekat', 'cache', 'cache_locks', 'jobs', 'failed_jobs', 'password_reset_tokens'];

    protected $signature = 'demo:sedia
                            {--percubaan : Sediakan mod percubaan (sistem penuh) dan bukan mod demo}
                            {--kekal=* : Mod percubaan: No. KP akaun yang kekal boleh log masuk (cth. akaun SUPERADMIN anda)}
                            {--paksa : Ganti DB sedia ada tanpa bertanya}';

    protected $description = 'Salin pangkalan data & fail sebenar ke persekitaran demo/percubaan yang berasingan';

    public function handle(): int
    {
        $mod = $this->option('percubaan') ? Demo::PERCUBAAN : Demo::DEMO;
        $modDb = Demo::namaDb($mod);
        $sebenarDb = config('database.connections.'.config('database.default').'.database');
        $kekal = array_values(array_filter(array_map('trim', (array) $this->option('kekal'))));

        if (Demo::modPenanda() === $mod) {
            $this->error("Mod {$mod} sedang hidup. Jalankan `php artisan demo:mati` dahulu.");

            return self::FAILURE;
        }
        if ($mod === Demo::PERCUBAAN) {
            if (! $kekal) {
                $this->error('Nyatakan akaun pentadbir yang kekal boleh log masuk: --kekal=<No. KP>. Semua akaun lain akan dikunci.');

                return self::FAILURE;
            }
            $tiada = array_diff($kekal, DB::table('users')->whereIn('ic_pekerja', $kekal)->pluck('ic_pekerja')->map(fn ($ic) => (string) $ic)->all());
            if ($tiada) {
                $this->error('No. KP tiada dalam DB sebenar: '.implode(', ', $tiada));

                return self::FAILURE;
            }
        }
        $wujud = DB::selectOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$modDb]);
        if ($wujud && ! $this->option('paksa') && ! $this->confirm("DB `{$modDb}` sudah wujud dan akan diganti. Teruskan?")) {
            return self::FAILURE;
        }

        File::ensureDirectoryExists(Demo::folderData($mod));
        $sumber = Demo::folderData($mod, 'sumber_'.Str::random(8).'.sql');
        $mula = microtime(true);

        try {
            $this->line("1/4 Menyalin `{$sebenarDb}` → `{$modDb}` ...");
            self::dump($sebenarDb, $sumber);
            DB::statement("DROP DATABASE IF EXISTS `{$modDb}`");
            DB::statement("CREATE DATABASE `{$modDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            PemulihanSandaran::importSql($sumber, $modDb);

            $this->line('2/4 Membersihkan salinan ...');
            [$bolehMasuk, $dikunci] = $this->bersihkan($modDb, $mod === Demo::DEMO ? Demo::akaun() : $kekal, $mod);

            $this->line('3/4 Menyalin fail imbasan ...');
            $bilFail = $this->salinFail(config('filesystems.disks.local.root'), Demo::folderData($mod, 'fail'));

            $this->line('4/4 Menyimpan keadaan asas ...');
            self::dump($modDb, Demo::folderData($mod, 'asas.sql'));
            File::deleteDirectory(Demo::folderData($mod, 'asas-fail'));
            File::copyDirectory(Demo::folderData($mod, 'fail'), Demo::folderData($mod, 'asas-fail'));
        } catch (\Throwable $e) {
            $this->error('Penyediaan gagal: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            File::delete($sumber);
            DB::purge('demo_sedia');
        }

        $this->info(sprintf('Mod %s sedia dalam %.1f s: %d akaun boleh log masuk, %d akaun dikunci (kata laluan rawak), %d fail.',
            $mod, microtime(true) - $mula, $bolehMasuk, $dikunci, $bilFail));
        $this->line('Hidupkan dengan: php artisan demo:hidup'.($mod === Demo::PERCUBAAN ? ' --percubaan' : ''));

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

    /**
     * @param  list<string>  $bolehMasuk  No. KP akaun yang kekal boleh log masuk
     * @return array{0: int, 1: int} [bil akaun boleh masuk, bil akaun dikunci]
     */
    private function bersihkan(string $modDb, array $bolehMasuk, string $mod): array
    {
        $nama = config('database.default');
        config(['database.connections.demo_sedia' => array_merge(config("database.connections.{$nama}"), ['database' => $modDb])]);
        $db = DB::connection('demo_sedia');
        $skema = Schema::connection('demo_sedia');

        foreach (self::DIKOSONGKAN as $jadual) {
            if ($skema->hasTable($jadual)) {
                $db->table($jadual)->delete();
            }
        }

        $idMasuk = $db->table('users')->whereIn('ic_pekerja', $bolehMasuk)->pluck('user_id');

        // Langganan Web Push: hanya akaun yang kekal (pentadbir percubaan boleh terima notifikasi peranti)
        if ($skema->hasTable('push_subscriptions')) {
            $mod === Demo::PERCUBAAN && $skema->hasColumn('push_subscriptions', 'subscribable_id')
                ? $db->table('push_subscriptions')->whereNotIn('subscribable_id', $idMasuk)->delete()
                : $db->table('push_subscriptions')->delete();
        }

        // Akaun lain: kata laluan rawak (tiada siapa tahu), log masuk "ingat saya" dibatalkan
        $lain = $db->table('users')->whereNotIn('ic_pekerja', $bolehMasuk)->pluck('user_id');
        foreach ($lain as $id) {
            $db->table('users')->where('user_id', $id)->update(['password' => Hash::make(Str::random(40)), 'remember_token' => null]);
        }
        // Percubaan: tanda dikunci juga, supaya notifikasi emel (dihidupkan dalam mod ini) tidak
        // dihantar ke alamat rekaan akaun ujian (emel lantun ke peti pengirim)
        if ($mod === Demo::PERCUBAAN && $skema->hasColumn('users', 'dikunci_hingga')) {
            $db->table('users')->whereIn('user_id', $lain)
                // TIMESTAMP MySQL terhad sehingga 2038-01-19
                ->update(['dikunci_hingga' => '2037-12-31 23:59:59', 'sebab_kunci' => 'Mod percubaan: akaun ujian dikunci']);
        }

        // Mod demo: akaun demo dimasuki terus melalui butang (tanpa 2FA, kunci atau paksaan tukar
        // kata laluan). Mod percubaan: akaun pentadbir dikekalkan seperti asal (termasuk 2FA).
        if ($mod === Demo::DEMO) {
            $lajur = fn (string $l) => $skema->hasColumn('users', $l);
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
            $db->table('users')->whereIn('ic_pekerja', $bolehMasuk)->update($kosongkan);
        }

        return [$idMasuk->count(), $lain->count()];
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
