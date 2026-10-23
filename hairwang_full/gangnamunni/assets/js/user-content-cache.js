(function () {
    'use strict';
    if (!window.UserContentCache) return;
    var storage;
    try { storage = window.localStorage; } catch (_) { storage = null; }
    var cache = UserContentCache.createCache(storage);
    // Runs once per page load (head, defer) AND after every Turbo in-page visit: the new
    // <body> carries fresh, unmounted [data-user-cache] sections.
    function mountAll() {
    document.querySelectorAll('[data-user-cache]').forEach(function (section) {
        var name = section.dataset.userCache;
        if (['hospitals', 'treatments'].indexOf(name) === -1) return;
        if (section.dataset.cacheMounted === '1') return;
        section.dataset.cacheMounted = '1';
        var scope = section.dataset.cacheScope;
        var content = section.querySelector('[data-cache-content]');
        var status = section.querySelector('[data-cache-status]');
        var key = UserContentCache.keyFor(scope, name, location.href, document.documentElement.lang);
        var visibleHtml = null;
        var pending = null;
        cache.clearOtherScopes(scope);
        function show(html) {
            if (visibleHtml === html) return;
            content.innerHTML = html;
            visibleHtml = html;
            section.setAttribute('data-cache-cold', 'false');
            document.dispatchEvent(new CustomEvent('sb:content-updated', { detail: { name: name, root: content } }));
        }
        function refresh() {
            if (pending) return pending;
            section.setAttribute('aria-busy', 'true');
            section.setAttribute('data-cache-cold', visibleHtml === null ? 'true' : 'false');
            status.textContent = visibleHtml === null ? '불러오는 중...' : '';
            pending = cache.refresh(key, async function () {
                var controller = new AbortController();
                var timer = setTimeout(function () { controller.abort(); }, 20000);
                try {
                    var response = await fetch(location.href, {
                        credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                        headers: { 'X-SB-Content': name, 'Accept': 'application/json' }
                    });
                    if (!response.ok || response.redirected) throw new Error('Content unavailable');
                    var data = await response.json();
                    if (data.scope !== scope) {
                        cache.remove(key);
                        visibleHtml = null;
                        content.replaceChildren();
                        location.reload();
                        throw new Error('Session changed');
                    }
                    if (typeof data.html !== 'string') throw new Error('Invalid response');
                    return data.html;
                } finally { clearTimeout(timer); }
            }).then(function (html) {
                show(html);
                status.textContent = '';
            }).catch(function () {
                status.textContent = visibleHtml === null
                    ? '목록을 불러오지 못했습니다. 다시 시도해주세요.'
                    : '새로고침하지 못했습니다. 저장된 목록을 표시합니다.';
            }).finally(function () {
                pending = null;
                section.setAttribute('aria-busy', 'false');
            });
            return pending;
        }
        var saved = cache.read(key);
        if (saved) show(saved.html);
        refresh();
        function onOnline() { if (section.isConnected) refresh(); }
        function onPageShow(event) { if (event.persisted && section.isConnected) refresh(); }
        window.addEventListener('online', onOnline);
        window.addEventListener('pageshow', onPageShow);
        // Drop this section's window listeners once Turbo has replaced the body
        // (at turbo:before-render the old body is still attached, so check after render).
        document.addEventListener('turbo:render', function cleanup() {
            if (section.isConnected) return;
            window.removeEventListener('online', onOnline);
            window.removeEventListener('pageshow', onPageShow);
            document.removeEventListener('turbo:render', cleanup);
        });
    });
    }
    mountAll();
    document.addEventListener('turbo:load', mountAll);
})();
