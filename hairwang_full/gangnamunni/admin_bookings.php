<?php
session_start();

$admin_page_title = '예약 관리';
$admin_current = 'bookings';


require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_filter.php'; 
require_once __DIR__ . '/includes/photo_upload_helper.php';
require_admin_login();
require_permission('bookings');
if (empty($_SESSION['admin_bookings_csrf'])) {
    $_SESSION['admin_bookings_csrf'] = bin2hex(random_bytes(32));
}
$admin_bookings_csrf = $_SESSION['admin_bookings_csrf'];

$_booking_schema_ok = true;
foreach (['doctor_id', 'doctor_name'] as $_col) {
    try {
        $_chk = mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_bookings LIKE '{$_col}'");
        if (!$_chk || mysqli_num_rows($_chk) === 0) $_booking_schema_ok = false;
    } catch (Throwable $e) {
        $_booking_schema_ok = false;
    }
}
if (!$_booking_schema_ok) {
    error_log('[admin_bookings] booking schema is outdated; run gangnamunni/sql/bookings_schema.sql');
    http_response_code(503);
    exit('예약 스키마 업데이트가 필요합니다. 관리자에게 문의해주세요.');
}

$booking_hospital_ids = get_strict_admin_hospital_ids($connect);
$booking_hosp_in = $booking_hospital_ids === null
    ? null
    : (empty($booking_hospital_ids) ? '0' : implode(',', array_map('intval', $booking_hospital_ids)));

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'export_hospital') {
    $export_hospital_id = max(0, (int)($_GET['hospital_id'] ?? 0));
    $export_allowed = $export_hospital_id > 0
        && ($booking_hospital_ids === null || in_array($export_hospital_id, $booking_hospital_ids, true));
    if (!$export_allowed) {
        http_response_code(403);
        exit('해당 병원의 예약을 다운로드할 권한이 없습니다.');
    }

    $hospital_stmt = mysqli_prepare($connect, "SELECT name FROM gangnamunni_hospitals WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($hospital_stmt, 'i', $export_hospital_id);
    mysqli_stmt_execute($hospital_stmt);
    $export_hospital = mysqli_fetch_assoc(mysqli_stmt_get_result($hospital_stmt));
    mysqli_stmt_close($hospital_stmt);
    if (!$export_hospital) {
        http_response_code(404);
        exit('병원을 찾을 수 없습니다.');
    }

    $export_stmt = mysqli_prepare($connect,
        "SELECT id, hospital_name, name, phone, treatment, booking_date, booking_time,
                status, doctor_name, price, memo, cancel_reason, created_at
         FROM gangnamunni_bookings WHERE hospital_id=? ORDER BY created_at DESC");
    mysqli_stmt_bind_param($export_stmt, 'i', $export_hospital_id);
    mysqli_stmt_execute($export_stmt);
    $export_result = mysqli_stmt_get_result($export_stmt);
    $export_rows = [];
    $export_status_labels = ['pending'=>'대기중', 'confirmed'=>'확정', 'completed'=>'완료', 'cancelled'=>'취소', 'no_show'=>'노쇼'];
    while ($export_row = mysqli_fetch_assoc($export_result)) {
        $export_row['status'] = $export_status_labels[$export_row['status']] ?? $export_row['status'];
        $export_rows[] = $export_row;
    }
    mysqli_stmt_close($export_stmt);

    require_once __DIR__ . '/includes/excel_engine.php';
    $export_headers = [
        ['key'=>'id', 'label'=>'예약 ID'], ['key'=>'hospital_name', 'label'=>'병원'],
        ['key'=>'name', 'label'=>'예약자'], ['key'=>'phone', 'label'=>'전화번호'],
        ['key'=>'treatment', 'label'=>'시술'], ['key'=>'booking_date', 'label'=>'예약일'],
        ['key'=>'booking_time', 'label'=>'예약시간'], ['key'=>'status', 'label'=>'상태'],
        ['key'=>'doctor_name', 'label'=>'담당의사'], ['key'=>'price', 'label'=>'금액'],
        ['key'=>'memo', 'label'=>'메모'], ['key'=>'cancel_reason', 'label'=>'취소 사유'],
        ['key'=>'created_at', 'label'=>'접수일시'],
    ];
    $export_filename = 'hospital_' . $export_hospital_id . '_bookings_' . date('Ymd_His') . '.xlsx';
    $export_path = exportToExcel($export_rows, $export_headers, $export_filename);
    if (!$export_path || !is_file($export_path)) {
        http_response_code(500);
        exit('엑셀 파일 생성에 실패했습니다.');
    }
    $export_download_name = basename($export_path);
    $export_extension = strtolower(pathinfo($export_path, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($export_extension === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    header('Content-Disposition: attachment; filename="' . $export_download_name . '"');
    header('Content-Length: ' . filesize($export_path));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($export_path);
    @unlink($export_path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'export_all_monthly') {
    $monthly_where = "booking_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
    if ($booking_hosp_in !== null) $monthly_where .= " AND hospital_id IN (" . $booking_hosp_in . ")";

    $monthly_result = mysqli_query($connect,
        "SELECT id, hospital_id, hospital_name, name, phone, treatment, booking_date, booking_time,
                status, doctor_name, price, memo, cancel_reason, created_at
         FROM gangnamunni_bookings WHERE {$monthly_where}
         ORDER BY hospital_name ASC, created_at DESC");
    $monthly_status_labels = ['pending'=>'대기중', 'confirmed'=>'확정', 'completed'=>'완료', 'cancelled'=>'취소', 'no_show'=>'노쇼'];
    $monthly_sheets = [];
    if ($monthly_result) {
        while ($monthly_row = mysqli_fetch_assoc($monthly_result)) {
            $monthly_row['status'] = $monthly_status_labels[$monthly_row['status']] ?? $monthly_row['status'];
            $monthly_sheet_title = trim((string)$monthly_row['hospital_name']) !== ''
                ? $monthly_row['hospital_name'] : ('병원#' . $monthly_row['hospital_id']);
            $monthly_sheets[$monthly_sheet_title][] = $monthly_row;
        }
    }

    require_once __DIR__ . '/includes/excel_engine.php';
    $monthly_headers = [
        ['key'=>'id', 'label'=>'예약 ID'], ['key'=>'hospital_name', 'label'=>'병원'],
        ['key'=>'name', 'label'=>'예약자'], ['key'=>'phone', 'label'=>'전화번호'],
        ['key'=>'treatment', 'label'=>'시술'], ['key'=>'booking_date', 'label'=>'예약일'],
        ['key'=>'booking_time', 'label'=>'예약시간'], ['key'=>'status', 'label'=>'상태'],
        ['key'=>'doctor_name', 'label'=>'담당의사'], ['key'=>'price', 'label'=>'금액'],
        ['key'=>'memo', 'label'=>'메모'], ['key'=>'cancel_reason', 'label'=>'취소 사유'],
        ['key'=>'created_at', 'label'=>'접수일시'],
    ];
    $monthly_filename = 'all_hospitals_monthly_bookings_' . date('Ymd_His') . '.xlsx';
    $monthly_path = exportToExcelMultiSheet($monthly_sheets, $monthly_headers, $monthly_filename);
    if (!$monthly_path || !is_file($monthly_path)) {
        http_response_code(500);
        exit('엑셀 파일 생성에 실패했습니다.');
    }
    $monthly_download_name = basename($monthly_path);
    $monthly_extension = strtolower(pathinfo($monthly_path, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($monthly_extension === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    header('Content-Disposition: attachment; filename="' . $monthly_download_name . '"');
    header('Content-Length: ' . filesize($monthly_path));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($monthly_path);
    @unlink($monthly_path);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (empty($_POST['admin_csrf']) || !hash_equals($admin_bookings_csrf, (string)$_POST['admin_csrf'])) {
        http_response_code(403);
        exit('요청이 만료되었습니다. 페이지를 새로고침해주세요.');
    }
    $admin_role = $_SESSION['ad_admin_role'] ?? 'viewer';
    if ($admin_role === 'viewer') {
        $msg = '뷰어 역할은 예약 상태를 변경할 수 없습니다.';
        $msg_type = 'danger';
        goto skip_action;
    }
    $id = intval($_POST['id'] ?? 0);
    $action = $_POST['action'];
    $allowed = ['pending', 'confirm', 'cancel', 'complete', 'noshow'];
    $_act_hin = $booking_hosp_in;
    if ($id > 0 && $_act_hin !== null) {
        $_own_r = mysqli_query($connect, "SELECT 1 FROM gangnamunni_bookings WHERE id={$id} AND hospital_id IN (" . $_act_hin . ") LIMIT 1");
        if (!$_own_r || mysqli_num_rows($_own_r) === 0) {
            $msg = '해당 예약에 대한 권한이 없습니다.';
            $msg_type = 'danger';
            goto skip_action;
        }
    }
    if ($id > 0 && in_array($action, $allowed)) {
        $status_map = ['pending' => 'pending', 'confirm' => 'confirmed', 'cancel' => 'cancelled', 'complete' => 'completed', 'noshow' => 'no_show'];
        $new_status = $status_map[$action];
        $current_scope = $_act_hin !== null ? " AND hospital_id IN ({$_act_hin})" : '';
        $current_result = mysqli_query($connect,
            "SELECT status FROM gangnamunni_bookings WHERE id={$id}{$current_scope} LIMIT 1");
        $current_row = $current_result ? mysqli_fetch_assoc($current_result) : null;
        $current_status = (string)($current_row['status'] ?? '');
        $is_super_admin = ($admin_role === 'super_admin');
        $is_hospital_admin = in_array($admin_role, ['hospital_super_admin', 'hospital_admin', 'hospital_sub_admin'], true);
        $can_override_status = $is_super_admin || $is_hospital_admin;
        $normal_transitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['completed', 'no_show'],
        ];

        if ($current_status === '' || $current_status === $new_status) {
            $msg = '예약 상태를 변경할 수 없습니다.';
            $msg_type = 'warning';
            goto skip_action;
        }
        if (!$can_override_status && !in_array($new_status, $normal_transitions[$current_status] ?? [], true)) {
            $msg = '해당 예약 상태를 수정할 권한이 없습니다.';
            $msg_type = 'danger';
            goto skip_action;
        }

        $sql = "UPDATE gangnamunni_bookings SET status='" . mysqli_real_escape_string($connect, $new_status) . "'";
        if ($action === 'cancel') {
            $reason = mysqli_real_escape_string($connect, $_POST['cancel_reason'] ?? '');
            $sql .= ", cancel_reason='" . $reason . "', cancelled_at=NOW()";
        } elseif ($current_status === 'cancelled') {
            $sql .= ", cancel_reason=NULL, cancelled_at=NULL";
        }
        $sql .= " WHERE id=" . $id;
        if ($_act_hin !== null) {
            $sql .= " AND hospital_id IN (" . $_act_hin . ")";
        }
        require_once __DIR__ . '/includes/points_ledger.php';

        $booking_row = null;
        mysqli_begin_transaction($connect);
        try {
            if (!mysqli_query($connect, $sql) || mysqli_affected_rows($connect) !== 1) {
                throw new RuntimeException('booking_status_update_failed');
            }

            $bk_r = mysqli_query($connect, "SELECT b.*, h.hms_hospital_id FROM gangnamunni_bookings b LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id WHERE b.id = {$id}");
            if ($bk_r) $booking_row = mysqli_fetch_assoc($bk_r);
            if (!$booking_row) throw new RuntimeException('booking_reload_failed');

            if (!empty($booking_row['hms_appointment_id'])) {
                $hms_apt_id = intval($booking_row['hms_appointment_id']);
                $hms_status_map = ['confirmed' => 0, 'completed' => 1, 'cancelled' => 2, 'no_show' => 2];
                $hms_is_completed = $hms_status_map[$new_status] ?? 0;
                if (!mysqli_query($connect, "UPDATE appointments SET is_completed = {$hms_is_completed}, updated_at = NOW() WHERE id = {$hms_apt_id}")) {
                    throw new RuntimeException('local_hms_status_update_failed');
                }
            }

            mysqli_commit($connect);
        } catch (Throwable $e) {
            mysqli_rollback($connect);
            error_log('[admin_bookings] atomic status update failed: ' . $e->getMessage());
            $msg = '예약 상태 변경에 실패했습니다. 다시 시도해주세요.';
            $msg_type = 'danger';
            goto skip_action;
        }

        if (!empty($booking_row['mb_id'])) {
            try {
                $bk_mb_no = points_member_no($connect, $booking_row['mb_id']);
                if ($bk_mb_no > 0) {
                    if ($new_status === 'confirmed') {
                        points_earn_booking($connect, $bk_mb_no, $id, (int)($booking_row['price'] ?? 0));
                    } elseif ($new_status === 'cancelled') {
                        points_cancel_booking($connect, $bk_mb_no, $id);
                    }
                }
            } catch (\Throwable $e) {
                error_log('[admin_bookings] point processing failed (booking already committed): ' . $e->getMessage());
            }
        }

        if ($new_status === 'confirmed' && file_exists(__DIR__ . '/includes/payment_core.php')) {
            try {
                require_once __DIR__ . '/includes/payment_core.php';
                platform_payment_create_booking($connect, $id);
            } catch (\Throwable $e) {
                error_log('[admin_bookings] booking payment order not created (booking already committed): ' . $e->getMessage());
            }
        }

        $hms_sync_warning = false;
        if ($booking_row && empty($booking_row['hms_appointment_id']) && !empty($booking_row['hms_hospital_id']) && $new_status === 'confirmed') {
            if (!function_exists("is_hms_standalone") || !is_hms_standalone()) { require_once __DIR__ . "/includes/infyhms_api.php";
            try {
                $hms_api = get_infyhms_api();
                $apt_data = [
                    'patient_name'  => $booking_row['name'] ?? '',
                    'patient_phone' => $booking_row['phone'] ?? '',
                    'appointment_datetime' => ($booking_row['booking_date'] ?? date('Y-m-d')) . ' ' . ($booking_row['booking_time'] ?? '10:00') . ':00',
                    'problem'       => '[서울미녀 예약] 시술: ' . ($booking_row['treatment'] ?? ''),
                    'tenant_id'     => $booking_row['hms_hospital_id'],
                ];
                $hms_result = $hms_api->createAppointment($apt_data);
                if (!empty($hms_result['success']) && !empty($hms_result['data']['id'])) {
                    $new_hms_id = intval($hms_result['data']['id']);
                    if (!mysqli_query($connect, "UPDATE gangnamunni_bookings SET hms_appointment_id = {$new_hms_id} WHERE id = {$id}")) {
                        $hms_sync_warning = true;
                        error_log("[admin_bookings] HMS appointment ID 저장 실패: booking_id={$id}, hms_id={$new_hms_id}");
                    }
                } else {
                    $hms_sync_warning = true;
                    error_log("[admin_bookings] HMS API 응답 실패: booking_id={$id}");
                }
            } catch (Exception $e) {
                $hms_sync_warning = true;
                error_log("[admin_bookings] HMS API 전송 실패: " . $e->getMessage());
            }
            } 
        }

        $patient_member_id = trim((string)($booking_row['mb_id'] ?? ''));
        if ($new_status === 'confirmed' && $booking_row && $patient_member_id === '') {
            $patient_phone = preg_replace('/[^0-9]/', '', (string)($booking_row['phone'] ?? ''));
            $member_table = $g5['member_table'] ?? 'g5_member';
            if ($patient_phone !== '' && preg_match('/^[A-Za-z0-9_]+$/', $member_table)) {
                $member_stmt = mysqli_prepare($connect,
                    "SELECT mb_id FROM {$member_table} WHERE REPLACE(REPLACE(mb_hp, '-', ''), ' ', '') = ? LIMIT 1");
                if ($member_stmt) {
                    mysqli_stmt_bind_param($member_stmt, 's', $patient_phone);
                    mysqli_stmt_execute($member_stmt);
                    $member_row = mysqli_fetch_assoc(mysqli_stmt_get_result($member_stmt));
                    mysqli_stmt_close($member_stmt);
                    $patient_member_id = trim((string)($member_row['mb_id'] ?? ''));
                }
            }
        }
        if ($new_status === 'confirmed' && $booking_row && $patient_member_id !== '') {
            try {
                require_once __DIR__ . '/includes/user_notifications.php';
                require_once __DIR__ . '/includes/admin_fcm.php';

                $notification_title = '예약이 확정되었습니다';
                $notification_body = trim(
                    ($booking_row['hospital_name'] ?? '') . ' · '
                    . ($booking_row['booking_date'] ?? '') . ' '
                    . substr((string)($booking_row['booking_time'] ?? ''), 0, 5)
                );
                $notification_link = SITE_FULL_URL . 'my_appointments.php';

                create_user_notification(
                    $connect,
                    $patient_member_id,
                    'general',
                    $notification_title,
                    $notification_body,
                    $notification_link
                );

                $patient_push_result = send_push_to_members(
                    $connect,
                    [$patient_member_id],
                    $notification_title,
                    $notification_body,
                    $notification_link
                );

                error_log(
                    '[admin_bookings] patient approval push: booking_id=' . $id
                    . ', member_id=' . $patient_member_id
                    . ', sent=' . (int)($patient_push_result['sent'] ?? 0)
                    . ', failed=' . (int)($patient_push_result['failed'] ?? 0)
                );
            } catch (Throwable $e) {
                error_log(
                    '[admin_bookings] patient notification failed (booking already confirmed): '
                    . $e->getMessage()
                );
            }
        }

        $msg = $hms_sync_warning
            ? '예약 상태는 변경되었지만 HMS 동기화에 실패했습니다. 로그를 확인하고 재동기화해주세요.'
            : '예약 상태가 변경되었습니다.';
        $msg_type = $hms_sync_warning ? 'warning' : 'success';
    }

    if ($id > 0 && $action === 'assign_doctor') {
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        $bk_h = mysqli_query($connect, "SELECT hospital_id FROM gangnamunni_bookings WHERE id={$id} LIMIT 1");
        $bk_hosp = $bk_h ? (int)(mysqli_fetch_assoc($bk_h)['hospital_id'] ?? 0) : 0;
        if ($doctor_id > 0) {
            $doc_r = mysqli_query($connect, "SELECT name, hospital_id FROM gangnamunni_doctors WHERE id={$doctor_id} LIMIT 1");
            $doc = $doc_r ? mysqli_fetch_assoc($doc_r) : null;
            if ($doc && (int)$doc['hospital_id'] === $bk_hosp) {
                $dn = mysqli_real_escape_string($connect, $doc['name']);
                $assign_scope = $_act_hin !== null ? " AND hospital_id IN ({$_act_hin})" : '';
                mysqli_query($connect, "UPDATE gangnamunni_bookings SET doctor_id={$doctor_id}, doctor_name='{$dn}' WHERE id={$id}{$assign_scope}");
                $msg = '담당의사가 배정되었습니다.';
                $msg_type = 'success';
            } else {
                $msg = '선택한 의사가 해당 예약의 병원 소속이 아닙니다.';
                $msg_type = 'danger';
            }
        } else {
            $assign_scope = $_act_hin !== null ? " AND hospital_id IN ({$_act_hin})" : '';
            mysqli_query($connect, "UPDATE gangnamunni_bookings SET doctor_id=NULL, doctor_name=NULL WHERE id={$id}{$assign_scope}");
            $msg = '담당의사 배정이 해제되었습니다.';
            $msg_type = 'success';
        }
    }
}
skip_action:

$is_super_admin_role = (($_SESSION['ad_admin_role'] ?? '') === 'super_admin');
$allowed_filter_statuses = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
$filter_status = (string)($_GET['status'] ?? '');
if (!in_array($filter_status, $allowed_filter_statuses, true)) $filter_status = '';
$filter_date = trim((string)($_GET['date'] ?? ''));
if ($filter_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) $filter_date = '';
$search = trim((string)($_GET['q'] ?? ''));
$filter_hospital = $is_super_admin_role ? max(0, intval($_GET['hospital_id'] ?? 0)) : 0;
$show_all_hospitals = $is_super_admin_role && (($_GET['view'] ?? '') === 'all');
$open_hospital_id = $is_super_admin_role ? max(0, (int)($_POST['open_hospital_id'] ?? 0)) : 0;
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$hospital_options = [];
if ($is_super_admin_role) {
    $hospital_result = mysqli_query($connect,
        "SELECT h.id, h.name,
                COUNT(b.id) AS booking_count,
                SUM(CASE WHEN b.status='pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN b.status='confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
                SUM(CASE WHEN b.status='completed' THEN 1 ELSE 0 END) AS completed_count,
                SUM(CASE WHEN b.status='cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN b.status='no_show' THEN 1 ELSE 0 END) AS no_show_count,
                SUM(CASE WHEN b.booking_date=CURDATE() THEN 1 ELSE 0 END) AS today_count
         FROM gangnamunni_hospitals h
         LEFT JOIN gangnamunni_bookings b ON b.hospital_id = h.id
         GROUP BY h.id, h.name
         ORDER BY h.name ASC");
    if ($hospital_result) {
        while ($hospital = mysqli_fetch_assoc($hospital_result)) {
            $hospital_options[] = $hospital;
        }
    }

    if ($filter_hospital > 0) {
        $valid_hospital_ids = array_map('intval', array_column($hospital_options, 'id'));
        if (!in_array($filter_hospital, $valid_hospital_ids, true)) {
            $filter_hospital = 0;
        }
    }
}
$show_booking_list = !$is_super_admin_role || $filter_hospital > 0 || $show_all_hospitals;

$bookings_by_hospital = [];
if ($is_super_admin_role && !$show_booking_list) {
    $grouped_booking_result = mysqli_query($connect,
        "SELECT id, hospital_id, name, phone, treatment, booking_date, booking_time, status, doctor_id, doctor_name
         FROM gangnamunni_bookings
         WHERE booking_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
         ORDER BY hospital_id ASC, created_at DESC");
    if ($grouped_booking_result) {
        while ($grouped_booking = mysqli_fetch_assoc($grouped_booking_result)) {
            $bookings_by_hospital[(int)$grouped_booking['hospital_id']][] = $grouped_booking;
        }
    }
}

$where = "1=1";
if ($filter_status) $where .= " AND b.status='" . mysqli_real_escape_string($connect, $filter_status) . "'";
if ($filter_date) $where .= " AND b.booking_date='" . mysqli_real_escape_string($connect, $filter_date) . "'";
if ($search) {
    $s = mysqli_real_escape_string($connect, $search);
    $where .= " AND (b.name LIKE '%" . $s . "%' OR b.phone LIKE '%" . $s . "%' OR b.hospital_name LIKE '%" . $s . "%')";
}
if ($filter_hospital > 0) {
    $where .= " AND b.hospital_id=" . $filter_hospital;
}

$hosp_in = $booking_hosp_in;
$hosp_and = ($hosp_in !== null) ? " AND hospital_id IN (" . $hosp_in . ")" : "";
if ($hosp_and) $where .= $hosp_and;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'export_list') {
    $list_export_stmt = mysqli_prepare($connect,
        "SELECT id, hospital_name, name, phone, treatment, booking_date, booking_time,
                status, doctor_name, price, memo, cancel_reason, created_at
         FROM gangnamunni_bookings b WHERE " . $where . " ORDER BY b.created_at DESC");
    mysqli_stmt_execute($list_export_stmt);
    $list_export_result = mysqli_stmt_get_result($list_export_stmt);
    $list_export_rows = [];
    $list_export_status_labels = ['pending'=>'대기중', 'confirmed'=>'확정', 'completed'=>'완료', 'cancelled'=>'취소', 'no_show'=>'노쇼'];
    while ($list_export_row = mysqli_fetch_assoc($list_export_result)) {
        $list_export_row['status'] = $list_export_status_labels[$list_export_row['status']] ?? $list_export_row['status'];
        $list_export_rows[] = $list_export_row;
    }
    mysqli_stmt_close($list_export_stmt);

    require_once __DIR__ . '/includes/excel_engine.php';
    $list_export_headers = [
        ['key'=>'id', 'label'=>'예약 ID'], ['key'=>'hospital_name', 'label'=>'병원'],
        ['key'=>'name', 'label'=>'예약자'], ['key'=>'phone', 'label'=>'전화번호'],
        ['key'=>'treatment', 'label'=>'시술'], ['key'=>'booking_date', 'label'=>'예약일'],
        ['key'=>'booking_time', 'label'=>'예약시간'], ['key'=>'status', 'label'=>'상태'],
        ['key'=>'doctor_name', 'label'=>'담당의사'], ['key'=>'price', 'label'=>'금액'],
        ['key'=>'memo', 'label'=>'메모'], ['key'=>'cancel_reason', 'label'=>'취소 사유'],
        ['key'=>'created_at', 'label'=>'접수일시'],
    ];
    $list_export_filename = ($filter_hospital > 0 ? 'hospital_' . $filter_hospital . '_' : 'all_') . 'bookings_' . date('Ymd_His') . '.xlsx';
    $list_export_path = exportToExcel($list_export_rows, $list_export_headers, $list_export_filename);
    if (!$list_export_path || !is_file($list_export_path)) {
        http_response_code(500);
        exit('엑셀 파일 생성에 실패했습니다.');
    }
    $list_export_download_name = basename($list_export_path);
    $list_export_extension = strtolower(pathinfo($list_export_path, PATHINFO_EXTENSION));
    header('Content-Type: ' . ($list_export_extension === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'));
    header('Content-Disposition: attachment; filename="' . $list_export_download_name . '"');
    header('Content-Length: ' . filesize($list_export_path));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($list_export_path);
    @unlink($list_export_path);
    exit;
}

$count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_bookings b WHERE " . $where;
$count_result = mysqli_query($connect, $count_sql);
$total = $count_result ? mysqli_fetch_assoc($count_result)['cnt'] : 0;
$total_pages = max(1, ceil($total / $per_page));

if ($page > $total_pages) {
    $page = (int)$total_pages;
    $offset = ($page - 1) * $per_page;
}

$sql = "SELECT b.* FROM gangnamunni_bookings b WHERE " . $where . " ORDER BY b.created_at DESC LIMIT " . $per_page . " OFFSET " . $offset;
$result = mysqli_query($connect, $sql);
$bookings = [];
if ($result) while ($row = mysqli_fetch_assoc($result)) $bookings[] = $row;

$doctors_by_hospital = [];
$_doc_hids = [];
foreach ($bookings as $_b) { $_hid = (int)($_b['hospital_id'] ?? 0); if ($_hid > 0) $_doc_hids[$_hid] = true; }
foreach ($bookings_by_hospital as $_hid => $_hospital_bookings) { if ((int)$_hid > 0) $_doc_hids[(int)$_hid] = true; }
if ($_doc_hids) {
    $_hid_list = implode(',', array_keys($_doc_hids));
    $doc_res = mysqli_query($connect, "SELECT id, name, hospital_id FROM gangnamunni_doctors WHERE hospital_id IN ({$_hid_list}) AND status='active' ORDER BY name ASC");
    if ($doc_res) while ($d = mysqli_fetch_assoc($doc_res)) {
        $doctors_by_hospital[(int)$d['hospital_id']][] = ['id' => (int)$d['id'], 'name' => $d['name']];
    }
}

$stats = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0, 'today' => 0];
$stats_conditions = [];
if ($hosp_in !== null) $stats_conditions[] = "hospital_id IN (" . $hosp_in . ")";
if ($filter_hospital > 0) $stats_conditions[] = "hospital_id=" . $filter_hospital;
$stats_where = $stats_conditions ? " WHERE " . implode(' AND ', $stats_conditions) : "";
$stats_r = mysqli_query($connect, "SELECT status, COUNT(*) as cnt FROM gangnamunni_bookings" . $stats_where . " GROUP BY status");
if ($stats_r) while ($r = mysqli_fetch_assoc($stats_r)) { $stats[$r['status']] = $r['cnt']; $stats['total'] += $r['cnt']; }
$today_conditions = array_merge(["booking_date=CURDATE()"], $stats_conditions);
$today_r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_bookings WHERE " . implode(' AND ', $today_conditions));
if ($today_r) $stats['today'] = mysqli_fetch_assoc($today_r)['cnt'];

$build_booking_url = static function (array $overrides = []) use ($filter_hospital, $filter_status, $filter_date, $search, $show_all_hospitals) {
    $params = array_merge([
        'hospital_id' => $filter_hospital ?: null,
        'view' => ($show_all_hospitals && !$filter_hospital) ? 'all' : null,
        'status' => $filter_status ?: null,
        'date' => $filter_date ?: null,
        'q' => $search ?: null,
    ], $overrides);
    return '?' . http_build_query(array_filter($params, static function ($value) {
        return $value !== null && $value !== '';
    }));
};

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <!-- 알림 -->
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show">
        <?php echo $msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- 헤더 -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-calendar-check me-2"></i>예약 관리</h4>
        <?php if (($_SESSION['ad_admin_role'] ?? '') === 'super_admin'): ?>
        <a href="admin_appointment_wizard.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>새 예약 등록</a>
        <?php endif; ?>
    </div>

    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <?php
        $stat_cards = [
            ['전체', $stats['total'], 'bi-calendar-range', 'primary'],
            ['대기중', $stats['pending'], 'bi-clock', 'warning'],
            ['확정', $stats['confirmed'], 'bi-check-circle', 'success'],
            ['완료', $stats['completed'], 'bi-check2-all', 'info'],
            ['취소', $stats['cancelled'], 'bi-x-circle', 'danger'],
            ['오늘 예약', $stats['today'], 'bi-calendar-day', 'dark'],
        ];
        foreach ($stat_cards as $sc):
        ?>
        <div class="col-6 col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <i class="bi <?php echo $sc[2]; ?> text-<?php echo $sc[3]; ?>" style="font-size:1.5rem;"></i>
                    <h5 class="mb-0 mt-1"><?php echo number_format($sc[1]); ?></h5>
                    <small class="text-muted"><?php echo $sc[0]; ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 필터 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <?php if ($show_all_hospitals && !$filter_hospital): ?><input type="hidden" name="view" value="all"><?php endif; ?>
                <?php if ($is_super_admin_role): ?>
                <div class="col-md-3">
                    <label class="form-label mb-0 small">병원</label>
                    <select name="hospital_id" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="조회할 병원 선택">
                        <option value="">병원을 선택하세요</option>
                        <?php foreach ($hospital_options as $hospital): ?>
                        <option value="<?php echo (int)$hospital['id']; ?>" <?php echo $filter_hospital === (int)$hospital['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($hospital['name']); ?> (<?php echo number_format((int)$hospital['booking_count']); ?>건)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <?php if ($show_booking_list): ?>
                <div class="<?php echo $is_super_admin_role ? 'col-md-2' : 'col-md-3'; ?>">
                    <label class="form-label mb-0 small">상태</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">전체 (<?php echo number_format((int)$stats['total']); ?>건)</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>대기중 (<?php echo number_format((int)$stats['pending']); ?>건)</option>
                        <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>확정 (<?php echo number_format((int)$stats['confirmed']); ?>건)</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>완료 (<?php echo number_format((int)$stats['completed']); ?>건)</option>
                        <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>취소 (<?php echo number_format((int)$stats['cancelled']); ?>건)</option>
                        <option value="no_show" <?php echo $filter_status === 'no_show' ? 'selected' : ''; ?>>노쇼 (<?php echo number_format((int)($stats['no_show'] ?? 0)); ?>건)</option>
                    </select>
                </div>
                <div class="<?php echo $is_super_admin_role ? 'col-md-2' : 'col-md-3'; ?>">
                    <label class="form-label mb-0 small">예약일</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                <div class="<?php echo $is_super_admin_role ? 'col-md-3' : 'col-md-4'; ?>">
                    <label class="form-label mb-0 small">검색</label>
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="이름, 전화번호, 병원명" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <div class="d-flex gap-1">
                        <button class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-search me-1"></i>검색</button>
                        <?php if ($filter_hospital || $filter_status || $filter_date || $search || $show_all_hospitals): ?>
                        <a href="admin_bookings.php" class="btn btn-outline-secondary btn-sm" title="병원별 보기로 돌아가기" aria-label="병원별 보기로 돌아가기"><i class="bi bi-arrow-counterclockwise"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php elseif ($is_super_admin_role): ?>
                <div class="col-md-9 d-flex justify-content-end align-items-end">
                    <a href="?view=all" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-ul me-1"></i>전체 예약 보기</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($is_super_admin_role && !$show_booking_list): ?>
    <!-- super_admin 기본 화면: 병원별 예약 아코디언 -->
    <style>
    .booking-hospital-section { padding: 0 !important; }
    .booking-hospital-toggle { border: 0 !important; border-radius: 0 !important; display: flex !important; margin: 0 !important; max-width: none !important; width: 100% !important; }
    .booking-hospital-head { background: var(--bs-light); }
    .booking-hospital-actions { flex: 0 0 auto; }
    .booking-hospital-toggle[aria-expanded="true"] .booking-hospital-chevron { transform: rotate(180deg); }
    .booking-hospital-chevron { transition: transform .2s ease; }
    </style>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <div>
                <strong><i class="bi bi-hospital me-2 text-primary"></i>병원별 예약 현황</strong>
                <small class="text-muted ms-2">병원을 누르면 최근 1개월 예약 내역이 펼쳐집니다. 전체 내역은 상세 목록/엑셀 다운로드에서 확인하세요.</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border"><?php echo number_format(count($hospital_options)); ?>개 병원</span>
                <a href="?action=export_all_monthly" class="btn btn-outline-success btn-sm" title="병원별 시트로 나눠 최근 1개월 예약을 한 번에 다운로드">
                    <i class="bi bi-file-earmark-excel me-1"></i>전체 병원 최근 1개월 다운로드
                </a>
            </div>
        </div>
        <div class="card-body p-3" id="hospitalBookingAccordion">
            <?php if (!$hospital_options): ?>
            <div class="text-center py-5 text-muted">조회할 병원이 없습니다.</div>
            <?php else: foreach ($hospital_options as $hospital):
                $hospital_id = (int)$hospital['id'];
                $hospital_bookings = $bookings_by_hospital[$hospital_id] ?? [];
            ?>
            <div class="booking-hospital-section border rounded mb-2 overflow-hidden w-100">
                <div class="booking-hospital-head d-flex align-items-stretch">
                    <button class="booking-hospital-toggle btn btn-light text-start justify-content-between align-items-center p-3 flex-grow-1" type="button"
                            data-bs-toggle="collapse" data-bs-target="#hospitalBookings<?php echo $hospital_id; ?>"
                            aria-expanded="<?php echo $open_hospital_id === $hospital_id ? 'true' : 'false'; ?>" aria-controls="hospitalBookings<?php echo $hospital_id; ?>">
                        <span class="d-flex align-items-center gap-2 flex-wrap">
                            <strong><i class="bi bi-hospital me-1 text-primary"></i><?php echo htmlspecialchars($hospital['name']); ?></strong>
                            <span class="badge bg-primary"><?php echo number_format((int)$hospital['booking_count']); ?>건</span>
                            <?php if ((int)$hospital['pending_count'] > 0): ?><span class="badge bg-warning text-dark">대기 <?php echo number_format((int)$hospital['pending_count']); ?></span><?php endif; ?>
                            <?php if ((int)$hospital['today_count'] > 0): ?><span class="badge bg-dark">오늘 <?php echo number_format((int)$hospital['today_count']); ?></span><?php endif; ?>
                        </span>
                        <i class="bi bi-chevron-down booking-hospital-chevron"></i>
                    </button>
                    <div class="booking-hospital-actions d-flex align-items-center gap-1 pe-3">
                        <a href="?hospital_id=<?php echo $hospital_id; ?>" class="btn btn-outline-primary btn-sm" title="이 병원 예약 상세 목록"><i class="bi bi-list-ul"></i></a>
                        <a href="?action=export_hospital&hospital_id=<?php echo $hospital_id; ?>" class="btn btn-outline-success btn-sm" title="이 병원 예약 엑셀 다운로드"><i class="bi bi-file-earmark-excel"></i></a>
                    </div>
                </div>
                <div class="collapse w-100 <?php echo $open_hospital_id === $hospital_id ? 'show' : ''; ?>" id="hospitalBookings<?php echo $hospital_id; ?>" data-bs-parent="#hospitalBookingAccordion">
                    <div class="p-2 border-top bg-body-tertiary">
                        <div class="bg-body rounded border overflow-hidden">
                            <?php if (!$hospital_bookings): ?>
                            <div class="text-center text-muted py-4">
                                <?php if ((int)$hospital['booking_count'] > 0): ?>
                                최근 1개월 이내 예약이 없습니다. <a href="?hospital_id=<?php echo $hospital_id; ?>">전체 내역 보기</a>
                                <?php else: ?>
                                예약 내역이 없습니다.
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th>예약자</th><th>전화번호</th><th>시술</th><th>예약일</th><th>예약시간</th><th>상태</th><th>담당의사</th><th style="width:190px">관리</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($hospital_bookings as $hospital_booking):
                                        $group_status = (string)($hospital_booking['status'] ?? 'pending');
                                        $group_badges = ['pending'=>'warning text-dark','confirmed'=>'success','completed'=>'info','cancelled'=>'danger','no_show'=>'secondary'];
                                        $group_labels = ['pending'=>'대기중','confirmed'=>'확정','completed'=>'완료','cancelled'=>'취소','no_show'=>'노쇼'];
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($hospital_booking['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($hospital_booking['phone']); ?></td>
                                        <td><?php echo htmlspecialchars($hospital_booking['treatment']); ?></td>
                                        <td><?php echo htmlspecialchars($hospital_booking['booking_date']); ?></td>
                                        <td><?php echo htmlspecialchars(substr((string)$hospital_booking['booking_time'], 0, 5)); ?></td>
                                        <td><span class="badge bg-<?php echo $group_badges[$group_status] ?? 'secondary'; ?>"><?php echo $group_labels[$group_status] ?? htmlspecialchars($group_status); ?></span></td>
                                        <td>
                                            <?php echo trim((string)$hospital_booking['doctor_name']) !== '' ? htmlspecialchars($hospital_booking['doctor_name']) : '<span class="text-muted">미배정</span>'; ?>
                                            <button type="button" class="btn btn-outline-secondary btn-sm ms-1" title="담당의사 배정"
                                                    onclick="assignDoctor(<?php echo (int)$hospital_booking['id']; ?>, <?php echo $hospital_id; ?>, <?php echo (int)($hospital_booking['doctor_id'] ?? 0); ?>)">
                                                <i class="bi bi-person-plus"></i>
                                            </button>
                                        </td>
                                        <td>
                                            <form method="post" class="d-flex align-items-center gap-1" onsubmit="return confirm('예약 상태를 변경하시겠습니까?');">
                                                <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_bookings_csrf, ENT_QUOTES); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int)$hospital_booking['id']; ?>">
                                                <input type="hidden" name="open_hospital_id" value="<?php echo $hospital_id; ?>">
                                                <select name="action" class="form-select form-select-sm" aria-label="예약 상태 변경">
                                                    <?php if ($group_status !== 'pending'): ?><option value="pending">예약대기</option><?php endif; ?>
                                                    <?php if ($group_status !== 'confirmed'): ?><option value="confirm">예약확정</option><?php endif; ?>
                                                    <?php if ($group_status !== 'completed'): ?><option value="complete">시술완료</option><?php endif; ?>
                                                    <?php if ($group_status !== 'no_show'): ?><option value="noshow">노쇼</option><?php endif; ?>
                                                    <?php if ($group_status !== 'cancelled'): ?><option value="cancel">예약취소</option><?php endif; ?>
                                                </select>
                                                <button type="submit" class="btn btn-outline-primary btn-sm" title="예약 상태 변경"><i class="bi bi-arrow-repeat"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php else: ?>
    <!-- 예약 목록 -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
            <small class="text-muted">총 <?php echo number_format((int)$total); ?>건</small>
            <a href="<?php echo htmlspecialchars($build_booking_url(['action' => 'export_list'])); ?>" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i>엑셀 다운로드
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px">#</th>
                        <th>예약자</th>
                        <th>전화번호</th>
                        <th>병원</th>
                        <th>시술</th>
                        <th>예약일</th>
                        <th>예약시간</th>
                        <th>사진</th>
                        <th>상태</th>
                        <th>담당의사</th>
                        <th style="width:150px">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                    <tr><td colspan="11" class="text-center py-5 text-muted"><i class="bi bi-inbox" style="font-size:2rem;"></i><br>예약 내역이 없습니다.</td></tr>
                    <?php else:
                    $is_viewer = (($_SESSION['ad_admin_role'] ?? '') === 'viewer');
                    $is_super_admin = (($_SESSION['ad_admin_role'] ?? '') === 'super_admin');
                    $is_hospital_admin = in_array(($_SESSION['ad_admin_role'] ?? ''), ['hospital_super_admin', 'hospital_admin', 'hospital_sub_admin'], true);
                    $can_override_status = $is_super_admin || $is_hospital_admin;
                    foreach ($bookings as $b): ?>
                    <tr>
                        <td><?php echo $b['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($b['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($b['phone']); ?></td>
                        <td><?php echo htmlspecialchars($b['hospital_name']); ?></td>
                        <td><?php echo htmlspecialchars($b['treatment']); ?></td>
                        <td><?php echo $b['booking_date']; ?></td>
                        <td><?php echo substr($b['booking_time'], 0, 5); ?></td>
                        <td><?php echo render_photo_thumbnails($b['photo_urls'] ?? null, 36); ?></td>
                        <td>
                            <?php
                            $badge_map = ['pending' => 'warning', 'confirmed' => 'success', 'completed' => 'info', 'cancelled' => 'danger', 'no_show' => 'secondary'];
                            $label_map = ['pending' => '대기중', 'confirmed' => '확정', 'completed' => '완료', 'cancelled' => '취소', 'no_show' => '노쇼'];
                            $st = $b['status'] ?? 'pending';
                            ?>
                            <span class="badge bg-<?php echo $badge_map[$st] ?? 'secondary'; ?>"><?php echo $label_map[$st] ?? $st; ?></span>
                        </td>
                        <td>
                            <?php $dn = trim($b['doctor_name'] ?? ''); ?>
                            <?php if ($dn !== ''): ?>
                            <span class="badge bg-light text-dark border"><i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($dn); ?></span>
                            <?php else: ?>
                            <span class="text-muted small">미배정</span>
                            <?php endif; ?>
                            <?php if (!$is_viewer): ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm ms-1" title="담당의사 배정"
                                    onclick="assignDoctor(<?php echo $b['id']; ?>, <?php echo (int)($b['hospital_id'] ?? 0); ?>, <?php echo (int)($b['doctor_id'] ?? 0); ?>)">
                                <i class="bi bi-person-plus"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($can_override_status): ?>
                            <form method="post" class="d-flex align-items-center gap-1"
                                  onsubmit="return confirm('예약 상태를 변경하시겠습니까?');">
                                <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_bookings_csrf, ENT_QUOTES); ?>">
                                <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                                <select name="action" class="form-select form-select-sm" aria-label="예약 상태 변경">
                                    <?php if ($st !== 'pending'): ?><option value="pending">예약대기</option><?php endif; ?>
                                    <?php if ($st !== 'confirmed'): ?><option value="confirm">예약확정</option><?php endif; ?>
                                    <?php if ($st !== 'completed'): ?><option value="complete">시술완료</option><?php endif; ?>
                                    <?php if ($st !== 'no_show'): ?><option value="noshow">노쇼</option><?php endif; ?>
                                    <?php if ($st !== 'cancelled'): ?><option value="cancel">예약취소</option><?php endif; ?>
                                </select>
                                <button type="submit" class="btn btn-outline-primary btn-sm" title="예약 상태 변경">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                            </form>
                            <?php elseif ($is_viewer): ?>
                            <span class="text-muted small">보기 전용</span>
                            <?php elseif ($st === 'pending'): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_bookings_csrf, ENT_QUOTES); ?>">
                                <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button class="btn btn-success btn-sm" title="승인"><i class="bi bi-check-lg"></i></button>
                            </form>
                            <button class="btn btn-danger btn-sm" title="거절" onclick="cancelBooking(<?php echo $b['id']; ?>)"><i class="bi bi-x-lg"></i></button>
                            <?php elseif ($st === 'confirmed'): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_bookings_csrf, ENT_QUOTES); ?>">
                                <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                                <input type="hidden" name="action" value="complete">
                                <button class="btn btn-info btn-sm text-white" title="완료"><i class="bi bi-check2-all"></i></button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_bookings_csrf, ENT_QUOTES); ?>">
                                <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                                <input type="hidden" name="action" value="noshow">
                                <button class="btn btn-secondary btn-sm" title="노쇼"><i class="bi bi-person-x"></i></button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                            <?php if ($b['memo']): ?>
                            <button class="btn btn-outline-secondary btn-sm" title="메모" data-bs-toggle="tooltip" data-bs-title="<?php echo htmlspecialchars($b['memo']); ?>"><i class="bi bi-chat-text"></i></button>
                            <?php endif; ?>
                            <?php if (in_array($st, ['cancelled', 'no_show'], true) && trim($b['cancel_reason'] ?? '') !== ''): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm" title="취소 사유 보기"
                                    onclick="showCancelReason(this)"
                                    data-reason="<?php echo htmlspecialchars($b['cancel_reason'], ENT_QUOTES); ?>">
                                <i class="bi bi-info-circle"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 페이지네이션 -->
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-top">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $page > 1 ? htmlspecialchars($build_booking_url(['page' => $page - 1])) : '#'; ?>" aria-label="이전 페이지"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <?php
                    $page_start = max(1, $page - 2);
                    $page_end = min((int)$total_pages, $page + 2);
                    if ($page_start > 1):
                    ?>
                    <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($build_booking_url(['page' => 1])); ?>">1</a></li>
                    <?php if ($page_start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $page_start; $i <= $page_end; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars($build_booking_url(['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($page_end < $total_pages): ?>
                    <?php if ($page_end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?php echo htmlspecialchars($build_booking_url(['page' => (int)$total_pages])); ?>"><?php echo (int)$total_pages; ?></a></li>
                    <?php endif; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $page < $total_pages ? htmlspecialchars($build_booking_url(['page' => $page + 1])) : '#'; ?>" aria-label="다음 페이지"><i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 취소 모달 -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_bookings_csrf, ENT_QUOTES); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">예약 취소</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="cancel_id">
                    <input type="hidden" name="action" value="cancel">
                    <div class="mb-3">
                        <label class="form-label">취소 사유</label>
                        <textarea name="cancel_reason" class="form-control" rows="3" placeholder="취소 사유를 입력하세요"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                    <button type="submit" class="btn btn-danger">취소 확정</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 담당의사 배정 모달 -->
<div class="modal fade" id="assignDoctorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_bookings_csrf, ENT_QUOTES); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">담당의사 배정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="assign_booking_id">
                    <input type="hidden" name="open_hospital_id" id="assign_open_hospital_id" value="0">
                    <input type="hidden" name="action" value="assign_doctor">
                    <div class="mb-2">
                        <label class="form-label">담당의사</label>
                        <select name="doctor_id" id="assign_doctor_select" class="form-select">
                            <option value="0">— 미배정 —</option>
                        </select>
                        <small class="text-muted">해당 예약 병원 소속의 활성 의사만 표시됩니다.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                    <button type="submit" class="btn btn-primary">배정</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 취소 사유 보기 모달 -->
<div class="modal fade" id="cancelReasonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-x-circle text-danger me-2"></i>취소 사유</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="cancelReasonText" class="mb-0" style="white-space:pre-wrap;word-break:break-word;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<script>
const DOCTORS_BY_HOSPITAL = <?php echo json_encode($doctors_by_hospital, JSON_UNESCAPED_UNICODE); ?>;
function showCancelReason(btn) {
    document.getElementById('cancelReasonText').textContent = btn.dataset.reason || '(사유 없음)';
    new bootstrap.Modal(document.getElementById('cancelReasonModal')).show();
}
function cancelBooking(id) {
    document.getElementById('cancel_id').value = id;
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}
function assignDoctor(bookingId, hospitalId, currentDoctorId) {
    document.getElementById('assign_booking_id').value = bookingId;
    document.getElementById('assign_open_hospital_id').value = hospitalId;
    const sel = document.getElementById('assign_doctor_select');
    sel.innerHTML = '<option value="0">— 미배정 —</option>';
    const list = DOCTORS_BY_HOSPITAL[hospitalId] || [];
    list.forEach(d => {
        const opt = document.createElement('option');
        opt.value = d.id;
        opt.textContent = d.name;
        if (d.id === currentDoctorId) opt.selected = true;
        sel.appendChild(opt);
    });
    if (list.length === 0) {
        const opt = document.createElement('option');
        opt.textContent = '등록된 활성 의사가 없습니다';
        opt.disabled = true;
        sel.appendChild(opt);
    }
    new bootstrap.Modal(document.getElementById('assignDoctorModal')).show();
}
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
});
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
