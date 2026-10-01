<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Controller;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Lcsilva\Component\Lcookies\Administrator\Model\ConsentsModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Consent records: CSV export and deletion of expired records.
 */
class ConsentsController extends BaseController
{
    /**
     * CSV columns: heading => record field.
     */
    private const CSV_COLUMNS = [
        'created_utc'     => 'created',
        'consent_id'      => 'consent_uuid',
        'action'          => 'action',
        'categories'      => 'categories',
        'policy_version'  => 'policy_version',
        'user_id'         => 'user_id',
        'language'        => 'language',
        'page_url'        => 'url',
        'ip_hash'         => 'ip_hash',
        'user_agent_hash' => 'ua_hash',
    ];

    /**
     * Downloads the records of the current filters as CSV (UTF-8, comma separated).
     *
     * @return  void
     */
    public function export(): void
    {
        $this->checkToken('get');

        if (!$this->app->getIdentity()->authorise('lcookies.consents.export', 'com_lcookies')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        /** @var ConsentsModel $model */
        $model = $this->getModel('Consents', 'Administrator', ['ignore_request' => false]);

        // Reads the filters and ordering the user chose in the list.
        $model->getState();

        // The file is the whole response: drop what the component output buffer holds so far.
        while (ob_get_level()) {
            ob_end_clean();
        }

        $filename = 'lcookies-consents-' . (new Date('now', 'UTC'))->format('Y-m-d_His') . '.csv';

        $this->app->setHeader('Content-Type', 'text/csv; charset=utf-8', true)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true)
            ->setHeader('Cache-Control', 'no-store', true)
            ->setHeader('X-Content-Type-Options', 'nosniff', true)
            ->sendHeaders();

        $output = fopen('php://output', 'w');

        // Byte order mark, so spreadsheet programs read UTF-8.
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, array_keys(self::CSV_COLUMNS), ',', '"', '');

        foreach ($model->getExportRows() as $row) {
            $line = [];

            foreach (self::CSV_COLUMNS as $field) {
                $value = (string) $row->$field;

                if ($field === 'created') {
                    $value = (new Date($value, 'UTC'))->format('Y-m-d\TH:i:s\Z');
                } elseif ($field === 'categories') {
                    $value = implode(' ', json_decode($value, true) ?: []);
                }

                $line[] = $this->csvSafe($value);
            }

            fputcsv($output, $line, ',', '"', '');
        }

        fclose($output);
        $this->app->close();
    }

    /**
     * Deletes the records older than the retention period set in the options.
     *
     * @return  void
     */
    public function purge(): void
    {
        $this->checkToken();

        if (!$this->app->getIdentity()->authorise('core.delete', 'com_lcookies')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        /** @var ConsentsModel $model */
        $model   = $this->getModel('Consents', 'Administrator', ['ignore_request' => true]);
        $deleted = $model->purge();

        $this->setRedirect(
            Route::_('index.php?option=com_lcookies&view=consents', false),
            Text::plural('COM_LCOOKIES_CONSENTS_N_PURGED', $deleted)
        );
    }

    /**
     * Neutralises values that spreadsheet programs would run as formulas (CSV injection).
     *
     * @param   string  $value  Cell value.
     *
     * @return  string
     */
    private function csvSafe(string $value): string
    {
        return $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
    }
}
