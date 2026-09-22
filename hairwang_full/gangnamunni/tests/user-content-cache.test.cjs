const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { createCache, keyFor } = require('../assets/js/user-cache-core.js');

function storage() {
    const box = {};
    Object.defineProperties(box, {
        getItem: { value: key => box[key] || null },
        setItem: { value: (key, value) => { box[key] = value; } },
        removeItem: { value: key => { delete box[key]; } }
    });
    return box;
}

test('TTL, stale fallback, hard expiry and unchanged response renewal', async () => {
    let now = 100;
    const cache = createCache(storage(), () => now);
    await cache.refresh('user|list', () => '<p>clinic</p>');
    now += 16 * 60000;
    assert.equal(cache.read('user|list').isStale, true);
    await assert.rejects(cache.refresh('user|list', () => Promise.reject(Error('offline'))));
    assert.equal(cache.read('user|list').html, '<p>clinic</p>');
    await cache.refresh('user|list', () => '<p>clinic</p>');
    assert.equal(cache.read('user|list').isStale, false);
    now += 7 * 86400000;
    assert.equal(cache.read('user|list'), null);
});

test('empty results, corrupt storage, blocked persistence, deduplication', async () => {
    const box = storage();
    const cache = createCache(box);
    await cache.refresh('empty', () => '');
    assert.equal(cache.read('empty').html, '');
    box.setItem('sb_content_v2:broken', '{');
    assert.equal(cache.read('broken'), null);
    assert.equal(await createCache(null).refresh('key', () => 'fresh'), 'fresh');
    let calls = 0;
    const loader = async () => { calls++; return 'new'; };
    await Promise.all([cache.refresh('same', loader), cache.refresh('same', loader)]);
    assert.equal(calls, 1);
});

test('keys isolate query, language, session; old scopes and excess entries removed', async () => {
    assert.equal(keyFor('a', 'hospitals', 'https://example.test/?b=2&a=1#x', 'ko'),
        keyFor('a', 'hospitals', 'https://example.test/?a=1&b=2', 'ko'));
    assert.notEqual(keyFor('a', 'hospitals', 'https://example.test/', 'ko'),
        keyFor('b', 'hospitals', 'https://example.test/', 'ko'));
    const box = storage();
    const cache = createCache(box);
    await cache.refresh('old|page', () => 'old');
    cache.clearOtherScopes('new');
    assert.equal(cache.read('old|page'), null);
    for (let i = 0; i < 30; i++) await cache.refresh('new|' + i, () => 'data');
    assert.equal(Object.keys(box).length, 24);
});

function harness(box, fetcher, name = 'hospitals') {
    const listeners = {};
    const winListeners = {};
    const button = { addEventListener: (type, fn) => { button[type] = fn; } };
    const status = {};
    const content = { writes: 0, set innerHTML(html) { this.html = html; this.writes++; }, replaceChildren() { this.html = ''; } };
    const section = { dataset: { userCache: name, cacheScope: 'user' }, setAttribute() {},
        querySelector: selector => ({ '[data-cache-content]': content, '[data-cache-status]': status, '[data-cache-refresh]': button })[selector] };
    const document = { documentElement: { lang: 'ko' }, querySelectorAll: () => [section],
        dispatchEvent() {}, addEventListener: (name, fn) => { listeners[name] = fn; } };
    const location = { href: 'https://example.test/hospitals.php', reload() { location.reloaded = true; } };
    const window = { UserContentCache: { createCache, keyFor }, localStorage: box, scrollY: 0,
        addEventListener: (name, fn) => { winListeners[name] = fn; } };
    vm.runInNewContext(fs.readFileSync(require.resolve('../assets/js/user-content-cache.js'), 'utf8'), {
        window, UserContentCache: window.UserContentCache, document, location, fetch: fetcher,
        AbortController, setTimeout, clearTimeout, CustomEvent: class {}
    });
    return { button, status, content, listeners, location };
}
const settle = () => new Promise(resolve => setImmediate(resolve));
const response = html => ({ ok: true, redirected: false, json: async () => ({ scope: 'user', html }) });

test('UI restores immediately, preserves DOM and offline data, and only explicit refresh bypasses cache', async () => {
    const box = storage();
    await createCache(box).refresh(keyFor('user', 'hospitals', 'https://example.test/hospitals.php', 'ko'), () => 'cached');
    let calls = 0;
    let offline = false;
    const ui = harness(box, async (_, options) => {
        calls++;
        assert.equal(options.cache, 'no-store');
        if (offline) throw Error('offline');
        return response(calls < 3 ? 'cached' : 'fresh');
    });
    assert.equal(ui.content.html, 'cached');
    assert.equal(ui.status.textContent, '');
    await settle();
    assert.equal(ui.content.writes, 1);
    offline = true;
    await ui.button.click();
    assert.equal(ui.content.html, 'cached');
    offline = false;
    assert.equal(ui.listeners.touchstart, undefined);
    assert.equal(ui.listeners.touchend, undefined);
    assert.equal(ui.listeners.touchcancel, undefined);
    assert.equal(calls, 2);
    await ui.button.click();
    assert.equal(ui.content.html, 'fresh');
    assert.equal(calls, 3);
});

test('cold error retries; session change clears visible content; admin mounts do not fetch', async () => {
    let fail = true;
    const ui = harness(storage(), async () => {
        if (fail) throw Error('offline');
        return { ok: true, json: async () => ({ scope: 'other', html: 'private' }) };
    });
    assert.match(ui.status.textContent, /불러오는/);
    await settle();
    assert.match(ui.status.textContent, /못했습니다/);
    fail = false;
    await ui.button.click();
    assert.equal(ui.location.reloaded, true);
    assert.equal(ui.content.html, '');
    let calls = 0;
    harness(storage(), () => { calls++; }, 'admin');
    await settle();
    assert.equal(calls, 0);
});
