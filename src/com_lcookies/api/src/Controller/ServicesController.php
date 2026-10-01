<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Api\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * /v1/lcookies/services
 */
class ServicesController extends AbstractLcookiesController
{
    /**
     * @var  string
     */
    protected $contentType = 'services';

    /**
     * @var  string
     */
    protected $default_view = 'services';

    /**
     * @var  string
     */
    protected $itemModel = 'Service';

    /**
     * @var  array<string, string[]>
     */
    protected $apiFilters = [
        'search'   => ['filter.search', 'STRING'],
        'state'    => ['filter.published', 'STRING'],
        'category' => ['filter.category_id', 'INT'],
    ];

    /**
     * @var  string[]
     */
    protected $orderingFields = ['a.id', 'a.ordering', 'a.title', 'a.provider', 'a.state'];
}
