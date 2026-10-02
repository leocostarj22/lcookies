<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Controller;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Lcsilva\Component\Lcookies\Administrator\Model\TransferModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Export and import (JSON) and the preset library.
 */
class TransferController extends BaseController
{
    /**
     * Downloads categories, services and cookies as JSON.
     *
     * @return  void
     */
    public function export(): void
    {
        $this->checkToken('get');
        $this->requirePermission('core.manage');

        /** @var TransferModel $model */
        $model    = $this->getModel('Transfer', 'Administrator');
        $json     = json_encode($model->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $filename = 'lcookies-' . (new Date('now', 'UTC'))->format('Y-m-d') . '.json';

        while (ob_get_level()) {
            ob_end_clean();
        }

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true)
            ->setHeader('Cache-Control', 'no-store', true)
            ->sendHeaders();

        echo $json;

        $this->app->close();
    }

    /**
     * Imports an uploaded JSON file (field `import_file`, option `overwrite`).
     *
     * @return  void
     */
    public function import(): void
    {
        $this->checkToken();
        $this->requirePermission('core.create');
        $this->requirePermission('core.edit');

        $file = $this->input->files->get('import_file', [], 'raw');
        $data = null;

        if (\is_array($file) && ($file['error'] ?? 1) === UPLOAD_ERR_OK && ($file['size'] ?? 0) <= TransferModel::MAX_SIZE
            && is_uploaded_file($file['tmp_name'])) {
            $data = json_decode((string) file_get_contents($file['tmp_name']), true);
        }

        $this->run(fn (TransferModel $model) => $model->import($data, (bool) $this->input->getInt('overwrite')));
    }

    /**
     * Adds a service of the library (`preset` = its alias; `return=scanner` goes back to the scanner).
     *
     * @return  void
     */
    public function preset(): void
    {
        $this->checkToken();
        $this->requirePermission('core.create');

        $alias = $this->input->getCmd('preset');

        $this->run(fn (TransferModel $model) => $model->addPreset($alias));
    }

    /**
     * Runs an import and shows its report on the library page.
     *
     * @param   callable  $task  Receives the model, returns the report.
     *
     * @return  void
     */
    private function run(callable $task): void
    {
        // The scanner suggests services of the library and asks to come back to its results.
        $view = $this->input->getCmd('return') === 'scanner' ? 'scanner' : 'presets';

        $this->setRedirect(Route::_('index.php?option=com_lcookies&view=' . $view, false));

        /** @var TransferModel $model */
        $model = $this->getModel('Transfer', 'Administrator');

        try {
            $report = $task($model);
        } catch (\InvalidArgumentException $e) {
            $this->setMessage($e->getMessage(), 'error');

            return;
        }

        $this->app->enqueueMessage(Text::sprintf(
            'COM_LCOOKIES_TRANSFER_REPORT',
            $report['categories']['added'],
            $report['categories']['updated'],
            $report['categories']['skipped'],
            $report['services']['added'],
            $report['services']['updated'],
            $report['services']['skipped'],
            $report['cookies']['added']
        ), $report['errors'] ? 'warning' : 'success');

        foreach ($report['errors'] as $error) {
            $this->app->enqueueMessage($error, 'error');
        }
    }

    /**
     * @param   string  $action  Permission.
     *
     * @return  void
     *
     * @throws  NotAllowed
     */
    private function requirePermission(string $action): void
    {
        if (!$this->app->getIdentity()->authorise($action, 'com_lcookies')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
