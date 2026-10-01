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
 * Consent banner. Hidden until the JavaScript finds that the visitor has not chosen yet.
 *
 * Override: templates/<template>/html/layouts/lcookies/banner.php
 * Keep the data-lcookies-* attributes: the JavaScript relies on them.
 *
 * @var  \Joomla\CMS\Layout\FileLayout  $this
 * @var  array                          $displayData  ['contract' => array] (docs/contract.schema.json)
 */

$contract = $displayData['contract'];
$texts    = $contract['texts'];
$layout   = $contract['layout'];
$modal    = $layout === 'modal';
?>
<?php if ($modal) : ?>
<dialog class="lcookies-banner lcookies-banner--modal" data-lcookies-banner aria-labelledby="lcookies-banner-title" aria-describedby="lcookies-banner-message">
<?php else : ?>
<div class="lcookies-banner lcookies-banner--<?php echo $this->escape($layout); ?>" data-lcookies-banner role="region" aria-labelledby="lcookies-banner-title" hidden>
<?php endif; ?>
    <div class="lcookies-banner__body">
        <h2 class="lcookies-banner__title" id="lcookies-banner-title"><?php echo $this->escape($texts['title']); ?></h2>
        <div class="lcookies-banner__message" id="lcookies-banner-message">
            <?php echo $texts['message']; ?>
            <?php if ($contract['privacyUrl']) : ?>
                <a class="lcookies-link" href="<?php echo $this->escape($contract['privacyUrl']); ?>"><?php echo $this->escape($texts['privacyPolicy']); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="lcookies-banner__actions">
        <button type="button" class="lcookies-btn lcookies-btn--primary" data-lcookies-action="reject"><?php echo $this->escape($texts['rejectAll']); ?></button>
        <button type="button" class="lcookies-btn lcookies-btn--secondary" data-lcookies-action="settings" aria-haspopup="dialog"><?php echo $this->escape($texts['settings']); ?></button>
        <button type="button" class="lcookies-btn lcookies-btn--primary" data-lcookies-action="accept"><?php echo $this->escape($texts['acceptAll']); ?></button>
    </div>
<?php echo $modal ? '</dialog>' : '</div>'; ?>

