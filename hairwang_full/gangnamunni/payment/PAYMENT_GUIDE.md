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
