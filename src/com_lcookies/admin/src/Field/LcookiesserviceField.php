<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Field;

use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Select list of services, grouped by category.
 */
class LcookiesserviceField extends GroupedlistField
{
    /**
     * @var    string
     */
    protected $type = 'Lcookiesservice';

    /**
     * @return  array
     */
    protected function getGroups()
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select(
                [
                    $db->quoteName('s.id'),
                    $db->quoteName('s.title'),
                    $db->quoteName('s.state'),
                    $db->quoteName('c.title', 'category_title'),
                ]
            )
            ->from($db->quoteName('#__lcookies_services', 's'))
            ->join('LEFT', $db->quoteName('#__lcookies_categories', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('s.category_id'))
            ->where($db->quoteName('s.state') . ' IN (0, 1)')
            ->order([$db->quoteName('c.ordering') . ' ASC', $db->quoteName('s.ordering') . ' ASC']);

        $groups = parent::getGroups();

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $label = LcookiesHelper::text($row->category_title);
            $text  = LcookiesHelper::text($row->title) . ((int) $row->state === 0 ? ' [-]' : '');

            $groups[$label][] = HTMLHelper::_('select.option', (int) $row->id, $text);
        }

        return $groups;
    }
}
