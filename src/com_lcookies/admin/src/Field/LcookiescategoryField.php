<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Field;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Select list of cookie categories. The value is the id, or the alias with `key="alias"`.
 */
class LcookiescategoryField extends ListField
{
    /**
     * @var    string
     */
    protected $type = 'Lcookiescategory';

    /**
     * @return  object[]
     */
    protected function getOptions()
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'alias', 'title', 'state']))
            ->from($db->quoteName('#__lcookies_categories'))
            ->where($db->quoteName('state') . ' IN (0, 1)')
            ->order($db->quoteName('ordering') . ' ASC');

        $byAlias = (string) $this->element['key'] === 'alias';
        $options = [];

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $text = LcookiesHelper::text($row->title) . ((int) $row->state === 0 ? ' [-]' : '');

            $options[] = HTMLHelper::_('select.option', $byAlias ? $row->alias : (int) $row->id, $text);
        }

        return array_merge(parent::getOptions(), $options);
    }
}
