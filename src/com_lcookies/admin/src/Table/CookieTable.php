<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Table;

use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Cookie (or other browser storage entry) table.
 */
class CookieTable extends AbstractLcookiesTable
{
    /**
     * @param   DatabaseInterface     $db          Database connector object.
     * @param   ?DispatcherInterface  $dispatcher  Event dispatcher for this table.
     */
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__lcookies_cookies', $db, $dispatcher);
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

        $this->name = trim((string) $this->name);

        if ($this->name === '') {
            return $this->fail('COM_LCOOKIES_ERROR_NAME_REQUIRED');
        }

        if (!(int) $this->service_id) {
            return $this->fail('COM_LCOOKIES_ERROR_SERVICE_REQUIRED');
        }

        if (!\in_array($this->match_type, LcookiesHelper::MATCH_TYPES, true)) {
            $this->match_type = 'exact';
        }

        if ($this->match_type === 'regex' && !LcookiesHelper::isValidRegex($this->name)) {
            return $this->fail('COM_LCOOKIES_ERROR_INVALID_REGEX');
        }

        if (!\in_array($this->type, LcookiesHelper::STORAGE_TYPES, true)) {
            $this->type = 'cookie';
        }

        if (!\in_array($this->duration_unit, LcookiesHelper::DURATION_UNITS, true)) {
            $this->duration_unit = 'session';
        }

        $this->duration_value = max(0, (int) $this->duration_value);

        if ($this->duration_unit === 'session') {
            $this->duration_value = 0;
        }

        $this->domain = trim((string) $this->domain);

        if (empty($this->source)) {
            $this->source = 'manual';
        }

        return true;
    }
}
