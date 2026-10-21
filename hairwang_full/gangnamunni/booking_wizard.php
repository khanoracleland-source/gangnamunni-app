<?php
/**
 * 구형 예약 마법사 — 사용 중지.
 *
 * 이전 버전은 로그인·CSRF·연령 제한 확인 없이 gangnamunni_bookings 에 직접 INSERT 했다(보안상 제거).
 * 예약은 booking.php (로그인 필수, CSRF, 연령 제한 검증 포함)로만 접수한다. 이전 코드는 git 기록에 남아 있다.
 */
require_once __DIR__ . '/includes/init.php';

$hospital_id = intval($_GET['hospital_id'] ?? $_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(410);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => '더 이상 사용하지 않는 예약 방식입니다. 앱에서 다시 예약해주세요.'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Location: ' . ($hospital_id > 0 ? 'booking.php?id=' . $hospital_id : 'hospitals.php'));
exit;
