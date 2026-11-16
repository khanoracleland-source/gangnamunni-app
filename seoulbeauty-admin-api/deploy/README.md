# seoulbeauty-admin-api — server deploy (Rocky Linux 9, systemd)

Read-only JSON API behind the admin web app (`seoulbeauty-admin-web`, `/admin-next`). It is a separate project from
`seoulbeauty-api`: own `package.json`, own port (`127.0.0.1:3300`), own systemd unit, own database user.

| Route | Answer |
|---|---|
| `GET /health` | `200 {"ok":true}` — no auth, works with the switch off |
| `GET /v1/me` | `200 {"admin":{"id","username","name","role","hospitalId","scope":"all"\|"own"},"features":{"hospitals":true\|false}}` |
| `GET /v1/dashboard/summary` | members / bookings (7 and 30 days, with the previous window) / consultations / catalog — `super_admin` only |
| `GET /v1/hospitals` | approved hospitals: counters + one page of the list. Query: `search`, `treatmentId`, `active` (`all`\|`1`\|`0`), `page`, `perPage` (10\|20\|50) |
| `GET /v1/hospitals/treatment-options` | treatments actively offered by the approved hospitals in scope, grouped by category (for the list filter) |
| `GET /v1/hospitals/:id` | one approved hospital: basic data, submitted documents (present or not, no file path), images, doctors, unreviewed changes |

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

## Apache
Nothing to add for this API: it is not exposed by Apache. Browsers reach it only through the admin web app
(`/admin-next/api/*` is rewritten to `http://127.0.0.1:3300/*` by that app's server). Do not add CORS or a public proxy.

## Notes
- The signed-in admin is cached per cookie for at most 5 s, the summary for 10 s (in memory, per process).
- Each session lookup refreshes the PHP admin session like a page view does; while the dashboard is open and polling,
  the PHP admin login does not idle out.
- Hospital answers are not cached by the API: every request reads the database (3 statements for the list, up to 5 for a detail page).
- Image and photo URLs are returned as stored: either absolute (`https://…`) or relative to the PHP admin directory
  (`uploads/hospitals/<id>/…`). Document files are not served by this API; the PHP admin (`hospital_document.php`) opens them.
- Time windows use the connection time zone `+09:00` (KST), set by the API on every connection.
