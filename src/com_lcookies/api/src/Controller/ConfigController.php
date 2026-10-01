<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Api\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\MVC\Controller\Exception\ResourceNotFound;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * /v1/lcookies/config — the frontend contract (docs/contract.schema.json), public.
 *
 * It holds nothing that the site pages do not already publish. `?language=pt-PT` picks the
 * language of the texts (an installed site language), otherwise the default site language is used.
 */
class ConfigController extends ApiController
{
    /**
     * @var  string
     */
    protected $contentType = 'config';

    /**
     * @var  string
     */
    protected $default_view = 'config';

    /**
     * Switches to the requested site language, then displays the contract.
     *
     * @param   integer  $id  Not used.
     *
     * @return  static
     *
     * @throws  ResourceNotFound  When the language is not installed on the site.
     */
    public function displayItem($id = null)
    {
        $tag = $this->input->getString('language', '') ?: ComponentHelper::getParams('com_languages')->get('site', 'en-GB');

        if (!LanguageHelper::exists($tag, JPATH_SITE)) {
            throw new ResourceNotFound(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        $language = $this->app->getLanguage();

        if ($language->getTag() !== $tag) {
            $language = Factory::getContainer()->get(LanguageFactoryInterface::class)->createLanguage($tag, (bool) $this->app->get('debug_lang'));
            $this->app->loadLanguage($language);

            // Text::_() translates with Factory::$language; the core language filter plugin switches it the same way.
            Factory::$language = $language;
        }

        return parent::displayItem();
    }
}
