<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

/** @var \Lcsilva\Component\Lcookies\Administrator\View\Cookies\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$user      = $this->getCurrentUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder === 'a.ordering';
$canChange = $user->authorise('core.edit.state', 'com_lcookies');
$canEdit   = $user->authorise('core.edit', 'com_lcookies');

if ($saveOrder && !empty($this->items)) {
    $saveOrderingUrl = 'index.php?option=com_lcookies&task=cookies.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}
?>
<form action="<?php echo Route::_('index.php?option=com_lcookies&view=cookies'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="cookieList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_LCOOKIES_COOKIES_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-1 text-center d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', '', 'a.ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-sort'); ?>
                                </th>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_LCOOKIES_HEADING_NAME', 'a.name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-15 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_LCOOKIES_HEADING_SERVICE', 's.title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-15 d-none d-lg-table-cell">
                                    <?php echo Text::_('COM_LCOOKIES_HEADING_CATEGORY'); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo Text::_('COM_LCOOKIES_HEADING_TYPE'); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo Text::_('COM_LCOOKIES_HEADING_DURATION'); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody<?php if ($saveOrder) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" data-nested="true"<?php endif; ?>>
                            <?php foreach ($this->items as $i => $item) :
                                $canCheckin = $user->authorise('core.manage', 'com_checkin') || (int) $item->checked_out === (int) $user->id || \is_null($item->checked_out);
                                ?>
                                <tr class="row<?php echo $i % 2; ?>" data-draggable-group="<?php echo (int) $item->service_id; ?>">
                                    <td class="text-center">
                                        <?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->name); ?>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <?php
                                        $iconClass = '';

                                        if (!$canChange) {
                                            $iconClass = ' inactive';
                                        } elseif (!$saveOrder) {
                                            $iconClass = ' inactive" title="' . Text::_('JORDERINGDISABLED');
                                        }
                                        ?>
                                        <span class="sortable-handler<?php echo $iconClass; ?>">
                                            <span class="icon-ellipsis-v" aria-hidden="true"></span>
                                        </span>
                                        <?php if ($canChange && $saveOrder) : ?>
                                            <input type="text" name="order[]" size="5" value="<?php echo (int) $item->ordering; ?>" class="width-20 text-area-order hidden">
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'cookies.', $canChange, 'cb'); ?>
                                    </td>
                                    <th scope="row">
                                        <?php if ($item->checked_out) : ?>
                                            <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'cookies.', $canCheckin); ?>
                                        <?php endif; ?>
                                        <?php if ($canEdit) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_lcookies&task=cookie.edit&id=' . (int) $item->id); ?>">
                                                <code><?php echo $this->escape($item->name); ?></code></a>
                                        <?php else : ?>
                                            <code><?php echo $this->escape($item->name); ?></code>
                                        <?php endif; ?>
                                        <?php if ($item->match_type !== 'exact') : ?>
                                            <span class="badge bg-secondary"><?php echo Text::_('COM_LCOOKIES_MATCH_' . strtoupper($item->match_type)); ?></span>
                                        <?php endif; ?>
                                        <?php if ($item->source === 'scanner') : ?>
                                            <span class="badge bg-warning text-dark"><?php echo Text::_('COM_LCOOKIES_SOURCE_SCANNER'); ?></span>
                                        <?php elseif (\in_array($item->source, ['preset', 'import'], true)) : ?>
                                            <span class="badge bg-info"><?php echo Text::_('COM_LCOOKIES_SOURCE_' . strtoupper($item->source)); ?></span>
                                        <?php endif; ?>
                                        <?php if ($item->domain) : ?>
                                            <div class="small"><?php echo Text::_('COM_LCOOKIES_FIELD_DOMAIN_LABEL'); ?>: <?php echo $this->escape($item->domain); ?></div>
                                        <?php endif; ?>
                                    </th>
                                    <td class="d-none d-md-table-cell">
                                        <?php echo $this->escape(LcookiesHelper::text($item->service_title)); ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php echo $this->escape(LcookiesHelper::text($item->category_title)); ?>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php echo Text::_('COM_LCOOKIES_TYPE_' . strtoupper($item->type)); ?>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php echo $this->escape(LcookiesHelper::duration((int) $item->duration_value, $item->duration_unit)); ?>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php echo (int) $item->id; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
