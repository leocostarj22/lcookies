<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\View\Dashboard;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dashboard of the component.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var  array
     */
    protected $summary = [];

    /**
     * Consent statistics, null without the permission to view consent records.
     *
     * @var  ?array
     */
    protected $stats;

    /**
     * @var  array
     */
    protected $alerts = [];

    /**
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        /** @var \Lcsilva\Component\Lcookies\Administrator\Model\DashboardModel $model */
        $model = $this->getModel();

        $this->summary = $model->getSummary();
        $this->alerts  = $model->getAlerts();

        if ($this->getCurrentUser()->authorise('lcookies.consents.view', 'com_lcookies')) {
            $this->stats = $model->getStats();
        }

        $user = $this->getCurrentUser();

        ToolbarHelper::title(Text::_('COM_LCOOKIES_DASHBOARD_TITLE'), 'shield-alt');

        if ($user->authorise('core.admin', 'com_lcookies') || $user->authorise('core.options', 'com_lcookies')) {
            $this->getDocument()->getToolbar()->preferences('com_lcookies');
        }

        parent::display($tpl);
    }
}
