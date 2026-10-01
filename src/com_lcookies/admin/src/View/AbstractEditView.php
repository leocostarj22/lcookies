<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\View;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Shared edit view: form, item and the save/cancel toolbar.
 */
abstract class AbstractEditView extends BaseHtmlView
{
    /**
     * @var  \Joomla\CMS\Form\Form
     */
    protected $form;

    /**
     * @var  object
     */
    protected $item;

    /**
     * @var  \Joomla\Registry\Registry
     */
    protected $state;

    /**
     * Toolbar title language key prefix (suffixed with _NEW / _EDIT) and icon.
     *
     * @var  string[]
     */
    protected $title = ['', ''];

    /**
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        $model       = $this->getModel();
        $this->form  = $model->getForm();
        $this->item  = $model->getItem();
        $this->state = $model->getState();

        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * @return  void
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $name       = $this->getName();
        $user       = $this->getCurrentUser();
        $isNew      = empty($this->item->id);
        $checkedOut = !(\is_null($this->item->checked_out) || (int) $this->item->checked_out === (int) $user->id);
        $canDo      = ContentHelper::getActions('com_lcookies');
        $canSave    = !$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'));
        $toolbar    = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_($this->title[0] . ($isNew ? '_NEW' : '_EDIT')), $this->title[1]);

        if ($canSave) {
            $toolbar->apply($name . '.apply');
        }

        $saveGroup = $toolbar->dropdownButton('save-group');
        $saveGroup->configure(
            function (Toolbar $childBar) use ($name, $canSave, $checkedOut, $canDo, $isNew) {
                if ($canSave) {
                    $childBar->save($name . '.save');
                }

                if (!$checkedOut && $canDo->get('core.create')) {
                    $childBar->save2new($name . '.save2new');
                }

                if (!$isNew && $canDo->get('core.create')) {
                    $childBar->save2copy($name . '.save2copy');
                }
            }
        );

        $toolbar->cancel($name . '.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
