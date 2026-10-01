<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\View\Service;

use Lcsilva\Component\Lcookies\Administrator\View\AbstractEditView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Edit a service.
 */
class HtmlView extends AbstractEditView
{
    /**
     * @var  string[]
     */
    protected $title = ['COM_LCOOKIES_SERVICE', 'shield-alt'];
}
