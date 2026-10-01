<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Api\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * /v1/lcookies/cookies
 */
class CookiesController extends AbstractLcookiesController
{
    /**
     * @var  string
     */
    protected $contentType = 'cookies';

    /**
     * @var  string
     */
    protected $default_view = 'cookies';

    /**
     * @var  string
     */
    protected $itemModel = 'Cookie';

    /**
     * @var  array<string, string[]>
     */
    protected $apiFilters = [
        'search'   => ['filter.search', 'STRING'],
        'state'    => ['filter.published', 'STRING'],
        'service'  => ['filter.service_id', 'INT'],
        'category' => ['filter.category_id', 'INT'],
        'type'     => ['filter.type', 'CMD'],
        'source'   => ['filter.source', 'CMD'],
    ];

    /**
     * @var  string[]
     */
    protected $orderingFields = ['a.id', 'a.ordering', 'a.name', 'a.type', 'a.state'];
}
