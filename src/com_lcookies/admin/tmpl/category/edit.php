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

/** @var \Lcsilva\Component\Lcookies\Administrator\View\Category\HtmlView $this */

$this->getDocument()->getWebAssetManager()
    ->useScript('keepalive')
    ->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_lcookies&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="category-form" class="form-validate"
    aria-label="<?php echo Text::_('COM_LCOOKIES_CATEGORY_' . ((int) $this->item->id === 0 ? 'NEW' : 'EDIT'), true); ?>">

    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_LCOOKIES_TAB_DETAILS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <?php echo $this->form->renderField('description'); ?>
                <?php echo $this->form->renderField('required'); ?>
                <?php echo $this->form->renderField('gcm_types'); ?>
            </div>
            <div class="col-lg-3">
                <?php echo $this->form->renderField('state'); ?>
                <?php echo $this->form->renderField('id'); ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
