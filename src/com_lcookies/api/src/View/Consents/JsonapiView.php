<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Api\View\Consents;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Consent records (/v1/lcookies/consents), read only.
 */
class JsonapiView extends BaseApiView
{
    /**
     * @var  string[]
     */
    protected $fieldsToRenderItem = [
        'id',
        'consent_uuid',
        'action',
        'categories',
        'policy_version',
        'user_id',
        'ip_hash',
        'ua_hash',
        'url',
        'language',
        'created',
    ];

    /**
     * @var  string[]
     */
    protected $fieldsToRenderList = [
        'id',
        'consent_uuid',
        'action',
        'categories',
        'policy_version',
        'user_id',
        'user_name',
        'ip_hash',
        'ua_hash',
        'url',
        'language',
        'created',
    ];

    /**
     * Categories as a list of aliases.
     *
     * @param   object  $item  The item.
     *
     * @return  object
     */
    protected function prepareItem($item)
    {
        if (\is_string($item->categories ?? null)) {
            $item->categories = json_decode($item->categories, true) ?: [];
        }

        return parent::prepareItem($item);
    }
}
