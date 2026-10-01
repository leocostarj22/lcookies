<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Model;

use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Edit model for a service (cookie provider).
 */
class ServiceModel extends AbstractItemModel
{
    /**
     * @var    string
     */
    public $typeAlias = 'com_lcookies.service';

    /**
     * @var    string
     */
    protected $orderingGroup = 'category_id';

    /**
     * Batch commands: move to another category (`batch[service_category_id]`). The core
     * `category_id` command works with com_categories, so another key is used.
     *
     * @var  array
     */
    protected $batch_commands = ['service_category_id' => 'batchCategory'];

    /**
     * Moves services to another category, at the end of its ordering.
     *
     * @param   mixed  $value     Id of the category.
     * @param   array  $pks       Service ids.
     * @param   array  $contexts  Item contexts.
     *
     * @return  boolean
     */
    protected function batchCategory($value, $pks, $contexts)
    {
        $categoryId = (int) $value;
        $db         = $this->getDatabase();
        $exists     = (int) $db->setQuery(
            $db->createQuery()
                ->select('COUNT(*)')
                ->from($db->quoteName('#__lcookies_categories'))
                ->where($db->quoteName('id') . ' = :id')
                ->where($db->quoteName('state') . ' IN (0, 1)')
                ->bind(':id', $categoryId, ParameterType::INTEGER)
        )->loadResult();

        if (!$exists || !$this->getCurrentUser()->authorise('core.edit', 'com_lcookies')) {
            return false;
        }

        $table = $this->getTable();

        foreach ($pks as $pk) {
            $table->reset();

            if (!$table->load((int) $pk) || (int) $table->category_id === $categoryId) {
                continue;
            }

            $table->category_id = $categoryId;
            $table->ordering    = $table->getNextOrder($db->quoteName('category_id') . ' = ' . $categoryId);

            if (!$table->check() || !$table->store()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deletes the services and the cookies that belong to them.
     *
     * @param   array  &$pks  An array of record primary keys.
     *
     * @return  boolean
     */
    public function delete(&$pks)
    {
        if (!parent::delete($pks)) {
            return false;
        }

        $db       = $this->getDatabase();
        $services = $db->createQuery()
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__lcookies_services'));
        $query    = $db->createQuery()
            ->delete($db->quoteName('#__lcookies_cookies'))
            ->where($db->quoteName('service_id') . ' NOT IN (' . $services . ')');

        $db->setQuery($query)->execute();

        return true;
    }
}
