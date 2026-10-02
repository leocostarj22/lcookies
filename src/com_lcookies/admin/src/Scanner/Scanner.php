<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Scanner;

use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Cookie scanner: pages to scan, the server pass, the scan token and the classification of what
 * was found against the declared cookies, the services and the preset library.
 *
 * - Server pass: each page is requested without cookies; every Set-Cookie of the response is a
 *   cookie set before any consent.
 * - Browser pass (media/com_lcookies/js/scanner.js): each page is loaded in a same-origin iframe
 *   with ?lcookies_scan=<mode>.<token>. plg_system_lcookies then ignores the visitor's own choice:
 *   "none" = no consent (requests to other sites are recorded), "all" = every category accepted
 *   (cookies and storage are recorded). Nothing is saved or logged in scan mode.
 */
final class Scanner
{
    /**
     * Name of the query parameter of the browser pass.
     */
    public const PARAM = 'lcookies_scan';

    /**
     * Validity of a scan token, in seconds.
     */
    public const TOKEN_TTL = 3600;

    /**
     * Most pages a scan may have.
     */
    public const MAX_PAGES = 100;

    /**
     * @param   DatabaseInterface  $db      Database connection.
     * @param   Registry           $params  Options of com_lcookies.
     */
    public function __construct(private DatabaseInterface $db, private Registry $params)
    {
    }

    /**
     * Token that turns on the scan mode of plg_system_lcookies for one scan.
     *
     * @param   integer  $scan     Scan id.
     * @param   string   $secret   Site secret.
     * @param   ?int     $expires  Unix time (default: now + TOKEN_TTL).
     *
     * @return  string  "<scan>.<expires>.<hmac>"
     */
    public static function token(int $scan, string $secret, ?int $expires = null): string
    {
        $expires ??= time() + self::TOKEN_TTL;

        return $scan . '.' . $expires . '.' . hash_hmac('sha256', 'lcookies-scan|' . $scan . '|' . $expires, $secret);
    }

    /**
     * Mode of a valid scan parameter.
     *
     * @param   string  $value   Value of the query parameter, "<mode>.<token>".
     * @param   string  $secret  Site secret.
     *
     * @return  ?string  "none" or "all", null when the value is not a valid, unexpired token.
     */
    public static function mode(string $value, string $secret): ?string
    {
        if ($secret === '' || !preg_match('/^(none|all)\.(\d{1,10})\.(\d{1,12})\.([0-9a-f]{64})$/', $value, $m)) {
            return null;
        }

        $expires = (int) $m[3];

        if ($expires < time() || $expires > time() + self::TOKEN_TTL) {
            return null;
        }

        return hash_equals(self::token((int) $m[2], $secret, $expires), $m[2] . '.' . $m[3] . '.' . $m[4]) ? $m[1] : null;
    }

    /**
     * Pages to scan: the home page, the privacy page, the public menu items of the site and the
     * extra addresses of the options, without duplicates.
     *
     * @return  string[]  Absolute URLs.
     */
    public function pages(): array
    {
        $root  = Uri::root();
        $limit = min(self::MAX_PAGES, max(1, (int) $this->params->get('scan_max_pages', 20)));
        $pages = [$root];

        $privacy = (int) $this->params->get('privacy_menuitem', 0);
        $items   = $privacy > 0 ? [$privacy] : [];

        $published = 1;
        $access    = 1;
        $client    = 0;
        $type      = 'component';
        $query     = $this->db->createQuery()
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__menu'))
            ->where([
                $this->db->quoteName('published') . ' = :published',
                $this->db->quoteName('access') . ' = :access',
                $this->db->quoteName('client_id') . ' = :client',
                $this->db->quoteName('type') . ' = :type',
                $this->db->quoteName('home') . ' = 0',
            ])
            ->order([$this->db->quoteName('lft') . ' ASC'])
            ->bind(':published', $published, ParameterType::INTEGER)
            ->bind(':access', $access, ParameterType::INTEGER)
            ->bind(':client', $client, ParameterType::INTEGER)
            ->bind(':type', $type)
            ->setLimit($limit);

        $items = array_merge($items, array_map('intval', $this->db->setQuery($query)->loadColumn()));

        foreach ($items as $id) {
            $pages[] = Route::link('site', 'index.php?Itemid=' . $id, false, Route::TLS_IGNORE, true);
        }

        foreach (preg_split('/\R/', (string) $this->params->get('scan_urls', '')) as $line) {
            $url = $this->sameSite(trim($line), $root);

            if ($url !== null) {
                $pages[] = $url;
            }
        }

        return \array_slice(array_values(array_unique($pages)), 0, $limit);
    }

    /**
     * Server pass of one page: the cookies its response sets for a visitor without cookies.
     *
     * @param   string  $url  Absolute URL of a page of the site.
     *
     * @return  array  ['status' => HTTP status (0 on failure), 'cookies' => [['name', 'duration' (seconds, 0 = session)], ...]]
     */
    public function fetch(string $url): array
    {
        if ($this->sameSite($url, Uri::root()) === null) {
            return ['status' => 0, 'cookies' => []];
        }

        try {
            // Redirects are not followed: a page of the site must not make the server request another address.
            $response = HttpFactory::getHttp(['follow_location' => false])->get($url, ['User-Agent' => 'LCookies-Scanner/1.0'], 15);
        } catch (\Throwable) {
            return ['status' => 0, 'cookies' => []];
        }

        $cookies = [];

        foreach ($response->getHeader('Set-Cookie') as $header) {
            $cookie = $this->parseSetCookie((string) $header);

            if ($cookie !== null) {
                $cookies[$cookie['name']] = $cookie;
            }
        }

        return ['status' => (int) $response->getStatusCode(), 'cookies' => array_values($cookies)];
    }

    /**
     * Matches what was found against the declared cookies and services and the preset library.
     *
     * @param   array  $items     [['name', 'type' (cookie/local/session), ...], ...]
     * @param   array  $requests  [['host', ...], ...] requests to other sites made before consent.
     *
     * @return  array  [items, requests], each entry with `declared` (or null), `preset` (or null) and `issue`.
     */
    public function classify(array $items, array $requests): array
    {
        $declared = $this->declaredCookies();
        $services = $this->declaredServices();
        $presets  = $this->presets();

        foreach ($items as &$item) {
            $item['declared'] = null;
            $item['preset']   = null;

            foreach ($declared as $cookie) {
                if ($cookie['type'] === $item['type'] && self::matches($cookie['match'], $cookie['name'], $item['name'])) {
                    $item['declared'] = $cookie;
                    break;
                }
            }

            if ($item['declared'] === null) {
                foreach ($presets as $preset) {
                    foreach ($preset['cookies'] as $cookie) {
                        if (($cookie['type'] ?? 'cookie') === $item['type'] && self::matches($cookie['match_type'] ?? 'exact', (string) $cookie['name'], $item['name'])) {
                            $item['preset'] = ['alias' => $preset['alias'], 'title' => $preset['title'], 'category' => $preset['category']];
                            break 2;
                        }
                    }
                }
            }

            // Set before consent and not strictly necessary (or of unknown purpose).
            $item['issue'] = !empty($item['before']) && !($item['declared']['required'] ?? false);
        }

        unset($item);

        foreach ($requests as &$request) {
            $url                 = 'https://' . $request['host'] . '/';
            $request['declared'] = null;
            $request['preset']   = null;

            foreach ($services as $service) {
                if (self::matchesPatterns($service['patterns'], $url)) {
                    $request['declared'] = $service;
                    break;
                }
            }

            if ($request['declared'] === null) {
                foreach ($presets as $preset) {
                    if (self::matchesPatterns($preset['block_patterns'] ?? [], $url)) {
                        $request['preset'] = ['alias' => $preset['alias'], 'title' => $preset['title'], 'category' => $preset['category']];
                        break;
                    }
                }
            }

            $request['issue'] = !($request['declared']['required'] ?? false);
        }

        unset($request);

        return [$items, $requests];
    }

    /**
     * Whether a cookie or storage key name matches a declared name (same rules as the frontend).
     *
     * @param   string  $match    exact, prefix or regex.
     * @param   string  $pattern  Declared name.
     * @param   string  $name     Name found.
     *
     * @return  boolean
     */
    public static function matches(string $match, string $pattern, string $name): bool
    {
        return match ($match) {
            'prefix' => $pattern !== '' && str_starts_with($name, $pattern),
            'regex'  => @preg_match('~' . str_replace('~', '\~', $pattern) . '~', $name) === 1,
            default  => $name === $pattern,
        };
    }

    /**
     * Whether a URL matches blocking patterns ("/.../" = regular expression, otherwise a
     * case-insensitive substring), as the frontend does.
     *
     * @param   string[]  $patterns  Patterns.
     * @param   string    $url       URL.
     *
     * @return  boolean
     */
    public static function matchesPatterns(array $patterns, string $url): bool
    {
        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);

            if (\strlen($pattern) > 2 && $pattern[0] === '/' && str_ends_with($pattern, '/')) {
                if (@preg_match('~' . str_replace('~', '\~', substr($pattern, 1, -1)) . '~i', $url) === 1) {
                    return true;
                }
            } elseif ($pattern !== '') {
                // Patterns usually hold a host and a path; the host alone is enough for a request.
                $host = strtolower(explode('/', $pattern, 2)[0]);

                if ($host !== '' && str_contains(strtolower($url), $host)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Same-site absolute URL for an address of the options (absolute, or relative to the root).
     *
     * @param   string  $url   Address.
     * @param   string  $root  Root URL of the site.
     *
     * @return  ?string  Null when empty or on another site.
     */
    private function sameSite(string $url, string $root): ?string
    {
        if ($url === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = rtrim($root, '/') . '/' . ltrim($url, '/');
        }

        $target = new Uri($url);
        $site   = new Uri($root);

        return strcasecmp($target->getHost(), $site->getHost()) === 0 && $target->getPort() === $site->getPort() ? $url : null;
    }

    /**
     * @param   string  $header  A Set-Cookie header.
     *
     * @return  ?array  ['name', 'duration' (seconds, 0 = session)], null when it deletes the cookie.
     */
    private function parseSetCookie(string $header): ?array
    {
        $parts = array_map('trim', explode(';', $header));
        $name  = trim(explode('=', array_shift($parts), 2)[0]);

        if ($name === '' || \strlen($name) > 255) {
            return null;
        }

        $duration = 0;

        foreach ($parts as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $key           = strtolower(trim($key));

            if ($key === 'max-age') {
                $duration = (int) $value;
                break;
            }

            if ($key === 'expires' && ($time = strtotime($value)) !== false) {
                $duration = $time - time();
            }
        }

        // Max-Age <= 0 or an expiry in the past: the response deletes the cookie.
        return $duration < 0 || preg_match('/;\s*max-age\s*=\s*0\b/i', $header) ? null : ['name' => $name, 'duration' => $duration];
    }

    /**
     * @return  array  Published cookies of published services and categories.
     */
    private function declaredCookies(): array
    {
        $state = 1;
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(
                ['k.id', 'k.name', 'k.match_type', 'k.type', 's.id', 's.title', 'c.alias', 'c.title', 'c.required'],
                ['id', 'name', 'match', 'type', 'service_id', 'service', 'category', 'category_title', 'required']
            ))
            ->from($this->db->quoteName('#__lcookies_cookies', 'k'))
            ->join('INNER', $this->db->quoteName('#__lcookies_services', 's'), $this->db->quoteName('s.id') . ' = ' . $this->db->quoteName('k.service_id'))
            ->join('INNER', $this->db->quoteName('#__lcookies_categories', 'c'), $this->db->quoteName('c.id') . ' = ' . $this->db->quoteName('s.category_id'))
            ->where([
                $this->db->quoteName('k.state') . ' = :kstate',
                $this->db->quoteName('s.state') . ' = :sstate',
                $this->db->quoteName('c.state') . ' = :cstate',
            ])
            ->order([$this->db->quoteName('c.ordering'), $this->db->quoteName('s.ordering'), $this->db->quoteName('k.ordering')])
            ->bind(':kstate', $state, ParameterType::INTEGER)
            ->bind(':sstate', $state, ParameterType::INTEGER)
            ->bind(':cstate', $state, ParameterType::INTEGER);

        return array_map(static fn (array $row): array => [
            'id'             => (int) $row['id'],
            'name'           => (string) $row['name'],
            'match'          => (string) $row['match'],
            'type'           => (string) $row['type'],
            'service_id'     => (int) $row['service_id'],
            'service'        => (string) $row['service'],
            'category'       => (string) $row['category'],
            'category_title' => (string) $row['category_title'],
            'required'       => (bool) $row['required'],
        ], $this->db->setQuery($query)->loadAssocList());
    }

    /**
     * @return  array  Published services with blocking patterns.
     */
    private function declaredServices(): array
    {
        $state = 1;
        $empty = '';
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(
                ['s.id', 's.title', 's.block_patterns', 'c.alias', 'c.title', 'c.required'],
                ['service_id', 'service', 'patterns', 'category', 'category_title', 'required']
            ))
            ->from($this->db->quoteName('#__lcookies_services', 's'))
            ->join('INNER', $this->db->quoteName('#__lcookies_categories', 'c'), $this->db->quoteName('c.id') . ' = ' . $this->db->quoteName('s.category_id'))
            ->where([
                $this->db->quoteName('s.state') . ' = :sstate',
                $this->db->quoteName('c.state') . ' = :cstate',
                $this->db->quoteName('s.block_patterns') . ' <> :empty',
            ])
            ->order([$this->db->quoteName('c.ordering'), $this->db->quoteName('s.ordering')])
            ->bind(':sstate', $state, ParameterType::INTEGER)
            ->bind(':cstate', $state, ParameterType::INTEGER)
            ->bind(':empty', $empty);

        return array_map(static fn (array $row): array => [
            'service_id'     => (int) $row['service_id'],
            'service'        => (string) $row['service'],
            'patterns'       => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $row['patterns'])))),
            'category'       => (string) $row['category'],
            'category_title' => (string) $row['category_title'],
            'required'       => (bool) $row['required'],
        ], $this->db->setQuery($query)->loadAssocList());
    }

    /**
     * @return  array  Services of the preset library.
     */
    private function presets(): array
    {
        $file = JPATH_ADMINISTRATOR . '/components/com_lcookies/presets/presets.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return \is_array($data['services'] ?? null) ? $data['services'] : [];
    }
}
