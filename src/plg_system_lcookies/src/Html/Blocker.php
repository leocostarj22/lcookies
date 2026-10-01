<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  plg_system_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Plugin\System\Lcookies\Html;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Rewrites the page so that scripts and iframes of optional services do not run or load.
 *
 * Blocking is always done on the server, whatever the visitor chose, so the output is the same
 * for everyone and can be cached; the JavaScript unblocks what the visitor accepted.
 *
 * - `<script>` whose src or inline code matches a pattern: `type="text/plain"` plus
 *   `data-lcookies-category` / `data-lcookies-service` (and `data-lcookies-type="module"`).
 * - `<iframe>` whose src matches: `src` becomes `data-lcookies-src`, optionally preceded by a
 *   placeholder that explains why the content is missing.
 *
 * Elements that already carry `data-lcookies-category` (marked by hand) are left alone.
 */
final class Blocker
{
    /**
     * Compiled rules: [category alias, category title, service alias, service title, patterns].
     *
     * @var  array<int, array{0: string, 1: string, 2: string, 3: string, 4: array<int, array{0: bool, 1: string}>}>
     */
    private array $rules = [];

    /**
     * Placeholder HTML with the {id}, {service}, {category} and {categoryAlias} tokens, or null.
     *
     * @var  ?string
     */
    private ?string $placeholder;

    /**
     * Counter used to link placeholders to their iframes.
     *
     * @var  integer
     */
    private int $counter = 0;

    /**
     * @param   array    $contract     The contract (see ContractBuilder); only optional categories are used.
     * @param   ?string  $placeholder  Placeholder HTML template, null to block iframes without placeholder.
     */
    public function __construct(array $contract, ?string $placeholder = null)
    {
        $this->placeholder = $placeholder;

        foreach ($contract['categories'] ?? [] as $category) {
            if (!empty($category['required'])) {
                continue;
            }

            foreach ($category['services'] as $service) {
                $patterns = [];

                foreach ($service['patterns'] as $pattern) {
                    if (\strlen($pattern) > 2 && $pattern[0] === '/' && substr($pattern, -1) === '/') {
                        $regex = '~' . str_replace('~', '\~', substr($pattern, 1, -1)) . '~i';

                        if (@preg_match($regex, '') !== false) {
                            $patterns[] = [true, $regex];
                        }
                    } else {
                        $patterns[] = [false, $pattern];
                    }
                }

                if ($patterns) {
                    $this->rules[] = [$category['alias'], $category['title'], $service['alias'], $service['title'], $patterns];
                }
            }
        }
    }

    /**
     * Whether there is anything to block.
     *
     * @return  boolean
     */
    public function hasRules(): bool
    {
        return $this->rules !== [];
    }

    /**
     * Blocks the matching scripts and iframes of an HTML document.
     *
     * @param   string  $html  The page.
     *
     * @return  string
     */
    public function process(string $html): string
    {
        if (!$this->rules) {
            return $html;
        }

        // Unrolled loop instead of a lazy .*? so that long inline scripts do not hit the backtrack limit.
        $result = preg_replace_callback(
            '#<script\b([^>]*)>([^<]*(?:<(?!/script\s*>)[^<]*)*)</script\s*>#i',
            fn (array $m): string => $this->script($m[0], $m[1], $m[2]),
            $html
        );

        $html   = $result ?? $html;
        $result = preg_replace_callback(
            '#<iframe\b([^>]*)>#i',
            fn (array $m): string => $this->iframe($m[0], $m[1]),
            $html
        );

        return $result ?? $html;
    }

    /**
     * Finds the rule a script address or code belongs to.
     *
     * @param   string  $subject  Address and/or code.
     *
     * @return  ?array  The rule, or null if nothing matches.
     */
    public function match(string $subject): ?array
    {
        if (trim($subject) === '') {
            return null;
        }

        foreach ($this->rules as $rule) {
            foreach ($rule[4] as [$isRegex, $pattern]) {
                if ($isRegex ? preg_match($pattern, $subject) === 1 : stripos($subject, $pattern) !== false) {
                    return $rule;
                }
            }
        }

        return null;
    }

    /**
     * Blocks one script element when it matches.
     *
     * @param   string  $tag      The whole element.
     * @param   string  $attribs  Attributes of the opening tag.
     * @param   string  $code     Inline code.
     *
     * @return  string
     */
    private function script(string $tag, string $attribs, string $code): string
    {
        if (preg_match('#\sdata-lcookies-(?:category|skip)\b#i', $attribs)) {
            return $tag;
        }

        $type = strtolower(trim((string) $this->attribute($attribs, 'type')));

        if ($type !== '' && $type !== 'module' && !str_contains($type, 'javascript') && !str_contains($type, 'ecmascript')) {
            return $tag;
        }

        $rule = $this->match($this->attribute($attribs, 'src') . "\n" . $code);

        if ($rule === null) {
            return $tag;
        }

        $attribs = preg_replace('#\stype\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $attribs);

        return '<script type="text/plain"' . $this->marks($rule)
            . ($type === 'module' ? ' data-lcookies-type="module"' : '')
            . $attribs . '>' . $code . '</script>';
    }

    /**
     * Blocks one iframe when its address matches.
     *
     * @param   string  $tag      The opening tag.
     * @param   string  $attribs  Its attributes.
     *
     * @return  string
     */
    private function iframe(string $tag, string $attribs): string
    {
        if (preg_match('#\sdata-lcookies-(?:category|skip|src)\b#i', $attribs)) {
            return $tag;
        }

        $rule = $this->match((string) $this->attribute($attribs, 'src'));

        if ($rule === null) {
            return $tag;
        }

        $id      = 'lc' . ++$this->counter;
        $attribs = preg_replace('#\ssrc\s*=#i', ' data-lcookies-src=', $attribs, 1);
        $iframe  = '<iframe' . $this->marks($rule) . ' data-lcookies-id="' . $id . '"'
            . ($this->placeholder !== null ? ' hidden' : '') . $attribs . '>';

        if ($this->placeholder === null) {
            return $iframe;
        }

        return strtr($this->placeholder, [
            '{id}'            => $id,
            '{service}'       => htmlspecialchars($rule[3], ENT_QUOTES, 'UTF-8'),
            '{category}'      => htmlspecialchars($rule[1], ENT_QUOTES, 'UTF-8'),
            '{categoryAlias}' => htmlspecialchars($rule[0], ENT_QUOTES, 'UTF-8'),
        ]) . $iframe;
    }

    /**
     * Data attributes that tie an element to its category and service.
     *
     * @param   array  $rule  The matching rule.
     *
     * @return  string
     */
    private function marks(array $rule): string
    {
        return ' data-lcookies-category="' . htmlspecialchars($rule[0], ENT_QUOTES, 'UTF-8') . '"'
            . ' data-lcookies-service="' . htmlspecialchars($rule[2], ENT_QUOTES, 'UTF-8') . '"';
    }

    /**
     * Reads an attribute value (entities decoded) from a tag's attribute string.
     *
     * @param   string  $attribs  Attributes.
     * @param   string  $name     Attribute name.
     *
     * @return  ?string
     */
    private function attribute(string $attribs, string $name): ?string
    {
        if (!preg_match('#(?:^|\s)' . $name . '\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))#i', $attribs, $m)) {
            return null;
        }

        return html_entity_decode($m[1] . ($m[2] ?? '') . ($m[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
