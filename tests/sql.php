<?php

/**
 * Test helper: runs SQL from stdin on the database of a Joomla site (MySQL/MariaDB or PostgreSQL,
 * credentials from its configuration.php) and prints rows like `mysql -N` (tab separated).
 *
 * Usage: php tests/sql.php <joomla root> < query.sql
 *        php tests/sql.php <joomla root> --type      prints mysql or pgsql
 */

[, $root, $option] = $argv + [null, null, null];

if (!$root || !is_file($root . '/configuration.php')) {
    fwrite(STDERR, "Usage: php tests/sql.php <joomla root> [--type] < query.sql\n");
    exit(1);
}

\define('_JEXEC', 1);
require $root . '/configuration.php';

$config        = new JConfig();
$pgsql         = $config->dbtype === 'pgsql';
[$host, $port] = explode(':', $config->host) + [1 => null];

if ($option === '--type') {
    echo $pgsql ? 'pgsql' : 'mysql', "\n";
    exit;
}

$dsn = ($pgsql ? 'pgsql' : 'mysql') . ':host=' . $host . ($port ? ';port=' . $port : '') . ';dbname=' . $config->db . ($pgsql ? '' : ';charset=utf8mb4');
$db  = new PDO($dsn, $config->user, $config->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sql = stream_get_contents(STDIN);

if (preg_match('/^(\s*--[^\n]*\n)*\s*(DROP|CREATE|INSERT|UPDATE|DELETE)/i', $sql)) {
    $db->exec($sql);
    exit;
}

foreach ($db->query($sql, PDO::FETCH_NUM) as $row) {
    echo implode("\t", array_map(fn ($v) => $v === null ? 'NULL' : str_replace(['\\', "\n", "\t"], ['\\\\', '\\n', '\\t'], (string) $v), $row)), "\n";
}
