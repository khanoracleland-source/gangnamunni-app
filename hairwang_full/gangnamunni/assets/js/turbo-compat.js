/**
 * Turbo Drive compatibility layer for the Seoul Beauty pages (loaded synchronously in <head>,
 * before any page script, only when includes/turbo_boot.php enables Turbo for the request).
 *
 * Why this exists: Turbo swaps <body> and RE-EXECUTES the page's inline scripts on every
 * visit, but `document`/`window` and everything the head loaded stay alive. Without this layer
 * that means duplicated document listeners (e.g. ad click tracking fired twice), piled-up
 * timers, missed DOMContentLoaded handlers and libraries executed again.
 *
 * What it does
 *  1. Navigation gate: only pages listed in window.SB_TURBO.pages (same directory) are
 *     visited in-page. Everything else (login, payment, Kakao, downloads, redirects to a
 *     non-listed page, non-HTML / error responses) is a normal full navigation.
 *  2. Forms are NOT driven by Turbo unless they sit inside data-turbo="true".
 *  3. Listeners on document/window/<html>, intervals and timeouts created by INLINE <script>s
 *     in <body> are removed before the next body is rendered (like a real page unload).
 *     Listeners from external libraries (src=) and head scripts are left alone.
 *  4. DOMContentLoaded / load handlers registered by inline body scripts during a Turbo
 *     render are run (once) right after the render, as a real page load would have done.
 *  5. External <script src> already loaded are not run again; missing ones are loaded (in order)
 *     before the new body is rendered.
 *  6. Safety nets: no response within 8 s, HTTP error, non-HTML or off-list redirect, or a
 *     same-origin script error right after a render => normal full page load. A page whose
 *     render failed is remembered for the rest of the session and always loaded normally.
 *  7. Posts {action:'pageChanged'} to the Flutter bridge after each Turbo render.
 *
 * The progress-bar colour lives in the server-injected <style> (turbo_boot.php): styles created
 * from JS would be removed by Turbo's head merge on the next visit.
 */
(function () {
  'use strict';
  if (window.__sbTurboCompat) return;
  window.__sbTurboCompat = true;

  var CFG = window.SB_TURBO || { pages: [] };
  var ALLOW = {};
  for (var i = 0; i < CFG.pages.length; i++) ALLOW[CFG.pages[i]] = 1;

  var FETCH_TIMEOUT_MS = 8000;
  var HEAD_SCRIPT_WAIT_MS = 6000;
  var ERROR_NET_MS = 2500;
  var BAD_KEY = 'sb_turbo_bad';

  var debug = false;
  try { debug = localStorage.getItem('sb_turbo_debug') === '1'; } catch (e) {}
  function log() {
    if (debug && window.console) console.log.apply(console, ['[turbo]'].concat([].slice.call(arguments)));
  }

  // ------------------------------------------------------------------ url helpers
  function pageKey(u) {
    try {
      var url = new URL(u, location.href);
      if (url.origin !== location.origin) return null;
      var p = url.pathname;
      var dir = p.substring(0, p.lastIndexOf('/') + 1);
      var curDir = location.pathname.substring(0, location.pathname.lastIndexOf('/') + 1);
      if (dir !== curDir) return null;
      var name = p.substring(p.lastIndexOf('/') + 1);
      return name === '' ? 'index.php' : name;
    } catch (e) { return null; }
  }
  function badSet() {
    try { return JSON.parse(sessionStorage.getItem(BAD_KEY) || '{}'); } catch (e) { return {}; }
  }
  function markBad(key) {
    if (!key) return;
    try { var s = badSet(); s[key] = 1; sessionStorage.setItem(BAD_KEY, JSON.stringify(s)); } catch (e) {}
  }
  function isAllowedUrl(u) {
    var k = pageKey(u);
    return !!(k && ALLOW[k] && !badSet()[k]);
  }
  function fullLoad(url) {
    log('full load', url || location.href);
    if (url && url !== location.href) window.location.href = url; else window.location.reload();
  }

  // ------------------------------------------------------------------ ownership tracking
  var phase = false;          // true while a Turbo render is executing the new body's scripts
  var netUntil = 0;           // errors before this time are attributed to the Turbo render
  var ctxBody = 0;            // >0 while running a callback that belongs to an inline body script
  var ownedListeners = [];
  var ownedTimers = [];       // [{id, interval}]

  // An INLINE script inside <body>: re-executed by Turbo on every visit, so what it creates
  // must die with the body. External (src) scripts are deduplicated and keep their listeners.
  function ownedByBody() {
    var cs = document.currentScript;
    if (cs) return !cs.src && !!document.body && document.body.contains(cs);
    return ctxBody > 0;
  }
  function withCtx(fn, self, args) {
    ctxBody++;
    try { return fn.apply(self, args); } finally { ctxBody--; }
  }

  var origAdd = EventTarget.prototype.addEventListener;
  var origRemove = EventTarget.prototype.removeEventListener;
  EventTarget.prototype.addEventListener = function (type, fn, opts) {
    var self = this;
    var isGlobal = self === document || self === window || self === document.documentElement;
    if (isGlobal && fn) {
      // Late DOMContentLoaded / load handlers would never fire after a Turbo render: run them.
      if (phase && ((type === 'DOMContentLoaded' && self === document) || (type === 'load' && self === window))) {
        setTimeout(function () {
          ctxBody++;
          try {
            var ev = new Event(type);
            if (typeof fn === 'function') fn.call(self, ev); else if (fn.handleEvent) fn.handleEvent(ev);
          } catch (err) { onScriptProblem(err); } finally { ctxBody--; }
        }, 0);
        return;
      }
      if (ownedByBody()) ownedListeners.push([self, type, fn, opts]);
    }
    return origAdd.call(self, type, fn, opts);
  };

  var origSetInterval = window.setInterval;
  var origSetTimeout = window.setTimeout;
  window.setInterval = function (fn, ms) {
    var args = [].slice.call(arguments);
    var own = typeof fn === 'function' && ownedByBody();
    if (own) { var f = fn; args[0] = function () { return withCtx(f, this, arguments); }; }
    var id = origSetInterval.apply(window, args);
    if (own) ownedTimers.push(id);
    return id;
  };
  window.setTimeout = function (fn, ms) {
    var args = [].slice.call(arguments);
    var own = typeof fn === 'function' && ownedByBody();
    if (own) { var f = fn; args[0] = function () { return withCtx(f, this, arguments); }; }
    var id = origSetTimeout.apply(window, args);
    if (own) ownedTimers.push(id);
    return id;
  };

  function cleanupOwned() {
    var l = ownedListeners; ownedListeners = [];
    for (var i = 0; i < l.length; i++) { try { origRemove.call(l[i][0], l[i][1], l[i][2], l[i][3]); } catch (e) {} }
    var t = ownedTimers; ownedTimers = [];
    for (var j = 0; j < t.length; j++) { try { clearInterval(t[j]); clearTimeout(t[j]); } catch (e) {} }
    log('cleaned', l.length, 'listeners,', t.length, 'timers');
  }

  // ------------------------------------------------------------------ script bookkeeping
  var settled = [];           // script elements whose load/error already fired
  origAdd.call(document, 'load', function (e) {
    if (e.target && e.target.tagName === 'SCRIPT') settled.push(e.target);
  }, true);
  origAdd.call(document, 'error', function (e) {
    if (e.target && e.target.tagName === 'SCRIPT') settled.push(e.target);
  }, true);

  var loaded = {};
  function norm(src) {
    try { var u = new URL(src, document.baseURI); return u.origin + u.pathname; } catch (e) { return src; }
  }
  function collectLoaded() {
    var list = document.querySelectorAll('script[src]');
    for (var i = 0; i < list.length; i++) loaded[norm(list[i].getAttribute('src'))] = 1;
  }
  collectLoaded();
  origAdd.call(document, 'DOMContentLoaded', collectLoaded);

  function waitFor(el, ms) {
    return new Promise(function (resolve) {
      var done = false;
      function fin() { if (!done) { done = true; resolve(); } }
      origAdd.call(el, 'load', fin); origAdd.call(el, 'error', fin);
      origSetTimeout.call(window, fin, ms);
    });
  }
  function loadScript(proto) {
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      for (var i = 0; i < proto.attributes.length; i++) s.setAttribute(proto.attributes[i].name, proto.attributes[i].value);
      s.async = false;
      s.onload = function () { loaded[norm(proto.getAttribute('src'))] = 1; resolve(); };
      s.onerror = function () { reject(new Error('script load failed')); };
      document.head.appendChild(s);
    });
  }

  // ------------------------------------------------------------------ Turbo events
  var fetchStart = 0, fetchTimer = null, firstLoad = true;
  function clearFetchTimer() { if (fetchTimer !== null) { clearTimeout(fetchTimer); fetchTimer = null; } }

  function onScriptProblem(err) {
    if (!(phase || Date.now() < netUntil)) return;
    log('script problem after render, full load:', err && err.message);
    markBad(pageKey(location.href));
    phase = false; netUntil = 0;
    fullLoad();
  }
  origAdd.call(window, 'error', function (ev) {
    if (ev.target && ev.target !== window) return;            // resource errors
    var f = ev.filename || '';
    if (f && f.indexOf(location.origin) !== 0) return;         // cross-origin / extension noise
    onScriptProblem(ev.error || { message: ev.message });
  });

  // Gate: which links may be followed in-page.
  origAdd.call(document, 'turbo:click', function (ev) {
    if (!isAllowedUrl(ev.detail.url)) ev.preventDefault();      // browser follows the link normally
  });
  origAdd.call(document, 'turbo:before-visit', function (ev) {
    if (!isAllowedUrl(ev.detail.url)) { ev.preventDefault(); fullLoad(ev.detail.url); }
  });

  // Forms are only driven by Turbo when explicitly opted in.
  origAdd.call(document, 'submit', function (ev) {
    var f = ev.target;
    if (f && f.tagName === 'FORM' && f.getAttribute('data-turbo') !== 'true' && !f.closest('[data-turbo="true"]')) {
      f.setAttribute('data-turbo', 'false');
    }
  }, true);

  origAdd.call(document, 'turbo:before-fetch-request', function (ev) {
    fetchStart = performance.now();
    clearFetchTimer();
    var url = ev.detail && ev.detail.url ? String(ev.detail.url) : null;
    if (!url) return;
    fetchTimer = origSetTimeout.call(window, function () {
      fetchTimer = null;
      log('no response in', FETCH_TIMEOUT_MS, 'ms');
      fullLoad(url);
    }, FETCH_TIMEOUT_MS);
  });
  origAdd.call(document, 'turbo:fetch-request-error', clearFetchTimer);

  origAdd.call(document, 'turbo:before-fetch-response', function (ev) {
    clearFetchTimer();
    var r = ev.detail.fetchResponse && ev.detail.fetchResponse.response;
    if (!r) return;
    var ct = (r.headers && r.headers.get('content-type')) || '';
    var offList = r.redirected && !isAllowedUrl(r.url);
    if (!r.ok || !/text\/html|application\/xhtml/i.test(ct) || offList) {
      ev.preventDefault();
      fullLoad(r.redirected ? r.url : location.href);
    }
  });

  // Before the new body goes in: end the old page's life, make sure its scripts are available.
  origAdd.call(document, 'turbo:before-render', function (ev) {
    cleanupOwned();
    phase = true;
    var nb = ev.detail && ev.detail.newBody;
    if (!nb) return;

    var pendingHead = [];
    var hs = document.head.querySelectorAll('script[src]');
    for (var i = 0; i < hs.length; i++) if (settled.indexOf(hs[i]) === -1 && !loaded[norm(hs[i].getAttribute('src'))]) pendingHead.push(hs[i]);

    var missing = [];
    var bs = nb.querySelectorAll('script[src]');
    for (var j = 0; j < bs.length; j++) {
      var s = bs[j], k = norm(s.getAttribute('src'));
      if (loaded[k]) { s.parentNode.removeChild(s); } else { missing.push(s); }
    }
    if (!pendingHead.length && !missing.length) return;

    ev.preventDefault();
    var resume = ev.detail.resume;
    log('waiting for', pendingHead.length, 'head and', missing.length, 'body scripts');
    Promise.all(pendingHead.map(function (h) { return waitFor(h, HEAD_SCRIPT_WAIT_MS); }))
      .then(function () {
        return missing.reduce(function (p, m) {
          return p.then(function () {
            if (loaded[norm(m.getAttribute('src'))]) return;
            return loadScript(m);
          });
        }, Promise.resolve());
      })
      .then(function () {
        for (var q = 0; q < missing.length; q++) if (missing[q].parentNode) missing[q].parentNode.removeChild(missing[q]);
        resume();
      })
      .catch(function () { phase = false; fullLoad(); });
  });

  origAdd.call(document, 'turbo:render', function () {
    phase = false;
    netUntil = Date.now() + ERROR_NET_MS;
  });

  // Turbo snapshots the page when leaving it; restore server-rendered form values first.
  origAdd.call(document, 'turbo:before-cache', function () {
    var forms = document.querySelectorAll('form[data-turbo="true"]');
    for (var i = 0; i < forms.length; i++) forms[i].reset();
  });

  origAdd.call(document, 'turbo:load', function () {
    clearFetchTimer();
    collectLoaded();
    if (firstLoad) { firstLoad = false; return; }
    if (debug && fetchStart) log('request to render:', Math.round(performance.now() - fetchStart), 'ms');
    try {
      if (window.FlutterBridge && typeof window.FlutterBridge.postMessage === 'function') {
        window.FlutterBridge.postMessage(JSON.stringify({ action: 'pageChanged', source: 'turbo' }));
      }
    } catch (e) { /* the native side is optional */ }
  });

})();
