<?php
/**
 * Booking notification service.
 *
 * Centralizes member resolution, notification preferences, in-app notification,
 * FCM push, and send logging for booking lifecycle events.
 */

if (!function_exists('create_user_notification')) {
    require_once __DIR__ . '/user_notifications.php';
}
if (!function_exists('send_push_to_members')) {
    require_once __DIR__ . '/admin_fcm.php';
}

function booking_notification_ensure_send_log_table($connect) {
    @mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_notification_sends (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        admin_username VARCHAR(50),
        target_type VARCHAR(50),
        target_count INT DEFAULT 0,
        notification_type VARCHAR(50),
        title VARCHAR(255),
        message TEXT,
        link VARCHAR(500),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @mysqli_query($connect, "ALTER TABLE gangnamunni_notification_sends
        ADD COLUMN IF NOT EXISTS target_count INT DEFAULT 0,
        ADD COLUMN IF NOT EXISTS notification_type VARCHAR(50),
        ADD COLUMN IF NOT EXISTS link VARCHAR(500)");
}

function booking_notification_fetch_booking_by_id($connect, $bookingId) {
    $bookingId = (int)$bookingId;
    if ($bookingId <= 0) return null;

    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_bookings WHERE id = ? LIMIT 1");
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, 'i', $bookingId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function booking_notification_base_url() {
    if (defined('SITE_FULL_URL') && SITE_FULL_URL !== '') {
        return rtrim(SITE_FULL_URL, '/') . '/';
    }
    return '';
}

function booking_notification_detail_link(array $booking, $event) {
    $base = booking_notification_base_url();
    $id = (int)($booking['id'] ?? 0);
    if ($id <= 0) return $base . 'my_bookings.php';

    if ($event === 'reschedule_required') {
        $hospitalId = (int)($booking['hospital_id'] ?? 0);
        $treatment = urlencode((string)($booking['treatment'] ?? ''));
        if ($hospitalId > 0) {
            return $base . 'booking.php?id=' . $hospitalId . '&rebook=1&source_booking_id=' . $id . '&treatment=' . $treatment;
        }
        return $base . 'booking_request_detail.php?booking_id=' . $id;
    }

    if (in_array($event, ['cancelled', 'refunded'], true)) {
        return $base . 'booking_cancel_detail.php?booking_id=' . $id;
    }

    return $base . 'booking_request_detail.php?booking_id=' . $id;
}

function booking_notification_normalize_korean_phone($phone) {
    $digits = preg_replace('/[^0-9]/', '', (string)$phone);
    if ($digits === '') return '';

    if (preg_match('/^010\d{8}$/', $digits)) {
        return $digits;
    }

    if (preg_match('/^8210\d{8}$/', $digits)) {
        return '0' . substr($digits, 2);
    }

    return '';
}

function booking_notification_find_member_id($connect, array $booking) {
    $memberId = trim((string)($booking['mb_id'] ?? ''));
    if ($memberId !== '') return $memberId;

    $patientPhone = booking_notification_normalize_korean_phone($booking['phone'] ?? '');
    if ($patientPhone === '') return '';
    $patientPhoneIntl = '82' . substr($patientPhone, 1);

    global $g5;
    $memberTable = $g5['member_table'] ?? 'g5_member';
    if (!preg_match('/^[A-Za-z0-9_]+$/', $memberTable)) return '';

    $stmt = mysqli_prepare(
        $connect,
        "SELECT mb_id FROM {$memberTable}
         WHERE REPLACE(REPLACE(REPLACE(mb_hp, '-', ''), ' ', ''), '+', '') IN (?, ?)
         ORDER BY mb_datetime DESC
         LIMIT 1"
    );
    if (!$stmt) return '';

    mysqli_stmt_bind_param($stmt, 'ss', $patientPhone, $patientPhoneIntl);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return trim((string)($row['mb_id'] ?? ''));
}

function booking_notification_preferences($connect, $memberId) {
    $defaults = ['noti_booking' => 1, 'noti_push' => 1];
    if ($memberId === '') return $defaults;

    $tableCheck = @mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_notification_settings'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) return $defaults;

    $stmt = mysqli_prepare(
        $connect,
        "SELECT noti_booking, noti_push FROM gangnamunni_notification_settings WHERE member_id = ? LIMIT 1"
    );
    if (!$stmt) return $defaults;

    mysqli_stmt_bind_param($stmt, 's', $memberId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) return $defaults;

    return [
        'noti_booking' => (int)($row['noti_booking'] ?? 1),
        'noti_push' => (int)($row['noti_push'] ?? 1),
    ];
}

function booking_notification_payload(array $booking, $event, array $context = []) {
    $hospital = trim((string)($booking['hospital_name'] ?? ''));
    $date = trim((string)($booking['booking_date'] ?? ''));
    $time = substr((string)($booking['booking_time'] ?? ''), 0, 5);
    $when = trim($date . ' ' . $time);
    $bodyParts = array_filter([$hospital, $when], static function ($value) {
        return trim((string)$value) !== '';
    });
    $defaultBody = implode(' · ', $bodyParts);
    if ($defaultBody === '') $defaultBody = trim((string)($booking['treatment'] ?? '예약 내역을 확인해주세요.'));

    $doctorName = trim((string)($context['doctor_name'] ?? $booking['doctor_name'] ?? ''));

    $map = [
        'hospital_checking' => ['예약 요청을 확인 중입니다', $defaultBody],
        'confirmed' => ['예약이 확정되었습니다', $defaultBody],
        'completed' => ['시술이 완료되었습니다', '방문해주셔서 감사합니다. 예약 내역에서 후기를 남길 수 있습니다.'],
        'cancelled' => ['병원 사정으로 예약이 취소되었어요', '예약상세에서 병원에서 전달한 취소 사유를 확인할 수 있어요'],
        'reschedule_required' => ['병원 사정으로 예약 일정 변경이 필요해요', "병원에서 예약 진행이 어려워졌습니다\n다른 일정으로 다시 예약해보세요"],
        'refunded' => ['취소·환불 처리가 완료되었어요!', "취소·환불처리가 완료되었어요\n예약 취소에서 상세 정보를 확인하세요"],
        'doctor_assigned' => ['담당의사가 배정되었습니다', trim(($doctorName !== '' ? $doctorName . ' · ' : '') . $defaultBody)],
        'doctor_unassigned' => ['담당의사 배정이 해제되었습니다', $defaultBody],
    ];

    $payload = $map[$event] ?? ['예약 상태가 변경되었습니다', $defaultBody];

    return [
        'title' => $payload[0],
        'body' => $payload[1],
        'link' => booking_notification_detail_link($booking, $event),
    ];
}

function booking_notification_log_send($connect, $memberId, $event, $title, $body, $link, array $result) {
    booking_notification_ensure_send_log_table($connect);

    $adminId = (int)($_SESSION['ad_admin_id'] ?? 0);
    $adminUsername = (string)($_SESSION['ad_admin_user'] ?? 'system');
    $targetType = 'booking:' . $memberId;
    $targetCount = $memberId !== '' ? 1 : 0;
    $notificationType = 'booking_' . $event;

    $stmt = mysqli_prepare(
        $connect,
        "INSERT INTO gangnamunni_notification_sends
            (admin_id, admin_username, target_type, target_count, notification_type, title, message, link)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return;

    $message = $body;
    $pushSent = (int)($result['push_sent'] ?? 0);
    $pushFailed = (int)($result['push_failed'] ?? 0);
    if (!empty($result['push_error'])) {
        $message .= "\n[push_error] " . $result['push_error'];
    } else {
        $message .= "\n[push] sent={$pushSent}, failed={$pushFailed}";
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ississss',
        $adminId,
        $adminUsername,
        $targetType,
        $targetCount,
        $notificationType,
        $title,
        $message,
        $link
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function notify_booking_member($connect, array $booking, $event, array $context = []) {
    $memberId = booking_notification_find_member_id($connect, $booking);
    $result = [
        'member_id' => $memberId,
        'in_app_created' => false,
        'push_sent' => 0,
        'push_failed' => 0,
        'push_skipped' => false,
        'push_error' => '',
        'reason' => null,
    ];

    if ($memberId === '') {
        $result['reason'] = 'recipient_not_found';
        error_log('[booking_notifications] recipient not found: booking_id=' . (int)($booking['id'] ?? 0) . ', event=' . $event);
        return $result;
    }

    $preferences = booking_notification_preferences($connect, $memberId);
    if ((int)$preferences['noti_booking'] !== 1) {
        $result['reason'] = 'booking_notifications_disabled';
        return $result;
    }

    $payload = booking_notification_payload($booking, $event, $context);
    $result['in_app_created'] = create_user_notification(
        $connect,
        $memberId,
        'booking',
        $payload['title'],
        $payload['body'],
        $payload['link']
    );

    if ((int)$preferences['noti_push'] === 1) {
        $pushResult = send_push_to_members(
            $connect,
            [$memberId],
            $payload['title'],
            $payload['body'],
            $payload['link']
        );
        $result['push_sent'] = (int)($pushResult['sent'] ?? 0);
        $result['push_failed'] = (int)($pushResult['failed'] ?? 0);
        $result['push_error'] = (string)($pushResult['error'] ?? '');
    } else {
        $result['push_skipped'] = true;
        $result['reason'] = 'push_disabled';
    }

    booking_notification_log_send(
        $connect,
        $memberId,
        $event,
        $payload['title'],
        $payload['body'],
        $payload['link'],
        $result
    );

    error_log(
        '[booking_notifications] booking_id=' . (int)($booking['id'] ?? 0)
        . ', event=' . $event
        . ', member_id=' . $memberId
        . ', in_app=' . (int)$result['in_app_created']
        . ', push_sent=' . $result['push_sent']
        . ', push_failed=' . $result['push_failed']
    );

    return $result;
}
