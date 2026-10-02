/**
 * LCookies - head bootstrap.
 *
 * plg_system_lcookies inlines this file at the top of <head>, before any other script, right after
 * `window.lcookiesHead = {...}` (configuration derived from the contract):
 *   v: policy version, n: consent cookie name, d: validity in days, req: required categories,
 *   rules: [{c: category, s: service, p: [patterns]}], gcm: null | {waitForUpdate, adsDataRedaction,
 *   urlPassthrough, map: {category: [consent types]}}, scan: "none" | "all" (cookie scanner only, with
 *   all: [every category]); in scan mode the visitor's own choice is ignored and nothing is stored.
 *   preview: true (live preview of the options in the backend): no stored choice, nothing stored.
 *
 * It reads the stored consent, sets the Google Consent Mode defaults, and keeps scripts and iframes
 * that other scripts create later blocked until their category is accepted. It defines
 * window.LCookies with hasConsent()/getConsent(); lcookies.js adds the rest of the API.
 *
 * @copyright  (C) 2026 leocostadeveloper
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
(function (w, d) {
  'use strict';

  var c = w.lcookiesHead;

  if (!c || w.LCookies) {
    return;
  }

  var GCM_TYPES = ['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage',
    'functionality_storage', 'personalization_storage', 'security_storage'];

  function readConsent() {
    var m = d.cookie.match(new RegExp('(?:^|;\\s*)' + c.n + '=([^;]*)'));

    if (!m) {
      return null;
    }

    try {
      var v = JSON.parse(decodeURIComponent(m[1]));

      if (v && v.v === c.v && Array.isArray(v.cats) && typeof v.ts === 'number'
        && (v.ts + c.d * 86400) * 1000 > Date.now()) {
        return v;
      }
    } catch (e) {
      // Invalid cookie: ask again.
    }

    return null;
  }

  var state = { consent: readConsent() };

  if (c.scan) {
    state.consent = c.scan === 'all' ? { id: '', v: c.v, cats: c.all.slice(), ts: Math.floor(Date.now() / 1000) } : null;
  } else if (c.preview) {
    state.consent = null;
  }

  function granted(cat) {
    return c.req.indexOf(cat) !== -1 || (!!state.consent && state.consent.cats.indexOf(cat) !== -1);
  }

  // Patterns: "/.../" is a regular expression, anything else a case-insensitive substring.
  var rules = (c.rules || []).map(function (r) {
    return {
      c: r.c,
      s: r.s,
      p: r.p.map(function (p) {
        if (p.length > 2 && p.charAt(0) === '/' && p.charAt(p.length - 1) === '/') {
          try {
            return new RegExp(p.slice(1, -1), 'i');
          } catch (e) {
            return null;
          }
        }

        return p.toLowerCase();
      }).filter(Boolean),
    };
  });

  function match(text) {
    if (!text) {
      return null;
    }

    var str = String(text);
    var lower = str.toLowerCase();

    for (var i = 0; i < rules.length; i++) {
      for (var j = 0; j < rules[i].p.length; j++) {
        var p = rules[i].p[j];

        if (typeof p === 'string' ? lower.indexOf(p) !== -1 : p.test(str)) {
          return rules[i];
        }
      }
    }

    return null;
  }

  function blockedRule(url) {
    var rule = match(url);

    return rule && !granted(rule.c) ? rule : null;
  }

  // Google Consent Mode v2.
  function gcmState(onlyRequired) {
    var result = {};

    GCM_TYPES.forEach(function (t) {
      result[t] = 'denied';
    });

    Object.keys(c.gcm.map).forEach(function (cat) {
      if (onlyRequired ? c.req.indexOf(cat) !== -1 : granted(cat)) {
        c.gcm.map[cat].forEach(function (t) {
          result[t] = 'granted';
        });
      }
    });

    return result;
  }

  function gcmUpdate() {
    if (c.gcm) {
      w.gtag('consent', 'update', gcmState(false));
    }
  }

  if (c.gcm) {
    w.dataLayer = w.dataLayer || [];

    if (typeof w.gtag !== 'function') {
      w.gtag = function () {
        // Consent Mode needs the arguments object itself, not an array.
        // eslint-disable-next-line prefer-rest-params
        w.dataLayer.push(arguments);
      };
    }

    var defaults = gcmState(true);
    defaults.wait_for_update = c.gcm.waitForUpdate;
    w.gtag('consent', 'default', defaults);

    if (c.gcm.adsDataRedaction) {
      w.gtag('set', 'ads_data_redaction', true);
    }

    if (c.gcm.urlPassthrough) {
      w.gtag('set', 'url_passthrough', true);
    }

    if (state.consent) {
      gcmUpdate();
    }
  }

  // Guard for elements created after the page was rendered (the server blocks the rest).
  function mark(el, rule) {
    el.setAttribute('data-lcookies-category', rule.c);
    el.setAttribute('data-lcookies-service', rule.s);
  }

  if (rules.length) {
    var scriptSrc = Object.getOwnPropertyDescriptor(HTMLScriptElement.prototype, 'src');
    var scriptType = Object.getOwnPropertyDescriptor(HTMLScriptElement.prototype, 'type');
    var frameSrc = Object.getOwnPropertyDescriptor(HTMLIFrameElement.prototype, 'src');
    var setAttr = Element.prototype.setAttribute;
    var createElement = d.createElement;

    var guardScript = function (el) {
      Object.defineProperties(el, {
        src: {
          configurable: true,
          get: function () {
            return scriptSrc.get.call(this);
          },
          set: function (value) {
            var rule = blockedRule(value);

            if (rule) {
              if (scriptType.get.call(this) === 'module') {
                setAttr.call(this, 'data-lcookies-type', 'module');
              }

              scriptType.set.call(this, 'text/plain');
              mark(this, rule);
            }

            scriptSrc.set.call(this, value);
          },
        },
        type: {
          configurable: true,
          get: function () {
            return scriptType.get.call(this);
          },
          set: function (value) {
            if (this.hasAttribute('data-lcookies-category') && !granted(this.getAttribute('data-lcookies-category'))) {
              if (value === 'module') {
                setAttr.call(this, 'data-lcookies-type', 'module');
              }

              return;
            }

            scriptType.set.call(this, value);
          },
        },
      });

      el.setAttribute = function (name, value) {
        var n = String(name).toLowerCase();

        if (n === 'src' || n === 'type') {
          this[n] = value;
        } else {
          setAttr.call(this, name, value);
        }
      };
    };

    var guardFrame = function (el) {
      Object.defineProperty(el, 'src', {
        configurable: true,
        get: function () {
          return frameSrc.get.call(this);
        },
        set: function (value) {
          var rule = blockedRule(value);

          if (rule) {
            mark(this, rule);
            setAttr.call(this, 'data-lcookies-src', value);
          } else {
            frameSrc.set.call(this, value);
          }
        },
      });

      el.setAttribute = function (name, value) {
        if (String(name).toLowerCase() === 'src') {
          this.src = value;
        } else {
          setAttr.call(this, name, value);
        }
      };
    };

    d.createElement = function (tag) {
      // eslint-disable-next-line prefer-rest-params
      var el = createElement.apply(this, arguments);
      var name = String(tag).toLowerCase();

      if (name === 'script') {
        guardScript(el);
      } else if (name === 'iframe') {
        guardFrame(el);
      }

      return el;
    };

    // Safety net for markup inserted with innerHTML and similar.
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          var nodes = [];

          if (node.nodeType !== 1) {
            return;
          }

          if (node.tagName === 'SCRIPT' || node.tagName === 'IFRAME') {
            nodes.push(node);
          } else if (node.querySelectorAll) {
            nodes = Array.prototype.slice.call(node.querySelectorAll('script[src],iframe[src]'));
          }

          nodes.forEach(function (el) {
            if (el.hasAttribute('data-lcookies-category') || el.hasAttribute('data-lcookies-skip')) {
              return;
            }

            var rule = blockedRule(el.getAttribute('src'));

            if (!rule) {
              return;
            }

            mark(el, rule);

            if (el.tagName === 'SCRIPT') {
              if (el.type === 'module') {
                setAttr.call(el, 'data-lcookies-type', 'module');
              }

              scriptType.set.call(el, 'text/plain');
            } else {
              setAttr.call(el, 'data-lcookies-src', el.getAttribute('src'));
              el.removeAttribute('src');
            }
          });
        });
      });
    });

    observer.observe(d.documentElement, { childList: true, subtree: true });

    // Firefox can still cancel a parser-inserted script here.
    d.addEventListener('beforescriptexecute', function (event) {
      var el = event.target;

      if (el.getAttribute('type') === 'text/plain' || blockedRule(el.getAttribute('src'))) {
        event.preventDefault();
      }
    }, true);
  }

  w.LCookies = {
    hasConsent: function (cat) {
      return granted(cat);
    },
    getConsent: function () {
      return state.consent ? JSON.parse(JSON.stringify(state.consent)) : null;
    },
    // Internal, used by lcookies.js.
    _state: state,
    _readConsent: readConsent,
    _gcmUpdate: gcmUpdate,
    _scan: c.scan || null,
    _preview: Boolean(c.preview),
    // CSP nonce of this script, given to the scripts LCookies runs after consent.
    _nonce: (d.currentScript && d.currentScript.nonce) || '',
  };
}(window, document));
