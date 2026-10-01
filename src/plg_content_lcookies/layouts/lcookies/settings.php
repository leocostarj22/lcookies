<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  plg_content_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * {lcookies-settings}: button that opens the cookie preferences (any element with
 * data-lcookies-open does, see plg_system_lcookies).
 *
 * Override: templates/<template>/html/layouts/lcookies/settings.php
 *
 * @var  \Joomla\CMS\Layout\FileLayout  $this
 * @var  array                          $displayData  ['label' => string]
 */
?>
<button type="button" class="btn btn-secondary lcookies-settings" data-lcookies-open><?php echo $this->escape($displayData['label']); ?></button>
