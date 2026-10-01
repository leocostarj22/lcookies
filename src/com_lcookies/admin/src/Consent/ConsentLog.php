<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Consent;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Consent log (proof of consent): validation, rate limit, recording and retention.
 *
 * The payload is the one sent by the site JavaScript: {consent: {id, v, cats, ts}, action, url}.
 */
final class ConsentLog
{
    /**
     * Actions sent by the site JavaScript.
     */
    public const ACTIONS = ['accept_all', 'reject_all', 'custom', 'allow'];

    /**
     * Largest request body accepted, in bytes.
     */
    public const MAX_BODY = 4096;

    /**
     * Records accepted per truncated IP address in RATE_WINDOW seconds. A /24 network can be a
     * whole company behind one address, so the limit only stops floods.
     */
    public const RATE_LIMIT = 30;

    /**
     * Rate limit window, in seconds.
     */
    public const RATE_WINDOW = 60;

    /**
     * One request in PURGE_CHANCE removes the records older than the retention period.
     */
    public const PURGE_CHANCE = 100;

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /**
     * @param   DatabaseInterface  $db          Database connection.
     * @param   Registry           $params      Options of com_lcookies.
     * @param   ?Anonymizer        $anonymizer  Hashing of the visitor data, only needed by record().
     */
    public function __construct(private DatabaseInterface $db, private Registry $params, private ?Anonymizer $anonymizer = null)
    {
    }

    /**
     * Whether consents are being recorded.
     *
     * @return  boolean
     */
    public function isEnabled(): bool
    {
        return (bool) $this->params->get('log_consents', 1);
    }

    /**
     * Validates and records a consent.
     *
     * @param   mixed    $payload    The decoded request body.
     * @param   array    $visitor    `ip`, `userAgent`, `userId` (0 for guests) and `language`.
     * @param   ?string  $siteRoot   Absolute URL of the site, the page URL must be under it (default Uri::root()).
     *
     * @return  array  The stored record: `id`, `consent_uuid`, `categories`, `policy_version`, `created`.
     *
     * @throws  ConsentException  With the HTTP status as code: 404 disabled, 422 invalid, 429 rate limit.
     */
    public function record(mixed $payload, array $visitor, ?string $siteRoot = null): array
    {
        if (!$this->isEnabled()) {
            throw new ConsentException('Consent logging is disabled.', 404);
        }

        if (!$this->anonymizer) {
            throw new \LogicException('ConsentLog::record() needs an Anonymizer.');
        }

        $row = $this->validate($payload, $siteRoot ?? Uri::root());

        $row->ip_hash  = $this->anonymizer->ipHash((string) ($visitor['ip'] ?? ''));
        $row->ua_hash  = $this->anonymizer->userAgentHash(substr((string) ($visitor['userAgent'] ?? ''), 0, 1024));
        $row->user_id  = (int) ($visitor['userId'] ?? 0) ?: null;
        $row->language = substr((string) ($visitor['language'] ?? ''), 0, 7);
        $row->created  = Factory::getDate()->toSql();

        if ($this->recentCount($row->ip_hash) >= self::RATE_LIMIT) {
            throw new ConsentException('Too many requests.', 429);
        }

        $this->db->insertObject('#__lcookies_consents', $row, 'id');

        if (random_int(1, self::PURGE_CHANCE) === 1) {
            $this->purge();
        }

        return [
            'id'             => (int) $row->id,
            'consent_uuid'   => $row->consent_uuid,
            'categories'     => json_decode($row->categories, true),
            'policy_version' => $row->policy_version,
            'created'        => $row->created,
        ];
    }

    /**
     * Deletes the records older than the retention period.
     *
     * @return  integer  Number of records deleted.
     */
    public function purge(): int
    {
        $months = min(120, max(1, (int) $this->params->get('log_retention_months', 24)));
        $limit  = Factory::getDate('-' . $months . ' months')->toSql();
        $query  = $this->db->createQuery()
            ->delete($this->db->quoteName('#__lcookies_consents'))
            ->where($this->db->quoteName('created') . ' < :limit')
            ->bind(':limit', $limit);

        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows();
    }

    /**
     * Strict validation of the payload. Unknown or missing values are refused, never fixed.
     *
     * @param   mixed   $payload   The decoded request body.
     * @param   string  $siteRoot  Absolute URL of the site.
     *
     * @return  \stdClass  Row with the consent fields.
     *
     * @throws  ConsentException
     */
    private function validate(mixed $payload, string $siteRoot): \stdClass
    {
        if (!\is_array($payload) || !\is_array($payload['consent'] ?? null)) {
            throw new ConsentException('Invalid payload.', 422);
        }

        $consent = $payload['consent'];
        $id      = $consent['id'] ?? null;
        $version = $consent['v'] ?? null;
        $cats    = $consent['cats'] ?? null;
        $action  = $payload['action'] ?? null;

        if (!\is_string($id) || !preg_match(self::UUID, $id)) {
            throw new ConsentException('Invalid consent id.', 422);
        }

        if (!\is_int($version) || $version !== max(1, (int) $this->params->get('policy_version', 1))) {
            throw new ConsentException('Invalid policy version.', 422);
        }

        if (!\is_string($action) || !\in_array($action, self::ACTIONS, true)) {
            throw new ConsentException('Invalid action.', 422);
        }

        if (!\is_array($cats) || !array_is_list($cats) || \count($cats) !== \count(array_unique($cats, SORT_REGULAR))) {
            throw new ConsentException('Invalid categories.', 422);
        }

        $known    = $this->categories();
        $selected = [];

        foreach ($known as $alias => $required) {
            if ($required && !\in_array($alias, $cats, true)) {
                throw new ConsentException('Required category missing.', 422);
            }

            if (\in_array($alias, $cats, true)) {
                $selected[] = $alias;
            }
        }

        if (\count($selected) !== \count($cats)) {
            throw new ConsentException('Unknown category.', 422);
        }

        return (object) [
            'consent_uuid'   => $id,
            'action'         => $action,
            'categories'     => json_encode($selected),
            'policy_version' => $version,
            'url'            => $this->pageUrl($payload['url'] ?? null, $siteRoot),
        ];
    }

    /**
     * The page where the choice was made, without query string and fragment (they can hold
     * personal data such as e-mail addresses or tokens).
     *
     * @param   mixed   $url       URL sent by the browser.
     * @param   string  $siteRoot  Absolute URL of the site.
     *
     * @return  string
     *
     * @throws  ConsentException  If it is not a page of this site.
     */
    private function pageUrl(mixed $url, string $siteRoot): string
    {
        $page = \is_string($url) ? parse_url($url) : false;
        $site = parse_url($siteRoot);

        if (
            !\is_array($page) || !isset($page['scheme'], $page['host']) || !\in_array(strtolower($page['scheme']), ['http', 'https'], true)
            || strcasecmp($page['host'], (string) ($site['host'] ?? '')) !== 0
        ) {
            throw new ConsentException('Invalid page URL.', 422);
        }

        $clean = strtolower($page['scheme']) . '://' . strtolower($page['host']) . (isset($page['port']) ? ':' . $page['port'] : '')
            . ($page['path'] ?? '/');

        return mb_substr($clean, 0, 2048);
    }

    /**
     * Published categories in order: alias => required.
     *
     * @return  array<string, boolean>
     */
    private function categories(): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['alias', 'required']))
            ->from($this->db->quoteName('#__lcookies_categories'))
            ->where($this->db->quoteName('state') . ' = 1')
            ->order($this->db->quoteName('ordering') . ' ASC');

        return array_map('boolval', $this->db->setQuery($query)->loadAssocList('alias', 'required'));
    }

    /**
     * Records stored for this address in the last RATE_WINDOW seconds.
     *
     * @param   string  $ipHash  Hash of the truncated address.
     *
     * @return  integer
     */
    private function recentCount(string $ipHash): int
    {
        $since = Factory::getDate('-' . self::RATE_WINDOW . ' seconds')->toSql();
        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__lcookies_consents'))
            ->where($this->db->quoteName('ip_hash') . ' = :hash')
            ->where($this->db->quoteName('created') . ' >= :since')
            ->bind(':hash', $ipHash)
            ->bind(':since', $since);

        return (int) $this->db->setQuery($query)->loadResult();
    }
}
