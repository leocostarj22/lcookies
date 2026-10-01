<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  plg_webservices_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Plugin\WebServices\Lcookies\Extension;

use Joomla\CMS\Event\Application\BeforeApiRouteEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Router\Route;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Routes of the LCookies Web Services API (/api/index.php/v1/lcookies/...). See docs/api.md.
 */
final class Lcookies extends CMSPlugin implements SubscriberInterface
{
    /**
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeApiRoute' => 'onBeforeApiRoute',
        ];
    }

    /**
     * Registers the routes.
     *
     * @param   BeforeApiRouteEvent  $event  The event.
     *
     * @return  void
     */
    public function onBeforeApiRoute(BeforeApiRouteEvent $event): void
    {
        $router   = $event->getRouter();
        $defaults = ['component' => 'com_lcookies'];

        foreach (['categories', 'services', 'cookies'] as $resource) {
            $router->createCRUDRoutes('v1/lcookies/' . $resource, $resource, $defaults);
        }

        $router->addRoutes([
            // Consent records are read only.
            new Route(['GET'], 'v1/lcookies/consents', 'consents.displayList', [], $defaults + ['public' => false]),
            new Route(['GET'], 'v1/lcookies/consents/:id', 'consents.displayItem', ['id' => '(\d+)'], $defaults + ['public' => false]),
            // The frontend contract (banner texts and categories), public like the pages that publish it.
            new Route(['GET'], 'v1/lcookies/config', 'config.displayItem', [], $defaults + ['public' => true]),
        ]);
    }
}
