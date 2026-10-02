<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Lcsilva\Component\Lcookies\Administrator\Contract\ContractBuilder;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Live preview of the banner in the options (media/com_lcookies/js/preview.js): renders a page with
 * the frontend layouts, CSS and JavaScript for the options being edited, before they are saved.
 */
class PreviewController extends BaseController
{
    /**
     * Options the preview takes from the form: name => filter.
     */
    private const OPTIONS = [
        'layout'            => 'cmd',
        'theme'             => 'cmd',
        'color_primary'     => 'string',
        'border_radius'     => 'int',
        'floating_button'   => 'int',
        'floating_position' => 'cmd',
        'privacy_menuitem'  => 'int',
        'respect_gpc'       => 'int',
        'text_title'        => 'string',
        'text_message'      => 'html',
        'text_accept'       => 'string',
        'text_reject'       => 'string',
        'text_settings'     => 'string',
        'text_save'         => 'string',
    ];

    /**
     * Sends the preview page (POST `jform` = options being edited, `show` = banner or preferences,
     * `nonce` = CSP nonce of the backend page, which the srcdoc page inherits).
     *
     * @return  void
     */
    public function render(): void
    {
        $user = $this->app->getIdentity();

        if (!$this->checkToken('post', false)
            || !($user->authorise('core.admin', 'com_lcookies') || $user->authorise('core.options', 'com_lcookies'))) {
            $this->app->setHeader('status', 403, true);
            $this->app->sendHeaders();
            $this->app->close();
        }

        $params = clone ComponentHelper::getParams('com_lcookies');
        $form   = $this->input->post->get('jform', [], 'array');
        $filter = InputFilter::getInstance([], [], InputFilter::ONLY_BLOCK_DEFINED_TAGS, InputFilter::ONLY_BLOCK_DEFINED_ATTRIBUTES);

        foreach (self::OPTIONS as $name => $type) {
            if (\array_key_exists($name, $form) && \is_scalar($form[$name])) {
                $params->set($name, $filter->clean((string) $form[$name], $type));
            }
        }

        // Texts in the default language of the site, as visitors see them.
        $language = $this->app->getLanguage();
        $siteTag  = (string) ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
        $language->load('com_lcookies', JPATH_SITE, $siteTag, true)
            || $language->load('com_lcookies', JPATH_SITE . '/components/com_lcookies', $siteTag, true);

        $contract = (new ContractBuilder(Factory::getContainer()->get(DatabaseInterface::class), $params))->build();

        // Nothing is recorded and nothing is blocked in the preview.
        $contract['endpoint']  = null;
        $contract['autoblock'] = false;
        $contract['gcm']       = null;

        $color  = (string) $params->get('color_primary', '#1f5fbf');
        $nonce  = (string) $this->input->post->get('nonce', '', 'raw');
        $layout = new FileLayout('page', JPATH_ADMINISTRATOR . '/components/com_lcookies/layouts/preview');
        $html   = $layout->render([
            'contract' => $contract,
            'language' => $siteTag,
            'color'    => preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $color) ? $color : '#1f5fbf',
            'radius'   => min(32, max(0, (int) $params->get('border_radius', 8))),
            'layouts'  => $this->layoutPaths(),
            'show'     => $this->input->post->getCmd('show') === 'preferences' ? 'preferences' : 'banner',
            'nonce'    => preg_match('#^[A-Za-z0-9+/=_-]{1,512}$#', $nonce) ? $nonce : '',
        ]);

        $this->app->setHeader('Content-Type', 'text/html; charset=utf-8', true)
            ->setHeader('Cache-Control', 'no-store', true)
            ->setHeader('X-Robots-Tag', 'noindex', true);
        $this->app->sendHeaders();

        echo $html;

        $this->app->close();
    }

    /**
     * Folders of the frontend layouts: overrides of the default site template first.
     *
     * @return  string[]
     */
    private function layoutPaths(): array
    {
        $db     = Factory::getContainer()->get(DatabaseInterface::class);
        $client = 0;
        $home   = '1';
        $query  = $db->createQuery()
            ->select($db->quoteName('template'))
            ->from($db->quoteName('#__template_styles'))
            ->where([$db->quoteName('client_id') . ' = :client', $db->quoteName('home') . ' = :home'])
            ->bind(':client', $client, ParameterType::INTEGER)
            ->bind(':home', $home)
            ->setLimit(1);

        $template = (string) $db->setQuery($query)->loadResult();
        $paths    = [JPATH_PLUGINS . '/system/lcookies/layouts'];

        if ($template !== '' && preg_match('/^[\w-]+$/', $template)) {
            array_unshift($paths, JPATH_SITE . '/templates/' . $template . '/html/layouts');
        }

        return $paths;
    }
}
