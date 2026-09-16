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

- **결제하기** opens PortOne V1. The server pre-registers the canonical order
  amount; browser completion, mobile return and webhook all query PortOne before
  accepting the order identity, amount, currency, sandbox flag and transaction ID.
- **결제 없이 계속하기** preserves the requested payment-free walkthrough. It
  records only `gangnamunni_booking_payment_previews`, leaving the financial
  order pending. It is available to the booking owner by default during rollout.
  Set `BOOKING_PAYMENT_PREVIEW_ENABLED=0` to disable it on both client and server.
  This is never an automatic fallback when a real payment fails.

Sandbox transactions use `provider=portone_test`. Those and historical
`mock_booking_payment` records are excluded from booking revenue and new
settlement items. Existing draft/scheduled batches containing historical test
payments are placed on hold when the monthly job processes them; already closed
historical batches require operator review and must not be silently rewritten.

### Installation and configuration

1. Install the canonical payment and settlement schemas first if absent.
2. Run `php hairwang_full/gangnamunni/payment/migrate.php` once (safe to rerun).
   This adds the preview/refund-request tables and settlement carry-forward column,
   and upgrades refund-request status to include definitive provider failures.
   The supplied legacy baseline SQL uses MariaDB-specific `ADD ... IF NOT EXISTS`;
   do not blindly run those legacy ALTER statements on MySQL 8. The new CLI
   migration checks column existence explicitly and supports MySQL 8.
   Payment CLI tools, reconciliation, settlement and webhook load the same
   `hairwang_full/data/dbconfig.php` as the booking pages when it exists, without
   starting a session. Only installations without that web config fall back to
   `hairwang_full/dbconfig.php`. Deploy the shared
   `includes/booking_payment_db_config.php` alongside those entry points.
3. Set runtime variables from the root `.env.example`:
   `PORTONE_IMP_CODE`, `PORTONE_API_KEY`, `PORTONE_API_SECRET`,
   `BOOKING_PAYMENT_BASE_URL` (the public HTTPS URL ending in `/gangnamunni`),
   `PORTONE_CHANNEL_CARD` and any additional contracted method's channel key.
   Easy-pay methods currently use KG Inicis hub `pay_method` values; configure
   compatible hub channels. Direct KakaoPay/NaverPay contracts need their own
   provider-specific request fields and are not interchangeable with hub channels.
   No key belongs in HTML or source control. Only the public store/channel IDs
   are returned to the browser.
4. Keep `PORTONE_SANDBOX=1` while testing and use a matching test channel.
   Enable checkout with `G5_PG_PAYMENTS_ENABLED=1`. An environment mismatch is
   rejected; test payments never become real revenue. Production requires live
   channels and `PORTONE_SANDBOX=0`.
5. Register `/gangnamunni/payment/portone_webhook.php` as the V1 webhook in the
   PortOne console. Use the canonical public base URL for mobile redirects.
   The untrusted webhook body cannot mark a payment paid: the backend queries
   the authenticated PortOne API and compares the server-owned order.
6. Restart/recreate the application service after changing runtime variables.
   The Compose gangnamunni service now forwards these variables explicitly.

Do not enable real charging until the operator has confirmed the merchant/PG
contract, available payment methods, refund rules and settlement arrangements.
No live credentials, external account settings or production deployment are
changed by this code change.

### Refunds and operations

The platform super administrator can use `admin_booking_payments.php`, linked
from booking revenue, to re-query and refund a payment. Partial refunds preserve
remaining balance. Each refund has a persisted request ID and uses PortOne's
remaining-balance checksum. Repeating the same request does not cancel twice.
Ambiguous timeout results remain pending until a fresh query/webhook confirms
what happened. A well-formed, successful HTTP response containing an explicit
PortOne API rejection marks the request `failed`; after reviewing the rejection,
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

See [the Uzbek testing guide](TESTING_UZ.md) for manual steps, sandbox setup and
expected results. Run these commands against the local Compose environment:

```sh
docker exec hms-saas-gangnamunni-1 php /var/www/html/hairwang_full/gangnamunni/payment/check.php
docker exec hms-saas-gangnamunni-1 php /var/www/html/tests/payments/regression.php
docker exec hms-saas-gangnamunni-1 php /var/www/html/tests/payments/http.php
docker exec hms-saas-gangnamunni-1 php /var/www/html/tests/payments/smoke.php
docker exec hms-saas-gangnamunni-1 php /var/www/html/tests/payments/config.php
```

The regression suite creates and removes a random isolated database, injects
simulated PortOne responses, and makes no live provider request. The HTTP suite
uses the actual endpoint source with isolated database/identity fixtures; it does
not test the real login flow. The smoke suite checks unauthenticated guards using
the running application's real bootstrap. Neither suite proves the external PG
checkout, mobile redirect or PortOne webhook delivery works; those need sandbox
testing with configured credentials and a publicly reachable HTTPS URL.

The preflight prints configuration presence and schema readiness without secret
values. Its exit code reports database/schema readiness; inspect `Real checkout`
separately. `--render` on the regression suite additionally writes a synthetic
checkout fixture for browser inspection; remove
`hairwang_full/gangnamunni/payment-fixture-preview.html` after inspection.

Official integration references:
- https://developers.portone.io/opi/ko/integration/start/v1/auth
- https://developers.portone.io/opi/ko/integration/webhook/readme-v1
- https://developers.portone.io/api/rest-v1/payment
