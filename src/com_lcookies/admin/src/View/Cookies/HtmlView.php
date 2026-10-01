<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\View\Cookies;

use Lcsilva\Component\Lcookies\Administrator\View\AbstractListView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Cookies list.
 */
class HtmlView extends AbstractListView
{
    /**
     * @var  string
     */
    protected $itemName = 'cookie';

    /**
     * @var  string[]
     */
    protected $title = ['COM_LCOOKIES_COOKIES_TITLE', 'shield-alt'];
}
