<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Helper;

use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Shared helpers for com_lcookies.
 */
abstract class LcookiesHelper
{
    /**
     * Google Consent Mode v2 storage types.
     */
    public const GCM_TYPES = [
        'ad_storage',
        'ad_user_data',
        'ad_personalization',
        'analytics_storage',
        'functionality_storage',
        'personalization_storage',
        'security_storage',
    ];

    /**
     * Cookie name match types.
     */
    public const MATCH_TYPES = ['exact', 'prefix', 'regex'];

    /**
     * Storage types a "cookie" entry can describe.
     */
    public const STORAGE_TYPES = ['cookie', 'local', 'session', 'pixel'];

    /**
     * Duration units, `session` meaning "until the browser closes".
     */
    public const DURATION_UNITS = ['session', 'minute', 'hour', 'day', 'month', 'year'];

    /**
     * Translates a stored value when it is a language constant, otherwise returns it unchanged.
     *
     * Default data is stored as language constants so it follows the site language and can be
     * changed with Joomla language overrides. Text typed by the administrator is returned as is.
     *
     * @param   ?string  $value  Stored value.
     *
     * @return  string
     */
    public static function text(?string $value): string
    {
        $value = (string) $value;

        if ($value !== '' && preg_match('/^[A-Z][A-Z0-9_]+$/', $value)) {
            return Text::_($value);
        }

        return $value;
    }

    /**
     * Checks whether a string is a valid PCRE pattern body (without delimiters).
     *
     * @param   string  $pattern  The pattern, e.g. `^_ga_.*$`.
     *
     * @return  boolean
     */
    public static function isValidRegex(string $pattern): bool
    {
        return @preg_match('~' . str_replace('~', '\~', $pattern) . '~', '') !== false;
    }

    /**
     * Human readable duration, e.g. "2 years" or "Session".
     *
     * @param   integer  $value  Amount.
     * @param   string   $unit   One of DURATION_UNITS.
     *
     * @return  string
     */
    public static function duration(int $value, string $unit): string
    {
        if ($unit === 'session' || $value <= 0) {
            return Text::_('COM_LCOOKIES_DURATION_SESSION');
        }

        return Text::plural('COM_LCOOKIES_DURATION_N_' . strtoupper($unit), $value);
    }

    /**
     * Name of a cookie for the tables of visitors (preferences, policy page): the label of the
     * contract (ContractBuilder::cookieLabel()) as code when it is the actual name or prefix, as
     * text when it is a display name; long names may break after "_".
     *
     * @param   array   $cookie  Cookie of the contract (`name`, `label`).
     * @param   string  $class   CSS class of the code element.
     *
     * @return  string  HTML.
     */
    public static function cookieLabelHtml(array $cookie, string $class = ''): string
    {
        $label = (string) ($cookie['label'] ?? $cookie['name'] ?? '');
        $html  = str_replace('_', '_<wbr>', htmlspecialchars($label, ENT_QUOTES, 'UTF-8'));
        $name  = (string) ($cookie['name'] ?? '');

        // A display name such as "Joomla session cookie" is text; an actual name or prefix is code.
        if ($label !== $name && $label !== $name . '…') {
            return $html;
        }

        return '<code' . ($class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '') . '>' . $html . '</code>';
    }

    /**
     * Loads the site language file of com_lcookies (frontend texts and default data).
     *
     * Language overrides and files installed in /language take precedence over the copy shipped
     * inside the component folder.
     *
     * @param   Language  $language  The language to load into.
     *
     * @return  void
     */
    public static function loadSiteLanguage(Language $language): void
    {
        $language->load('com_lcookies', JPATH_SITE)
            || $language->load('com_lcookies', JPATH_SITE . '/components/com_lcookies');
    }
}
