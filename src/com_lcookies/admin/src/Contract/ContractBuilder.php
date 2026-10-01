<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Contract;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds the JSON contract that the frontend (layouts, JavaScript, previews, API) consumes.
 *
 * The format is described by docs/contract.schema.json. Texts are translated with the active
 * language, so the caller must load the site language file of com_lcookies first
 * (see LcookiesHelper::loadSiteLanguage()).
 */
final class ContractBuilder
{
    /**
     * Version of the contract format. Increase it on incompatible changes.
     */
    public const SCHEMA = 1;

    /**
     * Name of the cookie that stores the visitor's choice.
     */
    public const COOKIE_NAME = 'lcookies_consent';

    /**
     * Site URL that records the consent (Site\Controller\ConsentController). Not routed, so it is
     * the same on every page and the contract stays cacheable.
     */
    public const ENDPOINT = 'index.php?option=com_lcookies&task=consent.save&format=json';

    /**
     * Consent Mode types that make a category subject to Global Privacy Control.
     */
    private const GPC_TYPES = ['ad_storage', 'ad_user_data', 'ad_personalization'];

    /**
     * Texts of the user interface: contract key => [option name, default language constant].
     */
    private const TEXTS = [
        'label'            => [null, 'COM_LCOOKIES_UI_LABEL'],
        'title'            => ['text_title', 'COM_LCOOKIES_UI_TITLE'],
        'message'          => ['text_message', 'COM_LCOOKIES_UI_MESSAGE'],
        'acceptAll'        => ['text_accept', 'COM_LCOOKIES_UI_ACCEPT_ALL'],
        'rejectAll'        => ['text_reject', 'COM_LCOOKIES_UI_REJECT_ALL'],
        'settings'         => ['text_settings', 'COM_LCOOKIES_UI_SETTINGS'],
        'save'             => ['text_save', 'COM_LCOOKIES_UI_SAVE'],
        'close'            => [null, 'COM_LCOOKIES_UI_CLOSE'],
        'preferencesTitle' => [null, 'COM_LCOOKIES_UI_PREFERENCES_TITLE'],
        'preferencesIntro' => [null, 'COM_LCOOKIES_UI_PREFERENCES_INTRO'],
        'alwaysActive'     => [null, 'COM_LCOOKIES_UI_ALWAYS_ACTIVE'],
        'privacyPolicy'    => [null, 'COM_LCOOKIES_UI_PRIVACY_POLICY'],
        'details'          => [null, 'COM_LCOOKIES_UI_DETAILS'],
        'provider'         => [null, 'COM_LCOOKIES_UI_PROVIDER'],
        'noCookies'        => [null, 'COM_LCOOKIES_UI_NO_COOKIES'],
        'colName'          => [null, 'COM_LCOOKIES_UI_COL_NAME'],
        'colType'          => [null, 'COM_LCOOKIES_UI_COL_TYPE'],
        'colDuration'      => [null, 'COM_LCOOKIES_UI_COL_DURATION'],
        'colDescription'   => [null, 'COM_LCOOKIES_UI_COL_DESCRIPTION'],
        'typeCookie'       => [null, 'COM_LCOOKIES_UI_TYPE_COOKIE'],
        'typeLocal'        => [null, 'COM_LCOOKIES_UI_TYPE_LOCAL'],
        'typeSession'      => [null, 'COM_LCOOKIES_UI_TYPE_SESSION'],
        'typePixel'        => [null, 'COM_LCOOKIES_UI_TYPE_PIXEL'],
        'placeholder'      => [null, 'COM_LCOOKIES_UI_PLACEHOLDER'],
        'placeholderAllow' => [null, 'COM_LCOOKIES_UI_PLACEHOLDER_ALLOW'],
        'floating'         => [null, 'COM_LCOOKIES_UI_FLOATING'],
        'gpc'              => [null, 'COM_LCOOKIES_UI_GPC'],
    ];

    /**
     * @param   DatabaseInterface  $db      Database connection.
     * @param   Registry           $params  Options of com_lcookies.
     */
    public function __construct(private DatabaseInterface $db, private Registry $params)
    {
    }

    /**
     * Builds the contract.
     *
     * @return  array
     */
    public function build(): array
    {
        $params = $this->params;
        $gcm    = null;

        if ($params->get('gcm_enabled', 0)) {
            $gcm = [
                'waitForUpdate'    => max(0, (int) $params->get('gcm_wait_for_update', 500)),
                'adsDataRedaction' => (bool) $params->get('gcm_ads_data_redaction', 1),
                'urlPassthrough'   => (bool) $params->get('gcm_url_passthrough', 0),
            ];
        }

        $floating = $params->get('floating_button', 1) ? $params->get('floating_position', 'left') : null;
        $privacy  = (int) $params->get('privacy_menuitem', 0);

        return [
            'schema'            => self::SCHEMA,
            'policyVersion'     => max(1, (int) $params->get('policy_version', 1)),
            'expiryDays'        => min(395, max(1, (int) $params->get('consent_expiry_days', 180))),
            'cookie'            => [
                'name'   => self::COOKIE_NAME,
                'domain' => trim((string) $params->get('cookie_domain', '')),
            ],
            'endpoint'          => $params->get('log_consents', 1) ? Uri::root(true) . '/' . self::ENDPOINT : null,
            'layout'            => $this->option('layout', ['bar-bottom', 'bar-top', 'box-bottom-left', 'box-bottom-right', 'modal'], 'box-bottom-left'),
            'theme'             => $this->option('theme', ['auto', 'light', 'dark'], 'auto'),
            'floatingButton'    => \in_array($floating, ['left', 'right'], true) ? $floating : null,
            'privacyUrl'        => $privacy > 0 ? Route::link('site', 'index.php?Itemid=' . $privacy) : null,
            'respectGpc'        => (bool) $params->get('respect_gpc', 1),
            'autoblock'         => (bool) $params->get('autoblock', 1),
            'iframePlaceholder' => (bool) $params->get('iframe_placeholder', 1),
            'gcm'               => $gcm,
            'categories'        => $this->categories(),
            'texts'             => $this->texts(),
        ];
    }

    /**
     * Published categories with their published services and cookies.
     *
     * Optional categories without services are left out: there is nothing to consent to.
     *
     * @return  array
     */
    private function categories(): array
    {
        $db    = $this->db;
        $state = 1;

        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'alias', 'title', 'description', 'required', 'gcm_types']))
            ->from($db->quoteName('#__lcookies_categories'))
            ->where($db->quoteName('state') . ' = :state')
            ->order($db->quoteName('ordering') . ' ASC')
            ->bind(':state', $state, ParameterType::INTEGER);

        $categories = $db->setQuery($query)->loadObjectList('id');

        if (!$categories) {
            return [];
        }

        $services = $this->services(array_keys($categories));
        $result   = [];

        foreach ($categories as $id => $category) {
            $gcm      = json_decode((string) $category->gcm_types, true);
            $gcm      = array_values(array_intersect(LcookiesHelper::GCM_TYPES, \is_array($gcm) ? $gcm : []));
            $required = (bool) $category->required;

            if (!$required && empty($services[$id])) {
                continue;
            }

            $result[] = [
                'alias'       => $category->alias,
                'title'       => LcookiesHelper::text($category->title),
                'description' => LcookiesHelper::text($category->description),
                'required'    => $required,
                'gcm'         => $gcm,
                'gpcOptOut'   => !$required && ($category->alias === 'marketing' || array_intersect(self::GPC_TYPES, $gcm) !== []),
                'services'    => $services[$id] ?? [],
            ];
        }

        return $result;
    }

    /**
     * Published services grouped by category id.
     *
     * @param   integer[]  $categoryIds  Category ids.
     *
     * @return  array
     */
    private function services(array $categoryIds): array
    {
        $db    = $this->db;
        $state = 1;

        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'category_id', 'alias', 'title', 'provider', 'privacy_url', 'description', 'block_patterns']))
            ->from($db->quoteName('#__lcookies_services'))
            ->where($db->quoteName('state') . ' = :state')
            ->whereIn($db->quoteName('category_id'), $categoryIds)
            ->order([$db->quoteName('category_id'), $db->quoteName('ordering') . ' ASC'])
            ->bind(':state', $state, ParameterType::INTEGER);

        $rows = $db->setQuery($query)->loadObjectList('id');

        if (!$rows) {
            return [];
        }

        $cookies = $this->cookies(array_keys($rows));
        $result  = [];

        foreach ($rows as $id => $row) {
            $patterns = preg_split('/\R/', (string) $row->block_patterns);

            $result[(int) $row->category_id][] = [
                'id'          => (int) $id,
                'alias'       => $row->alias,
                'title'       => LcookiesHelper::text($row->title),
                'provider'    => (string) $row->provider,
                'description' => LcookiesHelper::text($row->description),
                'privacyUrl'  => $row->privacy_url !== '' ? $row->privacy_url : null,
                'patterns'    => array_values(array_filter(array_map('trim', $patterns), 'strlen')),
                'cookies'     => $cookies[$id] ?? [],
            ];
        }

        return $result;
    }

    /**
     * Published cookies grouped by service id.
     *
     * @param   integer[]  $serviceIds  Service ids.
     *
     * @return  array
     */
    private function cookies(array $serviceIds): array
    {
        $db    = $this->db;
        $state = 1;

        $query = $db->createQuery()
            ->select($db->quoteName(['service_id', 'name', 'match_type', 'type', 'domain', 'duration_value', 'duration_unit', 'description']))
            ->from($db->quoteName('#__lcookies_cookies'))
            ->where($db->quoteName('state') . ' = :state')
            ->whereIn($db->quoteName('service_id'), $serviceIds)
            ->order([$db->quoteName('service_id'), $db->quoteName('ordering') . ' ASC'])
            ->bind(':state', $state, ParameterType::INTEGER);

        $result = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $result[(int) $row->service_id][] = [
                'name'        => $row->name,
                'match'       => $row->match_type,
                'type'        => $row->type,
                'domain'      => (string) $row->domain,
                'duration'    => LcookiesHelper::duration((int) $row->duration_value, $row->duration_unit),
                'description' => LcookiesHelper::text($row->description),
            ];
        }

        return $result;
    }

    /**
     * Interface texts: the value set in the options, or the translated default.
     *
     * @return  array<string, string>
     */
    private function texts(): array
    {
        $texts = [];

        foreach (self::TEXTS as $key => [$option, $constant]) {
            $value       = $option ? trim((string) $this->params->get($option, '')) : '';
            $texts[$key] = $value !== '' ? LcookiesHelper::text($value) : Text::_($constant);
        }

        return $texts;
    }

    /**
     * Reads an option restricted to a list of values.
     *
     * @param   string    $name     Option name.
     * @param   string[]  $allowed  Allowed values.
     * @param   string    $default  Fallback.
     *
     * @return  string
     */
    private function option(string $name, array $allowed, string $default): string
    {
        $value = (string) $this->params->get($name, $default);

        return \in_array($value, $allowed, true) ? $value : $default;
    }
}
