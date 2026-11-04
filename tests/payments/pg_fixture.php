<?php
/**
 * Fixture provider values for the ledger tests. No real PG is connected; the tests register
 * their own values the way a PG client would in includes/booking_payment_config.php.
 * Must be loaded before the payment config (that file only defines these functions when absent).
 *   test_pg          live PG rows (count as real payments)
 *   test_pg_sandbox  sandbox PG rows (excluded from revenue/settlement)
 */
if (!function_exists('booking_payment_pg_providers')) {
    function booking_payment_pg_providers(): array { return ['test_pg', 'test_pg_sandbox']; }
}
if (!function_exists('booking_payment_test_providers')) {
    function booking_payment_test_providers(): array { return ['mock_booking_payment', 'test_pg_sandbox']; }
}
