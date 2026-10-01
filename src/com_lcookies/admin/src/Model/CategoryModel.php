<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Utilities\ArrayHelper;
use Lcsilva\Component\Lcookies\Administrator\Exception\DeleteRefusedException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Edit model for a cookie category.
 */
class CategoryModel extends AbstractItemModel
{
    /**
     * @var    string
     */
    public $typeAlias = 'com_lcookies.category';

    /**
     * Locks the fields of core categories.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data.
     *
     * @return  \Joomla\CMS\Form\Form|boolean
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = parent::getForm($data, $loadData);

        if ($form === false) {
            return false;
        }

        $id    = (int) $this->getState($this->getName() . '.id');
        $table = $this->getTable();

        if ($id && $table->load($id) && (int) $table->core === 1) {
            foreach (['alias', 'state', 'required'] as $field) {
                $form->setFieldAttribute($field, 'readonly', 'true');
            }

            $form->setFieldAttribute('alias', 'description', 'COM_LCOOKIES_FIELD_ALIAS_CORE_DESC');
        }

        return $form;
    }

    /**
     * Decodes the Consent Mode types for the checkboxes.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed  Object on success, false on failure.
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if ($item && \is_string($item->gcm_types ?? null)) {
            $item->gcm_types = json_decode($item->gcm_types, true) ?: [];
        }

        return $item;
    }

    /**
     * Unchecking every checkbox posts nothing, so default the field to an empty list.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean
     */
    public function save($data)
    {
        $data['gcm_types'] = $data['gcm_types'] ?? [];

        return parent::save($data);
    }

    /**
     * Core categories must stay published.
     *
     * @param   array    &$pks   A list of the primary keys to change.
     * @param   integer  $value  The value of the published state.
     *
     * @return  boolean
     */
    public function publish(&$pks, $value = 1)
    {
        if ((int) $value !== 1) {
            $refused = [];
            $pks     = $this->withoutCore((array) $pks, 'COM_LCOOKIES_ERROR_CORE_STATE', $refused);

            $this->warn($refused);
        }

        return $pks ? parent::publish($pks, $value) : true;
    }

    /**
     * Core categories and categories with services cannot be deleted.
     *
     * @param   array  &$pks  An array of record primary keys.
     *
     * @return  boolean
     *
     * @throws  DeleteRefusedException  When none of the categories can be deleted.
     */
    public function delete(&$pks)
    {
        $refused = [];
        $pks     = $this->withoutCore(ArrayHelper::toInteger((array) $pks), 'COM_LCOOKIES_ERROR_CORE_DELETE', $refused);
        $used    = $this->countReferences('#__lcookies_services', 'category_id', $pks);

        foreach ($pks as $i => $pk) {
            if (!empty($used[$pk])) {
                $refused[] = Text::sprintf('COM_LCOOKIES_ERROR_CATEGORY_HAS_SERVICES', $pk, $used[$pk]);
                unset($pks[$i]);
            }
        }

        if (!$pks) {
            throw new DeleteRefusedException(implode("\n", $refused));
        }

        $this->warn($refused);

        return parent::delete($pks);
    }

    /**
     * Removes core categories from a list of ids.
     *
     * @param   int[]     $pks      Category ids.
     * @param   string    $key      Language key of the reason.
     * @param   string[]  $refused  Receives the reason for each removed category.
     *
     * @return  int[]
     */
    private function withoutCore(array $pks, string $key, array &$refused): array
    {
        $table = $this->getTable();

        foreach ($pks as $i => $pk) {
            $table->reset();

            if ($table->load((int) $pk) && (int) $table->core === 1) {
                $refused[] = Text::sprintf($key, $table->alias);
                unset($pks[$i]);
            }
        }

        return $pks;
    }

    /**
     * Shows the reasons why some categories were skipped.
     *
     * @param   string[]  $messages  The reasons.
     *
     * @return  void
     */
    private function warn(array $messages): void
    {
        foreach ($messages as $message) {
            Factory::getApplication()->enqueueMessage($message, 'warning');
        }
    }
}
