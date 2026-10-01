<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Model;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List model for cookies.
 */
class CookiesModel extends ListModel
{
    /**
     * @param   array                 $config   An optional associative array of configuration settings.
     * @param   ?MVCFactoryInterface  $factory  The factory.
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'name', 'a.name',
                'type', 'a.type',
                'source', 'a.source',
                'state', 'a.state',
                'ordering', 'a.ordering',
                'service_title', 's.title',
                'category_title', 'c.title',
                'published', 'service_id', 'category_id',
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
    protected function populateState($ordering = 'a.ordering', $direction = 'asc')
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
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.published');
        $id .= ':' . $this->getState('filter.service_id');
        $id .= ':' . $this->getState('filter.category_id');
        $id .= ':' . $this->getState('filter.type');
        $id .= ':' . $this->getState('filter.source');

        return parent::getStoreId($id);
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
                [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.service_id'),
                    $db->quoteName('a.name'),
                    $db->quoteName('a.match_type'),
                    $db->quoteName('a.type'),
                    $db->quoteName('a.domain'),
                    $db->quoteName('a.duration_value'),
                    $db->quoteName('a.duration_unit'),
                    $db->quoteName('a.source'),
                    $db->quoteName('a.state'),
                    $db->quoteName('a.ordering'),
                    $db->quoteName('a.checked_out'),
                    $db->quoteName('a.checked_out_time'),
                ]
            )
        )
            ->select(
                [
                    $db->quoteName('s.title', 'service_title'),
                    $db->quoteName('s.category_id', 'category_id'),
                    $db->quoteName('c.title', 'category_title'),
                    $db->quoteName('uc.name', 'editor'),
                ]
            )
            ->from($db->quoteName('#__lcookies_cookies', 'a'))
            ->join('LEFT', $db->quoteName('#__lcookies_services', 's'), $db->quoteName('s.id') . ' = ' . $db->quoteName('a.service_id'))
            ->join('LEFT', $db->quoteName('#__lcookies_categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('s.category_id'))
            ->join('LEFT', $db->quoteName('#__users', 'uc'), $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.checked_out'));

        $published = (string) $this->getState('filter.published');

        if (is_numeric($published)) {
            $published = (int) $published;
            $query->where($db->quoteName('a.state') . ' = :state')
                ->bind(':state', $published, ParameterType::INTEGER);
        } elseif ($published === '') {
            $query->where($db->quoteName('a.state') . ' IN (0, 1)');
        }

        if ($serviceId = (int) $this->getState('filter.service_id')) {
            $query->where($db->quoteName('a.service_id') . ' = :serviceId')
                ->bind(':serviceId', $serviceId, ParameterType::INTEGER);
        }

        if ($categoryId = (int) $this->getState('filter.category_id')) {
            $query->where($db->quoteName('s.category_id') . ' = :categoryId')
                ->bind(':categoryId', $categoryId, ParameterType::INTEGER);
        }

        if ($type = (string) $this->getState('filter.type')) {
            $query->where($db->quoteName('a.type') . ' = :type')
                ->bind(':type', $type);
        }

        if ($source = (string) $this->getState('filter.source')) {
            $query->where($db->quoteName('a.source') . ' = :source')
                ->bind(':source', $source);
        }

        if ($search = trim((string) $this->getState('filter.search'))) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')
                    ->bind(':id', $search, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', $search) . '%';
                $query->where('(' . $db->quoteName('a.name') . ' LIKE :name OR ' . $db->quoteName('a.domain') . ' LIKE :domain)')
                    ->bind(':name', $search)
                    ->bind(':domain', $search);
            }
        }

        $ordering  = $this->getState('list.ordering', 'a.ordering');
        $direction = $db->escape($this->getState('list.direction', 'ASC'));

        // Manual ordering is per service, so follow category and service order first.
        if ($ordering === 'a.ordering') {
            $query->order([$db->quoteName('c.ordering') . ' ASC', $db->quoteName('s.ordering') . ' ASC']);
        }

        $query->order($db->quoteName($db->escape($ordering)) . ' ' . $direction);

        return $query;
    }
}
