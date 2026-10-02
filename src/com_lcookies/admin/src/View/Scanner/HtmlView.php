<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\View\Scanner;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Lcsilva\Component\Lcookies\Administrator\Scanner\Scanner;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Cookie scanner: start button, results of the latest scan and history.
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Latest finished scan, or the one asked for (`id`).
     *
     * @var  ?array
     */
    protected $scan;

    /**
     * @var  array
     */
    protected $history = [];

    /**
     * Whether the user may scan.
     *
     * @var  boolean
     */
    protected $canScan = false;

    /**
     * Whether the user may add cookies and services.
     *
     * @var  boolean
     */
    protected $canCreate = false;

    /**
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        /** @var \Lcsilva\Component\Lcookies\Administrator\Model\ScannerModel $model */
        $model = $this->getModel();
        $user  = $this->getCurrentUser();

        $this->scan      = $model->getScan(Factory::getApplication()->getInput()->getInt('id', 0));
        $this->history   = $model->getHistory();
        $this->canScan   = $user->authorise('lcookies.scan', 'com_lcookies');
        $this->canCreate = $user->authorise('core.create', 'com_lcookies');

        if ($this->canScan) {
            $document = $this->getDocument();
            $document->addScriptOptions('com_lcookies.scanner', [
                'url'   => Route::_('index.php?option=com_lcookies&format=json', false),
                'token' => Session::getFormToken(),
                'param' => Scanner::PARAM,
            ]);

            Text::script('COM_LCOOKIES_SCAN_STATUS_SERVER');
            Text::script('COM_LCOOKIES_SCAN_STATUS_BEFORE');
            Text::script('COM_LCOOKIES_SCAN_STATUS_AFTER');
            Text::script('COM_LCOOKIES_SCAN_STATUS_SAVING');
            Text::script('COM_LCOOKIES_SCAN_STATUS_NO_BROWSER');
            Text::script('COM_LCOOKIES_SCAN_ERROR');

            $wa = $document->getWebAssetManager();
            $wa->getRegistry()->addExtensionRegistryFile('com_lcookies');
            $wa->useScript('com_lcookies.scanner');
        }

        ToolbarHelper::title(Text::_('COM_LCOOKIES_SCANNER_TITLE'), 'search');

        if ($user->authorise('core.admin', 'com_lcookies') || $user->authorise('core.options', 'com_lcookies')) {
            $this->getDocument()->getToolbar()->preferences('com_lcookies');
        }

        parent::display($tpl);
    }
}
