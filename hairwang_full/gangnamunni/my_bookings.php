<?php
/**
 * 내 예약 조회 페이지
 * - 전화번호 기반 예약 조회
 * - 로그인 시 자동 입력
 * - 진행중/지난 예약 2탭
 * - 예약 취소/날짜변경 기능
 */
require_once __DIR__ . '/includes/init.php';
// [2026-06-15] mypage.php 와 동일한 회원 로그인 체계 — SSO/회원세션 인식 (init.php에도 있지만 명시적 보강)
if (isset($connect) && $connect && file_exists(__DIR__ . '/includes/sso_auto_login.php')) {
    @include_once __DIR__ . '/includes/sso_auto_login.php';
}
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

// [2026-06-15] mypage.php 와 100% 동일한 로그인 판정 — 그누보드 전역변수 직접 확인
//   함수(is_gangnamunni_logged_in) 대신 $is_member/$member 를 직접 봐서 mypage 와 동일하게 동작
global $is_member, $member;
$is_user_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$logged_member = $is_user_logged_in ? $member : [];

// [2026-06-15] 예약조회 로그인 필수 (booking_require_login 설정, 기본 ON / booking_phone_auth=1이면 전화번호조회 허용)
if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) { require_once __DIR__ . '/includes/settings_helper.php'; }
$_require_login = true;
$_phone_auth = false;
if (function_exists('getSettingValue')) {
    $_require_login = getSettingValue($connect, 'booking_require_login', '1') === '1';
    $_phone_auth    = getSettingValue($connect, 'booking_phone_auth', '0') === '1';
    if ($_require_login && !$_phone_auth && !$is_user_logged_in) {
        header('Location: auth/login.php?url=' . urlencode('/hairwang/app/my_bookings.php'));
        exit;
    }
}
$logged_mb_id = $logged_member['mb_id'] ?? '';
if (empty($_SESSION['my_bookings_csrf'])) {
    $_SESSION['my_bookings_csrf'] = bin2hex(random_bytes(32));
}
$my_bookings_csrf = $_SESSION['my_bookings_csrf'];

// [05-37] 결제 기한 자동 취소 안내를 확인했다고 기록한다(본인의 system 취소 건만).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'expire_seen') {
    header('Content-Type: application/json; charset=utf-8');
    $ok = false;
    $tok = (string)($_POST['csrf_token'] ?? '');
    $bid = (int)($_POST['booking_id'] ?? 0);
    if ($tok !== '' && hash_equals($my_bookings_csrf, $tok) && $bid > 0 && $logged_mb_id !== '') {
        $st = @mysqli_prepare($connect, "UPDATE gangnamunni_bookings SET expire_notice_seen_at = NOW()
            WHERE id = ? AND mb_id = ? AND cancelled_by = 'system' AND expire_notice_seen_at IS NULL");
        if ($st) { mysqli_stmt_bind_param($st, 'is', $bid, $logged_mb_id); $ok = mysqli_stmt_execute($st); mysqli_stmt_close($st); }
    }
    echo json_encode(['success' => $ok]);
    exit;
}

$auto_phone = '';
if (!empty($logged_member['mb_hp'])) {
    $auto_phone = $logged_member['mb_hp'];
} elseif (!empty($_SESSION['user_phone'])) {
    $auto_phone = $_SESSION['user_phone'];
}

// URL의 ?phone 값은 본인확인 수단이 아니므로 조회 조건으로 사용하지 않는다.
// 로그인 회원은 mb_id(+과거 mb_id 없는 예약의 본인 전화번호), 비회원은
// 별도 인증 흐름에서 세션에 저장된 검증 완료 전화번호만 사용할 수 있다.
$search_phone = $is_user_logged_in ? $auto_phone : (string)($_SESSION['user_phone'] ?? '');
$bookings = [];
$active_bookings = [];
$past_bookings = [];
// [Figma Sync - 2026-06-06] 3탭 분리 (시술예정/시술완료/예약취소)
$upcoming_bookings = [];
$completed_bookings = [];
$cancelled_bookings = []; 

// 예약 시간이 지나도 병원/마스터가 확인하기 전에는 completed로 자동 변경하지 않는다.
// 대신 내 예약 화면에서는 '완료 확인중'으로 시술완료 탭에 노출한다.
$is_booking_time_passed = static function (array $booking): bool {
    $value = trim((string)($booking['booking_date'] ?? '') . ' ' . (string)($booking['booking_time'] ?? ''));
    $timestamp = strtotime($value);
    return $timestamp !== false && $timestamp <= time();
};

if ($is_user_logged_in && !empty($logged_mb_id)) {
    // 로그인 회원: mb_id가 일치하거나, 과거 데이터 중 mb_id가 비어 있고 본인 전화번호가 일치하는 예약만 조회
    $clean_phone = preg_replace('/[^0-9]/', '', $search_phone);
    $stmt = $connect->prepare("SELECT b.*, h.profile_image_url as hospital_image
        FROM gangnamunni_bookings b
        LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
        WHERE b.mb_id = ?
           OR ((b.mb_id IS NULL OR b.mb_id = '')
               AND ? <> ''
               AND REPLACE(REPLACE(b.phone, '-', ''), ' ', '') = ?)
        ORDER BY b.created_at DESC
        LIMIT 50");
    $stmt->bind_param('sss', $logged_mb_id, $clean_phone, $clean_phone);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
        if (in_array($row['status'], ['pending', 'hospital_checking', 'confirmed']) && !$is_booking_time_passed($row)) {
            $active_bookings[] = $row;
            $upcoming_bookings[] = $row;
        } elseif (in_array($row['status'], ['cancelled', 'no_show'])) {
            $past_bookings[] = $row;
            $cancelled_bookings[] = $row;
        } else {
            $past_bookings[] = $row;
            $completed_bookings[] = $row;
        }
    }
    $stmt->close();
} elseif ($_phone_auth && !empty($search_phone)) {
    // 비회원 전화번호 조회는 faqat oldindan tasdiqlanib sessiyaga yozilgan raqam bilan.
    $clean_phone = preg_replace('/[^0-9]/', '', $search_phone);
    $stmt = $connect->prepare("SELECT b.*, h.profile_image_url as hospital_image
        FROM gangnamunni_bookings b
        LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
        WHERE REPLACE(REPLACE(b.phone, '-', ''), ' ', '') = ?
        ORDER BY b.created_at DESC
        LIMIT 50");
    $stmt->bind_param('s', $clean_phone);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
        if (in_array($row['status'], ['pending', 'hospital_checking', 'confirmed']) && !$is_booking_time_passed($row)) {
            $active_bookings[] = $row;
            $upcoming_bookings[] = $row;
        } elseif (in_array($row['status'], ['cancelled', 'no_show'])) {
            $past_bookings[] = $row;
            $cancelled_bookings[] = $row;
        } else {
            $past_bookings[] = $row;
            $completed_bookings[] = $row;
        }
    }
    $stmt->close();
}

if (!function_exists('getSettingValue')) {
    require_once __DIR__ . '/includes/settings_helper.php';
}

// 결제 상태(#예약결제): 확정된 예약에 대해 결제 버튼/완료 배지를 분기하기 위한 맵.
//   원장 미설치 환경에서도 페이지가 깨지지 않도록 테이블 존재를 먼저 확인한다.
$payment_map = [];
$_pay_ids = [];
foreach ($bookings as $_booking_row) { $_pay_ids[] = (int)$_booking_row['id']; }
if ($_pay_ids) {
    $_chk = mysqli_query($connect,
        "SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gangnamunni_platform_payments' LIMIT 1");
    if ($_chk && mysqli_num_rows($_chk) > 0) {
        $_in = implode(',', array_map('intval', $_pay_ids));
        $_pr = mysqli_query($connect,
            "SELECT booking_id, status, amount, provider FROM gangnamunni_platform_payments
             WHERE payment_type = 'booking' AND booking_id IN ({$_in})");
        if ($_pr) while ($_row = mysqli_fetch_assoc($_pr)) {
            $payment_map[(int)$_row['booking_id']] = $_row;
        }
    }
}
require_once __DIR__ . '/includes/booking_payment_access.php';
$payment_previews = booking_payment_previews($connect, $_pay_ids);
foreach ($payment_previews as $preview_id => $preview_row) {
    if (($payment_map[$preview_id]['status'] ?? '') === 'pending') $payment_map[$preview_id]['status'] = 'preview';
}
if (!function_exists('booking_payment_state')) {
    /**
     * 확정 예약의 결제 상태: 'paid' | 'payable' | '' (표시 안 함)
     * @param array $bk  예약 행(id, status 포함)
     * @param array $map 예약 ID => 결제 행
     * @return string
     */
    function booking_payment_state(array $bk, array $map): string {
        if (($bk['status'] ?? '') !== 'confirmed') return '';
        $p = $map[(int)$bk['id']] ?? null;
        if (!$p) return '';
        return booking_payment_display_state($p);
    }
}

// 시술완료 예약별 후기 상태 (후기 작성/승인대기/공개 버튼 분기용)
require_once __DIR__ . '/includes/review_booking.php';
$completed_ids = [];
foreach ($past_bookings as $_pb) { if (($_pb['status'] ?? '') === 'completed') $completed_ids[] = (int)$_pb['id']; }
$review_map = getBookingReviewsMap($connect, $completed_ids);

// [05-37] 아직 확인하지 않은 결제 기한 자동 취소 안내(최근 30일, 가장 최근 1건)
$expired_notice = null;
foreach ($cancelled_bookings as $_cb) {
    if (($_cb['cancelled_by'] ?? '') === 'system' && empty($_cb['expire_notice_seen_at'])
        && !empty($_cb['cancelled_at']) && strtotime($_cb['cancelled_at']) >= strtotime('-30 days')) {
        $expired_notice = $_cb;
        break;
    }
}

$page_title = '내 예약';
$back_url = 'mypage.php';
if (($_GET['from'] ?? '') === 'notifications') {
    $return_to = (string)($_GET['return_to'] ?? 'my_notifications.php');
    $back_url = preg_match('/^my_notifications\.php(?:\?.*)?$/', $return_to) ? $return_to : 'my_notifications.php';
}
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/booking-sheets.css?v=<?php echo (int)@filemtime(__DIR__ . '/assets/css/booking-sheets.css'); ?>">
    <style>
        :root {
            --color-primary: #AC1830;
            --color-bg: #ffffff;
            --color-bg-light: #f8f9fa;
            --color-text: #222;
            --color-text-secondary: #666;
            --color-border: #eee;
        }
        [data-bs-theme="dark"] {
            --color-bg: #1a1a2e;
            --color-bg-light: #16213e;
            --color-text: #e0e0e0;
            --color-text-secondary: #aaa;
            --color-border: #333;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { width: 100%; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--color-bg); color: var(--color-text); padding-bottom: 77px; overflow-x: hidden; }
        .container { width: 100%; max-width: 640px; min-height: 0; margin: 0 auto; overflow-x: clip; flex: 1; display: flex; flex-direction: column; }

        .header{position:relative;padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
        .header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
        .header h1{position:absolute;left:50%;transform:translateX(-50%);font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;}
        .global-nav-share{width:36px;height:36px;margin-left:auto;display:inline-flex;align-items:center;justify-content:center;border:0;background:transparent;color:var(--color-text,#111);font-size:22px;cursor:pointer;}
        .global-nav-share:hover,.global-nav-share:focus-visible{color:var(--color-primary,#AC1830);}
        .share-copy-toast{position:fixed;left:50%;bottom:84px;z-index:2000;transform:translate(-50%,12px);padding:10px 16px;border-radius:20px;background:rgba(20,20,20,.9);color:#fff;font-size:13px;white-space:nowrap;opacity:0;pointer-events:none;transition:opacity .2s ease,transform .2s ease;}
        .share-copy-toast.is-visible{opacity:1;transform:translate(-50%,0);}


        /* 검색 */

        /* 탭 */
        .tabs { display: flex; background: var(--color-bg); border-bottom: 1px solid var(--color-border); }
        .tab-btn { flex: 1; padding: 12px; text-align: center; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; border: none; background: none; cursor: pointer; color: var(--color-text-secondary); border-bottom: 2px solid transparent; }
        .tab-btn.active { color: var(--color-primary); border-bottom-color: var(--color-primary); }
        /* 탭 건수 — 배경 없이 숫자만. 색은 탭 상태(활성/비활성)를 그대로 상속한다. */
        .tab-count { margin-left: 4px; font-weight: 700; }
        .tab-content { display: none; }
        .tab-content.active { display: block; flex: 1; }

        /* 예약 카드 */
        .booking-card { background: var(--color-bg); margin: 8px 12px; padding: 16px; }
        .booking-header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .booking-hospital-img { width: 40px; height: 40px; border-radius: 8px; background: var(--color-bg-light); display: flex; align-items: center; justify-content: center; font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); overflow: hidden; flex-shrink: 0; }
        .booking-hospital-img img { width: 100%; height: 100%; object-fit: cover; }
        .booking-hospital-name, .booking-date { font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; }
        .booking-status { display: inline-block; font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; margin-top: 10px; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { color: #991b1b;}
        .status-no_show { background: #f3f4f6; color: #6b7280; }

        .booking-detail-row { display: flex; width: 100%; min-width: 0; align-items: center; gap: 8px; }
        .booking-detail { display: flex; align-items: center; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color: #fff; line-height: 1.8; background: #AC1830; width: 60%; min-width: 0; }
        /* 시술완료/예약취소: detail-1(위)·detail-2(아래) 세로 배치 */
        .booking-detail.bd-stacked { flex-direction: column; align-items: flex-start; gap: 2px; padding: 6px 12px; }
        /* 시술완료: 회색 배경 + 어두운 글자 */
        .booking-detail.bd-done { background: #f2f2f2; }
        .booking-detail.bd-done .detail-1, .booking-detail.bd-done .detail-2 { color: #333; }
        .past-procedure-card { padding: 14px 0 28px; margin: 0; border-bottom: 1px solid #ddd; background: #fff; }
        .past-procedure-card + .past-procedure-card { padding-top: 28px; }
        .past-procedure-card .booking-header { margin-bottom: 8px; }
        .past-procedure-card .booking-date { font-size: 18px; font-weight: 500; }
        .completion-state { display:block; margin: 4px 0 10px; color: var(--color-primary); font-size: 18px; font-weight: 600; }
        .past-procedure-card .booking-detail-row { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 0; }
        .past-procedure-card .booking-detail { width: auto; min-width: 0; min-height: 70px; padding: 12px 18px; }
        .past-procedure-card .detail-btns { width: auto; min-width: 0; flex-direction: row; align-items: center; justify-content: space-evenly; gap: 4px; }
        .past-procedure-card .detail-btn { flex: 0 1 auto; padding: 10px 4px; border-radius: 0; font-size: 18px; font-weight: 500; white-space: nowrap; }
        .bd-left { padding-left: 12px; flex: 1; min-width: 0; }
        .bd-right { padding-right: 12px; font-size: 18px; font-weight: 800; white-space: nowrap; flex-shrink: 0; }
        .booking-detail i { width: 18px; display: inline-block; }
        .detail-1 { font-size: 15px !important; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .detail-2 { font-size: 13px !important; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .detail-btns { display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .detail-btn { padding: 8px 4px; border-radius: 6px; font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; cursor: pointer; text-align: center; border: none; background: var(--color-bg); color: var(--color-text); flex: 1; text-decoration: none; display: block; }
        /* 결제(#예약결제): 확정 후 결제 대기 = 강조, 결제 완료 = 비활성 표기 */
        .detail-btn-pay { background: #AC1830; color: #fff; }
        .detail-btn-pay:hover { background: #822327; color: #fff; }
        .detail-btn-paid { background: #F2F2F2; color: #BBBBBB; cursor: default; }
        @media (max-width: 399px) { .detail-btns { flex-direction: column; } }
        @media (min-width: 400px) { .detail-btns { flex-direction: row; } }

        .booking-actions { display: flex; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--color-border); }
        .action-btn { flex: 1; padding: 8px; border-radius: 6px; font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; cursor: pointer; text-align: center; border: 1px solid var(--color-border); background: var(--color-bg-light); color: var(--color-text); }
        .action-btn.cancel { color: var(--color-primary, #AC1830); border-color: #fecaca; }
        .action-btn.edit { color: var(--color-primary); border-color: #fed7aa; }

        .cancel-reason { font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--color-primary, #AC1830); margin-top: 8px; padding: 6px 10px; background: #fef2f2; border-radius: 6px; }

        /* 빈 상태 */
        .empty-state { text-align: center; padding: 60px 20px; color: var(--color-text-secondary); }
        .empty-icon { font-size: calc(48px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom: 12px; }

        /* 모달 */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: var(--color-bg); border-radius: 16px; width: 90%; max-width: 400px; padding: 24px; }
        .modal-title { font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; margin-bottom: 16px; }
        .modal-input { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 8px; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom: 10px; background: var(--color-bg-light); color: inherit; }
        .modal-btns { display: flex; gap: 8px; margin-top: 16px; }
        .modal-btn { flex: 1; padding: 10px; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; cursor: pointer; border: none; }
        .modal-btn-cancel { background: var(--color-bg-light); color: var(--color-text); border: 1px solid var(--color-border); }
        .modal-btn-confirm { background: var(--color-primary); color: white; }
        .modal-btn-danger { background: var(--color-primary, #AC1830); color: white; }
        .action-btn.hospital { color: #2563eb; border-color: #bfdbfe; text-decoration: none; }

        /* Screenshot reference: 예약/결제 내역 */
        html { background: #3f3f3f; }
        body { width: 100%; max-width: 640px; margin: 0 auto; background: #f3f3f3; color: #111; }
        .container { width: 100%; max-width: 640px; min-height: 0; background: #f3f3f3; }
        .header { height: 64px; padding: 0 16px; border-bottom: 0; background: #fff; }
        .header .back { font-size: 26px; }
        .header h1 { font-size: 18px; font-weight: 650; white-space: nowrap; }
        .tabs { background: #fff; border-bottom-color: #ccc; }
        .tab-btn { padding: 15px 8px 13px; font-size: 18px; font-weight: 500; color: #111; }
        .tab-btn.active { font-weight: 650; }
        .tab-count { margin-left: 3px; }
        .tab-content { padding: 20px 18px 4px; }

        .booking-card {
            margin: 0 0 18px; padding: 20px 22px 18px; background: #fff;
            border: 0; border-radius: 0; box-shadow: none;
        }
        .booking-card-top { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .booking-card-date { font-size: 17px; font-weight: 650; letter-spacing: -.35px; }
        .booking-dday { color: var(--color-primary); font-size: 16px; font-weight: 650; }
        .push-hint { display: flex; align-items: flex-start; gap: 10px; margin: 4px 0 18px; padding: 14px 16px; background: #f7f7f7; border-radius: 8px; color: #333; text-decoration: none; font-size: 13px; line-height: 1.5; }
        .push-hint small { display: block; margin-top: 2px; color: #888; font-size: 12px; }
        .booking-detail-link {
            margin-left: auto; padding: 6px 10px; border: 1px solid #df9aa6; border-radius: 6px;
            color: #111; text-decoration: none; font-size: 13px; white-space: nowrap;
        }
        .booking-summary { display: flex; align-items: flex-start; gap: 18px; }
        .booking-thumb {
            width: 74px; height: 74px; flex: 0 0 74px; overflow: hidden; background: #ce3033;
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-size: 12px; letter-spacing: 2px;
        }
        .booking-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .booking-summary-copy { min-width: 0; flex: 1; }
        .booking-treatment { font-size: 14px; font-weight: 700; line-height: 1.4; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .booking-hospital { font-size: 12px; line-height: 1.45; }
        .booking-price { margin-top: 13px; font-size: 18px; font-weight: 700; }

        .booking-progress { display: grid; grid-template-columns: repeat(4,1fr); margin: 32px 0 28px; }
        .progress-step { position: relative; min-width: 0; padding-top: 25px; text-align: center; color: #d0d0d0; font-size: 11px; }
        .progress-step::before {
            content: ""; position: absolute; top: 0; left: 50%; width: 10px; height: 10px;
            transform: translateX(-50%); background: #d8d8d8;
        }
        .progress-step::after {
            content: ""; position: absolute; top: 4px; left: calc(50% + 12px); width: calc(100% - 24px); height: 1px; background: #ddd;
        }
        .progress-step:last-child::after { display: none; }
        .progress-step.active { color: #111; font-weight: 650; }
        .progress-step.active::before { background: var(--color-primary); }
        .progress-step.active::after { background: var(--color-primary); }

        .booking-card-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; padding-top: 20px; border-top: 1px solid #ddd; }
        .booking-card-action {
            min-height: 50px; border: 1px solid var(--color-primary); background: #fff; color: var(--color-primary);
            display: flex; align-items: center; justify-content: center; text-decoration: none;
            font-size: 16px; font-weight: 700; cursor: pointer;
        }
        .booking-card-action.primary { background: var(--color-primary); color: #fff; }
        .booking-card-actions.paid-actions { grid-template-columns: minmax(180px, 224px); justify-content: center; }
        .booking-card-action:disabled { opacity: .45; cursor: not-allowed; }
        .cancelled-card .booking-card-top { margin-bottom: 16px; }
        .cancelled-card .cancel-state { color: var(--color-primary); font-size: 15px; font-weight: 700; white-space: nowrap; }
        .cancelled-card .booking-card-actions { grid-template-columns: minmax(180px, 240px); justify-content: center; }
        .cancelled-card .booking-progress { margin-bottom: 24px; }
        .cancelled-card .progress-step.done { color: #aaa; }
        .cancelled-card .progress-step.done::before,
        .cancelled-card .progress-step.done::after { background: var(--color-primary); }
        .push-tip {
            position: relative; margin: 2px 18px 22px; padding: 16px 46px 16px 20px;
            border: 1px solid var(--color-primary); border-radius: 18px; background: #fff;
            font-size: 13px; font-weight: 600; line-height: 1.55;
        }
        .push-tip-close { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); border: 0; background: none; font-size: 22px; cursor: pointer; }

        /* Figma: upcoming treatment tab */
        #tab-active { padding: 20px 18px 4px; background: #f3f3f3; }
        .upcoming-booking {
            margin: 0 0 20px; padding: 28px 24px 28px; border: 0; background: #fff; cursor: pointer;
        }
        .upcoming-booking:focus-visible { outline: 2px solid var(--color-primary); outline-offset: -2px; }
        .upcoming-booking:first-of-type { padding-top: 28px; }
        .upcoming-booking + .upcoming-booking { padding-top: 28px; }
        .upcoming-booking .booking-card-top { margin-bottom: 20px; }
        .upcoming-booking .booking-card-date { color: #111; }
        .upcoming-booking .booking-detail-link { border-color: #df9aa6; color: #111; }
        .upcoming-booking .booking-summary { gap: 20px; }
        .upcoming-booking .booking-progress { margin: 34px 0 30px; }
        .upcoming-booking .progress-step.done { color: #aaa; }
        .upcoming-booking .progress-step.done::before,
        .upcoming-booking .progress-step.done::after { background: var(--color-primary); }
        .upcoming-booking .booking-card-actions { margin: 0; padding-top: 26px; }
        .upcoming-booking .booking-card-action { min-height: 50px; }

        .modal-overlay.bottom-sheet-modal { align-items: flex-end; z-index: 12000; }
        .modal-overlay.booking-change-sheet-overlay { display: none; position: fixed; inset: 0; align-items: flex-end; z-index: 9000; }
        .modal-overlay.booking-change-sheet-overlay.open { display: flex; }
        #changeConfirmModal .booking-motion-backdrop,
        #changeConfirmModal .booking-motion-stage { bottom: var(--booking-nav-height, 77px); }
        .change-confirm-box {
            width: 100%; max-width: 640px; padding: 14px 28px calc(30px + env(safe-area-inset-bottom,0px));
            background: #fff; text-align: center; animation: bookingModalUp .32s cubic-bezier(.32,.72,0,1);
        }
        .change-confirm-box.booking-motion-panel { padding: 0; text-align: center; animation: none; }
        @keyframes bookingModalUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        .change-confirm-box .booking-motion-body { padding-left: 28px; padding-right: 28px; padding-bottom: 18px; }
        .change-sheet-handle { width: 48px; height: 5px; border-radius: 3px; background: var(--color-primary); margin: 0 auto 48px; }
        .change-sheet-handle.booking-motion-handle { width: 100%; height: 38px; margin: 0; border-radius: 0; background: transparent; }
        .change-alert-icon {
            width: 54px; height: 54px; margin: 34px auto 28px; border-radius: 50%; background: var(--color-primary);
            color: #fff; display: flex; align-items: center; justify-content: center; font-size: 31px; font-weight: 300;
        }
        .change-confirm-title { font-size: 23px; font-weight: 700; line-height: 1.45; letter-spacing: -.04em; }
        .change-confirm-sub { margin-top: 14px; font-size: 18px; font-weight: 400; color: #4A4446; }
        .change-confirm-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 48px; }
        .change-confirm-actions.booking-motion-footer { margin-top: 0; }
        .change-confirm-btn { height: 52px; border: 1px solid var(--color-primary); border-radius: 4px; background: #fff; color: var(--color-primary); font-size: 19px; font-weight: 500; cursor: pointer; }
        .change-confirm-btn.primary { background: var(--color-primary); color: #fff; }

        /* 예약 취소 사유 바텀시트 */
        .modal-overlay.cancel-sheet-modal { align-items:flex-end; z-index:12000; }
        .cancel-sheet {
            width:100%; max-width:640px; padding:14px 28px calc(28px + env(safe-area-inset-bottom,0px));
            background:#fff; text-align:center; animation:bookingModalUp .32s cubic-bezier(.32,.72,0,1);
        }
        .cancel-sheet-handle { width:48px; height:5px; border-radius:3px; background:var(--color-primary); margin:0 auto 48px; }
        .cancel-sheet-title { font-size:23px; font-weight:750; line-height:1.4; letter-spacing:-.04em; }
        .cancel-sheet-sub { margin-top:14px; font-size:15px; font-weight:600; }
        .cancel-reason-grid {
            max-width:430px; margin:30px auto 18px; display:grid; grid-template-columns:1fr 1fr;
            gap:20px 36px; text-align:left;
        }
        .cancel-reason-option { display:flex; align-items:center; gap:10px; font-size:16px; cursor:pointer; }
        .cancel-reason-option input {
            appearance:none; -webkit-appearance:none; width:16px; height:16px; margin:0;
            border:1px solid #d2d2d2; background:#fff; display:grid; place-content:center;
        }
        .cancel-reason-option input::before { content:""; width:8px; height:8px; transform:scale(0); background:#fff; }
        .cancel-reason-option input:checked { border-color:#d6001c; background:#d6001c; }
        .cancel-reason-option input:checked::before { transform:scale(1); clip-path:polygon(14% 44%,0 65%,41% 100%,100% 20%,80% 0,38% 62%); }
        .cancel-custom-reason {
            width:100%; max-width:430px; height:52px; margin:0 auto; padding:0 16px;
            border:1px solid #d8d8d8; background:#fff; font-size:14px;
        }
        .cancel-custom-reason::placeholder { color:#bbb; }
        .cancel-request-btn {
            width:220px; height:54px; margin-top:26px; border:0; background:var(--color-primary);
            color:#fff; font-size:16px; font-weight:700; cursor:pointer;
        }
        .cancel-request-btn:disabled { opacity:.55; cursor:wait; }

        @media (max-width: 420px) {
            .tab-content { padding-left: 12px; padding-right: 12px; }
            .booking-card { padding-left: 16px; padding-right: 16px; }
            .booking-card-top { gap: 8px; }
            .booking-card-date { font-size: 14px; white-space: nowrap; }
            .booking-dday { font-size: 14px; white-space: nowrap; }
            .booking-detail-link { padding-left: 7px; padding-right: 7px; }
            .booking-summary { gap: 12px; }
            .booking-thumb { width: 64px; height: 64px; flex-basis: 64px; }
            .booking-card-actions, .change-confirm-actions { gap: 10px; }
            .cancelled-card .booking-card-date { font-size: 14px; }
            .cancelled-card .cancel-state { font-size: 12px; }
            .cancelled-card .booking-detail-link { padding-left: 6px; padding-right: 6px; font-size: 12px; }
            .cancel-sheet { padding-left:20px; padding-right:20px; }
            .cancel-reason-grid { gap:18px 14px; }
            .cancel-reason-option { font-size:14px; }
            #tab-active { padding-left: 14px; padding-right: 14px; }
            .upcoming-booking { padding-left: 16px; padding-right: 16px; }
            .upcoming-booking .booking-card-actions { gap: 12px; }
        }
        /* 시술완료/완료 확인중 Figma 목록 */
        #tab-past.active { background:#fff; }
        #tab-past .past-procedure-card { margin:0; padding:14px 0 28px; border-bottom:1px solid #ddd; background:#fff; }
        #tab-past .past-procedure-card + .past-procedure-card { padding-top:28px; }
        #tab-past .past-procedure-card .booking-header { margin-bottom:8px; }
        #tab-past .past-procedure-card .booking-date { font-size:18px; font-weight:500; }
        #tab-past .past-procedure-card .booking-detail { width:auto; min-width:0; min-height:70px; padding:12px 18px; }
        #tab-past .past-procedure-card .detail-btns { width:auto; min-width:0; flex:initial; flex-direction:row; align-items:center; justify-content:space-evenly; gap:4px; }
        #tab-past .past-procedure-card .detail-btn { flex:0 1 auto; padding:10px 4px; border-radius:0; font-size:18px; font-weight:500; white-space:nowrap; }
        @media(max-width:420px){#tab-past .past-procedure-card{padding-left:0;padding-right:0}#tab-past .past-procedure-card .booking-date,.completion-state{font-size:16px}#tab-past .past-procedure-card .booking-detail-row{grid-template-columns:minmax(0,1.75fr) minmax(124px,1fr)}#tab-past .past-procedure-card .booking-detail{width:auto;padding:10px 12px}#tab-past .past-procedure-card .detail-btns{width:auto;min-width:124px;flex-basis:auto;gap:0;overflow:hidden}#tab-past .past-procedure-card .detail-btn{min-width:0;padding:10px 1px;font-size:13px;letter-spacing:-.04em}}

        /* 05-37 결제 기한 만료 안내 시트 (시안 글자 +3px) */
        .ex-overlay { position:fixed; inset:0; z-index:12000; }
        .ex-overlay[hidden] { display:none; }
        .ex-dim { position:absolute; inset:0; background:rgba(26,23,24,.45); }
        .ex-panel { position:absolute; left:0; right:0; bottom:0; max-width:640px; margin:0 auto; background:#fff; border-radius:16px 16px 0 0; padding-bottom:calc(34px + env(safe-area-inset-bottom)); }
        .ex-handle { width:40px; height:4px; border-radius:2px; background:#AC1830; margin:10px auto 0; }
        .ex-body { padding:28px 20px 8px; display:flex; flex-direction:column; gap:14px; }
        .ex-icon { width:48px; height:48px; margin:0 auto; border-radius:50%; background:#AC1830; color:#fff; font-size:28px; font-weight:700; display:flex; align-items:center; justify-content:center; }
        .ex-title { font-size:23px; font-weight:700; line-height:1.45; text-align:center; color:#1A1718; }
        .ex-desc { font-size:18px; line-height:1.6; color:#4A4446; text-align:center; }
        .ex-actions { margin-top:14px; display:flex; gap:10px; }
        .ex-btn { flex:1 1 0; height:52px; border-radius:4px; border:1px solid #AC1830; background:#fff; color:#AC1830; font-size:19px; font-weight:500; display:flex; align-items:center; justify-content:center; text-decoration:none; font-family:inherit; }
        .ex-btn.primary { background:#AC1830; color:#fff; }
    </style>
    <script>
        (function(){
            var t = localStorage.getItem('theme');
            if (t === 'dark') document.documentElement.setAttribute('data-bs-theme', 'dark');
        })();
    </script>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="header">
    <button class="back" onclick="location.href='<?= htmlspecialchars($back_url, ENT_QUOTES) ?>'"><i class="bi bi-chevron-left"></i></button>
    <h1>내 예약/결제 내역</h1>
    <button type="button" class="global-nav-share" id="pageShareCopyBtn" aria-label="공유하기" title="공유하기">
        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
    </button>
</div>
<div class="share-copy-toast" id="pageShareCopyToast" role="status" aria-live="polite">링크가 복사되었습니다.</div>

<div class="container">
    <!-- 검색 (비로그인 시 또는 다른 번호 조회 시) -->
    <?php if (!$is_user_logged_in || isset($_GET['phone'])): ?>
   
    <?php endif; ?>

    <?php if (!empty($bookings)): ?>
    <!-- 탭 -->
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('active')">시술예정<?php
            // 신청해둔(진행중) 예약 건수 — 이 탭이 보여주는 $active_bookings 와 같은 기준.
            if (!empty($active_bookings)): ?><span class="tab-count"><?php echo count($active_bookings); ?></span><?php endif;
        ?></button>
        <button class="tab-btn" onclick="switchTab('completed')">시술완료</button>
        <button class="tab-btn" onclick="switchTab('cancelled')">예약취소</button>
    </div>

    <?php
    $show_push_hint = false;
    if (!empty($active_bookings) && !empty($logged_mb_id)) {
        $show_push_hint = true;
        $_pt = @mysqli_prepare($connect, "SELECT COUNT(*) FROM g5_push_tokens WHERE mb_id = ? AND pt_use = 1");
        $_has_token = false;
        if ($_pt) {
            mysqli_stmt_bind_param($_pt, 's', $logged_mb_id);
            mysqli_stmt_execute($_pt);
            mysqli_stmt_bind_result($_pt, $_cnt);
            mysqli_stmt_fetch($_pt);
            mysqli_stmt_close($_pt);
            $_has_token = ((int)$_cnt > 0);
        }
        $_prefs = ['noti_booking'=>1,'noti_push'=>1];
        $_ps = @mysqli_prepare($connect, "SELECT noti_booking, noti_push FROM gangnamunni_notification_settings WHERE member_id = ? LIMIT 1");
        if ($_ps) {
            mysqli_stmt_bind_param($_ps, 's', $logged_mb_id);
            if (mysqli_stmt_execute($_ps)) {
                $_pr = mysqli_fetch_assoc(mysqli_stmt_get_result($_ps));
                if ($_pr) $_prefs = ['noti_booking'=>(int)$_pr['noti_booking'], 'noti_push'=>(int)$_pr['noti_push']];
            }
            mysqli_stmt_close($_ps);
        }
        if ($_has_token && (int)$_prefs['noti_booking'] === 1 && (int)$_prefs['noti_push'] === 1) $show_push_hint = false;
    }
    ?>
    <!-- 진행중 -->
    <div class="tab-content active" id="tab-active">
        <?php if (empty($active_bookings)): ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <div>진행중인 예약이 없습니다.</div>
        </div>
        <?php else: ?>
        <?php foreach ($active_bookings as $bk): ?>
        <article class="upcoming-booking" id="card-<?php echo $bk['id']; ?>" data-status="<?php echo htmlspecialchars($bk['status']); ?>" data-booking-date="<?php echo htmlspecialchars($bk['booking_date'], ENT_QUOTES); ?>" data-booking-time="<?php echo htmlspecialchars(substr($bk['booking_time'], 0, 5), ENT_QUOTES); ?>" data-edits-left="<?php echo max(0, 3 - (int)($bk['edit_count'] ?? 0)); ?>" data-detail-url="booking_request_detail.php?booking_id=<?php echo (int)$bk['id']; ?>" role="link" tabindex="0" aria-label="예약 상세 보기">
            <?php
                $_days_ko = ['일','월','화','수','목','금','토'];
                $_ts = strtotime($bk['booking_date'] . ' ' . $bk['booking_time']);
                $_dow = $_days_ko[(int)date('w', $_ts)];
                $_h = (int)date('G', $_ts);
                $_ampm = $_h < 12 ? '오전' : '오후';
                $_h12 = $_h % 12; if ($_h12 === 0) $_h12 = 12;
                $_date_text = date('Y.n.j', $_ts) . '(' . $_dow . ') ' . $_ampm . ' ' . $_h12 . ':' . date('i', $_ts);
                $_dd = (int)(new DateTime('today'))->diff(new DateTime($bk['booking_date']))->format('%r%a');
                $_dd_text = $_dd > 0 ? 'D-' . $_dd : ($_dd === 0 ? 'D-0' : 'D+' . abs($_dd));
                $_edits_left = max(0, 3 - (int)($bk['edit_count'] ?? 0));
                $_booking_steps = ['예약 요청 접수', '병원 확인 중', '결제 대기', '예약 확정'];
                $_payment_state = booking_payment_state($bk, $payment_map);
                $_booking_active_step = 1;
                if (($bk['status'] ?? '') === 'hospital_checking') {
                    $_booking_active_step = 2;
                } elseif (($bk['status'] ?? '') === 'confirmed') {
                    $_booking_active_step = in_array($_payment_state, ['paid','partially_refunded','test_paid','preview'], true) ? 4 : 3;
                    if ($_payment_state === 'preview') $_booking_steps[3] = '결제 없이 진행';
                    if ($_payment_state === 'test_paid') $_booking_steps[3] = '테스트 결제';
                    if ($_payment_state === 'refunded') $_booking_steps[2] = '환불 완료';
                    if ($_payment_state === 'cancelled') $_booking_steps[2] = '결제 취소';
                }
            ?>
            <div class="booking-card-top">
                <span class="booking-card-date"><?php echo htmlspecialchars($_date_text); ?></span>
                <span class="booking-dday"><?php echo (($bk['status'] ?? '') === 'confirmed' && in_array($_payment_state, ['payable','preview'], true)) ? '결제 대기 · ' : '시술예정 '; ?><?php echo htmlspecialchars($_dd_text); ?></span>
                <a class="booking-detail-link" href="booking_request_detail.php?booking_id=<?php echo (int)$bk['id']; ?>">예약상세</a>
            </div>
            <div class="booking-summary">
                <div class="booking-thumb">
                    <?php if (!empty($bk['hospital_image'])): ?>
                    <img src="<?php echo htmlspecialchars($bk['hospital_image']); ?>" alt="">
                    <?php else: ?>
                    SEOUL<br>BEAUTY
                    <?php endif; ?>
                </div>
                <div class="booking-summary-copy">
                    <div class="booking-treatment"><?php echo htmlspecialchars($bk['treatment'] ?: '선택 시술'); ?></div>
                    <div class="booking-hospital"><?php echo htmlspecialchars($bk['hospital_name'] ?: '-'); ?></div>
                    <div class="booking-price"><?php echo number_format((int)($bk['price'] ?? 0)); ?>원</div>
                </div>
            </div>
            <div class="booking-progress" aria-label="예약 진행 상태">
                <?php foreach ($_booking_steps as $_booking_i => $_booking_label): ?>
                <div class="progress-step<?php echo ($_booking_i + 1) === $_booking_active_step ? ' active' : (($_booking_i + 1) < $_booking_active_step ? ' done' : ''); ?>"><?php echo $_booking_label; ?></div>
                <?php endforeach; ?>
            </div>
            <div class="booking-card-actions<?php echo in_array($_payment_state, ['paid','partially_refunded','test_paid'], true) ? ' paid-actions' : ''; ?>">
                <?php if (($bk['status'] ?? '') === 'confirmed' && in_array($_payment_state, ['payable','preview'], true)): ?>
                <button type="button" class="booking-card-action primary" onclick="event.stopPropagation(); location.href='booking_payment.php?booking_id=<?php echo (int)$bk['id']; ?>'">결제하기</button>
                <?php elseif (($bk['status'] ?? '') !== 'confirmed'): ?>
                <button type="button" class="booking-card-action primary" onclick="event.stopPropagation(); openChangeConfirm(<?php echo (int)$bk['id']; ?>, '<?php echo htmlspecialchars($bk['booking_date'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars(substr($bk['booking_time'], 0, 5), ENT_QUOTES); ?>', <?php echo $_edits_left; ?>)" <?php echo $_edits_left <= 0 ? 'disabled' : ''; ?>>예약변경</button>
                <?php endif; ?>
                <button type="button" class="booking-card-action" onclick="event.stopPropagation(); openCancelModal(<?php echo (int)$bk['id']; ?>)">예약취소</button>
            </div>
        </article>
        <?php endforeach; ?>
        <?php if ($show_push_hint): ?>
        <a class="push-hint" href="notification_settings.php">
            <span class="push-hint-ico" aria-hidden="true">🔔</span>
            <span class="push-hint-copy">기기 알림을 켜면 예약현황을 푸시로도 알려드려요<small>마이페이지 &gt; 설정 &gt; 알림 설정에서 예약 알림·푸시 알림을 켜주세요</small></span>
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- 지난 예약 (시술완료/예약취소) - JS로 필터링 -->
    <div class="tab-content" id="tab-past">
        <div class="empty-state empty-state-dynamic" style="display:none;">
            <div class="empty-icon">📦</div>
            <div>해당 항목이 없습니다.</div>
        </div>
        <?php foreach ($past_bookings as $bk):
            $status_class = 'status-' . $bk['status'];
            $status_labels = ['completed'=>'완료','cancelled'=>'취소완료','no_show'=>'노쇼'];
            $status_label = $status_labels[$bk['status']] ?? $bk['status'];
        ?>
        <?php if ($_is_cancelled = in_array($bk['status'], ['cancelled', 'no_show'], true)):
            $_cancel_payment = $payment_map[(int)$bk['id']] ?? null;
            $_no_charge = !$_cancel_payment || booking_payment_is_test($_cancel_payment['provider'] ?? null) || in_array($_cancel_payment['status'] ?? '', ['pending','preview','failed','cancelled'], true);
            $_refund_complete = $_no_charge || ($_cancel_payment['status'] ?? '') === 'refunded';
            $_cancel_steps = $_no_charge ? ['예약 취소 접수','병원 확인 중','취소 처리','예약 취소 완료'] : ['예약 취소 접수','병원 확인 중','환불 확인 중','환불 완료'];
            $_cancel_active = $_refund_complete ? 4 : 1;
            $_cancel_ts = strtotime($bk['booking_date'] . ' ' . $bk['booking_time']);
            $_cancel_days = ['일','월','화','수','목','금','토'];
            $_cancel_hour = (int)date('G', $_cancel_ts);
            $_cancel_hour12 = $_cancel_hour % 12 ?: 12;
        ?>
        <div class="booking-card cancelled-card" data-status="<?php echo htmlspecialchars($bk['status']); ?>">
            <div class="booking-card-top">
                <span class="booking-card-date"><?php echo date('Y.n.j', $_cancel_ts) . '(' . $_cancel_days[(int)date('w', $_cancel_ts)] . ') ' . ($_cancel_hour < 12 ? '오전 ' : '오후 ') . $_cancel_hour12 . ':' . date('i', $_cancel_ts); ?></span>
                <?php if ($_refund_complete): ?><span class="cancel-state"><?php echo $_no_charge ? '예약취소 완료' : '환불완료'; ?></span><?php endif; ?>
                <a class="booking-detail-link" href="booking_cancel_detail.php?booking_id=<?php echo (int)$bk['id']; ?>">예약상세</a>
            </div>
            <div class="booking-summary">
                <div class="booking-thumb"><?php if (!empty($bk['hospital_image'])): ?><img src="<?php echo htmlspecialchars($bk['hospital_image']); ?>" alt=""><?php else: ?>SEOUL<br>BEAUTY<?php endif; ?></div>
                <div class="booking-summary-copy"><div class="booking-treatment"><?php echo htmlspecialchars($bk['treatment'] ?: '-'); ?></div><div class="booking-hospital"><?php echo htmlspecialchars($bk['hospital_name'] ?: '-'); ?></div><div class="booking-price"><?php echo number_format((int)($bk['price'] ?? 0)); ?>원</div></div>
            </div>
            <div class="booking-progress" aria-label="예약 취소 진행 상태">
                <?php foreach ($_cancel_steps as $_cancel_i => $_cancel_label): ?><div class="progress-step<?php echo ($_cancel_i + 1) === $_cancel_active ? ' active' : (($_cancel_i + 1) < $_cancel_active ? ' done' : ''); ?>"><?php echo $_cancel_label; ?></div><?php endforeach; ?>
            </div>
            <div class="booking-card-actions"><a class="booking-card-action primary" href="booking.php?id=<?php echo (int)$bk['hospital_id']; ?>&amp;rebook=1&amp;source_booking_id=<?php echo (int)$bk['id']; ?>&amp;treatment=<?php echo urlencode((string)$bk['treatment']); ?>">다시예약</a></div>
        </div>
        <?php else: ?>
        <div class="booking-card past-procedure-card" data-status="<?php echo htmlspecialchars($bk['status']); ?>">
            <div class="booking-header">
                <div>
                    <div class="booking-date"><?php
                        $_days_ko = ['일','월','화','수','목','금','토'];
                        $_is_cancelled = in_array($bk['status'], ['cancelled', 'no_show']);
                        $_dt_source = $_is_cancelled ? $bk['created_at'] : ($bk['booking_date'] . ' ' . $bk['booking_time']);
                        $_ts = strtotime($_dt_source);
                        $_dow = $_days_ko[(int)date('w', $_ts)];
                        $_h = (int)date('G', $_ts);
                        $_ampm = $_h < 12 ? '오전' : '오후';
                        $_h12 = $_h % 12; if ($_h12 === 0) $_h12 = 12;
                        echo date('Y.n.j', $_ts) . '(' . $_dow . ') ' . $_ampm . ' ' . $_h12 . ':' . date('i', $_ts);
                    ?></div>
                    <span class="completion-state"><?php echo $bk['status'] === 'completed' ? '시술완료' : '완료 확인중'; ?></span>
                </div>
            </div>
            <div class="booking-detail-row">
                <div class="booking-detail bd-stacked bd-done"<?php if ($bk['status'] === 'completed'): ?> role="link" tabindex="0" style="cursor:pointer" onclick="location.href='booking_request_detail.php?booking_id=<?php echo (int)$bk['id']; ?>'" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();location.href='booking_request_detail.php?booking_id=<?php echo (int)$bk['id']; ?>'}"<?php endif; ?>>
                    <?php if (!empty($bk['treatment'])): ?>
                    <div class="detail-1"><?php echo htmlspecialchars($bk['treatment']); ?></div>
                    <?php endif; ?>
                    <div class="detail-2"><?php echo htmlspecialchars($bk['hospital_name']); ?></div>
                </div>
                <div class="detail-btns">
                    <?php if ($bk['status'] === 'completed'): ?>
                    <?php
                        $_rv = $review_map[(int)$bk['id']] ?? null;
                        $_rs = bookingReviewState($_rv);
                    ?>
                    <?php if ($_rs['state'] === 'none'): ?>
                    <a href="review_write.php?booking_id=<?php echo (int)$bk['id']; ?>&amp;return_url=<?php echo urlencode('my_bookings.php?tab=completed'); ?>" class="detail-btn"><?php echo term('review', '후기'); ?>작성</a>
                    <?php elseif ($_rs['state'] === 'published'): ?>
                    <a href="review_detail.php?id=<?php echo (int)$_rv['id']; ?>" class="detail-btn"><?php echo htmlspecialchars($_rs['label']); ?></a>
                    <?php elseif ($_rs['state'] === 'rejected'): ?>
                    <a href="review_write.php?id=<?php echo (int)$_rv['id']; ?>&amp;return_url=<?php echo urlencode('my_bookings.php?tab=completed'); ?>" class="detail-btn"><?php echo term('review', '후기'); ?> 다시 작성</a>
                    <?php else: ?>
                    <span class="detail-btn" style="opacity:.6;">⏳ <?php echo htmlspecialchars($_rs['label']); ?></span>
                    <?php endif; ?>
                    <a href="booking.php?id=<?php echo (int)$bk['hospital_id']; ?>&amp;rebook=1&amp;source_booking_id=<?php echo (int)$bk['id']; ?>&amp;treatment=<?php echo urlencode((string)$bk['treatment']); ?>" class="detail-btn">다시예약</a>
                    <?php else: ?>
                    <a href="booking.php?id=<?php echo (int)$bk['hospital_id']; ?>&amp;rebook=1&amp;source_booking_id=<?php echo (int)$bk['id']; ?>&amp;treatment=<?php echo urlencode((string)$bk['treatment']); ?>" class="detail-btn">다시예약</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php elseif (!empty($search_phone)): ?>
    <div class="empty-state">
        <div class="empty-icon">🔍</div>
        <div>해당 번호로 등록된 예약이 없습니다.</div>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">📱</div>
        <?php if ($is_user_logged_in): ?>
        <div>예약 내역이 없습니다.<br><a href="hospitals.php" style="color:var(--color-primary);font-weight:600;"><?php echo term('business', '병원'); ?> 찾아보기</a></div>
        <?php else: ?>
        <div>로그인하면 예약 내역을<br>자동으로 조회합니다.<br><br><a href="auth/login.php" style="color:var(--color-primary);font-weight:600;">로그인하기</a></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 예약 취소 사유 바텀시트 -->
<div class="modal-overlay cancel-sheet-modal" id="cancelModal" aria-hidden="true" onclick="if (event.target === this) closeModal('cancelModal')">
    <section class="cancel-sheet" role="dialog" aria-modal="true" aria-labelledby="cancelSheetTitle">
        <div class="cancel-sheet-handle" aria-hidden="true"></div>
        <h2 class="cancel-sheet-title" id="cancelSheetTitle">예약을 취소하시는 이유가 있으신가요?</h2>
        <p class="cancel-sheet-sub">보다 나은 서비스를 위해 의견을 들려주세요</p>
        <input type="hidden" id="cancelBookingId">
        <div class="cancel-reason-grid">
            <label class="cancel-reason-option"><input type="radio" name="cancel_reason_choice" value="일정 변경" checked><span>일정 변경</span></label>
            <label class="cancel-reason-option"><input type="radio" name="cancel_reason_choice" value="개인 사정"><span>개인 사정</span></label>
            <label class="cancel-reason-option"><input type="radio" name="cancel_reason_choice" value="다른 병원 예약"><span>다른 병원 예약</span></label>
            <label class="cancel-reason-option"><input type="radio" name="cancel_reason_choice" value="금액 / 시술 변경"><span>금액 / 시술 변경</span></label>
            <label class="cancel-reason-option"><input type="radio" name="cancel_reason_choice" value="기타"><span>기타</span></label>
        </div>
        <input class="cancel-custom-reason" id="cancelReasonCustom" type="text" maxlength="255" placeholder="직접 입력할게요 (선택)">
        <button type="button" class="cancel-request-btn" id="cancelRequestButton" onclick="submitCancel()">예약 취소 요청</button>
    </section>
</div>

<!-- 예약 변경 횟수 안내 바텀시트 -->
<div class="modal-overlay bottom-sheet-modal booking-motion-overlay booking-change-sheet-overlay" id="changeConfirmModal" tabindex="-1" aria-hidden="true" data-booking-motion-sheet data-booking-motion-name="bookingChangeConfirmMotion">
    <section class="change-confirm-box booking-motion-panel" role="dialog" aria-modal="true" aria-labelledby="changeConfirmTitle">
        <button type="button" class="change-sheet-handle booking-motion-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기"></button>
        <div class="booking-motion-body">
            <div class="change-alert-icon">!</div>
            <h2 class="change-confirm-title" id="changeConfirmTitle">병원의 예약 현황에 따라<br>일정 변경이 불가할 수도 있어요</h2>
            <p class="change-confirm-sub">변경하시겠어요?</p>
        </div>
    </section>
    <div class="change-confirm-actions booking-motion-footer">
        <button type="button" class="change-confirm-btn primary" onclick="closeModal('changeConfirmModal')">취소</button>
        <button type="button" class="change-confirm-btn" onclick="continueBookingChange()">변경</button>
    </div>
</div>

<!-- 날짜변경 모달 -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-title">예약 날짜 변경</div>
        <input type="hidden" id="editBookingId">
        <label style="font-size:13px;font-weight:600;">새 날짜</label>
        <input type="date" class="modal-input" id="editDate" min="<?php echo date('Y-m-d'); ?>">
        <label style="font-size:13px;font-weight:600;">새 시간</label>
        <input type="time" class="modal-input" id="editTime">
        <label style="font-size:13px;font-weight:600;">변경 사유</label>
        <input type="text" class="modal-input" id="editReason" placeholder="변경 사유 (선택)">
        <div class="modal-btns">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal('editModal')">닫기</button>
            <button class="modal-btn modal-btn-confirm" onclick="submitEdit()">변경하기</button>
        </div>
    </div>
</div>

<script src="assets/js/booking-sheets.js?v=<?php echo (int)@filemtime(__DIR__ . '/assets/js/booking-sheets.js'); ?>"></script>
<script>
var searchPhone = <?php echo json_encode($search_phone, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
var pendingBookingChange = null;
var pageShareToastTimer = null;

function fallbackPageShareCopy(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    var copied = document.execCommand('copy');
    document.body.removeChild(textarea);
    return copied;
}

function showPageShareToast(message) {
    var toast = document.getElementById('pageShareCopyToast');
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('is-visible');
    window.clearTimeout(pageShareToastTimer);
    pageShareToastTimer = window.setTimeout(function () {
        toast.classList.remove('is-visible');
    }, 1800);
}

document.getElementById('pageShareCopyBtn').addEventListener('click', function () {
    var shareText = '이거 완전 괜찮은 앱이야!\n서울미녀\n' + window.location.href;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(shareText).then(function () {
            showPageShareToast('링크가 복사되었습니다.');
        }).catch(function () {
            showPageShareToast(fallbackPageShareCopy(shareText) ? '링크가 복사되었습니다.' : '링크 복사에 실패했습니다.');
        });
        return;
    }
    showPageShareToast(fallbackPageShareCopy(shareText) ? '링크가 복사되었습니다.' : '링크 복사에 실패했습니다.');
});

function switchTab(tab) {
    // [Figma Sync 2026-06-06] 3탭: active(시술예정) / completed(시술완료) / cancelled(예약취소)
    var tabOrder = ['active','completed','cancelled'];
    var activeIdx = tabOrder.indexOf(tab);
    document.querySelectorAll('.tab-btn').forEach(function(b,i) { b.classList.toggle('active', i===activeIdx); });
    document.getElementById('tab-active').classList.toggle('active', tab==='active');
    var pastEl = document.getElementById('tab-past');
    if (pastEl) {
        pastEl.classList.toggle('active', tab==='completed' || tab==='cancelled');
        // 카드별 status로 필터링
        pastEl.querySelectorAll('.booking-card').forEach(function(card){
            var status = card.getAttribute('data-status') || '';
            var isCancelled = (status === 'cancelled' || status === 'no_show');
            var show = (tab==='completed' && !isCancelled) || (tab==='cancelled' && isCancelled);
            card.style.display = show ? '' : 'none';
        });
        // 빈 메시지 처리
        var visible = pastEl.querySelectorAll('.booking-card:not([style*="display: none"])').length;
        var emptyEl = pastEl.querySelector('.empty-state-dynamic');
        if (emptyEl) emptyEl.style.display = visible === 0 ? 'block' : 'none';
    }
}

var requestedTab = new URLSearchParams(window.location.search).get('tab');
if (requestedTab === 'active' || requestedTab === 'completed' || requestedTab === 'cancelled') {
    switchTab(requestedTab);
}

document.querySelectorAll('.upcoming-booking[data-detail-url]').forEach(function(card) {
    function goDetail() {
        var url = card.getAttribute('data-detail-url');
        if (url) location.href = url;
    }
    card.addEventListener('click', function(event) {
        if (event.target.closest('a,button')) return;
        goDetail();
    });
    card.addEventListener('keydown', function(event) {
        if (event.target.closest('a,button')) return;
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            goDetail();
        }
    });
});

// Detail page actions return here and continue through the existing,
// CSRF-protected change/cancel modal flows.
var actionParams = new URLSearchParams(window.location.search);
var requestedAction = actionParams.get('action');
var requestedBookingId = parseInt(actionParams.get('booking_id') || '0', 10);
if ((requestedAction === 'change' || requestedAction === 'cancel') && requestedBookingId > 0) {
    var requestedCard = document.getElementById('card-' + requestedBookingId);
    if (requestedCard) {
        window.setTimeout(function () {
            if (requestedAction === 'change') {
                openChangeConfirm(
                    requestedBookingId,
                    requestedCard.getAttribute('data-booking-date') || '',
                    requestedCard.getAttribute('data-booking-time') || '',
                    parseInt(requestedCard.getAttribute('data-edits-left') || '0', 10)
                );
            } else {
                openCancelModal(requestedBookingId);
            }
            actionParams.delete('action');
            actionParams.delete('booking_id');
            var cleanQuery = actionParams.toString();
            history.replaceState(null, '', window.location.pathname + (cleanQuery ? '?' + cleanQuery : ''));
        }, 0);
    }
}

function openCancelModal(id) {
    document.getElementById('cancelBookingId').value = id;
    var reasons = document.querySelectorAll('input[name="cancel_reason_choice"]');
    if (reasons.length) reasons[0].checked = true;
    document.getElementById('cancelReasonCustom').value = '';
    var requestButton = document.getElementById('cancelRequestButton');
    requestButton.disabled = false;
    requestButton.textContent = '예약 취소 요청';
    var modal = document.getElementById('cancelModal');
    modal.classList.add('active');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function openEditModal(id, date, time) {
    document.getElementById('editBookingId').value = id;
    document.getElementById('editDate').value = date;
    document.getElementById('editTime').value = time;
    document.getElementById('editReason').value = '';
    document.getElementById('editModal').classList.add('active');
}

function openChangeConfirm(id, date, time, remaining) {
    if (remaining <= 0) {
        alert('예약 변경 가능 횟수(3회)를 모두 사용했습니다.');
        return;
    }
    pendingBookingChange = { id: id, date: date, time: time };
    var remainingCount = document.getElementById('changeRemainingCount');
    if (remainingCount) remainingCount.textContent = remaining;
    var modal = document.getElementById('changeConfirmModal');
    if (window.bookingChangeConfirmMotion) {
        window.bookingChangeConfirmMotion.open();
    } else {
        modal.classList.add('active');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
}

function continueBookingChange() {
    if (!pendingBookingChange) return;
    var change = pendingBookingChange;
    Promise.resolve(closeModal('changeConfirmModal')).then(function () {
        // [05-42] 예약 변경 전용 페이지로 이동 (기존 날짜/시간이 선택된 상태로 열린다)
        window.location.href = 'booking_change.php?booking_id=' + encodeURIComponent(change.id);
    });
}

function closeModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return Promise.resolve();
    if (id === 'changeConfirmModal' && window.bookingChangeConfirmMotion) {
        return window.bookingChangeConfirmMotion.close();
    }
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    return Promise.resolve();
}

function submitCancel() {
    var id = document.getElementById('cancelBookingId').value;
    var selectedReason = document.querySelector('input[name="cancel_reason_choice"]:checked');
    var customReason = document.getElementById('cancelReasonCustom').value.trim();
    var reason = selectedReason ? selectedReason.value : '';
    if (customReason) reason += (reason ? ': ' : '') + customReason;
    var requestButton = document.getElementById('cancelRequestButton');
    requestButton.disabled = true;
    requestButton.textContent = '요청 중...';
    var form = document.createElement('form');
    form.method = 'post';
    form.action = 'booking_cancel_request.php';
    [['csrf_token', <?php echo json_encode($my_bookings_csrf); ?>], ['booking_id', id], ['cancel_reason', reason]].forEach(function(item) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = item[0];
        input.value = item[1];
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

function submitEdit() {
    var id = document.getElementById('editBookingId').value;
    var newDate = document.getElementById('editDate').value;
    var newTime = document.getElementById('editTime').value;
    var reason = document.getElementById('editReason').value;

    if (!newDate || !newTime) { alert('날짜와 시간을 선택해주세요.'); return; }

    var fd = new FormData();
    fd.append('csrf_token', <?php echo json_encode($my_bookings_csrf); ?>);
    fd.append('booking_id', id);
    fd.append('phone', searchPhone);
    fd.append('new_date', newDate);
    fd.append('new_time', newTime);
    fd.append('edit_reason', reason);

    fetch('api/edit_booking.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(function() { alert('네트워크 오류가 발생했습니다.'); });

    closeModal('editModal');
}
</script>

<?php include __DIR__ . '/includes/bottom_nav.php'; ?>
<?php if ($expired_notice): ?>
<div class="ex-overlay" id="expireNotice" role="dialog" aria-modal="true" aria-labelledby="expireTitle">
    <div class="ex-dim" id="expireDim"></div>
    <div class="ex-panel">
        <div class="ex-handle"></div>
        <div class="ex-body">
            <div class="ex-icon" aria-hidden="true">!</div>
            <div class="ex-title" id="expireTitle">결제 기한이 지나<br>예약이 취소되었어요</div>
            <div class="ex-desc">병원 확인 후 <?php echo max(1, min(168, (int)getSettingValue($connect, 'payment_deadline_hours', '24') ?: 24)); ?>시간 안에 결제하지 않으면<br>예약이 자동으로 취소돼요. 다시 예약해주세요.</div>
            <div class="ex-actions">
                <button type="button" class="ex-btn" id="expireOk">확인했어요</button>
                <a class="ex-btn primary" id="expireRebook" href="booking.php?id=<?php echo (int)$expired_notice['hospital_id']; ?>&amp;rebook=1&amp;source_booking_id=<?php echo (int)$expired_notice['id']; ?>&amp;treatment=<?php echo urlencode((string)$expired_notice['treatment']); ?>">다시 예약하기</a>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var overlay = document.getElementById('expireNotice');
    if (!overlay) return;
    var bookingId = <?php echo (int)$expired_notice['id']; ?>;
    function markSeen() {
        var fd = new FormData();
        fd.append('action', 'expire_seen');
        fd.append('csrf_token', <?php echo json_encode($my_bookings_csrf); ?>);
        fd.append('booking_id', String(bookingId));
        return fetch('my_bookings.php', {method:'POST', body:fd, credentials:'same-origin', keepalive:true}).catch(function() {});
    }
    function close() { overlay.hidden = true; }
    document.getElementById('expireOk').addEventListener('click', function() { markSeen(); close(); });
    document.getElementById('expireDim').addEventListener('click', function() { markSeen(); close(); });
    document.getElementById('expireRebook').addEventListener('click', function() { markSeen(); });
})();
</script>
<?php endif; ?>
</body>
</html>
