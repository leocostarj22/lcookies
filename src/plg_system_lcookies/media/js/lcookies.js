/**
 * LCookies - consent banner, preferences, unblocking and cleanup.
 *
 * Reads the contract with Joomla.getOptions('lcookies') (docs/contract.schema.json) and the state
 * prepared by the head bootstrap (lcookies-head.js, window.LCookies).
 *
 * Public API (window.LCookies): hasConsent(category), getConsent(), open(), acceptAll(),
 * rejectAll(), save(categories), allow(category).
 * Events on document: `lcookies:ready` and `lcookies:change` (detail: {action, consent, granted, revoked}).
 * Any element with data-lcookies-open, or a link to #lcookies-settings, opens the preferences.
 *
 * @copyright  (C) 2026 leocostadeveloper
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

const contract = window.Joomla && window.Joomla.getOptions ? window.Joomla.getOptions('lcookies') : null;
const api = window.LCookies;
const root = document.getElementById('lcookies');

const required = [];
const optional = [];
const categories = new Map();
const services = new Map();
const activated = new Set();
let queue = Promise.resolve();
let placeholderTemplate = '';
let placeholderCounter = 0;
let opener = null;

const gpc = () => Boolean(contract.respectGpc && navigator.globalPrivacyControl === true);
const isGpcBlocked = (cat) => gpc() && Boolean(categories.get(cat)?.gpcOptOut);
const banner = () => root.querySelector('[data-lcookies-banner]');
const preferences = () => root.querySelector('[data-lcookies-preferences]');
const floating = () => root.querySelector('[data-lcookies-floating]');

const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (ch) => `&#${ch.charCodeAt(0)};`);

function uuid() {
  if (window.crypto && typeof window.crypto.randomUUID === 'function') {
    return window.crypto.randomUUID();
  }

  const bytes = window.crypto.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');

  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/* Consent ------------------------------------------------------------------------------------- */

const currentConsent = () => api._state.consent;
const grantedOptional = () => optional.filter((cat) => api.hasConsent(cat));

function writeCookie(consent) {
  const { name, domain } = contract.cookie;
  let cookie = `${name}=${encodeURIComponent(JSON.stringify(consent))}; Max-Age=${contract.expiryDays * 86400}; Path=/; SameSite=Lax`;

  if (domain) {
    cookie += `; Domain=${domain}`;
  }

  if (window.location.protocol === 'https:') {
    cookie += '; Secure';
  }

  document.cookie = cookie;
}

function send(consent, action) {
  if (!contract.endpoint) {
    return;
  }

  fetch(contract.endpoint, {
    method: 'POST',
    credentials: 'same-origin',
    keepalive: true,
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ consent, action, url: window.location.href }),
  }).catch(() => {});
}

function commit(cats, action) {
  // Scan mode (cookie scanner of the backend): the choice is fixed and nothing is stored.
  if (api._scan) {
    return;
  }

  const before = grantedOptional();
  const allowed = optional.filter((cat) => cats.includes(cat) && !isGpcBlocked(cat));
  const previous = currentConsent();
  const consent = {
    id: previous && previous.id ? previous.id : uuid(),
    v: contract.policyVersion,
    cats: [...required, ...allowed],
    ts: Math.floor(Date.now() / 1000),
  };

  writeCookie(consent);
  api._state.consent = consent;
  api._gcmUpdate();

  if (contract.gcm) {
    window.dataLayer.push({ event: 'lcookies_consent_update', lcookies_categories: consent.cats });
  }

  cleanup();
  hideBanner();

  const prefs = preferences();

  if (prefs && prefs.open) {
    prefs.close();
  }

  const revoked = before.filter((cat) => !allowed.includes(cat));
  const detail = {
    action, consent: api.getConsent(), granted: allowed.filter((cat) => !before.includes(cat)), revoked,
  };

  document.dispatchEvent(new CustomEvent('lcookies:change', { detail }));
  send(consent, action);

  // Code that already ran cannot be stopped: reload so that it is gone.
  if (revoked.some((cat) => activated.has(cat))) {
    window.location.reload();
    return;
  }

  activate();
}

/* Cleanup of rejected cookies and storage ------------------------------------------------------ */

function matcher(def) {
  if (def.match === 'prefix') {
    return (name) => name.startsWith(def.name);
  }

  if (def.match === 'regex') {
    try {
      const regex = new RegExp(def.name);
      return (name) => regex.test(name);
    } catch (e) {
      return null;
    }
  }

  return (name) => name === def.name;
}

function cookieDomains(extra) {
  const host = window.location.hostname;
  const domains = new Set(['', contract.cookie.domain, extra]);

  if (!/^[\d.]+$/.test(host) && !host.includes(':')) {
    const parts = host.split('.');

    for (let i = 0; i < parts.length - 1; i += 1) {
      domains.add(`.${parts.slice(i).join('.')}`);
    }
  }

  return [...domains].filter((d) => d !== undefined && d !== null);
}

function expireCookie(name, domain) {
  const secure = window.location.protocol === 'https:' ? '; Secure' : '';

  cookieDomains(domain).forEach((d) => {
    document.cookie = `${name}=; Max-Age=0; Path=/${d ? `; Domain=${d}` : ''}${secure}`;
  });
}

function storageKeys(storage) {
  const keys = [];

  try {
    for (let i = 0; i < storage.length; i += 1) {
      keys.push(storage.key(i));
    }
  } catch (e) {
    // Storage not available.
  }

  return keys;
}

function cleanup() {
  const names = document.cookie ? document.cookie.split(';').map((part) => part.split('=')[0].trim()) : [];

  contract.categories.forEach((category) => {
    if (category.required || api.hasConsent(category.alias)) {
      return;
    }

    category.services.forEach((service) => {
      service.cookies.forEach((def) => {
        const test = matcher(def);

        if (!test) {
          return;
        }

        if (def.type === 'cookie') {
          names.filter((name) => name !== contract.cookie.name && test(name))
            .forEach((name) => expireCookie(name, def.domain));
        } else if (def.type === 'local' || def.type === 'session') {
          try {
            const storage = def.type === 'local' ? window.localStorage : window.sessionStorage;
            storageKeys(storage).filter(test).forEach((key) => storage.removeItem(key));
          } catch (e) {
            // Storage not available.
          }
        }
      });
    });
  });
}

/* Unblocking ------------------------------------------------------------------------------------ */

function cloneScript(old) {
  const script = document.createElement('script');

  [...old.attributes].forEach((attr) => {
    if (attr.name !== 'type' && !attr.name.startsWith('data-lcookies-')) {
      script.setAttribute(attr.name, attr.value);
    }
  });

  if (old.dataset.lcookiesType) {
    script.type = old.dataset.lcookiesType;
  }

  if (old.nonce) {
    script.nonce = old.nonce;
  }

  script.text = old.text;

  const external = old.hasAttribute('src');
  let loaded = Promise.resolve();

  // Keep the original order for classic scripts: wait for each one before running the next.
  if (external && !old.hasAttribute('async') && script.type !== 'module') {
    script.async = false;
    loaded = new Promise((resolve) => {
      script.addEventListener('load', resolve);
      script.addEventListener('error', resolve);
    });
  }

  return { script, loaded };
}

async function runScript(old) {
  const { script, loaded } = cloneScript(old);
  old.replaceWith(script);
  await loaded;
}

async function runTemplate(template) {
  const fragment = template.content.cloneNode(true);
  const parent = template.parentNode;

  for (const node of [...fragment.childNodes]) {
    if (node.nodeName === 'SCRIPT') {
      const { script, loaded } = cloneScript(node);
      parent.insertBefore(script, template);
      // eslint-disable-next-line no-await-in-loop
      await loaded;
    } else {
      if (node.querySelectorAll) {
        node.querySelectorAll('script').forEach((inner) => inner.replaceWith(cloneScript(inner).script));
      }

      parent.insertBefore(node, template);
    }
  }

  template.remove();
}

function showFrame(frame) {
  const id = frame.dataset.lcookiesId;

  frame.src = frame.dataset.lcookiesSrc;
  frame.removeAttribute('data-lcookies-src');
  frame.hidden = false;

  if (id) {
    document.querySelectorAll(`[data-lcookies-for="${CSS.escape(id)}"]`).forEach((el) => el.remove());
  }
}

async function runActivation() {
  const nodes = document.querySelectorAll(
    'script[type="text/plain"][data-lcookies-category], template[data-lcookies-category], iframe[data-lcookies-src][data-lcookies-category]',
  );

  for (const node of nodes) {
    const cat = node.dataset.lcookiesCategory;

    if (!api.hasConsent(cat) || !node.isConnected) {
      continue;
    }

    activated.add(cat);

    if (node.nodeName === 'SCRIPT') {
      // eslint-disable-next-line no-await-in-loop
      await runScript(node);
    } else if (node.nodeName === 'TEMPLATE') {
      // eslint-disable-next-line no-await-in-loop
      await runTemplate(node);
    } else {
      showFrame(node);
    }
  }
}

function activate() {
  queue = queue.then(runActivation).catch(() => {});
}

/* Placeholders ---------------------------------------------------------------------------------- */

function sizePlaceholder(placeholder, frame) {
  const width = frame.getAttribute('width');
  const height = frame.getAttribute('height');

  if (/^\d+$/.test(width || '') && /^\d+$/.test(height || '')) {
    // min-height: auto lets the box grow when the text does not fit in the iframe's proportion.
    placeholder.style.aspectRatio = `${width} / ${height}`;
    placeholder.style.maxWidth = `${width}px`;
    placeholder.style.minHeight = 'auto';
  } else if (/^\d+$/.test(height || '')) {
    placeholder.style.minHeight = `${height}px`;
  }
}

function addPlaceholders() {
  document.querySelectorAll('iframe[data-lcookies-src][data-lcookies-category]').forEach((frame) => {
    const cat = frame.dataset.lcookiesCategory;

    if (api.hasConsent(cat)) {
      return;
    }

    let { lcookiesId: id } = frame.dataset;
    let placeholder = id ? document.querySelector(`[data-lcookies-for="${CSS.escape(id)}"]`) : null;

    if (!placeholder && contract.iframePlaceholder && placeholderTemplate) {
      placeholderCounter += 1;
      id = id || `lcd${placeholderCounter}`;
      frame.dataset.lcookiesId = id;
      frame.hidden = true;

      const category = categories.get(cat);
      const service = services.get(frame.dataset.lcookiesService);

      frame.insertAdjacentHTML('beforebegin', placeholderTemplate
        .replaceAll('{id}', escapeHtml(id))
        .replaceAll('{service}', escapeHtml(service ? service.title : ''))
        .replaceAll('{category}', escapeHtml(category ? category.title : cat))
        .replaceAll('{categoryAlias}', escapeHtml(cat)));
      placeholder = frame.previousElementSibling;
    }

    if (placeholder && !placeholder.dataset.lcookiesSized) {
      placeholder.dataset.lcookiesSized = '1';
      sizePlaceholder(placeholder, frame);
    }
  });
}

/* Interface ------------------------------------------------------------------------------------- */

function showFloating(show) {
  const button = floating();

  if (button) {
    button.hidden = !show;
  }
}

function showBanner() {
  const el = banner();

  if (!el) {
    return;
  }

  if (el.nodeName === 'DIALOG') {
    if (!el.open) {
      el.showModal();
    }
  } else {
    el.hidden = false;
  }

  showFloating(false);
}

function hideBanner() {
  const el = banner();

  if (el) {
    if (el.nodeName === 'DIALOG') {
      if (el.open) {
        el.close();
      }
    } else {
      el.hidden = true;
    }
  }

  showFloating(true);
}

function syncToggles() {
  const prefs = preferences();
  const consent = currentConsent();

  prefs.querySelectorAll('[data-lcookies-toggle]').forEach((input) => {
    const blocked = isGpcBlocked(input.value);
    const notice = input.closest('section')?.querySelector('[data-lcookies-gpc]');

    input.checked = !blocked && Boolean(consent) && api.hasConsent(input.value);
    input.disabled = blocked;

    if (notice) {
      notice.hidden = !blocked;
    }
  });
}

function openPreferences(trigger) {
  const prefs = preferences();

  if (!prefs || prefs.open) {
    return;
  }

  opener = trigger || document.activeElement;
  syncToggles();
  prefs.showModal();
}

function onPreferencesClosed() {
  if (!currentConsent()) {
    showBanner();
  }

  if (opener && opener.isConnected && typeof opener.focus === 'function' && opener.getClientRects().length) {
    opener.focus();
  } else {
    const target = currentConsent() ? floating() : banner()?.querySelector('button');

    if (target && !target.hidden) {
      target.focus();
    }
  }

  opener = null;
}

function selectedCategories() {
  return [...preferences().querySelectorAll('[data-lcookies-toggle]:checked')].map((input) => input.value);
}

function onClick(event) {
  const target = event.target.closest('[data-lcookies-action], [data-lcookies-allow], [data-lcookies-open], a[href$="#lcookies-settings"]');

  if (!target) {
    return;
  }

  const action = target.dataset.lcookiesAction;

  if (target.dataset.lcookiesAllow) {
    commit([...grantedOptional(), target.dataset.lcookiesAllow], 'allow');
  } else if (action === 'accept') {
    commit(optional, 'accept_all');
  } else if (action === 'reject') {
    commit([], 'reject_all');
  } else if (action === 'save') {
    commit(selectedCategories(), 'custom');
  } else if (action === 'close') {
    preferences().close();
  } else {
    event.preventDefault();
    openPreferences(target);
  }
}

/* Start ----------------------------------------------------------------------------------------- */

function init() {
  contract.categories.forEach((category) => {
    categories.set(category.alias, category);
    (category.required ? required : optional).push(category.alias);
    category.services.forEach((service) => services.set(service.alias, service));
  });

  const template = root.querySelector('template[data-lcookies-placeholder]');
  placeholderTemplate = template ? template.innerHTML.trim() : '';

  const prefs = preferences();
  const bannerEl = banner();

  document.addEventListener('click', onClick);

  if (prefs) {
    prefs.addEventListener('close', onPreferencesClosed);
  }

  if (bannerEl && bannerEl.nodeName === 'DIALOG') {
    // The modal banner asks for a choice: Escape does not dismiss it. If the browser closes it
    // anyway, the floating button stays available.
    bannerEl.addEventListener('cancel', (event) => event.preventDefault());
  }

  Object.assign(api, {
    open: () => openPreferences(null),
    acceptAll: () => commit(optional, 'accept_all'),
    rejectAll: () => commit([], 'reject_all'),
    save: (cats) => commit(Array.isArray(cats) ? cats : [], 'custom'),
    allow: (cat) => commit([...grantedOptional(), cat], 'allow'),
    contract,
  });

  if (!api._scan) {
    cleanup();
  }

  addPlaceholders();

  if (currentConsent()) {
    showFloating(!api._scan);
    activate();
  } else if (!api._scan) {
    showBanner();
  }

  // Blocked elements added later (by other scripts or the head guard).
  const relevant = (node) => node.nodeType === 1
    && (node.hasAttribute('data-lcookies-category') || Boolean(node.querySelector('[data-lcookies-category]')));

  new MutationObserver((mutations) => {
    if (mutations.some((m) => m.type === 'attributes' || [...m.addedNodes].some(relevant))) {
      addPlaceholders();
      activate();
    }
  }).observe(document.documentElement, { childList: true, subtree: true, attributeFilter: ['data-lcookies-category'] });

  document.dispatchEvent(new CustomEvent('lcookies:ready', { detail: { consent: api.getConsent() } }));
}

if (contract && api && root) {
  init();
}
