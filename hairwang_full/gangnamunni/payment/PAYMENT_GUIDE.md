# Seoul Beauty payment architecture

## Patient booking payments

Patient payments are recorded in `gangnamunni_platform_payments`.
Only server-verified provider results may move a booking payment to `paid`.
Hospital revenue is collected into monthly settlement batches.

## Advertising

Advertising is not sold as a fixed-duration package.

1. A hospital funds its separate advertising wallet
   (`gangnamunni_cpv_wallets`).
2. Assigning a monthly advertising slot requires a wallet balance of at least
   `selected_slot.monthly_price * 3`.
3. `cron_ad_slot_daily.php` deducts
   `monthly_price / number_of_days_in_charge_month` for every active day.
4. `gangnamunni_ad_slot_charges` prevents duplicate daily charges.
5. Ending an assignment stops future charges. The unused wallet balance remains
   on the hospital advertising account; no automatic refund is issued.

The retired `payment/ad_payment.php` and `payment/ad_refund.php` endpoints return
HTTP 410 and must not be used for new integrations.

## Booking checkout rollout (2026-09-16)

`booking_payment.php` has two explicit actions:

- **결제하기** opens the Toss Payments checkout (PortOne was removed on 2026-10-08;
  it never processed a payment). Payment is two-phase: the browser authenticates in
  the Toss window and returns to `booking_payment_toss_success.php`, where the server
  calls `POST /v1/payments/confirm` **only after** re-checking, under the payment lock,
  that the order is still pending, the amount equals the ledger, the booking is still
  `confirmed` and any point hold is still active. If any check fails nothing is charged
  and the Toss attempt expires. Mobile/PC return, the "상태 확인" button, the webhook and
  the reconcile cron all re-query Toss before touching the ledger.
  Every success-URL return (`paymentKey`, `orderId`, `amount`) is stored in
  `gangnamunni_payment_attempts` before confirm, as the Toss guide requires, together
  with its outcome. The `orderId` is the order's `merchant_uid`; Toss only refuses an
  orderId that was already approved or cancelled (`DUPLICATED_ORDER_ID`), so a buyer can
  retry the same order after a cancelled or failed attempt.
- **Mobile app (WebView).** The patient app loads these pages in `webview_flutter`.
  Following the Toss WebView guide, `mobile/patient_app/lib/services/payment_app_launcher.dart`
  converts card/easy-pay `intent:` URLs to app schemes and opens payment apps externally
  (Play Store fallback by package); the Android manifest `<queries>` lists the same
  packages. iOS opens schemes with `UIApplication.open`, so no
  `LSApplicationQueriesSchemes` entries are needed. Only allow-listed payment schemes are
  opened; anything else stays blocked. Toss notes that `webview_flutter_android` above
  3.16.0 fails to launch some card apps; the app currently resolves 4.10.x, so test card
  apps on a real Android device before release and pin the version if a card app fails.
- **결제 없이 계속하기** preserves the requested payment-free walkthrough. It
  records only `gangnamunni_booking_payment_previews`, leaving the financial
  order pending. It is available to the booking owner by default during rollout.
  Set `BOOKING_PAYMENT_PREVIEW_ENABLED=0` to disable it on both client and server.
  This is never an automatic fallback when a real payment fails.

Provider values live in one place: `booking_payment_pg_providers()` and
`booking_payment_test_providers()` in `includes/booking_payment_config.php`.
Sandbox transactions (`toss_test`, legacy `portone_test`) and historical
`mock_booking_payment` records are excluded from booking revenue and new
settlement items. Existing draft/scheduled batches containing historical test
payments are placed on hold when the monthly job processes them; already closed
historical batches require operator review and must not be silently rewritten.

### Installation and configuration

1. Install the canonical payment and settlement schemas first if absent.
2. Run `php hairwang_full/gangnamunni/payment/migrate.php` once (safe to rerun).
   This adds the preview/refund-request/payment-attempt tables and settlement carry-forward column,
   and upgrades refund-request status to include definitive provider failures.
   The supplied legacy baseline SQL uses MariaDB-specific `ADD ... IF NOT EXISTS`;
   do not blindly run those legacy ALTER statements on MySQL 8. The new CLI
   migration checks column existence explicitly and supports MySQL 8.
   Payment CLI tools, reconciliation, settlement and webhook load the same
   `hairwang_full/data/dbconfig.php` as the booking pages when it exists, without
   starting a session. Only installations without that web config fall back to
   `hairwang_full/dbconfig.php`. Deploy the shared
   `includes/booking_payment_db_config.php` alongside those entry points.
3. Set runtime variables (server: `hairwang_full/data/dbconfig.php`; local: `.env`):
   `TOSS_CLIENT_KEY` / `TOSS_SECRET_KEY` — 개발자센터 > API 개별 연동 키 (`test_ck_`/`test_sk_`
   or `live_ck_`/`live_sk_`; widget `gck`/`gsk` keys are refused). Both must be set and
   from the same environment; the secret prefix decides `provider` = `toss_test` / `toss`.
   `BOOKING_PAYMENT_BASE_URL` (canonical public HTTPS origin, no trailing path or slash,
   e.g. `https://hms.seoulbeauty.app`) and `BOOKING_PAYMENT_METHODS` (contracted methods:
   `card,bank,naverpay,kakaopay,tosspay`; easy-pay methods open with `flowMode: DIRECT`).
   Only the client key reaches the browser.
4. Enable checkout with `G5_PG_PAYMENTS_ENABLED=1`.
5. Register `<BOOKING_PAYMENT_BASE_URL>/payment/toss_webhook.php` in 개발자센터 > 웹훅
   (`PAYMENT_STATUS_CHANGED`). Toss payment webhooks are unsigned, so the body is only a
   signal: the endpoint re-queries the authenticated API via `booking_payment_sync()`.
6. Restart/recreate the application service after changing runtime variables.
   The Compose gangnamunni service now forwards these variables explicitly.

`booking_payment_base_url()` only validates syntax (HTTPS scheme, no userinfo,
query or fragment). It cannot tell whether the address resolves, so a stale but
well-formed value passes every check in this repository and `check.php` still
prints `Real checkout: READY`. A wrong value fails silently and off-site: the
card is charged, the PG notification 404s, and the order stays `pending`
with nothing logged locally. Confirm the endpoint with the curl above instead
of trusting the preflight alone.

The public site and the local Compose environment do not share a URL shape.
Production serves the booking pages from the web root through the rewrite in
`public/.htaccess`. `hairwang_full/local-router.php` does not implement that
rewrite, so on `localhost:8002` the pages stay under `/gangnamunni/...` and a
root-form path returns 404. Local URLs in the testing guide are correct as
written; do not copy them into `BOOKING_PAYMENT_BASE_URL`, which in any case
requires a public HTTPS address the PG can reach.

Do not enable real charging until the operator has confirmed the merchant/PG
contract, available payment methods, refund rules and settlement arrangements.
No live credentials, external account settings or production deployment are
changed by this code change.

### Refunds and operations

The platform super administrator can use `admin_booking_payments.php`, linked
from booking revenue, to re-query and refund a payment. Partial refunds preserve
remaining balance. Each refund has a persisted request ID (passed to the PG as an
idempotency key) and is checked against a fresh remaining balance under the lock. Repeating the same request does not cancel twice.
Ambiguous timeout results remain pending until a fresh query/webhook confirms
what happened. A well-formed, successful HTTP response containing an explicit
PG API rejection marks the request `failed`; after reviewing the rejection,
the operator may submit a new request ID. A failed request ID cannot be reused.
Transport errors, invalid responses and timeouts remain pending; never delete a
pending intent merely because an HTTP request timed out.

Provider observations are applied in one database transaction, including a first
observation that already contains a full refund. The original provider `paid_at`
is stored in Seoul time so a delayed notification does not move the payment into
the wrong month. Previously verified payments retain their stored sandbox/live
classification when runtime configuration changes.

Booking cancellation does not claim that a refund has happened. Operations must
review cancelled bookings with outstanding paid balances and process their
refunds separately. Automatic cancellation-policy decisions and 120-day automatic
refunds are not enabled. The checkout no longer promises those unimplemented
behaviors.

Run `cron_reconcile_booking_payments.php <last-id>` regularly. It processes up to
100 rows and prints a next cursor; continue through all pages, then start the next
scan from zero. Unknown/unpaid orders can produce nonzero exit status: alert on
persistent failures instead of treating them as successful payments. Never
include credentials or raw provider bodies in public error messages.

Run `cron_monthly_hospital_settlements.php` after the month ends. It builds the
previous month's liabilities, carries negative balances, and leaves approved
batches immutable. It does **not** send bank transfers. Bank-account verification,
payout provider integration, approval UI, fee policy, late-arriving events for
already closed periods, and production job scheduling remain rollout work.
Advertising wallet funding also still requires its own verified provider adapter.

Point spending is visibly disabled until reservation, spending, restoration and
partial-refund allocation are implemented atomically. Existing booking reward
logic now runs on service completion only when a real, fully paid booking exists,
not merely when a hospital confirms a booking. Review historical prepayment
rewards separately; this change does not rewrite balances.

The duplicate Laravel `/api/payments/*` API is retired with HTTP 410. It did not
use this ledger and must not remain an alternative unauthenticated payment path.
Clients still using those legacy URLs need to move to the booking checkout.

### Validation

Run these commands against the local Compose environment:

```sh
docker exec hms-saas-gangnamunni-1 php /var/www/html/hairwang_full/gangnamunni/payment/check.php
docker exec hms-saas-gangnamunni-1 php /var/www/html/tests/payments/regression.php
docker exec hms-saas-gangnamunni-1 php /var/www/html/tests/payments/http.php
docker exec hms-saas-gangnamunni-1 php /var/www/html/tests/payments/smoke.php
docker exec hms-saas-gangnamunni-1 php /var/www/html/tests/payments/config.php
```

The regression suite creates and removes a random isolated database, injects
a fake in-memory PG gateway, and makes no live provider request. The HTTP suite
uses the actual endpoint source with isolated database/identity fixtures; it does
not test the real login flow. The smoke suite checks unauthenticated guards using
the running application's real bootstrap. Neither suite proves the external PG
checkout, mobile redirect or PG webhook delivery works; those need sandbox
testing with configured credentials and a publicly reachable HTTPS URL.

The preflight prints configuration presence and schema readiness without secret
values. Its exit code reports database/schema readiness; inspect `Real checkout`
separately. `--render` on the regression suite additionally writes a synthetic
checkout fixture for browser inspection; remove
`hairwang_full/gangnamunni/payment-fixture-preview.html` after inspection.
