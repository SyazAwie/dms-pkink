<?php

// Fail .drawio (ERD lengkap: jadual, PK/FK, garis crow's foot) daripada skema sebenar dms_pkink_db. BACA sahaja.
// Guna: php eksport_drawio_xml.php <folder projek> <fail output .drawio>
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$db = getenv('ERD_DB') ?: 'dms_pkink_db'; // ERD_DB=dms_pkink_pemasangan untuk pakej pemasangan
// [nama kumpulan, warna kepala, warna garis, senarai jadual]
$kumpulan = [
    ['Organisasi & Akses', '#dae8fc', '#6c8ebf', ['bahagian', 'roles', 'user_roles', 'users', 'pendaftaran_staf', 'delegasi_kelulusan']],
    ['Templat Dokumen (EAV)', '#d5e8d4', '#82b366', ['jenis_dokumen', 'dokumen_field', 'templat_borang']],
    ['Dokumen Sebenar', '#fff2cc', '#d6b656', ['dokumen', 'dokumen_data']],
    ['Imbasan, OCR & AI', '#e1d5e7', '#9673a6', ['dokumen_scan', 'dokumen_ocr', 'pembetulan_ai']],
    ['Aliran Kelulusan', '#f8cecc', '#b85450', ['borang', 'borang_sejarah']],
    ['Audit & Keselamatan', '#ffe6cc', '#d79b00', ['log_audit', 'log_masuk', 'ip_disekat']],
    ['Sokongan Laravel', '#f5f5f5', '#666666', ['sessions', 'notifications', 'push_subscriptions']],
];
// Hubungan logik (tiada FK dalam DB): [anak, lajur anak, induk, lajur induk, label]
$logik = [
    ['templat_borang', 'created_by', 'users', 'user_id', 'mencipta (logik)'],
    ['notifications', 'notifiable_id', 'users', 'user_id', 'notifiable (polimorfik)'],
    ['push_subscriptions', 'subscribable_id', 'users', 'user_id', 'subscribable (polimorfik)'],
    ['delegasi_kelulusan', 'kod_peranan', 'roles', 'kod_roles', 'ikut kod (logik)'],
];

$lajur = collect(DB::select('SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE ty, IS_NULLABLE n, COLUMN_KEY k, GENERATION_EXPRESSION g
    FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME, ORDINAL_POSITION', [$db]))->groupBy('t');
$unik = collect(DB::select('SELECT TABLE_NAME t, COLUMN_NAME c FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA=? AND NON_UNIQUE=0 AND INDEX_NAME<>"PRIMARY"', [$db]))->map(fn ($r) => "{$r->t}.{$r->c}")->flip();
$fk = collect(DB::select('SELECT k.TABLE_NAME t, k.COLUMN_NAME c, k.REFERENCED_TABLE_NAME rt, k.REFERENCED_COLUMN_NAME rc, r.DELETE_RULE dr
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r
      ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
    WHERE k.TABLE_SCHEMA=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME, k.COLUMN_NAME', [$db]));
$adaFk = $fk->map(fn ($f) => "{$f->t}.{$f->c}")->flip();

$x = fn (string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
$id = fn (string ...$b) => preg_replace('/[^A-Za-z0-9_]/', '_', implode('__', $b));

$LEBAR = 300;
$BARIS = 22;
$KEPALA = 30;
$JARAK_X = 420;
$JARAK_Y = 50;
$sel = [];

// Legenda
$legenda = "ERD Sistem Arkib Digital (DMS) PKINK\n".
    "Sumber: skema pangkalan data sistem (22 jadual domain, 35 FK).\n".
    "PK = kunci primer, FK = kunci asing, UK = unik (termasuk lajur dijana untuk soft delete).\n".
    "Garis penuh = FK dalam DB. Garis putus-putus = hubungan logik (tiada FK).\n".
    "|| tepat satu   |o sifar/satu   o{ sifar/banyak.\n".
    "Nota: borang 1:1 dengan dokumen (dikuatkuasa aplikasi). user_roles.role_id -> roles.roles_id.\n".
    "log_audit (nama_jadual, rekod_id) merujuk rekod dalam mana-mana jadual (polimorfik).\n".
    'Tidak dilukis: cache, cache_locks, jobs, failed_jobs, password_reset_tokens.';
$sel[] = '<mxCell id="legenda" value="'.$x($legenda).'" style="shape=note;whiteSpace=wrap;html=0;size=14;align=left;verticalAlign=top;spacingLeft=8;spacingTop=6;fillColor=#fffbe6;strokeColor=#b8a000;fontSize=12;" vertex="1" parent="1"><mxGeometry x="0" y="0" width="760" height="150" as="geometry"/></mxCell>';

$maksY = 0;
foreach ($kumpulan as $gi => [$namaKumpulan, $warna, $garis, $jadual]) {
    $gx = $gi * $JARAK_X;
    $y = 200;
    $sel[] = '<mxCell id="'.$id('kumpulan', (string) $gi).'" value="'.$x($namaKumpulan).'" style="text;html=0;fontSize=16;fontStyle=1;align=left;verticalAlign=bottom;fontColor='.$garis.';" vertex="1" parent="1"><mxGeometry x="'.$gx.'" y="160" width="'.$LEBAR.'" height="34" as="geometry"/></mxCell>';
    foreach ($jadual as $t) {
        $cols = $lajur[$t];
        $tinggi = $KEPALA + count($cols) * $BARIS;
        $tid = $id('t', $t);
        $sel[] = '<mxCell id="'.$tid.'" value="'.$x($t).'" style="swimlane;fontStyle=1;childLayout=stackLayout;horizontal=1;startSize='.$KEPALA.';horizontalStack=0;resizeParent=1;resizeParentMax=0;resizeLast=0;collapsible=1;marginBottom=0;html=0;fillColor='.$warna.';strokeColor='.$garis.';swimlaneFillColor=#ffffff;fontSize=13;" vertex="1" parent="1"><mxGeometry x="'.$gx.'" y="'.$y.'" width="'.$LEBAR.'" height="'.$tinggi.'" as="geometry"/></mxCell>';
        foreach ($cols->values() as $i => $c) {
            $tanda = [];
            if ($c->k === 'PRI') {
                $tanda[] = 'PK';
            }
            if (isset($adaFk["{$t}.{$c->c}"])) {
                $tanda[] = 'FK';
            }
            if (isset($unik["{$t}.{$c->c}"])) {
                $tanda[] = 'UK';
            }
            $label = str_pad(implode(',', $tanda), 7).$c->c.' : '.$c->ty.($c->n === 'NO' && $c->k !== 'PRI' ? ' NN' : '').($c->g ? ' (dijana)' : '');
            $gaya = 'text;strokeColor=none;fillColor=none;align=left;verticalAlign=middle;spacingLeft=6;spacingRight=4;overflow=hidden;rotatable=0;points=[[0,0.5],[1,0.5]];portConstraint=eastwest;html=0;fontFamily=Courier New;fontSize=11;'
                .($c->k === 'PRI' ? 'fontStyle=5;' : (in_array('FK', $tanda, true) ? 'fontStyle=2;' : ''));
            $sel[] = '<mxCell id="'.$id('c', $t, $c->c).'" value="'.$x($label).'" style="'.$gaya.'" vertex="1" parent="'.$tid.'"><mxGeometry y="'.($KEPALA + $i * $BARIS).'" width="'.$LEBAR.'" height="'.$BARIS.'" as="geometry"/></mxCell>';
        }
        $y += $tinggi + $JARAK_Y;
    }
    $maksY = max($maksY, $y);
}

// Garis FK: dari lajur FK (anak, sifar/banyak) ke lajur PK induk (tepat satu / sifar-satu ikut nullable)
$nullable = fn ($t, $c) => $lajur[$t]->firstWhere('c', $c)->n === 'YES';
foreach ($fk as $i => $f) {
    $induk = $nullable($f->t, $f->c) ? 'ERzeroToOne' : 'ERmandOne';
    $anak = 'ERzeroToMany';
    // borang.dokumen_id: 1:1 dikuatkuasa aplikasi; pendaftaran_staf.user_id: satu akaun bagi satu pendaftaran
    if (in_array("{$f->t}.{$f->c}", ['borang.dokumen_id', 'pendaftaran_staf.user_id'], true)) {
        $anak = 'ERzeroToOne';
    }
    $sel[] = '<mxCell id="'.$id('fk', (string) $i).'" value="" style="edgeStyle=entityRelationEdgeStyle;html=0;rounded=0;endArrow='.$induk.';startArrow='.$anak.';endFill=0;startFill=0;strokeWidth=1;strokeColor=#555555;" edge="1" parent="1" source="'.$id('c', $f->t, $f->c).'" target="'.$id('c', $f->rt, $f->rc).'"><mxGeometry relative="1" as="geometry"/></mxCell>';
}
foreach ($logik as $i => [$t, $c, $rt, $rc, $label]) {
    $sel[] = '<mxCell id="'.$id('logik', (string) $i).'" value="'.$x($label).'" style="edgeStyle=entityRelationEdgeStyle;html=0;rounded=0;dashed=1;endArrow=ERzeroToOne;startArrow=ERzeroToMany;endFill=0;startFill=0;strokeColor=#999999;fontSize=10;fontColor=#777777;" edge="1" parent="1" source="'.$id('c', $t, $c).'" target="'.$id('c', $rt, $rc).'"><mxGeometry relative="1" as="geometry"/></mxCell>';
}

$lebarHalaman = count($kumpulan) * $JARAK_X;
$xml = '<mxfile host="app.diagrams.net"><diagram name="ERD DMS PKINK" id="erd-dms-pkink"><mxGraphModel dx="1600" dy="900" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="'.$lebarHalaman.'" pageHeight="'.($maksY + 50).'" math="0" shadow="0"><root><mxCell id="0"/><mxCell id="1" parent="0"/>'
    ."\n".implode("\n", $sel)."\n".'</root></mxGraphModel></diagram></mxfile>'."\n";

file_put_contents($argv[2], $xml);
echo 'jadual='.collect($kumpulan)->sum(fn ($k) => count($k[3])).' fk='.count($fk).' logik='.count($logik).' bait='.strlen($xml)."\n";
