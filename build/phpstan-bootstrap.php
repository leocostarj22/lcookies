<?php

/**
 * PHPStan bootstrap: constants and class autoloading of a Joomla installation, given by the
 * environment variable JOOMLA_PATH (e.g. JOOMLA_PATH=/var/www/joomla composer stan).
 */

$root = rtrim((string) getenv('JOOMLA_PATH'), '/');

if ($root === '' || !is_file($root . '/includes/defines.php')) {
    fwrite(STDERR, "Set JOOMLA_PATH to the root of a Joomla 5.2+ or 6.x installation.\n");
    exit(1);
}

\define('_JEXEC', 1);
\define('JPATH_BASE', $root);
\define('JDEBUG', false);
\define('JVERSION', '6.0.0');

require_once $root . '/includes/defines.php';
require_once $root . '/libraries/vendor/autoload.php';
