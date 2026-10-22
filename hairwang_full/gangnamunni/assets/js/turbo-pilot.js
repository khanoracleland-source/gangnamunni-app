/**
 * Turbo Drive PILOT (opt-in only).
 *
 * - Turbo is loaded only on pages that include this file (currently price_compare.php).
 * - Drive is OFF by default: only links/forms inside an element with data-turbo="true"
 *   are handled by Turbo. Everything else (login, payment, Kakao, external domains,
 *   custom URL schemes, bottom-nav tabs) stays a normal full-page navigation.
 * - Any Turbo failure falls back to a normal full page load (Turbo's own behaviour).
 *   Turbo has no request timeout, so we add one (FETCH_TIMEOUT_MS): if the server does
 *   not answer in time we do a normal full navigation, which brings back the native
 *   app's progress bar and error screen.
 *
 * Native app contract: after every *Turbo* page render (never on the first full load,
 * which Flutter already sees via onPageFinished) we post {action:'pageChanged'} to
 * FlutterBridge. Flutter decides what to do (URL tracking, header back button, throttled
 * session/cookie work). Push-token registration is intentionally NOT repeated here.
 */
(function () {
  'use strict';
  if (!window.Turbo || window.__sbTurboPilot) return;
  window.__sbTurboPilot = true;

  var FETCH_TIMEOUT_MS = 8000;

  Turbo.session.drive = false;

  var debug = false;
  try { debug = localStorage.getItem('sb_turbo_debug') === '1'; } catch (e) {}

  var firstLoad = true;
  var fetchStart = 0;
  var timeoutId = null;

  function clearFetchTimeout() {
    if (timeoutId !== null) { clearTimeout(timeoutId); timeoutId = null; }
  }

  // A request is leaving: start the clock and arm the full-page-load fallback.
  document.addEventListener('turbo:before-fetch-request', function (event) {
    fetchStart = performance.now();
    clearFetchTimeout();
    var url = event.detail && event.detail.url ? String(event.detail.url) : null;
    if (!url) return;
    timeoutId = setTimeout(function () {
      timeoutId = null;
      if (debug) console.log('[turbo-pilot] no response in ' + FETCH_TIMEOUT_MS + ' ms, full load: ' + url);
      window.location.href = url;
    }, FETCH_TIMEOUT_MS);
  });

  // The server answered (or failed): Turbo handles it from here, rendering is local.
  document.addEventListener('turbo:before-fetch-response', clearFetchTimeout);
  document.addEventListener('turbo:fetch-request-error', clearFetchTimeout);

  // Turbo snapshots the page when leaving it, including the value the user just picked
  // but has not been rendered yet. Restore the server-rendered values so that Back shows
  // a filter that matches the results on screen.
  document.addEventListener('turbo:before-cache', function () {
    var forms = document.querySelectorAll('form[data-turbo="true"]');
    for (var i = 0; i < forms.length; i++) forms[i].reset();
  });

  // Fires on the initial load AND after every Turbo visit.
  document.addEventListener('turbo:load', function () {
    clearFetchTimeout();
    if (firstLoad) { firstLoad = false; return; }

    if (debug && fetchStart) {
      console.log('[turbo-pilot] request to render: ' + Math.round(performance.now() - fetchStart) + ' ms');
    }

    try {
      if (window.FlutterBridge && typeof window.FlutterBridge.postMessage === 'function') {
        window.FlutterBridge.postMessage(JSON.stringify({ action: 'pageChanged', source: 'turbo' }));
      }
    } catch (e) { /* native side is optional */ }
  });
})();
