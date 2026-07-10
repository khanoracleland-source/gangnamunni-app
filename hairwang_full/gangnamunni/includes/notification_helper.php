<?php
/**
 * 통합 알림 헬퍼
 * 한 번 호출로: 인앱 알림 + FCM 푸시 + 대시보드 카운트
 * @created 2026-04-06
 */

if (!function_exists('create_user_notification')) {
    require_once __DIR__ . '/user_notifications.php';
}
if (!function_exists('send_push_to_all_admins')) {
    require_once __DIR__ . '/admin_fcm.php';
}
// admin_notifications.php 를 전역 스코프에서 로드해 $DEFAULT_NOTIFICATIONS 를 전역에 설정한다.
// (admin_fcm.php가 함수 내부에서 지연 require 하면 전역 미설정 → should_receive_notification 기본값 누락)
if (!function_exists('should_receive_notification')) {
    require_once __DIR__ . '/admin_notifications.php';
}

/**
 * 관리자 전체에게 알림 (인앱 + FCM)
 * @param mysqli $connect DB 연결
 * @param string $type 알림 유형 (hospital_apply, booking, review, consultation, event_entry)
 * @param string $title 알림 제목
 * @param string $message 알림 내용
 * @param string|null $link 클릭 시 이동 URL
 * @param array $extra 추가 데이터
 */
function notifyAdmins($connect, $type, $title, $message, $link = null, $extra = []) {
    // 1. 모든 관리자에게 인앱 알림
    $admins = mysqli_query($connect, "SELECT id FROM gangnamunni_admins WHERE role IN ('super_admin','admin','editor')");
    if ($admins) {
        while ($admin = mysqli_fetch_assoc($admins)) {
            create_user_notification($connect, $admin['id'], $type, $title, $message, $link);
        }
    }

    // 2. FCM 푸시 (관리자 앱)
    try {
        send_push_to_all_admins($connect, $title, $message, $link, $type);
    } catch (Exception $e) {
        error_log("[Notification] FCM 발송 실패: " . $e->getMessage());
    }

    // 3. 알림 로그 기록
    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_notification_sends (admin_id, admin_username, target_type, notification_type, title, message, link, created_at) VALUES (0, 'system', 'all_admins', ?, ?, ?, ?, NOW())"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ssss', $type, $title, $message, $link);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * 특정 회원에게 알림 (인앱 + FCM)
 * 병원 관리자에게 승인/반려 알림 등
 */
function notifyMember($connect, $member_id, $type, $title, $message, $link = null) {
    // 인앱 알림
    create_user_notification($connect, $member_id, $type, $title, $message, $link);

    // 해당 회원이 관리자면 FCM도 발송
    $stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_admins WHERE id=? OR username=?");
    $mid_str = strval($member_id);
    mysqli_stmt_bind_param($stmt, 'is', $member_id, $mid_str);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($admin = mysqli_fetch_assoc($result)) {
        try {
            if (function_exists('send_push_notification')) {
                send_push_notification($connect, $admin['id'], $title, $message, $link);
            }
        } catch (Exception $e) {
            error_log("[Notification] FCM member 발송 실패: " . $e->getMessage());
        }
    }
    mysqli_stmt_close($stmt);
}

/**
 * 알림 아이콘 매핑
 */
function getNotificationIcon($type) {
    $icons = [
        'hospital_apply' => 'bi-building-add',
        'hospital_approve' => 'bi-check-circle',
        'hospital_reject' => 'bi-x-circle',
        'booking' => 'bi-calendar-check',
        'review' => 'bi-chat-dots',
        'consultation' => 'bi-headset',
        'event_entry' => 'bi-ticket',
        'system' => 'bi-gear',
    ];
    return $icons[$type] ?? 'bi-bell';
}
