# seoulbeauty-admin-api — server deploy (Rocky Linux 9, systemd)

JSON API (read-only, plus hospital editing behind its own switch and its own database user) behind the admin web app (`seoulbeauty-admin-web`, `/admin-next`). It is a separate project from
`seoulbeauty-api`: own `package.json`, own port (`127.0.0.1:3300`), own systemd unit, own database user.

| Route | Answer |
|---|---|
| `GET /health` | `200 {"ok":true}` — no auth, works with the switch off |
| `GET /v1/me` | `200 {"admin":{"id","username","name","role","hospitalId","scope":"all"\|"own"},"features":{"hospitals":true\|false,"hospitalsWrite":true\|false}}` |
| `GET /v1/dashboard/summary` | members / bookings (7 and 30 days, with the previous window) / consultations / catalog — `super_admin` only |
| `GET /v1/hospitals` | approved hospitals: counters + one page of the list. Query: `search`, `treatmentId`, `active` (`all`\|`1`\|`0`), `page`, `perPage` (10\|20\|50) |
| `GET /v1/hospitals/treatment-options` | treatments actively offered by the approved hospitals in scope, grouped by category (for the list filter) |
| `GET /v1/hospitals/:id` | one approved hospital: basic data, submitted documents (present or not, no file path), images, doctors, unreviewed changes |
| `PATCH /v1/hospitals/:id` | edit an approved hospital (whitelisted fields only) — `ADMIN_HOSPITALS_WRITE=1`; super admin: any, hospital roles: their own |
| `POST /v1/hospitals/:id/active` | `{"active":true\|false}` — activate / deactivate — `super_admin` only, `ADMIN_HOSPITALS_WRITE=1` |
| `POST /v1/hospitals/:id/revisions/review` | mark the unreviewed changes as reviewed — `super_admin` only, `ADMIN_HOSPITALS_WRITE=1` |
| `GET /v1/treatments` | the whole treatment master (treatments with aliases, categories, conflict pairs, counters, home table mode) — `super_admin` only, `ADMIN_TREATMENTS_ENABLED=1` (section 13) |
| `GET /v1/treatment-units` | the active price units (`{"tableReady","items":[{"id","name"}]}`) — `super_admin` only, `ADMIN_TREATMENT_UNITS_ENABLED=1` (section 16) |

Who may call what:
- `super_admin`: everything, all hospitals (`scope: "all"`).
- `hospital_super_admin`, `hospital_admin`: only with `ADMIN_HOSPITALS_ENABLED=1`, and only `/v1/me` and `/v1/hospitals*`, limited to the
  hospital the account is bound to in the PHP admin (`scope: "own"`). An account without a bound hospital gets an empty list.
  Another hospital's id answers 404, the same as an id that does not exist.
- every other role: `403`.

`ADMIN_HOSPITALS_ENABLED` (default off; only the exact value `1` turns it on) is a second switch under `ADMIN_PANEL_ENABLED`.
While it is off, `/v1/hospitals*` answers `404 {"error":"not_found"}` for everybody and hospital roles get `403` on every
`/v1` route, exactly as before this switch existed.

Errors on `/v1/*`: `404 {"error":"not_found"}` (switch off, and also any unknown `/v1` path or wrong method; uncached), `401 {"error":"not_logged_in"}`, `403 {"error":"forbidden"}`
(signed in, but the role may not use the route), `503 {"error":"session_unavailable"}` (PHP could not be asked),
`500 {"error":"server_error"}` (anything unexpected, e.g. a database error; details only in the journal).

## 1. Database user (SELECT only)
The API runs nothing but `SELECT`. Give it its own user, limited to the tables it reads.

**First step: find the real schema name.** The code default for `DB_NAME` is `gangnamunni`, but the schema on a given
server may be named differently. Do not guess: ask the database the PHP site is connected to, then use that one name
everywhere below (GRANTs and `DB_NAME` in `.env`).
```bash
mysql -u root -p -e "SHOW DATABASES; SELECT table_schema FROM information_schema.tables WHERE table_name='g5_member';"
```
(the schema that contains `g5_member` and the `gangnamunni_*` tables is the one). In the statements below replace
`<DB_NAME>` with it and choose the password yourself. Run in `mysql -u root -p`:
```sql
CREATE USER IF NOT EXISTS 'sb_admin_ro'@'127.0.0.1' IDENTIFIED BY '<password>';
CREATE USER IF NOT EXISTS 'sb_admin_ro'@'localhost' IDENTIFIED BY '<password>';
-- members: only the columns the counters need (mb_id only to match admins; no e-mail, phone or password hash)
GRANT SELECT (mb_id, mb_datetime, mb_leave_date) ON <DB_NAME>.g5_member TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (mb_id, mb_datetime, mb_leave_date) ON <DB_NAME>.g5_member TO 'sb_admin_ro'@'localhost';
-- admins: only the flag and the member link (never username, e-mail or password columns)
GRANT SELECT (id, is_active, g5_mb_id) ON <DB_NAME>.gangnamunni_admins TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, is_active, g5_mb_id) ON <DB_NAME>.gangnamunni_admins TO 'sb_admin_ro'@'localhost';
GRANT SELECT (created_at, status, price) ON <DB_NAME>.gangnamunni_bookings TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (created_at, status, price) ON <DB_NAME>.gangnamunni_bookings TO 'sb_admin_ro'@'localhost';
GRANT SELECT (payment_type, funds_recipient, provider, status, amount, refunded_amount, paid_at) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (payment_type, funds_recipient, provider, status, amount, refunded_amount, paid_at) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_ro'@'localhost';
GRANT SELECT (created_at) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (created_at) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_ro'@'localhost';
GRANT SELECT (is_active, is_demo, status) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (is_active, is_demo, status) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_ro'@'localhost';
GRANT SELECT (status) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (status) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_ro'@'localhost';
GRANT SELECT (status, is_active) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (status, is_active) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_ro'@'localhost';
```

### Hospital pages (`/v1/hospitals*`, needed before `ADMIN_HOSPITALS_ENABLED=1`)
Column grants add up, so these can be run on top of the ones above (same user, still `SELECT` only):
```sql
-- hospitals: the columns the list and the detail page show. cert_business / cert_medical are only tested for
-- "is a file stored" inside SQL; the paths are never returned. No AI key or other secret column is granted.
GRANT SELECT (id, name, owner_name, business_number, introduction, address, phone, email, contact_name, contact_phone, website, sns_url,
  categories, badges, treatment_tags, subway_names, business_hours, latitude, longitude, status, is_active, ad_grade, rating, review_count,
  show_rating, has_unreviewed_changes, main_image_url, profile_image_url, applied_by, applied_at, created_at, updated_at,
  cert_business, cert_medical) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, name, owner_name, business_number, introduction, address, phone, email, contact_name, contact_phone, website, sns_url,
  categories, badges, treatment_tags, subway_names, business_hours, latitude, longitude, status, is_active, ad_grade, rating, review_count,
  show_rating, has_unreviewed_changes, main_image_url, profile_image_url, applied_by, applied_at, created_at, updated_at,
  cert_business, cert_medical) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_ro'@'localhost';
-- optional column (migration hospital_kakao_channel_20261113.sql); skip these two lines if it is not installed
GRANT SELECT (kakao_channel_url) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (kakao_channel_url) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_ro'@'localhost';
-- treatment filter
GRANT SELECT (hospital_id, treatment_id, status, is_active) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (hospital_id, treatment_id, status, is_active) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, name, category_id) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, name, category_id) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, name, sort_order) ON <DB_NAME>.gangnamunni_treatment_categories TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, name, sort_order) ON <DB_NAME>.gangnamunni_treatment_categories TO 'sb_admin_ro'@'localhost';
-- detail page
GRANT SELECT (id, hospital_id, image_url, image_type, sort_order, is_active) ON <DB_NAME>.hospital_images TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, hospital_id, image_url, image_type, sort_order, is_active) ON <DB_NAME>.hospital_images TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, hospital_id, name, specialty, experience, qualification, bio, photo_url, status) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, hospital_id, name, specialty, experience, qualification, bio, photo_url, status) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, hospital_id, field_name, old_value, new_value, changed_at, reviewed) ON <DB_NAME>.gangnamunni_hospital_revisions TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, hospital_id, field_name, old_value, new_value, changed_at, reviewed) ON <DB_NAME>.gangnamunni_hospital_revisions TO 'sb_admin_ro'@'localhost';
```
### Map for the location picker (`GET /v1/map-config`, part of the hospital pages)
Reads the Kakao browser key and the map provider from the PHP settings table. Read-only, no new env key.
```sql
GRANT SELECT (setting_key, setting_value) ON <DB_NAME>.gangnamunni_settings TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (setting_key, setting_value) ON <DB_NAME>.gangnamunni_settings TO 'sb_admin_ro'@'localhost';
```
Without the grant (or without the table) the route still answers `200 {"provider":"osm","key":null}` and the reason is
logged as a warning; the page then shows no Kakao map, only the latitude/longitude fields. The answer is cached for 5 minutes,
so after changing a setting or the grant allow that long (or restart the service).
```bash
curl -s -H 'Cookie: PHPSESSID=<value>' http://127.0.0.1:3300/v1/map-config
# {"provider":"kakao","key":"<browser key>"}   or   {"provider":"osm","key":null}
```
The key is a browser (JavaScript) key, meant to be sent to the page. In the Kakao developers console the `seoulbeauty.app`
domain must be listed among the registered web domains of that key, otherwise the SDK refuses to load.

What happens when something is missing:
- the `gangnamunni_hospitals` grant (list, counters, main detail query) is required: without it those routes answer
  `500 {"error":"server_error"}` and the reason is in the journal;
- `kakao_channel_url`, `hospital_images`, `gangnamunni_doctors` (detail columns), `gangnamunni_hospital_revisions` and the
  three treatment tables are optional for the page: a missing table, column or grant is logged as a warning and that
  part is served empty (no KakaoTalk URL, no images, no doctors, no change list, empty treatment filter list).
  The exception is choosing a treatment in the list filter (`?treatmentId=`): that query reads the treatment tables
  inside the main list query, so without their grants it answers `500` as well. Give the treatment grants below
  before relying on that filter.
- A `GRANT` naming a column that does not exist fails as a whole: remove that column from the statement and run it again.
A table that does not exist yet (e.g. `gangnamunni_platform_payments`) cannot be granted: skip that line; the API reports
its figures as 0. Any other database error (for example a missing grant) makes the summary fail with 500 and is logged.
`gangnamunni_admins.g5_mb_id` (admin <-> member link) comes from an optional migration of the PHP admin panel. If the
column does not exist yet, drop `g5_mb_id` from the admins grant (`GRANT SELECT (id, is_active)`): the summary then shows
the number of admins and reports "admins who are also members" as 0 (an unknown column or a missing grant on these two
admin figures is logged as a warning and counted as 0, it does not fail the page).
If a later page needs more columns, extend the grant instead of switching to a wider user.

## 2. PHP side
`hairwang_full/gangnamunni/api/admin_session_me.php` must be deployed (it answers who the admin of the forwarded session
cookie is). Check it from the server, without a cookie:
```bash
curl -s https://seoulbeauty.app/<path to gangnamunni>/api/admin_session_me.php
# {"loggedIn":false,"admin":null}
```

## 3. Build and env
```bash
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/seoulbeauty-admin-api
npm ci
npm run build
cp .env.example .env && chown root:sbapi .env && chmod 640 .env
# edit .env: DB_USER / DB_PASSWORD / DB_NAME (the schema found in step 1), ADMIN_PHP_SESSION_URL (+ ADMIN_PHP_SESSION_HOST for loopback)
# leave ADMIN_PANEL_ENABLED=0 until everything below is verified; ADMIN_HOSPITALS_ENABLED=0 until the hospital GRANTs of step 1 are in
```
`ADMIN_PHP_SESSION_URL` must reach the same Apache vhost admins use for the PHP admin pages, otherwise PHP opens another
session and everybody looks signed out. Loopback form: `http://127.0.0.1/<path>/api/admin_session_me.php` with
`ADMIN_PHP_SESSION_HOST=seoulbeauty.app`.

## 4. Service
```bash
sudo cp deploy/seoulbeauty-admin-api.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now seoulbeauty-admin-api
curl -s http://127.0.0.1:3300/health            # {"ok":true}
curl -s http://127.0.0.1:3300/v1/me             # {"error":"not_found"}  (switch still off)
```

## 5. Turn it on
Set `ADMIN_PANEL_ENABLED=1` in `.env`, then `sudo systemctl restart seoulbeauty-admin-api`.
```bash
curl -s http://127.0.0.1:3300/v1/me             # {"error":"not_logged_in"}
# with the PHPSESSID of a browser where a super admin is signed in to the PHP admin:
curl -s -H 'Cookie: PHPSESSID=<value>' http://127.0.0.1:3300/v1/me
curl -s -H 'Cookie: PHPSESSID=<value>' http://127.0.0.1:3300/v1/dashboard/summary
```
`{"error":"session_unavailable"}` here means the API cannot get a valid answer from `ADMIN_PHP_SESSION_URL`
(wrong path or host, a redirect, PHP error): see `journalctl -u seoulbeauty-admin-api -n 50`.

Hospital pages: after the hospital GRANTs of step 1, set `ADMIN_HOSPITALS_ENABLED=1` and restart.
```bash
curl -s -H 'Cookie: PHPSESSID=<value>' http://127.0.0.1:3300/v1/me                              # "features":{"hospitals":true}
curl -s -H 'Cookie: PHPSESSID=<value>' 'http://127.0.0.1:3300/v1/hospitals?perPage=10'
curl -s -H 'Cookie: PHPSESSID=<value>' http://127.0.0.1:3300/v1/hospitals/treatment-options
curl -s -H 'Cookie: PHPSESSID=<value>' http://127.0.0.1:3300/v1/hospitals/<id>
# with the PHPSESSID of a hospital admin: the list holds only that hospital; any other id -> {"error":"not_found"}
```
Turning it off again (`ADMIN_HOSPITALS_ENABLED=0` + restart) hides the hospital routes and locks hospital roles out; the
dashboard keeps working.

Rollback: `ADMIN_PANEL_ENABLED=0` + restart (or `systemctl disable --now seoulbeauty-admin-api`). Nothing else on the
server depends on this service.

## 6. Editing hospitals (`ADMIN_HOSPITALS_WRITE`, optional)
Off by default. With it off the three writing routes answer `404 {"error":"not_found"}`, `/v1/me` reports
`"hospitalsWrite":false`, no write database connection is ever opened and nothing in the database can change.
It only works together with `ADMIN_HOSPITALS_ENABLED=1`.

What a write may touch (everything else is rejected by the API and by the database user):
- `gangnamunni_hospitals`: name, owner_name, business_number, introduction, address, phone, email, contact_name, contact_phone,
  website, sns_url, categories, badges, treatment_tags, subway_names, business_hours, latitude, longitude, show_rating,
  is_active, has_unreviewed_changes, updated_at. Never `ad_grade`, `status`, `rating`, certificates, `kakao_channel_url`.
- `gangnamunni_hospital_revisions.reviewed`.
Values are stored in the same format as the PHP admin writes them (tag lists as JSON array text, `business_hours` as a JSON
object text, hyphenated phone, `updated_at = NOW()`), so the PHP pages and the patient app read them unchanged.
Each write is one short `UPDATE` (the review is one short transaction); no row or table locks are taken. A save is refused
with `409` when `updated_at` changed since the page was loaded (also when the PHP admin saved in between).

### 6.1 Write database user (run as the DB admin)
A second, separate user: `sb_admin_rw`. `<DB_NAME>` is the schema from step 1. Replace `<PASSWORD>` with a new strong password.
```sql
CREATE USER IF NOT EXISTS 'sb_admin_rw'@'127.0.0.1' IDENTIFIED BY '<PASSWORD>';
CREATE USER IF NOT EXISTS 'sb_admin_rw'@'localhost' IDENTIFIED BY '<PASSWORD>';

-- gangnamunni_hospitals: it may read only the two columns a write needs, and update only the editable columns
GRANT SELECT (id, updated_at) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, updated_at) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (name, owner_name, business_number, introduction, address, phone, email, contact_name, contact_phone, website, sns_url,
  categories, badges, treatment_tags, subway_names, business_hours, latitude, longitude, show_rating, is_active, has_unreviewed_changes,
  updated_at) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (name, owner_name, business_number, introduction, address, phone, email, contact_name, contact_phone, website, sns_url,
  categories, badges, treatment_tags, subway_names, business_hours, latitude, longitude, show_rating, is_active, has_unreviewed_changes,
  updated_at) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'localhost';

-- gangnamunni_hospital_revisions: only the "reviewed" mark
GRANT SELECT (hospital_id, reviewed) ON <DB_NAME>.gangnamunni_hospital_revisions TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (hospital_id, reviewed) ON <DB_NAME>.gangnamunni_hospital_revisions TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (reviewed) ON <DB_NAME>.gangnamunni_hospital_revisions TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (reviewed) ON <DB_NAME>.gangnamunni_hospital_revisions TO 'sb_admin_rw'@'localhost';

SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1';   -- check: no INSERT / DELETE, no ad_grade / status / rating; cert_* only after section 11
```
The read-only `sb_admin_ro` of step 1 stays as it is (it does the existence and duplicate-phone checks).

### 6.2 Env and turning it on
In `.env` (same file as step 3; keep `chmod 640 root:sbapi`):
```
ADMIN_HOSPITALS_WRITE=1
DB_RW_USER=sb_admin_rw
DB_RW_PASSWORD=<PASSWORD>
# DB_HOST / DB_PORT / DB_NAME are shared with the read-only user. Optional: DB_RW_POOL=2 (connections of the write pool)
```
Then `sudo systemctl restart seoulbeauty-admin-api`. The write connection is opened on the first write, not at start-up:
if the user or its grants are wrong, only writing answers `503 {"error":"write_unavailable"}` (reason in the journal);
reading and the dashboard keep working.

Check (a PHPSESSID of a signed-in super admin; use a test hospital; `expectedUpdatedAt` is `hospital.updatedAt` of the detail answer):
```bash
H='-H Content-Type:application/json -H X-Requested-With:admin-next -H Cookie:PHPSESSID=<value>'
curl -s -H 'Cookie: PHPSESSID=<value>' http://127.0.0.1:3300/v1/me                       # "features":{"hospitals":true,"hospitalsWrite":true}
curl -s -H 'Cookie: PHPSESSID=<value>' http://127.0.0.1:3300/v1/hospitals/<id>           # note hospital.updatedAt
curl -s -X PATCH $H -d '{"expectedUpdatedAt":"<updatedAt>","fields":{"introduction":"test"}}' http://127.0.0.1:3300/v1/hospitals/<id>
                                                                                            # {"updatedAt":"..."}
curl -s -X PATCH $H -d '{"expectedUpdatedAt":"<old updatedAt>","fields":{"introduction":"test"}}' http://127.0.0.1:3300/v1/hospitals/<id>
                                                                                            # 409 {"error":"conflict","updatedAt":"..."}
curl -s -X PATCH $H -d '{"expectedUpdatedAt":"<updatedAt>","fields":{"adGrade":"S"}}' http://127.0.0.1:3300/v1/hospitals/<id>
                                                                                            # 400 {"error":"invalid","fields":{"adGrade":"not_allowed"}}
curl -s -X PATCH -H 'Content-Type: application/json' -H 'Cookie: PHPSESSID=<value>' -d '{}' http://127.0.0.1:3300/v1/hospitals/<id>
                                                                                            # 403 {"error":"csrf"}  (marker header missing)
```
Other answers: `403 {"error":"forbidden"}` (hospital roles on `active` / `review`), `404` (hospital outside the account's scope,
pending, or unknown), `400 {"error":"invalid","fields":{"<field>":"required|too_long|bad_format|bad_url|duplicate_phone|not_allowed"}}`.
Every write is logged in the journal: `hospital write: admin=<id> role=<role> hospital=<id> action=<update|active|review> fields=<names>`
(field names only, never values): `journalctl -u seoulbeauty-admin-api | grep 'hospital write'`.

Turning it off: `ADMIN_HOSPITALS_WRITE=0` + restart (routes 404 again, editing buttons disappear). To also close the door at the database:
`DROP USER 'sb_admin_rw'@'127.0.0.1'; DROP USER 'sb_admin_rw'@'localhost';`. The PHP admin is not involved in any of this and keeps working.

## 7. Sales card, deleting hospitals and the archive (`ADMIN_HOSPITALS_DELETE`, optional)
Two separate things:
- **Sales card** (`GET /v1/hospitals/:id/stats`): read-only, needs only `ADMIN_HOSPITALS_ENABLED=1` and the extra SELECT grants of 7.1.
- **Delete + archive** (`GET /v1/hospitals/:id/delete-check`, `POST /v1/hospitals/:id/delete`, `GET /v1/hospital-archive`,
  `GET /v1/hospital-archive/:id`, `POST /v1/hospital-archive/:id/ack`): super admin only, off by default. They need
  `ADMIN_HOSPITALS_ENABLED=1`, `ADMIN_HOSPITALS_WRITE=1` and `ADMIN_HOSPITALS_DELETE=1`. With any of the three off these
  routes answer `404 {"error":"not_found"}` and `/v1/me` reports `"hospitalsDelete":false,"archive":false`.

What a delete does, in ONE transaction of the write pool (any error rolls everything back):
1. re-checks the blockers (open bookings, open consultations, unsettled money), locks the hospital row;
2. inserts the archive record (`gangnamunni_hospital_deletions`: sealed booking/sales totals and a JSON snapshot without
   the API key, certificates or document paths);
3. deletes the rows of `gangnamunni_doctor_treatments` (of its doctors), `gangnamunni_doctors`, `gangnamunni_hospital_treatments`,
   `hospital_images`, `gangnamunni_hospital_revisions` and finally `gangnamunni_hospitals` (`WHERE updated_at <=> expected`).
Bookings, payments, settlements, consultations and reviews are never touched (no write right on them exists).
Image and document FILES stay on the server; only database rows are deleted.
A delete is refused (409 `blocked`) while the hospital has: bookings not finished (`pending`, `hospital_checking`, `confirmed`; any date),
consultations still open (`pending`, `approved`, `contact_failed`), a settlement whose status is not `paid` (or whose newest settlement
ended with a negative balance), real hospital payments or verified refunds (`refund_verified`) that fall in no settlement period (before the first settlement, in a month without one, or after the newest), so a payment never turned into a settlement also blocks. Deactivate such a hospital instead.
One month after a delete the archive shows a notice ("bron va to'lov yozuvlarini tozalash mumkin"); nothing is cleaned up automatically.

**Before the FIRST delete take a database dump (a delete cannot be undone from the panel).**

### 7.1 Database (run as the DB admin; `<DB_NAME>` = schema from step 1)
Archive table (the API never creates it): `mysql -u root -p <DB_NAME> < deploy/sql/hospital_deletions.sql`

Read-only user `sb_admin_ro` (adds up to the grants of step 1; sales card, delete-check, archive):
```sql
-- bookings / payments / settlements / consultations: only the columns the figures and blockers read
GRANT SELECT (hospital_id, created_at, status, price, booking_date, booking_time) ON <DB_NAME>.gangnamunni_bookings TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (hospital_id, created_at, status, price, booking_date, booking_time) ON <DB_NAME>.gangnamunni_bookings TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, hospital_id, payment_type, funds_recipient, provider, status, amount, refunded_amount, paid_at) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (payment_id, event_type, received_at) ON <DB_NAME>.gangnamunni_payment_events TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, hospital_id, payment_type, funds_recipient, provider, status, amount, refunded_amount, paid_at) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_ro'@'localhost';
GRANT SELECT (payment_id, event_type, received_at) ON <DB_NAME>.gangnamunni_payment_events TO 'sb_admin_ro'@'localhost';
GRANT SELECT (hospital_id, status) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (hospital_id, status) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, hospital_id, status, period_start, period_end, treatment_gross, refunds, platform_fee, adjustments, carry_forward_amount) ON <DB_NAME>.gangnamunni_hospital_settlements TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, hospital_id, status, period_start, period_end, treatment_gross, refunds, platform_fee, adjustments, carry_forward_amount) ON <DB_NAME>.gangnamunni_hospital_settlements TO 'sb_admin_ro'@'localhost';
-- counts of what a delete removes (hospitals / hospital_images / doctors / hospital_treatments are granted in step 1)
-- archive
GRANT SELECT (id, hospital_id, hospital_name, snapshot, bookings_total, bookings_cancelled, sales_paid, sales_refunded, sales_net,
  deleted_by_name, deleted_at, notice_acked_at) ON <DB_NAME>.gangnamunni_hospital_deletions TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, hospital_id, hospital_name, snapshot, bookings_total, bookings_cancelled, sales_paid, sales_refunded, sales_net,
  deleted_by_name, deleted_at, notice_acked_at) ON <DB_NAME>.gangnamunni_hospital_deletions TO 'sb_admin_ro'@'localhost';
```
(The sales card also needs `hospital_id` of bookings and payments: included above. Without a grant, `/stats` and `delete-check`
answer `500 {"error":"server_error"}` on purpose: an empty figure would be a lie. The archive answers `503 {"error":"archive_unavailable"}`.)

Write user `sb_admin_rw` (adds up to the grants of 6.1; deleting + archive; still NO right on bookings / payments / settlements / consultations beyond SELECT):
```sql
-- reads inside the delete transaction: blockers, snapshot, counts
GRANT SELECT (hospital_id, status, booking_date, booking_time) ON <DB_NAME>.gangnamunni_bookings TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (hospital_id, status, booking_date, booking_time) ON <DB_NAME>.gangnamunni_bookings TO 'sb_admin_rw'@'localhost';
GRANT SELECT (id, hospital_id, payment_type, funds_recipient, provider, status, amount, refunded_amount, paid_at) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (payment_id, event_type, received_at) ON <DB_NAME>.gangnamunni_payment_events TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, hospital_id, payment_type, funds_recipient, provider, status, amount, refunded_amount, paid_at) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_rw'@'localhost';
GRANT SELECT (payment_id, event_type, received_at) ON <DB_NAME>.gangnamunni_payment_events TO 'sb_admin_rw'@'localhost';
GRANT SELECT (hospital_id, status) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (hospital_id, status) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_rw'@'localhost';
GRANT SELECT (id, hospital_id, status, period_start, period_end, treatment_gross, refunds, platform_fee, adjustments, carry_forward_amount) ON <DB_NAME>.gangnamunni_hospital_settlements TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, hospital_id, status, period_start, period_end, treatment_gross, refunds, platform_fee, adjustments, carry_forward_amount) ON <DB_NAME>.gangnamunni_hospital_settlements TO 'sb_admin_rw'@'localhost';
-- the hospital row (snapshot columns; id and updated_at were granted in 6.1)
GRANT SELECT (name, owner_name, business_number, introduction, address, phone, email, contact_name, contact_phone, website, sns_url, categories, badges,
  treatment_tags, subway_names, business_hours, latitude, longitude, status, is_active, ad_grade, rating, review_count, show_rating, main_image_url,
  profile_image_url, applied_by, applied_at, created_at) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (name, owner_name, business_number, introduction, address, phone, email, contact_name, contact_phone, website, sns_url, categories, badges,
  treatment_tags, subway_names, business_hours, latitude, longitude, status, is_active, ad_grade, rating, review_count, show_rating, main_image_url,
  profile_image_url, applied_by, applied_at, created_at) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'localhost';
GRANT SELECT (id, hospital_id, name, specialty) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, hospital_id, name, specialty) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'localhost';
GRANT SELECT (doctor_id) ON <DB_NAME>.gangnamunni_doctor_treatments TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (doctor_id) ON <DB_NAME>.gangnamunni_doctor_treatments TO 'sb_admin_rw'@'localhost';
GRANT SELECT (hospital_id) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (hospital_id) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_rw'@'localhost';
GRANT SELECT (id, hospital_id, image_type, image_url) ON <DB_NAME>.hospital_images TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, hospital_id, image_type, image_url) ON <DB_NAME>.hospital_images TO 'sb_admin_rw'@'localhost';
-- the only tables a delete removes rows from
GRANT DELETE ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'127.0.0.1';
GRANT DELETE ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'localhost';
GRANT DELETE ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'127.0.0.1';
GRANT DELETE ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'localhost';
GRANT DELETE ON <DB_NAME>.gangnamunni_doctor_treatments TO 'sb_admin_rw'@'127.0.0.1';
GRANT DELETE ON <DB_NAME>.gangnamunni_doctor_treatments TO 'sb_admin_rw'@'localhost';
GRANT DELETE ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_rw'@'127.0.0.1';
GRANT DELETE ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_rw'@'localhost';
GRANT DELETE ON <DB_NAME>.hospital_images TO 'sb_admin_rw'@'127.0.0.1';
GRANT DELETE ON <DB_NAME>.hospital_images TO 'sb_admin_rw'@'localhost';
GRANT DELETE ON <DB_NAME>.gangnamunni_hospital_revisions TO 'sb_admin_rw'@'127.0.0.1';
GRANT DELETE ON <DB_NAME>.gangnamunni_hospital_revisions TO 'sb_admin_rw'@'localhost';
-- archive table: add a record, acknowledge the notice
GRANT SELECT (id, notice_acked_at) ON <DB_NAME>.gangnamunni_hospital_deletions TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, notice_acked_at) ON <DB_NAME>.gangnamunni_hospital_deletions TO 'sb_admin_rw'@'localhost';
GRANT INSERT (hospital_id, hospital_name, snapshot, bookings_total, bookings_cancelled, sales_paid, sales_refunded, sales_net, deleted_by_admin_id,
  deleted_by_name, deleted_at) ON <DB_NAME>.gangnamunni_hospital_deletions TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (hospital_id, hospital_name, snapshot, bookings_total, bookings_cancelled, sales_paid, sales_refunded, sales_net, deleted_by_admin_id,
  deleted_by_name, deleted_at) ON <DB_NAME>.gangnamunni_hospital_deletions TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (notice_acked_at, notice_acked_by) ON <DB_NAME>.gangnamunni_hospital_deletions TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (notice_acked_at, notice_acked_by) ON <DB_NAME>.gangnamunni_hospital_deletions TO 'sb_admin_rw'@'localhost';

SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1';   -- check: DELETE only on the six tables above; no INSERT/UPDATE/DELETE on bookings, payments, settlements, consultations
```
A `GRANT` naming a column or table that does not exist fails as a whole: fix that statement and run it again. If a grant is missing,
the delete answers `503 {"error":"write_unavailable"}` and nothing is changed (the transaction is rolled back; reason in the journal).
If `DB_RW_USER` is unset the delete answers `503 write_unavailable` too.

### 7.2 Env and turning it on
```
ADMIN_HOSPITALS_ENABLED=1
ADMIN_HOSPITALS_WRITE=1
ADMIN_HOSPITALS_DELETE=1
```
Then `sudo systemctl restart seoulbeauty-admin-api`. Check (super admin PHPSESSID; use a test hospital):
```bash
C='-H Cookie:PHPSESSID=<value>'
H='-H Content-Type:application/json -H X-Requested-With:admin-next -H Cookie:PHPSESSID=<value>'
curl -s $C http://127.0.0.1:3300/v1/me                                      # "hospitalsDelete":true,"archive":true
curl -s $C http://127.0.0.1:3300/v1/hospitals/<id>/stats                    # {"week":{...},"month":{...}}
curl -s $C http://127.0.0.1:3300/v1/hospitals/<id>/delete-check             # canDelete, blockers[], deletableAfter, stats, willDelete
curl -s $C 'http://127.0.0.1:3300/v1/hospital-archive?perPage=10'           # {"items":[],"total":0,"dueCount":0}  (503 archive_unavailable = SQL not run)
curl -s -X POST $H -d '{"confirmName":"<exact name>","expectedUpdatedAt":"<hospital.updatedAt>"}' http://127.0.0.1:3300/v1/hospitals/<id>/delete
                                                                            # 200 {"archiveId":N}
curl -s $C http://127.0.0.1:3300/v1/hospital-archive/<archiveId>
curl -s -X POST $H -d '{}' http://127.0.0.1:3300/v1/hospital-archive/<archiveId>/ack   # {"ok":true}
```
Other answers of the delete: `400 {"error":"invalid","fields":{"confirmName":"mismatch"}}`, `409 {"error":"blocked","blockers":[...],"deletableAfter":...}`,
`409 {"error":"conflict","updatedAt":"..."}`, `403` (not a super admin / csrf), `404`, `503`. Each delete is logged:
`journalctl -u seoulbeauty-admin-api | grep 'hospital delete'` (`admin=<id> hospital=<id> archive=<id>`; no values).
Turning it off: `ADMIN_HOSPITALS_DELETE=0` + restart (routes 404, buttons and menu disappear; archive records stay in the table).

## 8. Hospital images (`ADMIN_HOSPITALS_IMAGES`, optional)
Adds `GET /v1/hospitals/:id/images` and `POST /v1/hospitals/:id/images` (multipart: `main` 0..1 file, `interior` 0..10 files). The files are written
into the PHP `gangnamunni` directory, so the PHP admin and the app keep reading them from `uploads/hospitals/<id>/<name>` as before.
Needs sections 1 and 6 (hospital pages, write user). With the switch off the POST answers 404 and nothing below is used.

**Before the first upload, dump the database** (an upload inserts and updates rows of `hospital_images` and updates `gangnamunni_hospitals.main_image_url`):
```bash
mysqldump --single-transaction <DB_NAME> hospital_images gangnamunni_hospitals > ~/backup-hospital-images-$(date +%F).sql   # keep it outside the web root
```

### 8.1 Database (run as the DB admin; `<DB_NAME>` = schema from step 1)
Only what sections 1, 6 and 7 did not already grant. The read-only user already has `SELECT` on `hospital_images` (step 1); the write user gets
the columns the upload reads, inserts and updates:
```sql
GRANT SELECT (id, hospital_id, image_url, image_type, sort_order, is_active) ON <DB_NAME>.hospital_images TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, hospital_id, image_url, image_type, sort_order, is_active) ON <DB_NAME>.hospital_images TO 'sb_admin_rw'@'localhost';
GRANT INSERT (hospital_id, image_url, image_type, sort_order, is_active, created_at) ON <DB_NAME>.hospital_images TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (hospital_id, image_url, image_type, sort_order, is_active, created_at) ON <DB_NAME>.hospital_images TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (image_url, sort_order, is_active) ON <DB_NAME>.hospital_images TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (image_url, sort_order, is_active) ON <DB_NAME>.hospital_images TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (main_image_url) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (main_image_url) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'localhost';
SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1';   -- check: hospital_images has SELECT / INSERT / UPDATE of the columns above plus DELETE (section 7), nothing more
```
`gangnamunni_hospitals` needs nothing else: `SELECT (id, updated_at)` and `UPDATE (... updated_at)` come from section 6.1. A `GRANT` naming a column that
does not exist fails as a whole: remove that column and run it again. A missing grant answers `503 {"error":"write_unavailable"}` and nothing is changed.

### 8.2 Upload folder (on the server)
The service user of this API (`User=` of `seoulbeauty-admin-api.service`, `sbapi` in the unit) must be able to create folders and files under
`<gangnamunni>/uploads/hospitals/`, while PHP (Apache) must still read what it writes. The API creates `uploads/hospitals/<id>/` with mode 0755 and
files with mode 0644, so Apache (a different user) can read them. Choose ONE of the two ways and adapt the names to the server:
```bash
G=/path/to/gangnamunni            # the PHP directory = ADMIN_UPLOAD_ROOT
ls -ld $G $G/uploads $G/uploads/hospitals; ps -o user= -C httpd | sort -u     # who owns what; which user Apache/PHP runs as
# a) ACL (keeps the current owner, so the PHP admin uploads keep working): write access for the service user, also as the default for new entries
sudo mkdir -p $G/uploads/hospitals
sudo setfacl -R -m u:sbapi:rwX $G/uploads/hospitals
sudo setfacl -R -d -m u:sbapi:rwX $G/uploads/hospitals
sudo setfacl -m u:sbapi:--x $G $G/uploads                        # traverse only (no listing, no writing there)
# b) or a shared group (no ACL tools): the service user joins Apache's group; the folder is group-writable and new entries inherit the group
#    sudo usermod -aG <apache-group> sbapi
#    sudo chgrp -R <apache-group> $G/uploads/hospitals ; sudo chmod -R g+rwX $G/uploads/hospitals ; sudo chmod g+s $G/uploads/hospitals
#    and in the systemd drop-in below:  SupplementaryGroups=<apache-group>
```
Files created by the service stay readable for Apache (0644) and folders traversable (0755); files the PHP admin wrote earlier are untouched.
SELinux (Rocky): keep the type Apache may read on the folder (`ls -Z $G/uploads/hospitals`); if the API is denied, look at `sudo ausearch -m avc -ts recent`.
Folders that PHP created earlier (owned by Apache, mode 0755) can only be written to through the ACL/group above; otherwise the upload for that hospital
answers `503 uploads_unavailable`.

**systemd (required with the shipped unit):** the unit uses `ProtectSystem=strict`, so the service cannot write anywhere except what is listed. Add the
upload folder in a drop-in (do not edit the shipped file; the path is server-specific):
```bash
sudo systemctl edit seoulbeauty-admin-api      # in the editor that opens, add the lines below and save
#   [Service]
#   ReadWritePaths=/path/to/gangnamunni/uploads/hospitals
#   SupplementaryGroups=<apache-group>          # only for way b)
sudo systemctl daemon-reload && sudo systemctl restart seoulbeauty-admin-api
```
(`PrivateTmp=true` stays: the temporary files of a request live in the service's private /tmp and are removed after every request.) Test that the service user can
write (replace names; run after the ACL/group step): `sudo -u sbapi sh -c 'mkdir -p $G/uploads/hospitals/_probe && touch $G/uploads/hospitals/_probe/x && rm $G/uploads/hospitals/_probe/x && rmdir $G/uploads/hospitals/_probe'`

### 8.3 Apache / proxy limits
A request carries up to 11 files (5 MB main + 10 x 10 MB interior, about 105 MB). The admin web app forwards the upload to this API, so every hop must allow it:
- Apache vhost of `seoulbeauty.app`, for `/admin-next/`: `LimitRequestBody 115343360` (about 110 MB; the default 0 = unlimited is also fine) and no stricter `LimitRequestBody`
  in a parent `<Directory>` / `<Location>`; mod_security / WAF body limits (if any) at least the same.
- Proxy time: `ProxyTimeout 300` (or `timeout=300` on the `ProxyPass` of `/admin-next/`), so a slow connection can finish a large upload.
  Cloudflare in front limits the request body by plan (100 MB on Free / Pro); an upload near the maximum may need to be split into two requests.
- The admin web app (Next) and its `/admin-next/api` rewrite must not cap the body below that either (see the admin web deploy notes).
- This API itself stays on `127.0.0.1:3300`; nothing is exposed publicly.

### 8.4 Env and turning it on
```
ADMIN_HOSPITALS_ENABLED=1
ADMIN_HOSPITALS_WRITE=1
ADMIN_HOSPITALS_IMAGES=1
ADMIN_UPLOAD_ROOT=/path/to/gangnamunni
```
Then `sudo systemctl restart seoulbeauty-admin-api`. Check with a TEST hospital (super admin PHPSESSID; any small JPEG; replace the placeholders):
```bash
C='-H Cookie:PHPSESSID=<value>'
H='-H X-Requested-With:admin-next -H Cookie:PHPSESSID=<value>'      # curl sets the multipart Content-Type itself
curl -s $C http://127.0.0.1:3300/v1/me                                # "hospitalsImages":true
curl -s $C http://127.0.0.1:3300/v1/hospitals/<id>/images             # {"items":[...],"mainUrl":...,"detailCount":N,"detailLimit":10}
curl -s -X POST $H -F 'main=@/tmp/test-main.jpg' -F 'interior=@/tmp/test-1.jpg' -F 'interior=@/tmp/test-2.png' http://127.0.0.1:3300/v1/hospitals/<id>/images
                                                                      # 200 {"items":[...],"mainUrl":"uploads/hospitals/<id>/main_<32 hex>.jpg","detailCount":N,"detailLimit":10,"added":3,"updatedAt":"..."}
ls -l $G/uploads/hospitals/<id>/                                      # new files mode 0644, folder 0755; open one through the PHP site: <gangnamunni URL>/uploads/hospitals/<id>/<file>
```
Answers: `400 {"error":"no_files"|"invalid_type"|"too_large"|"too_many"|"upload_failed","field":"main"|"interior","remaining":N}`, `403` (csrf: the `X-Requested-With` header and a
multipart body are required), `404`, `503 {"error":"uploads_unavailable"}` (`ADMIN_UPLOAD_ROOT` empty / relative / missing or not writable; the reason is only in the journal) or
`503 {"error":"write_unavailable"}` (write user or grants). Each upload is logged: `journalctl -u seoulbeauty-admin-api | grep 'hospital images'` (`hospital=<id> added=<n>`; no file names).
The replaced main image file stays on disk (only the database points to the new one). Turning it off: `ADMIN_HOSPITALS_IMAGES=0` + restart.

## 9. Adding doctors (`ADMIN_HOSPITALS_DOCTORS`, optional)
Adds `GET /v1/hospitals/:id/doctors` and `POST /v1/hospitals/:id/doctors` (multipart: `doctors` = JSON text of up to 20 rows, `photo` = 0..20 files referred to by
position). One request inserts the rows into `gangnamunni_doctors` in one transaction and writes the photos into the PHP `gangnamunni` directory, so the PHP admin
and the app keep reading them from `uploads/hospitals/<hospital id>/doctor_<doctor id>_<32 hex>.<ext>` as before. Editing and deleting doctors stay in the PHP admin.
Needs sections 1 and 6 (hospital pages, write user). With the switch off the POST answers 404 and nothing below is used.

**Before the first request, dump the database** (a request inserts rows of `gangnamunni_doctors` and updates their `photo_url`):
```bash
mysqldump --single-transaction <DB_NAME> gangnamunni_doctors > ~/backup-hospital-doctors-$(date +%F).sql   # keep it outside the web root
```

### 9.1 Database (run as the DB admin; `<DB_NAME>` = schema from step 1)
Only what the earlier sections did not already grant. Already there: the read-only user has `SELECT (id, hospital_id, name, specialty, experience, qualification,
bio, photo_url, status)` on `gangnamunni_doctors` (step 1, hospital pages), and the write user has `SELECT (id, hospital_id, name, specialty)` plus `DELETE` (section 7)
and `SELECT (id, updated_at)` on `gangnamunni_hospitals` (section 6.1, used to lock the hospital row). The write user still needs the rest of the columns it reads back,
inserts and updates:
```sql
GRANT SELECT (experience, qualification, bio, photo_url, status) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (experience, qualification, bio, photo_url, status) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'localhost';
GRANT INSERT (hospital_id, name, specialty, experience, qualification, bio, photo_url, status, created_at, updated_at) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (hospital_id, name, specialty, experience, qualification, bio, photo_url, status, created_at, updated_at) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (photo_url) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (photo_url) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'localhost';
SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1';   -- check: gangnamunni_doctors has SELECT / INSERT / UPDATE (photo_url) of the columns above plus DELETE (section 7), nothing more
```
A `GRANT` naming a column that does not exist fails as a whole: remove that column and run it again. A missing grant answers `503 {"error":"write_unavailable"}`
and nothing is changed. The hospital row (`gangnamunni_hospitals`) is not modified by adding doctors (the PHP form does not touch it either).

### 9.2 Upload folder
Nothing new: photos use the same folder as the hospital images, `ADMIN_UPLOAD_ROOT` and `<gangnamunni>/uploads/hospitals/<id>/` (ACL or shared group,
systemd `ReadWritePaths=<gangnamunni>/uploads/hospitals`, Apache/proxy limits): everything in section 8.2 and 8.3 applies unchanged. A request carries at most
20 photos of 5 MB (about 100 MB), within the limits set in 8.3. Rows without a photo need no folder at all; a photo with the folder missing or not writable answers
`503 {"error":"uploads_unavailable"}` and the inserted rows are rolled back.

### 9.3 Env and turning it on
```
ADMIN_HOSPITALS_ENABLED=1
ADMIN_HOSPITALS_WRITE=1
ADMIN_HOSPITALS_DOCTORS=1
ADMIN_UPLOAD_ROOT=/path/to/gangnamunni
```
Then `sudo systemctl restart seoulbeauty-admin-api`. Check with a TEST hospital (super admin PHPSESSID; any small JPEG; replace the placeholders):
```bash
C='-H Cookie:PHPSESSID=<value>'
H='-H X-Requested-With:admin-next -H Cookie:PHPSESSID=<value>'      # curl sets the multipart Content-Type itself
curl -s $C http://127.0.0.1:3300/v1/me                                # "hospitalsDoctors":true
curl -s $C http://127.0.0.1:3300/v1/hospitals/<id>/doctors            # {"items":[...],"count":N,"addLimit":20}
curl -s -X POST $H -F 'doctors=[{"name":"Test Doctor","specialty":"Dermatology","experience":10,"qualification":"x","bio":"y","photo":0},{"name":"Second"}]' \
  -F 'photo=@/tmp/test-doctor.jpg' http://127.0.0.1:3300/v1/hospitals/<id>/doctors
                                                                      # 200 {"items":[...],"count":N,"addLimit":20,"added":2}; the first item has "photoUrl":"uploads/hospitals/<id>/doctor_<doctor id>_<32 hex>.jpg"
ls -l $G/uploads/hospitals/<id>/                                      # the photo: mode 0644; open it through the PHP site: <gangnamunni URL>/uploads/hospitals/<id>/<file>
```
`photo` is the position (0, 1, ...) of the file in the list of `-F 'photo=@...'` parts, or null/absent for a doctor without a photo. Rows without a name are skipped
(with their photo); at least one named row is required.
Answers: `400 {"error":"no_doctors"|"too_many"|"invalid_field"|"invalid_type"|"too_large"|"upload_failed","index":<row>,"field":"name"|"specialty"|"experience"|"qualification"|"bio"|"photo"}`
(`index` and `field` only when they apply), `403` (csrf: the `X-Requested-With` header and a multipart body are required), `404`,
`503 {"error":"uploads_unavailable"}` or `503 {"error":"write_unavailable"}` (write user or grants). Each request is logged:
`journalctl -u seoulbeauty-admin-api | grep 'hospital doctors'` (`hospital=<id> added=<n>`; no names or file names). A failed request leaves no rows and no photo files.
Turning it off: `ADMIN_HOSPITALS_DOCTORS=0` + restart (the POST answers 404; the GET stays on with the hospital pages).

## 10. Treatments of a hospital (`ADMIN_HOSPITALS_TREATMENTS`, `ADMIN_HOSPITALS_TREATMENTS_REMOVE`, optional)
Adds `GET /v1/hospitals/:id/treatments` (assigned treatments with price options, image and question counts, and the treatments still available),
`POST /v1/hospitals/:id/treatments` (assign up to 300 master treatments), `PATCH /v1/hospitals/:id/treatments/:htId` (promotion star and the minimum age)
and, behind a second switch, `DELETE /v1/hospitals/:id/treatments/:htId` (release an assignment). Price options are edited through `PUT .../prices` behind a third switch (10.4); the "before treatment"
questions are edited through `PUT .../questions` behind a fourth switch (10.5); detail images are listed, added, deleted, reordered and reviewed through a fifth switch (10.6). Needs sections 1 and 6 (hospital pages, write user). With a switch off its routes answer 404
and nothing below is used; `/v1/me` reports `hospitalsTreatments`, `hospitalsTreatmentsRemove`, `hospitalsTreatmentsPrices`, `hospitalsTreatmentsQuestions` and `hospitalsTreatmentsImages`.

Who may change things (same rule as the PHP page): a super admin always; a hospital account (own hospital only) only while the setting `treatment_mgmt_mode` in the
PHP table `settings` is `hospital_only` or `both` (missing row = `both`). Otherwise `403 {"error":"forbidden_treatments"}` and the page hides the buttons. If the setting
cannot be read (no grant, no table) a hospital account is treated as not allowed and the reason is logged as a warning; a super admin is not affected.

What each write does (one transaction of the write pool, parameters only; any error rolls everything back):
- assign: a treatment the hospital never had is inserted (`status='active'`, `is_active=1`, price 0, promotion off, `registered_by` = `hospital` for a hospital account, else `admin`);
  one that was released earlier (`is_active=0`, or status `inactive`/`rejected`) is switched back on with promotion and prices reset; one already assigned is skipped.
  Old price option and image rows of a re-assigned treatment are deleted only when `ADMIN_HOSPITALS_TREATMENTS_REMOVE=1` (that is the switch that holds the DELETE rights).
  The hospital row is locked, not changed.
- promotion / minimum age: `UPDATE gangnamunni_hospital_treatments SET is_promoted` / `SET min_age` (null or 0 = no limit, 1..120), always `WHERE id=? AND hospital_id=?`.
  Without the `min_age` column (or its grant) the list answers `"minAgeSupported":false` and setting an age answers `503 {"error":"min_age_unavailable"}`; promotion keeps working.
- remove: deletes the assignment's rows in `gangnamunni_hospital_treatment_prices` and `gangnamunni_hospital_treatment_images`, then sets `is_active=0`, promotion off, prices 0
  on the assignment row (the row stays, like the PHP page). Image FILES are not deleted from disk.
Listing never writes anything (the PHP page also turns every `pending` assignment into `active` when it opens; this API does not).

**Before the first request take a dump** (writes touch these tables; the `settings` table is only read):
```bash
mysqldump --single-transaction <DB_NAME> gangnamunni_hospital_treatments gangnamunni_hospital_treatment_prices gangnamunni_hospital_treatment_images > ~/backup-hospital-treatments-$(date +%F).sql   # outside the web root
```

### 10.1 Database (run as the DB admin; `<DB_NAME>` = schema from step 1)
Only what the earlier sections did not already grant. Already there: the read-only user has `SELECT (status, is_active)` and `SELECT (hospital_id, treatment_id, status, is_active)`
on `gangnamunni_hospital_treatments`, `SELECT (id, name, category_id)` on `gangnamunni_treatment_master` and `SELECT (id, name, sort_order)` on `gangnamunni_treatment_categories`
(step 1); the write user has `SELECT (hospital_id)` plus `DELETE` on `gangnamunni_hospital_treatments` (section 7) and `SELECT (id, updated_at)` on `gangnamunni_hospitals` (section 6.1).
The `min_age` column comes from the PHP migration `hairwang_full/gangnamunni/sql/hospital_treatment_min_age_20261021.sql` (not run by this API). If the column does not exist yet, remove
`min_age` from the four statements that name it (the `sb_admin_ro` SELECT and the `sb_admin_rw` UPDATE, each for `127.0.0.1` and `localhost`; a `GRANT` naming a missing column fails as a whole) and add it later.
```sql
-- read-only user
GRANT SELECT (id, is_promoted, note, registered_by, min_age) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, is_promoted, note, registered_by, min_age) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_ro'@'localhost';
GRANT SELECT (hashtag, is_active, sort_order) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (hashtag, is_active, sort_order) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, hospital_treatment_id, option_name, body_part, dosage_value, dosage_unit, regular_price, price, is_representative, sort_order) ON <DB_NAME>.gangnamunni_hospital_treatment_prices TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, hospital_treatment_id, option_name, body_part, dosage_value, dosage_unit, regular_price, price, is_representative, sort_order) ON <DB_NAME>.gangnamunni_hospital_treatment_prices TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, hospital_treatment_id, approval_status) ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, hospital_treatment_id, approval_status) ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, hospital_treatment_id) ON <DB_NAME>.gangnamunni_treatment_questions TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, hospital_treatment_id) ON <DB_NAME>.gangnamunni_treatment_questions TO 'sb_admin_ro'@'localhost';
GRANT SELECT (`key`, `value`) ON <DB_NAME>.settings TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (`key`, `value`) ON <DB_NAME>.settings TO 'sb_admin_ro'@'localhost';
-- write user
GRANT SELECT (id, treatment_id, status, is_active) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, treatment_id, status, is_active) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_rw'@'localhost';
GRANT INSERT (hospital_id, treatment_id, price_min, price_max, is_promoted, note, status, registered_by, is_active, created_at, updated_at) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (hospital_id, treatment_id, price_min, price_max, is_promoted, note, status, registered_by, is_active, created_at, updated_at) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (status, is_active, reject_reason, is_promoted, price_min, price_max, dosage_value, dosage_unit, min_age, updated_at) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (status, is_active, reject_reason, is_promoted, price_min, price_max, dosage_value, dosage_unit, min_age, updated_at) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_rw'@'localhost';
GRANT SELECT (id, is_active) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, is_active) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_rw'@'localhost';
SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1';   -- check: gangnamunni_hospital_treatments has the columns above (+ hospital_id SELECT and DELETE from section 7), no more
```
`settings` cannot be limited to one row: the read-only user can read every row of that table (`key`, `value`), not only `treatment_mgmt_mode`. The PHP code keeps at least
`treatment_mgmt_mode` and `treatment_max_per_hospital` there; check the keys in that table first (SELECT key column) and, if it holds anything secret, do not grant it: the API then
treats hospital accounts as not allowed (fail closed) and super admins keep working. The read-only user is used only by this API and is never exposed to a browser.
A missing price / image / question table or grant makes only that part of the list empty (price options `[]`, counts `0`); a missing grant on the main tables answers `500`
(listing) or `503 {"error":"write_unavailable"}` (writes) and the reason is in the journal.

### 10.2 Release (remove) rights - only for `ADMIN_HOSPITALS_TREATMENTS_REMOVE=1`
Removing an assignment (and re-assigning an old one with leftovers) deletes rows of the two child tables:
```sql
GRANT SELECT (id, hospital_treatment_id) ON <DB_NAME>.gangnamunni_hospital_treatment_prices TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, hospital_treatment_id) ON <DB_NAME>.gangnamunni_hospital_treatment_prices TO 'sb_admin_rw'@'localhost';
GRANT DELETE ON <DB_NAME>.gangnamunni_hospital_treatment_prices TO 'sb_admin_rw'@'127.0.0.1';
GRANT DELETE ON <DB_NAME>.gangnamunni_hospital_treatment_prices TO 'sb_admin_rw'@'localhost';
GRANT SELECT (id, hospital_treatment_id) ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, hospital_treatment_id) ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_rw'@'localhost';
GRANT DELETE ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_rw'@'127.0.0.1';
GRANT DELETE ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_rw'@'localhost';
```
Without them a release answers `503 {"error":"write_unavailable"}` and nothing changes. Leave `ADMIN_HOSPITALS_TREATMENTS_REMOVE=0` until they are in.

### 10.3 Env and turning it on
```
ADMIN_HOSPITALS_ENABLED=1
ADMIN_HOSPITALS_WRITE=1
ADMIN_HOSPITALS_TREATMENTS=1
ADMIN_HOSPITALS_TREATMENTS_REMOVE=0        # 1 only after 10.2
```
Then `sudo systemctl restart seoulbeauty-admin-api`. Check with a TEST hospital (super admin PHPSESSID; `<ht>` = an `items[].id` of the list, `<tid>` = an `available[].id`):
```bash
C='-H Cookie:PHPSESSID=<value>'
H='-H Content-Type:application/json -H X-Requested-With:admin-next -H Cookie:PHPSESSID=<value>'
curl -s $C http://127.0.0.1:3300/v1/me                                       # "hospitalsTreatments":true,"hospitalsTreatmentsRemove":false
curl -s $C http://127.0.0.1:3300/v1/hospitals/<id>/treatments                # {"items":[...],"count":N,"promotedCount":M,"canManage":true,"minAgeSupported":true,"removeEnabled":false,"available":[...]}
curl -s -X POST $H -d '{"treatmentIds":[<tid>]}' http://127.0.0.1:3300/v1/hospitals/<id>/treatments        # 200 {"added":1,"skipped":0,"items":[...],"count":..,"promotedCount":..,"available":[...]}
curl -s -X PATCH $H -d '{"isPromoted":true,"minAge":19}' http://127.0.0.1:3300/v1/hospitals/<id>/treatments/<ht>   # 200 {"item":{...},"promotedCount":N}
curl -s -X DELETE $H http://127.0.0.1:3300/v1/hospitals/<id>/treatments/<ht>                                # only with REMOVE=1 (else 404): {"removed":true,"deleted":{"priceOptions":n,"images":n},...}
```
A DELETE needs the same `Content-Type: application/json` and `X-Requested-With: admin-next` headers as the other writes (even with an empty body), else `403 {"error":"csrf"}`.
Answers: `400 {"error":"no_treatments"|"invalid_treatment"|"invalid_field","field":"isPromoted"|"minAge"}`, `403` (csrf, `forbidden`, `forbidden_treatments`), `404`,
`503 {"error":"write_unavailable"|"min_age_unavailable"}`. Each write is logged: `journalctl -u seoulbeauty-admin-api | grep 'hospital treatments'`
(`admin=<id> hospital=<id> assign|update|remove ...`; no names). Turning it off: `ADMIN_HOSPITALS_TREATMENTS=0` (all routes 404) or only `ADMIN_HOSPITALS_TREATMENTS_REMOVE=0`, then restart.

### 10.4 Price options (`ADMIN_HOSPITALS_TREATMENTS_PRICES`, optional; run as the DB admin; `<DB_NAME>` = schema from step 1)
Adds `PUT /v1/hospitals/:id/treatments/:htId/prices` (the "가격 수정" form). Body `{"options":[{"optionName","bodyParts":[...],"dosageValue","dosageUnit","regularPrice","price","isRepresentative"}]}`
replaces ALL price options of that assignment in one transaction: `DELETE` of its rows in `gangnamunni_hospital_treatment_prices`, one `INSERT` per option (`sort_order` 0..), then
`UPDATE gangnamunni_hospital_treatments SET price_min=0, price_max=0, dosage_value=NULL, dosage_unit=NULL, updated_at=NOW()` for that assignment. Any error rolls everything back and the old options stay.
Same people may use it as for the other writes (`canManage`; section 10). Rules (same as the PHP page): completely empty rows are skipped; a filled row needs a dosage > 0 (at most two decimals) and a unit (<= 20 characters);
option name <= 100 characters; body parts joined with `||` (<= 500 characters, no `||` inside one part); prices are whole won 0..2000000000 and optional; the list price may not be below the sale price when both are above 0;
one representative at most; no two options with the same name, body parts, dosage and unit (case-insensitive); 1..50 options. This API never runs `ALTER TABLE`: the columns `option_name`, `body_part`, `regular_price`,
`is_representative` must already exist (the PHP admin adds them the first time its page saves a price). Without them the answer is `503 {"error":"price_columns_unavailable"}`.
`GET /v1/hospitals/:id/treatments` then also answers `pricesEnabled` and `priceUnits` (the unit list for the form: table `gangnamunni_treatment_units`, `is_active=1`, Latin first, then Korean; table missing or unreadable = the standard list `Box, cc, IU, kj, ml, 기타, 라인, 병, 샷, 시린지, 회`).

Already granted by 10.1: the read-only user's `SELECT` on the price columns, the write user's `UPDATE (price_min, price_max, dosage_value, dosage_unit, updated_at)` on `gangnamunni_hospital_treatments`.
Already granted by 10.2: the write user's `SELECT (id, hospital_treatment_id)` and `DELETE` on `gangnamunni_hospital_treatment_prices` (10.2 is now needed for this switch as well; its `images` lines are not).
Still missing - run these (a `GRANT` naming a missing column fails as a whole; check the columns with `SHOW COLUMNS FROM gangnamunni_hospital_treatment_prices` first):
```sql
GRANT INSERT (hospital_treatment_id, option_name, body_part, dosage_value, dosage_unit, regular_price, price, is_representative, sort_order) ON <DB_NAME>.gangnamunni_hospital_treatment_prices TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (hospital_treatment_id, option_name, body_part, dosage_value, dosage_unit, regular_price, price, is_representative, sort_order) ON <DB_NAME>.gangnamunni_hospital_treatment_prices TO 'sb_admin_rw'@'localhost';
GRANT SELECT (id, name, is_active) ON <DB_NAME>.gangnamunni_treatment_units TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, name, is_active) ON <DB_NAME>.gangnamunni_treatment_units TO 'sb_admin_ro'@'localhost';
-- body part groups of the price form (tables: hairwang_full/gangnamunni/sql/body_parts_20261125.sql); without them the form uses its built-in list
GRANT SELECT (id, name, sort_order, is_active) ON <DB_NAME>.gangnamunni_body_part_groups TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, name, sort_order, is_active) ON <DB_NAME>.gangnamunni_body_part_groups TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, group_id, name, sort_order, is_active) ON <DB_NAME>.gangnamunni_body_parts TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, group_id, name, sort_order, is_active) ON <DB_NAME>.gangnamunni_body_parts TO 'sb_admin_ro'@'localhost';
SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1';   -- check: ..._treatment_prices has SELECT (id, hospital_treatment_id), INSERT (9 columns above) and DELETE
```
The dump from section 10 already covers `gangnamunni_hospital_treatment_prices`. Without the INSERT / DELETE rights the answer is `503 {"error":"write_unavailable"}` and nothing changes.
Env (needs 10.1 and 10.2 first; leave `0` until then), then `sudo systemctl restart seoulbeauty-admin-api`:
```
ADMIN_HOSPITALS_TREATMENTS=1
ADMIN_HOSPITALS_TREATMENTS_PRICES=1
```
`/v1/me` then reports `"hospitalsTreatmentsPrices":true`. Check with a TEST hospital (headers as in 10.3; `<ht>` = an `items[].id` of the list):
```bash
curl -s -X PUT $H -d '{"options":[{"optionName":"","bodyParts":["이마","미간"],"dosageValue":50,"dosageUnit":"샷","regularPrice":300000,"price":250000,"isRepresentative":true}]}' http://127.0.0.1:3300/v1/hospitals/<id>/treatments/<ht>/prices
# 200 {"item":{... "priceOptions":[...]},"count":N,"promotedCount":M}
```
Answers: `400 {"error":"no_options"|"too_many"|"invalid_field"|"price_order"|"multiple_representative"|"duplicate_option","index":<row>,"field":"optionName"|"bodyParts"|"dosageValue"|"dosageUnit"|"regularPrice"|"price"}`
(`index` / `field` only where they apply), `403` (csrf, `forbidden`, `forbidden_treatments`), `404` (switch off, hospital out of scope, assignment not of this hospital or released),
`503 {"error":"price_columns_unavailable"|"write_unavailable"}`. Logged as `hospital treatments: admin=<id> hospital=<id> prices treatment=<ht> options=<n>` (no names, no amounts).
Turning it off: `ADMIN_HOSPITALS_TREATMENTS_PRICES=0`, restart (the price route answers 404; the list keeps showing the options read-only).

### 10.5 "Before treatment" questions (`ADMIN_HOSPITALS_TREATMENTS_QUESTIONS`, optional; run as the DB admin; `<DB_NAME>` = schema from step 1)
Adds `PUT /v1/hospitals/:id/treatments/:htId/questions` (the "시술 전 확인 질문" form: yes / no questions shown to the customer before booking, all optional). Body `{"questions":["...", "..."]}`
replaces ALL questions of that assignment in one transaction: the assignment row is locked (`SELECT ... FOR UPDATE`, not changed), `DELETE` of its rows in `gangnamunni_treatment_questions`,
then one `INSERT` per question (`hospital_treatment_id, question, sort_order` 0..). Any error rolls everything back and the old questions stay. This API never runs `ALTER TABLE` / `CREATE TABLE`:
the table comes from the PHP migration `hairwang_full/gangnamunni/sql/treatment_questions_20261023.sql` (not run by this API); without it the answer is `503 {"error":"questions_table_unavailable"}`.
Same people may use it as for the other writes (`canManage`; section 10). Rules: each question is trimmed and runs of white space become one space; empty ones are skipped and exact repeats dropped;
a question over 200 characters (counted as code points) is rejected with `too_long`, and more than 10 distinct questions with `too_many`. The PHP page cuts both silently; this API rejects them so nothing is
lost without notice (the form enforces both limits itself). An empty list is allowed and removes every question. The assignment only has to belong to that hospital (a released assignment is not checked, like the PHP page), but the answer is the listed item, so a released assignment answers `404` after saving.
`GET /v1/hospitals/:id/treatments` then also answers `questionsEnabled` and, when the actor may manage, `questions` (the texts in order) on every item; `questionCount` stays as before.
If the question texts cannot be read (missing grant on `question` / `sort_order`) the list answers `questionsEnabled:false` and no texts (the form is hidden instead of showing an empty list that a save would wipe).

Already granted by 10.1: the read-only user's `SELECT (id, hospital_treatment_id)` on `gangnamunni_treatment_questions` (question counts); the write user's access to `gangnamunni_hospital_treatments` (`SELECT (id, ...)`, `hospital_id`).
Already granted by section 10.2: nothing here (its tables are the price and image tables). Still missing - run these (check the columns with `SHOW COLUMNS FROM gangnamunni_treatment_questions` first; a `GRANT` naming a missing column fails as a whole):
```sql
GRANT SELECT (question, sort_order) ON <DB_NAME>.gangnamunni_treatment_questions TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (question, sort_order) ON <DB_NAME>.gangnamunni_treatment_questions TO 'sb_admin_ro'@'localhost';
GRANT SELECT (hospital_treatment_id) ON <DB_NAME>.gangnamunni_treatment_questions TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (hospital_treatment_id) ON <DB_NAME>.gangnamunni_treatment_questions TO 'sb_admin_rw'@'localhost';
GRANT INSERT (hospital_treatment_id, question, sort_order) ON <DB_NAME>.gangnamunni_treatment_questions TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (hospital_treatment_id, question, sort_order) ON <DB_NAME>.gangnamunni_treatment_questions TO 'sb_admin_rw'@'localhost';
GRANT DELETE ON <DB_NAME>.gangnamunni_treatment_questions TO 'sb_admin_rw'@'127.0.0.1';
GRANT DELETE ON <DB_NAME>.gangnamunni_treatment_questions TO 'sb_admin_rw'@'localhost';
SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1';   -- check: ..._treatment_questions has SELECT (hospital_treatment_id), INSERT (3 columns above) and DELETE
SHOW GRANTS FOR 'sb_admin_ro'@'127.0.0.1';   -- check: ..._treatment_questions has SELECT (id, hospital_treatment_id, question, sort_order)
```
Before the first save take a dump of the table (outside the web root): `mysqldump --single-transaction <DB_NAME> gangnamunni_treatment_questions > ~/backup-treatment-questions-$(date +%F).sql`.
Without the INSERT / DELETE rights the answer is `503 {"error":"write_unavailable"}` and nothing changes.
Env (needs sections 6 and 10, and 10.5 grants first; leave `0` until then), then `sudo systemctl restart seoulbeauty-admin-api`:
```
ADMIN_HOSPITALS_TREATMENTS=1
ADMIN_HOSPITALS_TREATMENTS_QUESTIONS=1
```
`/v1/me` then reports `"hospitalsTreatmentsQuestions":true`. Check with a TEST hospital (headers as in 10.3; `<ht>` = an `items[].id` of the list):
```bash
curl -s $C http://127.0.0.1:3300/v1/hospitals/<id>/treatments            # "questionsEnabled":true and "questions":[...] on every item
curl -s -X PUT $H -d '{"questions":["임신 중이거나 수유 중이에요?","알레르기가 있어요?"]}' http://127.0.0.1:3300/v1/hospitals/<id>/treatments/<ht>/questions
# 200 {"item":{... "questionCount":2,"questions":["...","..."]},"count":2}
curl -s -X PUT $H -d '{"questions":[]}' http://127.0.0.1:3300/v1/hospitals/<id>/treatments/<ht>/questions        # removes all: "count":0
```
Answers: `400 {"error":"invalid_body"}` (no `questions` list), `400 {"error":"invalid_field","index":<n>}` (not a string), `400 {"error":"too_long","index":<n>}`, `400 {"error":"too_many"}`,
`403` (csrf, `forbidden`, `forbidden_treatments`), `404` (switch off, hospital out of scope, assignment not of this hospital), `503 {"error":"questions_table_unavailable"|"write_unavailable"}`.
Logged as `hospital treatments: admin=<id> hospital=<id> questions treatment=<ht> count=<n>` (no question text).
Turning it off: `ADMIN_HOSPITALS_TREATMENTS_QUESTIONS=0`, restart (the route answers 404; the list keeps showing counts only). Removing the rights if needed:
`REVOKE INSERT, DELETE ON <DB_NAME>.gangnamunni_treatment_questions FROM 'sb_admin_rw'@'127.0.0.1';` (and `'localhost'`).

### 10.6 Detail images of an assignment (`ADMIN_HOSPITALS_TREATMENTS_IMAGES`, optional; run as the DB admin; `<DB_NAME>` = schema from step 1)
Adds the "시술 상세페이지 이미지" gallery of one assignment (table `gangnamunni_hospital_treatment_images`; the pictures are shown only on that hospital's treatment page):
- `GET  /v1/hospitals/:id/treatments/:htId/images` -> `{"items":[{"id","url","caption","status","uploadedBy","rejectReason","reviewedAt","reviewedBy","sortOrder"}],"canReview":true|false,"defaultImageUrl":"..."|null}`; every status is listed
  (a hospital account must see its rejected pictures); `url` is returned as stored (relative `uploads/treatments/<file>` or an absolute `https://` address). Nothing is written. A released assignment is still readable.
  `defaultImageUrl` = `gangnamunni_treatment_master.default_image_url` of the assignment's treatment (active master row, not blank), else `null`; a missing column or grant also gives `null` (never an error).
- `POST /v1/hospitals/:id/treatments/:htId/images` (multipart) with `image` (one file, JPG / PNG / GIF / WebP, at most 10 MB) OR `imageUrl` (http / https, at most 2048 bytes) and an optional `caption`
  (at most 200 characters; the PHP page does not cut it, the column is `VARCHAR(200)`, so this API rejects it). A file wins over the address. A hospital account's picture is stored `pending` / `hospital` and waits
  for a super admin; a super admin's is `approved` / `admin` at once (decided by the server, never by the request). `sort_order` = highest + 1 (the first picture gets 0). Answer `200 {"item":{...},"items":[...]}`.
  The extension must agree with the real type (JPEG `FF D8 FF`, PNG, GIF `GIF87a`/`GIF89a`, WebP `RIFF....WEBP`); the stored extension always comes from the real type. The file is stored as
  `<ADMIN_UPLOAD_ROOT>/uploads/treatments/treat_<unix time>_<8 hex>.<ext>` (folder 0755, file 0644) and `image_url` holds `uploads/treatments/<file>` (the same folder and value as the PHP admin).
- `DELETE /v1/hospitals/:id/treatments/:htId/images/:imageId` -> `{"items":[...]}`. The row is deleted first; afterwards the file is removed only when `image_url` starts with `uploads/treatments/`, has no `..` and stays inside
  the upload root. A file that cannot be removed is only logged (warning); the answer is still 200. Pictures added by address have no file.
- `POST .../images/:imageId/move` `{"direction":"up"|"down"}` -> `{"items":[...]}`: only an `approved` picture can be moved (otherwise `409 {"error":"not_approved"}`), and only among the approved ones
  (the order the patient sees). The approved pictures are put, in their new order, into the places the approved ones held (ordered by `sort_order, id`, rows locked); pending / rejected pictures keep their relative places.
  ALL rows are numbered 0..n-1 again in one transaction. Past the first / last approved picture `409 {"error":"cannot_move"}`.
- `POST .../images/:imageId/review` `{"decision":"approve"|"reject","reason":"..."}` -> `{"item":{...},"items":[...]}`; super admin only (`403 {"error":"forbidden_review"}` for a hospital account).
  A rejection needs a reason (trimmed, 1..1000 characters). `UPDATE ... WHERE id=? AND hospital_treatment_id=? AND approval_status='pending'`: a picture that is not pending any more (or does not exist) answers
  `409 {"error":"already_reviewed"}`. `reviewed_by` = the admin's name (at most 100 characters), `reviewed_at` = `NOW()`; on approval `reject_reason` is set to NULL.
Same people may use the writes as for the other treatment writes (`canManage`, section 10; otherwise `403 {"error":"forbidden_treatments"}`); a hospital account only reaches its own hospital. Every write is one transaction
of the write pool in which the assignment row is locked (`SELECT ... FOR UPDATE`, not changed). `GET /v1/hospitals/:id/treatments` also answers `imagesEnabled`; the counts `imageCount` / `pendingImageCount` are unchanged.
This API never runs `ALTER TABLE` / `CREATE TABLE`: the table comes from the PHP migration (the PHP page creates it on first use); without it (or without the read grant) the list answers
`503 {"error":"images_unavailable"}` and a write `503 {"error":"write_unavailable"}`.

**Before the first write take a dump** (outside the web root): `mysqldump --single-transaction <DB_NAME> gangnamunni_hospital_treatment_images > ~/backup-treatment-images-$(date +%F).sql` and note that the files live in `<gangnamunni>/uploads/treatments/`.

Database. Already granted: the read-only user's `SELECT (id, hospital_treatment_id, approval_status)` on the image table (10.1) and its access to `gangnamunni_hospital_treatments` (`id`, `hospital_id`), `gangnamunni_hospitals` (`id`) and
`settings` (10.1); the write user's `SELECT (id, treatment_id, status, is_active)` and `SELECT (hospital_id)` on `gangnamunni_hospital_treatments` (10.1, section 7), which is all the assignment lock needs. Still missing - run these
(check the columns with `SHOW COLUMNS FROM gangnamunni_hospital_treatment_images` and `... gangnamunni_treatment_master` first; a `GRANT` naming a missing column fails as a whole):
```sql
-- read-only user: the rest of the columns of the list
GRANT SELECT (image_url, caption, sort_order, uploaded_by, reject_reason, reviewed_at, reviewed_by) ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (image_url, caption, sort_order, uploaded_by, reject_reason, reviewed_at, reviewed_by) ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_ro'@'localhost';
-- read-only user: the standard picture of the treatment (the list answers null without it; id, is_active are already granted by step 1 / 10.1)
GRANT SELECT (default_image_url) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (default_image_url) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_ro'@'localhost';
-- write user
GRANT SELECT (id, hospital_treatment_id, image_url, sort_order, approval_status) ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, hospital_treatment_id, image_url, sort_order, approval_status) ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_rw'@'localhost';
GRANT INSERT (hospital_treatment_id, hospital_id, treatment_id, image_url, caption, sort_order, approval_status, uploaded_by) ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (hospital_treatment_id, hospital_id, treatment_id, image_url, caption, sort_order, approval_status, uploaded_by) ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (sort_order, approval_status, reject_reason, reviewed_at, reviewed_by) ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (sort_order, approval_status, reject_reason, reviewed_at, reviewed_by) ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_rw'@'localhost';
GRANT DELETE ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_rw'@'127.0.0.1';   -- already there if 10.2 was run; running it again is harmless
GRANT DELETE ON <DB_NAME>.gangnamunni_hospital_treatment_images TO 'sb_admin_rw'@'localhost';
SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1';   -- check: ..._treatment_images has SELECT (5 columns above), INSERT (8), UPDATE (5) and DELETE
SHOW GRANTS FOR 'sb_admin_ro'@'127.0.0.1';   -- check: ..._treatment_images has SELECT (id, hospital_treatment_id, approval_status, + the 7 above); ..._treatment_master has SELECT (..., default_image_url)
```
Without the INSERT / UPDATE / DELETE rights the answer is `503 {"error":"write_unavailable"}` and nothing changes (an uploaded file of that request is removed again).

Upload folder (only for file uploads; pictures added by address need none of this). Same method as section 8.2, but for the folder the PHP page already uses, `<gangnamunni>/uploads/treatments/`:
```bash
G=/path/to/gangnamunni            # = ADMIN_UPLOAD_ROOT
ls -ld $G/uploads/treatments; ps -o user= -C httpd | sort -u            # PHP / Apache usually owns it
sudo mkdir -p $G/uploads/treatments
sudo setfacl -R -m u:sbapi:rwX $G/uploads/treatments
sudo setfacl -R -d -m u:sbapi:rwX $G/uploads/treatments
sudo setfacl -m u:sbapi:--x $G $G/uploads                          # traverse only (already done for 8.2)
sudo systemctl edit seoulbeauty-admin-api      # the drop-in of 8.2: put the new folder on the ReadWritePaths line (a second path, separated by a space, or a second ReadWritePaths= line)
#   [Service]
#   ReadWritePaths=/path/to/gangnamunni/uploads/hospitals /path/to/gangnamunni/uploads/treatments
sudo systemctl daemon-reload && sudo systemctl restart seoulbeauty-admin-api
sudo -u sbapi sh -c 'touch $G/uploads/treatments/_probe && rm $G/uploads/treatments/_probe'      # the service user can write
```
With the shared-group way of 8.2 do the same `chgrp` / `chmod g+rwX` / `chmod g+s` on `$G/uploads/treatments`. The API creates the folder (0755) when it is missing and the parent is writable; files are 0644, so Apache keeps reading them.
SELinux: keep the type Apache may read (`ls -Z $G/uploads/treatments`). Files the PHP admin wrote earlier stay untouched, but the API can only remove (on DELETE) what its user may delete in that folder - give the ACL above `-R` (done) so
old files are covered too; a file that still cannot be removed is only logged. A folder that cannot be used answers `503 {"error":"uploads_unavailable"}`. Apache / proxy limits: one file of at most 10 MB per request, so the limits of 8.3 are enough.

Env (needs sections 6 and 10, and the 10.6 grants first; leave `0` until then), then `sudo systemctl restart seoulbeauty-admin-api`:
```
ADMIN_HOSPITALS_TREATMENTS=1
ADMIN_HOSPITALS_TREATMENTS_IMAGES=1
ADMIN_UPLOAD_ROOT=/path/to/gangnamunni     # only for file uploads
```
`/v1/me` then reports `"hospitalsTreatmentsImages":true`. Check with a TEST hospital (headers as in 10.3; `<ht>` = an `items[].id` of the list, `<img>` = an `items[].id` of the image list; the file upload uses `multipart/form-data`, so no `Content-Type` header of your own):
```bash
curl -s $C http://127.0.0.1:3300/v1/hospitals/<id>/treatments/<ht>/images                       # {"items":[...],"canReview":true}
curl -s -X POST -H X-Requested-With:admin-next -H Cookie:PHPSESSID=<value> -F image=@test.jpg -F caption=test http://127.0.0.1:3300/v1/hospitals/<id>/treatments/<ht>/images
curl -s -X POST -H X-Requested-With:admin-next -H Cookie:PHPSESSID=<value> -F imageUrl=https://example.com/a.jpg http://127.0.0.1:3300/v1/hospitals/<id>/treatments/<ht>/images
curl -s -X POST $H -d '{"direction":"up"}' http://127.0.0.1:3300/v1/hospitals/<id>/treatments/<ht>/images/<img>/move
curl -s -X POST $H -d '{"decision":"reject","reason":"too blurry"}' http://127.0.0.1:3300/v1/hospitals/<id>/treatments/<ht>/images/<img>/review
curl -s -X DELETE $H http://127.0.0.1:3300/v1/hospitals/<id>/treatments/<ht>/images/<img>
ls -l $G/uploads/treatments | tail -3                                                              # the uploaded file: -rw-r--r--, readable by Apache
```
Answers: add `400 {"error":"image_required"|"invalid_url"|"invalid_image"|"too_large"|"caption_too_long"|"upload_failed"}`, move `400 {"error":"invalid_field","field":"direction"}` and `409 {"error":"not_approved"|"cannot_move"}`,
review `400 {"error":"invalid_field","field":"decision"|"reason"|"reason_required"|"reason_too_long"}` (the last two without `field`) and `409 {"error":"already_reviewed"}`, `403` (csrf, `forbidden`, `forbidden_treatments`, `forbidden_review`),
`404` (switch off, hospital out of scope, assignment not of this hospital, image not of this assignment), `503 {"error":"images_unavailable"|"write_unavailable"|"uploads_unavailable"}`.
Each write is logged: `hospital treatments: admin=<id> hospital=<id> image add|delete|move-up|move-down|approve|reject treatment=<ht> image=<id>` (never the address, caption or reason).
Turning it off: `ADMIN_HOSPITALS_TREATMENTS_IMAGES=0`, restart (the image routes answer 404; the list keeps the counts).

## 11. Submitted documents (`ADMIN_HOSPITALS_DOCUMENTS`, optional)
Adds `POST /v1/hospitals/:id/documents/:doc` (upload or replace; multipart, one file in the field `file`) and `DELETE /v1/hospitals/:id/documents/:doc`
(`:doc` = `business` for the business registration or `medical` for the medical institution licence). Who may use it: a super admin and the hospital account bound to
that hospital (any other hospital answers 404). The files are the same ones the application form stores and the PHP viewer (`hospital_document.php`) opens;
this API never serves them. The hospital detail answer gets `documentsEditable` (true only with the switch on and an allowed account); `/v1/me` reports `hospitalsDocuments`.
Needs sections 1 and 6 (hospital pages, write user). With the switch off both routes answer 404 and nothing below is used.

What is stored: `gangnamunni_hospitals.cert_business` / `cert_medical` = `uploads/hospital_apply/hospital_<id>/cert_<business|medical>_<32 hex>.<jpg|png|pdf>`
(the shape `hospital_document.php` accepts). The name is random, the extension comes from the real file type (first bytes: JPEG, PNG, `%PDF-`), never from the name the browser
sent; JPG / PNG / PDF only, at most 5 MB. Nothing is written to `gangnamunni_hospital_revisions` (those rows are written by the applicant's own page, not by admin edits, and
neither PHP admin nor section 6 writes them) and `updated_at` is not touched, so an open edit form does not get a false `409`.

**Before the first upload, dump the column data** (an upload updates one of the two columns, a delete empties it):
```bash
mysqldump --single-transaction <DB_NAME> gangnamunni_hospitals > ~/backup-hospital-documents-$(date +%F).sql   # keep it outside the web root; the files themselves are in <gangnamunni>/uploads/hospital_apply/
```

### 11.1 Database (run as the DB admin; `<DB_NAME>` = schema from step 1)
The read-only user already reads both columns (step 1). The write user needs to read and update exactly these two columns, nothing else new
(`id` is already granted in 6.1; `status` is checked by the read-only user):
```sql
GRANT SELECT (cert_business, cert_medical) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (cert_business, cert_medical) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (cert_business, cert_medical) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (cert_business, cert_medical) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'localhost';
SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1';   -- check: the new lines name only cert_business, cert_medical; nothing about ad_grade / status / rating
```
No `ALTER` / `CREATE` is needed. A missing grant answers `503 {"error":"documents_unavailable"}` and nothing is changed (the file just written is removed again).
This note replaces the older "no cert_*" remark of 6.1 for the write user: from now on it may read and update these two columns.

### 11.2 Upload folder (on the server)
Same method as section 8.2, for `<gangnamunni>/uploads/hospital_apply/` (the folder the application form already uses; PHP / Apache usually owns it). The API creates
`hospital_<id>/` (0755) and the files (0644), so PHP can read them for the viewer:
```bash
G=/path/to/gangnamunni
ls -ld $G/uploads $G/uploads/hospital_apply; ps -o user= -C httpd | sort -u
sudo mkdir -p $G/uploads/hospital_apply                                   # normally exists already
sudo setfacl -R -m u:sbapi:rwX $G/uploads/hospital_apply                  # -R: also the applicants' existing folders, so a replaced / deleted old file can be removed
sudo setfacl -R -d -m u:sbapi:rwX $G/uploads/hospital_apply              # default: new entries
sudo setfacl -m u:sbapi:--x $G $G/uploads                                 # traverse only (already done for 8.2)
sudo systemctl edit seoulbeauty-admin-api      # the drop-in of 8.2: add the folder as one more ReadWritePaths entry (a second path separated by a space, or a second line)
#   [Service]
#   ReadWritePaths=/path/to/gangnamunni/uploads/hospitals /path/to/gangnamunni/uploads/treatments /path/to/gangnamunni/uploads/hospital_apply
sudo systemctl daemon-reload && sudo systemctl restart seoulbeauty-admin-api
sudo -u sbapi sh -c 'touch $G/uploads/hospital_apply/_probe && rm $G/uploads/hospital_apply/_probe'     # the service user can write
```
With the shared-group way of 8.2 do the same `chgrp` / `chmod g+rwX` / `chmod g+s` on `$G/uploads/hospital_apply`. The folder must exist before the service starts (a missing
`ReadWritePaths` path stops the unit). SELinux (Rocky): keep the type Apache may read (`ls -Z $G/uploads/hospital_apply`; see `ausearch -m avc -ts recent`). Files the application form wrote
earlier (mode 0600 / 0644, owner Apache) are only removed on replace / delete when the ACL above covers them; a file that cannot be removed is only logged and stays on disk.
A folder that cannot be used answers `503 {"error":"uploads_unavailable"}`; a `hospital_<id>` folder that is a symbolic link is refused the same way.

**Direct URL protection must stay as it is.** In the repository the documents are shut off by `gangnamunni/uploads/.htaccess` (`<FilesMatch "^cert_(business|medical)_">` -> denied for every
folder below `uploads/`, so also `hospital_apply/hospital_<id>/`); there is NO separate `uploads/hospital_apply/.htaccess` in the repository. The new names start with `cert_business_` / `cert_medical_`, so
they are covered. Check on the server, after the first upload (a TEST hospital), that the file is not reachable by its address, only through the PHP viewer:
```bash
test -f $G/uploads/.htaccess && grep -n 'cert_(business|medical)' $G/uploads/.htaccess        # the rule is there
ls -l $G/uploads/hospital_apply/.htaccess 2>&1                                                  # absent is expected; if the server has one, leave it untouched
curl -s -o /dev/null -w '%{http_code}\n' https://seoulbeauty.app/<path to gangnamunni>/uploads/hospital_apply/hospital_<id>/<file>      # must be 403 (or 404), never 200
```
Apache must have `AllowOverride` enough for `.htaccess` (`FileInfo AuthConfig Limit`, as for the other upload folders); if the direct address answers 200, STOP: turn the switch off
(`ADMIN_HOSPITALS_DOCUMENTS=0`) and fix that first.

### 11.3 Apache / proxy limits
One file of at most 5 MB per request; the limits of 8.3 (much larger) are enough.

### 11.4 Env and turning it on
```
ADMIN_HOSPITALS_ENABLED=1
ADMIN_HOSPITALS_WRITE=1
ADMIN_HOSPITALS_DOCUMENTS=1
ADMIN_UPLOAD_ROOT=/path/to/gangnamunni
```
Then `sudo systemctl restart seoulbeauty-admin-api`. Check with a TEST hospital (super admin PHPSESSID; any small JPEG / PNG / PDF; the multipart request needs no `Content-Type` of your own):
```bash
C='-H Cookie:PHPSESSID=<value>'
M='-H X-Requested-With:admin-next -H Cookie:PHPSESSID=<value>'                       # upload (curl sets the multipart Content-Type)
J='-H Content-Type:application/json -H X-Requested-With:admin-next -H Cookie:PHPSESSID=<value>'   # delete
curl -s $C http://127.0.0.1:3300/v1/me | grep -o '"hospitalsDocuments":[a-z]*'                         # true
curl -s $C http://127.0.0.1:3300/v1/hospitals/<id> | grep -o '"documentsEditable":[a-z]*'               # true (super admin, or the account bound to <id>)
curl -s -X POST $M -F 'file=@/tmp/business.pdf' http://127.0.0.1:3300/v1/hospitals/<id>/documents/business
                                                                                      # 200 {"documents":{"business":true,"medical":false}}
curl -s -X POST $M -F 'file=@/tmp/licence.jpg' http://127.0.0.1:3300/v1/hospitals/<id>/documents/medical     # upload / replace the other one
curl -s -X POST $M -F 'file=@/tmp/new.png' http://127.0.0.1:3300/v1/hospitals/<id>/documents/business        # replace: the old file disappears after the commit
ls -l $G/uploads/hospital_apply/hospital_<id>/                                         # cert_business_<32 hex>.png, mode 0644; folder 0755
curl -s -X DELETE $J http://127.0.0.1:3300/v1/hospitals/<id>/documents/business      # 200 {"documents":{"business":false,"medical":true}}
curl -s -X DELETE $J http://127.0.0.1:3300/v1/hospitals/<id>/documents/business      # 404 {"error":"no_document"}
```
Then open the hospital in the PHP admin: the document link (`hospital_document.php?hospital_id=<id>&doc=business`) must show the new file. Negative checks: a text file named `x.pdf` -> `400 invalid_file`;
a hospital account on another hospital's id -> `404 {"error":"not_found"}`; no `X-Requested-With` header -> `403 {"error":"csrf"}`.

Answers: `400 {"error":"file_required"|"invalid_file"|"too_large"|"upload_failed"}`, `403` (`csrf`, `forbidden` = a role that may not use the panel, `forbidden_documents`), `404` (`not_found`: switch off, hospital outside the
account's scope / not approved / unknown, wrong `:doc`; `no_document`: DELETE of a document that does not exist), `503` (`documents_unavailable` = write user, grants or columns; `uploads_unavailable` = upload folder).
Guard order of the upload: switch (404) -> CSRF (403) -> signed in / role (401, 403) -> scope and `:doc` (404) -> only then the file is read.
Every change is logged: `journalctl -u seoulbeauty-admin-api | grep 'hospital documents'` -> `hospital documents: admin=<id> hospital=<id> doc=<business|medical> upload|replace|delete` (never a path, a file name or content).

How a change is made: the new file is written first; then one transaction locks the hospital row and updates only that one column; only after the commit is the OLD file removed, and only when its stored value has the exact
viewer shape for that document and its real path (links resolved) is a regular file inside `uploads/hospital_apply/` (anything else is left alone). A failed update removes the new file and keeps the old one. A delete empties the
column first and removes the file afterwards (best-effort).

Rollback: `ADMIN_HOSPITALS_DOCUMENTS=0` + restart (both routes 404, buttons disappear; the documents stay and the PHP viewer keeps working). To also close the door at the database:
`REVOKE SELECT (cert_business, cert_medical), UPDATE (cert_business, cert_medical) ON <DB_NAME>.gangnamunni_hospitals FROM 'sb_admin_rw'@'127.0.0.1';` and the same for `'localhost'`; remove the `hospital_apply` entry from `ReadWritePaths`
and the ACL (`sudo setfacl -R -x u:sbapi $G/uploads/hospital_apply; sudo setfacl -R -k $G/uploads/hospital_apply`). Restore of data: the dump above for the columns; files removed by a replace or delete are gone (keep a copy of
`$G/uploads/hospital_apply/` before testing on real hospitals: `sudo tar czf ~/hospital_apply-$(date +%F).tgz -C $G/uploads hospital_apply`, outside the web root).

## 12. Approving / rejecting applications (`ADMIN_HOSPITALS_REVIEW`, optional)
Adds `POST /v1/hospitals/:id/approve` and `POST /v1/hospitals/:id/reject` for a hospital whose `status` is `pending` (super admin only; any other role gets 403).
Only the status columns change. **No hospital login account is created, no e-mail is sent, no notification is written**: after an approval the hospital still needs its
account from the PHP admin. `/v1/me` reports `hospitalsReview`. Needs sections 1 and 6 (hospital pages, write user). With the switch off both routes answer 404 and nothing below is used.

What each call does, in ONE transaction of the write pool: locks the row (`SELECT id, status ... FOR UPDATE`); unknown id -> `404 {"error":"not_found"}`; status other than `pending` ->
`409 {"error":"already_reviewed"}`; then
- approve: `status='approved', is_active=1, ad_grade='none', reviewed_by=<admin name, at most 100 characters>, reviewed_at=NOW(), updated_at=NOW()` -> `200 {"ok":true,"id":<id>,"status":"approved"}`;
- reject (body `{"reason":"<text>"}`, trimmed, 1..1000 characters; empty or missing -> `400 {"error":"reason_required","fields":{"reason":"required|bad_format|too_long"}}`):
  `status='rejected', is_active=0, reject_reason=<reason>, rejection_reason=<reason>, reviewed_by, reviewed_at=NOW(), updated_at=NOW()` -> `200 {"ok":true,"id":<id>,"status":"rejected"}`.
The UPDATE repeats `AND status = 'pending'`, so two admins at once cannot both succeed. A rejected hospital stays out of the Nest list and detail (they show approved and, to super admins, pending ones only).

### 12.1 Database (run as the DB admin; `<DB_NAME>` = schema from step 1)
The write user needs to read `status` (it already reads `id`, 6.1) and update exactly these columns of `gangnamunni_hospitals`:
```sql
GRANT SELECT (status) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (status) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (status, is_active, ad_grade, reviewed_by, reviewed_at, reject_reason, rejection_reason, updated_at) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (status, is_active, ad_grade, reviewed_by, reviewed_at, reject_reason, rejection_reason, updated_at) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'localhost';
SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1';   -- check: the new lines name only the columns above
```
Some of these columns may already be granted by sections 6 and 7 (`is_active`, `updated_at`, `ad_grade`); granting a column twice is harmless. No `ALTER` / `CREATE` is needed.
After this section the write user can update `status` and `ad_grade` (applications only: the code requires `status = 'pending'`), so the check in 6.1 that says "no ad_grade / status" no longer applies.
A missing grant answers `503 {"error":"write_unavailable"}` and nothing is changed. Before the first real use dump the table:
```bash
mysqldump --single-transaction <DB_NAME> gangnamunni_hospitals > ~/backup-hospital-review-$(date +%F).sql   # keep it outside the web root
```

### 12.2 Env and turning it on
```
ADMIN_HOSPITALS_ENABLED=1
ADMIN_HOSPITALS_WRITE=1
ADMIN_HOSPITALS_REVIEW=1
```
Then `sudo systemctl restart seoulbeauty-admin-api`. Check with a TEST application (a `pending` row; super admin PHPSESSID):
```bash
C='-H Cookie:PHPSESSID=<value>'
H='-H Content-Type:application/json -H X-Requested-With:admin-next -H Cookie:PHPSESSID=<value>'
curl -s $C http://127.0.0.1:3300/v1/me | grep -o '"hospitalsReview":[a-z]*'                                    # true
curl -s $C 'http://127.0.0.1:3300/v1/hospitals?pending=1'                                                       # the pending applications
curl -s -X POST $H -d '{"reason":""}' http://127.0.0.1:3300/v1/hospitals/<pendingId>/reject                       # 400 {"error":"reason_required",...}
curl -s -X POST $H -d '{"reason":"test"}' http://127.0.0.1:3300/v1/hospitals/<pendingId>/reject                   # 200 {"ok":true,"id":<id>,"status":"rejected"}
curl -s -X POST $H -d '{"reason":"test"}' http://127.0.0.1:3300/v1/hospitals/<pendingId>/reject                   # 409 {"error":"already_reviewed"}
curl -s -X POST $H -d '{}' http://127.0.0.1:3300/v1/hospitals/<otherPendingId>/approve                            # 200 {"ok":true,"id":<id>,"status":"approved"}
curl -s -X POST $H -d '{}' http://127.0.0.1:3300/v1/hospitals/<otherPendingId>/approve                            # 409 {"error":"already_reviewed"}
curl -s -X POST -H Content-Type:application/json -H Cookie:PHPSESSID=<value> -d '{}' http://127.0.0.1:3300/v1/hospitals/<id>/approve   # 403 {"error":"csrf"}
```
Answers: `400` `reason_required`; `403` (`csrf`, or a role that is not super admin); `404` `not_found` (switch off or unknown id); `409` `already_reviewed`; `503` `write_unavailable`.
Guard order: switch (404) -> CSRF (403) -> signed in / role (401, 403) -> id (404) -> body (400) -> database.
Every review is logged: `journalctl -u seoulbeauty-admin-api | grep 'hospital review'` -> `hospital review: admin=<id> hospital=<id> status=<approved|rejected>` (never the reason).
Rollback: `ADMIN_HOSPITALS_REVIEW=0` + restart (both routes 404, buttons disappear; reviewed rows stay as they are). To also close the door at the database:
`REVOKE SELECT (status), UPDATE (status, reviewed_by, reviewed_at, reject_reason, rejection_reason) ON <DB_NAME>.gangnamunni_hospitals FROM 'sb_admin_rw'@'127.0.0.1';` and the same for `'localhost'`
(leave `is_active`, `ad_grade`, `updated_at` if sections 6 / 7 still need them). A review itself is undone by restoring the dump above or by editing the row in the database.

## 13. Treatment master (`ADMIN_TREATMENTS_ENABLED`, optional; super admin only)
The global treatment catalogue of the PHP page `admin_treatments.php` (treatments, their aliases, the categories, the "cannot be booked on the same day" pairs and the home
table mode). Independent of the hospital switches (sections 6-12): it needs only section 1 (read-only user) and, for editing, the write user of 6.1. Hospital roles
get `403 {"error":"forbidden"}` on every route of this section, like on the PHP page. Off by default; with the switch off every route answers `404 {"error":"not_found"}`
and `/v1/me` reports `"treatments":false`.

Routes of this stage (reading only; nothing in this section writes):
- `GET /v1/treatments` -> everything in one answer (the page filters, searches and sorts in the browser):
  `{"stats":{"total","active","inactive","categories"},"schema":{"revisit","defaultImage","conflicts"},"tableMode":"excel"|"row"|"hybrid",`
  `"categories":[{"id","name","slug","icon","sortOrder","isActive","treatmentCount"}],`
  `"items":[{"id","categoryId","categoryName","sortOrder","name","hashtag","description","defaultImageUrl","durationMin","recoveryDays","anesthesiaType","repeatCycleMonths",`
  `"revisitDays","revisitStrict","precautions","avgPriceMin","avgPriceMax","isActive","popularityScore","createdBy","createdAt","aliasCount","hospitalCount","aliases":[{"id","alias","lang"}]}],`
  `"conflicts":[{"id","a","b"}]}`.
  `stats.categories` counts the active categories; `total` / `active` / `inactive` and `treatmentCount` count every treatment (inactive ones too). `hospitalCount` = rows of
  `gangnamunni_hospital_treatments` with that `treatment_id` (released assignments included). `defaultImageUrl` is `null` when blank; `revisitDays` `null` = no limit;
  `createdAt` is `YYYY-MM-DD HH:MM:SS` (KST) or `null`. Order: category sort, treatment sort, popularity (desc), id - the order of the PHP page. Nothing is cached.
- `/v1/me` -> `"features":{"treatments","treatmentsWrite","treatmentsImages"}` (the switches only; hospital roles never reach the routes).
Writing is section 13.2; with `ADMIN_TREATMENTS_WRITE=0` every writing route answers 404 and the page hides its editing controls.

The three optional parts come from PHP migrations that this API never runs (`hairwang_full/gangnamunni/sql/`): `treatment_master_default_image.sql` (column `default_image_url`),
`treatment_master_revisit_20261021.sql` (columns `revisit_days`, `revisit_strict`) and `treatment_conflicts_20261023.sql` (table `gangnamunni_treatment_conflicts`). Each is read by
its own statement: a missing table, column or grant (MySQL 1146 / 1054 / 1142 / 1143) makes that `schema` flag `false` (the page hides the fields; `defaultImageUrl` `null`,
`revisitDays` `null`, `conflicts` `[]`) and is logged as a warning; the page is still served. The same for the setting row (`tableMode` falls back to `excel`). Any other
database error on any statement answers `500 {"error":"server_error"}` (reason in the journal). The log line does not say whether the column or the grant is missing:
check with `SHOW COLUMNS FROM gangnamunni_treatment_master LIKE 'revisit_days'` and `SHOW GRANTS FOR 'sb_admin_ro'@'127.0.0.1'`.

### 13.1 Reading (run as the DB admin; `<DB_NAME>` = schema from step 1)
Already granted by sections 1 / 10 / 10.6 (not repeated): `gangnamunni_treatment_master (id, name, category_id, hashtag, is_active, sort_order, default_image_url)`,
`gangnamunni_treatment_categories (id, name, sort_order)`, `gangnamunni_hospital_treatments (hospital_id, treatment_id, status, is_active)`, `gangnamunni_settings (setting_key, setting_value)`.
If 10.6 was not run, add `default_image_url` to the first statement below (or skip it while the column is not installed).
```sql
GRANT SELECT (description, duration_min, recovery_days, anesthesia_type, repeat_cycle_months, precautions, avg_price_min, avg_price_max, popularity_score, created_by, created_at)
  ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (description, duration_min, recovery_days, anesthesia_type, repeat_cycle_months, precautions, avg_price_min, avg_price_max, popularity_score, created_by, created_at)
  ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_ro'@'localhost';
GRANT SELECT (slug, icon, is_active) ON <DB_NAME>.gangnamunni_treatment_categories TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (slug, icon, is_active) ON <DB_NAME>.gangnamunni_treatment_categories TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, treatment_id, alias, lang) ON <DB_NAME>.gangnamunni_treatment_aliases TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, treatment_id, alias, lang) ON <DB_NAME>.gangnamunni_treatment_aliases TO 'sb_admin_ro'@'localhost';
-- optional: only after the matching migration is installed (a GRANT naming a missing column or table fails as a whole; the API reports the flag false until then)
GRANT SELECT (revisit_days, revisit_strict) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (revisit_days, revisit_strict) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, tm_a_id, tm_b_id) ON <DB_NAME>.gangnamunni_treatment_conflicts TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, tm_a_id, tm_b_id) ON <DB_NAME>.gangnamunni_treatment_conflicts TO 'sb_admin_ro'@'localhost';
SHOW GRANTS FOR 'sb_admin_ro'@'127.0.0.1';   -- check: still SELECT only; gangnamunni_treatment_reviews is NOT granted (the page does not read it)
```
Env, then `sudo systemctl restart seoulbeauty-admin-api`:
```
ADMIN_TREATMENTS_ENABLED=1
```
Check (super admin PHPSESSID):
```bash
C='-H Cookie:PHPSESSID=<value>'
curl -s $C http://127.0.0.1:3300/v1/me                                   # "treatments":true,"treatmentsWrite":false,"treatmentsImages":false
curl -s $C http://127.0.0.1:3300/v1/treatments | head -c 600             # {"stats":{...},"schema":{"revisit":true,"defaultImage":true,"conflicts":true},"tableMode":"excel",...
curl -s -H 'Cookie: PHPSESSID=<hospital admin>' http://127.0.0.1:3300/v1/treatments   # 403 {"error":"forbidden"}
journalctl -u seoulbeauty-admin-api | grep -i 'unavailable'              # a schema flag that is false says here which statement failed
```
Turning it off: `ADMIN_TREATMENTS_ENABLED=0` + restart (every route 404, the menu entry points back at the PHP page).

### 13.2 Editing (`ADMIN_TREATMENTS_WRITE`)
Adds create / edit / activate / delete / bulk show-hide / reorder of treatments, aliases, categories, conflict pairs and the home table mode, through the write user of 6.1.
Keep `ADMIN_TREATMENTS_WRITE=0` until the grants below are in place and the dump (last paragraph) is taken; with it off every writing route answers 404 and the page hides its editing controls.
```sql
-- treatments
GRANT SELECT (id, category_id, name, is_active, default_image_url) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (category_id, sort_order, name, hashtag, description, default_image_url, duration_min, recovery_days, anesthesia_type, repeat_cycle_months, precautions, avg_price_min, avg_price_max, is_active, created_by)
  ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (category_id, sort_order, name, hashtag, description, default_image_url, duration_min, recovery_days, anesthesia_type, repeat_cycle_months, precautions, avg_price_min, avg_price_max, is_active)
  ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_rw'@'127.0.0.1';
GRANT DELETE ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_rw'@'127.0.0.1';
-- aliases
GRANT SELECT (id, treatment_id, alias, lang), INSERT (treatment_id, alias, lang), DELETE ON <DB_NAME>.gangnamunni_treatment_aliases TO 'sb_admin_rw'@'127.0.0.1';
-- categories (the delete statement checks the treatments of the category itself, hence SELECT on category_id above)
GRANT SELECT (id, slug), INSERT (name, slug, icon, sort_order, is_active), UPDATE (name, slug, icon, sort_order, is_active), DELETE ON <DB_NAME>.gangnamunni_treatment_categories TO 'sb_admin_rw'@'127.0.0.1';
-- home table mode: the row tc_table_mode. UPDATE cannot be limited to one row, so the write user may change any setting_value; the API only ever names tc_table_mode.
GRANT SELECT (id, setting_key), INSERT (setting_key, setting_value, setting_group, description), UPDATE (setting_value) ON <DB_NAME>.gangnamunni_settings TO 'sb_admin_rw'@'127.0.0.1';
-- optional, after the matching migration (INSERT is needed too: once the columns exist, creating a treatment writes them and is refused with 503 write_unavailable without it)
GRANT INSERT (revisit_days, revisit_strict), UPDATE (revisit_days, revisit_strict) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, tm_a_id, tm_b_id), INSERT (tm_a_id, tm_b_id), DELETE ON <DB_NAME>.gangnamunni_treatment_conflicts TO 'sb_admin_rw'@'127.0.0.1';
-- and each statement again for 'sb_admin_rw'@'localhost'
```
Then set `ADMIN_TREATMENTS_WRITE=1` next to the existing `DB_RW_*` keys and run `sudo systemctl restart seoulbeauty-admin-api`.
A refused grant shows as `503 {"error":"write_unavailable"}` (reason in the journal).

Uploading the default image (`POST /v1/treatments/:id/image`) is a second switch, `ADMIN_TREATMENTS_IMAGES=1` (needs `ADMIN_TREATMENTS_WRITE=1`). It needs no further GRANT (it only sets `default_image_url`, already
granted above) but it writes files: `ADMIN_UPLOAD_ROOT` must be the absolute path of the PHP `gangnamunni` directory, and the service user must be able to write to `<ADMIN_UPLOAD_ROOT>/uploads/treatments/`
(the folder of section 10.6; created when missing). A file is accepted as JPG / PNG / GIF / WebP up to 10 MB, only when its real type matches its extension; it is stored as `treat_<time>_<8 hex>.<ext>`.
The previous image file of a treatment is not deleted when it is replaced (as on the PHP page). With the switch off the route answers 404 and the form only offers an image address.
Deleting a treatment is a hard delete (as on the PHP page) and will be refused with `409 {"error":"in_use"}` while any hospital has it; a treatment that is part of a package
(`gangnamunni_treatment_package_items`, foreign key `ON DELETE RESTRICT`) cannot be deleted by the database either (answered `409 in_use`). Take a dump of the five tables above before the first write.

### 13.3 Home menu categories page (`ADMIN_CATEGORY_BOARD`)
Separate page for the categories of the home menu (order, treatments of each category, treatments with no category). Needs `ADMIN_TREATMENTS_ENABLED=1`; its writes also need `ADMIN_TREATMENTS_WRITE=1`. Default off; with the key off every route answers 404.
| Route | Purpose |
|---|---|
| `GET /v1/category-board` | categories in home order with their treatments, plus the treatments that belong to no category |
| `POST /v1/category-board/categories/:id/treatments` `{treatmentIds:[…]}` | add existing treatments (moved from another category / none) to the end of the category |
| `DELETE /v1/category-board/categories/:id/treatments/:treatmentId` | take a treatment out: its `category_id` becomes NULL, the app no longer shows it |
| `DELETE /v1/category-board/categories/:id` | delete the category; its treatments become uncategorized (one transaction) |
Adding, renaming, switching on/off and reordering categories use the existing `/v1/treatment-categories*` routes. No new GRANT: the statements use `UPDATE (category_id, sort_order)` on the treatment master and `DELETE` on the categories, both from 13.2.
`gangnamunni_treatment_master.category_id` must accept NULL; if it does not, taking a treatment out answers `503 {"error":"uncategorized_unsupported"}` and nothing changes.
Env: `ADMIN_CATEGORY_BOARD=1`, then `sudo systemctl restart seoulbeauty-admin-api`.

## 14. Registering a hospital (`ADMIN_HOSPITAL_APPLY_ENABLED`, optional; super admin only)
The PHP page `hospital_apply.php` without its application workflow: a super admin fills in a hospital and it is stored at once as `status='approved'`, `is_active=0`
(inactive; it is switched on from the hospital page). Independent of the hospital switches (sections 6-12). Hospital roles and anyone not signed in get 403 / 401; with the switch
off every route answers `404 {"error":"not_found"}` and `/v1/me` reports `"hospitalApply":false`.

Routes (guard order: switch -> CSRF for POST -> signed in -> super admin):
- `GET /v1/hospital-apply/options` -> `{"categories":[{"id","name"}],"treatments":[{"id","name","categoryId"}],"units":[...],"devMode":bool,"limits":{...}}` (active master treatments;
  only categories that have one; units from `gangnamunni_treatment_units`, the standard list without it).
- `GET /v1/hospital-apply/phone-check?phone=` -> `{"duplicate":bool,"hospitalName"?}`; empty or without digits -> `400 invalid_field phone`.
- `POST /v1/hospital-apply` (`multipart/form-data`, marker header `X-Requested-With: admin-next`). Text field `payload` = JSON
  `{name*, ownerName, businessNumber, phone, email, contactName, contactPhone, address, lat, lng, businessHours{월..일,점심|no_lunch_break,special_closed_dates}, introduction, website, snsUrl,
  treatments:[{treatmentId, prices:[{optionName,dosageValue,dosageUnit,price}]}], doctors:[{name,specialty,experienceYears,qualification,bio}]}`.
  Files: `certBusiness`, `certMedical`, `profileImage`, `interiorImages` (up to 10), `doctorPhoto_<position in doctors>`, `treatmentImages_<treatment id>` (up to 20 each).
  Only `name` is required. A price row is stored only when dosage > 0, a unit and price > 0 are all there, otherwise it is left out and counted in `warnings`
  (`[{"code":"price_rows_skipped","count":n}]`); a doctor row only when it has a name. Answer: `200 {"id":123,"warnings":[]}`.
  Errors: `400 invalid_body | invalid_field {field,index?} | too_many {field}`, `409 duplicate_phone {hospitalName}`, `413 file_too_large {field}`, `415 bad_file_type {field}`,
  `503 write_unavailable | uploads_unavailable`. Order of the checks (as on the PHP page): name, phone duplicate, e-mail, the rest of the form, treatments, files. The SNS field is free text (length only); extra closed days that are not after today (KST) are left out. Any error stores nothing:
  one transaction, and every file this request wrote is removed again.
- Stored: hospital (`status='approved'`, `is_active=0`, `ad_grade='none'`, `applied_by` and `reviewed_by` = the admin's username, `is_demo` = the PHP "dev mode" setting),
  `hospital_images` (main + interior), `gangnamunni_doctors` (active), `gangnamunni_hospital_treatments` (`registered_by='hospital'`), their price options and detail images
  (`approval_status='pending'`, `uploaded_by='hospital'`). Files: hospital files in `<ADMIN_UPLOAD_ROOT>/uploads/hospital_apply/admin_<admin id>/` (the PHP folder; certificates are
  named `cert_business_<hex>.<ext>` / `cert_medical_<hex>.<ext>`), doctor photos in `uploads/hospitals/<hospital id>/`, treatment images in `uploads/treatments/`.

### 14.1 Database (run as the DB admin; `<DB_NAME>` = schema from step 1)
Needs sections 6.1 (write user), 8 (`hospital_images` INSERT), 9 (doctors), 10.1 (treatment assignments, master `id, is_active`), 10.4 (price options) and 10.6 (detail images)
first, and the read-only grants of section 1 (`gangnamunni_hospitals` name/phone, treatment master, categories, units, settings). New for this section: INSERT of a whole hospital row.
```sql
GRANT INSERT (name, owner_name, business_number, phone, email, contact_name, contact_phone, address, latitude, longitude, categories, treatment_tags, business_hours,
  introduction, website, sns_url, profile_image_url, main_image_url, cert_business, cert_medical, applied_by, applied_at, status, is_active, ad_grade, reviewed_by,
  reviewed_at, is_demo, created_at) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'127.0.0.1';
-- and the same for 'sb_admin_rw'@'localhost'
SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1';   -- check: the new INSERT line names only the columns above
```
Undo: `REVOKE INSERT ON <DB_NAME>.gangnamunni_hospitals FROM 'sb_admin_rw'@'127.0.0.1';` (and `'localhost'`).

### 14.2 Upload folders and Apache limits
The service must be able to write below `<ADMIN_UPLOAD_ROOT>/uploads/` into `hospital_apply`, `hospitals` and `treatments` (sections 8, 9, 10.6, 11; `ReadWritePaths` of the unit).
One request can carry up to about 130 files of up to 10 MB: allow a large enough body in the proxy in front of the admin web app (`/admin-next/api/*`).

### 14.3 Env and turning it on
`ADMIN_PANEL_ENABLED=1`, `ADMIN_HOSPITAL_APPLY_ENABLED=1`, `DB_RW_USER` / `DB_RW_PASSWORD`, `ADMIN_UPLOAD_ROOT`; restart the service. Take a dump of the tables above before the first
real registration. Turning it off again: set the switch to `0` (the rows and files made so far stay).

## 15. Admin accounts (`ADMIN_ADMINS_ENABLED`, optional)

`GET /v1/admins`, `/v1/admins/options`, `/v1/admins/:id` (read-only in this step). A super admin sees every account; a hospital
role sees and opens only its own (another id answers 403). The password hash and the session counter are never selected or returned.
Independent of the hospital switches. Anything but `1` = off: the routes answer 404 and `/v1/me` reports `admins=false`.

### 15.1 Database (run as the DB admin; `<DB_NAME>` = schema from section 1)
First the column that records when an account was switched off (an account may be deleted only after 1 day of being inactive). Run once; safe to run again. The old PHP page does not write this column: an account it switches off keeps `deactivated_at` NULL, which the panel reads as "the waiting time has not started":
```sql
ALTER TABLE gangnamunni_admins ADD COLUMN IF NOT EXISTS deactivated_at DATETIME NULL DEFAULT NULL;
-- accounts that are off right now start their waiting day today
UPDATE gangnamunni_admins SET deactivated_at = NOW() WHERE is_active = 0 AND deactivated_at IS NULL;
```
Then the read grants. They add up to the ones of section 1 (same read-only user). No password, session counter or other secret column is granted:
```sql
GRANT SELECT (username, name, email, phone, role, hospital_id, hospital_name, last_login_at, permissions, deactivated_at) ON <DB_NAME>.gangnamunni_admins TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (username, name, email, phone, role, hospital_id, hospital_name, last_login_at, permissions, deactivated_at) ON <DB_NAME>.gangnamunni_admins TO 'sb_admin_ro'@'localhost';
```
Also needed, and normally given by section 1: `GRANT SELECT (id, is_active) ON <DB_NAME>.gangnamunni_admins` (and `g5_mb_id` once the SSO migration has added that column). Without `g5_mb_id` the page works, it just shows no member links.
The hospital drop-down reads `id, name` of `gangnamunni_hospitals`, already granted in section 1 (hospital pages).

### 15.2 Env and turning it on
`ADMIN_ADMINS_ENABLED=1` in `.env`, then `systemctl restart seoulbeauty-admin-api`. Without the migration the page still works, but no account counts as "inactive long enough" to delete.

### 15.3 Editing accounts (`ADMIN_ADMINS_WRITE_ENABLED`, optional)
- `POST /v1/admins` add an account (login name `[A-Za-z0-9_]{3,30}`, password of at least 8 characters, name, optional email / phone, role `super_admin` or `hospital_admin`, a hospital for a hospital admin, notifications). Super admin only. The email must not belong to another admin, member (`g5_member`) or user (`users`). The password is stored as a bcrypt hash with the `$2y$` prefix (what PHP `password_hash` writes, so the old login accepts it). Every active admin who receives "new admin" gets one notice row; no push.
- `PATCH /v1/admins/:id` name, phone, notification choice, email and password (`newPassword`, at least 8 characters) — a super admin for any account, every other role for its own only; a super admin may also change role and hospital (nobody their own role; the last active super admin cannot be demoted). A role that is not a super admin must send `currentPassword` to change its own email or password (checked before anything else: `400` missing, `403 wrong_password`; 5 wrong answers lock that account's check for 15 minutes: `429 too_many_attempts` with `retryAfterSeconds`; the counter lives in the process and resets on restart). A new password ends every session of that account (`cred_epoch + 1`, same statement; the old PHP login compares `cred_epoch` on each request, this API does not read it) and the answer says `passwordChanged`. The email follows the same uniqueness rule as add (the account's own email is not a clash). The audit log line names the fields, never their values.
- `POST /v1/admins/:id/active` (`{"active":true|false}`, super admin only). Switching off records the moment (`deactivated_at`), switching on clears it; nobody changes their own state; the last active super admin cannot be switched off.
- `DELETE /v1/admins/:id` (`{"confirm":"<login name>"}`, super admin only): only an account that has been inactive for 1 day (24 hours) (`409 still_active`, `no_inactive_record`, `not_inactive_long_enough`), never your own, and the login name must be typed exactly.
Every write locks the account and the active super admins in one transaction. Needs `ADMIN_ADMINS_ENABLED=1` as well; anything but `1` = off (404). `GET /v1/me` reports it as `features.adminsWrite`; the page shows the switch, edit and delete controls only then.

Grants (run as the DB admin; the same two hosts as the read user, shown for `127.0.0.1`: repeat each line for `'localhost'`):
```sql
-- read user: the three places an email can already be used (add checks them)
GRANT SELECT (email) ON <DB_NAME>.users TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (mb_email) ON <DB_NAME>.g5_member TO 'sb_admin_ro'@'127.0.0.1';
-- write user
GRANT SELECT (id, username, email, password, cred_epoch, role, is_active, hospital_id, permissions, deactivated_at) ON <DB_NAME>.gangnamunni_admins TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (name, phone, email, password, cred_epoch, permissions, role, hospital_id, hospital_name, is_active, deactivated_at) ON <DB_NAME>.gangnamunni_admins TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (username, password, name, email, phone, role, hospital_id, hospital_name, parent_admin_id, permissions) ON <DB_NAME>.gangnamunni_admins TO 'sb_admin_rw'@'127.0.0.1';
GRANT DELETE ON <DB_NAME>.gangnamunni_admins TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (admin_id, type, title, message, link, icon) ON <DB_NAME>.gangnamunni_notifications TO 'sb_admin_rw'@'127.0.0.1';
```
`DELETE` cannot be limited to columns: it is the only table-wide right of this user. If the site has no `users` or `g5_member` table, skip that grant (a missing table counts as "nobody there"; a refused grant answers `503 write_unavailable`).
`ADMIN_ADMINS_WRITE_ENABLED=1` in `.env`, then `systemctl restart seoulbeauty-admin-api`. Without the grants the routes answer `503 write_unavailable`.
Note: the old PHP page flips `is_active` without touching `deactivated_at`. An account it switches off keeps an older mark (or none), so switch accounts off in the new panel only.
SSO (`g5_mb_id`) is not editable here: the column does not exist on this site yet.

## 16. Price units (`ADMIN_TREATMENT_UNITS_ENABLED`, optional; super admin only)
The PHP page `admin_treatment_units.php` (table `gangnamunni_treatment_units`: the units of the price forms, e.g. Box, cc, ml, 시린지). Independent of the other switches. Hospital roles and anyone not
signed in get 403 / 401; with the switch off every route answers `404 {"error":"not_found"}` and `/v1/me` reports `"treatmentUnits":false`. The PHP `admin` role keeps using the PHP page.
This API never creates the table (the PHP page does it with `sql/treatment_units.sql` on every visit): without it the list answers `{"tableReady":false,"items":[]}` and a write answers
`503 {"error":"table_missing"}`. The table normally exists already because the price forms read it.

Routes (guard order for writes: write switch -> CSRF -> signed in -> super admin):
- `GET /v1/treatment-units` -> `{"tableReady":true,"items":[{"id","name"}]}`; active units only, Latin letters and digits first (case-insensitive), then Korean (same order as the PHP page).
- `POST /v1/treatment-units` `{"name"}` -> `{"unit":{"id","name"},"status":"created"|"restored"|"exists"}`. The name is trimmed, 1-20 characters, no control characters (`400 invalid_field`
  with `field:"name"` and `reason` `required|too_long|bad_format`). The key ignores case, so `box` finds `Box`: a removed unit is switched on again (`restored`, the stored spelling is kept), an active one changes nothing (`exists`).
- `DELETE /v1/treatment-units/:id` -> `{"ok":true}`. Soft delete (`is_active=0`) like the PHP page: prices that already carry the unit keep it. Repeating it is fine; an unknown id answers `404`.

Needs the write switch `ADMIN_TREATMENT_UNITS_WRITE=1` (also `ADMIN_TREATMENT_UNITS_ENABLED=1` and `DB_RW_*`) for the two writing routes. GRANTs (run as the DB admin; `<DB_NAME>` = schema from step 1):
```sql
-- read-only user: already granted for the price forms (section 10.4); needed for the list
GRANT SELECT (id, name, is_active) ON <DB_NAME>.gangnamunni_treatment_units TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, name, is_active) ON <DB_NAME>.gangnamunni_treatment_units TO 'sb_admin_ro'@'localhost';
-- write user
GRANT SELECT (id, name, is_active), INSERT (name, is_active), UPDATE (is_active) ON <DB_NAME>.gangnamunni_treatment_units TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, name, is_active), INSERT (name, is_active), UPDATE (is_active) ON <DB_NAME>.gangnamunni_treatment_units TO 'sb_admin_rw'@'localhost';
```
```
ADMIN_TREATMENT_UNITS_ENABLED=1
ADMIN_TREATMENT_UNITS_WRITE=1
```
Then `sudo systemctl restart seoulbeauty-admin-api`. A refused grant shows as `503 {"error":"write_unavailable"}` on writes (reason in the journal) and as `tableReady:false` on the list.
Turning it off: `ADMIN_TREATMENT_UNITS_WRITE=0` (the page becomes read-only) or `ADMIN_TREATMENT_UNITS_ENABLED=0` (all routes 404), then restart.

## 17. Bookings (`ADMIN_BOOKINGS_ENABLED`, optional; super admin and hospital accounts)
`/v1/bookings` (list, detail, hospital filter, Excel by visit day) and, with the write switch, the doctor assignment and the status flow
(pending → checking → confirmed → completed, cancel while checking). A super admin sees every hospital, a hospital account only its own.
A confirmation also opens the payment order (once per booking) and the member gets an in-app notice and a push after the change is saved.
Points are not connected: `ADMIN_BOOKINGS_POINTS` is reserved and does nothing yet.

17.1 Check the three columns the status flow writes exist (empty result = missing; ask before altering the schema):
```sql
SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '<DB_NAME>' AND TABLE_NAME = 'gangnamunni_bookings'
  AND COLUMN_NAME IN ('cancelled_by', 'confirmed_at', 'expire_notice_seen_at', 'cancelled_at', 'cancel_reason');
```
17.2 GRANTs (run as the DB admin; repeat each line for `'127.0.0.1'` and `'localhost'`):
```sql
-- read-only user
GRANT SELECT (id, hospital_id, hospital_name, name, phone, treatment, booking_date, booking_time, status, doctor_id, doctor_name, memo, cancel_reason, photo_urls, price, mb_id, created_at) ON <DB_NAME>.gangnamunni_bookings TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, booking_id, question, answer) ON <DB_NAME>.gangnamunni_booking_answers TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, name, hospital_id, status) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, name) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (mb_id, mb_hp, mb_datetime) ON <DB_NAME>.g5_member TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (mb_id, pt_token, pt_platform, pt_device_info, pt_use) ON <DB_NAME>.g5_push_tokens TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (member_id, noti_booking) ON <DB_NAME>.gangnamunni_notification_settings TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (setting_key, setting_value) ON <DB_NAME>.gangnamunni_theme_settings TO 'sb_admin_ro'@'127.0.0.1';
-- write user
GRANT SELECT (id, hospital_id, hospital_name, name, phone, treatment, status, doctor_id, doctor_name, price, mb_id), UPDATE (status, doctor_id, doctor_name, cancel_reason, cancelled_at, cancelled_by, confirmed_at, expire_notice_seen_at) ON <DB_NAME>.gangnamunni_bookings TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, name, hospital_id, status) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, payment_type, booking_id, status), INSERT (payment_type, funds_recipient, booking_id, hospital_id, hospital_name, patient_mb_id, patient_name, patient_phone, treatment_name, amount, currency, merchant_uid, status) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (setting_key, setting_value) ON <DB_NAME>.gangnamunni_settings TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (member_id, type, title, message, link, icon) ON <DB_NAME>.gangnamunni_user_notifications TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (admin_id, admin_username, target_type, target_count, notification_type, title, message, link, resolved_count, in_app_count, push_sent, push_failed, push_skipped, push_error, push_details) ON <DB_NAME>.gangnamunni_notification_sends TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (pt_token), UPDATE (pt_use) ON <DB_NAME>.g5_push_tokens TO 'sb_admin_rw'@'127.0.0.1';
```
17.3 Push needs the Firebase service account file readable only by the service user (never inside the repo), then in `seoulbeauty-admin-api/.env`:
```
ADMIN_BOOKINGS_ENABLED=1
ADMIN_BOOKINGS_WRITE=1
FCM_SERVICE_ACCOUNT_PATH=/etc/seoulbeauty/fcm-service-account.json
FCM_EXPECTED_PROJECT_ID=hospital-8425b
PHP_PUBLIC_SITE_URL=https://seoulbeauty.app/
```
Without `FCM_SERVICE_ACCOUNT_PATH` the in-app notice is still created and the push is skipped. Then `sudo systemctl restart seoulbeauty-admin-api`.
A missing column from 17.1 also shows as `write_unavailable`. If the journal says `payment order not created` after a confirmation, the booking is confirmed but has no payment order (as in the PHP page): check its price and create the order by hand. A booking without a member id is matched to the newest member with the same phone number (as in PHP). A refused grant shows as `503 {"error":"write_unavailable"}` on writes and `503 {"error":"bookings_unavailable"}` on reads (reason in the journal).
Turning it off: `ADMIN_BOOKINGS_WRITE=0` (read-only) or `ADMIN_BOOKINGS_ENABLED=0` (all routes 404), then restart.

## 18. Booking payments and revenue (`ADMIN_PAYMENTS_ENABLED`, optional, read only; super admin and hospital accounts)
`/v1/payments` (list of the ledger with the totals of the dates), `/v1/payments/by-hospital`, `/v1/payments/by-treatment`, `/v1/payments/hospitals` and
`/v1/payments/compare` (this week against last week day by day, this month against last month as a running total; `metric=received|recognized|held`).
Nothing here writes and no payment gateway is called; refunds and gateway syncs stay in the PHP admin (`admin_booking_payments.php`).
A super admin sees every hospital, a hospital account only its own (the same scope as the booking page, applied in every query).
Counted: booking payments to hospitals with status `paid`, `partially_refunded`, `refunded`, never the sandbox providers (`mock_booking_payment`, `inicis_test`).
Dates are the payment day (`paid_at`, KST); a week runs Monday to Sunday.
Money is split by the booking: received (what was paid) - refunded = recognized (booking `completed`) + held (treatment not done yet, the hospital may not take it out).
The list is narrowed by hospital, payment dates and the patient's name only (no status, phone, treatment or hospital-name search); the phone number of a patient is masked in every answer.
A date range of a year or more is refused (400 `range_too_long`). The hospital and treatment tables show at most 500 rows and answer `truncated: true` when more existed.
`held` money whose booking is cancelled, refunded or a no-show is also reported as `stuck` (per payment: `needsAttention`): no treatment will release it, it needs a refund or a decision in the PHP admin.

18.1 GRANTs (run as the DB admin; repeat each line for `'127.0.0.1'` and `'localhost'`). The read-only user already reads the booking columns of 17.2 (`id`, `status`, `booking_date`, `booking_time`):
```sql
GRANT SELECT (id, payment_type, funds_recipient, booking_id, hospital_id, hospital_name, patient_name, patient_phone, treatment_name, amount, refunded_amount, provider, status, paid_at, refunded_at) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_ro'@'127.0.0.1';
```
18.2 `seoulbeauty-admin-api/.env` (needs `ADMIN_PANEL_ENABLED=1`; hospital accounts also need `ADMIN_HOSPITALS_ENABLED=1`):
```
ADMIN_PAYMENTS_ENABLED=1
```
Then `sudo systemctl restart seoulbeauty-admin-api`. Missing table or column: the routes answer 503 `payments_unavailable`.
Turning it off: `ADMIN_PAYMENTS_ENABLED=0`, then restart (all routes 404).

### 18.3 Payment method, fee and settlement (`ADMIN_PAYMENTS_FEES`, optional; needs 18)
With the switch on, `/v1/payments` also answers per payment the method (card / bank transfer), the fee rate and fee stored with the payment, the settlement
(`(paid by card/transfer - fee) + points used`, a full refund is 0) and its state; `methods` (per method), `weeks` (per payout Sunday) and `orderedHeld` (booked, payment awaited).
`/v1/payments/board?period=week|month` (the three boxes: held / counted / received; a week runs Monday to Sunday) and
`/v1/payments/section?stage=held|counted|received` (the bookings of one box, with the treatment date and time; without dates the whole current month).
`/v1/payments/fee-rates` (GET for every admin who may see payments; POST and DELETE for a super admin only): a rate in basis points (143 = 1.43%) per method with a start date.
A new rate must start after the method's latest start and from tomorrow on; only a rate that is not in force yet can be deleted. The fee of a payment is fixed when it is paid, so a later rate change never touches it.
Held = treatment booked, payment awaited (no fee). Counted = paid, settlement calculated, not paid out yet. Received = paid out on the Sunday after the treatment (booking `completed`).
Payments made before the columns exist show no fee or settlement ("—"); nothing is guessed. Everything is off and answers 404 unless the switch is `1`.

18.3.1 SQL, in this order (run as the DB admin; the files are in `hairwang_full/gangnamunni/sql/`): `platform_payments_pay_method.sql`, `payment_fee_rates.sql`, `platform_payments_fee.sql`.
18.3.2 GRANTs (repeat each line for `'127.0.0.1'` and `'localhost'`):
```sql
GRANT SELECT (pay_method, fee_rate_bp, fee_amount) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT ON <DB_NAME>.gangnamunni_payment_fee_rates TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT, INSERT, DELETE ON <DB_NAME>.gangnamunni_payment_fee_rates TO 'sb_admin_rw'@'127.0.0.1';
```
Points: the settlement adds the points that covered part of a price (`points_used`). That column exists only after the points schema (`points_v2.sql`) is deployed, so it is read only with `ADMIN_PAYMENTS_POINTS=1`
(then also `GRANT SELECT (points_used) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_ro'@'127.0.0.1';`). Off = points count as 0.
The payment return (Nest `DB_PAY_USER`) also needs `SELECT` on `gangnamunni_payment_fee_rates` and the right to write the three new payment columns.
18.3.3 Enter the first rates (card, bank transfer) in the admin screen `/admin-next/payments/fee-rates` before turning the recording on. Then in `seoulbeauty-admin-api/.env`:
```
ADMIN_PAYMENTS_FEES=1
```
and in the payment return (`seoulbeauty-api`) `PAYMENTS_RECORD_PAY_METHOD=1` and `PAYMENTS_RECORD_FEE=1` (the PHP page uses `G5_PAYMENTS_RECORD_PAY_METHOD`, `G5_PAYMENTS_RECORD_FEE`, `G5_PAYMENT_FEES_ADMIN`). Then `sudo systemctl restart seoulbeauty-admin-api`.
Turning it off: `ADMIN_PAYMENTS_FEES=0`, restart (the new routes answer 404, the old answers are unchanged).

## 19. Hospital display order (`ADMIN_HOSPITALS_ORDER`, optional; super admin only)

Adds the "순서 변경" button to `/admin-next/hospitals`. It opens a screen with every active, approved hospital in the order the app shows them
(`display_order` ascending, hospitals without a place last by id); a hospital is moved by dragging, with the keyboard, to the top, or by typing a place.
Each move is saved at once and renumbers every active hospital 1..N in one transaction; the first move therefore fixes the order the app already shows, so nothing jumps.
Needs `ADMIN_HOSPITALS_ENABLED=1` and `ADMIN_HOSPITALS_WRITE=1`, and the grants of sections 11 and 12 (the write user also needs `SELECT (id)`).

Database (as root, once; `display_order` already exists because the app reads it):

```sql
GRANT SELECT (display_order) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (display_order) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_ro'@'localhost';
GRANT SELECT (status, is_active, display_order), UPDATE (display_order) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (status, is_active, display_order), UPDATE (display_order) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'localhost';
FLUSH PRIVILEGES;
```

`seoulbeauty-admin-api/.env`: `ADMIN_HOSPITALS_ORDER=1`, then `sudo systemctl restart seoulbeauty-admin-api`.
Rollback: `ADMIN_HOSPITALS_ORDER=0` + restart (the button disappears and both order routes answer 404; stored values stay as they are).

## 20. Doctors pages (`ADMIN_DOCTORS_ENABLED`, optional; super admin and hospital accounts)

`GET /v1/doctors` (list with hospital filter, search, paging), `/v1/doctors/:id`, `/v1/doctors/hospitals` and `/v1/doctors/treatment-options?hospitalId=` for the new doctors screen.
Read only: a hospital account sees only the doctors of its own hospital (any other id: 404). The list is in the order the app shows doctors (placed ones first, then by name).
Needs `ADMIN_DOCTORS_ENABLED=1` (hospital accounts also `ADMIN_HOSPITALS_ENABLED=1`). Nothing is written.

Database (as root; the read-only user, add only what is missing in `SHOW GRANTS FOR 'sb_admin_ro'@'127.0.0.1'`):

```sql
GRANT SELECT (id, hospital_id, name, specialty, experience, qualification, bio, photo_url, status) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_ro'@'127.0.0.1';
-- only when `SHOW COLUMNS FROM gangnamunni_doctors LIKE 'display_order'` shows the column (a GRANT on a missing column fails as a whole):
GRANT SELECT (display_order) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (doctor_id, hospital_id, treatment_id, is_active) ON <DB_NAME>.gangnamunni_doctor_treatments TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (hospital_id, treatment_id, is_active, status) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, name, category_id) ON <DB_NAME>.gangnamunni_treatment_master TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, name, sort_order) ON <DB_NAME>.gangnamunni_treatment_categories TO 'sb_admin_ro'@'127.0.0.1';
-- the same for 'sb_admin_ro'@'localhost'
FLUSH PRIVILEGES;
```

`display_order` of the doctors table is added by the old admin page the first time it is opened; until it exists the list is ordered by name and the second `display_order` GRANT must wait. The hospital filter offers approved hospitals only.
`seoulbeauty-admin-api/.env`: `ADMIN_DOCTORS_ENABLED=1`, then `sudo systemctl restart seoulbeauty-admin-api`. Rollback: `ADMIN_DOCTORS_ENABLED=0` + restart.

## 21. Editing and retiring doctors (`ADMIN_DOCTORS_WRITE`, optional)

`PATCH /v1/doctors/:id` (multipart: `doctor` = JSON text with name, hospitalId, specialty, qualification, treatmentIds; `photo` = optional picture, 5 MB, jpg/png/webp) and `POST /v1/doctors/:id/retire` (JSON, empty body).
Edit: the doctor row, the treatments the doctor is assigned to (only treatments the hospital offers right now; the rest is ignored and every assignment not in the list is switched off) and the photo (the old file is removed after the commit) change in one transaction. Moving a doctor to another hospital puts the doctor last there (display order cleared) and re-assigns the treatments there. A retired doctor can not be edited (409 `doctor_resigned`). A hospital account works only on its own hospital's doctors and can not move one away (404).
Retire: status `resigned`, display order cleared, every assignment switched off; repeating it changes nothing. Nothing is deleted.
Needs `ADMIN_DOCTORS_ENABLED=1` and `ADMIN_DOCTORS_WRITE=1`; the photo also needs `ADMIN_UPLOAD_ROOT` (same folder as section 11). The write pool (`DB_RW_USER`) must be set, otherwise 503 `write_unavailable`.

Database (as root; the write user, add only what is missing in `SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1'`):

```sql
GRANT SELECT (id, hospital_id, specialty, photo_url, status), UPDATE (name, hospital_id, specialty, qualification, photo_url, status, updated_at) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'127.0.0.1';
-- only when the column exists (see section 20):
GRANT UPDATE (display_order) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (doctor_id, treatment_id, is_active), INSERT (doctor_id, hospital_id, treatment_id, case_count, speciality_level, is_active), UPDATE (hospital_id, is_active) ON <DB_NAME>.gangnamunni_doctor_treatments TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (hospital_id, treatment_id, is_active, status) ON <DB_NAME>.gangnamunni_hospital_treatments TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, status) ON <DB_NAME>.gangnamunni_hospitals TO 'sb_admin_rw'@'127.0.0.1';
-- the same for 'sb_admin_rw'@'localhost'
FLUSH PRIVILEGES;
```

`seoulbeauty-admin-api/.env`: `ADMIN_DOCTORS_WRITE=1`, then `sudo systemctl restart seoulbeauty-admin-api`. Rollback: `ADMIN_DOCTORS_WRITE=0` + restart.

Level, cases and certification of each assigned treatment (list card counts, detail table, `treatments` in the edit body): these columns are granted per column, so add them too (without the read grant `GET /v1/doctors` and `GET /v1/doctors/:id` fail; without the write grant a save with `treatments` answers 503 `write_unavailable`). The `certification` column must exist (`SHOW COLUMNS FROM <DB_NAME>.gangnamunni_doctor_treatments LIKE 'certification'`).

```sql
GRANT SELECT (speciality_level, case_count, certification) ON <DB_NAME>.gangnamunni_doctor_treatments TO 'sb_admin_ro'@'127.0.0.1';
GRANT INSERT (certification), UPDATE (case_count, speciality_level, certification, updated_at) ON <DB_NAME>.gangnamunni_doctor_treatments TO 'sb_admin_rw'@'127.0.0.1';
-- the same for 'sb_admin_ro'@'localhost' and 'sb_admin_rw'@'localhost'
FLUSH PRIVILEGES;
```

Order after `git pull`: grants first, then rebuild and restart `seoulbeauty-admin-api`, then rebuild `seoulbeauty-admin-web` (section "After each `git pull`" of its deploy README).

## 22. Doctor display order and permanent delete (`ADMIN_DOCTORS_ORDER`, `ADMIN_DOCTORS_DELETE`, optional)

- `GET /v1/doctors/order?hospitalId=` (read, needs `ADMIN_DOCTORS_ENABLED`): the active doctors of one hospital in the app's order, positions 1..N; `orderable:false` while the `display_order` column does not exist yet.
- `POST /v1/doctors/:id/order` (`ADMIN_DOCTORS_ORDER=1`; JSON `{"direction":"up"|"down"}` or `{"position":n}`): moves one active doctor and renumbers the active doctors of that hospital 1..N in one transaction (locked, so two moves can not interleave). A hospital account moves only inside its own hospital. A retired doctor: 409 `doctor_resigned`; no column yet: 409 `order_unavailable`.
- `POST /v1/doctors/:id/delete` (`ADMIN_DOCTORS_DELETE=1`; JSON `{"confirm":true}`; **super admin only**, cannot be undone): only a RETIRED doctor (else 409 `not_resigned`). Bookings and reviews keep their rows, only their `doctor_id` is cleared; assignments and schedules are deleted with the doctor; the picture file is removed after the commit when this panel or the old page wrote it. If another table still points at the doctor the database refuses and nothing changes (409 `has_references`).
All three need `ADMIN_DOCTORS_WRITE=1`.

Database (as root; the write user, add only what is missing in `SHOW GRANTS FOR 'sb_admin_rw'@'127.0.0.1'`; only for the switches you turn on):

```sql
-- order (the column must exist, see section 20)
GRANT SELECT (name, hospital_id, status, display_order), UPDATE (display_order) ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'127.0.0.1';
-- delete
GRANT SELECT (name, hospital_id, status, photo_url), DELETE ON <DB_NAME>.gangnamunni_doctors TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (doctor_id), UPDATE (doctor_id) ON <DB_NAME>.gangnamunni_bookings TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (doctor_id), UPDATE (doctor_id) ON <DB_NAME>.hospital_reviews TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (doctor_id), DELETE ON <DB_NAME>.gangnamunni_doctor_treatments TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (doctor_id), DELETE ON <DB_NAME>.gangnamunni_doctor_schedules TO 'sb_admin_rw'@'127.0.0.1';
-- the same for 'sb_admin_rw'@'localhost'
FLUSH PRIVILEGES;
```

`DELETE` is a table-level privilege (it can not be limited to columns): grant it only when you really turn the permanent delete on, and revoke it with `ADMIN_DOCTORS_DELETE=0` if it is not used.
`seoulbeauty-admin-api/.env`: `ADMIN_DOCTORS_ORDER=1` and/or `ADMIN_DOCTORS_DELETE=1`, then `sudo systemctl restart seoulbeauty-admin-api`. Rollback: set it to 0 + restart.

## 23. Consultation requests (`ADMIN_CONSULTATIONS_ENABLED`, optional, read only so far; super admin and hospital accounts)
`/v1/consultations` (list with counts, search, paging), `/v1/consultations/hospitals` (hospital filter), `/v1/consultations/:id` (the whole application: full text, the hospital's message, the progress record) and `/v1/consultations/:id/photos/:index` (an attached photo). A super admin sees every hospital, a hospital account only its own
(and, like the other pages, only while `ADMIN_HOSPITALS_ENABLED=1`; an account without a bound hospital sees nothing). Newest first, 20 per page; the counts follow the hospital choice only.
A photo is sent only for an application inside the admin's scope, only as a plain image file of that application's own folder `uploads/consultations/<id>/` (anything else is 404), never cached. Changing the status and the hospital message come in a later step with its own switch.

23.1 GRANTs (run as the DB admin; repeat each line for `'127.0.0.1'` and `'localhost'`; `gangnamunni_hospitals (id, name)` is already granted in section 17):
```sql
GRANT SELECT (id, hospital_id, hospital_name, consultation_type, name, phone, message, photo_urls, status, created_at) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, hospital_id, hospital_name, consultation_type, name, phone, message, photo_urls, status, created_at) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_ro'@'localhost';
-- detail: the hospital's message and the progress record (migration consultation_history_20261023.sql); without these the detail still answers, with `historySupported: false`
GRANT SELECT (hospital_message, hospital_message_at, callback_slot) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (hospital_message, hospital_message_at, callback_slot) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, consultation_id, kind, created_at) ON <DB_NAME>.gangnamunni_consultation_events TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, consultation_id, kind, created_at) ON <DB_NAME>.gangnamunni_consultation_events TO 'sb_admin_ro'@'localhost';
```
23.2 In `seoulbeauty-admin-api/.env`: `ADMIN_CONSULTATIONS_ENABLED=1`, then `sudo systemctl restart seoulbeauty-admin-api`. Without the GRANT the routes answer 503 `consultations_unavailable`.
Photos need `ADMIN_UPLOAD_ROOT` (the same absolute path of the PHP `gangnamunni` directory as in the other upload sections; reading only needs read and enter rights on it). Without it a photo answers 503 `uploads_unavailable`.
Turning it off: `ADMIN_CONSULTATIONS_ENABLED=0` (all routes 404), then restart.

23.3 Changing the status and writing the hospital's message (`ADMIN_CONSULTATIONS_WRITE=1`, needs `ADMIN_CONSULTATIONS_ENABLED=1`; default off, then the two POST routes answer 404).
`POST /v1/consultations/:id/status` (`approved`, `completed`, `rejected`, `contact_failed`; the same status again changes nothing) and `POST /v1/consultations/:id/message` (up to 1000 characters; empty clears it).
Only a super admin and hospital accounts reach them (a viewer gets 403), the CSRF header is required, and a hospital account works on its own hospital only (anything else is 404). Statements run in one transaction.
What the PHP page also did is done here too:
- `completed` and `contact_failed` add a line to the progress record `gangnamunni_consultation_events` (`completed` / `no_answer`) in the same transaction. A database without that table (or without the right to write it) only skips the line, the status is still saved.
- After the change is saved, `approved` and `contact_failed` tell the member (the member of the request; a request made without signing in is matched by phone number): an in-app notice and a push. `completed` and `rejected` tell nobody. This runs in the background; a failure is only logged and never fails the admin's request.
```sql
-- write user: the status, the message, the progress record
GRANT SELECT (id, hospital_id, status), UPDATE (status, hospital_message, hospital_message_at) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, hospital_id, status), UPDATE (status, hospital_message, hospital_message_at) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_rw'@'localhost';
GRANT INSERT (consultation_id, kind) ON <DB_NAME>.gangnamunni_consultation_events TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT (consultation_id, kind) ON <DB_NAME>.gangnamunni_consultation_events TO 'sb_admin_rw'@'localhost';
-- read-only user: who to tell (the member id is only read by the notice sender, never sent to the page)
GRANT SELECT (mb_id) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (mb_id) ON <DB_NAME>.gangnamunni_consultations TO 'sb_admin_ro'@'localhost';
```
The notices use the same rights and Firebase file as the booking notices: the `g5_member`, `g5_push_tokens`, `gangnamunni_theme_settings` and `gangnamunni_user_notifications` GRANTs of section 17.2 and `FCM_SERVICE_ACCOUNT_PATH` of 17.3 (and `PHP_PUBLIC_SITE_URL` for the links). Without them the status still changes and only the notice is missing (see the log).
Without the first two GRANTs (or without the history columns, for the message) the answer is `503 {"error":"write_unavailable"}` and nothing changes.
Turning writing off: `ADMIN_CONSULTATIONS_WRITE=0`, then restart.

## 24. Refund of booking payments (`ADMIN_PAYMENTS_REFUND`, optional; needs 18)

Adds the refund to `/admin-next/payments`: the detail panel of a payment, a "전액 환불" button and the refund history. A refund is always the whole of what the
customer paid and has not got back (`amount - refunded_amount`; points that covered part of the price are not refunded). It is offered only for an Inicis payment
whose booking is already cancelled (cancel the booking first). A super admin refunds any hospital's payment, a hospital account only its own.
The refund asks Inicis (INIAPI) to cancel the card payment, then records it the way the PHP admin does under the same payment lock: the request row, the evidence
event, the payment row (`refunded`), the booking (`refunded`) and the audit row; the customer gets the "취소·환불" push. A refund whose answer from Inicis is unknown
(timeout, broken answer) stays `pending`, the screen says to check the Inicis console, and no further refund of that payment is accepted until it is settled by hand.
Routes: `GET /v1/payments/item/:id`, `GET /v1/payments/refunds?page=`, `POST /v1/payments/:id/refund` ({requestId: 64 hex, reason}).
24.1 Environment (`.env` of the admin API, see `.env.example`): `ADMIN_PAYMENTS_REFUND=1`, `INICIS_MID`, `INICIS_INIAPI_KEY`, `INICIS_CLIENT_IP`, `INICIS_MODE`
(`live` only on the live server). Needs the write user (section 6) and a restart.
24.2 GRANTs (repeat each line for `'127.0.0.1'` and `'localhost'`):
```sql
GRANT SELECT (id, booking_id, patient_name, patient_phone, treatment_name, hospital_name, paid_at, refunded_at, provider_transaction_id, merchant_uid) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, payment_id, booking_id, amount, actor_name, status, created_at) ON <DB_NAME>.gangnamunni_refund_audit TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (payment_id, status, reason, completed_at) ON <DB_NAME>.gangnamunni_payment_refund_requests TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, booking_id, hospital_id, hospital_name, patient_name, patient_phone, treatment_name, amount, refunded_amount, status, paid_at, refunded_at, provider,
  provider_transaction_id, merchant_uid, payment_type, funds_recipient, pay_method, points_used) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (refunded_amount, refunded_at, status) ON <DB_NAME>.gangnamunni_platform_payments TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (id, status, refund_status, booking_date, booking_time), UPDATE (status, refund_status, updated_at) ON <DB_NAME>.gangnamunni_bookings TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE (status, completed_at) ON <DB_NAME>.gangnamunni_payment_refund_requests TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT ON <DB_NAME>.gangnamunni_payment_events TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT ON <DB_NAME>.gangnamunni_refund_audit TO 'sb_admin_rw'@'127.0.0.1';
```
(drop `pay_method` / `points_used` from the lists while ADMIN_PAYMENTS_FEES / ADMIN_PAYMENTS_POINTS are off and the columns do not exist). The three tables are created by the PHP
admin (`admin_bookings.php`, `sql/booking_payment_previews.sql`, `sql/platform_payments_schema.sql`); they must exist before the switch is turned on.
24.3 First live test: refund one small real payment of a cancelled booking and check it in the Inicis console; if Inicis answers "already cancelled" with a code, add the
code to `ALREADY_CANCELLED_CODES` in `src/payments/payments.refund.logic.ts` (the PHP admin has the same open item). Bank-transfer payments are not refundable here yet.
Turning it off: `ADMIN_PAYMENTS_REFUND=0`, restart (the route answers 404, the panel hides the button).

## 25. Members (`ADMIN_MEMBERS_ENABLED`, optional, read only so far; super admin only)

`GET /v1/members` (search, status filter, sorting, paging), `/v1/members/summary` (counters) and `/v1/members/:id` (detail) for the new members screen. Hospital accounts get 403.
Search looks in login id, name, nick, e-mail and mobile number; status is `active`, `blocked` (not left, block date set) or `left` (leave date set); the sort keys are `joined`, `lastLogin`, `no`, `id`, `name`. There is no point column: the old `mb_point` is not the points the app uses (the ledger, see section 28). Block and leave dates are read in both stored formats (`YYYYMMDD` and `YYYY-MM-DD`). The password hash is never selected.
Needs `ADMIN_MEMBERS_ENABLED=1`. Nothing is written.

Database (as root; the read-only user, add only what is missing in `SHOW GRANTS FOR 'sb_admin_ro'@'127.0.0.1'`):

```sql
GRANT SELECT (mb_no, mb_id, mb_name, mb_nick, mb_email, mb_hp, mb_tel, mb_sex, mb_birth, mb_addr1, mb_addr2, mb_datetime, mb_today_login,
  mb_login_ip, mb_leave_date, mb_intercept_date, mb_sms, mb_mailling, mb_memo) ON <DB_NAME>.g5_member TO 'sb_admin_ro'@'127.0.0.1';
-- the same for 'sb_admin_ro'@'localhost'
FLUSH PRIVILEGES;
```

`seoulbeauty-admin-api/.env`: `ADMIN_MEMBERS_ENABLED=1`, then `sudo systemctl restart seoulbeauty-admin-api`. Rollback: `ADMIN_MEMBERS_ENABLED=0` + restart.

## 26. Changing a member's e-mail and the audit journal (`ADMIN_MEMBERS_WRITE`, optional; needs 25; super admin only)

`PATCH /v1/members/:id` with `{"email": "..."}`. The e-mail is the only field of a member that can be edited, and not for a member who has left (409 `member_left`); any other key answers 400 `invalid` (`not_allowed`). The e-mail is stored lower-cased; an address another member already has answers 409 `email_taken`; the same address as now changes nothing and writes no journal row. The address check and the update run in one transaction that locks only the member being changed; `g5_member.mb_email` has no unique key, so two super admins setting the same new address on two different members at the very same moment could both succeed (accepted: rare, manual action).
Every change is one transaction: the member row is locked, the e-mail updated and a row written to `gangnamunni_member_audit` (who: admin id, login name, role, IP; whom: member number and id; what: `email_change` with the old and new e-mail). If the journal row cannot be written the e-mail is not changed either. Without the write user, the table or the GRANTs the route answers 503 `write_unavailable`.
Needs the write user of section 6 and `ADMIN_MEMBERS_ENABLED=1`, `ADMIN_MEMBERS_WRITE=1`.

Database, as the DB admin, once (replace `hms_saas` by the database that holds `g5_member`; safe to run again). The write user gets `SELECT (mb_no, mb_id, mb_email, mb_leave_date)` and `UPDATE (mb_email)` on `g5_member` and `INSERT` on the journal, nothing else:

```sql
-- Audit journal of member changes (README section 26). Run as the DB admin, once. Safe to run again.
-- Replace hms_saas by the database that holds g5_member (the DB_NAME of the API .env).
CREATE TABLE IF NOT EXISTS hms_saas.gangnamunni_member_audit (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id       INT UNSIGNED NOT NULL,
  admin_username VARCHAR(191) NOT NULL DEFAULT '',
  admin_role     VARCHAR(50)  NOT NULL DEFAULT '',
  member_no      INT NOT NULL,
  member_id      VARCHAR(30)  NOT NULL DEFAULT '',
  action         VARCHAR(40)  NOT NULL,
  before_value   VARCHAR(255) NULL,
  after_value    VARCHAR(255) NULL,
  ip             VARCHAR(45)  NOT NULL DEFAULT '',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_member (member_no, created_at),
  KEY idx_admin (admin_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Write user: change the e-mail of a member and journal it. Nothing else of g5_member can be written.
GRANT SELECT (mb_no, mb_id, mb_email, mb_leave_date) ON hms_saas.g5_member TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (mb_email) ON hms_saas.g5_member TO 'sb_admin_rw'@'127.0.0.1';
GRANT INSERT ON hms_saas.gangnamunni_member_audit TO 'sb_admin_rw'@'127.0.0.1';
-- Read-only user: the history of a member.
GRANT SELECT (id, admin_id, admin_username, admin_role, member_no, member_id, action, before_value, after_value, ip, created_at) ON hms_saas.gangnamunni_member_audit TO 'sb_admin_ro'@'127.0.0.1';

GRANT SELECT (mb_no, mb_id, mb_email, mb_leave_date) ON hms_saas.g5_member TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (mb_email) ON hms_saas.g5_member TO 'sb_admin_rw'@'localhost';
GRANT INSERT ON hms_saas.gangnamunni_member_audit TO 'sb_admin_rw'@'localhost';
GRANT SELECT (id, admin_id, admin_username, admin_role, member_no, member_id, action, before_value, after_value, ip, created_at) ON hms_saas.gangnamunni_member_audit TO 'sb_admin_ro'@'localhost';

FLUSH PRIVILEGES;
```

`seoulbeauty-admin-api/.env`: `ADMIN_MEMBERS_WRITE=1`, then `sudo systemctl restart seoulbeauty-admin-api`. Rollback: `ADMIN_MEMBERS_WRITE=0` + restart (the journal stays).

## 27. Resetting a member's password (`ADMIN_MEMBERS_PASSWORD`, optional; needs 26; super admin only)

`POST /v1/members/:id/password` with `{"password": "..."}`. The super admin chooses the new password (9-20 printable ASCII characters with a letter, a digit and a special character, the same rule as the app) and tells it to the member by phone; the answer is `{"ok": true}` and never contains a password.
An active or a blocked member; one who has left answers 409 `member_left`. At most 3 resets of the same member within one hour, then 429 `too_many`. Any other key in the body answers 400 `invalid`.
One transaction: the member row is locked, the Gnuboard hash (`sha256:12000:...`, the one the site and the app read) replaces `mb_password`, the credential epoch moves (`mb_cred_epoch`, `mb_lost_certify` cleared) and the remembered / app tokens are deleted from `sso_tokens`, so every sign-in of the member ends and the new password is needed. A row `password_reset` (who, whom, IP; no password) goes to `gangnamunni_member_audit`. If any step fails nothing is changed (503). No other system (HMS) is touched.
Needs `ADMIN_MEMBERS_ENABLED=1` and `ADMIN_MEMBERS_PASSWORD=1`, the write user of section 6 and section 26 (audit table).

Database, as the DB admin, once (replace `hms_saas`; safe to run again). `DELETE` cannot be limited to columns: it is a table-wide right on `sso_tokens`, the only one this user gets here:

```sql
-- Password reset of members (README section 27). Run as the DB admin, once. Safe to run again. Needs section 26 (the audit table).
-- Replace hms_saas by the database that holds g5_member.
GRANT SELECT (mb_leave_date, mb_intercept_date) ON hms_saas.g5_member TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (mb_cred_epoch) ON hms_saas.g5_member TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (mb_password, mb_cred_epoch, mb_lost_certify) ON hms_saas.g5_member TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (member_no, action, created_at) ON hms_saas.gangnamunni_member_audit TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (g5_mb_id), DELETE ON hms_saas.sso_tokens TO 'sb_admin_rw'@'127.0.0.1';

GRANT SELECT (mb_leave_date, mb_intercept_date) ON hms_saas.g5_member TO 'sb_admin_rw'@'localhost';
GRANT SELECT (mb_cred_epoch) ON hms_saas.g5_member TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (mb_password, mb_cred_epoch, mb_lost_certify) ON hms_saas.g5_member TO 'sb_admin_rw'@'localhost';
GRANT SELECT (member_no, action, created_at) ON hms_saas.gangnamunni_member_audit TO 'sb_admin_rw'@'localhost';
GRANT SELECT (g5_mb_id), DELETE ON hms_saas.sso_tokens TO 'sb_admin_rw'@'localhost';

FLUSH PRIVILEGES;
```

`seoulbeauty-admin-api/.env`: `ADMIN_MEMBERS_PASSWORD=1`, then `sudo systemctl restart seoulbeauty-admin-api`. Rollback: `ADMIN_MEMBERS_PASSWORD=0` + restart.

## 28. Member activity (`ADMIN_MEMBERS_ACTIVITY`, optional; needs 25 and 26; read only; super admin only)

`GET /v1/members/:id/consultations`, `/bookings`, `/points`, `/history` (`?page=`, 20 rows per page, newest first). Nothing is written.
- consultations: the member's applications (date, hospital, type, status, first 300 characters of the message). Matched by `mb_id`, and also by phone number for ones made without signing in; a table without `mb_id` falls back to the phone number alone.
- bookings: when and at which hospital (booking date and time, treatment, price, status, cancel reason).
- points: the ledger balance (`gangnamunni_points`, the number the app shows), totals by kind, and every entry with its reason and reference (what the points were used for).
- history: what admins changed for the member (e-mail change, password reset). A password reset never carries a value.

Database, as the DB admin, once (replace `hms_saas`; safe to run again):

```sql
-- Member activity (README section 28). Run as the DB admin, once. Safe to run again.
GRANT SELECT (mb_no, mb_id, mb_hp) ON hms_saas.g5_member TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, hospital_id, hospital_name, consultation_type, message, status, created_at, phone) ON hms_saas.gangnamunni_consultations TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, hospital_id, hospital_name, treatment, price, booking_date, booking_time, status, cancel_reason, created_at, mb_id) ON hms_saas.gangnamunni_bookings TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, member_id, type, amount, balance, reason, reference_type, reference_id, expires_at, created_at) ON hms_saas.gangnamunni_points TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, admin_username, admin_role, member_no, action, before_value, after_value, ip, created_at) ON hms_saas.gangnamunni_member_audit TO 'sb_admin_ro'@'127.0.0.1';

GRANT SELECT (mb_no, mb_id, mb_hp) ON hms_saas.g5_member TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, hospital_id, hospital_name, consultation_type, message, status, created_at, phone) ON hms_saas.gangnamunni_consultations TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, hospital_id, hospital_name, treatment, price, booking_date, booking_time, status, cancel_reason, created_at, mb_id) ON hms_saas.gangnamunni_bookings TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, member_id, type, amount, balance, reason, reference_type, reference_id, expires_at, created_at) ON hms_saas.gangnamunni_points TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, admin_username, admin_role, member_no, action, before_value, after_value, ip, created_at) ON hms_saas.gangnamunni_member_audit TO 'sb_admin_ro'@'localhost';

FLUSH PRIVILEGES;
```

The consultations GRANT has no `mb_id`: new applications do not store it, so the API matches by phone number. If the table does have `mb_id`, add it to that GRANT to match by login id as well; without the right the API falls back to the phone number.
`seoulbeauty-admin-api/.env`: `ADMIN_MEMBERS_ACTIVITY=1`, then `sudo systemctl restart seoulbeauty-admin-api`. Rollback: `ADMIN_MEMBERS_ACTIVITY=0` + restart.

## Apache
Nothing to add for this API: it is not exposed by Apache. Browsers reach it only through the admin web app
(`/admin-next/api/*` is rewritten to `http://127.0.0.1:3300/*` by that app's server). Do not add CORS or a public proxy.

## Notes
- The signed-in admin is cached per cookie for at most 5 s, the summary for 10 s (in memory, per process).
- Each session lookup refreshes the PHP admin session like a page view does; while the dashboard is open and polling,
  the PHP admin login does not idle out.
- Hospital answers are not cached by the API: every request reads the database (3 statements for the list, up to 5 for a detail page).
- The image upload, the document upload, the add-doctors, the edit-doctor and the treatment image add routes are the exceptions: they require `Content-Type: multipart/form-data` (same marker header and Origin rule).
- Writing routes require `Content-Type: application/json`, the header `X-Requested-With: admin-next`, and an `Origin` (when sent) equal to the request host (`X-Forwarded-Host`, else `Host`); otherwise `403 {"error":"csrf"}`.
- Image and photo URLs are returned as stored: either absolute (`https://…`) or relative to the PHP admin directory
  (`uploads/hospitals/<id>/…`). Document files are not served by this API; the PHP admin (`hospital_document.php`) opens them (they can be uploaded, replaced and deleted here: section 11).
- Time windows use the connection time zone `+09:00` (KST), set by the API on every connection.

## 29. Member status: block, unblock, leave (`ADMIN_MEMBERS_STATUS`, optional; needs 26 and 27; super admin only)

`POST /v1/members/:id/status` with `{"status": "blocked" | "active" | "left"}`; no reason is asked. The answer is the member as it is now. Any other key answers 400 `invalid`.
Rules: an active member can be blocked or can leave; a blocked member can be made active again (unblock) but cannot leave (409 `member_blocked`: unblock first); a member who has left cannot be changed (409 `member_left`); asking for the state the member is already in changes nothing.
Leaving is soft: the row stays (the PHP admin deleted it for good), `mb_leave_date` gets today's date (Korea time, `YYYYMMDD`) and the member can no longer sign in.
One transaction: the member row is locked, `mb_intercept_date` (block, cleared on unblock) or `mb_leave_date` (leave) is set; blocking and leaving also move the credential epoch (`mb_cred_epoch`, `mb_lost_certify` cleared) and delete the member's rows of `sso_tokens`, so every sign-in ends. A row `block` / `unblock` / `leave` (before and after state, who, whom, IP) goes to `gangnamunni_member_audit`. If any step fails nothing is changed (503). No other system (HMS) is touched.
Needs `ADMIN_MEMBERS_ENABLED=1` and `ADMIN_MEMBERS_STATUS=1`, the write user of section 6 and sections 26 and 27 (audit table, `sso_tokens` right).

Database, as the DB admin, once (replace `hms_saas`; safe to run again):

```sql
-- Member status (README section 29). Run as the DB admin, once. Safe to run again. Needs sections 26 and 27.
GRANT SELECT (mb_cred_epoch) ON hms_saas.g5_member TO 'sb_admin_rw'@'127.0.0.1';
GRANT UPDATE (mb_intercept_date, mb_leave_date) ON hms_saas.g5_member TO 'sb_admin_rw'@'127.0.0.1';
GRANT SELECT (mb_cred_epoch) ON hms_saas.g5_member TO 'sb_admin_rw'@'localhost';
GRANT UPDATE (mb_intercept_date, mb_leave_date) ON hms_saas.g5_member TO 'sb_admin_rw'@'localhost';
FLUSH PRIVILEGES;
```

`seoulbeauty-admin-api/.env`: `ADMIN_MEMBERS_STATUS=1`, then `sudo systemctl restart seoulbeauty-admin-api`. Rollback: `ADMIN_MEMBERS_STATUS=0` + restart.

## 30. Members a member blocked, with the reasons (part of `ADMIN_MEMBERS_ACTIVITY`; read only; super admin only)

`GET /v1/members/:id/blocks` (`?page=`, 20 rows per page, newest block first): the members this member blocked in the app (`gangnamunni_blocked_users`, `blocker_id` = the member's login id). Each row has the blocked member's name and state (active / blocked / left; none when the member no longer exists, then the stored name is shown), the date and the block's own reason (the app stores none), and the reasons: the reports this member filed about that member (`gangnamunni_content_reports`, `reporter_id` = this member, `reported_user_id` = the blocked one): the content type, a text excerpt, the report reason (spam, harassment, ...), the free text, the handling status and the date. The newest 5 reports are listed per blocked member, with the total number. Nothing is written. The tab is on with the other activity tabs, so run the SQL below before restarting the API.

```sql
-- Blocked members and reports (README section 30). Run as the DB admin, once. Safe to run again.
GRANT SELECT (mb_no, mb_id, mb_nick, mb_name, mb_leave_date, mb_intercept_date) ON hms_saas.g5_member TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, blocker_id, blocked_id, blocked_name, reason, created_at) ON hms_saas.gangnamunni_blocked_users TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, reporter_id, reported_user_id, content_type, content_id, content_snippet, reason, reason_detail, status, created_at) ON hms_saas.gangnamunni_content_reports TO 'sb_admin_ro'@'127.0.0.1';

GRANT SELECT (mb_no, mb_id, mb_nick, mb_name, mb_leave_date, mb_intercept_date) ON hms_saas.g5_member TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, blocker_id, blocked_id, blocked_name, reason, created_at) ON hms_saas.gangnamunni_blocked_users TO 'sb_admin_ro'@'localhost';
GRANT SELECT (id, reporter_id, reported_user_id, content_type, content_id, content_snippet, reason, reason_detail, status, created_at) ON hms_saas.gangnamunni_content_reports TO 'sb_admin_ro'@'localhost';

FLUSH PRIVILEGES;
```

## 31. Reports about community posts (part of the content review page; read only; super admin only)

On `GET /v1/moderation/community` every post also carries `reportCount` (how many members reported it) and `reports` (the newest 5: who reported, the reason, the free text, the handling status, the date), read from `gangnamunni_content_reports` (`content_type = 'community'`, `content_id` = the post). `?reported=1` lists only the posts somebody reported. Nothing is written; handling a report stays in the PHP admin.
The reports are a side read: if the read-only user cannot read the table the posts are still listed, without report numbers (`reportCount` and `reports` are `null`, and the API log says why). A report names the reporter by the name stored at report time.

```sql
-- Reports about community posts (README section 31). Run as the DB admin, once. Safe to run again.
GRANT SELECT (id, content_type, content_id, reporter_name, reason, reason_detail, status, created_at) ON hms_saas.gangnamunni_content_reports TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (id, content_type, content_id, reporter_name, reason, reason_detail, status, created_at) ON hms_saas.gangnamunni_content_reports TO 'sb_admin_ro'@'localhost';
FLUSH PRIVILEGES;
```

## 32. Notifications to members: the page and its history (`ADMIN_NOTIFICATIONS_ENABLED`; read only; super admin only)

Part 1 of the notifications page: choosing who is written to and counting them (`POST /v1/notifications/preview`), the history of sends (`GET /v1/notifications/history`, `history/:id` with what happened to each recipient), the search of the pages a notification can open (`GET /v1/notifications/link-targets?q=&kind=page|hospital|event`), the push token health (`diagnostics`) and what the member API's 방문 전 알림 has sent (`automatic`). **Nothing is sent or written yet**: sending and planning come with `ADMIN_NOTIFICATIONS_SEND` and `ADMIN_NOTIFICATIONS_SCHEDULE` (later sections). `PHP admin_send_notification.php` keeps working as before.
Needs `ADMIN_PANEL_ENABLED=1`. Anything but `1` = off: every `/v1/notifications*` route answers 404 (the PHP session is not asked). Without the tables or GRANTs below the page still opens and says "not available" for the parts it cannot read (the history, the recipient list of a send, the visit counters); a preview then uses the 설정 defaults and says so (`settingsAvailable: false`).

Who counts as a recipient and who gets what (the member's 설정 page decides):

| Type | Switch | In-app row | Push |
|---|---|---|---|
| `booking` / `community` / `review` | 예약·결제 / 커뮤니티 / 포인트·후기 | only when the switch is on | only when the switch is on |
| `promotion`, `event` | 마케팅 푸시 | everybody | `마케팅 푸시` on; between 21:00 and 08:00 KST also `야간 푸시` on |
| `general`, `system`, `raffle` | none (a notice) | everybody | everybody |

- Members that left are never written to; a member without a 설정 row has the page defaults (notice switches on, marketing off). The old `noti_general`, `noti_event`, `noti_promo`, `noti_push` columns are not looked at (the member cannot change them any more).
- A link is not free text: only a fixed page of the member site or an id page (`/next/hospitals/N`, `/next/events/N`, `/next/community/N`, `/next/reviews/N`).
- Run once as the DB admin: `deploy/sql/notifications.sql` (two columns on the history table, the per-recipient table, the scheduled-sends table and the read-only GRANTs; MariaDB). It does not give the API user any write right.

```
ADMIN_NOTIFICATIONS_ENABLED=1
```
