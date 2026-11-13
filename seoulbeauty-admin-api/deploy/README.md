# seoulbeauty-admin-api — server deploy (Rocky Linux 9, systemd)

Read-only JSON API behind the admin web app (`seoulbeauty-admin-web`, `/admin-next`). It is a separate project from
`seoulbeauty-api`: own `package.json`, own port (`127.0.0.1:3300`), own systemd unit, own database user.

| Route | Answer |
|---|---|
| `GET /health` | `200 {"ok":true}` — no auth, works with the switch off |
| `GET /v1/me` | `200 {"admin":{"id","username","name","role"}}` |
| `GET /v1/dashboard/summary` | members / bookings (7 and 30 days, with the previous window) / consultations / catalog |

Errors on `/v1/*`: `404 {"error":"not_found"}` (switch off, and also any unknown `/v1` path or wrong method; uncached), `401 {"error":"not_logged_in"}`, `403 {"error":"forbidden"}`
(signed in, but not `super_admin`), `503 {"error":"session_unavailable"}` (PHP could not be asked).

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
-- members: only the columns the counters need (no e-mail, phone or password hash)
GRANT SELECT (mb_datetime, mb_leave_date) ON <DB_NAME>.g5_member TO 'sb_admin_ro'@'127.0.0.1';
GRANT SELECT (mb_datetime, mb_leave_date) ON <DB_NAME>.g5_member TO 'sb_admin_ro'@'localhost';
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
A table that does not exist yet (e.g. `gangnamunni_platform_payments`) cannot be granted: skip that line; the API reports
its figures as 0. Any other database error (for example a missing grant) makes the summary fail with 500 and is logged.
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
# leave ADMIN_PANEL_ENABLED=0 until everything below is verified
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

Rollback: `ADMIN_PANEL_ENABLED=0` + restart (or `systemctl disable --now seoulbeauty-admin-api`). Nothing else on the
server depends on this service.

## Apache
Nothing to add for this API: it is not exposed by Apache. Browsers reach it only through the admin web app
(`/admin-next/api/*` is rewritten to `http://127.0.0.1:3300/*` by that app's server). Do not add CORS or a public proxy.

## Notes
- The signed-in admin is cached per cookie for at most 5 s, the summary for 10 s (in memory, per process).
- Each session lookup refreshes the PHP admin session like a page view does; while the dashboard is open and polling,
  the PHP admin login does not idle out.
- Time windows use the connection time zone `+09:00` (KST), set by the API on every connection.
