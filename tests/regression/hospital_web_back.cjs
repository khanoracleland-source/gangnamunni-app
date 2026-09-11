const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync(__dirname + '/../../hairwang_full/gangnamunni/detail.php', 'utf8');
const script = source.slice(source.indexOf('var explicitReturn ='), source.indexOf('var shareBtn ='));
function click({explicit = '', referrer = '', stored = null, sort = null, wishlist = false} = {}) {
  let listener;
  const location = {href: 'https://hms.seoulbeauty.app/hairwang/gangnamunni/detail.php?id=8216', search: wishlist ? '?from=my_wishlist' : '?id=8216'};
  const button = {href: 'hospitals.php', addEventListener: (_, fn) => listener = fn};
  let historyCalls = 0;
  vm.runInNewContext(script.replace(/<\?php[\s\S]*?\?>/, JSON.stringify(explicit)), {
    location, window: {location}, URL, URLSearchParams,
    document: {referrer, getElementById: () => button},
    localStorage: {getItem: () => sort}, sessionStorage: {getItem: () => stored}, history: {back: () => historyCalls++}
  });
  listener({preventDefault() {}});
  return {href: location.href, historyCalls};
}
const url = 'hospitals.php?treatment=%ED%8B%B0%ED%83%80%EB%8A%84&sort=price_asc&page=3';
test('header preserves full list query with empty WebView referrer and storage', () => {
  assert.equal(click({explicit: url}).href, url);
});
test('detail tabs and stale storage cannot overwrite explicit list filters', () => {
  assert.equal(click({explicit: url, referrer: 'detail.php?tab=reviews', stored: 'hospitals.php?sort=other'}).href, url);
});
test('wishlist retains its own destination', () => {
  assert.equal(click({explicit: url, wishlist: true}).href, 'my_wishlist.php');
});
test('returning from booking goes to list with saved sorting, never history', () => {
  for (const referrer of ['booking.php?id=8216', 'hospitals.php?sort=rating', '']) {
    const result = click({referrer, sort: 'rating'});
    assert.equal(result.href, 'https://hms.seoulbeauty.app/hairwang/gangnamunni/hospitals.php?sort=rating');
    assert.equal(result.historyCalls, 0);
  }
});
test('external stored URL cannot redirect the header', () => {
  assert.equal(click({stored: 'https://evil.test/hospitals.php'}).href, 'https://hms.seoulbeauty.app/hairwang/gangnamunni/hospitals.php');
});
