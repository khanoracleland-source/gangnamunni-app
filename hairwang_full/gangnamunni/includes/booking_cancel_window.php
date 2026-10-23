<?php
/**
 * 결제 후 취소 기한 (05-48): 방문일 하루 전 오후 5시까지 앱에서 취소할 수 있고, 그 이후에는 병원에 직접 문의한다.
 * 이 기한은 "앱 결제가 끝난 예약"에만 적용된다. 결제 전 예약(대기·병원 확인 중·결제 대기)은 언제든 취소할 수 있다.
 * 환불 자체는 기한 안에 취소해도 자동이 아니라 관리자 확인 후 처리된다(booking_payment_refund).
 */
const BOOKING_CANCEL_DEADLINE_HOUR = 17;

/** 방문일 하루 전 17:00 (타임스탬프). */
function booking_cancel_deadline_ts(string $bookingDate): int {
    $d = DateTime::createFromFormat('Y-m-d', substr($bookingDate, 0, 10));
    if (!$d) return 0;
    $d->modify('-1 day')->setTime(BOOKING_CANCEL_DEADLINE_HOUR, 0, 0);
    return $d->getTimestamp();
}

/** 앱 결제가 끝난 예약이면 true. */
function booking_cancel_has_paid_payment(mysqli $db, int $bookingId): bool {
    $t = @mysqli_query($db, "SHOW TABLES LIKE 'gangnamunni_platform_payments'");
    if (!$t || mysqli_num_rows($t) === 0) return false;
    $st = mysqli_prepare($db, "SELECT provider, status FROM gangnamunni_platform_payments WHERE payment_type='booking' AND booking_id=? LIMIT 1");
    if (!$st) return false;
    mysqli_stmt_bind_param($st, 'i', $bookingId);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$row) return false;
    require_once __DIR__ . '/booking_payment_config.php';
    return booking_payment_is_pg($row['provider'] ?? null) && in_array($row['status'] ?? '', ['paid', 'partially_refunded'], true);
}

/** 앱에서 취소할 수 있는 기한이 지났으면 true (결제된 예약만 해당). */
function booking_cancel_window_closed(mysqli $db, int $bookingId, string $bookingDate, ?int $now = null): bool {
    $deadline = booking_cancel_deadline_ts($bookingDate);
    if ($deadline <= 0 || ($now ?? time()) <= $deadline) return false;
    return booking_cancel_has_paid_payment($db, $bookingId);
}
