<?php

/**
 * @package     Lcsilva.LCookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Package installer script: enforces the minimum Joomla and PHP versions and enables the plugins
 * the first time they are installed (later updates keep whatever the administrator chose).
 * It also keeps the mail template of the scheduled scan; on uninstall it removes the scheduled tasks
 * and the mail templates of plg_task_lcookies.
 */
class Pkg_LcookiesInstallerScript extends InstallerScript
{
    /**
     * @var  string
     */
    protected $minimumJoomla = '5.2.0';

    /**
     * @var  string
     */
    protected $minimumPhp = '8.1.0';

    /**
     * Plugins enabled on their first installation: [folder, element].
     *
     * @var  array
     */
    private array $plugins = [['system', 'lcookies'], ['webservices', 'lcookies'], ['content', 'lcookies'], ['privacy', 'lcookies'], ['task', 'lcookies']];

    /**
     * Plugins that were not installed before this run.
     *
     * @var  array
     */
    private array $newPlugins = [];

    /**
     * Checks the requirements and remembers which plugins are new.
     *
     * @param   string            $type    install, update or discover_install.
     * @param   InstallerAdapter  $parent  The adapter.
     *
     * @return  boolean
     */
    public function preflight($type, $parent)
    {
        if (!parent::preflight($type, $parent)) {
            return false;
        }

        foreach ($this->plugins as $plugin) {
            if ($this->pluginId(...$plugin) === 0) {
                $this->newPlugins[] = $plugin;
            }
        }

        return true;
    }

    /**
     * Mail templates: id => [subject, body, tags].
     *
     * @var  array
     */
    private array $mailTemplates = [
        'plg_task_lcookies.scan' => [
            'PLG_TASK_LCOOKIES_MAIL_SCAN_SUBJECT',
            'PLG_TASK_LCOOKIES_MAIL_SCAN_BODY',
            ['sitename', 'url', 'pages', 'issues', 'unknown', 'link'],
        ],
    ];

    /**
     * Enables the plugins installed for the first time and adds missing mail templates.
     *
     * @param   string            $type    install, update, discover_install or uninstall.
     * @param   InstallerAdapter  $parent  The adapter.
     *
     * @return  void
     */
    public function postflight($type, $parent)
    {
        // Joomla also runs postflight after an uninstall: nothing to enable or add then.
        if ($type === 'uninstall') {
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        foreach ($this->newPlugins as $plugin) {
            $id = $this->pluginId(...$plugin);

            if ($id === 0) {
                continue;
            }

            $query = $db->createQuery()
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('extension_id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);

            $db->setQuery($query)->execute();
        }

        foreach ($this->mailTemplates as $id => [$subject, $body, $tags]) {
            $exists = $db->setQuery(
                $db->createQuery()
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__mail_templates'))
                    ->where($db->quoteName('template_id') . ' = :id')
                    ->bind(':id', $id)
            )->loadResult();

            if ((int) $exists) {
                continue;
            }

            $template = (object) [
                'template_id' => $id,
                'extension'   => explode('.', $id, 2)[0],
                'language'    => '',
                'subject'     => $subject,
                'body'        => $body,
                'htmlbody'    => '',
                'attachments' => '',
                'params'      => json_encode(['tags' => $tags]),
            ];

            $db->insertObject('#__mail_templates', $template);
        }
    }

    /**
     * Removes the scheduled tasks and the mail templates of plg_task_lcookies, which would be left orphaned.
     *
     * @param   InstallerAdapter  $parent  The adapter.
     *
     * @return  boolean
     */
    public function uninstall($parent)
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $types = 'lcookies.%';
        $query = $db->createQuery()
            ->delete($db->quoteName('#__scheduler_tasks'))
            ->where($db->quoteName('type') . ' LIKE :types')
            ->bind(':types', $types);

        $db->setQuery($query)->execute();

        $extension = 'plg_task_lcookies';
        $query     = $db->createQuery()
            ->delete($db->quoteName('#__mail_templates'))
            ->where($db->quoteName('extension') . ' = :extension')
            ->bind(':extension', $extension);

        $db->setQuery($query)->execute();

        return true;
    }

    /**
     * Id of an installed plugin, 0 if it is not installed.
     *
     * @param   string  $folder   Plugin group.
     * @param   string  $element  Plugin element.
     *
     * @return  integer
     */
    private function pluginId(string $folder, string $element): int
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $type  = 'plugin';
        $query = $db->createQuery()
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where([
                $db->quoteName('type') . ' = :type',
                $db->quoteName('folder') . ' = :folder',
                $db->quoteName('element') . ' = :element',
            ])
            ->bind(':type', $type)
            ->bind(':folder', $folder)
            ->bind(':element', $element);

        return (int) $db->setQuery($query)->loadResult();
    }
}
