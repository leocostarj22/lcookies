<?php

/**
 * @package     Lcsilva.LCookies
 * @subpackage  plg_system_lcookies
 *
 * @copyright   (C) 2026 leocostadeveloper
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Preferences dialog: one switch per optional category (never pre-selected) and the list of
 * services and cookies of each category.
 *
 * Override: templates/<template>/html/layouts/lcookies/preferences.php
 * Keep the data-lcookies-* attributes: the JavaScript relies on them.
 *
 * @var  \Joomla\CMS\Layout\FileLayout  $this
 * @var  array                          $displayData  ['contract' => array] (docs/contract.schema.json)
 */

$contract = $displayData['contract'];
$texts    = $contract['texts'];
$types    = ['cookie' => 'typeCookie', 'local' => 'typeLocal', 'session' => 'typeSession', 'pixel' => 'typePixel'];
?>
<dialog class="lcookies-prefs" data-lcookies-preferences aria-labelledby="lcookies-prefs-title" aria-describedby="lcookies-prefs-intro">
    <div class="lcookies-prefs__header">
        <h2 class="lcookies-prefs__title" id="lcookies-prefs-title"><?php echo $this->escape($texts['preferencesTitle']); ?></h2>
        <button type="button" class="lcookies-close" data-lcookies-action="close" aria-label="<?php echo $this->escape($texts['close']); ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        </button>
    </div>
    <div class="lcookies-prefs__content">
        <p class="lcookies-prefs__intro" id="lcookies-prefs-intro">
            <?php echo $this->escape($texts['preferencesIntro']); ?>
            <?php if ($contract['privacyUrl']) : ?>
                <a class="lcookies-link" href="<?php echo $this->escape($contract['privacyUrl']); ?>"><?php echo $this->escape($texts['privacyPolicy']); ?></a>
            <?php endif; ?>
        </p>
        <?php foreach ($contract['categories'] as $category) : ?>
            <?php $id = 'lcookies-cat-' . $this->escape($category['alias']); ?>
            <section class="lcookies-cat" aria-labelledby="<?php echo $id; ?>">
                <div class="lcookies-cat__head">
                    <h3 class="lcookies-cat__title" id="<?php echo $id; ?>"><?php echo $this->escape($category['title']); ?></h3>
                    <?php if ($category['required']) : ?>
                        <span class="lcookies-cat__always"><?php echo $this->escape($texts['alwaysActive']); ?></span>
                    <?php else : ?>
                        <input type="checkbox" role="switch" class="lcookies-switch" data-lcookies-toggle value="<?php echo $this->escape($category['alias']); ?>"
                            aria-labelledby="<?php echo $id; ?>" aria-describedby="<?php echo $id; ?>-desc">
                    <?php endif; ?>
                </div>
                <p class="lcookies-cat__desc" id="<?php echo $id; ?>-desc"><?php echo $this->escape($category['description']); ?></p>
                <?php if ($category['gpcOptOut']) : ?>
                    <p class="lcookies-cat__gpc" data-lcookies-gpc hidden><?php echo $this->escape($texts['gpc']); ?></p>
                <?php endif; ?>
                <?php if ($category['services']) : ?>
                    <details class="lcookies-cat__details">
                        <summary><?php echo $this->escape($texts['details']); ?></summary>
                        <?php foreach ($category['services'] as $service) : ?>
                            <div class="lcookies-svc">
                                <h4 class="lcookies-svc__title"><?php echo $this->escape($service['title']); ?></h4>
                                <?php if ($service['provider'] !== '' || $service['privacyUrl']) : ?>
                                    <p class="lcookies-svc__meta">
                                        <?php if ($service['provider'] !== '') : ?>
                                            <span><?php echo $this->escape($texts['provider']); ?>: <?php echo $this->escape($service['provider']); ?></span>
                                        <?php endif; ?>
                                        <?php if ($service['privacyUrl']) : ?>
                                            <a class="lcookies-link" href="<?php echo $this->escape($service['privacyUrl']); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape($texts['privacyPolicy']); ?></a>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($service['description'] !== '') : ?>
                                    <p class="lcookies-svc__desc"><?php echo $this->escape($service['description']); ?></p>
                                <?php endif; ?>
                                <?php if ($service['cookies']) : ?>
                                    <div class="lcookies-table-wrap">
                                        <table class="lcookies-table">
                                            <thead>
                                                <tr>
                                                    <th scope="col"><?php echo $this->escape($texts['colName']); ?></th>
                                                    <th scope="col"><?php echo $this->escape($texts['colType']); ?></th>
                                                    <th scope="col"><?php echo $this->escape($texts['colDuration']); ?></th>
                                                    <th scope="col"><?php echo $this->escape($texts['colDescription']); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($service['cookies'] as $cookie) : ?>
                                                    <tr>
                                                        <th scope="row"><code><?php echo $this->escape($cookie['name'] . ($cookie['match'] === 'prefix' ? '*' : '')); ?></code></th>
                                                        <td><?php echo $this->escape($texts[$types[$cookie['type']] ?? 'typeCookie']); ?></td>
                                                        <td><?php echo $this->escape($cookie['duration']); ?></td>
                                                        <td><?php echo $this->escape($cookie['description']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else : ?>
                                    <p class="lcookies-svc__empty"><?php echo $this->escape($texts['noCookies']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </details>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <div class="lcookies-prefs__footer">
        <button type="button" class="lcookies-btn lcookies-btn--primary" data-lcookies-action="reject"><?php echo $this->escape($texts['rejectAll']); ?></button>
        <button type="button" class="lcookies-btn lcookies-btn--secondary" data-lcookies-action="save"><?php echo $this->escape($texts['save']); ?></button>
        <button type="button" class="lcookies-btn lcookies-btn--primary" data-lcookies-action="accept"><?php echo $this->escape($texts['acceptAll']); ?></button>
    </div>
</dialog>
