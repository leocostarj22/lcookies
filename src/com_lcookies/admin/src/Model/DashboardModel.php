<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Lcsilva\Component\Lcookies\Administrator\Consent\ConsentLog;
use Lcsilva\Component\Lcookies\Administrator\Helper\CookieDomain;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dashboard: totals, consent statistics and configuration alerts.
 */
class DashboardModel extends BaseDatabaseModel
{
    /**
     * Days covered by the statistics.
     */
    public const DAYS = 30;

    /**
     * Published categories, services and cookies, and the main options.
     *
     * @return  array
     */
    public function getSummary(): array
    {
        $params = ComponentHelper::getParams('com_lcookies');

        return [
            'categories'    => $this->count('#__lcookies_categories'),
            'services'      => $this->count('#__lcookies_services'),
            'cookies'       => $this->count('#__lcookies_cookies'),
            'policyVersion' => max(1, (int) $params->get('policy_version', 1)),
            'logging'       => (bool) $params->get('log_consents', 1),
            'consentMode'   => (bool) $params->get('gcm_enabled', 0),
        ];
    }

    /**
     * Consent records of the last DAYS days (UTC).
     *
     * @return  array  `total`, `consents` (distinct ids), `actions` (action => count),
     *                 `categories` (alias => [title, count]), `days` (Y-m-d => count, every day).
     */
    public function getStats(): array
    {
        $db    = $this->getDatabase();
        $since = Factory::getDate('-' . (self::DAYS - 1) . ' days')->format('Y-m-d') . ' 00:00:00';
        $base  = $db->createQuery()
            ->from($db->quoteName('#__lcookies_consents'))
            ->where($db->quoteName('created') . ' >= :since')
            ->bind(':since', $since);

        $totals = $db->setQuery(
            (clone $base)->select(['COUNT(*) AS ' . $db->quoteName('total'), 'COUNT(DISTINCT ' . $db->quoteName('consent_uuid') . ') AS ' . $db->quoteName('consents')])
        )->loadAssoc();

        $actions = array_fill_keys(ConsentLog::ACTIONS, 0);

        foreach ($db->setQuery((clone $base)->select([$db->quoteName('action'), 'COUNT(*) AS ' . $db->quoteName('total')])->group($db->quoteName('action')))->loadAssocList() as $row) {
            $actions[$row['action']] = (int) $row['total'];
        }

        $day  = 'CAST(' . $db->quoteName('created') . ' AS DATE)';
        $days = [];

        for ($i = self::DAYS - 1; $i >= 0; $i--) {
            $days[Factory::getDate('-' . $i . ' days')->format('Y-m-d')] = 0;
        }

        foreach ($db->setQuery((clone $base)->select([$day . ' AS ' . $db->quoteName('day'), 'COUNT(*) AS ' . $db->quoteName('total')])->group($day))->loadAssocList() as $row) {
            $key = substr((string) $row['day'], 0, 10);

            if (isset($days[$key])) {
                $days[$key] = (int) $row['total'];
            }
        }

        // Share of the choices that include each optional category.
        $categories = [];
        $optional   = $db->setQuery(
            $db->createQuery()
                ->select($db->quoteName(['alias', 'title']))
                ->from($db->quoteName('#__lcookies_categories'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('required') . ' = 0')
                ->order($db->quoteName('ordering') . ' ASC')
        )->loadAssocList();

        foreach ($optional as $category) {
            // The alias with its JSON quotes; "_" and "%" escaped so they are not wildcards.
            $like = '%' . addcslashes(json_encode($category['alias']), '_%') . '%';

            $categories[$category['alias']] = [
                'title' => LcookiesHelper::text($category['title']),
                'count' => (int) $db->setQuery(
                    (clone $base)->select('COUNT(*)')
                        ->where($db->quoteName('categories') . ' LIKE :category')
                        ->bind(':category', $like)
                )->loadResult(),
            ];
        }

        return [
            'total'      => (int) $totals['total'],
            'consents'   => (int) $totals['consents'],
            'actions'    => $actions,
            'categories' => $categories,
            'days'       => $days,
        ];
    }

    /**
     * Policy versions with their consent records (only the records still kept, see the retention
     * period); the current version is always included.
     *
     * @return  array  Newest first: `version`, `current`, `first`, `last` (UTC or null), `total`,
     *                 `consents` (distinct ids), `accepted`, `rejected` (choices of all / none).
     */
    public function getPolicyHistory(): array
    {
        $db       = $this->getDatabase();
        $current  = max(1, (int) ComponentHelper::getParams('com_lcookies')->get('policy_version', 1));
        $accepted = 'accept_all';
        $rejected = 'reject_all';
        $query    = $db->createQuery()
            ->select([
                $db->quoteName('policy_version', 'version'),
                'MIN(' . $db->quoteName('created') . ') AS ' . $db->quoteName('first'),
                'MAX(' . $db->quoteName('created') . ') AS ' . $db->quoteName('last'),
                'COUNT(*) AS ' . $db->quoteName('total'),
                'COUNT(DISTINCT ' . $db->quoteName('consent_uuid') . ') AS ' . $db->quoteName('consents'),
                'SUM(CASE WHEN ' . $db->quoteName('action') . ' = :accepted THEN 1 ELSE 0 END) AS ' . $db->quoteName('accepted'),
                'SUM(CASE WHEN ' . $db->quoteName('action') . ' = :rejected THEN 1 ELSE 0 END) AS ' . $db->quoteName('rejected'),
            ])
            ->from($db->quoteName('#__lcookies_consents'))
            ->group($db->quoteName('policy_version'))
            ->bind(':accepted', $accepted)
            ->bind(':rejected', $rejected);

        $history = [];

        foreach ($db->setQuery($query)->loadAssocList() as $row) {
            $version           = (int) $row['version'];
            $history[$version] = [
                'version'  => $version,
                'current'  => $version === $current,
                'first'    => $row['first'],
                'last'     => $row['last'],
                'total'    => (int) $row['total'],
                'consents' => (int) $row['consents'],
                'accepted' => (int) $row['accepted'],
                'rejected' => (int) $row['rejected'],
            ];
        }

        $history[$current] ??= ['version' => $current, 'current' => true, 'first' => null, 'last' => null, 'total' => 0, 'consents' => 0, 'accepted' => 0, 'rejected' => 0];
        krsort($history);

        return array_values($history);
    }

    /**
     * Publishes a new version of the cookie policy: every visitor is asked again.
     *
     * @return  integer  The new version.
     */
    public function newPolicyVersion(): int
    {
        $db      = $this->getDatabase();
        $type    = 'component';
        $element = 'com_lcookies';
        $params  = clone ComponentHelper::getParams('com_lcookies');
        $version = max(1, (int) $params->get('policy_version', 1)) + 1;

        $params->set('policy_version', $version);

        $json  = $params->toString();
        $query = $db->createQuery()
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('params') . ' = :params')
            ->where([$db->quoteName('type') . ' = :type', $db->quoteName('element') . ' = :element'])
            ->bind(':params', $json)
            ->bind(':type', $type)
            ->bind(':element', $element);

        $db->setQuery($query)->execute();

        // Component options are cached in _system; the contract cache is keyed by the options.
        $this->cleanCache('_system');
        $this->cleanCache('com_lcookies');

        return $version;
    }

    /**
     * Problems in the configuration.
     *
     * @return  array  List of [type (danger|warning|info), language key, argument, link].
     */
    public function getAlerts(): array
    {
        $alerts = [];
        $params = ComponentHelper::getParams('com_lcookies');

        if (!PluginHelper::isEnabled('system', 'lcookies')) {
            $alerts[] = ['danger', 'COM_LCOOKIES_ALERT_PLUGIN_DISABLED', '', 'index.php?option=com_plugins&view=plugins&filter[folder]=system&filter[search]=lcookies'];
        }

        $db           = $this->getDatabase();
        $unclassified = $db->setQuery(
            $db->createQuery()
                ->select([$db->quoteName('c.id'), 'COUNT(' . $db->quoteName('s.id') . ') AS ' . $db->quoteName('total')])
                ->from($db->quoteName('#__lcookies_categories', 'c'))
                ->join('INNER', $db->quoteName('#__lcookies_services', 's'), $db->quoteName('s.category_id') . ' = ' . $db->quoteName('c.id'))
                ->where($db->quoteName('c.alias') . ' = ' . $db->quote('unclassified'))
                ->where($db->quoteName('s.state') . ' = 1')
                ->group($db->quoteName('c.id'))
        )->loadAssoc();

        if ($unclassified) {
            $alerts[] = ['warning', 'COM_LCOOKIES_ALERT_UNCLASSIFIED', (string) $unclassified['total'],
                'index.php?option=com_lcookies&view=services&filter[category_id]=' . (int) $unclassified['id']];
        }

        $empty = (int) $db->setQuery(
            $db->createQuery()
                ->select('COUNT(*)')
                ->from($db->quoteName('#__lcookies_services', 's'))
                ->where($db->quoteName('s.state') . ' = 1')
                ->where('(' . $db->quoteName('s.block_patterns') . ' IS NULL OR ' . $db->quoteName('s.block_patterns') . ' = ' . $db->quote('') . ')')
                ->where('(' . $db->quoteName('s.head_code') . ' IS NULL OR ' . $db->quoteName('s.head_code') . ' = ' . $db->quote('') . ')')
                ->where('(' . $db->quoteName('s.body_code') . ' IS NULL OR ' . $db->quoteName('s.body_code') . ' = ' . $db->quote('') . ')')
                ->where($db->quoteName('s.category_id') . ' IN (SELECT ' . $db->quoteName('id') . ' FROM ' . $db->quoteName('#__lcookies_categories')
                    . ' WHERE ' . $db->quoteName('required') . ' = 0)')
        )->loadResult();

        if ($empty) {
            $alerts[] = ['info', 'COM_LCOOKIES_ALERT_NOT_BLOCKED', (string) $empty, 'index.php?option=com_lcookies&view=services'];
        }

        $done = 'done';
        $scan = $db->setQuery(
            $db->createQuery()
                ->select($db->quoteName(['unknown', 'issues']))
                ->from($db->quoteName('#__lcookies_scans'))
                ->where($db->quoteName('status') . ' = :done')
                ->order($db->quoteName('id') . ' DESC')
                ->bind(':done', $done)
                ->setLimit(1)
        )->loadAssoc();
        $scanner = 'index.php?option=com_lcookies&view=scanner';

        if (!$scan) {
            $alerts[] = ['info', 'COM_LCOOKIES_ALERT_NEVER_SCANNED', '', $scanner];
        } elseif ((int) $scan['issues']) {
            $alerts[] = ['danger', 'COM_LCOOKIES_ALERT_SCAN_ISSUES', (string) $scan['issues'], $scanner];
        } elseif ((int) $scan['unknown']) {
            $alerts[] = ['warning', 'COM_LCOOKIES_ALERT_SCAN_UNKNOWN', (string) $scan['unknown'], $scanner];
        }

        if (!$params->get('log_consents', 1)) {
            $alerts[] = ['info', 'COM_LCOOKIES_ALERT_LOGGING_OFF', '', 'index.php?option=com_config&view=component&component=com_lcookies'];
        }

        $domain = (string) $params->get('cookie_domain', '');

        if ($domain !== '' && !CookieDomain::matches(CookieDomain::normalize($domain), (new Uri(Uri::root()))->getHost())) {
            $alerts[] = ['warning', 'COM_LCOOKIES_ALERT_COOKIE_DOMAIN', $domain, 'index.php?option=com_config&view=component&component=com_lcookies'];
        }

        if (!(int) $params->get('privacy_menuitem', 0)) {
            $alerts[] = ['info', 'COM_LCOOKIES_ALERT_NO_PRIVACY_PAGE', '', 'index.php?option=com_config&view=component&component=com_lcookies'];
        }

        return $alerts;
    }

    /**
     * @param   string  $table  Table name.
     *
     * @return  integer  Published rows.
     */
    private function count(string $table): int
    {
        $db = $this->getDatabase();

        return (int) $db->setQuery(
            $db->createQuery()
                ->select('COUNT(*)')
                ->from($db->quoteName($table))
                ->where($db->quoteName('state') . ' = 1')
        )->loadResult();
    }
}
