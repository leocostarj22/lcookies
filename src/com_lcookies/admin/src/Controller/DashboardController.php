<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Controller;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Lcsilva\Component\Lcookies\Administrator\Model\DashboardModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Actions of the dashboard.
 */
class DashboardController extends BaseController
{
    /**
     * Publishes a new version of the cookie policy (Options or Admin permission).
     *
     * @return  void
     *
     * @throws  NotAllowed
     */
    public function newPolicy(): void
    {
        $this->checkToken();

        $user = $this->app->getIdentity();

        if (!$user->authorise('core.admin', 'com_lcookies') && !$user->authorise('core.options', 'com_lcookies')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        /** @var DashboardModel $model */
        $model   = $this->getModel('Dashboard', 'Administrator', ['ignore_request' => true]);
        $version = $model->newPolicyVersion();

        $this->setRedirect(
            Route::_('index.php?option=com_lcookies&view=dashboard', false),
            Text::sprintf('COM_LCOOKIES_DASHBOARD_NEW_POLICY_DONE', $version)
        );
    }
}
