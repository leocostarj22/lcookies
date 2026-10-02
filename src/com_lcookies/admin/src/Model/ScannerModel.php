<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Lcsilva\Component\Lcookies\Administrator\Scanner\Scanner;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Cookie scans: start, server pass page by page, browser results, classification and history.
 *
 * A scan row holds, while running, `{"pages": [...], "server": {url: {status, cookies}}}` and, when
 * done, the classified results (see finish()).
 */
class ScannerModel extends BaseDatabaseModel
{
    /**
     * Scans kept in the history.
     */
    public const KEEP = 20;

    /**
     * A scan still running after this many seconds was abandoned (browser closed).
     */
    public const ABANDONED = 3600;

    /**
     * Most names per list in the browser results.
     */
    private const MAX_NAMES = 500;

    /**
     * Starts a scan.
     *
     * @param   string  $source  manual or task.
     *
     * @return  array  `id`, `pages` (absolute URLs) and `param` (query parameter of the browser pass, "<token>").
     */
    public function start(string $source = 'manual'): array
    {
        $db      = $this->getDatabase();
        $pages   = $this->scanner()->pages();
        $now     = Factory::getDate()->toSql();
        $userId  = (int) Factory::getApplication()->getIdentity()?->id;
        $results = json_encode(['pages' => $pages, 'server' => new \stdClass()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $status  = 'running';
        $count   = \count($pages);

        $this->abandon();

        $query = $db->createQuery()
            ->insert($db->quoteName('#__lcookies_scans'))
            ->columns($db->quoteName(['status', 'source', 'pages', 'results', 'created', 'created_by']))
            ->values(':status, :source, :pages, :results, :created, :user')
            ->bind(':status', $status)
            ->bind(':source', $source)
            ->bind(':pages', $count, ParameterType::INTEGER)
            ->bind(':results', $results)
            ->bind(':created', $now)
            ->bind(':user', $userId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        $id = (int) $db->insertid();

        return ['id' => $id, 'pages' => $pages, 'param' => Scanner::token($id, (string) Factory::getApplication()->get('secret'))];
    }

    /**
     * Server pass of one page of a running scan.
     *
     * @param   integer  $id   Scan id.
     * @param   integer  $page Index of the page in the scan.
     *
     * @return  array  See Scanner::fetch().
     *
     * @throws  \InvalidArgumentException  Unknown scan, scan not running or page out of range.
     */
    public function server(int $id, int $page): array
    {
        $results = $this->running($id);
        $url     = $results['pages'][$page] ?? null;

        if (!\is_string($url)) {
            throw new \InvalidArgumentException('Unknown page.');
        }

        $fetched                  = $this->scanner()->fetch($url);
        $results['server'][$page] = $fetched;

        $this->store($id, ['results' => $results]);

        return $fetched;
    }

    /**
     * Ends a scan: merges the server pass and the browser pass (if any), classifies and saves.
     *
     * @param   integer  $id       Scan id.
     * @param   mixed    $browser  Browser results, null when there was no browser pass:
     *                             [{page: index, before: {cookies: [], hosts: []}, after: {cookies: [], local: [], session: []}}, ...]
     *
     * @return  array  The scan (see getScan()).
     *
     * @throws  \InvalidArgumentException  Unknown scan or scan not running.
     */
    public function finish(int $id, mixed $browser = null): array
    {
        $running  = $this->running($id);
        $pages    = $running['pages'];
        $items    = [];
        $requests = [];
        $errors   = [];

        $add = static function (string $name, string $type, int $page, string $where, bool $before, ?int $duration = null) use (&$items): void {
            $key = $type . ':' . $name;

            $items[$key] ??= ['name' => $name, 'type' => $type, 'server' => false, 'browser' => false, 'before' => false, 'duration' => null, 'pages' => []];
            $items[$key][$where]    = true;
            $items[$key]['before']  = $items[$key]['before'] || $before;
            $items[$key]['pages'][] = $page;

            if ($duration !== null) {
                $items[$key]['duration'] = max($items[$key]['duration'] ?? 0, $duration);
            }
        };

        foreach ((array) ($running['server'] ?? []) as $page => $fetched) {
            if (($fetched['status'] ?? 0) < 200 || $fetched['status'] >= 400) {
                $errors[] = ['page' => (int) $page, 'status' => (int) ($fetched['status'] ?? 0)];
            }

            foreach ($fetched['cookies'] ?? [] as $cookie) {
                $add((string) $cookie['name'], 'cookie', (int) $page, 'server', true, (int) $cookie['duration']);
            }
        }

        $browser = $this->browserResults($browser, \count($pages));

        foreach ($browser ?? [] as $result) {
            $page = $result['page'];

            foreach ($result['before']['cookies'] as $name) {
                $add($name, 'cookie', $page, 'browser', true);
            }

            foreach ($result['after']['cookies'] as $name) {
                $add($name, 'cookie', $page, 'browser', false);
            }

            foreach (['local', 'session'] as $type) {
                foreach ($result['after'][$type] as $name) {
                    $add($name, $type, $page, 'browser', false);
                }
            }

            foreach ($result['before']['hosts'] as $host) {
                $requests[$host] ??= ['host' => $host, 'pages' => []];
                $requests[$host]['pages'][] = $page;
            }
        }

        foreach ($items as &$item) {
            $item['pages'] = array_values(array_unique($item['pages']));
        }

        unset($item);

        foreach ($requests as &$request) {
            $request['pages'] = array_values(array_unique($request['pages']));
        }

        unset($request);

        [$items, $requests] = $this->scanner()->classify(array_values($items), array_values($requests));

        usort($items, static fn (array $a, array $b): int => [$b['issue'], $a['declared'] === null ? 0 : 1, $a['name']] <=> [$a['issue'], $b['declared'] === null ? 0 : 1, $b['name']]);

        $unknown = \count(array_filter($items, static fn (array $item): bool => $item['declared'] === null));
        $issues  = \count(array_filter($items, static fn (array $item): bool => $item['issue']))
            + \count(array_filter($requests, static fn (array $request): bool => $request['issue']));

        $this->store($id, [
            'status'   => 'done',
            'results'  => ['pages' => $pages, 'browser' => $browser !== null, 'items' => $items, 'requests' => $requests, 'errors' => $errors],
            'unknown'  => $unknown,
            'issues'   => $issues,
            'finished' => Factory::getDate()->toSql(),
        ]);

        $this->prune();

        return $this->getScan($id);
    }

    /**
     * A scan with its decoded results.
     *
     * @param   integer  $id  Scan id (0: the latest finished one).
     *
     * @return  ?array  Null when there is none.
     */
    public function getScan(int $id = 0): ?array
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__lcookies_scans'))
            ->order($db->quoteName('id') . ' DESC')
            ->setLimit(1);

        if ($id > 0) {
            $query->where($db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
        } else {
            $status = 'done';
            $query->where($db->quoteName('status') . ' = :status')->bind(':status', $status);
        }

        $row = $db->setQuery($query)->loadAssoc();

        if (!$row) {
            return null;
        }

        $row['results'] = json_decode((string) $row['results'], true) ?: [];

        return $row;
    }

    /**
     * @return  array  Latest scans (without results), newest first.
     */
    public function getHistory(): array
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'status', 'source', 'pages', 'unknown', 'issues', 'created', 'finished']))
            ->from($db->quoteName('#__lcookies_scans'))
            ->order($db->quoteName('id') . ' DESC')
            ->setLimit(self::KEEP);

        return $db->setQuery($query)->loadObjectList();
    }

    /**
     * @return  Scanner
     */
    private function scanner(): Scanner
    {
        return new Scanner($this->getDatabase(), ComponentHelper::getParams('com_lcookies'));
    }

    /**
     * Results of a running scan.
     *
     * @param   integer  $id  Scan id.
     *
     * @return  array
     *
     * @throws  \InvalidArgumentException
     */
    private function running(int $id): array
    {
        $scan = $id > 0 ? $this->getScan($id) : null;

        if ($scan === null || $scan['status'] !== 'running' || !\is_array($scan['results']['pages'] ?? null)) {
            throw new \InvalidArgumentException('Unknown scan.');
        }

        return $scan['results'];
    }

    /**
     * Strict validation of the browser results; anything malformed is dropped.
     *
     * @param   mixed    $data   Decoded request data.
     * @param   integer  $pages  Number of pages of the scan.
     *
     * @return  ?array
     */
    private function browserResults(mixed $data, int $pages): ?array
    {
        if (!\is_array($data)) {
            return null;
        }

        $names = static fn (mixed $list): array => \is_array($list) ? \array_slice(array_values(array_unique(array_filter(
            $list,
            static fn (mixed $name): bool => \is_string($name) && $name !== '' && \strlen($name) <= 255 && !preg_match('/[\x00-\x1f]/', $name)
        ))), 0, self::MAX_NAMES) : [];

        $hosts = static fn (mixed $list): array => array_values(array_filter(
            $names($list),
            static fn (string $host): bool => (bool) preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*$/i', $host)
        ));

        $results = [];

        foreach (\array_slice($data, 0, $pages) as $result) {
            $page = \is_array($result) ? ($result['page'] ?? null) : null;

            if (!\is_int($page) || $page < 0 || $page >= $pages) {
                continue;
            }

            $results[] = [
                'page'   => $page,
                'before' => ['cookies' => $names($result['before']['cookies'] ?? null), 'hosts' => array_map('strtolower', $hosts($result['before']['hosts'] ?? null))],
                'after'  => [
                    'cookies' => $names($result['after']['cookies'] ?? null),
                    'local'   => $names($result['after']['local'] ?? null),
                    'session' => $names($result['after']['session'] ?? null),
                ],
            ];
        }

        return $results;
    }

    /**
     * Updates columns of a scan.
     *
     * @param   integer  $id      Scan id.
     * @param   array    $values  Column => value (`results` is encoded).
     *
     * @return  void
     */
    private function store(int $id, array $values): void
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->update($db->quoteName('#__lcookies_scans'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        if (isset($values['results'])) {
            $values['results'] = json_encode($values['results'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        foreach (array_keys($values) as $column) {
            $query->set($db->quoteName($column) . ' = :' . $column)
                ->bind(':' . $column, $values[$column], \is_int($values[$column]) ? ParameterType::INTEGER : ParameterType::STRING);
        }

        $db->setQuery($query)->execute();
    }

    /**
     * Marks scans abandoned while running as failed.
     *
     * @return  void
     */
    private function abandon(): void
    {
        $db      = $this->getDatabase();
        $running = 'running';
        $failed  = 'failed';
        $limit   = Factory::getDate('-' . self::ABANDONED . ' seconds')->toSql();
        $query   = $db->createQuery()
            ->update($db->quoteName('#__lcookies_scans'))
            ->set($db->quoteName('status') . ' = :failed')
            ->where([$db->quoteName('status') . ' = :running', $db->quoteName('created') . ' < :limit'])
            ->bind(':failed', $failed)
            ->bind(':running', $running)
            ->bind(':limit', $limit);

        $db->setQuery($query)->execute();
    }

    /**
     * Keeps the latest KEEP scans.
     *
     * @return  void
     */
    private function prune(): void
    {
        $db  = $this->getDatabase();
        $ids = $db->setQuery(
            $db->createQuery()
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__lcookies_scans'))
                ->order($db->quoteName('id') . ' DESC')
                ->setLimit(1000, self::KEEP)
        )->loadColumn();

        if ($ids) {
            $db->setQuery(
                $db->createQuery()
                    ->delete($db->quoteName('#__lcookies_scans'))
                    ->whereIn($db->quoteName('id'), array_map('intval', $ids))
            )->execute();
        }
    }
}
