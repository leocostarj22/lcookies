<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  plg_task_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Plugin\Task\Lcookies\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Lcsilva\Component\Lcookies\Administrator\Consent\ConsentLog;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Scheduled tasks of LCookies (System → Scheduled Tasks).
 *
 * Without a task, expired consent records are still removed now and then while visitors save
 * choices (one save in ConsentLog::PURGE_CHANCE); the task removes them on a fixed schedule, also
 * on sites with few visitors.
 */
final class Lcookies extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * Routines offered to the scheduler.
     */
    private const TASKS_MAP = [
        'lcookies.purge' => [
            'langConstPrefix' => 'PLG_TASK_LCOOKIES_PURGE',
            'method'          => 'purge',
        ],
    ];

    /**
     * @var  boolean
     */
    protected $autoloadLanguage = true;

    /**
     * Returns the events this plugin listens to.
     *
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Deletes the consent records older than the retention period of the component options.
     *
     * @param   ExecuteTaskEvent  $event  The event.
     *
     * @return  integer  Status code.
     */
    private function purge(ExecuteTaskEvent $event): int
    {
        if (!ComponentHelper::isEnabled('com_lcookies')) {
            $this->logTask(Text::_('PLG_TASK_LCOOKIES_LOG_DISABLED'), 'warning');

            return Status::NO_RUN;
        }

        $params  = ComponentHelper::getParams('com_lcookies');
        $deleted = (new ConsentLog($this->getDatabase(), $params))->purge();

        $this->logTask(Text::sprintf('PLG_TASK_LCOOKIES_LOG_PURGED', $deleted, ConsentLog::retentionMonths($params)));

        return Status::OK;
    }
}
