// /rb/rb.mod/pwa/service-worker.js
// SW_VERSION: 2025-09-13_01

self.addEventListener('install', (e) => {
  // 새 SW를 즉시 활성화하도록 보장
  e.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (e) => {
  // 기존 클라이언트 즉시 장악
  e.waitUntil(self.clients.claim());
});

// 상대/절대 어떤 문자열이 와도 SW 스코프 기준 절대 URL로 변환
function abs(u) {
  try {
    return new URL(u, self.registration.scope).href;
  } catch (_) {
    try { return new URL(String(u || '/'), self.registration.scope).href; }
    catch (_2) { return self.registration.scope; }
  }
}

self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch (_) {}

  const title = (data && data.title) ? String(data.title) : '알림';
  const ICON_DEFAULT = abs('/data/pwa/icons/icon-192.png');

  const iconUrl  = abs((data && data.icon)  ? data.icon  : ICON_DEFAULT);
  const imageUrl = (data && data.image) ? abs(data.image) : null;
  const target   = (data && data.url)   ? data.url : '/';

  const opts = {
    body:  (data && data.body) ? String(data.body) : '',
    data:  { url: target },
    icon:  iconUrl
    // badge/image는 조건부로 아래에서 세팅
  };

  // 안드로이드 Big Picture
  if (imageUrl) opts.image = imageUrl;

  // payload에 badge가 있을 때만 사용 (기본 배지는 넣지 않음)
  if (data && data.badge) {
    try { opts.badge = abs(data.badge); } catch (_) {}
  }

  event.waitUntil(self.registration.showNotification(title, opts));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const raw = (event.notification && event.notification.data && event.notification.data.url) || '/';
  const dest = abs(raw);

  event.waitUntil((async () => {
    const all = await clients.matchAll({ type: 'window', includeUncontrolled: true });

    // dest의 origin+path+search를 기준으로 비교
    let destUrl;
    try { destUrl = new URL(dest); } catch (_) {}

    if (destUrl) {
      for (const client of all) {
        try {
          const cu = new URL(client.url);
          const sameOrigin = (cu.origin === destUrl.origin);
          const samePath   = (cu.pathname === destUrl.pathname && cu.search === destUrl.search);

          if (sameOrigin && samePath) {
            await client.focus();
            return;
          }
        } catch (_) {}
      }
    }

    // 같은 탭이 없으면 새 창/탭
    await clients.openWindow(dest);
  })());
});
