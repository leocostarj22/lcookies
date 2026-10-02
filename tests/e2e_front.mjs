/**
 * End-to-end test of the LCookies frontend in a real browser (Playwright + axe-core).
 *
 * Usage: node tests/e2e_front.mjs <site url> <joomla root>
 *   e.g. node tests/e2e_front.mjs http://127.0.0.1:8106 /tmp/claude-1000/lc/j6.0.0
 * Env: LC_PHP (PHP CLI binary), LC_CHROME (Chromium binary), LC_SHOTS (folder for screenshots).
 *
 * Uses tests/fixture.php to add test services and a module with scripts/iframes to the site,
 * and changes the component options: run it only against a test site.
 */

import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { homedir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const require = createRequire(import.meta.url);
const project = join(dirname(fileURLToPath(import.meta.url)), '..');
const [url, root] = process.argv.slice(2);

if (!url || !root) {
  console.error('Usage: node tests/e2e_front.mjs <site url> <joomla root>');
  process.exit(1);
}

const PHP = process.env.LC_PHP || '/tmp/claude-1000/lc/php';
const CHROME = process.env.LC_CHROME || join(homedir(), '.cache/ms-playwright/chromium-1234/chrome-linux64/chrome');
const SHOTS = process.env.LC_SHOTS || '';
const AXE = readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
const DEFAULTS = ['layout=box-bottom-left', 'theme=auto', 'policy_version=1', 'gcm_enabled=1', 'respect_gpc=1',
  'floating_button=1', 'autoblock=1', 'iframe_placeholder=1', 'log_consents=1'];

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

const fixture = (...args) => execFileSync(PHP, [join(project, 'tests/fixture.php'), root, ...args]).toString();
const records = () => JSON.parse(fixture('consents'));
const isEndpoint = (r) => r.url().includes('task=consent.save') && r.request().method() === 'POST';

async function shot(page, name) {
  if (SHOTS) {
    mkdirSync(SHOTS, { recursive: true });
    await page.screenshot({ path: join(SHOTS, `${name}.png`) });
  }
}

async function open(context) {
  const page = await context.newPage();
  const errors = [];

  page.on('pageerror', (error) => errors.push(error.message));
  // Failed requests count only for our files (the core can request files that do not exist).
  page.on('console', (msg) => {
    if (msg.type() === 'error' && !msg.text().startsWith('Failed to load resource')) {
      errors.push(msg.text());
    }
  });
  page.on('response', (response) => {
    if (response.status() >= 400 && /lcookies|lctest/.test(response.url())) {
      errors.push(`${response.status()} ${response.url()}`);
    }
  });
  page.errors = errors;
  await page.goto(url);
  await page.waitForFunction(() => typeof window.LCookies?.open === 'function');

  return page;
}

const ready = (page) => page.waitForFunction(() => typeof window.LCookies?.open === 'function');
const consent = (page) => page.evaluate(() => window.LCookies.getConsent());
const globals = (page) => page.evaluate(() => ({
  external: window.lcTestExternal,
  inline: window.lcTestInline,
  module: window.lcTestModule,
  dyn: window.lcTestDyn,
  head: window.lcHeadCode,
  body: window.lcBodyCode,
  free: window.lcFree,
}));
// What ConsentHelper (PHP) answered when the page was built (plugin plg_system_lctest of tests/fixture.php).
const helper = (page) => page.evaluate(() => JSON.parse(document.getElementById('lctest-helper').textContent));
const moduleStatus = (page) => page.evaluate(() => {
  const el = document.querySelector('.mod-lcookies [data-mod-lcookies-status]');
  return el && !el.hidden ? el.textContent : null;
});
const gcm = (page, kind) => page.evaluate((k) => (window.dataLayer || [])
  .filter((e) => e && e[0] === 'consent' && e[1] === k).map((e) => e[2]), kind);

async function axe(page, selector) {
  await page.evaluate(AXE);
  const result = await page.evaluate((sel) => window.axe.run(sel, { resultTypes: ['violations'] }), selector);

  return result.violations.map((v) => `${v.id} (${v.nodes.length})`);
}

async function main() {
  console.log(fixture('setup').trim());
  fixture('params', ...DEFAULTS);

  const browser = await chromium.launch({ executablePath: CHROME });

  try {
    /* 1. First visit: everything optional is blocked ------------------------------------------- */
    console.log('First visit');
    const context = await browser.newContext();
    let page = await open(context);

    check('banner visible', await page.isVisible('[data-lcookies-banner]'));
    check('floating button hidden', !(await page.isVisible('[data-lcookies-floating]')));
    let h = await helper(page);
    check('PHP ConsentHelper without a choice: only required', h.has.necessary && !h.has.statistics && !h.has.marketing && !h.has.nope
      && h.granted.join() === 'necessary' && h.consent === null, JSON.stringify(h));
    let g = await globals(page);
    check('external/inline/module scripts blocked', g.external === undefined && g.inline === undefined && g.module === undefined, JSON.stringify(g));
    check('head/body code of the service blocked', g.head === undefined && g.body === undefined);
    check('unrelated inline script runs', g.free === true);
    check('script created by another script blocked (guard)', g.dyn === undefined
      && await page.getAttribute('#lctest-dyn', 'type') === 'text/plain'
      && await page.getAttribute('#lctest-dyn', 'data-lcookies-category') === 'statistics');
    check('iframe without src and hidden', await page.getAttribute('#lctest-frame', 'src') === null
      && !(await page.isVisible('#lctest-frame')));
    check('iframe placeholder visible', await page.isVisible('.lcookies-placeholder[data-lcookies-for="lc1"]'));
    const defaults = await gcm(page, 'default');
    check('Consent Mode default', defaults.length === 1 && defaults[0].analytics_storage === 'denied'
      && defaults[0].ad_storage === 'denied' && defaults[0].security_storage === 'granted' && defaults[0].wait_for_update === 500,
    JSON.stringify(defaults));
    check('contract has the consent endpoint', await page.evaluate(() => Joomla.getOptions('lcookies').endpoint)
      === '/index.php?option=com_lcookies&task=consent.save&format=json');
    check('no untranslated language constants', !(await page.content()).includes('COM_LCOOKIES_'));
    let violations = await axe(page, '#lcookies');
    check('axe: banner without violations', violations.length === 0, violations.join(', '));
    await shot(page, '1-banner');

    await page.keyboard.press('Tab');
    check('first Tab reaches the banner', await page.evaluate(() => Boolean(document.activeElement.closest('[data-lcookies-banner]'))));

    /* 2. Preferences dialog ------------------------------------------------------------------- */
    console.log('Preferences');
    await page.click('[data-lcookies-banner] [data-lcookies-action="settings"]');
    check('preferences open (modal dialog)', await page.evaluate(() => document.querySelector('[data-lcookies-preferences]').open));
    check('switches not pre-selected', await page.evaluate(() => [...document.querySelectorAll('[data-lcookies-toggle]')].every((i) => !i.checked)));
    check('only categories with services are listed', await page.evaluate(() => [...document.querySelectorAll('[data-lcookies-toggle]')].map((i) => i.value).join()) === 'statistics,marketing');
    await page.click('.lcookies-cat__details >> nth=1');
    violations = await axe(page, '[data-lcookies-preferences]');
    check('axe: preferences without violations', violations.length === 0, violations.join(', '));
    await shot(page, '2-preferences');
    await page.keyboard.press('Escape');
    check('Escape closes the preferences', !(await page.evaluate(() => document.querySelector('[data-lcookies-preferences]').open)));
    check('focus returns to the button that opened them', await page.evaluate(() => document.activeElement?.dataset.lcookiesAction === 'settings'));
    check('banner still visible without a choice', await page.isVisible('[data-lcookies-banner]'));

    /* Shortcodes of plg_content_lcookies (module of tests/fixture.php) */
    console.log('Content shortcodes');
    const policy = await page.evaluate(() => ({
      all: [...document.querySelectorAll('#lctest-all [data-lcookies-policy]')].map((s) => s.dataset.lcookiesPolicy).join(),
      marketing: [...document.querySelectorAll('#lctest-marketing [data-lcookies-policy]')].map((s) => s.dataset.lcookiesPolicy).join(),
      text: document.querySelector('#lctest-all').textContent.replace(/\s+/g, ' '),
      noCookies: Joomla.getOptions('lcookies').texts.noCookies,
      inParagraph: Boolean(document.querySelector('p .lcookies-policy, p table')),
      left: document.body.innerHTML.includes('{lcookies-'),
      button: document.querySelector('#lctest-settings .lcookies-settings')?.textContent,
    }));
    check('{lcookies-table}: the categories of the banner', policy.all === 'necessary,statistics,marketing', JSON.stringify(policy));
    check('{lcookies-table}: services, cookies and services without cookies', ['Test Analytics', 'Test Inc.', '_lc_test_*', 'lc_test_ls',
      'Distinguishes users.', 'Test Video'].every((t) => policy.text.includes(t)) && policy.text.includes(policy.noCookies), policy.text);
    check('{lcookies-table marketing}: one category', policy.marketing === 'marketing', policy.marketing);
    check('shortcodes replaced, table not left inside a paragraph', !policy.left && !policy.inParagraph);
    check('{lcookies-settings label}: plain-text label', policy.button === 'Change my choice', policy.button);
    violations = await axe(page, '#lctest-all');
    check('axe: policy table without violations', violations.length === 0, violations.join(', '));
    await page.click('#lctest-settings .lcookies-settings');
    check('{lcookies-settings} opens the preferences', await page.evaluate(() => document.querySelector('[data-lcookies-preferences]').open));
    await page.keyboard.press('Escape');

    /* mod_lcookies (module of tests/fixture.php) */
    console.log('Module');
    check('module: status without a choice', await moduleStatus(page) === 'You have not chosen which cookies to allow yet.', await moduleStatus(page));
    check('module: default button text', (await page.textContent('.mod-lcookies [data-lcookies-open]')).trim() === 'Cookie settings');
    check('module: no policy link without a policy page', await page.locator('.mod-lcookies__policy').count() === 0);
    violations = await axe(page, '.mod-lcookies');
    check('axe: module without violations', violations.length === 0, violations.join(', '));
    await page.click('.mod-lcookies [data-lcookies-open]');
    check('module button opens the preferences', await page.evaluate(() => document.querySelector('[data-lcookies-preferences]').open));
    await page.keyboard.press('Escape');

    /* 3. Accept all ----------------------------------------------------------------------------- */
    console.log('Accept all');
    await page.evaluate(() => {
      window.lcEvents = [];
      document.addEventListener('lcookies:change', (e) => window.lcEvents.push(e.detail));
    });
    const recorded = page.waitForResponse(isEndpoint);
    await page.click('[data-lcookies-banner] [data-lcookies-action="accept"]');
    const answer = await recorded;
    // keepalive requests: the body is not available to Playwright, the records are checked in section 8.
    check('consent sent to the endpoint', answer.status() === 200, String(answer.status()));
    await page.waitForFunction(() => window.lcTestExternal && window.lcTestModule && window.lcTestDyn && window.lcBodyCode);
    g = await globals(page);
    check('scripts run once after consent', g.external === 1 && g.inline === 1 && g.head === 1 && g.module && g.dyn && g.body, JSON.stringify(g));
    check('iframe loaded and placeholder removed', await page.getAttribute('#lctest-frame', 'src') === '/media/lctest/frame-marketing.html'
      && await page.isVisible('#lctest-frame') && await page.locator('[data-lcookies-for="lc1"]').count() === 0);
    let c = await consent(page);
    check('consent cookie stored', c && c.v === 1 && c.cats.join() === 'necessary,statistics,marketing' && /^[0-9a-f-]{36}$/.test(c.id), JSON.stringify(c));
    const updates = await gcm(page, 'update');
    check('Consent Mode update', updates.length === 1 && updates[0].analytics_storage === 'granted' && updates[0].ad_user_data === 'granted'
      && updates[0].functionality_storage === 'denied', JSON.stringify(updates));
    check('dataLayer event', await page.evaluate(() => window.dataLayer.some((e) => e.event === 'lcookies_consent_update')));
    check('module: status updated without reload', /^You allow cookies for Statistics and Marketing\. Choice made on \d{1,2} \w+ \d{4}\.$/.test(await moduleStatus(page)),
      await moduleStatus(page));
    const events = await page.evaluate(() => window.lcEvents);
    check('lcookies:change event', events.length === 1 && events[0].action === 'accept_all' && events[0].granted.join() === 'statistics,marketing', JSON.stringify(events));
    check('banner hidden, floating button visible', !(await page.isVisible('[data-lcookies-banner]')) && await page.isVisible('[data-lcookies-floating]'));
    const cookies = await context.cookies();
    check('statistics cookie set by the accepted script', cookies.some((k) => k.name === '_lc_test_a'));
    await shot(page, '3-accepted');

    /* 4. Next page view ----------------------------------------------------------------------- */
    console.log('Reload with consent');
    await page.reload();
    await ready(page);
    await page.waitForFunction(() => window.lcTestExternal && window.lcBodyCode);
    g = await globals(page);
    check('no banner, scripts run on load', !(await page.isVisible('[data-lcookies-banner]')) && g.inline === 1 && g.head === 1);

    /* 5. Withdraw statistics from the preferences -------------------------------------------- */
    console.log('Withdraw statistics');
    await page.click('#lctest-link');
    check('link to #lcookies-settings opens the preferences', await page.evaluate(() => document.querySelector('[data-lcookies-preferences]').open));
    check('switches reflect the consent', await page.evaluate(() => [...document.querySelectorAll('[data-lcookies-toggle]')].every((i) => i.checked)));
    await page.uncheck('[data-lcookies-toggle][value="statistics"]');
    await Promise.all([page.waitForEvent('load'), page.click('[data-lcookies-preferences] [data-lcookies-action="save"]')]);
    await ready(page);
    g = await globals(page);
    c = await consent(page);
    check('page reloaded after withdrawing a category that ran', g.external === undefined && g.head === undefined, JSON.stringify(g));
    check('consent now without statistics', c.cats.join() === 'necessary,marketing', JSON.stringify(c));
    check('statistics cookie removed', !(await context.cookies()).some((k) => k.name === '_lc_test_a'));
    check('statistics local storage removed', await page.evaluate(() => localStorage.getItem('lc_test_ls')) === null);
    check('marketing iframe still loaded', await page.isVisible('#lctest-frame'));

    /* 6. Server expires rejected cookies ------------------------------------------------------ */
    console.log('Server cleanup');
    const value = encodeURIComponent(JSON.stringify({ id: 'x', v: 1, cats: ['necessary'], ts: Math.floor(Date.now() / 1000) }));
    const response = await fetch(url, { headers: { Cookie: `lcookies_consent=${value}; _lc_test_x=1` } });
    const setCookie = response.headers.getSetCookie().join('\n');
    check('rejected cookie expired by the server', /_lc_test_x=deleted;[^\n]*Max-Age=0/i.test(setCookie), setCookie);
    check('consent cookie untouched by the server', !/lcookies_consent=/.test(setCookie));

    /* 7. Reject all, then allow from the placeholder ----------------------------------------- */
    console.log('Reject all + placeholder');
    await Promise.all([page.waitForEvent('load'), page.evaluate(() => window.LCookies.rejectAll())]);
    await ready(page);
    check('rejectAll(): only necessary', (await consent(page)).cats.join() === 'necessary');
    check('module: only necessary', (await moduleStatus(page) || '').startsWith('You only allow the strictly necessary cookies. Choice made on'),
      await moduleStatus(page));
    check('placeholder back after rejecting', await page.isVisible('.lcookies-placeholder[data-lcookies-for="lc1"]'));
    await page.evaluate(() => { window.lcSamePage = true; });
    await page.click('.lcookies-placeholder [data-lcookies-allow="marketing"]');
    await page.waitForSelector('#lctest-frame:not([hidden])');
    check('placeholder button allows the category without reload', await page.evaluate(() => window.lcSamePage === true)
      && (await consent(page)).cats.join() === 'necessary,marketing');
    check('API hasConsent()', await page.evaluate(() => window.LCookies.hasConsent('marketing') && !window.LCookies.hasConsent('statistics')
      && window.LCookies.hasConsent('necessary')));

    /* 8. Consent records -------------------------------------------------------------------- */
    console.log('Consent records');
    const id = (await consent(page)).id;
    let rows = records();
    check('one record per choice, same consent id', rows.map((r) => r.action).join() === 'accept_all,custom,reject_all,allow'
      && rows.every((r) => r.consent_uuid === id), JSON.stringify(rows.map((r) => [r.action, r.consent_uuid])));
    check('records keep the categories and the policy version', rows.map((r) => JSON.parse(r.categories).join('+')).join()
      === 'necessary+statistics+marketing,necessary+marketing,necessary,necessary+marketing' && rows.every((r) => Number(r.policy_version) === 1));
    check('IP and browser only as hashes', rows.every((r) => /^[0-9a-f]{64}$/.test(r.ip_hash) && /^[0-9a-f]{64}$/.test(r.ua_hash))
      && rows.every((r) => !JSON.stringify({ ...r, url: '' }).includes('127.0.0')));
    check('page URL without query string', rows.every((r) => r.url.startsWith(url) && !r.url.includes('?')), rows[0].url);
    check('guest, site language', rows.every((r) => r.user_id === null && r.language === 'en-GB'));

    await page.reload();
    await ready(page);
    h = await helper(page);
    check('PHP ConsentHelper reads the cookie like hasConsent()', h.has.necessary && h.has.marketing && !h.has.statistics && !h.has.nope
      && h.granted.join() === 'necessary,marketing' && h.consent.id === id && h.consent.v === 1, JSON.stringify(h));
    const changes = JSON.parse(fixture('events'));
    check('onLCookiesConsentChange once per recorded choice', changes.map((e) => e.action).join() === 'accept_all,custom,reject_all,allow'
      && changes.every((e) => e.id === id && e.version === 1 && e.user === 0), JSON.stringify(changes));
    check('event: previous, granted and revoked', JSON.stringify(changes.map((e) => [e.previous, e.granted, e.revoked])) === JSON.stringify([
      [null, ['necessary', 'statistics', 'marketing'], []],
      [['necessary', 'statistics', 'marketing'], [], ['statistics']],
      [['necessary', 'marketing'], [], ['marketing']],
      [['necessary'], ['marketing'], []],
    ]), JSON.stringify(changes));

    /* 9. New policy version asks again --------------------------------------------------------- */
    console.log('Policy version');
    fixture('params', 'policy_version=2');
    await page.reload();
    await ready(page);
    check('banner shown again after a new policy version', await page.isVisible('[data-lcookies-banner]'));
    check('old consent ignored', (await consent(page)) === null && await page.evaluate(() => window.lcTestExternal) === undefined);
    h = await helper(page);
    check('PHP ConsentHelper ignores an older policy version', h.consent === null && !h.has.marketing, JSON.stringify(h));
    fixture('params', 'policy_version=1');
    await context.close();

    /* 10. Global Privacy Control --------------------------------------------------------------- */
    console.log('GPC');
    const gpcContext = await browser.newContext();
    await gpcContext.addInitScript(() => Object.defineProperty(Navigator.prototype, 'globalPrivacyControl', { get: () => true }));
    page = await open(gpcContext);
    await page.click('[data-lcookies-banner] [data-lcookies-action="accept"]');
    check('accept all with GPC leaves marketing off', (await consent(page)).cats.join() === 'necessary,statistics');
    await page.click('[data-lcookies-floating]');
    check('marketing switch disabled with notice', await page.evaluate(() => {
      const input = document.querySelector('[data-lcookies-toggle][value="marketing"]');
      return input.disabled && !input.checked && !input.closest('section').querySelector('[data-lcookies-gpc]').hidden;
    }));
    await shot(page, '4-gpc');
    check('no JavaScript errors (GPC)', page.errors.length === 0, page.errors.join(' | '));
    await gpcContext.close();

    /* 11. Modal layout and dark theme --------------------------------------------------------- */
    console.log('Modal layout');
    fixture('params', 'layout=modal', 'theme=dark');
    const modalContext = await browser.newContext();
    page = await open(modalContext);
    check('modal banner open', await page.evaluate(() => document.querySelector('dialog[data-lcookies-banner]')?.open === true));
    await page.keyboard.press('Escape');
    check('Escape does not dismiss the modal banner', await page.evaluate(() => document.querySelector('dialog[data-lcookies-banner]').open));
    violations = await axe(page, '#lcookies');
    check('axe: modal banner (dark) without violations', violations.length === 0, violations.join(', '));
    await shot(page, '5-modal-dark');
    await page.click('[data-lcookies-banner] [data-lcookies-action="reject"]');
    check('modal closes after a choice', await page.evaluate(() => !document.querySelector('dialog[data-lcookies-banner]').open));
    check('no JavaScript errors (modal)', page.errors.length === 0, page.errors.join(' | '));
    await modalContext.close();

    /* 12. Bar layout on a phone ---------------------------------------------------------------- */
    console.log('Bar layout, small screen');
    fixture('params', 'layout=bar-bottom', 'theme=light');
    const phone = await browser.newContext({ viewport: { width: 375, height: 700 } });
    page = await open(phone);
    const box = await page.locator('[data-lcookies-banner]').boundingBox();
    check('bar fits the screen at the bottom', box && box.x === 0 && Math.round(box.width) === 375 && Math.round(box.y + box.height) === 700, JSON.stringify(box));
    await shot(page, '6-bar-phone');
    await page.click('[data-lcookies-banner] [data-lcookies-action="settings"]');
    const dialog = await page.locator('[data-lcookies-preferences]').boundingBox();
    check('preferences fit the phone screen', dialog && dialog.width <= 375 && dialog.height <= 700, JSON.stringify(dialog));
    await shot(page, '7-preferences-phone');
    check('no JavaScript errors (bar)', page.errors.length === 0, page.errors.join(' | '));
    await phone.close();

    /* 13. Logging turned off ------------------------------------------------------------------- */
    console.log('Logging off');
    fixture('params', 'log_consents=0');
    const before = records().length;
    const quiet = await browser.newContext();
    page = await open(quiet);
    let posted = false;
    page.on('request', (r) => { posted = posted || (r.url().includes('task=consent.save')); });
    check('no endpoint in the contract', await page.evaluate(() => Joomla.getOptions('lcookies').endpoint) === null);
    await page.click('[data-lcookies-banner] [data-lcookies-action="accept"]');
    await page.waitForTimeout(500);
    check('nothing sent or recorded', !posted && records().length === before);
    const off = await fetch(`${url}/index.php?option=com_lcookies&task=consent.save&format=json`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}',
    });
    check('endpoint answers 404 when off', off.status === 404);
    await quiet.close();

    /* 14. Cookie scanner (backend) ------------------------------------------------------------- */
    console.log('Cookie scanner');
    fixture('params', 'log_consents=1');
    const [adminUser, adminPass] = (process.env.LC_ADMIN || 'admin:Admin123456789!').split(/:(.*)/s);
    // The guided tour of the backend would take over the page.
    const tours = fixture('enable', 'system', 'guidedtours', '0').trim();
    // Atum (Joomla 6) uses cross-document view transitions, which stop headless Chromium from painting.
    const scanContext = await browser.newContext({ reducedMotion: 'reduce' });
    const host = new URL(url).hostname;
    // The administrator's own choice for the site (reject all) must not change what the scan finds.
    const ownChoice = encodeURIComponent(JSON.stringify({ id: 'own', v: 1, cats: ['necessary'], ts: Math.floor(Date.now() / 1000) }));
    await scanContext.addCookies([{ name: 'lcookies_consent', value: ownChoice, domain: host, path: '/' }]);
    const admin = await scanContext.newPage();
    const adminErrors = [];
    admin.on('pageerror', (error) => adminErrors.push(error.message));
    await admin.goto(`${url}/administrator/index.php`);
    await admin.fill('#mod-login-username', adminUser);
    await admin.fill('#mod-login-password', adminPass);
    await admin.click('#btn-login-submit');
    await admin.waitForSelector('a[href*="task=logout"]', { state: 'attached' });
    await admin.goto(`${url}/administrator/index.php?option=com_lcookies&view=scanner`);
    const recordsBefore = records().length;
    const scanUrls = {};
    admin.on('request', (r) => {
      const mode = new URL(r.url()).searchParams.get('lcookies_scan')?.split('.')[0];
      if (mode && r.resourceType() === 'document') {
        scanUrls[mode] = r.url();
      }
    });
    await admin.click('[data-lcookies-scan-start]');
    await admin.waitForURL(/view=scanner&id=\d+/, { timeout: 180000 });
    const found = await admin.evaluate(() => ({
      items: Object.fromEntries([...document.querySelectorAll('[data-lcookies-scan-item]')]
        .map((row) => [row.dataset.lcookiesScanItem, row.cells[2].textContent.replace(/\s+/g, ' ').trim()])),
      requests: [...document.querySelectorAll('[data-lcookies-scan-request]')].map((row) => row.dataset.lcookiesScanRequest),
      issues: Number(document.querySelector('[data-lcookies-scan-issues]')?.dataset.lcookiesScanIssues || 0),
    }));
    const status = (key) => found.items[key] || '';
    check('scan results shown', Object.keys(found.items).length > 0, JSON.stringify(found));
    check('scan: cookie set by a service after consent is declared', /Declared/.test(status('cookie:_lc_test_a')) && !/Before consent/.test(status('cookie:_lc_test_a')), status('cookie:_lc_test_a'));
    check('scan: local storage of a service is declared', /Declared/.test(status('local:lc_test_ls')), status('local:lc_test_ls'));
    check('scan: unblocked cookie reported before consent', /Before consent/.test(status('cookie:lc_scan_free')) && /Not declared/.test(status('cookie:lc_scan_free')), status('cookie:lc_scan_free'));
    check('scan: request to another site before consent', found.requests.includes('localhost'), JSON.stringify(found));
    check('scan: problems counted', found.issues >= 2, String(found.issues));
    check('scan stores no consent', records().length === recordsBefore);
    const kept = (await scanContext.cookies(url)).find((c) => c.name === 'lcookies_consent');
    check("scan keeps the administrator's own choice", kept && kept.value === ownChoice, kept && kept.value);
    check('no JavaScript errors (scanner)', adminErrors.length === 0, adminErrors.join(' | '));

    /* 14b. Accessibility of the backend pages (WCAG 2.2 A/AA, content of LCookies only) ---------- */
    console.log('Backend accessibility');
    const backendPages = [['dashboard', 'view=dashboard'], ['categories', 'view=categories'], ['services', 'view=services'],
      ['cookies', 'view=cookies'], ['library', 'view=presets'], ['consents', 'view=consents'], ['scanner', 'view=scanner'],
      ['service form', 'task=service.edit&id=1'], ['cookie form', 'task=cookie.edit&id=1'], ['category form', 'task=category.edit&id=3']];
    for (const [name, query] of backendPages) {
      await admin.goto(`${url}/administrator/index.php?option=com_lcookies&${query}`);
      await admin.evaluate(AXE);
      const result = await admin.evaluate(() => window.axe.run({
        include: [['#content']],
        // Disabled state toggles of the core (JGrid) put aria-labelledby on a span, in every Joomla list.
        exclude: [['span[aria-labelledby^="cb"]']],
      }, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] }, resultTypes: ['violations'] }));
      const found = result.violations.map((v) => `${v.id} (${v.nodes.length}: ${v.nodes[0].target.join(' ')})`);
      check(`axe: backend ${name} without violations`, found.length === 0, found.join(', '));
      if (query.startsWith('task=')) {
        // Close the form so that the record is checked in again.
        await Promise.all([admin.waitForNavigation(), admin.click('#toolbar-cancel button, joomla-toolbar-button#toolbar-cancel')]);
      }
    }

    /* 15. Live preview in the options ---------------------------------------------------------- */
    console.log('Live preview');
    const paramsBefore = fixture('params').trim();
    const recordsPreview = records().length;
    await admin.goto(`${url}/administrator/index.php?option=com_config&view=component&component=com_lcookies`);
    await admin.click('button[role="tab"][aria-controls="appearance"]');
    const preview = admin.frameLocator('#appearance [data-lcookies-preview] iframe');
    const rendered = (selector, attribute) => preview.locator(selector).getAttribute(attribute, { timeout: 10000 }).catch(() => null);
    await preview.locator('[data-lcookies-banner]').waitFor({ timeout: 15000 });
    const savedLayout = JSON.parse(paramsBefore).layout || 'box-bottom-left';
    check('preview shows the banner with the saved layout', (await rendered('[data-lcookies-banner]', 'class') || '').includes(`--${savedLayout}`), savedLayout);
    const newLayout = savedLayout === 'bar-top' ? 'bar-bottom' : 'bar-top';
    await admin.selectOption('#jform_layout', newLayout);
    await admin.selectOption('#jform_theme', 'dark');
    await admin.waitForFunction((layout) => document.querySelector('#appearance [data-lcookies-preview] iframe')
      .contentDocument?.querySelector(`.lcookies-banner--${layout}`), newLayout, { timeout: 15000 }).catch(() => {});
    check('preview follows the layout and theme before saving', (await rendered('[data-lcookies-banner]', 'class') || '').includes(`--${newLayout}`)
      && await rendered('#lcookies', 'data-lcookies-theme') === 'dark');
    await admin.click('button[role="tab"][aria-controls="texts"]');
    await admin.fill('#jform_text_title', 'Preview title');
    const textsPreview = admin.frameLocator('#texts [data-lcookies-preview] iframe');
    await admin.waitForFunction(() => document.querySelector('#texts [data-lcookies-preview] iframe')
      .contentDocument?.querySelector('#lcookies-banner-title')?.textContent === 'Preview title', null, { timeout: 15000 }).catch(() => {});
    check('preview follows the texts', await textsPreview.locator('#lcookies-banner-title').textContent() === 'Preview title');
    await textsPreview.locator('[data-lcookies-banner] [data-lcookies-action="accept"]').click();
    check('a choice in the preview only hides the banner', !(await textsPreview.locator('[data-lcookies-banner]').isVisible())
      && !(await scanContext.cookies(url)).some((c) => c.name === 'lcookies_consent' && c.value !== ownChoice) && records().length === recordsPreview);
    await admin.click('#texts [data-lcookies-preview-show="preferences"]');
    await admin.waitForFunction(() => document.querySelector('#texts [data-lcookies-preview] iframe')
      .contentDocument?.querySelector('[data-lcookies-preferences]')?.open, null, { timeout: 15000 }).catch(() => {});
    check('preview of the preferences', await textsPreview.locator('[data-lcookies-preferences]').evaluate((d) => d.open));
    violations = await textsPreview.locator('body').evaluate(async (body, axeSource) => {
      body.ownerDocument.defaultView.eval(axeSource);
      const result = await body.ownerDocument.defaultView.axe.run(body.ownerDocument.getElementById('lcookies'), { resultTypes: ['violations'] });
      return result.violations.map((v) => `${v.id} (${v.nodes.length})`);
    }, AXE);
    check('axe: preview of the preferences without violations', violations.length === 0, violations.join(', '));
    check('preview saves nothing', fixture('params').trim() === paramsBefore);
    check('no JavaScript errors (preview)', adminErrors.length === 0, adminErrors.join(' | '));
    await scanContext.close();

    /* 16. Content-Security-Policy ("System - HTTP Headers") ------------------------------------- */
    console.log('Content-Security-Policy');
    const headersParams = fixture('plugin', 'system', 'httpheaders').trim() || '{}';
    const headersEnabled = fixture('enable', 'system', 'httpheaders', '1').trim();
    const csp = (mode, client) => JSON.stringify({
      contentsecuritypolicy: '1', contentsecuritypolicy_client: client, contentsecuritypolicy_report_only: '0',
      nonce_enabled: mode === 'nonce' ? '1' : '0', strict_dynamic_enabled: mode === 'nonce' ? '1' : '0',
      script_hashes_enabled: mode === 'hashes' ? '1' : '0', style_hashes_enabled: '0', frame_ancestors_self_enabled: '1',
      contentsecuritypolicy_values: { __field0: { directive: 'script-src', value: "'self'", client } },
    });
    const cspPage = async () => {
      const cspContext = await browser.newContext();
      const cspPageObj = await cspContext.newPage();
      const response = await cspPageObj.goto(url);
      await cspPageObj.waitForFunction(() => typeof window.LCookies?.open === 'function', null, { timeout: 10000 }).catch(() => {});
      return { context: cspContext, page: cspPageObj, header: response.headers()['content-security-policy'] || '' };
    };

    try {
      fixture('plugin', 'system', 'httpheaders', csp('nonce', 'both'));
      let cspRun = await cspPage();
      check('nonce CSP active (unsigned inline code blocked)', /nonce-/.test(cspRun.header) && await cspRun.page.evaluate(() => window.lcFree === undefined), cspRun.header);
      check('nonce CSP: banner works', await cspRun.page.isVisible('[data-lcookies-banner]'));
      await cspRun.page.click('[data-lcookies-banner] [data-lcookies-action="accept"]');
      await cspRun.page.waitForTimeout(500);
      let g = await globals(cspRun.page);
      check('nonce CSP: code of services runs after consent (inline and external)', g.head === 1 && g.body === true && g.external === 1, JSON.stringify(g));
      await cspRun.context.close();

      // The preview of the options inherits the policy of the backend page.
      const cspAdminContext = await browser.newContext({ reducedMotion: 'reduce' });
      const cspAdmin = await cspAdminContext.newPage();
      await cspAdmin.goto(`${url}/administrator/index.php`);
      await cspAdmin.fill('#mod-login-username', adminUser);
      await cspAdmin.fill('#mod-login-password', adminPass);
      await cspAdmin.click('#btn-login-submit');
      await cspAdmin.waitForSelector('a[href*="task=logout"]', { state: 'attached' });
      await cspAdmin.goto(`${url}/administrator/index.php?option=com_config&view=component&component=com_lcookies`);
      await cspAdmin.click('button[role="tab"][aria-controls="appearance"]');
      const cspPreview = await cspAdmin.waitForFunction(() => document.querySelector('#appearance [data-lcookies-preview] iframe')
        .contentWindow?.LCookies?.open, null, { timeout: 15000 }).then(() => true, () => false);
      check('nonce CSP: preview of the options works', cspPreview);
      await cspAdminContext.close();

      fixture('plugin', 'system', 'httpheaders', csp('hashes', 'site'));
      cspRun = await cspPage();
      check('hash CSP: LCookies adds the hash of its inline script', /script-src[^;]*'sha256-/.test(cspRun.header)
        && await cspRun.page.evaluate(() => typeof window.LCookies?.open === 'function'), cspRun.header);
      await cspRun.page.click('[data-lcookies-banner] [data-lcookies-action="accept"]');
      await cspRun.page.waitForTimeout(500);
      g = await globals(cspRun.page);
      check('hash CSP: external code of services runs after consent', g.external === 1, JSON.stringify(g));
      await cspRun.context.close();
    } finally {
      fixture('plugin', 'system', 'httpheaders', headersParams);
      fixture('enable', 'system', 'httpheaders', headersEnabled);
    }
    fixture('enable', 'system', 'guidedtours', tours);

    /* 17. Consent shared between subdomains (cookie_domain) ------------------------------------ */
    console.log('Shared consent between subdomains');
    const port = new URL(url).port;
    const hostsBrowser = await chromium.launch({
      executablePath: CHROME, args: ['--host-resolver-rules=MAP *.lctest.test 127.0.0.1, MAP *.lctest.co.uk 127.0.0.1'],
    });
    const at = (host) => `http://${host}:${port}/`;
    const visit = async (ctx, host) => {
      const tab = await ctx.newPage();
      tab.warnings = [];
      tab.on('console', (msg) => { if (msg.type() === 'warning') tab.warnings.push(msg.text()); });
      await tab.goto(at(host));
      await ready(tab);
      return tab;
    };

    // Test sites served by `php -S` set $live_site, which redirects requests for other hosts.
    const liveSite = fixture('livesite', '').trim();

    try {
      fixture('params', 'cookie_domain=.lctest.test');
      let shared = await hostsBrowser.newContext();
      let tab = await visit(shared, 'www.lctest.test');
      await tab.click('[data-lcookies-banner] [data-lcookies-action="accept"]');
      const stored = (await shared.cookies(at('www.lctest.test'))).filter((c) => c.name === 'lcookies_consent');
      check('consent cookie set for the shared domain', stored.length === 1 && stored[0].domain === '.lctest.test', JSON.stringify(stored.map((c) => c.domain)));
      tab = await visit(shared, 'shop.lctest.test');
      h = await helper(tab);
      check('another subdomain knows the choice (browser and PHP)', !(await tab.isVisible('[data-lcookies-banner]'))
        && await tab.evaluate(() => window.LCookies.hasConsent('statistics')) && h.has.statistics, JSON.stringify(h));
      await shared.close();

      // A domain the host does not belong to is not used: the choice stays on this host.
      fixture('params', 'cookie_domain=.example.com');
      shared = await hostsBrowser.newContext();
      tab = await visit(shared, 'www.lctest.test');
      await tab.click('[data-lcookies-banner] [data-lcookies-action="reject"]');
      await tab.reload();
      await ready(tab);
      check('cookie domain of another site ignored: choice kept on this host', !(await tab.isVisible('[data-lcookies-banner]'))
        && (await shared.cookies(at('www.lctest.test'))).some((c) => c.name === 'lcookies_consent' && c.domain === 'www.lctest.test'));
      await shared.close();

      // A public suffix is refused by the browser: LCookies falls back to this host and warns.
      fixture('params', 'cookie_domain=.co.uk');
      shared = await hostsBrowser.newContext();
      tab = await visit(shared, 'www.lctest.co.uk');
      await tab.click('[data-lcookies-banner] [data-lcookies-action="reject"]');
      const warned = tab.warnings.some((w) => w.includes('refused the consent cookie'));
      await tab.reload();
      await ready(tab);
      check('cookie refused by the browser: choice kept on this host, with a warning', warned && !(await tab.isVisible('[data-lcookies-banner]')), tab.warnings.join(' | '));
      await shared.close();
    } finally {
      fixture('params', 'cookie_domain=');
      fixture('livesite', liveSite);
      await hostsBrowser.close();
    }

    // Scan mode seen by a page: PHP (ConsentHelper) and JavaScript agree, nothing is shown or stored.
    const scanVisit = await browser.newContext();
    page = await scanVisit.newPage();
    await page.goto(scanUrls.all || `${url}/?lcookies_scan=all.missing`);
    await ready(page);
    h = await helper(page);
    check('scan mode "all": PHP and JS accept every category', h.has.marketing && h.has.statistics
      && await page.evaluate(() => window.LCookies.hasConsent('marketing')), JSON.stringify(h));
    check('scan mode "all": no banner, no floating button', !(await page.isVisible('[data-lcookies-banner]')) && !(await page.isVisible('[data-lcookies-floating]')));
    check('scan mode "all": services run', (await globals(page)).external === 1);
    await page.goto(scanUrls.none || `${url}/?lcookies_scan=none.missing`);
    await ready(page);
    h = await helper(page);
    check('scan mode "none": nothing accepted, no banner', !h.has.statistics && !(await page.evaluate(() => window.LCookies.hasConsent('statistics')))
      && !(await page.isVisible('[data-lcookies-banner]')), JSON.stringify(h));
    check('scan mode stores no consent cookie', !(await scanVisit.cookies(url)).some((c) => c.name === 'lcookies_consent'));
    await page.goto(`${url}/?lcookies_scan=all.1.9999999999.${'0'.repeat(64)}`);
    await ready(page);
    check('invalid scan token: normal page with the banner', await page.isVisible('[data-lcookies-banner]'));
    await scanVisit.close();
  } finally {
    await browser.close();
    fixture('params', ...DEFAULTS, 'gcm_enabled=0');
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
