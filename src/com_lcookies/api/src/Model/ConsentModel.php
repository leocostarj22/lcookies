<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Api\Model;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One consent record (GET /v1/lcookies/consents/:id).
 */
class ConsentModel extends BaseDatabaseModel
{
    /**
     * @param   ?integer  $pk  Record id, default the `consent.id` state.
     *
     * @return  object  The record; its `id` is null when it does not exist.
     */
    public function getItem($pk = null): object
    {
        $pk    = (int) ($pk ?? $this->getState('consent.id'));
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__lcookies_consents'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $pk, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject() ?: (object) ['id' => null];
    }
}
