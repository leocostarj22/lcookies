<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  mod_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Override: templates/<template>/html/mod_lcookies/default.php
 * Keep data-lcookies-open (opens the preferences) and data-mod-lcookies-status with its data-* texts
 * (media/mod_lcookies/js/status.js fills it in; %s is replaced).
 *
 * @var  \Joomla\CMS\Application\SiteApplication  $app
 * @var  \stdClass                                $module
 * @var  \Joomla\Registry\Registry                $params
 * @var  string                                   $buttonText
 * @var  string                                   $buttonClass
 * @var  ?string                                  $policyUrl
 * @var  boolean                                  $showStatus
 */

if ($showStatus) {
    $wa = $app->getDocument()->getWebAssetManager();
    $wa->getRegistry()->addExtensionRegistryFile('mod_lcookies');
    $wa->useScript('mod_lcookies.status');
}

$escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
?>
<div class="mod-lcookies">
    <?php if ($showStatus) : ?>
        <p class="mod-lcookies__status" data-mod-lcookies-status aria-live="polite" hidden
            data-none="<?php echo $escape(Text::_('MOD_LCOOKIES_STATUS_NONE')); ?>"
            data-necessary="<?php echo $escape(Text::_('MOD_LCOOKIES_STATUS_NECESSARY')); ?>"
            data-some="<?php echo $escape(Text::_('MOD_LCOOKIES_STATUS_SOME')); ?>"
            data-date="<?php echo $escape(Text::_('MOD_LCOOKIES_STATUS_DATE')); ?>"></p>
    <?php endif; ?>
    <p class="mod-lcookies__actions">
        <button type="button" class="<?php echo $buttonClass; ?>" data-lcookies-open><?php echo $escape($buttonText); ?></button>
        <?php if ($policyUrl) : ?>
            <a class="mod-lcookies__policy ms-2" href="<?php echo $escape($policyUrl); ?>"><?php echo Text::_('COM_LCOOKIES_UI_PRIVACY_POLICY'); ?></a>
        <?php endif; ?>
    </p>
</div>
