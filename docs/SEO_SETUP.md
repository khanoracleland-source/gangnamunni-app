# Seoul Beauty SEO setup

The public site uses `https://seoulbeauty.app/gangnamunni/`. The domain root
currently redirects there. HMS keeps its existing `/hairwang/gangnamunni/` path.

## Files served by the public document root

`hairwang_full/` is the public-site tree. Deploy its `sitemap.xml`, `robots.txt`,
`favicon.ico`, and the changed files under `gangnamunni/` together. Do not copy
these over the separate HMS Laravel `public/` tree. The new `gangnamunni/seo.php`
is required by `theme_helper.php` and must be included in deployment.

The sitemap is a cleaned snapshot of the supplied `seoulbeauty_SEO.zip`, not an
inventory of every database record. It includes the canonical homepage, known
public listing pages, and unique clinic, doctor and event detail IDs. Login,
consultation, tab, filter and sorting variants are excluded. Content-changing
query parameters remain in page canonical tags so distinct search/pagination
results are not incorrectly consolidated.

All export timestamps and priority fields were removed. Add `lastmod` only from
verified significant content updates; neither deployment time nor file mtime
is an appropriate substitute. A database-backed sitemap covering records beyond
the supplied snapshot remains a separate extension.

## Regenerate and validate

```sh
python3 scripts/build_seoulbeauty_sitemap.py /path/to/seoulbeauty_SEO.zip
python3 tests/seo/test_sitemap.py
php -n tests/seo/canonical_test.php
```

The PNG favicon is the supplied 1024x1024 image. The ICO is the supplied 16x16
fallback. HTML declares their actual sizes and uses absolute URL paths.

## Production checks after deployment

- `/sitemap.xml`: HTTP 200, XML content, only canonical public-site URLs.
- `/robots.txt`: includes `Sitemap: https://seoulbeauty.app/sitemap.xml`.
  Cloudflare also inserts its managed rules; keep its existing configuration.
- `/gangnamunni/`: canonical points to itself and favicon assets return 200.
- Detail tab variants: canonical keeps the record ID and removes the tab.
- Login HTML: `noindex, follow`.
- Submit the sitemap in the verified Search Console property after deployment.

Live baseline on 2026-09-11: the homepage and robots.txt returned HTTP 200;
`/sitemap.xml` returned HTTP 404. Local edits alone do not alter production.

Validation: all 213 generated URLs returned HTTP 200 without redirects on
2026-09-11. The response bodies did not contain the checked clinic/doctor/event
not-found messages. This is not proof of Google indexing or full content quality.
PHP syntax checks (17 files), 11 canonical cases plus HTML escaping, two sitemap
tests, and public/HMS domain configuration checks passed.
