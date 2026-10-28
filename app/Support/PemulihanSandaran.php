<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Operasi sandaran yang dikongsi (Fasa Keselamatan S4): cari sandaran terkini, buka zip
 * (disulitkan AES-256 dengan SANDARAN_KATA_LALUAN), import SQL ke pangkalan data, dan
 * pulihkan fail imbasan. Dipakai oleh sandaran:uji (ke DB sementara) dan sandaran:pulih.
 */
class PemulihanSandaran
{
    /** Awalan DB sementara: pengguna DB aplikasi diberi keistimewaan pada corak ini sahaja. */
    public const AWALAN_DB_UJI = 'dms_ujian_pulih_';

    public static function folder(): string
    {
        return rtrim((string) config('services.sandaran.folder'), '\\/');
    }

    /** Zip sandaran terkini dalam folder (atau null). */
    public static function terkini(?string $folder = null): ?string
    {
        $fail = File::glob(($folder ?? self::folder()).'/sandaran_*.zip');
        sort($fail);

        return $fail ? end($fail) : null;
    }

    /**
     * Buka zip ke folder sementara. Pulangkan [folder, laluan .sql].
     *
     * @return array{0: string, 1: string}
     */
    public static function buka(string $zip): array
    {
        $arkib = new ZipArchive;
        if ($arkib->open($zip) !== true) {
            throw new \RuntimeException("Fail sandaran tidak dapat dibuka: {$zip}");
        }
        if ($kataLaluan = config('services.sandaran.kata_laluan')) {
            $arkib->setPassword((string) $kataLaluan);
        }

        $folder = storage_path('app/pulih_sementara/'.Str::random(12));
        File::ensureDirectoryExists($folder);
        if (! $arkib->extractTo($folder)) {
            $arkib->close();
            File::deleteDirectory($folder);
            throw new \RuntimeException('Fail sandaran tidak dapat diekstrak. Jika ia disulitkan, pastikan SANDARAN_KATA_LALUAN betul.');
        }
        $arkib->close();

        $sql = File::glob("{$folder}/pangkalan_data/*.sql")[0] ?? null;
        if (! $sql) {
            File::deleteDirectory($folder);
            throw new \RuntimeException('Sandaran tidak mengandungi fail pangkalan data (.sql).');
        }

        return [$folder, $sql];
    }

    /** Import fail SQL ke pangkalan data $namaDb (mesti sudah wujud). */
    public static function importSql(string $sql, string $namaDb): void
    {
        $db = config('database.connections.'.config('database.default'));
        $proses = new Process([
            self::binMysql(),
            '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
            '--default-character-set=utf8mb4', $namaDb,
        ], null, ['MYSQL_PWD' => (string) ($db['password'] ?? '')]);
        $proses->setInput(fopen($sql, 'r'));
        $proses->setTimeout((int) config('services.sandaran.had_masa', 900));
        $proses->run();

        if (! $proses->isSuccessful()) {
            throw new \RuntimeException('Import SQL gagal: '.trim($proses->getErrorOutput() ?: $proses->getOutput()));
        }
    }

    /** Salin semula fail imbasan (fail/...) ke disk `local`. Pulangkan bilangan fail. */
    public static function pulihkanFail(string $folder): int
    {
        $sumber = "{$folder}/fail";
        if (! File::isDirectory($sumber)) {
            return 0;
        }
        $sasaran = storage_path('app/private');
        $bil = 0;
        foreach (File::allFiles($sumber) as $f) {
            $tuju = $sasaran.DIRECTORY_SEPARATOR.$f->getRelativePathname();
            File::ensureDirectoryExists(dirname($tuju));
            File::copy($f->getPathname(), $tuju);
            $bil++;
        }

        return $bil;
    }

    public static function binMysql(): string
    {
        return (string) (config('services.sandaran.mysql_bin')
            ?: str_ireplace('mysqldump', 'mysql', (string) config('services.sandaran.mysqldump_bin', 'mysqldump')));
    }

    public static function tulisStatus(string $nama, array $status): void
    {
        try {
            File::ensureDirectoryExists(self::folder());
            File::put(self::folder()."/{$nama}", json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (\Throwable) {
            // Status maklumat tambahan sahaja
        }
    }
}
