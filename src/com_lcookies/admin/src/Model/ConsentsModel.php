<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;
use Lcsilva\Component\Lcookies\Administrator\Consent\ConsentLog;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List model for the consent records (read only).
 */
class ConsentsModel extends ListModel
{
    /**
     * Records read per query while exporting.
     */
    private const EXPORT_CHUNK = 1000;

    /**
     * @param   array                 $config   An optional associative array of configuration settings.
     * @param   ?MVCFactoryInterface  $factory  The factory.
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'created', 'a.created',
                'action', 'a.action',
                'policy_version', 'a.policy_version',
                'category', 'user', 'from', 'to',
            ];
        }

        parent::__construct($config, $factory);
    }

    /**
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     */
    protected function populateState($ordering = 'a.id', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }

    /**
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string
     */
    protected function getStoreId($id = '')
    {
        foreach (['search', 'action', 'policy_version', 'category', 'user', 'from', 'to'] as $filter) {
            $id .= ':' . $this->getState('filter.' . $filter);
        }

        return parent::getStoreId($id);
    }

    /**
     * Rows of the current filters and ordering for the CSV export, read in chunks.
     *
     * @return  \Generator<object>
     */
    public function getExportRows(): \Generator
    {
        $db     = $this->getDatabase();
        $query  = $this->getListQuery();
        $offset = 0;

        do {
            $query->setLimit(self::EXPORT_CHUNK, $offset);
            $rows = $db->setQuery($query)->loadObjectList();

            yield from $rows;

            $offset += self::EXPORT_CHUNK;
        } while (\count($rows) === self::EXPORT_CHUNK);
    }

    /**
     * Translated titles of all categories by alias (records store aliases).
     *
     * @return  array<string, string>
     */
    public function getCategoryTitles(): array
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName(['alias', 'title']))
            ->from($db->quoteName('#__lcookies_categories'));

        return array_map([LcookiesHelper::class, 'text'], $db->setQuery($query)->loadAssocList('alias', 'title'));
    }

    /**
     * Deletes the records older than the retention period of the options.
     *
     * @return  integer  Number of records deleted.
     */
    public function purge(): int
    {
        $log = new ConsentLog($this->getDatabase(), ComponentHelper::getParams('com_lcookies'));

        return $log->purge();
    }

    /**
     * @return  QueryInterface
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery();

        $query->select(
            $this->getState(
                'list.select',
                $db->quoteName(
                    ['a.id', 'a.consent_uuid', 'a.action', 'a.categories', 'a.policy_version', 'a.user_id', 'a.ip_hash', 'a.ua_hash', 'a.url', 'a.language', 'a.created']
                )
            )
        )
            ->select([$db->quoteName('u.name', 'user_name'), $db->quoteName('u.username', 'user_username')])
            ->from($db->quoteName('#__lcookies_consents', 'a'))
            ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.user_id'));

        if ($action = (string) $this->getState('filter.action')) {
            $query->where($db->quoteName('a.action') . ' = :action')
                ->bind(':action', $action);
        }

        if ($version = (int) $this->getState('filter.policy_version')) {
            $query->where($db->quoteName('a.policy_version') . ' = :version')
                ->bind(':version', $version, ParameterType::INTEGER);
        }

        // Categories are stored as a JSON list of aliases: ["necessary","statistics"].
        if ($category = (string) $this->getState('filter.category')) {
            $category = '%' . json_encode($category) . '%';
            $query->where($db->quoteName('a.categories') . ' LIKE :category')
                ->bind(':category', $category);
        }

        $user = (string) $this->getState('filter.user');

        if ($user === 'guest') {
            $query->where($db->quoteName('a.user_id') . ' IS NULL');
        } elseif ($user === 'registered') {
            $query->where($db->quoteName('a.user_id') . ' IS NOT NULL');
        }

        // Dates are typed in the user's time zone, records are stored in UTC.
        $timezone = Factory::getApplication()->getIdentity()->getTimezone();

        foreach (['from' => ['>=', '00:00:00'], 'to' => ['<=', '23:59:59']] as $filter => [$operator, $time]) {
            $date = trim((string) $this->getState('filter.' . $filter));

            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
                $utc = Factory::getDate(substr($date, 0, 10) . ' ' . $time, $timezone)->toSql();
                $query->where($db->quoteName('a.created') . ' ' . $operator . ' :' . $filter)
                    ->bind(':' . $filter, $utc);
            }
        }

        if ($search = trim((string) $this->getState('filter.search'))) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')
                    ->bind(':id', $search, ParameterType::INTEGER);
            } elseif (preg_match('/^(?:uuid:)?\s*([0-9a-f-]{36})$/i', $search, $match)) {
                $uuid = strtolower($match[1]);
                $query->where($db->quoteName('a.consent_uuid') . ' = :uuid')
                    ->bind(':uuid', $uuid);
            } else {
                $search = '%' . str_replace(' ', '%', $search) . '%';
                $query->where(
                    '(' . $db->quoteName('a.consent_uuid') . ' LIKE :uuid'
                    . ' OR ' . $db->quoteName('a.url') . ' LIKE :url)'
                )
                    ->bind(':uuid', $search)
                    ->bind(':url', $search);
            }
        }

        $ordering  = $this->getState('list.ordering', 'a.id');
        $direction = $db->escape($this->getState('list.direction', 'DESC'));

        $query->order($db->quoteName($db->escape($ordering)) . ' ' . $direction);

        // Same second: keep the insertion order.
        if ($ordering !== 'a.id') {
            $query->order($db->quoteName('a.id') . ' ' . $direction);
        }

        return $query;
    }
}
