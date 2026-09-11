# Seoul Beauty SEO setup

The public site uses `https://seoulbeauty.app/gangnamunni/`. The domain root
currently redirects there. HMS keeps its existing `/hairwang/gangnamunni/` path.

## Files served by the public document root

Apache serves `seoulbeauty.app` from the repository's `public/` directory.
The existing contractor robots file is `public/robots.txt`; preserve it unchanged.
Cloudflare adds its managed crawler rules to the HTTP response.

`public/sitemap.xml` is the original 500-URL XML from `seoulbeauty_SEO.zip`
(`02_stiemap/sitemap.xml`), copied byte-for-byte at the user's latest request.
It is served at `https://seoulbeauty.app/sitemap.xml` next to the existing robots
file. Deploy this file to `public/`, not `hairwang_full/`.

The earlier `hairwang_full/sitemap.xml` is a separate, cleaned 213-URL candidate,
not the sitemap served at the domain root. Its generator and tests below apply
only to that candidate. Do not replace the requested original with it implicitly.

PHP application changes remain under `hairwang_full/gangnamunni/`.
`gangnamunni/seo.php` is required by `theme_helper.php`.

In the cleaned candidate, export timestamps and priority fields were removed. Add `lastmod` only from
verified significant content updates; neither deployment time nor file mtime
is an appropriate substitute. A database-backed sitemap covering records beyond
the supplied snapshot remains a separate extension.

## Regenerate and validate the separate cleaned candidate

```sh
python3 scripts/build_seoulbeauty_sitemap.py /path/to/seoulbeauty_SEO.zip
python3 tests/seo/test_sitemap.py
php -n tests/seo/canonical_test.php
```

The PNG favicon is the supplied 1024x1024 image. The ICO is the supplied 16x16
fallback. HTML declares their actual sizes and uses absolute URL paths.

## Production checks after deployment

- `/sitemap.xml`: HTTP 200, XML content, byte-identical to the original 500-URL attachment.
- `/robots.txt`: existing contractor content remains unchanged. Keep the existing
  Cloudflare managed configuration as well.
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

Root-path correction verified on 2026-09-11: `/sitemap.xml` now returns HTTP
200 and exactly matches the original 500-URL attachment. The origin robots file
and the public robots response are unchanged.
