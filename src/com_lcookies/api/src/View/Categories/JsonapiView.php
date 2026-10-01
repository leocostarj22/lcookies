<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Api\View\Categories;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Categories (/v1/lcookies/categories). Titles and descriptions are returned as stored (default data uses language constants).
 */
class JsonapiView extends BaseApiView
{
    /**
     * @var  string[]
     */
    protected $fieldsToRenderItem = [
        'id',
        'alias',
        'title',
        'description',
        'required',
        'core',
        'gcm_types',
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
        'alias',
        'title',
        'description',
        'required',
        'core',
        'gcm_types',
        'state',
        'ordering',
        'count_services',
        'checked_out',
        'checked_out_time',
    ];

    /**
     * Consent Mode types as a list.
     *
     * @param   object  $item  The item.
     *
     * @return  object
     */
    protected function prepareItem($item)
    {
        if (\is_string($item->gcm_types ?? null)) {
            $item->gcm_types = json_decode($item->gcm_types, true) ?: [];
        }

        return parent::prepareItem($item);
    }
}
