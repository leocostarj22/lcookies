<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Lcsilva\Component\Lcookies\Administrator\Contract\ContractBuilder;
use Lcsilva\Component\Lcookies\Administrator\Scanner\Scanner;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The visitor's choice on the server, read from the consent cookie with the same rules as
 * window.LCookies.hasConsent() in the browser.
 *
 * ```php
 * use Lcsilva\Component\Lcookies\Administrator\Helper\ConsentHelper;
 *
 * if (ConsentHelper::has('marketing')) { ... }
 * ```
 *
 * The answer depends on the visitor: output built with it must not be cached for everyone
 * (System - Page Cache, module/component caching). For cacheable pages, output the code always
 * and let LCookies block it (blocking patterns, or type="text/plain" data-lcookies-category="...").
 *
 * On the pages opened by the cookie scanner (?lcookies_scan=...) it answers like the browser does
 * there: no consent, or every category accepted.
 */
final class ConsentHelper
{
    /**
     * Per request: [consent or null, published categories alias => required].
     *
     * @var  ?array
     */
    private static ?array $state = null;

    /**
     * Whether the visitor accepted a category. Required categories are always accepted.
     *
     * @param   string  $category  Category alias, e.g. "statistics" or "marketing".
     *
     * @return  boolean  False for unknown or unpublished categories.
     */
    public static function has(string $category): bool
    {
        [$consent, $categories] = self::state();

        if (!isset($categories[$category])) {
            return false;
        }

        return $categories[$category] || ($consent !== null && \in_array($category, $consent['cats'], true));
    }

    /**
     * The valid consent of the visitor, as window.LCookies.getConsent().
     *
     * @return  ?array  `id` (consent id), `v` (policy version), `cats` (accepted categories), `ts` (Unix time);
     *                  null when there is no choice yet, it expired or is from an older policy version.
     */
    public static function get(): ?array
    {
        return self::state()[0];
    }

    /**
     * All the categories the visitor accepted, required ones included, in the site's order.
     *
     * @return  string[]
     */
    public static function granted(): array
    {
        return array_values(array_filter(array_keys(self::state()[1]), [self::class, 'has']));
    }

    /**
     * Forgets what was read in this request (tests, or after changing the options).
     *
     * @return  void
     */
    public static function reset(): void
    {
        self::$state = null;
    }

    /**
     * @return  array  [?array consent, array categories]
     */
    private static function state(): array
    {
        if (self::$state === null) {
            $app        = Factory::getApplication();
            $params     = ComponentHelper::getParams('com_lcookies');
            $version    = max(1, (int) $params->get('policy_version', 1));
            $categories = self::categories();
            $scan       = Scanner::mode((string) $app->getInput()->get(Scanner::PARAM, '', 'cmd'), (string) $app->get('secret'));
            $consent    = self::parse(
                (string) $app->getInput()->cookie->get(ContractBuilder::COOKIE_NAME, '', 'raw'),
                $version,
                min(395, max(1, (int) $params->get('consent_expiry_days', 180)))
            );

            if ($scan !== null) {
                $consent = $scan === 'all' ? ['id' => '', 'v' => $version, 'cats' => array_keys($categories), 'ts' => time()] : null;
            } elseif ($consent !== null) {
                $consent['cats'] = array_values(array_filter($consent['cats'], static fn ($alias) => isset($categories[$alias])));
            }

            self::$state = [$consent, $categories];
        }

        return self::$state;
    }

    /**
     * Validates the cookie value like the head script does.
     *
     * @param   string   $value    Cookie value (JSON).
     * @param   integer  $version  Current policy version.
     * @param   integer  $days     Validity of a choice in days.
     *
     * @return  ?array
     */
    private static function parse(string $value, int $version, int $days): ?array
    {
        if ($value === '' || \strlen($value) > 4096) {
            return null;
        }

        $consent = json_decode($value, true);

        if (
            !\is_array($consent) || ($consent['v'] ?? null) !== $version || !\is_int($consent['ts'] ?? null)
            || !\is_array($consent['cats'] ?? null) || !array_is_list($consent['cats'])
            || ($consent['ts'] + $days * 86400) <= time()
        ) {
            return null;
        }

        return [
            'id'   => \is_string($consent['id'] ?? null) ? $consent['id'] : '',
            'v'    => $version,
            'cats' => array_values(array_unique(array_filter($consent['cats'], 'is_string'))),
            'ts'   => $consent['ts'],
        ];
    }

    /**
     * @return  array  Published categories in order: alias => required.
     */
    private static function categories(): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $state = 1;
        $query = $db->createQuery()
            ->select($db->quoteName(['alias', 'required']))
            ->from($db->quoteName('#__lcookies_categories'))
            ->where($db->quoteName('state') . ' = :state')
            ->bind(':state', $state, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        return array_map('boolval', $db->setQuery($query)->loadAssocList('alias', 'required'));
    }
}
