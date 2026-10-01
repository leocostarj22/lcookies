/**
 * LCookies module - shows the visitor's choice.
 *
 * Fills every [data-mod-lcookies-status] from window.LCookies (plg_system_lcookies) once it is
 * ready and after each change, so the module HTML stays the same for every visitor (cacheable).
 * Texts come from the element: data-none, data-necessary, data-some and data-date (%s is replaced).
 *
 * @copyright  (C) 2026 leocostadeveloper
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

const lang = document.documentElement.lang || undefined;

function list(items) {
  try {
    return new Intl.ListFormat(lang, { type: 'conjunction' }).format(items);
  } catch (e) {
    return items.join(', ');
  }
}

function date(ts) {
  try {
    return new Intl.DateTimeFormat(lang, { dateStyle: 'long' }).format(new Date(ts * 1000));
  } catch (e) {
    return new Date(ts * 1000).toLocaleDateString();
  }
}

function render() {
  const api = window.LCookies;
  const consent = api.getConsent();
  const optional = (api.contract?.categories || []).filter((c) => !c.required);
  const accepted = consent ? optional.filter((c) => consent.cats.includes(c.alias)).map((c) => c.title) : [];

  document.querySelectorAll('[data-mod-lcookies-status]').forEach((el) => {
    const { none, necessary, some } = el.dataset;
    let text = none;

    if (consent) {
      text = accepted.length ? some.replace('%s', list(accepted)) : necessary;
      text += ` ${el.dataset.date.replace('%s', date(consent.ts))}`;
    }

    el.textContent = text;
    el.hidden = false;
  });
}

if (typeof window.LCookies?.open === 'function') {
  render();
} else {
  document.addEventListener('lcookies:ready', render, { once: true });
}

document.addEventListener('lcookies:change', render);
