# seoulbeauty-web (Next.js pilot)

Hospital list + detail on top of `seoulbeauty-api` (NestJS). Navigation between pages is client-side:
React Query keeps every visited page in memory (like a Redux store), so back/forward and repeat visits
paint instantly and refresh in the background. Detail data is prefetched on touch.

## Run
```
cp .env.example .env     # API_BASE, NEXT_PUBLIC_ASSET_BASE, PORT
npm ci
npm run dev              # http://localhost:3000/hospitals
npm run build && npm start
```
Routes: `/hospitals?q=&treatment=&category=&region=&sort=` and `/hospitals/[id]`.
`/api/*` is rewritten to the NestJS API (same origin, no CORS).

Other bottom-nav tabs still link to the PHP pages (full load) during the pilot.
