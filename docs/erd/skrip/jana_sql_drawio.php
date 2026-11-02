<?php

// DDL ringkas (serasi pengimport SQL draw.io) daripada skema sebenar dms_pkink_db. BACA sahaja.
// Tanpa backtick, ENGINE, GENERATED, ON DELETE, UNIQUE; satu lajur setiap baris; PRIMARY KEY & FOREIGN KEY.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$db = 'dms_pkink_db';
$kecuali = ['cache', 'cache_locks', 'jobs', 'failed_jobs', 'password_reset_tokens'];
// Susunan ikut kumpulan supaya kotak yang berkaitan diletak berdekatan
$susunan = ['bahagian', 'users', 'roles', 'user_roles', 'pendaftaran_staf', 'delegasi_kelulusan',
    'jenis_dokumen', 'dokumen_field', 'templat_borang', 'dokumen', 'dokumen_data',
    'dokumen_scan', 'dokumen_ocr', 'pembetulan_ai', 'borang', 'borang_sejarah',
    'log_audit', 'log_masuk', 'ip_disekat', 'sessions', 'notifications', 'push_subscriptions'];

$lajur = collect(DB::select('SELECT TABLE_NAME t, COLUMN_NAME c, DATA_TYPE dt, COLUMN_TYPE ty, IS_NULLABLE n, COLUMN_KEY k
    FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME, ORDINAL_POSITION', [$db]))->groupBy('t');
$fk = collect(DB::select('SELECT TABLE_NAME t, COLUMN_NAME c, REFERENCED_TABLE_NAME rt, REFERENCED_COLUMN_NAME rc
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, COLUMN_NAME', [$db]))->groupBy('t');

$semua = $lajur->keys()->diff($kecuali);
if ($semua->diff($susunan)->isNotEmpty() || collect($susunan)->diff($semua)->isNotEmpty()) {
    fwrite(STDERR, 'Senarai jadual tidak sepadan: '.$semua->diff($susunan)->merge(collect($susunan)->diff($semua))->implode(', ')."\n");
    exit(1);
}

// Jenis ringkas: panjang dikekalkan bagi varchar/char; tiada "unsigned", tiada koma dalam kurungan
$jenis = function ($c) {
    return match ($c->dt) {
        'varchar' => 'VARCHAR'.(preg_match('/\((\d+)\)/', $c->ty, $m) ? "({$m[1]})" : ''),
        'char' => 'CHAR'.(preg_match('/\((\d+)\)/', $c->ty, $m) ? "({$m[1]})" : ''),
        default => strtoupper($c->dt),
    };
};

$keluar = [];
foreach ($susunan as $t) {
    $baris = [];
    foreach ($lajur[$t] as $c) {
        $baris[] = "  {$c->c} ".$jenis($c).($c->n === 'NO' ? ' NOT NULL' : '');
    }
    $pk = $lajur[$t]->where('k', 'PRI')->pluck('c')->implode(', ');
    if ($pk !== '') {
        $baris[] = "  PRIMARY KEY ({$pk})";
    }
    foreach ($fk->get($t, collect()) as $f) {
        $baris[] = "  FOREIGN KEY ({$f->c}) REFERENCES {$f->rt}({$f->rc})";
    }
    $keluar[] = "CREATE TABLE {$t} (\n".implode(",\n", $baris)."\n);";
}
// Tulis terus ke fail (argumen 2) supaya pengekodan kekal UTF-8 (">" dalam PowerShell 5.1 menulis UTF-16)
isset($argv[2]) ? file_put_contents($argv[2], implode("\n\n", $keluar)."\n") : print implode("\n\n", $keluar)."\n";
