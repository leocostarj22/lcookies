<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Default controller of com_lcookies.
 */
class DisplayController extends BaseController
{
    /**
     * @var    string
     */
    protected $default_view = 'categories';

    /**
     * Edit views and the list each one returns to.
     */
    private const EDIT_VIEWS = [
        'category' => 'categories',
        'service'  => 'services',
        'cookie'   => 'cookies',
    ];

    /**
     * Displays a view, refusing direct access to edit layouts that were not opened through a task.
     *
     * @param   boolean  $cachable   If true, the view output will be cached.
     * @param   array    $urlparams  An array of safe url parameters and their variable types.
     *
     * @return  BaseController|boolean
     */
    public function display($cachable = false, $urlparams = [])
    {
        $view   = $this->input->get('view', $this->default_view);
        $layout = $this->input->get('layout', 'default');
        $id     = $this->input->getInt('id');

        if (isset(self::EDIT_VIEWS[$view]) && $layout === 'edit' && !$this->checkEditId('com_lcookies.edit.' . $view, $id)) {
            if (!\count($this->app->getMessageQueue())) {
                $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id), 'error');
            }

            $this->setRedirect(Route::_('index.php?option=com_lcookies&view=' . self::EDIT_VIEWS[$view], false));

            return false;
        }

        return parent::display($cachable, $urlparams);
    }
}
