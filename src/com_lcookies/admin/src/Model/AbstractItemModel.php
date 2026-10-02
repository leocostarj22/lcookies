<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\ParameterType;
use Joomla\String\StringHelper;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Shared edit-model behaviour: forms, ordering of new rows, save as copy and trash-before-delete.
 */
abstract class AbstractItemModel extends AdminModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     */
    protected $text_prefix = 'COM_LCOOKIES';

    /**
     * Column that groups rows for ordering (e.g. `category_id`), empty for a flat list.
     *
     * @var    string
     */
    protected $orderingGroup = '';

    /**
     * Column made unique on "save as copy", empty when none.
     *
     * @var    string
     */
    protected $uniqueAlias = 'alias';

    /**
     * Returns a Table object, always from the administrator namespace.
     *
     * @param   string  $name     The table name.
     * @param   string  $prefix   The class prefix.
     * @param   array   $options  Configuration array for the table.
     *
     * @return  \Joomla\CMS\Table\Table
     */
    public function getTable($name = '', $prefix = 'Administrator', $options = [])
    {
        return parent::getTable($name ?: ucfirst($this->getName()), $prefix, $options);
    }

    /**
     * Loads the edit form `forms/<name>.xml`.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data.
     *
     * @return  \Joomla\CMS\Form\Form
     *
     * @throws  \Exception  When the form cannot be loaded.
     */
    public function getForm($data = [], $loadData = true)
    {
        $name = $this->getName();

        return $this->loadForm($this->option . '.' . $name, $name, ['control' => 'jform', 'load_data' => $loadData]);
    }

    /**
     * Data for the form: the session (after a failed save) or the stored item.
     *
     * @return  mixed
     */
    protected function loadFormData()
    {
        $context = $this->option . '.' . $this->getName();
        $data    = Factory::getApplication()->getUserState($this->option . '.edit.' . $this->getName() . '.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        $this->preprocessData($context, $data);

        return $data;
    }

    /**
     * Only trashed rows can be deleted.
     *
     * @param   object  $record  A record object.
     *
     * @return  boolean
     */
    protected function canDelete($record)
    {
        if (empty($record->id) || (int) $record->state !== -2) {
            return false;
        }

        return parent::canDelete($record);
    }

    /**
     * Places new rows at the end of their ordering group.
     *
     * @param   \Joomla\CMS\Table\Table  $table  A Table object.
     *
     * @return  void
     */
    protected function prepareTable($table)
    {
        if (empty($table->id) && empty($table->ordering)) {
            $table->ordering = $table->getNextOrder(implode(' AND ', $this->getReorderConditions($table)));
        }
    }

    /**
     * Restricts drag and drop ordering to the row's group.
     *
     * @param   \Joomla\CMS\Table\Table  $table  A Table object.
     *
     * @return  string[]
     */
    protected function getReorderConditions($table)
    {
        if ($this->orderingGroup === '') {
            return [];
        }

        $db = $this->getDatabase();

        return [$db->quoteName($this->orderingGroup) . ' = ' . (int) $table->{$this->orderingGroup}];
    }

    /**
     * Saves the item. On "save as copy" the copy gets a unique alias and starts unpublished.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean
     */
    public function save($data)
    {
        $input = Factory::getApplication()->getInput();

        if ($input->get('task') === 'save2copy') {
            $data['state'] = 0;

            if ($this->uniqueAlias !== '') {
                $data = $this->makeUniqueCopy($data);
            }
        }

        return parent::save($data);
    }

    /**
     * Increments title and alias until the alias is free.
     *
     * @param   array  $data  The form data.
     *
     * @return  array
     */
    protected function makeUniqueCopy(array $data): array
    {
        $db    = $this->getDatabase();
        $table = $this->getTable();
        $alias = (string) ($data[$this->uniqueAlias] ?? '');
        $title = LcookiesHelper::text($data['title'] ?? '');

        if ($alias === '') {
            return $data;
        }

        while (true) {
            $query = $db->createQuery()
                ->select('COUNT(*)')
                ->from($db->quoteName($table->getTableName()))
                ->where($db->quoteName($this->uniqueAlias) . ' = :alias')
                ->bind(':alias', $alias);

            if ((int) $db->setQuery($query)->loadResult() === 0) {
                break;
            }

            $alias = StringHelper::increment($alias, 'dash');
            $title = StringHelper::increment($title);
        }

        $data[$this->uniqueAlias] = $alias;
        $data['title']            = $title;

        return $data;
    }

    /**
     * Counts rows of a table that reference the given ids.
     *
     * @param   string  $table   Table name.
     * @param   string  $column  Foreign key column.
     * @param   int[]   $ids     Referenced ids.
     *
     * @return  array<int, int>  Count per referenced id.
     */
    protected function countReferences(string $table, string $column, array $ids): array
    {
        if (!$ids) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select([$db->quoteName($column, 'ref'), 'COUNT(*) AS ' . $db->quoteName('total')])
            ->from($db->quoteName($table))
            ->whereIn($db->quoteName($column), $ids, ParameterType::INTEGER)
            ->group($db->quoteName($column));

        return array_map('intval', $db->setQuery($query)->loadAssocList('ref', 'total'));
    }
}
