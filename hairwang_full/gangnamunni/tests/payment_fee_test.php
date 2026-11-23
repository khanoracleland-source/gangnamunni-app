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
exit($fail ? 1 : 0);
