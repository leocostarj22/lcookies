<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  plg_content_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * {lcookies-table}: the categories with their services and cookies, for the cookie policy page.
 * Uses the template's classes (Bootstrap in Cassiopeia); text-reset keeps the cookie names in the text
 * colour (Cassiopeia's pink <code> does not reach the WCAG AA contrast).
 *
 * Override: templates/<template>/html/layouts/lcookies/policy.php
 *
 * @var  \Joomla\CMS\Layout\FileLayout  $this
 * @var  array                          $displayData  ['contract' => array (docs/contract.schema.json),
 *                                                     'categories' => array (from the contract), 'level' => int]
 */

$texts = $displayData['contract']['texts'];
$h     = 'h' . (int) $displayData['level'];
$types = ['cookie' => 'typeCookie', 'local' => 'typeLocal', 'session' => 'typeSession', 'pixel' => 'typePixel'];
?>
<div class="lcookies-policy">
    <?php foreach ($displayData['categories'] as $category) : ?>
        <section class="lcookies-policy__category" data-lcookies-policy="<?php echo $this->escape($category['alias']); ?>">
            <<?php echo $h; ?>><?php echo $this->escape($category['title']); ?></<?php echo $h; ?>>
            <?php if ($category['description'] !== '') : ?>
                <p><?php echo $this->escape($category['description']); ?></p>
            <?php endif; ?>
            <?php if (!$category['services']) : ?>
                <p><?php echo $this->escape($texts['noCookies']); ?></p>
            <?php else : ?>
                <?php // Focusable so that keyboard users can scroll a table wider than the column. ?>
                <div class="table-responsive" role="region" aria-label="<?php echo $this->escape($category['title']); ?>" tabindex="0">
                    <table class="table table-striped lcookies-policy__table">
                        <caption class="visually-hidden"><?php echo $this->escape($category['title']); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?php echo Text::_('PLG_CONTENT_LCOOKIES_COL_SERVICE'); ?></th>
                                <th scope="col"><?php echo $this->escape($texts['colName']); ?></th>
                                <th scope="col"><?php echo $this->escape($texts['colType']); ?></th>
                                <th scope="col"><?php echo $this->escape($texts['colDuration']); ?></th>
                                <th scope="col"><?php echo $this->escape($texts['colDescription']); ?></th>
                            </tr>
                        </thead>
                        <?php foreach ($category['services'] as $service) : ?>
                            <?php $cookies = $service['cookies'] ?: [null]; ?>
                            <tbody>
                                <?php foreach ($cookies as $i => $cookie) : ?>
                                    <tr>
                                        <?php if ($i === 0) : ?>
                                            <th scope="rowgroup" rowspan="<?php echo \count($cookies); ?>">
                                                <?php echo $this->escape($service['title']); ?>
                                                <?php if ($service['provider'] !== '') : ?>
                                                    <br><small><?php echo $this->escape($texts['provider']); ?>: <?php echo $this->escape($service['provider']); ?></small>
                                                <?php endif; ?>
                                                <?php if ($service['privacyUrl']) : ?>
                                                    <br><small><a href="<?php echo $this->escape($service['privacyUrl']); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape($texts['privacyPolicy']); ?></a></small>
                                                <?php endif; ?>
                                            </th>
                                        <?php endif; ?>
                                        <?php if ($cookie === null) : ?>
                                            <td colspan="4"><?php echo $this->escape($texts['noCookies']); ?></td>
                                        <?php else : ?>
                                            <td><code class="text-reset"><?php echo $this->escape($cookie['name'] . ($cookie['match'] === 'prefix' ? '*' : '')); ?></code></td>
                                            <td><?php echo $this->escape($texts[$types[$cookie['type']] ?? 'typeCookie']); ?></td>
                                            <td><?php echo $this->escape($cookie['duration']); ?></td>
                                            <td><?php echo $this->escape($cookie['description']); ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
