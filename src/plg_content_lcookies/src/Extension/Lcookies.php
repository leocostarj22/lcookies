<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  plg_content_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Plugin\Content\Lcookies\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Lcsilva\Component\Lcookies\Administrator\Contract\ContractBuilder;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Shortcodes for the cookie policy page:
 *
 *   {lcookies-table}                       categories, services and cookies, always up to date
 *   {lcookies-table statistics,marketing}  only these categories (aliases)
 *   {lcookies-settings}                    button that opens the cookie preferences
 *   {lcookies-settings Change my choice}   the same with another label
 *
 * The output is the same for every visitor, so pages stay cacheable. Layouts:
 * plugins/content/lcookies/layouts/lcookies/{policy,settings}.php
 * (override: templates/<template>/html/layouts/lcookies/).
 */
final class Lcookies extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Shortcode, optionally alone in a paragraph (editors wrap it in <p>, which cannot hold a table).
     */
    private const PATTERN = '#(<p\b[^>]*>\s*)?\{lcookies-(table|settings)(?:\s+([^}]*))?\}(\s*</p>)?#i';

    /**
     * @var  boolean
     */
    protected $autoloadLanguage = true;

    /**
     * Contract of the request (texts and categories in the site language).
     *
     * @var  ?array
     */
    private ?array $contract = null;

    /**
     * Returns the events this plugin listens to.
     *
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    /**
     * Replaces the shortcodes in the text of articles, custom modules and other content.
     *
     * @param   ContentPrepareEvent  $event  The event.
     *
     * @return  void
     */
    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        $item = $event->getItem();

        if (!isset($item->text) || !\is_string($item->text) || stripos($item->text, '{lcookies-') === false) {
            return;
        }

        // Search indexing, other clients or the component missing: remove the shortcodes.
        $render = $event->getContext() !== 'com_finder.indexer' && $this->getApplication()->isClient('site')
            && ComponentHelper::isEnabled('com_lcookies');

        $item->text = preg_replace_callback(self::PATTERN, function (array $m) use ($render): string {
            [$open, $name, $argument, $close] = [$m[1], strtolower($m[2]), trim($this->plain($m[3] ?? '')), $m[4] ?? ''];

            $html = $render ? ($name === 'table' ? $this->table($argument) : $this->settings($argument)) : '';

            // A table cannot stay in the paragraph; a button can, unless the paragraph is left empty.
            if ($open !== '' && $close !== '' && ($name === 'table' || $html === '')) {
                return $html;
            }

            return $open . $html . $close;
        }, $item->text);
    }

    /**
     * @param   string  $argument  Category aliases separated by commas or spaces (empty: all).
     *
     * @return  string
     */
    private function table(string $argument): string
    {
        $contract   = $this->contract();
        $aliases    = array_filter(preg_split('/[\s,]+/', strtolower($argument)) ?: []);
        $categories = array_values(array_filter(
            $contract['categories'],
            static fn (array $category): bool => !$aliases || \in_array($category['alias'], $aliases, true)
        ));

        if (!$categories) {
            return '';
        }

        return $this->render('policy', [
            'contract'   => $contract,
            'categories' => $categories,
            'level'      => min(5, max(2, (int) $this->params->get('heading_level', 3))),
        ]);
    }

    /**
     * @param   string  $label  Button text (empty: the default one).
     *
     * @return  string  Nothing while the system plugin, which opens the preferences, is disabled.
     */
    private function settings(string $label): string
    {
        if (!PluginHelper::isEnabled('system', 'lcookies')) {
            return '';
        }

        return $this->render('settings', ['label' => $label !== '' ? $label : $this->contract()['texts']['floating']]);
    }

    /**
     * @return  array
     */
    private function contract(): array
    {
        if ($this->contract === null) {
            LcookiesHelper::loadSiteLanguage($this->getApplication()->getLanguage());

            $this->contract = (new ContractBuilder($this->getDatabase(), ComponentHelper::getParams('com_lcookies')))->build();
        }

        return $this->contract;
    }

    /**
     * Shortcode argument as plain text (editors may add tags and entities such as &nbsp;).
     *
     * @param   string  $value  The argument.
     *
     * @return  string
     */
    private function plain(string $value): string
    {
        return str_replace("\u{a0}", ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * @param   string  $name  Layout name.
     * @param   array   $data  Display data.
     *
     * @return  string
     */
    private function render(string $name, array $data): string
    {
        $layout = new FileLayout('lcookies.' . $name);
        $layout->setIncludePaths(array_merge($layout->getIncludePaths(), [JPATH_PLUGINS . '/content/lcookies/layouts']));

        return $layout->render($data);
    }
}
