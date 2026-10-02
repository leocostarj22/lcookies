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
 * List model for services.
 */
class ServicesModel extends ListModel
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
                'provider', 'a.provider',
                'state', 'a.state',
                'ordering', 'a.ordering',
                'category_title', 'c.title',
                'published', 'category_id',
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
        $id .= ':' . $this->getState('filter.category_id');

        return parent::getStoreId($id);
    }

    /**
     * Form of the batch dialog (move to another category).
     *
     * @return  \Joomla\CMS\Form\Form
     *
     * @throws  \Exception  When the form cannot be loaded.
     */
    public function getBatchForm()
    {
        return $this->loadForm($this->context . '.batch', 'batch_services', ['control' => '', 'load_data' => false]);
    }

    /**
     * @return  QueryInterface
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery();

        $cookies = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__lcookies_cookies', 'k'))
            ->where($db->quoteName('k.service_id') . ' = ' . $db->quoteName('a.id'))
            ->where($db->quoteName('k.state') . ' IN (0, 1)');

        $query->select(
            $this->getState(
                'list.select',
                [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.category_id'),
                    $db->quoteName('a.alias'),
                    $db->quoteName('a.title'),
                    $db->quoteName('a.provider'),
                    $db->quoteName('a.privacy_url'),
                    $db->quoteName('a.block_patterns'),
                    $db->quoteName('a.state'),
                    $db->quoteName('a.ordering'),
                    $db->quoteName('a.checked_out'),
                    $db->quoteName('a.checked_out_time'),
                ]
            )
        )
            ->select(
                [
                    '(' . $cookies . ') AS ' . $db->quoteName('count_cookies'),
                    $db->quoteName('c.title', 'category_title'),
                    $db->quoteName('c.alias', 'category_alias'),
                    $db->quoteName('uc.name', 'editor'),
                ]
            )
            ->from($db->quoteName('#__lcookies_services', 'a'))
            ->join('LEFT', $db->quoteName('#__lcookies_categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.category_id'))
            ->join('LEFT', $db->quoteName('#__users', 'uc'), $db->quoteName('uc.id') . ' = ' . $db->quoteName('a.checked_out'));

        $published = (string) $this->getState('filter.published');

        if (is_numeric($published)) {
            $published = (int) $published;
            $query->where($db->quoteName('a.state') . ' = :state')
                ->bind(':state', $published, ParameterType::INTEGER);
        } elseif ($published === '') {
            $query->where($db->quoteName('a.state') . ' IN (0, 1)');
        }

        if ($categoryId = (int) $this->getState('filter.category_id')) {
            $query->where($db->quoteName('a.category_id') . ' = :categoryId')
                ->bind(':categoryId', $categoryId, ParameterType::INTEGER);
        }

        if ($search = trim((string) $this->getState('filter.search'))) {
            if (stripos($search, 'id:') === 0) {
                $search = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :id')
                    ->bind(':id', $search, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', $search) . '%';
                $query->where(
                    '(' . $db->quoteName('a.title') . ' LIKE :title'
                    . ' OR ' . $db->quoteName('a.alias') . ' LIKE :alias'
                    . ' OR ' . $db->quoteName('a.provider') . ' LIKE :provider)'
                )
                    ->bind(':title', $search)
                    ->bind(':alias', $search)
                    ->bind(':provider', $search);
            }
        }

        $ordering  = $this->getState('list.ordering', 'a.ordering');
        $direction = $db->escape($this->getState('list.direction', 'ASC'));

        // Manual ordering is per category, so list categories in their own order first.
        if ($ordering === 'a.ordering') {
            $query->order($db->quoteName('c.ordering') . ' ASC');
        }

        $query->order($db->quoteName($db->escape($ordering)) . ' ' . $direction);

        return $query;
    }
}
