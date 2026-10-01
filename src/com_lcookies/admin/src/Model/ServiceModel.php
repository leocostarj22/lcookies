<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Model;

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
