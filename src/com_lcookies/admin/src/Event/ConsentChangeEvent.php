<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Event;

use Joomla\CMS\Event\AbstractImmutableEvent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * onLCookiesConsentChange: a visitor made a choice in the banner and it was recorded.
 *
 * Dispatched by the consent endpoint (com_lcookies, task=consent.save) after the record is saved,
 * so only while consent logging is on. Plugins of the `system` and `lcookies` groups receive it.
 *
 * ```php
 * public static function getSubscribedEvents(): array
 * {
 *     return [ConsentChangeEvent::NAME => 'onConsentChange'];
 * }
 *
 * public function onConsentChange(ConsentChangeEvent $event): void
 * {
 *     if (\in_array('marketing', $event->getRevoked(), true)) { ... }
 * }
 * ```
 *
 * The request comes from the visitor's browser in the background (keepalive fetch): do not send
 * output or redirect, and keep the work short.
 */
class ConsentChangeEvent extends AbstractImmutableEvent
{
    /**
     * Event name.
     */
    public const NAME = 'onLCookiesConsentChange';

    /**
     * @param   string  $name       The event name (NAME).
     * @param   array   $arguments  `consentId` (string), `action` (string), `categories` (string[]),
     *                              `previous` (?string[], null on the first recorded choice),
     *                              `policyVersion` (int), `userId` (int, 0 for guests).
     *
     * @throws  \BadMethodCallException  If an argument is missing.
     */
    public function __construct(string $name, array $arguments = [])
    {
        foreach (['consentId', 'action', 'categories', 'previous', 'policyVersion', 'userId'] as $argument) {
            if (!\array_key_exists($argument, $arguments)) {
                throw new \BadMethodCallException("Argument '$argument' of event $name is required but has not been provided");
            }
        }

        parent::__construct($name, $arguments);
    }

    /**
     * The consent id (UUID v4) kept in the visitor's cookie, the same in every choice they make.
     *
     * @return  string
     */
    public function getConsentId(): string
    {
        return $this->arguments['consentId'];
    }

    /**
     * How the choice was made: accept_all, reject_all, custom or allow.
     *
     * @return  string
     */
    public function getAction(): string
    {
        return $this->arguments['action'];
    }

    /**
     * Categories accepted now, required ones included.
     *
     * @return  string[]
     */
    public function getCategories(): array
    {
        return $this->arguments['categories'];
    }

    /**
     * Categories of the previous recorded choice with the same consent id.
     *
     * @return  ?string[]  Null on the first choice (or when the earlier records were purged).
     */
    public function getPrevious(): ?array
    {
        return $this->arguments['previous'];
    }

    /**
     * Categories accepted now that were not accepted before (all of them on the first choice).
     *
     * @return  string[]
     */
    public function getGranted(): array
    {
        return array_values(array_diff($this->arguments['categories'], $this->arguments['previous'] ?? []));
    }

    /**
     * Categories accepted before that are no longer accepted.
     *
     * @return  string[]
     */
    public function getRevoked(): array
    {
        return array_values(array_diff($this->arguments['previous'] ?? [], $this->arguments['categories']));
    }

    /**
     * @return  integer
     */
    public function getPolicyVersion(): int
    {
        return $this->arguments['policyVersion'];
    }

    /**
     * @return  integer  The logged-in user, 0 for guests.
     */
    public function getUserId(): int
    {
        return $this->arguments['userId'];
    }

    /**
     * @param   string  $value  The consent id.
     *
     * @return  string
     */
    protected function onSetConsentId(string $value): string
    {
        return $value;
    }

    /**
     * @param   string  $value  The action.
     *
     * @return  string
     */
    protected function onSetAction(string $value): string
    {
        return $value;
    }

    /**
     * @param   array  $value  Category aliases.
     *
     * @return  string[]
     */
    protected function onSetCategories(array $value): array
    {
        return array_values(array_map('strval', $value));
    }

    /**
     * @param   ?array  $value  Category aliases or null.
     *
     * @return  ?string[]
     */
    protected function onSetPrevious(?array $value): ?array
    {
        return $value === null ? null : array_values(array_map('strval', $value));
    }

    /**
     * @param   integer  $value  The policy version.
     *
     * @return  integer
     */
    protected function onSetPolicyVersion(int $value): int
    {
        return $value;
    }

    /**
     * @param   integer  $value  The user id.
     *
     * @return  integer
     */
    protected function onSetUserId(int $value): int
    {
        return $value;
    }
}
