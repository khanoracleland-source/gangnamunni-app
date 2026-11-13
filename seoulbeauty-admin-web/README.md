# seoulbeauty-admin-web (Next.js admin panel)

Super-admin dashboard on top of `seoulbeauty-admin-api` (NestJS). Independent of `seoulbeauty-web`:
own `package.json`, own port, own systemd unit.

## Run
```
cp .env.example .env     # optional: every key has a default (see below)
npm ci
npm run dev              # http://127.0.0.1:3400/admin-next
npm run build && npm start
```

| Key | Default | Meaning |
|---|---|---|
| `API_BASE` | `http://127.0.0.1:3300` | admin API, target of the `/admin-next/api/*` rewrite |
| `NEXT_BASE_PATH` | `/admin-next` | URL prefix (must match the Apache `ProxyPass`) |
| `NEXT_PUBLIC_ADMIN_PHP_LOGIN_URL` | `/hairwang/gangnamunni/dashboard.php` | PHP admin dashboard: login, "기존 관리자" link, logout |

`NEXT_BASE_PATH` and `NEXT_PUBLIC_*` are baked in by `npm run build`: rebuild after changing them.

## How it works
- Login stays in the PHP admin. The browser calls `/admin-next/api/v1/me` and
  `/admin-next/api/v1/dashboard/summary` with the PHP session cookie (same origin).
- `401` → redirect to the PHP login page; `403` → "최고관리자만 접근할 수 있습니다";
  `404` (panel switched off) / `503` → error screen with a retry button.
- Numbers refresh every 60 s while the tab is visible, plus a manual 새로고침 button.
- The sidebar is drawn from one array: `src/lib/menu.ts`. Add new pages there.
- Day/night mode shares the `admin_theme` localStorage key with the PHP admin.

Deploy: see `deploy/`.
