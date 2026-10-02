/**
 * Language check of the LCookies frontend in a real browser: everything a visitor sees comes from
 * the language files of the site language, with no language constants and no text of the other
 * language left.
 *
 * Usage: node tests/e2e_lang.mjs <site url> <joomla root> <site language: pt-PT|en-GB>
 *   e.g. node tests/e2e_lang.mjs http://127.0.0.1:8106 /tmp/claude-1000/lc/j6.0.0 pt-PT
 * Env: LC_PHP, LC_CHROME (as tests/e2e_front.mjs), LC_SHOTS (folder for screenshots to review).
 *
 * The site must already use that language (com_languages "site"). Uses tests/fixture.php (test
 * services, the shortcodes module and mod_lcookies): run it only against a test site.
 */

import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const project = join(dirname(fileURLToPath(import.meta.url)), '..');
const [url, root, tag] = process.argv.slice(2);

if (!url || !root || !['pt-PT', 'en-GB'].includes(tag)) {
  console.error('Usage: node tests/e2e_lang.mjs <site url> <joomla root> <pt-PT|en-GB>');
  process.exit(1);
}

const other = tag === 'pt-PT' ? 'en-GB' : 'pt-PT';
const PHP = process.env.LC_PHP || '/tmp/claude-1000/lc/php';
const CHROME = process.env.LC_CHROME || join(homedir(), '.cache/ms-playwright/chromium-1234/chrome-linux64/chrome');
const SHOTS = process.env.LC_SHOTS || '';
const FILES = ['com_lcookies/site/language/%s/com_lcookies.ini', 'mod_lcookies/language/%s/mod_lcookies.ini',
  'plg_content_lcookies/language/%s/plg_content_lcookies.ini'];

let passed = 0;
const failures = [];

function check(name, condition, detail = '') {
  if (condition) {
    passed += 1;
    console.log(`  ok   ${name}`);
  } else {
    failures.push(name);
    console.log(`  FAIL ${name} ${detail}`);
  }
}

function strings(language) {
  const result = {};

  FILES.forEach((file) => {
    readFileSync(join(project, 'src', file.replace('%s', language)), 'utf8').split('\n').forEach((line) => {
      const m = line.match(/^([A-Z0-9_]+)="(.*)"\s*$/);

      if (m) {
        result[m[1]] = m[2].replace(/\\"/g, '"');
      }
    });
  });

  return result;
}

const own = strings(tag);
const foreign = strings(other);
// Texts that only exist in the other language (long enough not to match by chance), split at the
// placeholders (%s, %d, {service}...) so that filled-in texts are found too.
const leftovers = [...new Set(Object.keys(foreign)
  .filter((key) => own[key] !== foreign[key])
  // Option labels of the module and plugin are backend texts; descriptions of the data are not.
  .filter((key) => /^COM_LCOOKIES_(CAT|SVC|COOKIE)_.*_DESC$/.test(key) || !/_XML_|_FIELD_|_LABEL$|_DESC$|HEADING_LEVEL/.test(key))
  .flatMap((key) => foreign[key].split(/%\d?\$?[sd]|\{\w+\}/))
  .map((part) => part.trim())
  .filter((part) => part.length >= 8 && !Object.values(own).some((value) => value.includes(part))))];

const fixture = (...args) => execFileSync(PHP, [join(project, 'tests/fixture.php'), root, ...args]).toString();

async function shot(page, name) {
  if (SHOTS) {
    mkdirSync(SHOTS, { recursive: true });
    await page.screenshot({ path: join(SHOTS, `lang-${tag}-${name}.png`), fullPage: false });
  }
}

function problems(text) {
  return {
    constants: [...new Set(text.match(/\b(?:COM|MOD|PLG)_LCOOKIES_[A-Z0-9_]+/g) || [])],
    foreign: leftovers.filter((part) => text.includes(part)),
  };
}

async function main() {
  console.log(fixture('setup').trim());
  fixture('params', 'layout=box-bottom-left', 'theme=light', 'floating_button=1', 'log_consents=1', 'gcm_enabled=0', 'respect_gpc=1',
    'text_title=', 'text_message=', 'text_accept=', 'text_reject=', 'text_settings=', 'text_save=');

  const browser = await chromium.launch({ executablePath: CHROME });

  try {
    const context = await browser.newContext({ locale: tag, viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();
    const errors = [];

    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(url);
    await page.waitForFunction(() => typeof window.LCookies?.open === 'function');

    check(`page language is ${tag}`, (await page.getAttribute('html', 'lang'))?.toLowerCase() === tag.toLowerCase(), await page.getAttribute('html', 'lang'));

    const texts = await page.evaluate(() => window.Joomla.getOptions('lcookies').texts);
    const uiValues = Object.entries(own).filter(([key]) => key.startsWith('COM_LCOOKIES_UI_')).map(([, value]) => value);
    const notFromFile = Object.entries(texts).filter(([, value]) => !uiValues.includes(value)).map(([key]) => key);
    check(`all interface texts of the contract come from the ${tag} file`, notFromFile.length === 0, notFromFile.join(', '));

    // Banner
    let p = problems(await page.locator('[data-lcookies-banner]').innerText());
    check('banner: no constants, nothing in the other language', !p.constants.length && !p.foreign.length, JSON.stringify(p));
    await shot(page, '1-banner');

    // Preferences, with every service opened
    await page.click('[data-lcookies-banner] [data-lcookies-action="settings"]');
    await page.evaluate(() => document.querySelectorAll('[data-lcookies-preferences] details').forEach((d) => { d.open = true; }));
    const prefs = await page.locator('[data-lcookies-preferences]').innerText();
    p = problems(prefs);
    check('preferences: no constants, nothing in the other language', !p.constants.length && !p.foreign.length, JSON.stringify(p));
    const duration = new RegExp(own.COM_LCOOKIES_DURATION_N_YEAR.replace('%d', '\\d+'));
    check('preferences: durations translated', duration.test(prefs) && prefs.includes(own.COM_LCOOKIES_DURATION_SESSION), prefs.slice(0, 200));
    check('preferences: category titles translated', [own.COM_LCOOKIES_CAT_NECESSARY, own.COM_LCOOKIES_CAT_STATISTICS, own.COM_LCOOKIES_CAT_MARKETING]
      .every((title) => prefs.includes(title)));
    await shot(page, '2-preferences');
    await page.keyboard.press('Escape');

    // Blocked iframe placeholder, module status and the policy page shortcodes
    const blocked = await page.locator('#lctest .lcookies-placeholder').first().innerText().catch(() => '');
    p = problems(blocked);
    check('placeholder of a blocked iframe translated', blocked !== '' && !p.constants.length && !p.foreign.length, blocked);
    const module = await page.locator('.mod-lcookies').innerText();
    p = problems(module);
    check('module: status before a choice translated', module.includes(own.MOD_LCOOKIES_STATUS_NONE) && !p.constants.length && !p.foreign.length, module);
    const policy = await page.locator('#lctest-all').innerText();
    p = problems(policy);
    check('policy table translated', policy.includes(own.PLG_CONTENT_LCOOKIES_COL_SERVICE) && policy.includes(own.COM_LCOOKIES_UI_COL_DURATION)
      && !p.constants.length && !p.foreign.length, JSON.stringify(p));

    // After a choice: floating button and module status with a date
    await page.click('[data-lcookies-banner] [data-lcookies-action="reject"]');
    await page.waitForTimeout(300);
    const after = await page.locator('.mod-lcookies').innerText();
    check('module: status after a choice translated', after.includes(own.MOD_LCOOKIES_STATUS_NECESSARY)
      && after.includes(own.MOD_LCOOKIES_STATUS_DATE.split('%s')[0].trim()), after);
    check('floating button label translated', (await page.getAttribute('[data-lcookies-floating]', 'aria-label')) === own.COM_LCOOKIES_UI_FLOATING);
    await shot(page, '3-after-choice');
    check('no JavaScript errors', errors.length === 0, errors.join(' | '));
    await context.close();
  } finally {
    await browser.close();
    console.log(fixture('teardown').trim());
  }

  console.log(`\n${passed}/${passed + failures.length} checks passed`);

  if (failures.length) {
    process.exitCode = 1;
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
