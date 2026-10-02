<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\View\Presets;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Service library (presets), import and export.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Presets of the library, each with `added`.
     *
     * @var  array
     */
    protected $presets = [];

    /**
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        /** @var \Lcsilva\Component\Lcookies\Administrator\Model\TransferModel $model */
        $model         = $this->getModel();
        $this->presets = $model->getPresets();

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * @return  void
     */
    protected function addToolbar(): void
    {
        $user    = $this->getCurrentUser();
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_LCOOKIES_PRESETS_TITLE'), 'puzzle-piece');

        $toolbar->linkButton('arrow-left', 'JTOOLBAR_BACK')
            ->url(Route::_('index.php?option=com_lcookies&view=services', false))
            ->icon('icon-arrow-left');

        $toolbar->linkButton('download', 'COM_LCOOKIES_TRANSFER_EXPORT')
            ->url(Route::_('index.php?option=com_lcookies&task=transfer.export&' . Session::getFormToken() . '=1', false))
            ->icon('icon-download');

        if ($user->authorise('core.admin', 'com_lcookies') || $user->authorise('core.options', 'com_lcookies')) {
            $toolbar->preferences('com_lcookies');
        }
    }
}
