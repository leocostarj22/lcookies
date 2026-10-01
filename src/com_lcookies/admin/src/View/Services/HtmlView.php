<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\View\Services;

use Lcsilva\Component\Lcookies\Administrator\View\AbstractListView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Services list.
 */
class HtmlView extends AbstractListView
{
    /**
     * @var  string
     */
    protected $itemName = 'service';

    /**
     * @var  string[]
     */
    protected $title = ['COM_LCOOKIES_SERVICES_TITLE', 'shield-alt'];
}
