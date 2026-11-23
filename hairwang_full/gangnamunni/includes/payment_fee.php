<?php
// 결제 수수료 계산 (순수 함수, 외부 의존 없음). Nest API 와 관리자 API 의 같은 규칙과 같은 시험 예시를 쓴다.

if (!function_exists('platform_payment_fee_amount')) {
    /** Fee of one payment: the amount paid by card / transfer times the rate (basis points; 143 = 1.43%), rounded DOWN to a whole won. */
    function platform_payment_fee_amount(int $paid, int $rate_bp): int {
        return intdiv(max(0, $paid) * max(0, $rate_bp), 10000);
    }

    /** Hospital settlement of one payment: (paid by card / transfer - fee) + points that covered part of the price. */
    function platform_payment_settlement(int $paid, int $fee, int $points_used): int {
        return max(0, $paid) - max(0, $fee) + max(0, $points_used);
    }

    /** The Sunday strictly after a date (Y-m-d): the day the hospital's settlement for a treatment done on that date is paid. Null if not a real date. */
    function platform_payment_next_sunday(string $date): ?string {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return null;
        $d = new DateTimeImmutable($m[1] . '-' . $m[2] . '-' . $m[3]);
        $dow = (int)$d->format('w'); // 0 = Sunday
        return $d->modify('+' . ($dow === 0 ? 7 : 7 - $dow) . ' days')->format('Y-m-d');
    }

    /**
     * What one ledger row means for the hospital. $row: status, amount, refunded_amount, points_used, pay_method, fee_rate_bp, fee_amount,
     * booking_status, booking_date. $today: Y-m-d (Seoul).
     * state: held (ordered, not paid) | counted (paid; the settlement is fixed, not yet paid out) | received (payout day has come) | refunded | none.
     * fee / settlement stay null when the fee was not recorded for this payment (no method or no rate at payment time): never guessed.
     * Settlement = (paid by card/transfer - fee) + points that covered part of the price. A part refund removes that part and its fee (same rate, rounded down).
     * @return array{state:string,fee:?int,settlement:?int,payout_date:?string,paid_net:int}
     */
    function platform_payment_figures(array $row, string $today): array {
        $status = (string)($row['status'] ?? '');
        $amount = max(0, (int)($row['amount'] ?? 0));
        $refunded = min($amount, max(0, (int)($row['refunded_amount'] ?? 0)));
        $points = max(0, (int)($row['points_used'] ?? 0));
        $out = ['state' => 'none', 'fee' => null, 'settlement' => null, 'payout_date' => null, 'paid_net' => 0];
        if ($status === 'pending') { $out['state'] = 'held'; return $out; }
        if ($status === 'refunded') { $out['state'] = 'refunded'; $out['fee'] = 0; $out['settlement'] = 0; return $out; }
        if (!in_array($status, ['paid', 'partially_refunded'], true)) return $out;
        $out['paid_net'] = $amount - $refunded;
        $payout = ((string)($row['booking_status'] ?? '') === 'completed') ? platform_payment_next_sunday((string)($row['booking_date'] ?? '')) : null;
        $out['payout_date'] = $payout;
        $out['state'] = ($payout !== null && $payout <= $today) ? 'received' : 'counted';
        $method = (string)($row['pay_method'] ?? '');
        if ($method === '' || !isset($row['fee_rate_bp']) || !isset($row['fee_amount'])) return $out;
        $fee_net = max(0, (int)$row['fee_amount'] - platform_payment_fee_amount($refunded, (int)$row['fee_rate_bp']));
        $out['fee'] = $fee_net;
        $out['settlement'] = $out['paid_net'] - $fee_net + $points;
        return $out;
    }

    function platform_payment_method_label(?string $method): string {
        return ['card' => '카드', 'bank_transfer' => '계좌이체'][(string)$method] ?? '미상';
    }

    function platform_payment_valid_date(string $v): bool {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $v, $m)) return false;
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    /** "1.43" -> 143 (basis points, 1/100 %). At most two decimals, 0..100. Null when not a plain percent. */
    function platform_payment_fee_percent_to_bp(string $text): ?int {
        if (!preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', trim($text), $m)) return null;
        $bp = (int)$m[1] * 100 + (isset($m[2]) ? (int)str_pad($m[2], 2, '0') : 0);
        return $bp <= 10000 ? $bp : null;
    }

    /**
     * History rule (same as the admin API): a method's new rate must start after its latest start AND from tomorrow on;
     * the first rate of a method may start on any allowed date. Returns an error key or null.
     */
    function platform_payment_fee_order_error(?string $latest_from, string $from, string $today): ?string {
        if ($latest_from === null) return null;
        if ($from <= $latest_from) return 'not_after_latest';
        if ($from <= $today) return 'must_be_future';
        return null;
    }
}
