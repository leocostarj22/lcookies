<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\View\Services;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Lcsilva\Component\Lcookies\Administrator\View\AbstractListView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Services list.
 */
class HtmlView extends AbstractListView
{
    /**
     * @var  string
     */
    protected $itemName = 'service';

    /**
     * @var  string[]
     */
    protected $title = ['COM_LCOOKIES_SERVICES_TITLE', 'shield-alt'];

    /**
     * Form of the batch dialog.
     *
     * @var  \Joomla\CMS\Form\Form|null
     */
    public $batchForm;

    /**
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        /** @var \Lcsilva\Component\Lcookies\Administrator\Model\ServicesModel $model */
        $model           = $this->getModel();
        $this->batchForm = $this->canBatch() ? $model->getBatchForm() : null;

        parent::display($tpl);
    }

    /**
     * Moving services needs to edit them.
     *
     * @return  boolean
     */
    public function canBatch(): bool
    {
        return $this->getCurrentUser()->authorise('core.edit', 'com_lcookies');
    }

    /**
     * Batch (move to another category) and the link to the service library.
     *
     * @param   \Joomla\CMS\Toolbar\Toolbar  $toolbar  The toolbar.
     *
     * @return  void
     */
    protected function addViewButtons($toolbar): void
    {
        if ($this->canBatch()) {
            $toolbar->popupButton('batch', 'JTOOLBAR_BATCH')
                ->popupType('inline')
                ->textHeader(Text::_('COM_LCOOKIES_BATCH_SERVICES'))
                ->url('#joomla-dialog-batch')
                ->modalWidth('600px')
                ->modalHeight('fit-content')
                ->listCheck(true);
        }

        $toolbar->linkButton('puzzle-piece', 'COM_LCOOKIES_PRESETS_BUTTON')
            ->url(Route::_('index.php?option=com_lcookies&view=presets', false))
            ->icon('icon-puzzle-piece');
    }
}
