# seoulbeauty-api (NestJS, read-only pilot)

Existing MariaDB (`gangnamunni`) -> JSON for the future Next.js frontend. PHP keeps running on the same DB.

## Endpoints (v1)
| Path | Notes |
|---|---|
| `/health` | DB ping |
| `/v1/hospitals` | `q, category, treatment, region(a,b), sort(manual\|price\|rating\|popular\|review), limit(1-100), offset` |
| `/v1/hospitals/:id` | detail |
| `/v1/hospitals/:id/reviews` | only `is_active=1 AND review_status='published'` |
| `/v1/hospitals/:id/treatments`, `/doctors` | detail.php 시술목록 / 의료진 |
| `/v1/me` | login state (PHP session via `api/session_me.php`) |
| `POST /v1/wishlist/toggle`, `GET /v1/wishlist/hospitals/:id` | writes `gangnamunni_wishlist` |
| `POST /v1/consultations` | writes `gangnamunni_consultations` |

## Run
```
cp .env.example .env   # DB user: SELECT + limited writes (deploy/README.md); Node >= 22.9 loads .env via --env-file-if-exists
npm ci && npm run build && npm start
# with systemd/pm2 you may instead provide the env vars directly
npm test
BASE=http://127.0.0.1:3100 ./scripts/smoke.sh
```
Listens on 127.0.0.1:3100 by default; put nginx/Apache proxy in front (e.g. `/api-next/`).

## Not ported yet (PHP-only logic in includes/hospital_list_data.php)
dosage filter, keyword->treatment resolver, ad-slot ranking + in-feed ads, region_mix, user wishlist state, auth.
All SQL is parameterised (the PHP version interpolates escaped strings).
