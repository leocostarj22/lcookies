<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Lcsilva\Component\Lcookies\Administrator\View\Service\HtmlView $this */

$this->getDocument()->getWebAssetManager()
    ->useScript('keepalive')
    ->useScript('form.validate');
?>
<form action="<?php echo Route::_('index.php?option=com_lcookies&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="service-form" class="form-validate"
    aria-label="<?php echo Text::_('COM_LCOOKIES_SERVICE_' . ((int) $this->item->id === 0 ? 'NEW' : 'EDIT'), true); ?>">

    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_LCOOKIES_TAB_DETAILS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <?php echo $this->form->renderField('category_id'); ?>
                <?php echo $this->form->renderField('provider'); ?>
                <?php echo $this->form->renderField('privacy_url'); ?>
                <?php echo $this->form->renderField('description'); ?>
            </div>
            <div class="col-lg-3">
                <?php echo $this->form->renderField('state'); ?>
                <?php echo $this->form->renderField('id'); ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'blocking', Text::_('COM_LCOOKIES_FIELDSET_BLOCKING')); ?>
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_LCOOKIES_FIELDSET_BLOCKING'); ?></legend>
            <p><?php echo Text::_('COM_LCOOKIES_FIELDSET_BLOCKING_DESC'); ?></p>
            <?php echo $this->form->renderFieldset('blocking'); ?>
        </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'code', Text::_('COM_LCOOKIES_FIELDSET_CODE')); ?>
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_LCOOKIES_FIELDSET_CODE'); ?></legend>
            <p><?php echo Text::_('COM_LCOOKIES_FIELDSET_CODE_DESC'); ?></p>
            <?php echo $this->form->renderFieldset('code'); ?>
        </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
