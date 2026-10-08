<?php
/**
 * PG(결제대행사) 연결 지점.
 *
 * PortOne 연동은 2026-10-08 제거되었다. 다음 PG(Toss Payments)는 이 인터페이스를
 * 구현한 클라이언트를 booking_payment_gateway() 에서 반환하기만 하면 된다.
 * 원장·상태 전이·잠금·환불 멱등성(booking_payment_service.php)은 PG와 무관하다.
 *
 * PG가 연결되지 않은 동안:
 *  - booking_payment_checkout_ready() 는 false → 결제 화면은 "온라인 결제 준비 중",
 *    '결제 없이 계속하기'(미리보기)는 그대로 동작한다.
 *  - booking_payment_sync() 는 원장 상태를 그대로 돌려준다(외부에 결제 건이 존재할 수 없음).
 *  - booking_payment_refund() 는 BookingPaymentGatewayUnavailableException 을 던진다.
 */

/** PG가 요청을 명확히 거절한 경우만. 결과가 불분명한 오류는 RuntimeException 으로 남긴다. */
class BookingPaymentRejectedException extends RuntimeException {}

/** PG가 아직 연결되지 않았다. */
class BookingPaymentGatewayUnavailableException extends RuntimeException {}

interface BookingPaymentGateway {
    /** 원장 provider 컬럼에 기록할 값. booking_payment_pg_providers() 안의 값이어야 한다. */
    public function provider(): string;

    /**
     * 우리가 발급한 주문번호(merchant_uid)로 PG의 결제 상태를 조회해 아래 형태로 돌려준다.
     *  payment_id, transaction_id, status(ready|paid|failed|cancelled), raw_status,
     *  amount(int), cancelled_amount(int), currency, paid_at(unix time|null)
     * 아직 PG에 결제 건이 없으면 status='ready'.
     */
    public function payment(string $merchantUid): array;

    /**
     * 부분/전체 환불. $remaining 은 잠금 안에서 방금 조회한 환불 가능 잔액,
     * $requestId 는 멱등키로 쓴다. 명확한 거절은 BookingPaymentRejectedException.
     */
    public function cancel(string $merchantUid, string $transactionId, int $amount, int $remaining, string $reason, string $requestId): array;
}

/** 현재 연결된 PG. 없으면 null. */
function booking_payment_gateway(): ?BookingPaymentGateway {
    return null;
}

function booking_payment_require_gateway(): BookingPaymentGateway {
    $gateway = booking_payment_gateway();
    if (!$gateway) throw new BookingPaymentGatewayUnavailableException('No payment gateway is configured.');
    return $gateway;
}
