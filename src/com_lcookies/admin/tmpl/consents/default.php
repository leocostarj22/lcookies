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

/** @var \Lcsilva\Component\Lcookies\Administrator\View\Consents\HtmlView $this */

$this->getDocument()->getWebAssetManager()->useScript('table.columns');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$actions   = [
    'accept_all' => ['COM_LCOOKIES_CONSENT_ACTION_ACCEPT_ALL', 'bg-success'],
    'reject_all' => ['COM_LCOOKIES_CONSENT_ACTION_REJECT_ALL', 'bg-danger'],
    'custom'     => ['COM_LCOOKIES_CONSENT_ACTION_CUSTOM', 'bg-info'],
    'allow'      => ['COM_LCOOKIES_CONSENT_ACTION_ALLOW', 'bg-secondary'],
];
?>
<form action="<?php echo Route::_('index.php?option=com_lcookies&view=consents'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php if (!$this->logging) : ?>
                    <div class="alert alert-warning">
                        <span class="icon-exclamation-triangle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('WARNING'); ?></span>
                        <?php echo Text::_('COM_LCOOKIES_CONSENTS_LOGGING_OFF'); ?>
                    </div>
                <?php endif; ?>

                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="consentList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_LCOOKIES_CONSENTS_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col" class="w-15">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_LCOOKIES_HEADING_DATE', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo Text::_('COM_LCOOKIES_HEADING_CONSENT_ID'); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_LCOOKIES_HEADING_ACTION', 'a.action', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-20">
                                    <?php echo Text::_('COM_LCOOKIES_HEADING_ACCEPTED'); ?>
                                </th>
                                <th scope="col" class="w-5 text-center d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_LCOOKIES_HEADING_POLICY_VERSION', 'a.policy_version', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo Text::_('COM_LCOOKIES_HEADING_USER'); ?>
                                </th>
                                <th scope="col" class="w-15 d-none d-lg-table-cell">
                                    <?php echo Text::_('COM_LCOOKIES_HEADING_PAGE'); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-lg-table-cell">
                                    <?php echo Text::_('JGRID_HEADING_LANGUAGE'); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->items as $item) :
                                [$actionText, $actionClass] = $actions[$item->action] ?? [$item->action, 'bg-secondary'];
                                $categories = json_decode((string) $item->categories, true) ?: [];
                                $path       = (string) parse_url((string) $item->url, PHP_URL_PATH);
                                ?>
                                <tr>
                                    <td>
                                        <?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC6')); ?>
                                    </td>
                                    <th scope="row">
                                        <a href="<?php echo Route::_('index.php?option=com_lcookies&view=consents&filter[search]=uuid:' . $this->escape($item->consent_uuid)); ?>"
                                            title="<?php echo $this->escape(Text::_('COM_LCOOKIES_CONSENT_HISTORY')); ?>">
                                            <code><?php echo $this->escape($item->consent_uuid); ?></code></a>
                                        <details class="small">
                                            <summary><?php echo Text::_('COM_LCOOKIES_CONSENT_DETAILS'); ?></summary>
                                            <dl class="row mb-0">
                                                <dt class="col-sm-4"><?php echo Text::_('COM_LCOOKIES_IP_HASH'); ?></dt>
                                                <dd class="col-sm-8 text-break"><code><?php echo $this->escape($item->ip_hash ?: '-'); ?></code></dd>
                                                <dt class="col-sm-4"><?php echo Text::_('COM_LCOOKIES_UA_HASH'); ?></dt>
                                                <dd class="col-sm-8 text-break"><code><?php echo $this->escape($item->ua_hash ?: '-'); ?></code></dd>
                                                <dt class="col-sm-4"><?php echo Text::_('COM_LCOOKIES_HEADING_PAGE'); ?></dt>
                                                <dd class="col-sm-8 text-break"><?php echo $this->escape($item->url); ?></dd>
                                                <dt class="col-sm-4"><?php echo Text::_('JGRID_HEADING_ID'); ?></dt>
                                                <dd class="col-sm-8"><?php echo (int) $item->id; ?></dd>
                                            </dl>
                                        </details>
                                    </th>
                                    <td>
                                        <span class="badge <?php echo $actionClass; ?>"><?php echo Text::_($actionText); ?></span>
                                    </td>
                                    <td>
                                        <?php foreach ($categories as $alias) : ?>
                                            <span class="badge bg-light text-dark border"><?php echo $this->escape($this->categoryTitles[$alias] ?? $alias); ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <?php echo (int) $item->policy_version; ?>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php if ($item->user_id) : ?>
                                            <?php echo $this->escape($item->user_name ?? Text::_('COM_LCOOKIES_USER_DELETED')); ?>
                                            <div class="small"><?php echo Text::_('JGRID_HEADING_ID'); ?>: <?php echo (int) $item->user_id; ?></div>
                                        <?php else : ?>
                                            <span><?php echo Text::_('COM_LCOOKIES_USER_GUEST'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell text-break">
                                        <?php echo $this->escape($path ?: '/'); ?>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?php echo $this->escape($item->language); ?>
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
