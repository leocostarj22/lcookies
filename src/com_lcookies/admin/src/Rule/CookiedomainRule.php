<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Rule;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Lcsilva\Component\Lcookies\Administrator\Helper\CookieDomain;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Validates the domain of the consent cookie: a domain name that the address of the site belongs
 * to (the form filter has already normalised it, see CookieDomain::filter()).
 */
class CookiedomainRule extends FormRule
{
    /**
     * @param   \SimpleXMLElement  $element  The field.
     * @param   mixed              $value    The filtered value.
     * @param   ?string            $group    Field group.
     * @param   ?Registry          $input    All the form data.
     * @param   ?Form              $form     The form.
     *
     * @return  boolean|\UnexpectedValueException
     */
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        $value = (string) $value;

        if ($value === '') {
            return true;
        }

        $domain = CookieDomain::normalize($value);

        if ($domain === '' || $domain !== $value) {
            return new \UnexpectedValueException(Text::sprintf('COM_LCOOKIES_CONFIG_COOKIE_DOMAIN_NOT_DOMAIN', $value));
        }

        $host = (new Uri(Uri::root()))->getHost();

        if (CookieDomain::matches($domain, $host)) {
            return true;
        }

        return new \UnexpectedValueException(Text::sprintf('COM_LCOOKIES_CONFIG_COOKIE_DOMAIN_INVALID', $value, $host));
    }
}
