<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  plg_system_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Plugin\System\Lcookies\Extension;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Application\AfterRenderEvent;
use Joomla\CMS\Event\Application\BeforeCompileHeadEvent;
use Joomla\CMS\Event\PageCache\IsExcludedEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\Priority;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Lcsilva\Component\Lcookies\Administrator\Contract\ContractBuilder;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;
use Lcsilva\Component\Lcookies\Administrator\Scanner\Scanner;
use Lcsilva\Plugin\System\Lcookies\Html\Blocker;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * LCookies frontend: consent banner, preferences, script/iframe blocking and Consent Mode.
 *
 * onBeforeCompileHead publishes the contract (Joomla.getOptions('lcookies')) and the assets;
 * onAfterRender blocks scripts/iframes, adds the head bootstrap, the services' code and the markup.
 * With a valid ?lcookies_scan=<mode>.<token> (the cookie scanner of com_lcookies) the page ignores
 * the visitor's choice ("none": no consent, "all": everything accepted), stores nothing and is
 * not cached.
 */
final class Lcookies extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Contract of the current request, null when the plugin is not active for it.
     *
     * @var  ?array
     */
    private ?array $contract = null;

    /**
     * Code of the services to add to the page: [category alias, service alias, required, head, body].
     *
     * @var  array
     */
    private array $code = [];

    /**
     * Scan mode of the request: null, "none" or "all".
     *
     * @var  ?string
     */
    private ?string $scan = null;

    /**
     * Returns the events this plugin listens to.
     *
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeCompileHead'   => 'onBeforeCompileHead',
            // Last, so that scripts added by other plugins in onAfterRender are blocked too.
            'onAfterRender'         => ['onAfterRender', Priority::MIN],
            'onPageCacheIsExcluded' => 'onPageCacheIsExcluded',
        ];
    }

    /**
     * Publishes the contract and the assets, and expires cookies the visitor did not accept.
     *
     * @param   BeforeCompileHeadEvent  $event  The event.
     *
     * @return  void
     */
    public function onBeforeCompileHead(BeforeCompileHeadEvent $event): void
    {
        $app      = $this->getApplication();
        $document = $event->getDocument();

        if (!$app->isClient('site') || !$document instanceof HtmlDocument || !ComponentHelper::isEnabled('com_lcookies')) {
            return;
        }

        $language = $app->getLanguage();
        LcookiesHelper::loadSiteLanguage($language);

        $params = ComponentHelper::getParams('com_lcookies');
        $data   = $this->load($params, $language->getTag());

        $this->contract = $data['contract'];
        $this->code     = $data['code'];
        $this->scan     = Scanner::mode((string) $app->getInput()->get(Scanner::PARAM, '', 'cmd'), (string) $app->get('secret'));

        $document->addScriptOptions('lcookies', $this->contract);

        $color  = (string) $params->get('color_primary', '#1f5fbf');
        $color  = preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $color) ? $color : '#1f5fbf';
        $radius = min(32, max(0, (int) $params->get('border_radius', 8)));

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('plg_system_lcookies');
        $wa->usePreset('plg_system_lcookies.lcookies')
            ->addInlineStyle(
                '.lcookies,.lcookies-placeholder{--lcookies-primary:' . $color . ';--lcookies-radius:' . $radius . 'px}',
                ['name' => 'plg_system_lcookies.vars'],
                [],
                ['plg_system_lcookies.lcookies']
            );

        if ($this->scan !== null) {
            // Joomla then sends no-cache headers (browsers, proxies and CDNs).
            $app->allowCache(false);

            return;
        }

        $this->expireRejectedCookies();
    }

    /**
     * Keeps the pages of the cookie scanner out of the page cache.
     *
     * @param   IsExcludedEvent  $event  The event.
     *
     * @return  void
     */
    public function onPageCacheIsExcluded(IsExcludedEvent $event): void
    {
        if ($this->scan !== null) {
            $event->addResult(true);
        }
    }

    /**
     * Rewrites the page: blocking, head bootstrap, services' code and the banner markup.
     *
     * @param   AfterRenderEvent  $event  The event.
     *
     * @return  void
     */
    public function onAfterRender(AfterRenderEvent $event): void
    {
        if ($this->contract === null) {
            return;
        }

        $app      = $this->getApplication();
        $body     = (string) $app->getBody();
        $contract = $this->contract;

        if ($body === '' || stripos($body, '<body') === false) {
            return;
        }

        $placeholder = $this->render('placeholder', ['contract' => $contract]);

        if ($contract['autoblock']) {
            $body = (new Blocker($contract, $contract['iframePlaceholder'] ? $placeholder : null))->process($body);
        }

        [$headCode, $bodyCode] = $this->servicesCode();

        $markup = '<div id="lcookies" class="lcookies" data-lcookies-theme="' . $contract['theme'] . '">'
            . $this->render('banner', ['contract' => $contract])
            . $this->render('preferences', ['contract' => $contract])
            . $this->render('floating', ['contract' => $contract])
            . '<template data-lcookies-placeholder>' . $placeholder . '</template>'
            . '</div>';

        $body = $this->insertAfter($body, '#<meta\s+charset\s*=[^>]*>|<head\b[^>]*>#i', $this->headScript());
        $body = $this->insertBefore($body, '</head>', $headCode);
        $body = $this->insertAfter($body, '#<body\b[^>]*>#i', $markup);
        $body = $this->insertBefore($body, '</body>', $bodyCode);

        $app->setBody($body);
    }

    /**
     * Contract and services' code, cached per language and options (the cache group is cleaned
     * by com_lcookies whenever a category, service or cookie is saved).
     *
     * @param   Registry  $params  Options of com_lcookies.
     * @param   string    $tag     Language tag.
     *
     * @return  array{contract: array, code: array}
     */
    private function load(Registry $params, string $tag): array
    {
        $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
            ->createCacheController('output', ['defaultgroup' => 'com_lcookies']);
        // The host is part of the key: the cookie domain depends on it (ContractBuilder::cookieDomain()).
        $key   = 'contract.' . md5($tag . '|' . Uri::root() . '|' . Uri::getInstance()->getHost() . '|' . $params->toString());
        $data  = $cache->get($key);

        if (\is_array($data) && isset($data['contract']['schema']) && $data['contract']['schema'] === ContractBuilder::SCHEMA) {
            return $data;
        }

        $data = [
            'contract' => (new ContractBuilder($this->getDatabase(), $params))->build(),
            'code'     => $this->loadCode(),
        ];

        $cache->store($data, $key);

        return $data;
    }

    /**
     * Head and body code of the published services.
     *
     * @return  array
     */
    private function loadCode(): array
    {
        $db    = $this->getDatabase();
        $state = 1;
        $empty = '';

        $query = $db->createQuery()
            ->select($db->quoteName(['c.alias', 's.alias', 'c.required', 's.head_code', 's.body_code'], ['category', 'service', 'required', 'head', 'body']))
            ->from($db->quoteName('#__lcookies_services', 's'))
            ->join('INNER', $db->quoteName('#__lcookies_categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('s.category_id'))
            ->where([
                $db->quoteName('s.state') . ' = :sstate',
                $db->quoteName('c.state') . ' = :cstate',
                '(' . $db->quoteName('s.head_code') . ' <> :head OR ' . $db->quoteName('s.body_code') . ' <> :body)',
            ])
            ->order([$db->quoteName('c.ordering'), $db->quoteName('s.ordering')])
            ->bind(':sstate', $state, ParameterType::INTEGER)
            ->bind(':cstate', $state, ParameterType::INTEGER)
            ->bind(':head', $empty)
            ->bind(':body', $empty);

        return array_map(
            fn (object $row): array => [$row->category, $row->service, (bool) $row->required, trim((string) $row->head), trim((string) $row->body)],
            $db->setQuery($query)->loadObjectList()
        );
    }

    /**
     * Services' code for the head and the end of the body. Code of optional categories is wrapped
     * in an inert <template> that the JavaScript activates after consent.
     *
     * @return  string[]  [head, body]
     */
    private function servicesCode(): array
    {
        $result = ['', ''];

        foreach ($this->code as [$category, $service, $required, $head, $body]) {
            foreach ([$head, $body] as $i => $code) {
                if ($code === '') {
                    continue;
                }

                $result[$i] .= $required ? $code : '<template data-lcookies-category="' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8')
                    . '" data-lcookies-service="' . htmlspecialchars($service, ENT_QUOTES, 'UTF-8') . '">' . $code . '</template>';
            }
        }

        return $result;
    }

    /**
     * The bootstrap script that must run before any other: stored consent, Consent Mode defaults and
     * the guard for scripts/iframes created later by other scripts.
     *
     * @return  string
     */
    private function headScript(): string
    {
        $contract = $this->contract;
        $required = [];
        $rules    = [];
        $gcmMap   = [];

        foreach ($contract['categories'] as $category) {
            $gcmMap[$category['alias']] = $category['gcm'];

            if ($category['required']) {
                $required[] = $category['alias'];
                continue;
            }

            foreach ($category['services'] as $service) {
                if ($contract['autoblock'] && $service['patterns']) {
                    $rules[] = ['c' => $category['alias'], 's' => $service['alias'], 'p' => $service['patterns']];
                }
            }
        }

        $config = [
            'v'     => $contract['policyVersion'],
            'n'     => $contract['cookie']['name'],
            'd'     => $contract['expiryDays'],
            'req'   => $required,
            'rules' => $rules,
            'gcm'   => $contract['gcm'] ? $contract['gcm'] + ['map' => (object) $gcmMap] : null,
        ];

        if ($this->scan !== null) {
            $config['scan'] = $this->scan;
            $config['all']  = array_keys($gcmMap);
        }

        $file   = JPATH_ROOT . '/media/plg_system_lcookies/js/lcookies-head' . (JDEBUG ? '' : '.min') . '.js';
        $file   = is_file($file) ? $file : JPATH_ROOT . '/media/plg_system_lcookies/js/lcookies-head.js';
        $code   = is_file($file) ? trim((string) file_get_contents($file)) : '';
        $nonce  = (string) $this->getApplication()->get('csp_nonce', '');
        $inline = 'window.lcookiesHead=' . json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ";\n" . $code;

        if ($nonce === '') {
            $this->allowInlineScript($inline);
        }

        return '<script data-lcookies-skip' . ($nonce !== '' ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"' : '') . '>'
            . $inline . '</script>';
    }

    /**
     * Adds the hash of an inline script to the Content-Security-Policy of the response, when there
     * is one without a nonce (e.g. "System - HTTP Headers" with script hashes, which are computed
     * before LCookies adds its script). Policies that allow 'unsafe-inline' are left alone: a hash
     * would turn 'unsafe-inline' off for every other inline script.
     *
     * @param   string  $code  Content of the script element.
     *
     * @return  void
     */
    private function allowInlineScript(string $code): void
    {
        $app  = $this->getApplication();
        $hash = "'sha256-" . base64_encode(hash('sha256', $code, true)) . "'";

        foreach ($app->getHeaders() as $header) {
            $name = strtolower((string) $header['name']);

            if (!\in_array($name, ['content-security-policy', 'content-security-policy-report-only'], true)) {
                continue;
            }

            $directives = array_map('trim', explode(';', (string) $header['value']));
            $target     = null;

            foreach ($directives as $i => $directive) {
                $directiveName = strtolower(strtok($directive, " \t") ?: '');

                if ($directiveName === 'script-src' || ($directiveName === 'default-src' && $target === null)) {
                    $target = $i;
                }
            }

            if ($target === null || stripos($directives[$target], "'unsafe-inline'") !== false) {
                continue;
            }

            $directives[$target] .= ' ' . $hash;
            $app->setHeader((string) $header['name'], implode('; ', array_filter($directives, 'strlen')), true);
        }
    }

    /**
     * Expires, on this response, cookies of categories the visitor did not accept. The JavaScript
     * does the same in the browser (and for local/session storage).
     *
     * @return  void
     */
    private function expireRejectedCookies(): void
    {
        $input    = $this->getApplication()->getInput()->cookie;
        $contract = $this->contract;
        $consent  = json_decode((string) $input->get($contract['cookie']['name'], '', 'raw'), true);

        if (!\is_array($consent) || ($consent['v'] ?? null) !== $contract['policyVersion'] || !\is_array($consent['cats'] ?? null)) {
            return;
        }

        $present = array_keys($input->getArray());
        $domains = $this->cookieDomains();

        foreach ($contract['categories'] as $category) {
            if ($category['required'] || \in_array($category['alias'], $consent['cats'], true)) {
                continue;
            }

            foreach ($category['services'] as $service) {
                foreach ($service['cookies'] as $cookie) {
                    if ($cookie['type'] !== 'cookie') {
                        continue;
                    }

                    foreach ($present as $name) {
                        if ($name === $contract['cookie']['name'] || !$this->cookieMatches($cookie, (string) $name)) {
                            continue;
                        }

                        foreach (array_unique(array_merge($domains, [$cookie['domain']])) as $domain) {
                            $input->set((string) $name, '', ['expires' => 1, 'path' => '/', 'domain' => $domain]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Whether a cookie name matches a declared cookie.
     *
     * @param   array   $cookie  Declared cookie (contract format).
     * @param   string  $name    Name of a cookie sent by the browser.
     *
     * @return  boolean
     */
    private function cookieMatches(array $cookie, string $name): bool
    {
        return match ($cookie['match']) {
            'prefix' => str_starts_with($name, $cookie['name']),
            'regex'  => @preg_match('~' . str_replace('~', '\~', $cookie['name']) . '~', $name) === 1,
            default  => $name === $cookie['name'],
        };
    }

    /**
     * Domains a cookie may have been set on: host only, the host and its parent domains, and the
     * domain configured in the options.
     *
     * @return  string[]
     */
    private function cookieDomains(): array
    {
        $host    = Uri::getInstance()->getHost();
        $domains = ['', (string) $this->contract['cookie']['domain']];

        if ($host !== '' && !filter_var($host, FILTER_VALIDATE_IP)) {
            $parts = explode('.', $host);

            for ($i = 0; $i < \count($parts) - 1; $i++) {
                $domains[] = '.' . implode('.', \array_slice($parts, $i));
            }
        }

        return array_values(array_unique($domains));
    }

    /**
     * Renders a layout; template overrides go in templates/<template>/html/layouts/lcookies/.
     *
     * @param   string  $name  Layout name.
     * @param   array   $data  Display data.
     *
     * @return  string
     */
    private function render(string $name, array $data): string
    {
        $layout = new FileLayout('lcookies.' . $name);
        $layout->setIncludePaths(array_merge($layout->getIncludePaths(), [JPATH_PLUGINS . '/system/lcookies/layouts']));

        return $layout->render($data);
    }

    /**
     * Inserts HTML right after the first match of a pattern (or nowhere if there is none).
     *
     * @param   string  $html     The page.
     * @param   string  $pattern  Regular expression.
     * @param   string  $insert   HTML to insert.
     *
     * @return  string
     */
    private function insertAfter(string $html, string $pattern, string $insert): string
    {
        if ($insert === '' || !preg_match($pattern, $html, $m, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        $offset = $m[0][1] + \strlen($m[0][0]);

        return substr($html, 0, $offset) . $insert . substr($html, $offset);
    }

    /**
     * Inserts HTML right before the last occurrence of a closing tag.
     *
     * @param   string  $html    The page.
     * @param   string  $tag     Closing tag, e.g. </body>.
     * @param   string  $insert  HTML to insert.
     *
     * @return  string
     */
    private function insertBefore(string $html, string $tag, string $insert): string
    {
        $offset = $insert === '' ? false : strripos($html, $tag);

        return $offset === false ? $html : substr($html, 0, $offset) . $insert . substr($html, $offset);
    }
}
