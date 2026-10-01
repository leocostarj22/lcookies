<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
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
 * List model for cookie categories.
 */
class CategoriesModel extends ListModel
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
                'title', 'a.title',
                'alias', 'a.alias',
                'state', 'a.state',
                'required', 'a.required',
                'ordering', 'a.ordering',
                'published',
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

        return parent::getStoreId($id);
    }

    /**
     * @return  QueryInterface
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery();

        $services = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__lcookies_services', 's'))
            ->where($db->quoteName('s.category_id') . ' = ' . $db->quoteName('a.id'))
            ->where($db->quoteName('s.state') . ' IN (0, 1)');

        $query->select(
            $this->getState(
                'list.select',
                [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.alias'),
                    $db->quoteName('a.title'),
                    $db->quoteName('a.description'),
                    $db->quoteName('a.required'),
                    $db->quoteName('a.core'),
                    $db->quoteName('a.gcm_types'),
                    $db->quoteName('a.state'),
                    $db->quoteName('a.ordering'),
                    $db->quoteName('a.checked_out'),
                    $db->quoteName('a.checked_out_time'),
                ]
            )
        )
            ->select('(' . $services . ') AS ' . $db->quoteName('count_services'))
            ->select($db->quoteName('uc.name', 'editor'))
            ->from($db->quoteName('#__lcookies_categories', 'a'))
            ->join('LEFT', $db->quoteName('#__users', 'uc'), $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.checked_out'));

        $published = (string) $this->getState('filter.published');

        if (is_numeric($published)) {
            $published = (int) $published;
            $query->where($db->quoteName('a.state') . ' = :state')
                ->bind(':state', $published, ParameterType::INTEGER);
        } elseif ($published === '') {
            $query->where($db->quoteName('a.state') . ' IN (0, 1)');
        }

        if ($search = trim((string) $this->getState('filter.search'))) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')
                    ->bind(':id', $search, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', $search) . '%';
                $query->where('(' . $db->quoteName('a.title') . ' LIKE :title OR ' . $db->quoteName('a.alias') . ' LIKE :alias)')
                    ->bind(':title', $search)
                    ->bind(':alias', $search);
            }
        }

        $query->order(
            $db->quoteName($db->escape($this->getState('list.ordering', 'a.ordering'))) . ' ' . $db->escape($this->getState('list.direction', 'ASC'))
        );

        return $query;
    }
}
