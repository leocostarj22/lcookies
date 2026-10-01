<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Api\View\Config;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Frontend contract (/v1/lcookies/config): the attributes follow docs/contract.schema.json, the id is the language tag.
 */
class JsonapiView extends BaseApiView
{
    /**
     * @var  string[]
     */
    protected $fieldsToRenderItem = [
        'schema',
        'policyVersion',
        'expiryDays',
        'cookie',
        'endpoint',
        'layout',
        'theme',
        'floatingButton',
        'privacyUrl',
        'respectGpc',
        'autoblock',
        'iframePlaceholder',
        'gcm',
        'categories',
        'texts',
    ];

    /**
     * @var  string[]
     */
    protected $fieldsToRenderList = [
        'schema',
        'policyVersion',
        'expiryDays',
        'cookie',
        'endpoint',
        'layout',
        'theme',
        'floatingButton',
        'privacyUrl',
        'respectGpc',
        'autoblock',
        'iframePlaceholder',
        'gcm',
        'categories',
        'texts',
    ];
}
