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
use Joomla\CMS\Router\Route;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

/** @var \Lcsilva\Component\Lcookies\Administrator\View\Presets\HtmlView $this */

$user      = $this->getCurrentUser();
$canAdd    = $user->authorise('core.create', 'com_lcookies');
$canImport = $canAdd && $user->authorise('core.edit', 'com_lcookies');
$action    = Route::_('index.php?option=com_lcookies&view=presets');
$category  = [
    'necessary'   => 'COM_LCOOKIES_CAT_NECESSARY',
    'preferences' => 'COM_LCOOKIES_CAT_PREFERENCES',
    'statistics'  => 'COM_LCOOKIES_CAT_STATISTICS',
    'marketing'   => 'COM_LCOOKIES_CAT_MARKETING',
];
?>
<div id="lcookies-presets" class="row">
    <div class="col-lg-8">
        <h2 class="h3"><?php echo Text::_('COM_LCOOKIES_PRESETS_LIBRARY'); ?></h2>
        <p><?php echo Text::_('COM_LCOOKIES_PRESETS_INTRO'); ?></p>

        <table class="table" id="presetList">
            <caption class="visually-hidden"><?php echo Text::_('COM_LCOOKIES_PRESETS_LIBRARY'); ?></caption>
            <thead>
                <tr>
                    <th scope="col"><?php echo Text::_('COM_LCOOKIES_HEADING_SERVICE'); ?></th>
                    <th scope="col" class="w-20 d-none d-md-table-cell"><?php echo Text::_('COM_LCOOKIES_HEADING_CATEGORY'); ?></th>
                    <th scope="col" class="w-10 text-center d-none d-md-table-cell"><?php echo Text::_('COM_LCOOKIES_HEADING_COOKIES'); ?></th>
                    <th scope="col" class="w-15 text-end"><span class="visually-hidden"><?php echo Text::_('JACTION_CREATE'); ?></span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->presets as $preset) : ?>
                    <tr>
                        <th scope="row">
                            <?php echo $this->escape($preset['title']); ?>
                            <div class="small"><?php echo $this->escape(LcookiesHelper::text($preset['description'])); ?></div>
                            <?php if ($preset['provider']) : ?>
                                <div class="small"><?php echo $this->escape($preset['provider']); ?></div>
                            <?php endif; ?>
                        </th>
                        <td class="d-none d-md-table-cell">
                            <?php echo $this->escape(Text::_($category[$preset['category']] ?? $preset['category'])); ?>
                        </td>
                        <td class="text-center d-none d-md-table-cell">
                            <?php echo \count($preset['cookies']); ?>
                        </td>
                        <td class="text-end">
                            <?php if ($preset['added']) : ?>
                                <span class="badge bg-success"><?php echo Text::_('COM_LCOOKIES_PRESETS_ADDED'); ?></span>
                            <?php elseif ($canAdd) : ?>
                                <form action="<?php echo $action; ?>" method="post" class="d-inline">
                                    <button type="submit" class="btn btn-sm btn-primary" name="preset" value="<?php echo $this->escape($preset['alias']); ?>"
                                        aria-label="<?php echo $this->escape(Text::sprintf('COM_LCOOKIES_PRESETS_ADD_SERVICE', $preset['title'])); ?>">
                                        <span class="icon-plus" aria-hidden="true"></span> <?php echo Text::_('COM_LCOOKIES_PRESETS_ADD'); ?>
                                    </button>
                                    <input type="hidden" name="task" value="transfer.preset">
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="col-lg-4">
        <?php if ($canImport) : ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="card-title h4"><?php echo Text::_('COM_LCOOKIES_TRANSFER_IMPORT'); ?></h2>
                    <p class="small"><?php echo Text::_('COM_LCOOKIES_TRANSFER_IMPORT_DESC'); ?></p>
                    <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="lcookies-import">
                        <div class="mb-3">
                            <label for="import_file" class="form-label"><?php echo Text::_('COM_LCOOKIES_TRANSFER_FILE'); ?></label>
                            <input type="file" class="form-control" id="import_file" name="import_file" accept=".json,application/json" required>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="overwrite" name="overwrite" value="1">
                            <label class="form-check-label" for="overwrite"><?php echo Text::_('COM_LCOOKIES_TRANSFER_OVERWRITE'); ?></label>
                            <div class="small"><?php echo Text::_('COM_LCOOKIES_TRANSFER_OVERWRITE_DESC'); ?></div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <span class="icon-upload" aria-hidden="true"></span> <?php echo Text::_('COM_LCOOKIES_TRANSFER_IMPORT'); ?>
                        </button>
                        <input type="hidden" name="task" value="transfer.import">
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-body">
                <h2 class="card-title h4"><?php echo Text::_('COM_LCOOKIES_TRANSFER_EXPORT'); ?></h2>
                <p class="small mb-0"><?php echo Text::_('COM_LCOOKIES_TRANSFER_EXPORT_DESC'); ?></p>
            </div>
        </div>
    </div>
</div>
