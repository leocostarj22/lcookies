<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\View\Services;

use Joomla\CMS\Router\Route;
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

    /**
     * Adds the link to the service library (presets, import and export).
     *
     * @return  void
     */
    protected function addToolbar(): void
    {
        parent::addToolbar();

        $this->getDocument()->getToolbar()->linkButton('puzzle-piece', 'COM_LCOOKIES_PRESETS_BUTTON')
            ->url(Route::_('index.php?option=com_lcookies&view=presets', false))
            ->icon('icon-puzzle-piece');
    }
}
