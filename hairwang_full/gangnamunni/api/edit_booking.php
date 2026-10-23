<?php
/**
 * 예약 수정 API (날짜/시간 변경)
 * POST: { booking_id, phone, new_date, new_time, edit_reason }
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST만 허용']);
    exit;
}

// common.php를 거쳐야 그누보드의 실제 세션명(session_name)과 동일하게 세션을 읽을 수 있음
// (dbconfig.php만 사용하면 커스텀 세션명 환경에서 로그인 세션을 못 찾는 문제 발생)
require_once dirname(__DIR__) . '/includes/init.php';
if (!function_exists('getSettingValue') && file_exists(dirname(__DIR__).'/includes/settings_helper.php')) require_once dirname(__DIR__).'/includes/settings_helper.php';
if (!isset($connect) || !$connect) {
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$csrf = (string)($_POST['csrf_token'] ?? '');
if ($csrf === '' || empty($_SESSION['my_bookings_csrf']) || !hash_equals($_SESSION['my_bookings_csrf'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => '요청이 만료되었습니다. 페이지를 새로고침해주세요.']);
    exit;
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$phone = trim($_POST['phone'] ?? '');
$new_date = trim($_POST['new_date'] ?? '');
$new_time = trim($_POST['new_time'] ?? '');
$new_treatment = trim(stripslashes($_POST['new_treatment'] ?? '')); // 시술 변경(선택)
$edit_reason = trim($_POST['edit_reason'] ?? '');

if ($booking_id <= 0 || empty($new_date) || empty($new_time)) {
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

// edit_count 컬럼은 배포 시 sql/bookings_schema.sql 로만 추가한다.
try {
    $_ec_chk = mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_bookings LIKE 'edit_count'");
} catch (Throwable $e) {
    $_ec_chk = false;
}
if (!$_ec_chk || mysqli_num_rows($_ec_chk) === 0) {
    error_log('[edit_booking] booking schema is outdated; run gangnamunni/sql/bookings_schema.sql');
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => '예약 기능 업데이트가 필요합니다. 관리자에게 문의해주세요.']);
    exit;
}
$MAX_EDITS = 3; // 예약 1건당 최대 변경 횟수

// 예약 조회
$stmt = mysqli_prepare($connect, "SELECT id, phone, mb_id, status, booking_date, booking_time, treatment, edit_count, hms_appointment_id FROM gangnamunni_bookings WHERE id = ?");
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
$_req_login = function_exists('getSettingValue') ? (getSettingValue($connect,'booking_require_login','1')==='1') : true;
$_phone_auth = function_exists('getSettingValue') ? (getSettingValue($connect,'booking_phone_auth','0')==='1') : false;
$_sess_mb_id = $_SESSION['ss_mb_id'] ?? '';
if ($_req_login && !$_phone_auth) {
    if (empty($_sess_mb_id) || empty($booking['mb_id']) || $_sess_mb_id !== $booking['mb_id']) {
        echo json_encode(['success' => false, 'message' => '로그인 후 본인 예약만 수정할 수 있습니다.']);
        exit;
    }
} else {
    if (empty($phone)) {
        echo json_encode(['success' => false, 'message' => '필수 항목을 입력해주세요.']);
        exit;
    }
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    $clean_db_phone = preg_replace('/[^0-9]/', '', $booking['phone']);
    if ($clean_phone !== $clean_db_phone) {
        echo json_encode(['success' => false, 'message' => '본인 예약만 수정할 수 있습니다.']);
        exit;
    }
}

// 수정 가능 상태 체크 (pending, hospital_checking, confirmed만)
if (!in_array($booking['status'], ['pending', 'hospital_checking', 'confirmed'])) {
    echo json_encode(['success' => false, 'message' => '수정할 수 없는 상태의 예약입니다.']);
    exit;
}

// 변경 횟수 제한 (예약당 최대 3회)
$cur_edit_count = (int)($booking['edit_count'] ?? 0);
if ($cur_edit_count >= $MAX_EDITS) {
    echo json_encode(['success' => false, 'message' => '예약 변경 가능 횟수(' . $MAX_EDITS . '회)를 모두 사용했습니다.']);
    exit;
}

// 이전 일정 기록
$old_schedule = $booking['booking_date'] . ' ' . $booking['booking_time'];
$memo_addition = "변경: {$old_schedule} → {$new_date} {$new_time}";

// 시술 변경 반영 (선택) — 값이 있고 기존과 다르면 treatment 도 함께 수정
$treatment_changed = ($new_treatment !== '' && $new_treatment !== ($booking['treatment'] ?? ''));
if ($treatment_changed) {
    $new_treatment = mb_substr($new_treatment, 0, 100);
    $memo_addition .= " / 시술: {$booking['treatment']} → {$new_treatment}";
}
if (!empty($edit_reason)) {
    $memo_addition .= " (사유: {$edit_reason})";
}

// 수정 처리 (변경 성공 시 edit_count +1)
// [05-43] 확정된 예약(결제 전)을 변경하면 병원이 새 일정을 다시 확인해야 하므로 "병원 확인 중"으로 되돌린다.
//   이미 결제된 예약은 일정이 어긋나므로 여기서 변경할 수 없다. 결제 승인과 겹치지 않게 같은 결제 잠금 안에서 처리한다.
require_once dirname(__DIR__) . '/includes/booking_payment_access.php';
require_once dirname(__DIR__) . '/includes/points_ledger.php';
require_once dirname(__DIR__) . '/includes/booking_payment_service.php';
$was_confirmed = false;
$ok = false;
try {
    booking_payment_lock_for_booking($connect, $booking_id, function () use ($connect, $booking_id, $treatment_changed, $new_date, $new_time, $new_treatment, $memo_addition, &$was_confirmed, &$ok) {
        $cur = mysqli_prepare($connect, "SELECT status FROM gangnamunni_bookings WHERE id = ?");
        mysqli_stmt_bind_param($cur, 'i', $booking_id);
        mysqli_stmt_execute($cur);
        $cur_row = mysqli_fetch_assoc(mysqli_stmt_get_result($cur));
        mysqli_stmt_close($cur);
        if (!$cur_row || !in_array($cur_row['status'], ['pending', 'hospital_checking', 'confirmed'], true)) {
            throw new RuntimeException('booking_not_editable');
        }
        $order = booking_payment_order($connect, $booking_id);
        if ($order && in_array($order['status'], ['paid', 'partially_refunded'], true)) {
            throw new RuntimeException('booking_paid');
        }
        $was_confirmed = ($cur_row['status'] === 'confirmed');
        $status_sql = "status=IF(status='confirmed','hospital_checking',status), ";
        if ($treatment_changed) {
            $stmt2 = mysqli_prepare($connect, "UPDATE gangnamunni_bookings SET {$status_sql}booking_date=?, booking_time=?, treatment=?, edit_count=edit_count+1, memo=CONCAT(IFNULL(memo,''), '\n[', NOW(), '] ', ?), updated_at=NOW() WHERE id=? AND status IN ('pending','hospital_checking','confirmed')");
            mysqli_stmt_bind_param($stmt2, 'ssssi', $new_date, $new_time, $new_treatment, $memo_addition, $booking_id);
        } else {
            $stmt2 = mysqli_prepare($connect, "UPDATE gangnamunni_bookings SET {$status_sql}booking_date=?, booking_time=?, edit_count=edit_count+1, memo=CONCAT(IFNULL(memo,''), '\n[', NOW(), '] ', ?), updated_at=NOW() WHERE id=? AND status IN ('pending','hospital_checking','confirmed')");
            mysqli_stmt_bind_param($stmt2, 'sssi', $new_date, $new_time, $memo_addition, $booking_id);
        }
        $ok = mysqli_stmt_execute($stmt2) && mysqli_stmt_affected_rows($stmt2) === 1;
        mysqli_stmt_close($stmt2);
    });
} catch (Throwable $e) {
    if ($e->getMessage() === 'booking_paid') {
        echo json_encode(['success' => false, 'message' => '이미 결제된 예약은 일정을 변경할 수 없어요. 취소 후 다시 예약해주세요.']);
        exit;
    }
    if ($e->getMessage() === 'booking_not_editable') {
        echo json_encode(['success' => false, 'message' => '수정할 수 없는 상태의 예약입니다.']);
        exit;
    }
    error_log('[edit_booking] update failed: ' . $e->getMessage());
    $ok = false;
}

// 결제 전 확정 예약을 되돌렸으면 결제에 걸어 둔 포인트를 복구한다(재확정 후 결제 화면에서 다시 사용).
if ($ok && $was_confirmed) {
    try {
        require_once dirname(__DIR__) . '/includes/points_checkout.php';
        points_checkout_release_for_booking($connect, $booking_id, '예약 변경 포인트 복구');
    } catch (\Throwable $e) {
        error_log('[edit_booking] point hold release failed: ' . $e->getMessage());
    }
}

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
$edits_left = max(0, $MAX_EDITS - ($cur_edit_count + 1));
echo json_encode(['success' => true, 'message' => '예약이 변경되었습니다. (변경 가능 ' . $edits_left . '회 남음)', 'new_date' => $new_date, 'new_time' => $new_time, 'edits_left' => $edits_left]);
