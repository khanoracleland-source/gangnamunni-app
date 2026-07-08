# Keyword Ad CPC/CPM Runbook

## Scope

The existing ranked ad slots remain the source of truth for positions 1-5.
This change adds performance billing beside the legacy monthly billing model:

- `monthly`: existing daily prorated charge from `cron_ad_slot_daily.php`
- `cpc`: charge after a valid click
- `cpm`: charge per valid impression (`cpm_price / 1000`)

The wallet remains `gangnamunni_cpv_wallets`. Every live performance charge also
creates a `deduct` row in `gangnamunni_cpv_transactions`.

## Event flow

1. `hospitals.php` resolves a search term through treatment names, hashtags, and aliases.
2. Active slot bookings are prepended in their configured rank order.
3. Only paid slot cards receive `data-ad-slot-booking` tracking metadata.
4. `includes/footer.php` records an impression after 50% visibility for one second.
5. Clicks and impressions are sent to `api_ad_slot_tracking.php`.
6. `includes/ad_slot_performance.php` validates the booking, product, billing model,
   duplicate window, budgets, wallet status, and balance on the server.
7. Test mode records the event without changing the wallet. Live mode performs the
   wallet update, transaction insert, event update, and booking spend update in one DB transaction.

Duplicate windows are five minutes for impressions and thirty minutes for clicks.

## Deployment order

1. Back up the production database.
2. Deploy the code while keeping performance billing in test mode.
3. Run:

   ```bash
   php artisan migrate --path=database/migrations/2026_07_08_000001_add_performance_billing_to_ad_slots.php --force
   ```

4. Open `admin_ad_slots.php` and create a CPC or CPM slot with daily and total budgets.
5. Assign a hospital that has an active wallet and sufficient balance.
6. Search the configured keyword and verify impressions, clicks, CTR, and zero charged spend.
7. Use the confirmation button in `admin_ad_slots.php` to enable live billing.
8. Verify the first live event against the wallet, transaction, event, and booking totals.

Do not enable live billing before the migration and test-mode checks are complete.

## Data issue to resolve

The current alias data contains incorrect mappings:

- Thermage aliases are attached to `온다 리프팅` even though `써마지FLX` exists.
- Ulthera aliases are attached to `써마지FLX`, but no Ulthera treatment master row exists.

The resolver prioritizes an existing treatment master name over a conflicting alias, so
the Korean search term `써마지` resolves to `써마지FLX`. English Thermage aliases and
Ulthera still require a reviewed data cleanup before those terms are used for paid ads.
