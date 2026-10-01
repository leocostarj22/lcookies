<?php

/**
 * Test helper for tests/e2e_front.mjs: prepares a Joomla test site (MySQL/MariaDB or PostgreSQL).
 *
 * Usage:
 *   php tests/fixture.php <joomla root> setup             test services, cookies, a mod_custom with
 *                                                        scripts/iframes, the files in media/lctest and the
 *                                                        plugin plg_system_lctest (ConsentHelper answers at
 *                                                        the end of each page, onLCookiesConsentChange log)
 *   php tests/fixture.php <joomla root> events           prints the onLCookiesConsentChange events as JSON
 *   php tests/fixture.php <joomla root> params k=v ...   sets options of com_lcookies (JSON values)
 *   php tests/fixture.php <joomla root> consents         prints the consent records as JSON
 *   php tests/fixture.php <joomla root> token <username> [group id]
 *                                                        prints an API token for the user (Joomla token
 *                                                        plugin format); with a group id the user is created
 *   php tests/fixture.php <joomla root> asset <name> [json]  prints the rules of an asset (e.g. root.1,
 *                                                        com_lcookies), then replaces them with json if given
 *   php tests/fixture.php <joomla root> plugin <folder> <element> [json]  prints the params of a plugin,
 *                                                        then replaces them with json if given
 *   php tests/fixture.php <joomla root> deluser <username>  deletes a user created by `token`
 *   php tests/fixture.php <joomla root> teardown         removes everything setup added (and all consent records)
 *
 * Only for test sites: it writes directly to the database.
 */

[$script, $root, $command] = $argv + [null, null, null];
$args = array_slice($argv, 3);

if (!$root || !is_file($root . '/configuration.php') || !in_array($command, ['setup', 'params', 'consents', 'events', 'token', 'asset', 'plugin', 'deluser', 'teardown'], true)) {
    fwrite(STDERR, "Usage: php tests/fixture.php <joomla root> setup|params|consents|events|token|asset|plugin|deluser|teardown [key=value ...]\n");
    exit(1);
}

define('_JEXEC', 1);
require $root . '/configuration.php';

$config = new JConfig();
[$host, $port] = explode(':', $config->host) + [1 => null];
$pgsql  = $config->dbtype === 'pgsql';
$dsn    = ($pgsql ? 'pgsql' : 'mysql') . ':host=' . $host . ($port ? ';port=' . $port : '') . ';dbname=' . $config->db
    . ($pgsql ? '' : ';charset=utf8mb4');
$db     = new PDO($dsn, $config->user, $config->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$p      = $config->dbprefix;
$now    = gmdate('Y-m-d H:i:s');

// Ids far from the ones the administrator creates.
const SVC_STATS = 9001;
const SVC_VIDEO = 9002;
const MODULE    = 9001;
const PLUGIN    = 9001;
const EVENTS    = '/tmp/lctest-events.log';

function run(PDO $db, string $sql, array $values = []): void
{
    $db->prepare($sql)->execute($values);
}

function teardown(PDO $db, string $p, string $root): void
{
    run($db, "DELETE FROM {$p}lcookies_cookies WHERE service_id IN (" . SVC_STATS . ',' . SVC_VIDEO . ')');
    run($db, "DELETE FROM {$p}lcookies_services WHERE id IN (" . SVC_STATS . ',' . SVC_VIDEO . ')');
    run($db, "DELETE FROM {$p}modules_menu WHERE moduleid = " . MODULE);
    run($db, "DELETE FROM {$p}modules WHERE id = " . MODULE);
    run($db, "DELETE FROM {$p}lcookies_consents");

    foreach (glob($root . '/media/lctest/*') ?: [] as $file) {
        unlink($file);
    }

    @rmdir($root . '/media/lctest');

    run($db, "DELETE FROM {$p}extensions WHERE extension_id = " . PLUGIN);
    @unlink($root . '/plugins/system/lctest/services/provider.php');
    @rmdir($root . '/plugins/system/lctest/services');
    @rmdir($root . '/plugins/system/lctest');
    @unlink($root . EVENTS);
}

if ($command === 'teardown') {
    teardown($db, $p, $root);
    echo "teardown ok\n";
    exit;
}

if ($command === 'events') {
    $lines = is_file($root . EVENTS) ? file($root . EVENTS, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    echo '[' . implode(',', $lines) . "]\n";
    exit;
}

if ($command === 'consents') {
    echo json_encode($db->query("SELECT * FROM {$p}lcookies_consents ORDER BY id")->fetchAll(PDO::FETCH_ASSOC)), "\n";
    exit;
}

if ($command === 'token') {
    [$username, $group] = $args + [null, null];
    $find = $db->prepare("SELECT id FROM {$p}users WHERE username = ?");
    $find->execute([$username]);
    $userId = (int) $find->fetchColumn();

    if (!$userId && $group) {
        $q = $pgsql ? '"' : '`';
        $columns = implode(', ', array_map(fn ($c) => $q . $c . $q, ['name', 'username', 'email', 'password', 'block', 'sendEmail', 'registerDate', 'params', 'requireReset']));
        run($db, "INSERT INTO {$p}users ($columns) VALUES (?, ?, ?, ?, 0, 0, ?, '{}', 0)",
            [$username, $username, $username . '@example.com', password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), $now]);
        $find->execute([$username]);
        $userId = (int) $find->fetchColumn();
        run($db, "INSERT INTO {$p}user_usergroup_map (user_id, group_id) VALUES (?, ?)", [$userId, (int) $group]);
    }

    if (!$userId) {
        fwrite(STDERR, "Unknown user $username\n");
        exit(1);
    }

    $seed = base64_encode(random_bytes(32));
    run($db, "DELETE FROM {$p}user_profiles WHERE user_id = ? AND profile_key LIKE 'joomlatoken.%'", [$userId]);
    run($db, "INSERT INTO {$p}user_profiles (user_id, profile_key, profile_value, ordering) VALUES (?, 'joomlatoken.token', ?, 1), (?, 'joomlatoken.enabled', '1', 2)",
        [$userId, $seed, $userId]);
    echo base64_encode('sha256:' . $userId . ':' . hash_hmac('sha256', base64_decode($seed), $config->secret)), "\n";
    exit;
}

if ($command === 'deluser') {
    $find = $db->prepare("SELECT id FROM {$p}users WHERE username = ?");
    $find->execute([$args[0] ?? '']);

    if ($userId = (int) $find->fetchColumn()) {
        foreach (['user_profiles', 'user_usergroup_map'] as $table) {
            run($db, "DELETE FROM {$p}$table WHERE user_id = ?", [$userId]);
        }

        run($db, "DELETE FROM {$p}users WHERE id = ?", [$userId]);
    }

    echo "deluser ok\n";
    exit;
}

if ($command === 'plugin') {
    [$folder, $element, $params] = $args + [null, null, null];
    $find = $db->prepare("SELECT params FROM {$p}extensions WHERE type = 'plugin' AND folder = ? AND element = ?");
    $find->execute([$folder, $element]);
    echo $find->fetchColumn(), "\n";

    if ($params !== null) {
        run($db, "UPDATE {$p}extensions SET params = ? WHERE type = 'plugin' AND folder = ? AND element = ?", [$params, $folder, $element]);
    }

    exit;
}

if ($command === 'asset') {
    [$name, $rules] = $args + [null, null];
    $find = $db->prepare("SELECT rules FROM {$p}assets WHERE name = ?");
    $find->execute([$name]);
    echo $find->fetchColumn(), "\n";

    if ($rules !== null) {
        run($db, "UPDATE {$p}assets SET rules = ? WHERE name = ?", [$rules, $name]);
    }

    exit;
}

if ($command === 'params') {
    $current = $db->query("SELECT params FROM {$p}extensions WHERE element = 'com_lcookies' AND type = 'component'")->fetchColumn();
    $params  = json_decode((string) $current, true) ?: [];

    foreach ($args as $arg) {
        [$key, $value] = explode('=', $arg, 2);
        $params[$key]  = json_decode($value, true) ?? $value;
    }

    run($db, "UPDATE {$p}extensions SET params = ? WHERE element = 'com_lcookies' AND type = 'component'", [json_encode($params)]);
    echo json_encode($params), "\n";
    exit;
}

teardown($db, $p, $root);

// Earlier tests (e2e_admin.py) may have edited the default category.
run($db, "UPDATE {$p}lcookies_categories SET title = ?, description = ?, gcm_types = ?, state = 1 WHERE alias = 'statistics'",
    ['COM_LCOOKIES_CAT_STATISTICS', 'COM_LCOOKIES_CAT_STATISTICS_DESC', '["analytics_storage"]']);

$service = "INSERT INTO {$p}lcookies_services (id, category_id, alias, title, provider, privacy_url, description, block_patterns, head_code, body_code, state, ordering, created, modified)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)";

run($db, $service, [SVC_STATS, 3, 'lctest-stats', 'Test Analytics', 'Test Inc.', 'https://example.com/privacy', 'Analytics used by the tests.',
    "analytics-test\n/lcTestInline/", '<script>window.lcHeadCode = (window.lcHeadCode || 0) + 1;</script>', '<script>window.lcBodyCode = true;</script>', 90, $now, $now]);
run($db, $service, [SVC_VIDEO, 4, 'lctest-video', 'Test Video', '', '', 'Video embeds used by the tests.',
    'frame-marketing', '', '', 91, $now, $now]);

$cookie = "INSERT INTO {$p}lcookies_cookies (service_id, name, match_type, type, domain, duration_value, duration_unit, description, state, ordering, created, modified)
    VALUES (?, ?, ?, ?, '', ?, ?, ?, 1, ?, ?, ?)";

run($db, $cookie, [SVC_STATS, '_lc_test_', 'prefix', 'cookie', 2, 'year', 'Distinguishes users.', 1, $now, $now]);
run($db, $cookie, [SVC_STATS, 'lc_test_ls', 'exact', 'local', 0, 'session', 'Local storage of the test.', 2, $now, $now]);

$content = <<<'HTML'
<div id="lctest">
<script src="/media/lctest/analytics-test.js"></script>
<script>window.lcTestInline = (window.lcTestInline || 0) + 1;</script>
<script>window.lcFree = true;</script>
<script type="module" src="/media/lctest/analytics-test-module.js"></script>
<script>
  (function () {
    var s = document.createElement('script');
    s.src = '/media/lctest/analytics' + '-test-dyn.js';
    s.id = 'lctest-dyn';
    document.head.appendChild(s);
  }());
</script>
<p><iframe id="lctest-frame" src="/media/lctest/frame-marketing.html" width="400" height="200" title="Test frame"></iframe></p>
<a href="#lcookies-settings" id="lctest-link">Cookie settings</a>
</div>
HTML;

run($db, "INSERT INTO {$p}modules (id, asset_id, title, note, content, ordering, position, publish_up, publish_down, published, module, access, showtitle, params, client_id, language)
    VALUES (?, 0, 'LCookies test', '', ?, 1, 'sidebar-right', NULL, NULL, 1, 'mod_custom', 1, 0, ?, 0, '*')", [MODULE, $content, '{"prepare_content":"0","layout":"_:default","moduleclass_sfx":"","cache":0}']);
run($db, "INSERT INTO {$p}modules_menu (moduleid, menuid) VALUES (?, 0)", [MODULE]);

@mkdir($root . '/media/lctest');
file_put_contents($root . '/media/lctest/analytics-test.js', "window.lcTestExternal = (window.lcTestExternal || 0) + 1;\n"
    . "document.cookie = '_lc_test_a=1; path=/; max-age=3600';\nlocalStorage.setItem('lc_test_ls', '1');\n");
file_put_contents($root . '/media/lctest/analytics-test-module.js', "window.lcTestModule = true;\n");
file_put_contents($root . '/media/lctest/analytics-test-dyn.js', "window.lcTestDyn = true;\n");
file_put_contents($root . '/media/lctest/frame-marketing.html', "<!doctype html><title>frame</title><p>Marketing frame</p>\n");

// plg_system_lctest: shows what ConsentHelper answers and logs onLCookiesConsentChange.
$provider = <<<'PHP'
<?php
\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\SubscriberInterface;
use Lcsilva\Component\Lcookies\Administrator\Event\ConsentChangeEvent;
use Lcsilva\Component\Lcookies\Administrator\Helper\ConsentHelper;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, function (Container $container) {
            $config = (array) PluginHelper::getPlugin('system', 'lctest');
            $args   = version_compare(JVERSION, '5.3.0', '<') ? [$container->get(DispatcherInterface::class), $config] : [$config];
            $plugin = new class (...$args) extends CMSPlugin implements SubscriberInterface {
                public static function getSubscribedEvents(): array
                {
                    return ['onAfterRender' => 'afterRender', 'onLCookiesConsentChange' => 'consentChange'];
                }

                public function afterRender(): void
                {
                    $app = $this->getApplication();

                    if (!$app->isClient('site') || $app->getDocument()?->getType() !== 'html') {
                        return;
                    }

                    $has = [];

                    foreach (['necessary', 'statistics', 'marketing', 'nope'] as $category) {
                        $has[$category] = ConsentHelper::has($category);
                    }

                    $info = json_encode(['has' => $has, 'granted' => ConsentHelper::granted(), 'consent' => ConsentHelper::get()]);
                    $app->setBody(str_replace('</body>', '<script type="application/json" id="lctest-helper">' . $info . '</script></body>', $app->getBody()));
                }

                public function consentChange(ConsentChangeEvent $event): void
                {
                    file_put_contents(JPATH_ROOT . '/tmp/lctest-events.log', json_encode([
                        'id' => $event->getConsentId(), 'action' => $event->getAction(), 'categories' => $event->getCategories(),
                        'previous' => $event->getPrevious(), 'granted' => $event->getGranted(), 'revoked' => $event->getRevoked(),
                        'version' => $event->getPolicyVersion(), 'user' => $event->getUserId(),
                    ]) . "\n", FILE_APPEND);
                }
            };
            $plugin->setApplication(Factory::getApplication());

            return $plugin;
        });
    }
};
PHP;
@mkdir($root . '/plugins/system/lctest/services', 0777, true);
file_put_contents($root . '/plugins/system/lctest/services/provider.php', $provider);
run($db, "INSERT INTO {$p}extensions (extension_id, package_id, name, type, element, folder, client_id, enabled, access, protected, locked, manifest_cache, params, custom_data, ordering, state)
    VALUES (?, 0, 'plg_system_lctest', 'plugin', 'lctest', 'system', 0, 1, 1, 0, 0, '{}', '{}', '', 99, 0)", [PLUGIN]);
@unlink($root . EVENTS);

echo "setup ok\n";
