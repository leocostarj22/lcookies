<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \Lcsilva\Component\Lcookies\Administrator\View\Services\HtmlView $this */
?>
<div class="p-3">
    <p><?php echo Text::_('COM_LCOOKIES_BATCH_SERVICES_TIP'); ?></p>
    <?php echo $this->batchForm->renderField('service_category_id', 'batch'); ?>
</div>
<div class="btn-toolbar p-3">
    <joomla-toolbar-button task="service.batch" class="ms-auto">
        <button type="button" class="btn btn-success"><?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?></button>
    </joomla-toolbar-button>
</div>
