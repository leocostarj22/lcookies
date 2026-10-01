<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List controller for cookies: publish, trash, delete, check-in and ordering.
 */
class CookiesController extends AdminController
{
    /**
     * @var    string
     */
    protected $text_prefix = 'COM_LCOOKIES';

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    The model name.
     * @param   string  $prefix  The class prefix.
     * @param   array   $config  The array of possible config values.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
     */
    public function getModel($name = 'Cookie', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
