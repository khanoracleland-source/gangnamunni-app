# User content cache

Enabled surfaces:
- `hospitals.php`: hospital cards, including filtered/sorted results and empty lists.
- `index.php`: treatment category table in every display mode.

Admin sessions use the original server-rendered path. Admin pages, authentication,
booking, payment, mypage, community and consultation forms are not stored by this
cache. Detail pages and other home sections remain server-rendered. Images retain
their existing browser cache policy.

The document shell still goes through PHP/session validation. Hospital list SQL
is deferred to a same-URL GET with `X-SB-Content: hospitals`; treatment queries use
`X-SB-Content: treatments`. These return JSON containing only the rendered list
fragment and a session/account scope hash. No full documents or login exchange
scripts are persisted. Normal requests retain their page-specific scripts/styles.

Local storage keys include scope, page URL, sorted query parameters and language.
Fresh TTL is 15 minutes; entries older than 7 days are removed. Every mount
revalidates, including fresh cache hits. At most 24 entries / approximately 1.5M
characters are retained. Storage failures do not block fresh rendering.

Identical HTML renews the saved time without replacing DOM. The refresh button
requests the server with `cache: no-store`. Pull-to-refresh is intentionally disabled;
swiping down does not trigger a request or reload. Concurrent refresh
requests for a key share a promise. Network/HTTP/JSON failures preserve the last
good fragment; a cold failure shows retry. Session changes clear visible content
and reload the shell. Dynamic fragments rebind wishlist and ad impression hooks.

Deployment: deploy all changed PHP files and both new JS assets together.
No Flutter rebuild is needed for these website changes. Existing HTTP-cached
documents from the former max-age policy can persist until expiry; allow that
window or reload the WebView once when verifying deployment.

This is not an offline app shell. A cold navigation without connectivity still
needs a separately designed WebView shell/offline navigation solution. Offline
refresh of an already loaded page preserves its cached content.

Run:
```
node --test hairwang_full/gangnamunni/tests/user-content-cache.test.cjs
php -n -d zend.assertions=1 -d assert.exception=1 hairwang_full/gangnamunni/tests/user-content-fragment.test.php
```

Before production rollout, verify with the real DB on Android/iOS: navigate from
home to filtered hospitals and back, refresh, change region/sort, toggle wishlist,
change treatment category, switch accounts, and disconnect during a refresh.
