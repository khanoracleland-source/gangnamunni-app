(function () {
    'use strict';
    if (!window.UserContentCache) return;
    var storage;
    try { storage = window.localStorage; } catch (_) { storage = null; }
    var cache = UserContentCache.createCache(storage);
    document.querySelectorAll('[data-user-cache]').forEach(function (section) {
        var name = section.dataset.userCache;
        if (['hospitals', 'treatments'].indexOf(name) === -1) return;
        var scope = section.dataset.cacheScope;
        var content = section.querySelector('[data-cache-content]');
        var status = section.querySelector('[data-cache-status]');
        var button = section.querySelector('[data-cache-refresh]');
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
            button.disabled = true;
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
                button.disabled = false;
                section.setAttribute('aria-busy', 'false');
            });
            return pending;
        }
        var saved = cache.read(key);
        if (saved) show(saved.html);
        button.hidden = false;
        button.addEventListener('click', refresh);
        refresh();
        window.addEventListener('online', refresh);
        window.addEventListener('pageshow', function (event) { if (event.persisted) refresh(); });
    });
})();
