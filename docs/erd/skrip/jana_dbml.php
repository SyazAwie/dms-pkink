<?php

// DBML (dbdiagram.io) daripada skema sebenar dms_pkink_db. BACA sahaja.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$db = getenv('ERD_DB') ?: 'dms_pkink_db'; // ERD_DB=dms_pkink_pemasangan untuk pakej pemasangan
$kumpulan = [
    'Organisasi_dan_Akses' => ['bahagian', 'users', 'roles', 'user_roles', 'pendaftaran_staf', 'delegasi_kelulusan'],
    'Templat_Dokumen_EAV' => ['jenis_dokumen', 'dokumen_field', 'templat_borang'],
    'Dokumen_Sebenar' => ['dokumen', 'dokumen_data'],
    'Imbasan_OCR_AI' => ['dokumen_scan', 'dokumen_ocr', 'pembetulan_ai'],
    'Aliran_Kelulusan' => ['borang', 'borang_sejarah'],
    'Audit_dan_Keselamatan' => ['log_audit', 'log_masuk', 'ip_disekat'],
    'Sokongan_Laravel' => ['sessions', 'notifications', 'push_subscriptions'],
];
$notaJadual = [
    'users' => 'Pengguna sistem. Log masuk dengan No. KP. Banyak peranan melalui user_roles. Soft delete.',
    'roles' => '8 peranan: SUPERADMIN, ADMIN, KERANI, PENGURUS, STAFF, PENYOKONG, PELULUS, AUDIT',
    'user_roles' => 'Jadual simpang Many-to-Many users-roles. PERHATIAN: role_id merujuk roles.roles_id',
    'jenis_dokumen' => 'Kategori dokumen / borang (EAV: entiti)',
    'dokumen_field' => 'Definisi medan bagi setiap kategori (EAV: atribut). pilihan & tetapan = JSON',
    'dokumen_data' => 'Nilai medan bagi setiap dokumen (EAV: nilai). Satu baris setiap pasangan dokumen + medan',
    'dokumen' => 'Dokumen sebenar. no_rujukan {KOD}/{TAHUN}/{NNNN}. Soft delete',
    'borang' => 'Permohonan kelulusan: SATU borang bagi setiap dokumen (1:1, dikuatkuasa aplikasi). Merujuk users 4 kali: pemohon, penyokong, pelulus, penolak',
    'borang_sejarah' => 'Sejarah setiap tindakan kelulusan (hantar/sokong/lulus/tolak); bagi_pihak_id = delegasi',
    'templat_borang' => 'Templat borang kosong berversi bagi kategori; kedudukan_medan = JSON susunan borang siap. created_by -> users.user_id (logik, tiada FK)',
    'log_audit' => 'Jejak audit. (nama_jadual, rekod_id) merujuk rekod dalam MANA-MANA jadual (polimorfik, tiada FK)',
    'delegasi_kelulusan' => 'Delegasi kuasa sokong/lulus semasa cuti. kod_peranan merujuk roles.kod_roles (logik)',
    'notifications' => 'Notifikasi Laravel; notifiable_id -> users.user_id bila notifiable_type = App\\Models\\User (polimorfik)',
    'push_subscriptions' => 'Langganan Web Push; subscribable_id -> users.user_id (polimorfik)',
    'pembetulan_ai' => 'Nilai cadangan AI berbanding nilai akhir yang disimpan staf',
];

$lajur = collect(DB::select('SELECT TABLE_NAME t, COLUMN_NAME c, DATA_TYPE dt, COLUMN_TYPE ty, IS_NULLABLE n, COLUMN_KEY k, COLUMN_DEFAULT d, EXTRA e, GENERATION_EXPRESSION g
    FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME, ORDINAL_POSITION', [$db]))->groupBy('t');
$unik = collect(DB::select('SELECT TABLE_NAME t, INDEX_NAME i, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) k
    FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=? AND NON_UNIQUE=0 AND INDEX_NAME<>"PRIMARY" GROUP BY TABLE_NAME, INDEX_NAME', [$db]))->groupBy('t');
$fk = collect(DB::select('SELECT k.TABLE_NAME t, k.COLUMN_NAME c, k.REFERENCED_TABLE_NAME rt, k.REFERENCED_COLUMN_NAME rc, r.DELETE_RULE dr
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r
      ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
    WHERE k.TABLE_SCHEMA=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME, k.COLUMN_NAME', [$db]));

$teks = fn (string $s) => "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $s)."'";
$jenis = function ($c) {
    $t = preg_replace('/\s+/', ' ', strtolower($c->ty));

    return preg_match('/[\s(]/', $t) && ! preg_match('/^[a-z]+\([\d,]+\)$/', $t) ? "\"{$t}\"" : $t;
};
$lalai = function ($c) use ($teks) {
    $d = $c->d;
    if ($d === null || $d === 'NULL' || $c->g) {
        return null;
    }
    if (preg_match('/^current_timestamp/i', $d)) {
        return 'default: `CURRENT_TIMESTAMP`';
    }
    if (preg_match("/^'(.*)'$/s", $d, $m)) {
        return 'default: '.$teks($m[1]);
    }

    return is_numeric($d) ? "default: {$d}" : 'default: '.$teks($d);
};

$unikTunggal = [];
$unikGabung = [];
foreach ($unik as $t => $senarai) {
    foreach ($senarai as $u) {
        str_contains($u->k, ',') ? $unikGabung[$t][] = $u->k : $unikTunggal[$t][$u->k] = true;
    }
}

$keluar = ["Project DMS_PKINK {\n  database_type: 'MySQL'\n  Note: 'Sistem Arkib Digital (DMS) PKINK. Skema pangkalan data sistem (22 jadual domain; cache, cache_locks, jobs, failed_jobs, password_reset_tokens tidak dimasukkan).'\n}"];
foreach ($kumpulan as $jadualSenarai) {
    foreach ($jadualSenarai as $t) {
        $baris = [];
        foreach ($lajur[$t] as $c) {
            $tetapan = [];
            if ($c->k === 'PRI') {
                $tetapan[] = 'pk';
            }
            if (str_contains($c->e, 'auto_increment')) {
                $tetapan[] = 'increment';
            }
            if ($c->n === 'NO' && $c->k !== 'PRI') {
                $tetapan[] = 'not null';
            }
            if (isset($unikTunggal[$t][$c->c])) {
                $tetapan[] = 'unique';
            }
            if ($d = $lalai($c)) {
                $tetapan[] = $d;
            }
            if ($c->g) {
                $tetapan[] = 'note: '.$teks('Lajur dijana: '.preg_replace('/\s+/', ' ', str_replace('`', '', $c->g)));
            }
            $baris[] = "  {$c->c} ".$jenis($c).($tetapan ? ' ['.implode(', ', $tetapan).']' : '');
        }
        if (! empty($unikGabung[$t])) {
            $baris[] = "\n  indexes {";
            foreach ($unikGabung[$t] as $k) {
                $baris[] = '    ('.str_replace(',', ', ', $k).') [unique]';
            }
            $baris[] = '  }';
        }
        if (isset($notaJadual[$t])) {
            $baris[] = "\n  Note: ".$teks($notaJadual[$t]);
        }
        $keluar[] = "Table {$t} {\n".implode("\n", $baris)."\n}";
    }
}
foreach ($fk as $f) {
    $keluar[] = "Ref: {$f->t}.{$f->c} > {$f->rt}.{$f->rc} [delete: ".strtolower($f->dr).']';
}
foreach ($kumpulan as $nama => $jadualSenarai) {
    $keluar[] = "TableGroup {$nama} {\n  ".implode("\n  ", $jadualSenarai)."\n}";
}
// Tulis terus ke fail (argumen 2) supaya pengekodan kekal UTF-8 (">" dalam PowerShell 5.1 menulis UTF-16)
isset($argv[2]) ? file_put_contents($argv[2], implode("\n\n", $keluar)."\n") : print implode("\n\n", $keluar)."\n";
