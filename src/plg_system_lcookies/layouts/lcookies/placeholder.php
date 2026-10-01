<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  plg_system_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Placeholder shown instead of a blocked iframe. Rendered once with the tokens {id}, {service},
 * {category} and {categoryAlias}, which the server and the JavaScript replace (already escaped)
 * for each iframe. Only phrasing content (span, button), so it is valid inside a paragraph.
 *
 * Override: templates/<template>/html/layouts/lcookies/placeholder.php
 *
 * @var  \Joomla\CMS\Layout\FileLayout  $this
 * @var  array                          $displayData  ['contract' => array] (docs/contract.schema.json)
 */

$contract = $displayData['contract'];
$texts    = $contract['texts'];
?>
<span class="lcookies-placeholder" data-lcookies-for="{id}" data-lcookies-theme="<?php echo $this->escape($contract['theme']); ?>">
    <span class="lcookies-placeholder__text"><?php echo $this->escape($texts['placeholder']); ?></span>
    <span class="lcookies-placeholder__actions">
        <button type="button" class="lcookies-btn lcookies-btn--primary" data-lcookies-allow="{categoryAlias}"><?php echo $this->escape($texts['placeholderAllow']); ?></button>
        <button type="button" class="lcookies-btn lcookies-btn--secondary" data-lcookies-action="settings" aria-haspopup="dialog"><?php echo $this->escape($texts['settings']); ?></button>
    </span>
</span>
