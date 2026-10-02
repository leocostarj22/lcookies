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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Lcsilva\Component\Lcookies\Administrator\Model\ScannerModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Cookie scanner, called by media/com_lcookies/js/scanner.js (JSON) and by the results table.
 */
class ScanController extends BaseController
{
    /**
     * Starts a scan: `{id, pages, param}`.
     *
     * @return  void
     */
    public function start(): void
    {
        $this->json(fn (ScannerModel $model): array => $model->start('manual'));
    }

    /**
     * Server pass of one page (`id`, `page`).
     *
     * @return  void
     */
    public function page(): void
    {
        $this->json(fn (ScannerModel $model): array => $model->server($this->input->getInt('id', 0), $this->input->getInt('page', -1)));
    }

    /**
     * Ends a scan (`id`; body: JSON browser results or null).
     *
     * @return  void
     */
    public function finish(): void
    {
        $this->json(function (ScannerModel $model): array {
            $browser = json_decode((string) $this->input->post->get('browser', '', 'raw'), true);
            $scan    = $model->finish($this->input->getInt('id', 0), $browser);

            return ['id' => (int) $scan['id'], 'unknown' => (int) $scan['unknown'], 'issues' => (int) $scan['issues']];
        });
    }

    /**
     * Opens a new cookie filled in with a cookie found by the scanner (`name`, `type`, `duration`).
     *
     * @return  void
     */
    public function adopt(): void
    {
        $this->checkToken('get');
        $this->requirePermission('core.create');

        $type           = $this->input->getCmd('type');
        $duration       = max(0, $this->input->getInt('duration', 0));
        [$value, $unit] = match (true) {
            $duration === 0          => [0, 'session'],
            $duration >= 86400 * 365 => [(int) round($duration / (86400 * 365)), 'year'],
            $duration >= 86400 * 30  => [(int) round($duration / (86400 * 30)), 'month'],
            $duration >= 86400       => [(int) round($duration / 86400), 'day'],
            $duration >= 3600        => [(int) round($duration / 3600), 'hour'],
            default                  => [max(1, (int) round($duration / 60)), 'minute'],
        };

        $this->app->setUserState('com_lcookies.edit.cookie.data', [
            'name'           => mb_substr($this->input->getString('name'), 0, 255),
            'match_type'     => 'exact',
            'type'           => \in_array($type, ['cookie', 'local', 'session'], true) ? $type : 'cookie',
            'duration_value' => $value,
            'duration_unit'  => $unit,
            'source'         => 'scanner',
            'state'          => 1,
        ]);

        $this->setRedirect(Route::_('index.php?option=com_lcookies&view=cookie&layout=edit', false));
    }

    /**
     * Runs a scanner step and sends its result as JSON.
     *
     * @param   callable  $step  Receives the model, returns the data.
     *
     * @return  void
     */
    private function json(callable $step): void
    {
        try {
            if (!$this->checkToken('post', false)) {
                throw new NotAllowed(Text::_('JINVALID_TOKEN_NOTICE'), 403);
            }

            $this->requirePermission('lcookies.scan');

            /** @var ScannerModel $model */
            $model    = $this->getModel('Scanner', 'Administrator', ['ignore_request' => true]);
            $response = new JsonResponse($step($model));
        } catch (NotAllowed $e) {
            $this->app->setHeader('status', 403, true);
            $response = new JsonResponse($e);
        } catch (\InvalidArgumentException $e) {
            $this->app->setHeader('status', 400, true);
            $response = new JsonResponse($e);
        }

        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true)
            ->setHeader('Cache-Control', 'no-store', true);
        $this->app->sendHeaders();

        echo $response;

        $this->app->close();
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
