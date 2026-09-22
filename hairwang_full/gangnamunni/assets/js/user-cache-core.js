(function (root) {
    'use strict';
    var PREFIX = 'sb_content_v2:';
    var TTL = 15 * 60 * 1000;
    var MAX_AGE = 7 * 24 * 60 * 60 * 1000;
    var MAX_ENTRIES = 24;
    var MAX_SIZE = 1500000;

    function createCache(storage, clock) {
        clock = clock || Date.now;
        var inFlight = new Map();
        function remove(key) {
            try { storage.removeItem(PREFIX + key); } catch (_) {}
        }
        function read(key) {
            try {
                var entry = JSON.parse(storage.getItem(PREFIX + key));
                if (!entry) return null;
                if (typeof entry.html !== 'string' || !Number.isFinite(entry.savedAt) ||
                    clock() < entry.savedAt || clock() - entry.savedAt >= MAX_AGE) {
                    remove(key);
                    return null;
                }
                entry.isStale = clock() - entry.savedAt >= TTL;
                return entry;
            } catch (_) { remove(key); return null; }
        }
        function prune() {
            try {
                var entries = Object.keys(storage).filter(function (key) { return key.indexOf(PREFIX) === 0; }).map(function (key) {
                    var entry = read(key.slice(PREFIX.length));
                    return entry && { key: key, savedAt: entry.savedAt, size: storage.getItem(key).length };
                }).filter(Boolean).sort(function (a, b) { return a.savedAt - b.savedAt; });
                var size = entries.reduce(function (sum, entry) { return sum + entry.size; }, 0);
                while (entries.length > MAX_ENTRIES || size > MAX_SIZE) {
                    var oldest = entries.shift();
                    size -= oldest.size;
                    storage.removeItem(oldest.key);
                }
            } catch (_) {}
        }
        function write(key, html) {
            try {
                if (html.length > MAX_SIZE) return;
                storage.setItem(PREFIX + key, JSON.stringify({ html: html, savedAt: clock() }));
                prune();
            } catch (_) { prune(); }
        }
        function clearOtherScopes(scope) {
            try {
                Object.keys(storage).forEach(function (key) {
                    if (key.indexOf(PREFIX) === 0 && key.indexOf(PREFIX + scope + '|') !== 0) storage.removeItem(key);
                });
            } catch (_) {}
        }
        function refresh(key, loader) {
            if (inFlight.has(key)) return inFlight.get(key);
            var task = Promise.resolve().then(loader).then(function (html) {
                if (typeof html !== 'string') throw new Error('Invalid content');
                write(key, html); // Renew TTL even when the visible data is unchanged.
                return html;
            }).finally(function () { inFlight.delete(key); });
            inFlight.set(key, task);
            return task;
        }
        return { read: read, refresh: refresh, remove: remove, clearOtherScopes: clearOtherScopes };
    }
    function keyFor(scope, name, href, language) {
        var url = new URL(href);
        url.hash = '';
        url.searchParams.delete('server_render');
        url.searchParams.sort();
        return scope + '|' + name + '|' + language + '|' + url.href;
    }
    var api = { createCache: createCache, keyFor: keyFor };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    root.UserContentCache = api;
})(typeof window !== 'undefined' ? window : globalThis);
