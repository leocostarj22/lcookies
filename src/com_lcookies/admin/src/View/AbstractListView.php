<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\View;

use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Shared list view: items, search tools and the standard list toolbar.
 */
abstract class AbstractListView extends BaseHtmlView
{
    /**
     * @var  \Joomla\CMS\Form\Form
     */
    public $filterForm;

    /**
     * @var  array
     */
    public $activeFilters = [];

    /**
     * @var  array
     */
    protected $items = [];

    /**
     * @var  \Joomla\CMS\Pagination\Pagination
     */
    protected $pagination;

    /**
     * @var  \Joomla\Registry\Registry
     */
    protected $state;

    /**
     * Singular item name used in tasks, e.g. `category`.
     *
     * @var  string
     */
    protected $itemName = '';

    /**
     * Toolbar title language key and icon.
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
        $model = $this->getModel();

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

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
        $canDo   = ContentHelper::getActions('com_lcookies');
        $toolbar = $this->getDocument()->getToolbar();
        $list    = $this->getName();
        $trashed = (string) $this->state->get('filter.published') === '-2';

        ToolbarHelper::title(Text::_($this->title[0]), $this->title[1]);

        if ($canDo->get('core.create')) {
            $toolbar->addNew($this->itemName . '.add');
        }

        if ($canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group', 'JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            $childBar->publish($list . '.publish')->listCheck(true);
            $childBar->unpublish($list . '.unpublish')->listCheck(true);
            $childBar->checkin($list . '.checkin')->listCheck(true);

            if (!$trashed) {
                $childBar->trash($list . '.trash')->listCheck(true);
            }
        }

        if ($trashed && $canDo->get('core.delete')) {
            $toolbar->delete($list . '.delete', 'JTOOLBAR_DELETE_FROM_TRASH')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_lcookies');
        }
    }
}
