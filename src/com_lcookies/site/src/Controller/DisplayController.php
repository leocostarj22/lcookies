<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Site\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The component has no pages on the site, only the consent.save task.
 */
class DisplayController extends BaseController
{
    /**
     * @param   boolean  $cachable   If true, the view output will be cached.
     * @param   array    $urlparams  An array of safe url parameters and their variable types.
     *
     * @return  static
     *
     * @throws  \Exception
     */
    public function display($cachable = false, $urlparams = [])
    {
        throw new \Exception(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
    }
}
