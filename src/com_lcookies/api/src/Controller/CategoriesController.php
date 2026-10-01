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
 * /v1/lcookies/categories
 */
class CategoriesController extends AbstractLcookiesController
{
    /**
     * @var  string
     */
    protected $contentType = 'categories';

    /**
     * @var  string
     */
    protected $default_view = 'categories';

    /**
     * @var  string
     */
    protected $itemModel = 'Category';

    /**
     * @var  string[]
     */
    protected $orderingFields = ['a.id', 'a.ordering', 'a.title', 'a.state'];

    /**
     * Consent Mode types are stored as JSON; PATCH merges the stored value, so decode it for the form.
     *
     * @param   array  $data  Data to save.
     *
     * @return  array
     */
    protected function preprocessSaveData(array $data): array
    {
        if (\is_string($data['gcm_types'] ?? null)) {
            $data['gcm_types'] = json_decode($data['gcm_types'], true) ?: [];
        }

        return parent::preprocessSaveData($data);
    }
}
