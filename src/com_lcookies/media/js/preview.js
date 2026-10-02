/**
 * LCookies - live preview of the banner in the options (field "lcookiespreview").
 *
 * Sends the options being edited (unsaved) to task=preview.render whenever the form changes and
 * shows the returned page, built with the frontend layouts, CSS and JavaScript, in the iframe.
 *
 * @copyright  (C) 2026 leocostadeveloper
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

const options = window.Joomla.getOptions('com_lcookies.preview');
const previews = [...document.querySelectorAll('[data-lcookies-preview]')];
const DELAY = 400;
// Size of the simulated screen: the page is rendered at this size and scaled down to fit.
const SCREENS = { desktop: [1280, 760], mobile: [390, 760] };

let timer = null;
let request = null;
let show = 'banner';

function fit(preview) {
  const [width, height] = SCREENS[preview.dataset.lcookiesPreviewScreen || 'desktop'];
  const available = preview.clientWidth;

  if (!available) {
    return;
  }

  const scale = Math.min(1, available / width);
  const box = preview.querySelector('.lcookies-preview__frame');
  const frame = box.querySelector('iframe');

  frame.style.width = `${width}px`;
  frame.style.height = `${height}px`;
  frame.style.transform = `scale(${scale})`;
  box.style.width = `${Math.round(width * scale)}px`;
  box.style.height = `${Math.round(height * scale)}px`;
}

async function refresh() {
  const form = previews[0]?.closest('form');

  if (!form) {
    return;
  }

  const body = new FormData();

  new FormData(form).forEach((value, key) => {
    if (key.startsWith('jform[') && typeof value === 'string') {
      body.append(key, value);
    }
  });

  body.append('show', show);
  body.append(options.token, '1');

  request?.abort();
  request = new AbortController();

  try {
    const response = await fetch(options.url, {
      method: 'POST', body, credentials: 'same-origin', signal: request.signal,
    });

    if (!response.ok) {
      return;
    }

    const html = await response.text();

    previews.forEach((preview) => {
      preview.querySelector('iframe').srcdoc = html;
    });
  } catch (e) {
    // Aborted by a newer change, or offline: keep the last preview.
  }
}

function schedule() {
  clearTimeout(timer);
  timer = setTimeout(refresh, DELAY);
}

previews.forEach((preview) => {
  preview.addEventListener('click', (event) => {
    const showButton = event.target.closest('[data-lcookies-preview-show]');
    const sizeButton = event.target.closest('[data-lcookies-preview-size]');

    if (showButton) {
      show = showButton.dataset.lcookiesPreviewShow;
      refresh();
    }

    if (sizeButton) {
      preview.dataset.lcookiesPreviewScreen = sizeButton.dataset.lcookiesPreviewSize;
      fit(preview);
      preview.querySelectorAll('[data-lcookies-preview-size]').forEach((button) => {
        button.setAttribute('aria-pressed', String(button === sizeButton));
      });
    }
  });
});

// The previews of hidden tabs get their size when the tab is shown.
const observer = new ResizeObserver((entries) => entries.forEach((entry) => fit(entry.target)));

previews.forEach((preview) => observer.observe(preview));

if (previews.length) {
  const form = previews[0].closest('form');

  form?.addEventListener('input', schedule);
  form?.addEventListener('change', schedule);
  refresh();
}
