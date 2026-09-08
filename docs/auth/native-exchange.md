# Native exchange deployment

Deploy both native-auth migrations before these endpoints. `sso_tokens` must
use InnoDB: exchange claim, token issuance, encrypted response persistence and
commit are one transaction. Logout locks/deletes exchange rows before deleting
device tokens, using the same transaction ordering. Refresh locks the existing
refresh row, renews it in place, then issues access/bridge tokens in that same
transaction. The lock order is exchange rows → refresh rows → access/bridge
rows; a missing or revoked refresh credential is rejected, never re-created.
Database failures return 503 and roll back; only invalid credentials/account
state return 401.

Provision `APP_NATIVE_AUTH_KEY` in the PHP worker environment: base64 of 32
cryptographically random bytes. Keep it outside the database and repository,
identical across all workers. No default key is provided. A missing/invalid key
returns HTTP 503 without issuing tokens. Keep this key stable across deployments.

Rotating this key is a breaking change for any exchange code encrypted under
the old one: decryption fails closed (503, not a silent fresh mint), so a
retry mid-rotation just returns "unavailable" instead of a fresh code. Exchange
codes only live up to 90s, so rotate during low native-auth traffic and expect
a brief window of 503s rather than rolling the key with no user impact.

The retry payload uses AES-256-GCM with the exchange-code hash as authenticated
associated data. Plaintext legacy payloads are deliberately rejected. If an
older prototype has been deployed, invalidate its short-lived exchange rows
before rollout; do not migrate plaintext credentials into the new cache.
Expired encrypted payloads are cleared opportunistically by exchange requests.
A periodic database cleanup may additionally delete expired sso_tokens rows.

Run `php -n tests/regression/exchange_atomic_encryption.php` for encryption checks.
For rollout, explicitly apply both `2026_09_07_000001` (native token types)
and `2026_09_08_000001` (`payload` column), and rebuild/distribute the patient
app. PHP source updates alone do not update installed Flutter apps.

Smoke-test the deployed exchange endpoint with a deliberately invalid code
and a syntactically valid 64-character hex device ID: expect HTTP 401
`exchange_invalid`. HTTP 503 `exchange_unavailable` is a deployment failure,
not a successful login-persistence check. Check the PHP worker's
`APP_NATIVE_AUTH_KEY` and the migrated schema; never log key/token values.
The login page can succeed while this separate exchange endpoint fails.

Cold-start acceptance: sign in, navigate between several pages immediately,
force-stop the app, reopen, and confirm the same account is restored. Repeat
after the access token expires, offline/online, and after explicit logout.
An absent native token must preserve existing WebView cookies; a rejected
native refresh token must still clear them. Page navigations within one login
window reuse the same exchange code so responses cannot save competing bundles.

Set `APP_AUTH_TEST_SOCKET` to a disposable local MySQL socket to also test row
locking across two connections, exact-response retry, rollback on persistence
failure, and logout ordering. The test creates and drops only a randomized
`auth_regression_*` schema. It never uses application DB credentials.

## Persistence hardening (2026-09-08)

See `login-persistence-audit-2026-09-08.md` for findings and acceptance status.
Deploy the backend (including `api/app_auth_bootstrap.php`) before the app.
The web bootstrap and standalone auth endpoints must agree on Seoul PHP/SQL
time; standalone APIs explicitly configure their SQL session. The common
bootstrap also aliases its DB connection for the remember/SSO helpers before
those helpers execute.

Do not revoke existing device remember credentials during exchange: the
WebView cookie is the recovery path if the process dies before the response
is committed to secure storage. Expired credentials are pruned; explicit
logout revokes every credential for that account/device.

The app persists a single `native_session_v2` record and migrates legacy keys.
An explicit signed-out record prevents partially deleted legacy keys from
restoring a session after logout. Android acknowledges storage mutations only
after synchronous disk commit on the plugin worker thread. Secure preference
files are excluded from backup/transfer because Keystore keys do not transfer.
A normal app update preserves data; reinstall/data-clear and downgrade to a
pre-v2 build are not session-preserving update tests.

Run the deployed API check with Python 3 and curl (TLS verification enabled):
`python3 scripts/auth/check_native_auth.py https://hms.seoulbeauty.app`.
This checks readiness with invalid credentials; it does not replace an actual
signed-in device test or schema review. A nonzero exit blocks release.

For real HTTP handler regression checks against a disposable local MySQL:
`APP_AUTH_TEST_SOCKET=/path/mysql.sock php -n tests/regression/native_auth_http.php`.
The script creates/drops its own randomized schema, starts/stops a local PHP
server and never reads production DB configuration. Both this test and the
existing encryption/locking test should pass before rollout.


### Shared implementation after the persistence audit

Deploy `gangnamunni/includes/app_native_auth_http.php` with the four native
API handlers, and `gangnamunni/includes/sso_session_token.php` with both
SSO promoters. These are required includes, not optional deployment files.
The first centralizes HTTP input, retryable DB initialization and member
responses; the second enforces accepted token types and atomic device-bound
bridge consumption. Native token hashes must never be accepted as raw SSO
credentials. The unused destructive refresh-cleanup helper was removed.

Android preference migration commits the destination before removing the
source. An encrypted-store initialization failure now surfaces to Flutter
instead of switching stores and appearing signed out. Real-device migration
and update acceptance tests remain required; Java compilation alone does
not validate Keystore behavior.


### Diagnosing an exchange-only 503

Exchange validates its worker key and payload schema before rejecting an
invalid code. Both dependencies can produce the same `exchange_unavailable`
response. Safe server logs now include a stage name, exception class and
numeric code, never exception messages or credential values.

Run `php scripts/auth/inspect_native_auth.php /actual/path/to/hairwang_full`
on the server for a read-only file/schema/CLI preflight. CLI key availability
does not prove PHP-FPM/Apache worker key availability. Confirm that Laravel
migrations and both PHP dbconfig entry points target the intended database.
Passing invalid-credential smoke checks does not prove successful issuance;
an authenticated test-account exchange and restoration are still necessary.
