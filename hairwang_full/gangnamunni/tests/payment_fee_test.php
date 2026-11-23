<?php
// php tests/payment_fee_test.php  — 종료 코드 0 = 통과. Nest/관리자 API 시험과 같은 예시를 쓴다.
require_once __DIR__ . '/../includes/payment_fee.php';

$fail = 0;
$check = function (string $name, $got, $want) use (&$fail) {
    if ($got !== $want) { $fail++; fwrite(STDERR, "FAIL $name: got " . var_export($got, true) . ", want " . var_export($want, true) . "\n"); }
};
// [paid, rate_bp, fee]
foreach ([[45000, 143, 643], [45000, 198, 891], [45000, 195, 877], [1, 143, 0], [0, 143, 0], [45000, 0, 0], [100, 10000, 100], [999, 143, 14]] as [$paid, $bp, $fee]) {
    $check("fee($paid,$bp)", platform_payment_fee_amount($paid, $bp), $fee);
}
// 50 000 won treatment, 5 000 points, 45 000 by card at 1.43%: (45 000 - 643) + 5 000
$check('settlement', platform_payment_settlement(45000, platform_payment_fee_amount(45000, 143), 5000), 49357);

// Received date: the Sunday after the treatment (Thursday 12-03 -> Sunday 12-06; a Sunday treatment -> the next Sunday).
$check('sunday after thursday', platform_payment_next_sunday('2026-12-03'), '2026-12-06');
$check('sunday after saturday', platform_payment_next_sunday('2026-12-05'), '2026-12-06');
$check('sunday after sunday', platform_payment_next_sunday('2026-12-06'), '2026-12-13');
$check('sunday after monday', platform_payment_next_sunday('2026-12-07'), '2026-12-13');
$check('sunday over year end', platform_payment_next_sunday('2026-12-29'), '2027-01-03');
$check('bad date', platform_payment_next_sunday('2026-02-30'), null);

// One ledger row. 50 000 treatment: 5 000 points + 45 000 by card at 1.43% (fee 643): settlement 49 357.
$card = ['status' => 'paid', 'amount' => 45000, 'refunded_amount' => 0, 'points_used' => 5000, 'pay_method' => 'card', 'fee_rate_bp' => 143, 'fee_amount' => 643, 'booking_status' => 'confirmed', 'booking_date' => '2026-12-03'];
$f = platform_payment_figures($card, '2026-12-05');
$check('counted state', $f['state'], 'counted'); $check('counted fee', $f['fee'], 643); $check('counted settlement', $f['settlement'], 49357); $check('counted no payout date', $f['payout_date'], null);
$done = array_merge($card, ['booking_status' => 'completed']);
$f = platform_payment_figures($done, '2026-12-05');
$check('done, before sunday', [$f['state'], $f['payout_date'], $f['settlement']], ['counted', '2026-12-06', 49357]);
$f = platform_payment_figures($done, '2026-12-06');
$check('done, on sunday', [$f['state'], $f['payout_date'], $f['settlement']], ['received', '2026-12-06', 49357]);
// A rate change later never touches the stored fee: same row, same result.
$check('stored fee is used', platform_payment_figures(array_merge($card, ['fee_rate_bp' => 143, 'fee_amount' => 643]), '2027-01-01')['settlement'], 49357);
$transfer = ['status' => 'paid', 'amount' => 45000, 'refunded_amount' => 0, 'points_used' => 0, 'pay_method' => 'bank_transfer', 'fee_rate_bp' => 198, 'fee_amount' => 891, 'booking_status' => 'confirmed', 'booking_date' => '2026-12-03'];
$check('transfer settlement', platform_payment_figures($transfer, '2026-12-05')['settlement'], 44109);
$check('fully refunded', array_values(array_intersect_key(platform_payment_figures(array_merge($card, ['status' => 'refunded', 'refunded_amount' => 45000]), '2026-12-05'), ['state' => 1, 'fee' => 1, 'settlement' => 1])), ['refunded', 0, 0]);
$part = platform_payment_figures(array_merge($card, ['status' => 'partially_refunded', 'refunded_amount' => 10000]), '2026-12-05'); // fee on 10 000 = 143
$check('part refund', [$part['paid_net'], $part['fee'], $part['settlement']], [35000, 500, 35000 - 500 + 5000]);
$none = platform_payment_figures(array_merge($card, ['pay_method' => null, 'fee_rate_bp' => null, 'fee_amount' => null]), '2026-12-05');
$check('no fee recorded: never guessed', [$none['state'], $none['fee'], $none['settlement']], ['counted', null, null]);
$check('held', platform_payment_figures(['status' => 'pending', 'amount' => 50000], '2026-12-05')['state'], 'held');
$check('held has no fee', platform_payment_figures(['status' => 'pending', 'amount' => 50000], '2026-12-05')['settlement'], null);
$check('bad booking date: no payout date', platform_payment_figures(array_merge($done, ['booking_date' => '']), '2026-12-05')['payout_date'], null);

// Percent -> basis points
foreach (['1.43' => 143, '1.98' => 198, '2' => 200, '2.0' => 200, '1.4' => 140, '0' => 0, '100' => 10000, '0.05' => 5] as $text => $bp) $check("percent $text", platform_payment_fee_percent_to_bp((string)$text), $bp);
foreach (['', 'abc', '1,43', '1.435', '100.01', '-1', '1e2', ' ', '1.'] as $bad) $check("bad percent '$bad'", platform_payment_fee_percent_to_bp($bad), null);

// History rule (same cases as the admin API)
$check('first rate', platform_payment_fee_order_error(null, '2026-01-01', '2026-11-23'), null);
$check('later rate from tomorrow', platform_payment_fee_order_error('2026-01-01', '2026-11-24', '2026-11-23'), null);
$check('rate from today', platform_payment_fee_order_error('2026-01-01', '2026-11-23', '2026-11-23'), 'must_be_future');
$check('rate not after latest', platform_payment_fee_order_error('2026-12-01', '2026-11-30', '2026-11-23'), 'not_after_latest');
$check('valid date', [platform_payment_valid_date('2026-02-28'), platform_payment_valid_date('2026-02-30'), platform_payment_valid_date('26-2-3')], [true, false, false]);

$check('date with trailing newline is refused', platform_payment_valid_date("2026-12-01\n"), false);

exit($fail ? 1 : 0);
