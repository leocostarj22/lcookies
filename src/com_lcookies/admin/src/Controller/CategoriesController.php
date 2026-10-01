<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Controller;

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Lcsilva\Component\Lcookies\Administrator\Exception\DeleteRefusedException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List controller for categories: publish, trash, delete, check-in and ordering.
 */
class CategoriesController extends AdminController
{
    /**
     * @var    string
     */
    protected $text_prefix = 'COM_LCOOKIES';

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    The model name.
     * @param   string  $prefix  The class prefix.
     * @param   array   $config  The array of possible config values.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
     */
    public function getModel($name = 'Category', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Deletes the selected categories, showing why when none can be deleted.
     *
     * @return  void
     */
    public function delete()
    {
        try {
            parent::delete();
        } catch (DeleteRefusedException $e) {
            $this->setMessage($e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_lcookies&view=categories', false));
        }
    }
}
