(function (window) {
    'use strict';

    var PREFIX = 'sb_cache_';
    var META_SUFFIX = '__meta';

    function now() {
        return Date.now();
    }

    function storageAvailable() {
        try {
            var key = PREFIX + 'test';
            window.localStorage.setItem(key, '1');
            window.localStorage.removeItem(key);
            return true;
        } catch (error) {
            return false;
        }
    }

    var canStore = storageAvailable();

    function fullKey(key) {
        return PREFIX + String(key);
    }

    function remove(key) {
        if (!canStore) return;
        var cacheKey = fullKey(key);
        window.localStorage.removeItem(cacheKey);
        window.localStorage.removeItem(cacheKey + META_SUFFIX);
    }

    var AppCache = {
        set: function (key, data, ttlSeconds) {
            if (!canStore) return false;
            var cacheKey = fullKey(key);
            var meta = {
                createdAt: now(),
                ttl: Math.max(0, Number(ttlSeconds || 0)) * 1000
            };

            try {
                window.localStorage.setItem(cacheKey, JSON.stringify(data));
                window.localStorage.setItem(cacheKey + META_SUFFIX, JSON.stringify(meta));
                return true;
            } catch (error) {
                this.clearExpired();
                try {
                    window.localStorage.setItem(cacheKey, JSON.stringify(data));
                    window.localStorage.setItem(cacheKey + META_SUFFIX, JSON.stringify(meta));
                    return true;
                } catch (retryError) {
                    remove(key);
                    return false;
                }
            }
        },

        get: function (key) {
            if (!canStore) return null;
            var cacheKey = fullKey(key);
            var raw = window.localStorage.getItem(cacheKey);
            var rawMeta = window.localStorage.getItem(cacheKey + META_SUFFIX);
            if (!raw || !rawMeta) return null;

            try {
                var meta = JSON.parse(rawMeta);
                if (!meta.createdAt || !meta.ttl || now() - meta.createdAt > meta.ttl) {
                    remove(key);
                    return null;
                }
                return JSON.parse(raw);
            } catch (error) {
                remove(key);
                return null;
            }
        },

        remember: function (key, ttlSeconds, loader) {
            var cached = this.get(key);
            if (cached !== null) return Promise.resolve(cached);

            return Promise.resolve()
                .then(loader)
                .then(function (data) {
                    AppCache.set(key, data, ttlSeconds);
                    return data;
                });
        },

        remove: remove,

        clearByPrefix: function (prefix) {
            if (!canStore) return;
            var target = fullKey(prefix);
            Object.keys(window.localStorage).forEach(function (key) {
                if (key.indexOf(target) === 0) {
                    window.localStorage.removeItem(key);
                }
            });
        },

        clearExpired: function () {
            if (!canStore) return;
            Object.keys(window.localStorage).forEach(function (key) {
                if (key.indexOf(PREFIX) !== 0 || key.slice(-META_SUFFIX.length) !== META_SUFFIX) return;
                var dataKey = key.slice(0, -META_SUFFIX.length);
                try {
                    var meta = JSON.parse(window.localStorage.getItem(key) || '{}');
                    if (!meta.createdAt || !meta.ttl || now() - meta.createdAt > meta.ttl) {
                        window.localStorage.removeItem(key);
                        window.localStorage.removeItem(dataKey);
                    }
                } catch (error) {
                    window.localStorage.removeItem(key);
                    window.localStorage.removeItem(dataKey);
                }
            });
        },

        stats: function () {
            if (!canStore) return { count: 0, sizeKB: 0 };
            var count = 0;
            var size = 0;
            Object.keys(window.localStorage).forEach(function (key) {
                if (key.indexOf(PREFIX) !== 0) return;
                size += (window.localStorage.getItem(key) || '').length;
                if (key.slice(-META_SUFFIX.length) !== META_SUFFIX) count++;
            });
            return { count: count, sizeKB: Math.round(size / 10.24) / 100 };
        }
    };

    window.AppCache = AppCache;
})(window);
