<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Exception;

use Joomla\CMS\MVC\Controller\Exception\Save;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * None of the requested records can be deleted; the message holds the reasons.
 *
 * Extends the core Save exception so the Web Services API answers with this code (409) and message.
 */
class DeleteRefusedException extends Save
{
    /**
     * @param   string  $message  The reasons.
     */
    public function __construct(string $message)
    {
        parent::__construct($message, 409);
    }
}
