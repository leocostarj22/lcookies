<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Lcsilva\Component\Lcookies\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Live preview of the banner in the options: follows the form while it is edited, before saving.
 * Stores no value.
 */
class LcookiespreviewField extends FormField
{
    /**
     * @var  string
     */
    protected $type = 'Lcookiespreview';

    /**
     * @return  string
     */
    protected function getInput()
    {
        $document = Factory::getApplication()->getDocument();
        $document->addScriptOptions('com_lcookies.preview', [
            'url'   => Route::_('index.php?option=com_lcookies&task=preview.render', false),
            'token' => Session::getFormToken(),
        ]);

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_lcookies');
        $wa->useScript('com_lcookies.preview')
            ->addInlineStyle(
                '.lcookies-preview-group .controls{flex:1 1 100%;max-width:100%;margin-inline-start:0}'
                . '.lcookies-preview__frame{position:relative;max-width:100%;margin:0 auto;overflow:hidden;background:#f4f6f9}'
                . '.lcookies-preview__frame iframe{position:absolute;top:0;left:0;border:0;transform-origin:0 0}',
                ['name' => 'com_lcookies.preview']
            );

        $buttons = '';

        foreach (['banner' => 'COM_LCOOKIES_PREVIEW_BANNER', 'preferences' => 'COM_LCOOKIES_PREVIEW_PREFERENCES'] as $show => $label) {
            $buttons .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-lcookies-preview-show="' . $show . '">' . Text::_($label) . '</button>';
        }

        foreach (['desktop' => 'COM_LCOOKIES_PREVIEW_DESKTOP', 'mobile' => 'COM_LCOOKIES_PREVIEW_MOBILE'] as $size => $label) {
            $buttons .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-lcookies-preview-size="' . $size . '" aria-pressed="' . ($size === 'desktop' ? 'true' : 'false') . '">'
                . Text::_($label) . '</button>';
        }

        return '<div class="lcookies-preview" data-lcookies-preview>'
            . '<div class="d-flex flex-wrap gap-2 mb-2" role="toolbar" aria-label="' . htmlspecialchars(Text::_('COM_LCOOKIES_PREVIEW_LABEL'), ENT_QUOTES, 'UTF-8') . '">' . $buttons . '</div>'
            . '<div class="lcookies-preview__frame border rounded">'
            // Sandboxed without allow-same-origin: the preview cannot act with the backend session.
            . '<iframe sandbox="allow-scripts" title="' . htmlspecialchars(Text::_('COM_LCOOKIES_PREVIEW_LABEL'), ENT_QUOTES, 'UTF-8') . '"></iframe>'
            . '</div>'
            . '<p class="small mt-2 mb-0">' . Text::_('COM_LCOOKIES_PREVIEW_DESC') . '</p>'
            . '</div>';
    }
}
