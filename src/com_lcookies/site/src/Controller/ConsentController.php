<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Site\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\Database\DatabaseInterface;
use Joomla\Utilities\IpHelper;
use Lcsilva\Component\Lcookies\Administrator\Consent\Anonymizer;
use Lcsilva\Component\Lcookies\Administrator\Consent\ConsentException;
use Lcsilva\Component\Lcookies\Administrator\Consent\ConsentLog;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Records the visitor's choice sent by the banner (contract `endpoint`).
 *
 * index.php?option=com_lcookies&task=consent.save&format=json, POST with a JSON body
 * {consent: {id, v, cats, ts}, action, url}.
 *
 * There is no CSRF token: pages must stay identical for every visitor so they can be cached.
 * Instead the request must be a same-origin JSON POST (a cross-site form cannot send
 * application/json, and browsers report cross-site requests in Sec-Fetch-Site), the payload is
 * validated strictly and requests are rate limited per truncated IP address.
 */
class ConsentController extends BaseController
{
    /**
     * Records a consent and answers with a JSON response.
     *
     * @return  void
     */
    public function save(): void
    {
        $app = $this->app;

        try {
            if ($this->input->getMethod() !== 'POST') {
                throw new ConsentException('Method not allowed.', 405);
            }

            $type = strtolower(trim(explode(';', $this->input->server->getString('CONTENT_TYPE', ''))[0]));

            if ($type !== 'application/json') {
                throw new ConsentException('Unsupported media type.', 415);
            }

            $site = $this->input->server->getString('HTTP_SEC_FETCH_SITE', '');

            if ($site !== '' && $site !== 'same-origin') {
                throw new ConsentException('Cross-site request.', 403);
            }

            $raw = (string) $this->input->json->getRaw();

            if (\strlen($raw) > ConsentLog::MAX_BODY) {
                throw new ConsentException('Payload too large.', 413);
            }

            $log = new ConsentLog(
                Factory::getContainer()->get(DatabaseInterface::class),
                ComponentHelper::getParams('com_lcookies'),
                new Anonymizer((string) $app->get('secret'))
            );

            $record = $log->record(json_decode($raw, true), [
                'ip'        => (string) IpHelper::getIp(),
                'userAgent' => $this->input->server->getString('HTTP_USER_AGENT', ''),
                'userId'    => (int) $app->getIdentity()?->id,
                'language'  => $app->getLanguage()->getTag(),
            ]);

            echo new JsonResponse(['id' => $record['consent_uuid'], 'created' => $record['created']]);
        } catch (ConsentException $e) {
            $app->setHeader('status', $e->getCode(), true);

            if ($e->getCode() === 405) {
                $app->setHeader('Allow', 'POST', true);
            }

            echo new JsonResponse(null, $e->getMessage(), true);
        }
    }
}
