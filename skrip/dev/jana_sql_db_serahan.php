<?php

/**
 * Alat pembangun (TIDAK termasuk dalam pakej serahan), Fasa H-B.
 *
 * Menjana skrip SQL yang membina pangkalan data serahan `dms_pkink_serahan` daripada DB
 * pembangunan (DB_DATABASE dalam .env), untuk dijalankan oleh pentadbir dalam HeidiSQL:
 *   - struktur penuh (mysqldump --no-data: semua jadual, indeks, kunci asing, lajur janaan),
 *     tanpa nilai AUTO_INCREMENT lama dan tanpa lajur lapuk dokumen.status;
 *   - data konfigurasi AKTIF sahaja: peranan, bahagian, kategori, medan, templat borang;
 *   - tiada dokumen, log, notifikasi atau akaun lama;
 *   - akaun UAT: satu bagi setiap peranan, kata laluan sementara rawak (wajib ditukar).
 *
 * DB sumber hanya DIBACA. Hasil (di luar repo):
 *   ../Serahan_DMS_PKINK/sql/bina_db_serahan.sql
 *   ../Serahan_DMS_PKINK/rahsia/AKAUN_UAT.md   (kata laluan sementara; JANGAN dimasukkan ke zip)
 *
 * Guna:  php skrip/dev/jana_sql_db_serahan.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$akar = dirname(__DIR__, 2);
require $akar.'/vendor/autoload.php';
$app = require $akar.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const DB_SERAHAN = 'dms_pkink_serahan';
const BAHAGIAN_UAT = 'BD'; // akaun UAT dalam Bahagian Digital

$sumber = config('database.connections.mysql.database');
$folderKeluar = dirname($akar).DIRECTORY_SEPARATOR.'Serahan_DMS_PKINK';
@mkdir($folderKeluar.'/sql', 0775, true);
@mkdir($folderKeluar.'/rahsia', 0775, true);

// 1. Struktur (baca sahaja)
$dump = config('services.sandaran.mysqldump_bin', 'mysqldump');
$db = config('database.connections.mysql');
$proses = new Symfony\Component\Process\Process(
    [$dump, '-h', $db['host'], '-P', (string) $db['port'], '-u', $db['username'], '--no-data', '--skip-comments',
        '--skip-add-drop-table', '--routines', '--triggers', $sumber],
    null, ['MYSQL_PWD' => (string) ($db['password'] ?? '')]
);
$proses->setTimeout(120)->mustRun();
$skema = $proses->getOutput();

$skema = preg_replace('/ AUTO_INCREMENT=\d+/', '', $skema);
$sebelum = substr_count($skema, "`status` varchar(50) NOT NULL DEFAULT 'Archived',");
if ($sebelum !== 1) {
    fwrite(STDERR, "Lajur dokumen.status tidak dijumpai tepat sekali ({$sebelum}).\n");
    exit(1);
}
$skema = preg_replace("/^[ \\t]*`status` varchar\\(50\\) NOT NULL DEFAULT 'Archived',\\R/m", '', $skema);
if (str_contains($skema, "`status` varchar(50) NOT NULL DEFAULT 'Archived'")) {
    fwrite(STDERR, "Lajur dokumen.status gagal dibuang.\n");
    exit(1);
}
$bilJadual = substr_count($skema, 'CREATE TABLE');
$bilFk = substr_count($skema, 'FOREIGN KEY');

// 2. Lajur bukan janaan bagi INSERT ... SELECT
$lajur = function (string $jadual) use ($sumber): string {
    return collect(DB::select(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND EXTRA NOT LIKE '%GENERATED%' ORDER BY ORDINAL_POSITION",
        [$sumber, $jadual]
    ))->pluck('COLUMN_NAME')->map(fn ($c) => "`{$c}`")->implode(', ');
};

$salin = function (string $jadual, string $syarat = '') use ($lajur, $sumber): string {
    $l = $lajur($jadual);

    return "INSERT INTO `{$jadual}` ({$l})\n  SELECT {$l} FROM `{$sumber}`.`{$jadual}`".($syarat ? "\n  WHERE {$syarat}" : '').";\n";
};

// 3. Akaun UAT
$abjad = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
$kataLaluan = function () use ($abjad): string {
    do {
        $k = '';
        for ($i = 0; $i < 12; $i++) {
            $k .= $abjad[random_int(0, strlen($abjad) - 1)];
        }
    } while (! preg_match('/[A-Za-z]/', $k) || ! preg_match('/\d/', $k));

    return $k;
};
$akaun = [
    ['SUPERADMIN', 'Super Admin'],
    ['ADMIN', 'Pentadbir Sistem'],
    ['KERANI', 'Kerani Arkib'],
    ['PENGURUS', 'Pengurus Bahagian'],
    ['STAFF', 'Kakitangan'],
    ['PENYOKONG', 'Pegawai Penyokong'],
    ['PELULUS', 'Pegawai Pelulus'],
    ['AUDIT', 'Juruaudit'],
];
$bahagian = DB::table('bahagian')->where('kod_bahagian', BAHAGIAN_UAT)->whereNull('deleted_at')->first();
if (! $bahagian) {
    fwrite(STDERR, 'Bahagian '.BAHAGIAN_UAT." tidak dijumpai.\n");
    exit(1);
}
$peranan = DB::table('roles')->pluck('roles_id', 'kod_roles');

$sqlAkaun = '';
$senarai = [];
foreach ($akaun as $i => [$kod, $nama]) {
    $id = $i + 1;
    $ic = sprintf('0000000000%02d', $id);
    $emel = 'uat.'.strtolower($kod).'@pkink.gov.my';
    $kl = $kataLaluan();
    $hash = Hash::make($kl);
    $sqlAkaun .= sprintf(
        "INSERT INTO `users` (`user_id`, `ic_pekerja`, `nama_staff`, `jawatan`, `email`, `password`, `perlu_tukar_kata_laluan`, `bahagian_id`, `created_at`, `updated_at`)\n  VALUES (%d, '%s', '%s', '%s', '%s', '%s', 1, %d, NOW(), NOW());\n",
        $id, $ic, "Pengguna UAT {$nama}", 'Akaun Ujian Penerimaan Pengguna (UAT)', $emel, $hash, $bahagian->bahagian_id
    );
    $sqlAkaun .= sprintf("INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES (%d, %d);\n", $id, $peranan[$kod]);
    $senarai[] = [$kod, "Pengguna UAT {$nama}", $ic, $kl, $emel];
}

// 4. Skrip SQL
$tarikh = now()->format('Y-m-d H:i');
$sql = <<<SQL
-- =============================================================================
-- Sistem Arkib Digital (DMS) PKINK: bina pangkalan data serahan (Fasa H-B)
-- Dijana: {$tarikh} daripada `{$sumber}` (DIBACA SAHAJA; tidak diubah)
--
-- Jalankan keseluruhan skrip ini dalam HeidiSQL (F9) sebagai pentadbir MySQL.
-- Hasil: pangkalan data BAHARU `dms_pkink_serahan` ({$bilJadual} jadual, {$bilFk} kunci asing) dengan
-- konfigurasi aktif dan 8 akaun UAT. Tiada dokumen, log atau akaun lama.
-- =============================================================================

DROP DATABASE IF EXISTS `dms_pkink_serahan`;
CREATE DATABASE `dms_pkink_serahan` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `dms_pkink_serahan`;

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Struktur
-- -----------------------------------------------------------------------------
{$skema}

-- -----------------------------------------------------------------------------
-- 2. Data konfigurasi (rekod aktif sahaja)
-- -----------------------------------------------------------------------------

SQL;
$sql .= $salin('roles');
$sql .= $salin('bahagian', '`deleted_at` IS NULL');
$sql .= $salin('jenis_dokumen', '`deleted_at` IS NULL');
$sql .= $salin('dokumen_field', "`deleted_at` IS NULL AND `jenis_dokumen_id` IN (SELECT `jenis_dokumen_id` FROM `{$sumber}`.`jenis_dokumen` WHERE `deleted_at` IS NULL)");
$sql .= $salin('templat_borang', "`deleted_at` IS NULL AND `jenis_dokumen_id` IN (SELECT `jenis_dokumen_id` FROM `{$sumber}`.`jenis_dokumen` WHERE `deleted_at` IS NULL)");
$sql .= "UPDATE `templat_borang` SET `created_by` = NULL;\n";
$sql .= <<<SQL

-- -----------------------------------------------------------------------------
-- 3. Akaun UAT (kata laluan sementara dalam dokumen AKAUN_UAT, wajib ditukar)
-- -----------------------------------------------------------------------------
{$sqlAkaun}
SET FOREIGN_KEY_CHECKS = 1;

-- Semakan ringkas (hasil dipaparkan dalam HeidiSQL)
SELECT 'peranan' AS jadual, COUNT(*) AS bil FROM `roles`
UNION ALL SELECT 'bahagian', COUNT(*) FROM `bahagian`
UNION ALL SELECT 'kategori', COUNT(*) FROM `jenis_dokumen`
UNION ALL SELECT 'medan', COUNT(*) FROM `dokumen_field`
UNION ALL SELECT 'templat', COUNT(*) FROM `templat_borang`
UNION ALL SELECT 'pengguna', COUNT(*) FROM `users`
UNION ALL SELECT 'dokumen', COUNT(*) FROM `dokumen`
UNION ALL SELECT 'log_audit', COUNT(*) FROM `log_audit`;

SQL;
file_put_contents($folderKeluar.'/sql/bina_db_serahan.sql', $sql);

// 5. Senarai akaun (sulit)
$md = "# Akaun Ujian Penerimaan Pengguna (UAT)\n\n**SULIT.** Untuk pentadbir sistem PKINK sahaja. Dijana: {$tarikh}.\n\n";
$md .= "| Peranan | Nama | No. KP (log masuk) | Kata laluan sementara | Emel |\n|---|---|---|---|---|\n";
foreach ($senarai as [$kod, $nama, $ic, $kl, $emel]) {
    $md .= "| {$kod} | {$nama} | `{$ic}` | `{$kl}` | {$emel} |\n";
}
file_put_contents($folderKeluar.'/rahsia/AKAUN_UAT.md', $md);

echo "Ditulis: {$folderKeluar}/sql/bina_db_serahan.sql ({$bilJadual} jadual, {$bilFk} kunci asing)\n";
echo "Ditulis: {$folderKeluar}/rahsia/AKAUN_UAT.md (8 akaun)\n";
