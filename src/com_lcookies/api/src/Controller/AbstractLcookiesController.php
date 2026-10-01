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
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;
use Lcsilva\Component\Lcookies\Administrator\Exception\DeleteRefusedException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Shared behaviour of the LCookies API controllers.
 *
 * The administrator models, forms and tables are reused, so the API applies the same rules as the
 * backend. Permissions are checked here the same way on every Joomla version: reading needs
 * `$viewPermission`, writing needs core.manage plus core.create, core.edit or core.delete (the core
 * ApiController does not check reading, and its checks differ between 5.2, 5.4 and 6.0; 5.4.9 even
 * fails on PATCH).
 */
abstract class AbstractLcookiesController extends ApiController
{
    /**
     * Name of the administrator item model, e.g. `Category`.
     *
     * @var  string
     */
    protected $itemModel = '';

    /**
     * Permission needed to read.
     *
     * @var  string
     */
    protected $viewPermission = 'core.manage';

    /**
     * Accepted `filter[...]` parameters: API name => [model state, input filter type].
     *
     * @var  array<string, string[]>
     */
    protected $apiFilters = [
        'search' => ['filter.search', 'STRING'],
        'state'  => ['filter.published', 'STRING'],
    ];

    /**
     * Accepted values of `list[ordering]`.
     *
     * @var  string[]
     */
    protected $orderingFields = ['a.id', 'a.ordering'];

    /**
     * Loads the language of the component (error messages of the models and tables).
     *
     * @param   string  $task  The task to perform.
     *
     * @return  mixed
     */
    public function execute($task)
    {
        $language = $this->app->getLanguage();
        $language->load('com_lcookies', JPATH_ADMINISTRATOR)
            || $language->load('com_lcookies', JPATH_ADMINISTRATOR . '/components/com_lcookies');

        return parent::execute($task);
    }

    /**
     * @param   integer  $id  The primary key to display.
     *
     * @return  static
     */
    public function displayItem($id = null)
    {
        $this->checkViewAccess();

        return parent::displayItem($id);
    }

    /**
     * Lists the records, with the `filter[...]` and `list[ordering|direction]` parameters.
     *
     * @return  static
     */
    public function displayList()
    {
        $this->checkViewAccess();

        $filter  = InputFilter::getInstance();
        $filters = $this->input->get('filter', [], 'array');

        foreach ($this->apiFilters as $name => [$state, $type]) {
            if (\array_key_exists($name, $filters) && \is_scalar($filters[$name])) {
                $this->modelState->set($state, $filter->clean($filters[$name], $type));
            }
        }

        $list = $this->input->get('list', [], 'array');

        if (\in_array($list['ordering'] ?? null, $this->orderingFields, true)) {
            $this->modelState->set('list.ordering', $list['ordering']);
        }

        if (\in_array(strtoupper((string) ($list['direction'] ?? '')), ['ASC', 'DESC'], true)) {
            $this->modelState->set('list.direction', strtoupper($list['direction']));
        }

        return parent::displayList();
    }

    /**
     * Deletes a record. As in the backend only trashed records can be deleted (409 otherwise).
     *
     * @param   integer  $id  The primary key.
     *
     * @return  void
     *
     * @throws  NotAllowed|ResourceNotFound|DeleteRefusedException
     */
    public function delete($id = null)
    {
        if (!$this->can('core.delete')) {
            throw new NotAllowed(Text::_('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED'), 403);
        }

        $id    = (int) ($id ?? $this->input->getInt('id'));
        $model = $this->getModel($this->itemModel, 'Administrator', ['ignore_request' => true]);
        $table = $model->getTable();

        if (!$id || !$table->load($id)) {
            throw new ResourceNotFound(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        if ((int) $table->state !== -2) {
            throw new DeleteRefusedException(Text::_('COM_LCOOKIES_ERROR_DELETE_NOT_TRASHED'));
        }

        $pks = [$id];

        if (!$model->delete($pks)) {
            throw new DeleteRefusedException(Text::_('JLIB_APPLICATION_ERROR_DELETE'));
        }

        $this->app->setHeader('status', 204);
    }

    /**
     * @param   array  $data  An array of input data.
     *
     * @return  boolean
     */
    protected function allowAdd($data = [])
    {
        return $this->can('core.create');
    }

    /**
     * @param   array   $data  An array of input data.
     * @param   string  $key   The name of the key for the primary key.
     *
     * @return  boolean
     */
    protected function allowEdit($data = [], $key = 'id')
    {
        return $this->can('core.edit');
    }

    /**
     * Writing needs to manage the component and the given permission.
     *
     * @param   string  $action  core.create, core.edit or core.delete.
     *
     * @return  boolean
     */
    protected function can(string $action): bool
    {
        $user = $this->app->getIdentity();

        return $user->authorise('core.manage', $this->option) && $user->authorise($action, $this->option);
    }

    /**
     * @return  void
     *
     * @throws  NotAllowed
     */
    protected function checkViewAccess(): void
    {
        if (!$this->app->getIdentity()->authorise($this->viewPermission, $this->option)) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
