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
Set `APP_AUTH_TEST_SOCKET` to a disposable local MySQL socket to also test row
locking across two connections, exact-response retry, rollback on persistence
failure, and logout ordering. The test creates and drops only a randomized
`auth_regression_*` schema. It never uses application DB credentials.
