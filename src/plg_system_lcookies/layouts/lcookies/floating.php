<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  plg_system_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Floating button that reopens the preferences after the visitor has chosen.
 *
 * Override: templates/<template>/html/layouts/lcookies/floating.php
 *
 * @var  \Joomla\CMS\Layout\FileLayout  $this
 * @var  array                          $displayData  ['contract' => array] (docs/contract.schema.json)
 */

$contract = $displayData['contract'];

if (!$contract['floatingButton']) {
    return;
}

$label = $this->escape($contract['texts']['floating']);
?>
<button type="button" class="lcookies-floating lcookies-floating--<?php echo $this->escape($contract['floatingButton']); ?>" data-lcookies-floating data-lcookies-action="settings" aria-haspopup="dialog" aria-label="<?php echo $label; ?>" title="<?php echo $label; ?>" hidden>
    <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5zm-3.5 7a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm6 6a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm4.5-3a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
</button>
