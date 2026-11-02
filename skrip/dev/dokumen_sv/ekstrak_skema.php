<?php

// Baca skema (BACA SAHAJA) daripada INFORMATION_SCHEMA dan tulis skema.json.
$db = $argv[1] ?? 'dms_pkink_pemasangan';
$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$jadual = [];
foreach ($pdo->query("SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME") as $r) {
    $jadual[$r['TABLE_NAME']] = ['lajur' => [], 'pk' => [], 'fk' => []];
}
$q = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_KEY, IS_NULLABLE, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME, ORDINAL_POSITION');
$q->execute([$db]);
foreach ($q as $r) {
    $jadual[$r['TABLE_NAME']]['lajur'][] = ['nama' => $r['COLUMN_NAME'], 'null' => $r['IS_NULLABLE'] === 'YES', 'janaan' => str_contains($r['EXTRA'], 'GENERATED')];
}
$q = $pdo->prepare("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND CONSTRAINT_NAME='PRIMARY' ORDER BY ORDINAL_POSITION");
$q->execute([$db]);
foreach ($q as $r) {
    $jadual[$r['TABLE_NAME']]['pk'][] = $r['COLUMN_NAME'];
}
$q = $pdo->prepare('SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE
    FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
      ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME
    WHERE k.TABLE_SCHEMA=? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME, k.ORDINAL_POSITION');
$q->execute([$db]);
$bilFk = 0;
foreach ($q as $r) {
    $jadual[$r['TABLE_NAME']]['fk'][] = ['lajur' => $r['COLUMN_NAME'], 'rujuk' => $r['REFERENCED_TABLE_NAME'], 'lajur_rujuk' => $r['REFERENCED_COLUMN_NAME'], 'padam' => $r['DELETE_RULE']];
    $bilFk++;
}
file_put_contents(__DIR__.'/skema.json', json_encode($jadual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
printf("%d jadual, %d lajur, %d FK\n", count($jadual), array_sum(array_map(fn ($t) => count($t['lajur']), $jadual)), $bilFk);
foreach ($jadual as $n => $t) {
    printf("%-22s lajur=%2d pk=%s fk=%d\n", $n, count($t['lajur']), implode(',', $t['pk']), count($t['fk']));
}
