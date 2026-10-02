<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  com_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Uri\Uri;

/**
 * Page of the live preview: a sketch of a site page with the frontend markup, CSS and JavaScript of
 * plg_system_lcookies in preview mode (nothing is stored, sent or run).
 *
 * @var  array  $displayData  contract, language, color, radius, layouts (folders), show (banner|preferences)
 */

$contract = $displayData['contract'];
$root     = Uri::root();
$media    = JPATH_ROOT . '/media/plg_system_lcookies';
$asset    = static function (string $file) use ($media, $root): string {
    $min  = preg_replace('/\.(js|css)$/', '.min.$1', $file);
    $file = !JDEBUG && is_file($media . '/' . $min) ? $min : $file;

    return $root . 'media/plg_system_lcookies/' . $file . '?' . (is_file($media . '/' . $file) ? filemtime($media . '/' . $file) : '');
};
$render = static function (string $name) use ($displayData, $contract): string {
    $layout = new FileLayout('lcookies.' . $name);
    $layout->setIncludePaths($displayData['layouts']);

    return $layout->render(['contract' => $contract]);
};

$required = array_values(array_map(
    static fn (array $category): string => $category['alias'],
    array_filter($contract['categories'], static fn (array $category): bool => $category['required'])
));
$head = [
    'v'       => $contract['policyVersion'],
    'n'       => $contract['cookie']['name'],
    'd'       => $contract['expiryDays'],
    'req'     => $required,
    'rules'   => [],
    'gcm'     => null,
    'preview' => true,
];
$headFile = $media . '/js/lcookies-head' . (JDEBUG ? '' : '.min') . '.js';
$headFile = is_file($headFile) ? $headFile : $media . '/js/lcookies-head.js';
$json     = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
$escape   = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="<?php echo $escape($displayData['language']); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <base href="<?php echo $escape($root); ?>">
    <title>LCookies</title>
    <script data-lcookies-skip>window.lcookiesHead=<?php echo json_encode($head, $json); ?>;
<?php echo is_file($headFile) ? trim((string) file_get_contents($headFile)) : ''; ?></script>
    <script type="application/json" class="joomla-script-options new"><?php echo json_encode(['lcookies' => $contract], $json); ?></script>
    <link rel="stylesheet" href="<?php echo $escape($asset('css/lcookies.css')); ?>">
    <style>
        .lcookies, .lcookies-placeholder { --lcookies-primary: <?php echo $displayData['color']; ?>; --lcookies-radius: <?php echo (int) $displayData['radius']; ?>px; }
        html { color-scheme: light; }
        body { margin: 0; font: 16px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: #f4f6f9; color: #1d2733; }
        .sketch-header { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.5rem; background: #fff; border-bottom: 1px solid #dde3ea; }
        .sketch-logo { width: 7rem; height: 1.5rem; border-radius: 4px; background: #c8d1dc; }
        .sketch-nav { display: flex; gap: .75rem; margin-left: auto; }
        .sketch-nav span { width: 3.5rem; height: .75rem; border-radius: 4px; background: #dde3ea; }
        .sketch-main { max-width: 52rem; margin: 2rem auto; padding: 0 1.5rem; }
        .sketch-line { height: .8rem; margin: 0 0 .9rem; border-radius: 4px; background: #dde3ea; }
        .sketch-line--title { width: 55%; height: 1.6rem; margin-bottom: 1.5rem; background: #c8d1dc; }
        .sketch-line--short { width: 70%; }
        .sketch-box { height: 9rem; margin: 1.5rem 0; border-radius: 8px; background: #e6ebf1; }
    </style>
</head>
<body data-lcookies-preview-show="<?php echo $escape($displayData['show']); ?>">
    <div class="sketch-header" aria-hidden="true">
        <span class="sketch-logo"></span>
        <span class="sketch-nav"><span></span><span></span><span></span></span>
    </div>
    <div class="sketch-main" aria-hidden="true">
        <div class="sketch-line sketch-line--title"></div>
        <div class="sketch-line"></div>
        <div class="sketch-line"></div>
        <div class="sketch-line sketch-line--short"></div>
        <div class="sketch-box"></div>
        <div class="sketch-line"></div>
        <div class="sketch-line sketch-line--short"></div>
    </div>
    <div id="lcookies" class="lcookies" data-lcookies-theme="<?php echo $escape($contract['theme']); ?>">
        <?php echo $render('banner') . $render('preferences') . $render('floating'); ?>
        <template data-lcookies-placeholder><?php echo $render('placeholder'); ?></template>
    </div>
    <script src="<?php echo $escape($root . 'media/system/js/core' . (JDEBUG ? '' : '.min') . '.js'); ?>"></script>
    <script type="module" src="<?php echo $escape($asset('js/lcookies.js')); ?>"></script>
    <script type="module">
        // Opened on "Preferences" in the backend; links do not leave the preview.
        if (document.body.dataset.lcookiesPreviewShow === 'preferences') {
            window.LCookies.open();
        }

        document.addEventListener('click', (event) => {
            if (event.target.closest('a[href]:not([href^="#"])')) {
                event.preventDefault();
            }
        });
    </script>
</body>
</html>
