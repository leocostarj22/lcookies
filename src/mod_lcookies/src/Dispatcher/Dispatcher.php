<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  mod_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Module\Lcookies\Site\Dispatcher;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Button that opens the cookie preferences, link to the policy and the visitor's choice.
 *
 * The HTML is the same for every visitor (cacheable); the choice is filled in by
 * media/mod_lcookies/js/status.js from window.LCookies.
 */
class Dispatcher extends AbstractModuleDispatcher
{
    /**
     * Renders nothing while LCookies is not active on the site (the button would do nothing).
     *
     * @return  void
     */
    public function dispatch()
    {
        if (!ComponentHelper::isEnabled('com_lcookies') || !PluginHelper::isEnabled('system', 'lcookies')) {
            return;
        }

        parent::dispatch();
    }

    /**
     * @return  array
     */
    protected function getLayoutData(): array
    {
        $data   = parent::getLayoutData();
        $params = $data['params'];

        LcookiesHelper::loadSiteLanguage($this->getApplication()->getLanguage());

        $privacy = (int) ComponentHelper::getParams('com_lcookies')->get('privacy_menuitem', 0);
        $label   = trim((string) $params->get('button_text', ''));

        $data['buttonText']  = $label !== '' ? $label : Text::_('COM_LCOOKIES_UI_FLOATING');
        $data['buttonClass'] = $params->get('button_style', 'button') === 'link' ? 'btn btn-link p-0' : 'btn btn-secondary';
        $data['policyUrl']   = $params->get('show_policy', 1) && $privacy > 0 ? Route::_('index.php?Itemid=' . $privacy) : null;
        $data['showStatus']  = (bool) $params->get('show_status', 1);

        return $data;
    }
}
