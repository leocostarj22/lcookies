<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\View\Consents;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Lcsilva\Component\Lcookies\Administrator\Consent\ConsentLog;
use Lcsilva\Component\Lcookies\Administrator\View\AbstractListView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Consent records list (read only).
 */
class HtmlView extends AbstractListView
{
    /**
     * @var  string[]
     */
    protected $title = ['COM_LCOOKIES_CONSENTS_TITLE', 'list'];

    /**
     * Category titles by alias, for the records that store aliases.
     *
     * @var  array<string, string>
     */
    protected $categoryTitles = [];

    /**
     * Whether consents are being recorded (option).
     *
     * @var  boolean
     */
    protected $logging = true;

    /**
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        if (!$this->getCurrentUser()->authorise('lcookies.consents.view', 'com_lcookies')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->logging = (bool) ComponentHelper::getParams('com_lcookies')->get('log_consents', 1);

        /** @var \Lcsilva\Component\Lcookies\Administrator\Model\ConsentsModel $model */
        $model                = $this->getModel();
        $this->categoryTitles = $model->getCategoryTitles();

        parent::display($tpl);
    }

    /**
     * @return  void
     */
    protected function addToolbar(): void
    {
        $user    = $this->getCurrentUser();
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_($this->title[0]), $this->title[1]);

        if ($user->authorise('lcookies.consents.export', 'com_lcookies')) {
            $toolbar->linkButton('download', 'COM_LCOOKIES_CONSENTS_EXPORT')
                ->url(Route::_('index.php?option=com_lcookies&task=consents.export&' . Session::getFormToken() . '=1', false))
                ->icon('icon-download');
        }

        if ($user->authorise('core.delete', 'com_lcookies')) {
            $toolbar->confirmButton('delete', 'COM_LCOOKIES_CONSENTS_PURGE', 'consents.purge')
                ->message(Text::sprintf('COM_LCOOKIES_CONSENTS_PURGE_CONFIRM', ConsentLog::retentionMonths(ComponentHelper::getParams('com_lcookies'))))
                ->icon('icon-trash')
                ->listCheck(false);
        }

        if ($user->authorise('core.admin', 'com_lcookies') || $user->authorise('core.options', 'com_lcookies')) {
            $toolbar->preferences('com_lcookies');
        }
    }
}
