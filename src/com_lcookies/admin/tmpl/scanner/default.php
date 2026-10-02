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
use Joomla\CMS\Session\Session;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

/** @var \Lcsilva\Component\Lcookies\Administrator\View\Scanner\HtmlView $this */

$scan    = $this->scan;
$results = $scan['results'] ?? [];
$pages   = $results['pages'] ?? [];
$token   = Session::getFormToken();
$types   = ['cookie' => 'COM_LCOOKIES_TYPE_COOKIE', 'local' => 'COM_LCOOKIES_TYPE_LOCAL', 'session' => 'COM_LCOOKIES_TYPE_SESSION'];

// Pages where something was found: the first one as a link, then "+N".
$where = function (array $indexes) use ($pages): string {
    $first = $pages[$indexes[0] ?? -1] ?? '';

    if ($first === '') {
        return '';
    }

    $path = parse_url($first, PHP_URL_PATH) ?: '/';
    $html = '<a href="' . $this->escape($first) . '" target="_blank" rel="noopener">' . $this->escape(urldecode($path)) . '</a>';

    return \count($indexes) > 1 ? $html . ' ' . Text::sprintf('COM_LCOOKIES_SCAN_MORE_PAGES', \count($indexes) - 1) : $html;
};

$suggestion = function (array $entry, string $actions = '') use ($token): string {
    if ($entry['preset'] ?? null) {
        $html = Text::sprintf('COM_LCOOKIES_SCAN_PRESET', $this->escape(LcookiesHelper::text($entry['preset']['title'])));

        if ($this->canCreate) {
            $html .= ' <form method="post" class="d-inline" action="' . Route::_('index.php?option=com_lcookies') . '">'
                . '<input type="hidden" name="task" value="transfer.preset">'
                . '<input type="hidden" name="preset" value="' . $this->escape($entry['preset']['alias']) . '">'
                . '<input type="hidden" name="return" value="scanner">'
                . '<input type="hidden" name="' . $token . '" value="1">'
                . '<button type="submit" class="btn btn-sm btn-outline-primary ms-1">' . Text::_('COM_LCOOKIES_SCAN_ADD_PRESET') . '</button>'
                . '</form>';
        }

        return $html;
    }

    return $actions;
};
?>
<div id="lcookies-scanner">
    <section class="card mb-4" aria-labelledby="lcookies-scan-start">
        <div class="card-body">
            <h2 id="lcookies-scan-start" class="h4"><?php echo Text::_('COM_LCOOKIES_SCAN_HEADING'); ?></h2>
            <p><?php echo Text::_('COM_LCOOKIES_SCAN_DESC'); ?></p>

            <?php if ($this->canScan) : ?>
                <button type="button" class="btn btn-primary" data-lcookies-scan-start>
                    <span class="icon-search" aria-hidden="true"></span>
                    <?php echo Text::_('COM_LCOOKIES_SCAN_START'); ?>
                </button>
                <div class="mt-3" data-lcookies-scan-progress hidden>
                    <progress class="w-100" max="100" value="0" aria-labelledby="lcookies-scan-status"></progress>
                    <p id="lcookies-scan-status" class="small mb-0" role="status" aria-live="polite"></p>
                </div>
                <div class="visually-hidden" data-lcookies-scan-frames aria-hidden="true"></div>
            <?php else : ?>
                <p class="mb-0 text-muted"><?php echo Text::_('COM_LCOOKIES_SCAN_NOT_ALLOWED'); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($scan === null || $scan['status'] !== 'done') : ?>
        <p data-lcookies-scan-none><?php echo Text::_('COM_LCOOKIES_SCAN_NONE'); ?></p>
    <?php else : ?>
        <section class="mb-4" aria-labelledby="lcookies-scan-results" data-lcookies-scan-results="<?php echo (int) $scan['id']; ?>">
            <h2 id="lcookies-scan-results" class="h4">
                <?php echo Text::sprintf('COM_LCOOKIES_SCAN_RESULTS', HTMLHelper::_('date', $scan['finished'], Text::_('DATE_FORMAT_LC2'))); ?>
            </h2>
            <p>
                <?php echo Text::plural('COM_LCOOKIES_SCAN_N_PAGES', \count($pages)); ?>
                <?php if (empty($results['browser'])) : ?>
                    <?php echo Text::_('COM_LCOOKIES_SCAN_SERVER_ONLY'); ?>
                <?php endif; ?>
            </p>

            <?php if ((int) $scan['issues'] > 0) : ?>
                <div class="alert alert-danger" data-lcookies-scan-issues="<?php echo (int) $scan['issues']; ?>">
                    <?php echo Text::plural('COM_LCOOKIES_SCAN_N_ISSUES', (int) $scan['issues']); ?>
                </div>
            <?php endif; ?>
            <?php if ((int) $scan['unknown'] > 0) : ?>
                <div class="alert alert-warning" data-lcookies-scan-unknown="<?php echo (int) $scan['unknown']; ?>">
                    <?php echo Text::plural('COM_LCOOKIES_SCAN_N_UNKNOWN', (int) $scan['unknown']); ?>
                </div>
            <?php endif; ?>
            <?php if (!(int) $scan['issues'] && !(int) $scan['unknown']) : ?>
                <div class="alert alert-success"><?php echo Text::_('COM_LCOOKIES_SCAN_ALL_GOOD'); ?></div>
            <?php endif; ?>

            <h3 class="h5"><?php echo Text::_('COM_LCOOKIES_SCAN_ITEMS'); ?></h3>
            <?php if (empty($results['items'])) : ?>
                <p><?php echo Text::_('COM_LCOOKIES_SCAN_NO_ITEMS'); ?></p>
            <?php else : ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="lcookies-scan-items">
                        <caption class="visually-hidden"><?php echo Text::_('COM_LCOOKIES_SCAN_ITEMS'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_LCOOKIES_HEADING_NAME'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_LCOOKIES_HEADING_TYPE'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_LCOOKIES_SCAN_HEADING_STATUS'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_LCOOKIES_SCAN_HEADING_PAGES'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['items'] as $item) :
                                $declared = $item['declared'];
                                $adopt    = $this->canCreate && $declared === null
                                    ? '<a class="btn btn-sm btn-outline-secondary" href="' . Route::_('index.php?option=com_lcookies&task=scan.adopt&type=' . rawurlencode($item['type'])
                                        . '&name=' . rawurlencode($item['name']) . '&duration=' . (int) ($item['duration'] ?? 0) . '&' . $token . '=1') . '">'
                                        . Text::_('COM_LCOOKIES_SCAN_ADOPT') . '</a>'
                                    : ''; ?>
                                <tr data-lcookies-scan-item="<?php echo $this->escape($item['type'] . ':' . $item['name']); ?>">
                                    <th scope="row"><code class="text-reset"><?php echo $this->escape($item['name']); ?></code></th>
                                    <td><?php echo Text::_($types[$item['type']] ?? 'COM_LCOOKIES_TYPE_COOKIE'); ?></td>
                                    <td>
                                        <?php if ($item['issue']) : ?>
                                            <span class="badge bg-danger"><?php echo Text::_('COM_LCOOKIES_SCAN_BEFORE_CONSENT'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($declared !== null) : ?>
                                            <span class="badge bg-success"><?php echo Text::_('COM_LCOOKIES_SCAN_DECLARED'); ?></span>
                                            <a href="<?php echo Route::_('index.php?option=com_lcookies&task=cookie.edit&id=' . (int) $declared['id']); ?>">
                                                <?php echo $this->escape(LcookiesHelper::text($declared['service'])); ?></a>
                                            (<?php echo $this->escape(LcookiesHelper::text($declared['category_title'])); ?>)
                                        <?php else : ?>
                                            <span class="badge bg-warning text-dark"><?php echo Text::_('COM_LCOOKIES_SCAN_UNKNOWN'); ?></span>
                                            <?php echo $suggestion($item, $adopt); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $where($item['pages']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($results['requests'])) : ?>
                <h3 class="h5"><?php echo Text::_('COM_LCOOKIES_SCAN_REQUESTS'); ?></h3>
                <p class="small text-muted"><?php echo Text::_('COM_LCOOKIES_SCAN_REQUESTS_DESC'); ?></p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="lcookies-scan-requests">
                        <caption class="visually-hidden"><?php echo Text::_('COM_LCOOKIES_SCAN_REQUESTS'); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('COM_LCOOKIES_SCAN_HEADING_HOST'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_LCOOKIES_SCAN_HEADING_STATUS'); ?></th>
                                <th scope="col"><?php echo Text::_('COM_LCOOKIES_SCAN_HEADING_PAGES'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['requests'] as $request) :
                                $declared = $request['declared']; ?>
                                <tr data-lcookies-scan-request="<?php echo $this->escape($request['host']); ?>">
                                    <th scope="row"><code class="text-reset"><?php echo $this->escape($request['host']); ?></code></th>
                                    <td>
                                        <?php if ($request['issue']) : ?>
                                            <span class="badge bg-danger"><?php echo Text::_('COM_LCOOKIES_SCAN_NOT_BLOCKED'); ?></span>
                                        <?php endif; ?>
                                        <?php if ($declared !== null) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_lcookies&task=service.edit&id=' . (int) $declared['service_id']); ?>">
                                                <?php echo $this->escape(LcookiesHelper::text($declared['service'])); ?></a>
                                            (<?php echo $this->escape(LcookiesHelper::text($declared['category_title'])); ?>)
                                        <?php else : ?>
                                            <?php echo $suggestion($request, Text::_('COM_LCOOKIES_SCAN_NO_SERVICE')); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $where($request['pages']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($results['errors'])) : ?>
                <h3 class="h5"><?php echo Text::_('COM_LCOOKIES_SCAN_ERRORS'); ?></h3>
                <ul>
                    <?php foreach ($results['errors'] as $error) : ?>
                        <li>
                            <?php echo $this->escape($pages[$error['page']] ?? ''); ?>:
                            <?php echo $error['status'] ? (int) $error['status'] : Text::_('COM_LCOOKIES_SCAN_UNREACHABLE'); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (\count($this->history) > 1) : ?>
        <section aria-labelledby="lcookies-scan-history">
            <h2 id="lcookies-scan-history" class="h4"><?php echo Text::_('COM_LCOOKIES_SCAN_HISTORY'); ?></h2>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('JDATE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_LCOOKIES_SCAN_HEADING_SOURCE'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_LCOOKIES_SCAN_HEADING_PAGES'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_LCOOKIES_SCAN_UNKNOWN'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_LCOOKIES_SCAN_HEADING_ISSUES'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->history as $row) : ?>
                            <tr>
                                <td>
                                    <?php if ($row->status === 'done') : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_lcookies&view=scanner&id=' . (int) $row->id); ?>">
                                            <?php echo HTMLHelper::_('date', $row->created, Text::_('DATE_FORMAT_LC5')); ?></a>
                                    <?php else : ?>
                                        <?php echo HTMLHelper::_('date', $row->created, Text::_('DATE_FORMAT_LC5')); ?>
                                        (<?php echo Text::_('COM_LCOOKIES_SCAN_STATUS_' . strtoupper($row->status)); ?>)
                                    <?php endif; ?>
                                </td>
                                <td><?php echo Text::_('COM_LCOOKIES_SCAN_SOURCE_' . strtoupper($row->source)); ?></td>
                                <td><?php echo (int) $row->pages; ?></td>
                                <td><?php echo $row->status === 'done' ? (int) $row->unknown : '–'; ?></td>
                                <td><?php echo $row->status === 'done' ? (int) $row->issues : '–'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
</div>
