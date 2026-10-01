<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Api\Controller;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * /v1/lcookies/consents — consent records, read only (lcookies.consents.view).
 */
class ConsentsController extends AbstractLcookiesController
{
    /**
     * @var  string
     */
    protected $contentType = 'consents';

    /**
     * @var  string
     */
    protected $default_view = 'consents';

    /**
     * @var  string
     */
    protected $viewPermission = 'lcookies.consents.view';

    /**
     * Same filters as the backend list. `search` takes a consent id (uuid), `id:<n>` or part of the page URL.
     *
     * @var  array<string, string[]>
     */
    protected $apiFilters = [
        'search'         => ['filter.search', 'STRING'],
        'action'         => ['filter.action', 'CMD'],
        'category'       => ['filter.category', 'CMD'],
        'policy_version' => ['filter.policy_version', 'INT'],
        'user'           => ['filter.user', 'CMD'],
        'from'           => ['filter.from', 'STRING'],
        'to'             => ['filter.to', 'STRING'],
    ];

    /**
     * @var  string[]
     */
    protected $orderingFields = ['a.id', 'a.action', 'a.policy_version'];

    /**
     * Records are proof of consent and cannot be changed through the API.
     *
     * @return  void
     *
     * @throws  NotAllowed
     */
    public function add()
    {
        throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
    }

    /**
     * @return  static
     *
     * @throws  NotAllowed
     */
    public function edit()
    {
        throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
    }

    /**
     * @param   integer  $id  The primary key.
     *
     * @return  void
     *
     * @throws  NotAllowed
     */
    public function delete($id = null)
    {
        throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
    }
}
