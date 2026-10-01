<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  plg_privacy_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Plugin\Privacy\Lcookies\Extension;

use Joomla\CMS\Event\Privacy\ExportRequestEvent;
use Joomla\CMS\Event\Privacy\RemoveDataEvent;
use Joomla\Component\Privacy\Administrator\Plugin\PrivacyPlugin;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Consent records of LCookies in the requests of com_privacy (Users → Privacy).
 *
 * Only records made while logged in are linked to an account (user_id); choices made as a guest
 * cannot be tied to a person and are left alone.
 */
final class Lcookies extends PrivacyPlugin implements SubscriberInterface
{
    /**
     * Columns exported, in this order. The IP address and the browser are only stored as hashes.
     */
    private const COLUMNS = ['id', 'consent_uuid', 'action', 'categories', 'policy_version', 'url', 'language', 'created', 'ip_hash', 'ua_hash'];

    /**
     * Returns the events this plugin listens to.
     *
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onPrivacyExportRequest' => 'onPrivacyExportRequest',
            'onPrivacyRemoveData'    => 'onPrivacyRemoveData',
        ];
    }

    /**
     * Adds the user's consent records to the export.
     *
     * @param   ExportRequestEvent  $event  The event.
     *
     * @return  void
     */
    public function onPrivacyExportRequest(ExportRequestEvent $event): void
    {
        $user = $event->getUser();

        if (!$user) {
            return;
        }

        $db     = $this->getDatabase();
        $userId = (int) $user->id;
        $query  = $db->createQuery()
            ->select($db->quoteName(self::COLUMNS))
            ->from($db->quoteName('#__lcookies_consents'))
            ->where($db->quoteName('user_id') . ' = :user')
            ->order($db->quoteName('id') . ' ASC')
            ->bind(':user', $userId, ParameterType::INTEGER);

        $domain = $this->createDomain('lcookies_consents', 'lcookies_cookie_consent_records');

        foreach ($db->setQuery($query)->loadAssocList() as $row) {
            $id = (int) $row['id'];
            unset($row['id']);
            $row['created'] .= ' UTC';

            $domain->addItem($this->createItemFromArray($row, $id));
        }

        $event->addResult([$domain]);
    }

    /**
     * Removes the link between the user and their consent records, or deletes the records (option).
     *
     * Unlinking keeps the proof of consent (the site may need it to show that it had consent) but
     * no longer ties it to the account: what is left is a random consent id and hashes.
     *
     * @param   RemoveDataEvent  $event  The event.
     *
     * @return  void
     */
    public function onPrivacyRemoveData(RemoveDataEvent $event): void
    {
        $user = $event->getUser();

        if (!$user) {
            return;
        }

        $db     = $this->getDatabase();
        $userId = (int) $user->id;
        $query  = $this->params->get('removal', 'unlink') === 'delete'
            ? $db->createQuery()->delete($db->quoteName('#__lcookies_consents'))
            : $db->createQuery()->update($db->quoteName('#__lcookies_consents'))->set($db->quoteName('user_id') . ' = NULL');

        $query->where($db->quoteName('user_id') . ' = :user')
            ->bind(':user', $userId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();
    }
}
