<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Controller;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Edit controller for a single service.
 */
class ServiceController extends FormController
{
    /**
     * @var    string
     */
    protected $view_list = 'services';

    /**
     * Moves the selected services to another category.
     *
     * @param   ?\Joomla\CMS\MVC\Model\BaseDatabaseModel  $model  The model.
     *
     * @return  boolean
     */
    public function batch($model = null)
    {
        $this->checkToken();

        $this->setRedirect(Route::_('index.php?option=com_lcookies&view=services' . $this->getRedirectToListAppend(), false));

        return parent::batch($this->getModel('Service', 'Administrator', []));
    }
}
