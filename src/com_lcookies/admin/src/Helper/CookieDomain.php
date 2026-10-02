<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Domain of the consent cookie (option "cookie_domain"), which shares the visitor's choice
 * between subdomains (e.g. ".example.com" for www.example.com and shop.example.com).
 *
 * A browser silently refuses a cookie whose domain the page does not belong to, and then the
 * banner would come back on every page: the option is normalised and checked when saved, and only
 * used on hosts that belong to it.
 */
final class CookieDomain
{
    /**
     * Form filter of the option (config.xml): the normalised domain, or the value as typed when it
     * is not a domain name, so that the rule (Rule\CookiedomainRule) can refuse it.
     *
     * @param   mixed  $value  Value typed in the options.
     *
     * @return  string
     */
    public static function filter($value): string
    {
        return self::normalize($value) ?: trim((string) $value);
    }

    /**
     * Normalised domain: lower case, one leading dot, no scheme, port, path or trailing dot
     * ("https://www.Example.com/" → ".www.example.com").
     *
     * @param   mixed  $value  Value typed in the options.
     *
     * @return  string  Empty when empty or not a valid domain name (IP addresses included).
     */
    public static function normalize($value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $value);
        $value = trim(preg_split('#[/:?\#\s]#', $value)[0] ?? '', '.');

        if ($value === '' || filter_var($value, FILTER_VALIDATE_IP) || \strlen($value) > 253) {
            return '';
        }

        $labels = explode('.', $value);

        foreach ($labels as $label) {
            if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $label)) {
                return '';
            }
        }

        // A single label ("com", "localhost") cannot be shared with subdomains.
        return \count($labels) > 1 ? '.' . $value : '';
    }

    /**
     * Whether a host belongs to a cookie domain (the domain itself or one of its subdomains).
     *
     * @param   string  $domain  Normalised domain (see normalize()).
     * @param   string  $host    Host of the page, without port.
     *
     * @return  boolean
     */
    public static function matches(string $domain, string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));

        if ($domain === '' || $host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return $host === substr($domain, 1) || str_ends_with($host, $domain);
    }
}
