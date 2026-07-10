<?php
/**
 * 알림 헬퍼
 * gangnamunni_notifications 테이블 CRUD + FCM 푸시 알림
 * @created 2026-02-26
 * @updated 2026-02-26 FCM 푸시 알림 연동 추가
 */

// FCM 푸시 모듈 로드
require_once __DIR__ . '/admin_fcm.php';

// 알림 타입 정의
$NOTIFICATION_TYPES = [
    // 고객/외부 활동
    'hospital_request'   => ['label' => '병원 등록 요청',   'icon' => 'bi-building-add',    'color' => 'primary'],
    'ad_request'         => ['label' => '광고 등록 요청',   'icon' => 'bi-megaphone-fill',  'color' => 'warning'],
    'review_new'         => ['label' => '새 후기 등록',     'icon' => 'bi-star',            'color' => 'success'],
    'consultation_new'   => ['label' => '상담 신청',       'icon' => 'bi-chat-dots',       'color' => 'info'],
    'appointment_new'    => ['label' => '예약 신청',       'icon' => 'bi-calendar-check',  'color' => 'primary'],
    'member_join'        => ['label' => '새 회원 가입',     'icon' => 'bi-person-plus',     'color' => 'success'],
    'mail_incoming'      => ['label' => '수신 메일',       'icon' => 'bi-envelope',        'color' => 'secondary'],
    // 승인 워크플로우
    'review_pending'     => ['label' => '후기 검수 대기',   'icon' => 'bi-shield-check',    'color' => 'warning'],
    'hospital_pending'   => ['label' => '병원 승인 대기',   'icon' => 'bi-building-check',  'color' => 'warning'],
    'ad_pending'         => ['label' => '광고 승인 대기',   'icon' => 'bi-megaphone',       'color' => 'warning'],
    // 시스템/보안
    'test_fail'          => ['label' => '자동 테스트 실패', 'icon' => 'bi-exclamation-triangle', 'color' => 'danger'],
    'login_fail'         => ['label' => '로그인 실패 반복', 'icon' => 'bi-shield-exclamation',   'color' => 'danger'],
    'maintenance_toggle' => ['label' => '점검 모드 변경',   'icon' => 'bi-tools',           'color' => 'secondary'],
    'admin_created'      => ['label' => '새 관리자 등록',   'icon' => 'bi-person-badge',    'color' => 'info'],
    'ai_key_error'       => ['label' => 'AI API 오류',    'icon' => 'bi-robot',           'color' => 'danger'],
];

// 기본 알림 수신 목록 (새 관리자 생성 시 기본값)
$DEFAULT_NOTIFICATIONS = [
    'hospital_request', 'ad_request', 'review_new', 'consultation_new',
    'appointment_new', 'review_pending', 'hospital_pending', 'ad_pending',
    'test_fail', 'admin_created'
];

/**
 * 알림 생성
 * @param mysqli $connect DB 연결
 * @param string $type 알림 타입
 * @param string $title 알림 제목
 * @param string|null $message 상세 메시지
 * @param string|null $link 클릭 시 이동 URL
 * @param int|null $admin_id 특정 관리자 (NULL=전체)
 */
function create_notification($connect, $type, $title, $message = null, $link = null, $admin_id = null) {
    global $NOTIFICATION_TYPES;
    $icon = $NOTIFICATION_TYPES[$type]['icon'] ?? 'bi-bell';

    if ($admin_id !== null) {
        // 특정 관리자에게만
        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_notifications (admin_id, type, title, message, link, icon) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'isssss', $admin_id, $type, $title, $message, $link, $icon);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // FCM 푸시 알림 전송
        send_push_notification($connect, $admin_id, $title, $message ?? $title, $link, $icon);
    } else {
        // 해당 알림 타입을 수신하는 모든 관리자에게 개별 생성
        $admins = mysqli_query($connect, "SELECT id, permissions FROM gangnamunni_admins WHERE is_active = 1");
        while ($a = mysqli_fetch_assoc($admins)) {
            if (should_receive_notification($a, $type)) {
                $aid = $a['id'];
                $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_notifications (admin_id, type, title, message, link, icon) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, 'isssss', $aid, $type, $title, $message, $link, $icon);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                // FCM 푸시 알림 전송
                send_push_notification($connect, $aid, $title, $message ?? $title, $link, $icon);
            }
        }
    }
}

/**
 * 관리자가 해당 알림을 수신해야 하는지 체크
 */
function should_receive_notification($admin_row, $type) {
    global $DEFAULT_NOTIFICATIONS;
    // admin_fcm.php가 이 파일을 함수 내부에서 지연 require 하면 $DEFAULT_NOTIFICATIONS가
    // 전역에 설정되지 않아 null 이 될 수 있다 → in_array(null) TypeError(500) 방지용 방어 코드.
    $defaults = is_array($DEFAULT_NOTIFICATIONS) ? $DEFAULT_NOTIFICATIONS : [];
    $perms = json_decode($admin_row['permissions'] ?? '{}', true);
    if (!is_array($perms)) $perms = [];

    // notifications 키가 없으면 기본값 사용
    if (!isset($perms['notifications'])) {
        return in_array($type, $defaults);
    }

    $subscribed = is_array($perms['notifications']) ? $perms['notifications'] : [];
    return in_array($type, $subscribed);
}

/**
 * 읽지 않은 알림 개수
 */
function get_unread_count($connect, $admin_id) {
    $stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_notifications WHERE admin_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, 'i', $admin_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    return intval($result['cnt'] ?? 0);
}

/**
 * 최근 알림 목록
 */
function get_recent_notifications($connect, $admin_id, $limit = 10) {
    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_notifications WHERE admin_id = ? ORDER BY created_at DESC LIMIT ?");
    mysqli_stmt_bind_param($stmt, 'ii', $admin_id, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $notifications;
}

/**
 * 알림 읽음 처리
 */
function mark_notification_read($connect, $notification_id, $admin_id) {
    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_notifications SET is_read = 1 WHERE id = ? AND admin_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $notification_id, $admin_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * 전체 읽음 처리
 */
function mark_all_read($connect, $admin_id) {
    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_notifications SET is_read = 1 WHERE admin_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, 'i', $admin_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * 오래된 알림 정리 (30일 이상)
 */
function cleanup_old_notifications($connect, $days = 30) {
    mysqli_query($connect, "DELETE FROM gangnamunni_notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)");
}

/**
 * 시간 포맷 (n분전, n시간전, n일전)
 */
function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return '방금';
    if ($diff < 3600) return floor($diff / 60) . '분전';
    if ($diff < 86400) return floor($diff / 3600) . '시간전';
    if ($diff < 604800) return floor($diff / 86400) . '일전';
    return date('m/d', strtotime($datetime));
}
