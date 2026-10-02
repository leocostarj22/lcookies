<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Model;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;
use Lcsilva\Component\Lcookies\Administrator\Table\AbstractLcookiesTable;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Export, import and the preset library. All three use the same JSON format:
 *
 *     {"format": "lcookies", "version": 1,
 *      "categories": [{alias, title, description, required, gcm_types[], state}],
 *      "services": [{alias, category (alias), title, provider, privacy_url, description,
 *                    block_patterns[], head_code, body_code, state,
 *                    cookies: [{name, display_name, match_type, type, domain, duration_value, duration_unit, description, state}]}]}
 *
 * Records are matched by alias (cookies by name inside their service). Every record goes through
 * the tables, so the rules of the backend forms apply (unique aliases, valid patterns, core
 * categories locked).
 */
class TransferModel extends BaseDatabaseModel
{
    public const FORMAT = 'lcookies';

    public const VERSION = 1;

    /**
     * Largest file accepted by import(), in bytes.
     */
    public const MAX_SIZE = 2097152;

    /**
     * Category used when a service names a category that does not exist.
     */
    private const FALLBACK_CATEGORY = 'unclassified';

    /**
     * Every category, service and cookie that is not trashed.
     *
     * @return  array
     */
    public function export(): array
    {
        $db = $this->getDatabase();

        $categories = $db->setQuery(
            $db->createQuery()
                ->select($db->quoteName(['id', 'alias', 'title', 'description', 'required', 'gcm_types', 'state']))
                ->from($db->quoteName('#__lcookies_categories'))
                ->where($db->quoteName('state') . ' IN (0, 1)')
                ->order($db->quoteName('ordering') . ' ASC')
        )->loadObjectList('id');

        $services = $db->setQuery(
            $db->createQuery()
                ->select($db->quoteName(['id', 'category_id', 'alias', 'title', 'provider', 'privacy_url', 'description', 'block_patterns', 'head_code', 'body_code', 'state']))
                ->from($db->quoteName('#__lcookies_services'))
                ->where($db->quoteName('state') . ' IN (0, 1)')
                ->order($db->quoteName('ordering') . ' ASC')
        )->loadObjectList();

        $cookies = $db->setQuery(
            $db->createQuery()
                ->select($db->quoteName(['service_id', 'name', 'display_name', 'match_type', 'type', 'domain', 'duration_value', 'duration_unit', 'description', 'state']))
                ->from($db->quoteName('#__lcookies_cookies'))
                ->where($db->quoteName('state') . ' IN (0, 1)')
                ->order($db->quoteName('ordering') . ' ASC')
        )->loadObjectList();

        $data = ['format' => self::FORMAT, 'version' => self::VERSION, 'categories' => [], 'services' => []];

        foreach ($categories as $category) {
            $data['categories'][] = [
                'alias'       => $category->alias,
                'title'       => $category->title,
                'description' => (string) $category->description,
                'required'    => (int) $category->required,
                'gcm_types'   => json_decode((string) $category->gcm_types, true) ?: [],
                'state'       => (int) $category->state,
            ];
        }

        foreach ($services as $service) {
            if (!isset($categories[$service->category_id])) {
                continue;
            }

            $own = array_filter($cookies, fn ($cookie) => (int) $cookie->service_id === (int) $service->id);

            $data['services'][] = [
                'alias'          => $service->alias,
                'category'       => $categories[$service->category_id]->alias,
                'title'          => $service->title,
                'provider'       => $service->provider,
                'privacy_url'    => $service->privacy_url,
                'description'    => (string) $service->description,
                'block_patterns' => array_values(array_filter(preg_split('/\R/', (string) $service->block_patterns), static fn (string $line): bool => $line !== '')),
                'head_code'      => (string) $service->head_code,
                'body_code'      => (string) $service->body_code,
                'state'          => (int) $service->state,
                'cookies'        => array_values(array_map(fn ($cookie) => [
                    'name'           => $cookie->name,
                    'display_name'   => (string) $cookie->display_name,
                    'match_type'     => $cookie->match_type,
                    'type'           => $cookie->type,
                    'domain'         => $cookie->domain,
                    'duration_value' => (int) $cookie->duration_value,
                    'duration_unit'  => $cookie->duration_unit,
                    'description'    => (string) $cookie->description,
                    'state'          => (int) $cookie->state,
                ], $own)),
            ];
        }

        return $data;
    }

    /**
     * Imports a decoded file.
     *
     * @param   mixed    $data       Decoded JSON.
     * @param   boolean  $overwrite  Update records that already exist (their cookies are replaced);
     *                               otherwise they are skipped.
     * @param   string   $source     Source of the new cookies: `import` or `preset`.
     *
     * @return  array  Report: counts `added`, `updated`, `skipped` per kind and `errors` (messages).
     *
     * @throws  \InvalidArgumentException  When the data is not an LCookies file.
     */
    public function import(mixed $data, bool $overwrite = false, string $source = 'import'): array
    {
        if (!\is_array($data) || ($data['format'] ?? null) !== self::FORMAT || ($data['version'] ?? null) !== self::VERSION) {
            throw new \InvalidArgumentException(Text::_('COM_LCOOKIES_TRANSFER_ERROR_FORMAT'));
        }

        $report = [
            'categories' => ['added' => 0, 'updated' => 0, 'skipped' => 0],
            'services'   => ['added' => 0, 'updated' => 0, 'skipped' => 0],
            'cookies'    => ['added' => 0],
            'errors'     => [],
        ];

        foreach ((array) ($data['categories'] ?? []) as $category) {
            if (\is_array($category)) {
                $this->importCategory($category, $overwrite, $report);
            }
        }

        foreach ((array) ($data['services'] ?? []) as $service) {
            if (\is_array($service)) {
                $this->importService($service, $overwrite, $source, $report);
            }
        }

        // The frontend contract is cached.
        $this->cleanCache('com_lcookies');

        return $report;
    }

    /**
     * Services of the preset library, with `added` = whether a service with the alias exists.
     *
     * @return  array
     */
    public function getPresets(): array
    {
        $file    = JPATH_ADMINISTRATOR . '/components/com_lcookies/presets/presets.json';
        $presets = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $presets = \is_array($presets['services'] ?? null) ? $presets['services'] : [];
        $db      = $this->getDatabase();
        $aliases = array_column($presets, 'alias');
        $exists  = [];

        if ($aliases) {
            $exists = $db->setQuery(
                $db->createQuery()
                    ->select($db->quoteName('alias'))
                    ->from($db->quoteName('#__lcookies_services'))
                    ->whereIn($db->quoteName('alias'), $aliases, ParameterType::STRING)
            )->loadColumn();
        }

        foreach ($presets as &$preset) {
            $preset['added'] = \in_array($preset['alias'], $exists, true);
        }

        return $presets;
    }

    /**
     * Adds a service of the preset library (with its cookies).
     *
     * @param   string  $alias  Alias of the preset.
     *
     * @return  array  Report, see import().
     *
     * @throws  \InvalidArgumentException  When the preset does not exist.
     */
    public function addPreset(string $alias): array
    {
        foreach ($this->getPresets() as $preset) {
            if ($preset['alias'] === $alias) {
                unset($preset['added']);

                return $this->import(['format' => self::FORMAT, 'version' => self::VERSION, 'services' => [$preset]], false, 'preset');
            }
        }

        throw new \InvalidArgumentException(Text::_('COM_LCOOKIES_TRANSFER_ERROR_PRESET'));
    }

    /**
     * @param   array    $data       Category from the file.
     * @param   boolean  $overwrite  Update an existing category.
     * @param   array    &$report    The report.
     *
     * @return  void
     */
    private function importCategory(array $data, bool $overwrite, array &$report): void
    {
        /** @var AbstractLcookiesTable $table */
        $table = $this->getTable('Category', 'Administrator');
        $alias = (string) ($data['alias'] ?? '');
        $found = $alias !== '' && $table->load(['alias' => $alias]);

        if ($found && !$overwrite) {
            $report['categories']['skipped']++;

            return;
        }

        $row = $this->values($data, [
            'alias'       => fn ($v) => (string) $v,
            'title'       => fn ($v) => (string) $v,
            'description' => fn ($v) => (string) $v,
            'required'    => fn ($v) => (int) $v,
            'gcm_types'   => fn ($v) => array_values(array_filter((array) $v, 'is_string')),
            'state'       => fn ($v) => (int) $v === 0 ? 0 : 1,
        ], $found);

        if (!$found) {
            $table->reset();
            $table->id       = 0;
            $table->ordering = $this->nextOrdering('#__lcookies_categories');
        }

        if ($this->save($table, $row, Text::sprintf('COM_LCOOKIES_TRANSFER_CATEGORY', $alias), $report)) {
            $report['categories'][$found ? 'updated' : 'added']++;
        }
    }

    /**
     * @param   array    $data       Service from the file.
     * @param   boolean  $overwrite  Update an existing service and replace its cookies.
     * @param   string   $source     Source of the new cookies.
     * @param   array    &$report    The report.
     *
     * @return  void
     */
    private function importService(array $data, bool $overwrite, string $source, array &$report): void
    {
        /** @var AbstractLcookiesTable $table */
        $table = $this->getTable('Service', 'Administrator');
        $alias = (string) ($data['alias'] ?? '');
        $found = $alias !== '' && $table->load(['alias' => $alias]);
        $label = Text::sprintf('COM_LCOOKIES_TRANSFER_SERVICE', $alias !== '' ? $alias : (string) ($data['title'] ?? ''));

        if ($found && !$overwrite) {
            $report['services']['skipped']++;

            return;
        }

        // Code that runs on the site only from users who may store it (as in the service form).
        if (!LcookiesHelper::canStoreCode($this->getCurrentUser())) {
            if (trim((string) ($data['head_code'] ?? '')) !== '' || trim((string) ($data['body_code'] ?? '')) !== '') {
                $report['errors'][] = Text::sprintf('COM_LCOOKIES_TRANSFER_CODE_SKIPPED', $label);
            }

            unset($data['head_code'], $data['body_code']);
        }

        $row = $this->values($data, [
            'alias'          => fn ($v) => (string) $v,
            'title'          => fn ($v) => (string) $v,
            'provider'       => fn ($v) => (string) $v,
            'privacy_url'    => fn ($v) => (string) $v,
            'description'    => fn ($v) => (string) $v,
            'block_patterns' => fn ($v) => \is_array($v) ? implode("\n", array_filter($v, 'is_string')) : (string) $v,
            'head_code'      => fn ($v) => (string) $v,
            'body_code'      => fn ($v) => (string) $v,
            'state'          => fn ($v) => (int) $v === 0 ? 0 : 1,
        ], $found);

        // A category that does not exist sends the service to Unclassified.
        if (!$found || \array_key_exists('category', $data)) {
            $row['category_id'] = $this->categoryId((string) ($data['category'] ?? '')) ?: $this->categoryId(self::FALLBACK_CATEGORY);
        }

        $categoryId = (int) ($row['category_id'] ?? $table->category_id);

        if (!$found) {
            $table->reset();
            $table->id       = 0;
            $table->ordering = $this->nextOrdering('#__lcookies_services', 'category_id', $categoryId);
        }

        if (!$this->save($table, $row, $label, $report)) {
            return;
        }

        $report['services'][$found ? 'updated' : 'added']++;
        $serviceId = (int) $table->id;

        // Cookies are replaced only when the file lists them.
        if ($found && !\array_key_exists('cookies', $data)) {
            return;
        }

        if ($found) {
            $db = $this->getDatabase();
            $db->setQuery(
                $db->createQuery()
                    ->delete($db->quoteName('#__lcookies_cookies'))
                    ->where($db->quoteName('service_id') . ' = :id')
                    ->bind(':id', $serviceId, ParameterType::INTEGER)
            )->execute();
        }

        $ordering = 0;

        foreach ((array) ($data['cookies'] ?? []) as $cookie) {
            if (!\is_array($cookie)) {
                continue;
            }

            /** @var AbstractLcookiesTable $cookieTable */
            $cookieTable           = $this->getTable('Cookie', 'Administrator');
            $cookieTable->ordering = ++$ordering;

            $saved = $this->save($cookieTable, [
                'service_id'     => $serviceId,
                'name'           => (string) ($cookie['name'] ?? ''),
                'display_name'   => (string) ($cookie['display_name'] ?? ''),
                'match_type'     => (string) ($cookie['match_type'] ?? 'exact'),
                'type'           => (string) ($cookie['type'] ?? 'cookie'),
                'domain'         => (string) ($cookie['domain'] ?? ''),
                'duration_value' => (int) ($cookie['duration_value'] ?? 0),
                'duration_unit'  => (string) ($cookie['duration_unit'] ?? 'session'),
                'description'    => (string) ($cookie['description'] ?? ''),
                'source'         => $source,
                'state'          => $this->state($cookie),
            ], $label . ' / ' . (string) ($cookie['name'] ?? ''), $report);

            if ($saved) {
                $report['cookies']['added']++;
            }
        }
    }

    /**
     * Values of a record from the file. A new record gets every column (missing ones as empty
     * values, published by default); an existing one only the columns present in the file, so an
     * update never clears what the file does not mention.
     *
     * @param   array       $data     Record from the file.
     * @param   callable[]  $columns  Column => converter of the file value.
     * @param   boolean     $update   Whether the record exists.
     *
     * @return  array
     */
    private function values(array $data, array $columns, bool $update): array
    {
        $row = [];

        foreach ($columns as $column => $convert) {
            if (\array_key_exists($column, $data)) {
                $row[$column] = $convert($data[$column]);
            } elseif (!$update) {
                $row[$column] = $convert($column === 'state' ? 1 : null);
            }
        }

        return $row;
    }

    /**
     * Binds, checks and stores a row, recording the reason of a failure in the report.
     *
     * @param   AbstractLcookiesTable  $table    The table (loaded for an update).
     * @param   array                  $row      Values.
     * @param   string                 $label    Name of the record in the messages.
     * @param   array                  &$report  The report.
     *
     * @return  boolean
     */
    private function save(AbstractLcookiesTable $table, array $row, string $label, array &$report): bool
    {
        try {
            if ($table->bind($row) && $table->check() && $table->store()) {
                return true;
            }

            $report['errors'][] = $label . ': ' . ($table->getFailure() ?: Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'));
        } catch (\RuntimeException $e) {
            $report['errors'][] = $label . ': ' . $e->getMessage();
        }

        return false;
    }

    /**
     * @param   string  $alias  Category alias.
     *
     * @return  integer  The id, 0 if there is no such category (trashed ones included).
     */
    private function categoryId(string $alias): int
    {
        $db = $this->getDatabase();

        return (int) $db->setQuery(
            $db->createQuery()
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__lcookies_categories'))
                ->where($db->quoteName('alias') . ' = :alias')
                ->bind(':alias', $alias)
        )->loadResult();
    }

    /**
     * @param   string   $table   Table name.
     * @param   string   $column  Grouping column, empty for none.
     * @param   integer  $value   Value of the grouping column.
     *
     * @return  integer  Ordering for a new row at the end of its group.
     */
    private function nextOrdering(string $table, string $column = '', int $value = 0): int
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select('MAX(' . $db->quoteName('ordering') . ')')
            ->from($db->quoteName($table));

        if ($column !== '') {
            $query->where($db->quoteName($column) . ' = :value')
                ->bind(':value', $value, ParameterType::INTEGER);
        }

        return (int) $db->setQuery($query)->loadResult() + 1;
    }

    /**
     * Published (1) or unpublished (0); imports never create trashed records.
     *
     * @param   array  $data  Record from the file.
     *
     * @return  integer
     */
    private function state(array $data): int
    {
        return (int) ($data['state'] ?? 1) === 0 ? 0 : 1;
    }
}
