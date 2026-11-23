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
}
