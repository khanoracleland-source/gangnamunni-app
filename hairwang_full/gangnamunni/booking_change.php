<?php
/**
 * [05-42] 예약 변경 페이지
 * booking.php 의 예약 화면(날짜/시간/옵션 변경)을 재사용하되,
 * 들어오면 기존 예약의 날짜·시간·시술이 이미 선택되어 있고
 * 제출은 api/edit_booking.php 로 보낸다 (결제/사전확인 흐름 없음).
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/page_cache.php';
gangnamunni_page_cache(0, 'private');

$cb_id = max(0, (int)($_GET['booking_id'] ?? 0));
$cb_mb = (string)($_SESSION['ss_mb_id'] ?? '');
if ($cb_id <= 0 || $cb_mb === '') {
    header('Location: my_bookings.php');
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// edit_count 컬럼은 sql/bookings_schema.sql 로만 추가되므로 컬럼명을 고정하지 않는다 (없으면 ?? 0 으로 처리).
$cb_stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_bookings WHERE id = ? AND mb_id = ? LIMIT 1");
mysqli_stmt_bind_param($cb_stmt, 'is', $cb_id, $cb_mb);
mysqli_stmt_execute($cb_stmt);
$cb_row = mysqli_fetch_assoc(mysqli_stmt_get_result($cb_stmt));
mysqli_stmt_close($cb_stmt);

// 본인 예약 + 변경 가능한 상태 + 변경 횟수 남음 (api/edit_booking.php 와 동일 기준)
if (!$cb_row
    || !in_array($cb_row['status'], ['pending', 'hospital_checking', 'confirmed'], true)
    || (int)($cb_row['edit_count'] ?? 0) >= 3) {
    header('Location: my_bookings.php');
    exit;
}

if (empty($_SESSION['my_bookings_csrf'])) {
    $_SESSION['my_bookings_csrf'] = bin2hex(random_bytes(32));
}

$cb_date = (string)$cb_row['booking_date'];
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cb_date) || $cb_date < date('Y-m-d')) {
    $cb_date = date('Y-m-d'); // 이미 지난 날짜면 오늘부터 다시 고르게 한다
    $cb_time = '';
} else {
    $cb_time = substr((string)$cb_row['booking_time'], 0, 5);
}

$change_booking = [
    'id'    => (int)$cb_row['id'],
    'date'  => $cb_date,
    'time'  => $cb_time,
    'phone' => (string)$cb_row['phone'],
];

// booking.php 가 읽는 요청값 구성 (병원 + 기존 시술 미리 선택)
$_GET['id'] = (string)(int)$cb_row['hospital_id'];
// 저장된 시술명은 "시술명 · 옵션명" 형태일 수 있으므로 분리해서 미리 선택한다.
$cb_parts = explode(' · ', (string)$cb_row['treatment'], 2);
$_GET['treatment'] = trim($cb_parts[0]);
$change_booking['option_name'] = isset($cb_parts[1]) ? trim($cb_parts[1]) : '';
unset($_GET['treatment_id'], $_GET['option_id']);
$_GET['rebook'] = '1';

require __DIR__ . '/booking.php';
