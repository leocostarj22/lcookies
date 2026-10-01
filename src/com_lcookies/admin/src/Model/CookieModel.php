<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Edit model for a cookie.
 */
class CookieModel extends AbstractItemModel
{
    /**
     * @var    string
     */
    public $typeAlias = 'com_lcookies.cookie';

    /**
     * @var    string
     */
    protected $orderingGroup = 'service_id';

    /**
     * Cookie names are not unique (the same name can exist on several domains).
     *
     * @var    string
     */
    protected $uniqueAlias = '';
}
