<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\View\Categories;

use Lcsilva\Component\Lcookies\Administrator\View\AbstractListView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Cookie categories list.
 */
class HtmlView extends AbstractListView
{
    /**
     * @var  string
     */
    protected $itemName = 'category';

    /**
     * @var  string[]
     */
    protected $title = ['COM_LCOOKIES_CATEGORIES_TITLE', 'shield-alt'];
}
