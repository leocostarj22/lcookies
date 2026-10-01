<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Table;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Cookie category table.
 */
class CategoryTable extends AbstractLcookiesTable
{
    /**
     * Array fields stored as JSON.
     *
     * @var    array
     */
    protected $_jsonEncode = ['gcm_types'];

    /**
     * @param   DatabaseInterface     $db          Database connector object.
     * @param   ?DispatcherInterface  $dispatcher  Event dispatcher for this table.
     */
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__lcookies_categories', $db, $dispatcher);
    }

    /**
     * Validates the row before storing it.
     *
     * @return  boolean
     */
    public function check()
    {
        if (!parent::check()) {
            return false;
        }

        $this->title = trim((string) $this->title);

        if ($this->title === '') {
            return $this->fail('COM_LCOOKIES_ERROR_TITLE_REQUIRED');
        }

        // The core flag is never taken from user input. Core categories are referenced by the
        // frontend, so their alias, required flag and published state are fixed.
        $this->core = (int) $this->getStoredValue('core');

        if ($this->core === 1) {
            $this->alias = (string) $this->getStoredValue('alias');
            $this->state = 1;
        } else {
            $alias       = trim((string) $this->alias);
            $this->alias = ApplicationHelper::stringURLSafe($alias !== '' ? $alias : $this->title);
        }

        if ($this->alias === '') {
            return $this->fail('COM_LCOOKIES_ERROR_ALIAS_REQUIRED');
        }

        if (!$this->isUnique('alias', $this->alias)) {
            return $this->fail('COM_LCOOKIES_ERROR_ALIAS_EXISTS');
        }

        if ($this->alias === 'necessary') {
            $this->required = 1;
        } elseif ($this->alias === 'unclassified') {
            $this->required = 0;
        }

        $this->required = (int) (bool) $this->required;

        // Keep only known Consent Mode types.
        $types = \is_string($this->gcm_types) ? json_decode($this->gcm_types, true) : $this->gcm_types;
        $types = array_values(array_intersect((array) $types, LcookiesHelper::GCM_TYPES));

        $this->gcm_types = json_encode($types);

        return true;
    }
}
