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
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryAwareTrait;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Lcsilva\Component\Lcookies\Administrator\Consent\ConsentLog;
use Lcsilva\Component\Lcookies\Administrator\Model\ScannerModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Scheduled tasks of LCookies (System → Scheduled Tasks).
 *
 * - lcookies.purge: without a task, expired consent records are still removed now and then while
 *   visitors save choices (one save in ConsentLog::PURGE_CHANCE); the task removes them on a fixed
 *   schedule, also on sites with few visitors.
 * - lcookies.scan: server pass of the cookie scanner (cookies set before consent); e-mails the mail
 *   template "plg_task_lcookies.scan" when something is wrong.
 */
final class Lcookies extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;
    use UserFactoryAwareTrait;

    /**
     * Routines offered to the scheduler.
     */
    private const TASKS_MAP = [
        'lcookies.purge' => [
            'langConstPrefix' => 'PLG_TASK_LCOOKIES_PURGE',
            'method'          => 'purge',
        ],
        'lcookies.scan' => [
            'langConstPrefix' => 'PLG_TASK_LCOOKIES_SCAN',
            'method'          => 'scan',
            'form'            => 'scan',
        ],
    ];

    /**
     * Mail template of the scan.
     */
    public const MAIL_TEMPLATE = 'plg_task_lcookies.scan';

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

    /**
     * Scans the pages of the site (server pass) and reports problems by e-mail.
     *
     * @param   ExecuteTaskEvent  $event  The event.
     *
     * @return  integer  Status code.
     */
    private function scan(ExecuteTaskEvent $event): int
    {
        if (!ComponentHelper::isEnabled('com_lcookies')) {
            $this->logTask(Text::_('PLG_TASK_LCOOKIES_LOG_DISABLED'), 'warning');

            return Status::NO_RUN;
        }

        /** @var ScannerModel $model */
        $model = $this->getApplication()->bootComponent('com_lcookies')->getMVCFactory()
            ->createModel('Scanner', 'Administrator', ['ignore_request' => true]);
        $scan  = $model->start('task');

        foreach (array_keys($scan['pages']) as $page) {
            $model->server($scan['id'], $page);
        }

        $result = $model->finish($scan['id']);

        $this->logTask(Text::sprintf('PLG_TASK_LCOOKIES_LOG_SCANNED', \count($scan['pages']), (int) $result['issues'], (int) $result['unknown']));

        if ((int) $result['issues'] || (int) $result['unknown']) {
            $this->notify($result, (string) ($event->getArgument('params')->emails ?? ''));
        }

        return Status::OK;
    }

    /**
     * E-mails the result of a scan to the addresses of the task, or else to the Super Users who
     * receive system e-mails.
     *
     * @param   array   $scan    The scan (ScannerModel::getScan()).
     * @param   string  $emails  Addresses separated by commas.
     *
     * @return  void
     */
    private function notify(array $scan, string $emails): void
    {
        $recipients = array_filter(array_map('trim', explode(',', $emails)), static fn (string $email): bool => (bool) filter_var($email, FILTER_VALIDATE_EMAIL));

        if (!$recipients) {
            $db    = $this->getDatabase();
            $flag  = 1;
            $block = 0;
            $query = $db->createQuery()
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__users'))
                ->where([$db->quoteName('sendEmail') . ' = :flag', $db->quoteName('block') . ' = :block'])
                ->bind(':flag', $flag, ParameterType::INTEGER)
                ->bind(':block', $block, ParameterType::INTEGER);

            foreach ($db->setQuery($query)->loadColumn() as $id) {
                $user = $this->getUserFactory()->loadUserById((int) $id);

                if ($user->authorise('core.admin')) {
                    $recipients[] = $user->email;
                }
            }
        }

        $root = rtrim(Uri::root(), '/');
        $data = [
            'sitename' => (string) $this->getApplication()->get('sitename'),
            'url'      => $root,
            'pages'    => (string) $scan['pages'],
            'issues'   => (string) $scan['issues'],
            'unknown'  => (string) $scan['unknown'],
            'link'     => $root . '/administrator/index.php?option=com_lcookies&view=scanner&id=' . (int) $scan['id'],
        ];

        foreach (array_unique($recipients) as $email) {
            try {
                $mail = new MailTemplate(self::MAIL_TEMPLATE, $this->getApplication()->getLanguage()->getTag());
                $mail->addTemplateData($data);
                $mail->addRecipient($email);
                $mail->send();
            } catch (\Throwable $e) {
                $this->logTask(Text::sprintf('PLG_TASK_LCOOKIES_LOG_MAIL_FAILED', $email, $e->getMessage()), 'warning');
            }
        }
    }
}
