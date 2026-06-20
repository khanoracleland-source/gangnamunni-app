<?php
/**
 * 예약 수정 API (날짜/시간 변경)
 * POST: { booking_id, phone, new_date, new_time, edit_reason }
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST만 허용']);
    exit;
}

require_once dirname(__DIR__) . '/../dbconfig.php';
if (!function_exists('getSettingValue') && file_exists(dirname(__DIR__).'/includes/settings_helper.php')) require_once dirname(__DIR__).'/includes/settings_helper.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$booking_id = intval($_POST['booking_id'] ?? 0);
$phone = trim($_POST['phone'] ?? '');
$new_date = trim($_POST['new_date'] ?? '');
$new_time = trim($_POST['new_time'] ?? '');
$edit_reason = trim($_POST['edit_reason'] ?? '');

if ($booking_id <= 0 || empty($phone) || empty($new_date) || empty($new_time)) {
    echo json_encode(['success' => false, 'message' => '필수 항목을 입력해주세요.']);
    exit;
}

// 날짜 유효성 검증
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_date) || strtotime($new_date) < strtotime('today')) {
    echo json_encode(['success' => false, 'message' => '유효한 미래 날짜를 선택해주세요.']);
    exit;
}

// 시간 유효성 검증
if (!preg_match('/^\d{2}:\d{2}$/', $new_time)) {
    echo json_encode(['success' => false, 'message' => '유효한 시간을 선택해주세요.']);
    exit;
}

// 예약 조회
$stmt = mysqli_prepare($connect, "SELECT id, phone, mb_id, status, booking_date, booking_time, hms_appointment_id FROM gangnamunni_bookings WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $booking_id);
mysqli_stmt_execute($stmt);
$booking = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$booking) {
    echo json_encode(['success' => false, 'message' => '예약을 찾을 수 없습니다.']);
    exit;
}

// 전화번호 일치 확인
// [2026-06-15] 본인확인: 로그인필수 모드면 회원ID, 아니면 전화번호 (booking_require_login 설정)
@session_start();
$_req_login = function_exists('getSettingValue') ? (getSettingValue($connect,'booking_require_login','1')==='1') : true;
$_phone_auth = function_exists('getSettingValue') ? (getSettingValue($connect,'booking_phone_auth','0')==='1') : false;
$_sess_mb_id = $_SESSION['ss_mb_id'] ?? '';
if ($_req_login && !$_phone_auth) {
    if (empty($_sess_mb_id) || empty($booking['mb_id']) || $_sess_mb_id !== $booking['mb_id']) {
        echo json_encode(['success' => false, 'message' => '로그인 후 본인 예약만 수정할 수 있습니다.']);
        exit;
    }
} else {
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    $clean_db_phone = preg_replace('/[^0-9]/', '', $booking['phone']);
    if ($clean_phone !== $clean_db_phone) {
        echo json_encode(['success' => false, 'message' => '본인 예약만 수정할 수 있습니다.']);
        exit;
    }
}

// 수정 가능 상태 체크 (pending, confirmed만)
if (!in_array($booking['status'], ['pending', 'confirmed'])) {
    echo json_encode(['success' => false, 'message' => '수정할 수 없는 상태의 예약입니다.']);
    exit;
}

// 이전 일정 기록
$old_schedule = $booking['booking_date'] . ' ' . $booking['booking_time'];
$memo_addition = "변경: {$old_schedule} → {$new_date} {$new_time}";
if (!empty($edit_reason)) {
    $memo_addition .= " (사유: {$edit_reason})";
}

// 수정 처리
$stmt2 = mysqli_prepare($connect, "UPDATE gangnamunni_bookings SET booking_date=?, booking_time=?, memo=CONCAT(IFNULL(memo,''), '\n[', NOW(), '] ', ?), updated_at=NOW() WHERE id=?");
mysqli_stmt_bind_param($stmt2, 'sssi', $new_date, $new_time, $memo_addition, $booking_id);
$ok = mysqli_stmt_execute($stmt2);
mysqli_stmt_close($stmt2);

if (!$ok) {
    echo json_encode(['success' => false, 'message' => '수정 처리 중 오류가 발생했습니다.']);
    exit;
}

// HMS 동기화
if (!empty($booking['hms_appointment_id'])) {
    $hms_stmt = mysqli_prepare($connect, "UPDATE appointments SET opd_date=? WHERE id=?");
    if ($hms_stmt) {
        $hms_datetime = $new_date . ' ' . $new_time . ':00';
        mysqli_stmt_bind_param($hms_stmt, 'si', $hms_datetime, $booking['hms_appointment_id']);
        mysqli_stmt_execute($hms_stmt);
        mysqli_stmt_close($hms_stmt);
    }
}

mysqli_close($connect);
echo json_encode(['success' => true, 'message' => '예약이 변경되었습니다.', 'new_date' => $new_date, 'new_time' => $new_time]);
