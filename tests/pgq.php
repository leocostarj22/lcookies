<?php

/**
 * Test helper: runs SQL from stdin on a PostgreSQL database and prints rows like `mysql -N`.
 * Usage: php tests/pgq.php <database>
 */
$p = new PDO("pgsql:host=127.0.0.1;port=5433;dbname=" . $argv[1], "postgres", "");
$p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$q = stream_get_contents(STDIN);
if (preg_match('/^(\s*--[^\n]*\n)*\s*(DROP|CREATE|INSERT|UPDATE|DELETE)/i', $q)) {
    $p->exec($q);
    exit;
}
foreach ($p->query($q, PDO::FETCH_NUM) as $row) {
    echo implode("\t", array_map(fn ($v) => $v === null ? 'NULL' : str_replace(["\\", "\n", "\t"], ["\\\\", "\\n", "\\t"], (string) $v), $row)), "\n";
}
