<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Table;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Common behaviour of the LCookies tables: `state` as publish column, nullable checkout and
 * created/modified bookkeeping.
 */
abstract class AbstractLcookiesTable extends Table
{
    /**
     * Indicates that columns fully support the NULL value in the database.
     *
     * @var    boolean
     */
    protected $_supportNullValue = true;

    /**
     * @param   string                    $tableName   Table name.
     * @param   DatabaseInterface         $db          Database connector object.
     * @param   ?DispatcherInterface      $dispatcher  Event dispatcher for this table.
     */
    public function __construct(string $tableName, DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->setColumnAlias('published', 'state');

        parent::__construct($tableName, 'id', $db, $dispatcher);
    }

    /**
     * Basic checks shared by all tables.
     *
     * @return  boolean
     */
    public function check()
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Stores the row, filling the created/modified columns.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean
     */
    public function store($updateNulls = true)
    {
        $date   = Factory::getDate()->toSql();
        $userId = (int) Factory::getApplication()->getIdentity()?->id;

        if (!(int) $this->id) {
            if (empty($this->created)) {
                $this->created = $date;
            }

            if (empty($this->created_by)) {
                $this->created_by = $userId;
            }
        }

        $this->modified    = $date;
        $this->modified_by = $userId;

        return parent::store($updateNulls);
    }

    /**
     * Checks that a column value is not used by another row.
     *
     * @param   string  $column  Column name.
     * @param   string  $value   Value that must be unique.
     *
     * @return  boolean  True when the value is free.
     */
    protected function isUnique(string $column, string $value): bool
    {
        $db    = $this->_db;
        $id    = (int) $this->id;
        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName($this->_tbl))
            ->where($db->quoteName($column) . ' = :value')
            ->where($db->quoteName('id') . ' != :id')
            ->bind(':value', $value)
            ->bind(':id', $id, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult() === 0;
    }

    /**
     * Loads one column of the row as currently stored in the database.
     *
     * @param   string  $column  Column name.
     *
     * @return  mixed  Null when the row does not exist yet.
     */
    protected function getStoredValue(string $column)
    {
        $id = (int) $this->id;

        if (!$id) {
            return null;
        }

        $db    = $this->_db;
        $query = $db->createQuery()
            ->select($db->quoteName($column))
            ->from($db->quoteName($this->_tbl))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return $db->setQuery($query)->loadResult();
    }

    /**
     * Sets a translated error and returns false, for use in check().
     *
     * @param   string  $key  Language key.
     *
     * @return  false
     */
    protected function fail(string $key): bool
    {
        $this->setError(Text::_($key));

        return false;
    }
}
