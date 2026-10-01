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
use Lcsilva\Component\Lcookies\Administrator\Model\DashboardModel;

/** @var \Lcsilva\Component\Lcookies\Administrator\View\Dashboard\HtmlView $this */

$summary = $this->summary;
$stats   = $this->stats;
$percent = static fn (int $part, int $total): int => $total ? (int) round($part * 100 / $total) : 0;
$actions = [
    'accept_all' => ['COM_LCOOKIES_CONSENT_ACTION_ACCEPT_ALL', 'bg-success'],
    'reject_all' => ['COM_LCOOKIES_CONSENT_ACTION_REJECT_ALL', 'bg-danger'],
    'custom'     => ['COM_LCOOKIES_CONSENT_ACTION_CUSTOM', 'bg-info'],
    'allow'      => ['COM_LCOOKIES_CONSENT_ACTION_ALLOW', 'bg-secondary'],
];
$cards = [
    ['COM_LCOOKIES_HEADING_CATEGORIES', $summary['categories'], 'categories'],
    ['COM_LCOOKIES_HEADING_SERVICES', $summary['services'], 'services'],
    ['COM_LCOOKIES_HEADING_COOKIES', $summary['cookies'], 'cookies'],
];
?>
<div id="lcookies-dashboard">
    <?php if ($this->alerts) : ?>
        <section class="mb-4" aria-labelledby="lcookies-alerts">
            <h2 id="lcookies-alerts" class="h4"><?php echo Text::_('COM_LCOOKIES_DASHBOARD_ALERTS'); ?></h2>
            <?php foreach ($this->alerts as [$type, $key, $argument, $link]) : ?>
                <div class="alert alert-<?php echo $type; ?>" data-lcookies-alert="<?php echo strtolower(substr($key, \strlen('COM_LCOOKIES_ALERT_'))); ?>">
                    <?php echo $argument !== '' ? Text::sprintf($key, $argument) : Text::_($key); ?>
                    <a href="<?php echo Route::_($link); ?>" class="alert-link ms-1"><?php echo Text::_('COM_LCOOKIES_DASHBOARD_FIX'); ?></a>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($cards as [$label, $count, $view]) : ?>
            <div class="col-sm-6 col-lg-3 mb-3">
                <a class="card h-100 text-decoration-none" href="<?php echo Route::_('index.php?option=com_lcookies&view=' . $view); ?>">
                    <span class="card-body">
                        <span class="d-block display-6"><?php echo (int) $count; ?></span>
                        <span class="d-block"><?php echo Text::_($label); ?></span>
                    </span>
                </a>
            </div>
        <?php endforeach; ?>
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <span class="d-block display-6"><?php echo (int) $summary['policyVersion']; ?></span>
                    <span class="d-block"><?php echo Text::_('COM_LCOOKIES_HEADING_POLICY_VERSION'); ?></span>
                    <span class="d-block small text-muted">
                        <?php echo Text::_('COM_LCOOKIES_DASHBOARD_CONSENT_MODE'); ?>:
                        <?php echo Text::_($summary['consentMode'] ? 'JYES' : 'JNO'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($stats !== null) : ?>
        <section class="card mb-4" aria-labelledby="lcookies-stats">
            <div class="card-body">
                <h2 id="lcookies-stats" class="h4"><?php echo Text::sprintf('COM_LCOOKIES_DASHBOARD_STATS', DashboardModel::DAYS); ?></h2>

                <?php if (!$stats['total']) : ?>
                    <p class="mb-0"><?php echo Text::_('COM_LCOOKIES_DASHBOARD_NO_RECORDS'); ?></p>
                <?php else : ?>
                    <p>
                        <?php echo Text::sprintf('COM_LCOOKIES_DASHBOARD_TOTALS', $stats['total'], $stats['consents']); ?>
                        <a href="<?php echo Route::_('index.php?option=com_lcookies&view=consents'); ?>"><?php echo Text::_('COM_LCOOKIES_MENU_CONSENTS_LINK'); ?></a>
                    </p>

                    <div class="row">
                        <div class="col-lg-6">
                            <h3 class="h5"><?php echo Text::_('COM_LCOOKIES_HEADING_ACTION'); ?></h3>
                            <?php foreach ($actions as $action => [$label, $class]) :
                                $value = $percent($stats['actions'][$action], $stats['total']); ?>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between small">
                                        <span><?php echo Text::_($label); ?></span>
                                        <span><?php echo (int) $stats['actions'][$action]; ?> (<?php echo $value; ?>%)</span>
                                    </div>
                                    <div class="progress" role="progressbar" aria-label="<?php echo $this->escape(Text::_($label)); ?>"
                                        aria-valuenow="<?php echo $value; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar <?php echo $class; ?>" style="width: <?php echo $value; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-lg-6">
                            <h3 class="h5"><?php echo Text::_('COM_LCOOKIES_DASHBOARD_ACCEPTANCE'); ?></h3>
                            <?php foreach ($stats['categories'] as $alias => $category) :
                                $value = $percent($category['count'], $stats['total']); ?>
                                <div class="mb-2" data-lcookies-rate="<?php echo $this->escape($alias); ?>">
                                    <div class="d-flex justify-content-between small">
                                        <span><?php echo $this->escape($category['title']); ?></span>
                                        <span><?php echo $value; ?>%</span>
                                    </div>
                                    <div class="progress" role="progressbar" aria-label="<?php echo $this->escape($category['title']); ?>"
                                        aria-valuenow="<?php echo $value; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar" style="width: <?php echo $value; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <p class="small text-muted"><?php echo Text::_('COM_LCOOKIES_DASHBOARD_ACCEPTANCE_DESC'); ?></p>
                        </div>
                    </div>

                    <h3 class="h5 mt-3"><?php echo Text::_('COM_LCOOKIES_DASHBOARD_PER_DAY'); ?></h3>
                    <?php $max = max(1, ...array_values($stats['days'])); ?>
                    <div class="d-flex align-items-end gap-1 border-bottom" style="height: 8rem" aria-hidden="true">
                        <?php foreach ($stats['days'] as $day => $count) : ?>
                            <div class="flex-fill bg-primary" style="height: <?php echo max($count ? 2 : 0, (int) round($count * 100 / $max)); ?>%"
                                title="<?php echo $this->escape(HTMLHelper::_('date', $day, Text::_('DATE_FORMAT_LC4'), null) . ': ' . $count); ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <table class="visually-hidden">
                        <caption><?php echo Text::_('COM_LCOOKIES_DASHBOARD_PER_DAY'); ?></caption>
                        <?php foreach ($stats['days'] as $day => $count) : ?>
                            <tr><th scope="row"><?php echo HTMLHelper::_('date', $day, Text::_('DATE_FORMAT_LC4'), null); ?></th><td><?php echo (int) $count; ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                    <p class="small text-muted mt-1"><?php echo Text::_('COM_LCOOKIES_DASHBOARD_UTC'); ?></p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
