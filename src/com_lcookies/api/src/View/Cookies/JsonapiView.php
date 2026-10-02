<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Api\View\Cookies;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Cookies (/v1/lcookies/cookies).
 */
class JsonapiView extends BaseApiView
{
    /**
     * @var  string[]
     */
    protected $fieldsToRenderItem = [
        'id',
        'service_id',
        'name',
        'display_name',
        'match_type',
        'type',
        'domain',
        'duration_value',
        'duration_unit',
        'description',
        'source',
        'state',
        'ordering',
        'created',
        'created_by',
        'modified',
        'modified_by',
        'checked_out',
        'checked_out_time',
    ];

    /**
     * @var  string[]
     */
    protected $fieldsToRenderList = [
        'id',
        'service_id',
        'service_title',
        'category_id',
        'category_title',
        'name',
        'display_name',
        'match_type',
        'type',
        'domain',
        'duration_value',
        'duration_unit',
        'source',
        'state',
        'ordering',
        'checked_out',
        'checked_out_time',
    ];
}
