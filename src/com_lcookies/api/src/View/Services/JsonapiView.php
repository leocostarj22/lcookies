<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Api\View\Services;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Services (/v1/lcookies/services).
 */
class JsonapiView extends BaseApiView
{
    /**
     * @var  string[]
     */
    protected $fieldsToRenderItem = [
        'id',
        'category_id',
        'alias',
        'title',
        'provider',
        'privacy_url',
        'description',
        'block_patterns',
        'head_code',
        'body_code',
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
        'category_id',
        'category_alias',
        'category_title',
        'alias',
        'title',
        'provider',
        'privacy_url',
        'block_patterns',
        'state',
        'ordering',
        'count_cookies',
        'checked_out',
        'checked_out_time',
    ];
}
