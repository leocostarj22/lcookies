/**
 * LCookies - cookie scanner (Components → LCookies → Cookie Scanner).
 *
 * 1. Starts a scan (task=scan.start): the server lists the pages and gives a scan token.
 * 2. Server pass (task=scan.page): the server requests each page without cookies and keeps the
 *    cookies its responses set.
 * 3. Browser pass, only when the site has the same origin as the backend: each page is loaded in a
 *    hidden iframe with ?lcookies_scan=<mode>.<token> (plg_system_lcookies ignores the visitor's own
 *    choice and stores nothing):
 *    - "none" (no consent): requests to other sites and new cookies are problems;
 *    - "all" (everything accepted): every cookie and storage key is listed.
 * 4. Ends the scan (task=scan.finish) with the browser results and reloads the page.
 *
 * @copyright  (C) 2026 leocostadeveloper
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

const options = window.Joomla.getOptions('com_lcookies.scanner');
const root = document.getElementById('lcookies-scanner');

// Time for the scripts of a page to run after it loaded, and the most a page may take to load.
const SETTLE = 3000;
const TIMEOUT = 20000;

const text = (key, ...args) => args.reduce((result, arg) => result.replace('%s', arg), window.Joomla.Text._(key));
const sleep = (ms) => new Promise((resolve) => { setTimeout(resolve, ms); });

async function post(task, data = {}) {
  const body = new FormData();

  body.append(options.token, '1');
  Object.entries(data).forEach(([key, value]) => body.append(key, value));

  const response = await fetch(`${options.url}&task=scan.${task}`, { method: 'POST', body, credentials: 'same-origin' });
  const json = await response.json().catch(() => null);

  if (!json || !json.success) {
    throw new Error(json && json.message ? json.message : `HTTP ${response.status}`);
  }

  return json.data;
}

function cookieNames(doc) {
  return doc.cookie ? doc.cookie.split(';').map((part) => part.split('=')[0].trim()).filter(Boolean) : [];
}

function storageKeys(storage) {
  const keys = [];

  try {
    for (let i = 0; i < storage.length; i += 1) {
      keys.push(storage.key(i));
    }
  } catch (e) {
    // Storage disabled.
  }

  return keys;
}

function load(container, url, param) {
  return new Promise((resolve, reject) => {
    const frame = document.createElement('iframe');
    const target = new URL(url);
    const timer = setTimeout(() => reject(new Error(`Timeout: ${url}`)), TIMEOUT);

    target.searchParams.set(options.param, param);
    frame.title = url;
    frame.width = '1280';
    frame.height = '800';
    frame.tabIndex = -1;
    frame.addEventListener('load', async () => {
      clearTimeout(timer);
      await sleep(SETTLE);
      resolve(frame);
    }, { once: true });
    frame.src = target.href;
    container.append(frame);
  });
}

async function run(button) {
  const progress = root.querySelector('[data-lcookies-scan-progress]');
  const bar = progress.querySelector('progress');
  const status = progress.querySelector('[role="status"]');
  const frames = root.querySelector('[data-lcookies-scan-frames]');

  const step = (done, total, message) => {
    bar.value = Math.round((done * 100) / total);
    status.textContent = message;
  };

  button.disabled = true;
  progress.hidden = false;

  try {
    const scan = await post('start');
    const { pages } = scan;
    const sameOrigin = pages.every((url) => new URL(url).origin === window.location.origin);
    const total = pages.length * (sameOrigin ? 3 : 1) + 1;
    let done = 0;

    for (let i = 0; i < pages.length; i += 1) {
      step(done, total, text('COM_LCOOKIES_SCAN_STATUS_SERVER', i + 1, pages.length));
      // Sequential on purpose: one request at a time for the site.
      // eslint-disable-next-line no-await-in-loop
      await post('page', { id: scan.id, page: i });
      done += 1;
    }

    let browser = null;

    if (sameOrigin) {
      const skipStorage = new Set([...storageKeys(window.localStorage), ...storageKeys(window.sessionStorage)]);
      const seen = new Set(cookieNames(document));

      browser = pages.map((url, page) => ({
        page, before: { cookies: [], hosts: [] }, after: { cookies: [], local: [], session: [] },
      }));

      // Without consent first: once a page with consent ran, its cookies exist and would hide new ones.
      for (let i = 0; i < pages.length; i += 1) {
        step(done, total, text('COM_LCOOKIES_SCAN_STATUS_BEFORE', i + 1, pages.length));

        try {
          // eslint-disable-next-line no-await-in-loop
          const frame = await load(frames, pages[i], `none.${scan.param}`);
          const win = frame.contentWindow;
          const hosts = new Set(win.performance.getEntriesByType('resource')
            .map((entry) => { try { return new URL(entry.name); } catch (e) { return null; } })
            .filter((u) => u && /^https?:$/.test(u.protocol) && u.hostname !== window.location.hostname)
            .map((u) => u.hostname));

          browser[i].before.hosts = [...hosts];
          browser[i].before.cookies = cookieNames(win.document).filter((name) => !seen.has(name));
          browser[i].before.cookies.forEach((name) => seen.add(name));
          frame.remove();
        } catch (e) {
          // Page not loaded: the server pass reports it.
        }

        done += 1;
      }

      for (let i = 0; i < pages.length; i += 1) {
        step(done, total, text('COM_LCOOKIES_SCAN_STATUS_AFTER', i + 1, pages.length));

        try {
          // eslint-disable-next-line no-await-in-loop
          const frame = await load(frames, pages[i], `all.${scan.param}`);
          const win = frame.contentWindow;

          browser[i].after.cookies = cookieNames(win.document);
          browser[i].after.local = storageKeys(win.localStorage).filter((key) => !skipStorage.has(key));
          browser[i].after.session = storageKeys(win.sessionStorage).filter((key) => !skipStorage.has(key));
          frame.remove();
        } catch (e) {
          // Page not loaded: the server pass reports it.
        }

        done += 1;
      }
    } else {
      window.Joomla.renderMessages({ warning: [text('COM_LCOOKIES_SCAN_STATUS_NO_BROWSER')] });
    }

    frames.replaceChildren();
    step(done, total, text('COM_LCOOKIES_SCAN_STATUS_SAVING'));

    const result = await post('finish', { id: scan.id, browser: JSON.stringify(browser) });

    step(total, total, text('COM_LCOOKIES_SCAN_STATUS_SAVING'));
    window.location.href = `${window.location.pathname}?option=com_lcookies&view=scanner&id=${result.id}`;
  } catch (error) {
    window.Joomla.renderMessages({ error: [text('COM_LCOOKIES_SCAN_ERROR', error.message)] });
    progress.hidden = true;
    button.disabled = false;
  }
}

root.querySelector('[data-lcookies-scan-start]')?.addEventListener('click', (event) => run(event.currentTarget));
