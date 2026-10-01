<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 Lcsilva
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Api\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Lcsilva\Component\Lcookies\Administrator\Contract\ContractBuilder;
use Lcsilva\Component\Lcookies\Administrator\Helper\LcookiesHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The frontend contract (GET /v1/lcookies/config), in the language of the application.
 */
class ConfigModel extends BaseDatabaseModel
{
    /**
     * @return  object  The contract, with the language tag as `id`.
     */
    public function getItem(): object
    {
        $language = Factory::getApplication()->getLanguage();

        LcookiesHelper::loadSiteLanguage($language);

        $contract = (new ContractBuilder($this->getDatabase(), ComponentHelper::getParams('com_lcookies')))->build();

        return (object) (['id' => $language->getTag()] + $contract);
    }
}
