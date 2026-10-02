<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Table;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Service (cookie provider) table.
 */
class ServiceTable extends AbstractLcookiesTable
{
    /**
     * @param   DatabaseInterface     $db          Database connector object.
     * @param   ?DispatcherInterface  $dispatcher  Event dispatcher for this table.
     */
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__lcookies_services', $db, $dispatcher);
    }

    /**
     * Validates the row before storing it.
     *
     * @return  boolean
     */
    public function check()
    {
        if (!parent::check()) {
            return false;
        }

        $this->title = trim((string) $this->title);

        if ($this->title === '') {
            return $this->fail('COM_LCOOKIES_ERROR_TITLE_REQUIRED');
        }

        if (!(int) $this->category_id) {
            return $this->fail('COM_LCOOKIES_ERROR_CATEGORY_REQUIRED');
        }

        $alias       = trim((string) $this->alias);
        $this->alias = ApplicationHelper::stringURLSafe($alias !== '' ? $alias : $this->title);

        if ($this->alias === '') {
            return $this->fail('COM_LCOOKIES_ERROR_ALIAS_REQUIRED');
        }

        if (!$this->isUnique('alias', $this->alias)) {
            return $this->fail('COM_LCOOKIES_ERROR_ALIAS_EXISTS');
        }

        $this->privacy_url = trim((string) $this->privacy_url);

        if ($this->privacy_url !== '' && !preg_match('#^https?://#i', $this->privacy_url)) {
            return $this->fail('COM_LCOOKIES_ERROR_PRIVACY_URL');
        }

        // One pattern per line: plain text is a substring match, /.../ is a regular expression.
        $patterns = preg_split('/\R/', (string) $this->block_patterns);
        $patterns = array_values(array_filter(array_map('trim', $patterns), static fn (string $line): bool => $line !== ''));

        foreach ($patterns as $pattern) {
            if (\strlen($pattern) > 2 && $pattern[0] === '/' && substr($pattern, -1) === '/'
                && !LcookiesHelper::isValidRegex(substr($pattern, 1, -1))) {
                return $this->fail('COM_LCOOKIES_ERROR_INVALID_PATTERN', $pattern);
            }
        }

        $this->block_patterns = implode("\n", $patterns);

        return true;
    }
}
