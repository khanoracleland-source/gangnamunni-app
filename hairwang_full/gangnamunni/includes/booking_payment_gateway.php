<?php
/**
 * PG(결제대행사) 연결 지점.
 *
 * PG 클라이언트(booking_payment_inicis.php — KG이니시스)는 이 인터페이스를 구현하고 booking_payment_gateway() 가
 * 반환한다. 원장·상태 전이·잠금·환불 멱등성(booking_payment_service.php)은 PG와 무관하다.
 *
 * PG 설정이 없는 동안(booking_payment_gateway() === null):
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
     * 2단계 결제의 서버 승인. 브라우저 인증이 끝난 결제를 확정하고 정규화된 결과를 돌려준다.
     * 명확한 거절은 BookingPaymentRejectedException(메시지 = PG 오류 코드).
     */
    public function confirm(string $merchantUid, string $paymentKey, int $amount): array;

    /**
     * 부분/전체 환불. $remaining 은 잠금 안에서 방금 조회한 환불 가능 잔액,
     * $requestId 는 멱등키로 쓴다. 명확한 거절은 BookingPaymentRejectedException.
     */
    public function cancel(string $merchantUid, string $transactionId, int $amount, int $remaining, string $reason, string $requestId): array;
}

/**
 * 현재 연결된 PG: KG이니시스(InicisGateway). 설정(INICIS_MID + INICIS_INIAPI_KEY)이 없으면 null(환불·조회 비활성).
 * 설정은 booking_payment_setting() 이 읽는다 — data/payment_config.php 의 define() 또는 환경변수. 요청값은 절대 쓰지 않는다.
 */
function booking_payment_gateway(): ?BookingPaymentGateway {
    static $gateway = null, $resolved = false;
    if ($resolved) return $gateway;
    $resolved = true;
    $mid = trim(booking_payment_setting('INICIS_MID'));
    $key = trim(booking_payment_setting('INICIS_INIAPI_KEY'));
    if ($mid === '' || $key === '') return null;
    require_once __DIR__ . '/booking_payment_inicis.php';
    try {
        // hashData 에 들어가고 이니시스에 등록된 공인 IP 여야 한다. SERVER_ADDR 은 프록시/컨테이너 뒤에서 내부 IP 가 되므로 쓰지 않는다.
        $ip = trim(booking_payment_setting('INICIS_CLIENT_IP'));
        if ($ip === '') throw new InvalidArgumentException('INICIS_CLIENT_IP (public server IP registered with Inicis) is required.');
        $gateway = new InicisGateway($mid, $key, trim(booking_payment_setting('INICIS_MODE', 'live')), $ip,
            booking_payment_inicis_ledger_reader(booking_payment_ledger_db()), null, 15, trim(booking_payment_setting('INICIS_HASHKEY')));
    } catch (Throwable $e) {
        error_log('[booking_payment_gateway] Inicis not connected: ' . $e->getMessage());
        $gateway = null;
    }
    return $gateway;
}

/** 원장 조회용 DB 연결: 그누보드 공용 연결이 있으면 그것을, 없으면(cron) 설정으로 새로 연다. */
function booking_payment_ledger_db(): mysqli {
    static $own = null;
    if (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) return $GLOBALS['connect'];
    if ($own instanceof mysqli) return $own;
    if (!defined('G5_MYSQL_HOST')) require_once __DIR__ . '/booking_payment_db_config.php';
    $own = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? (int)G5_MYSQL_PORT : 3306);
    $own->set_charset('utf8mb4');
    $own->query("SET time_zone='+09:00'");
    return $own;
}

function booking_payment_require_gateway(): BookingPaymentGateway {
    $gateway = booking_payment_gateway();
    if (!$gateway) throw new BookingPaymentGatewayUnavailableException('No payment gateway is configured.');
    return $gateway;
}
