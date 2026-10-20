<?php
/**
 * 사용자 알림 헬퍼
 * gangnamunni_user_notifications 테이블 CRUD
 * @created 2026-02-27
 */

// 사용자 알림 타입 정의
$USER_NOTIFICATION_TYPES = [
    'general'   => ['label' => '일반 알림',   'icon' => 'bi-bell',           'color' => '#6c757d'],
    'booking'   => ['label' => '예약 알림',   'icon' => 'bi-calendar-check', 'color' => '#AC1830'],
    'event'     => ['label' => '이벤트',      'icon' => 'bi-calendar-event', 'color' => '#AC1830'],
    'raffle'    => ['label' => '응모/추첨',   'icon' => 'bi-gift',           'color' => '#198754'],
    'system'    => ['label' => '시스템',      'icon' => 'bi-gear',           'color' => '#0d6efd'],
    'promotion' => ['label' => '프로모션',    'icon' => 'bi-megaphone',      'color' => '#dc3545'],
];

/**
 * 사용자 알림 생성
 */
function create_user_notification($connect, $member_id, $type, $title, $message = null, $link = null) {
    global $USER_NOTIFICATION_TYPES;
    $icon = $USER_NOTIFICATION_TYPES[$type]['icon'] ?? 'bi-bell';
    $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_user_notifications (member_id, type, title, message, link, icon) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ssssss', $member_id, $type, $title, $message, $link, $icon);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $result;
}

/**
 * 일괄 알림 생성 (전체 회원 또는 특정 그룹)
 * @return int 생성된 알림 수
 */
function create_user_notifications_batch($connect, $member_ids, $type, $title, $message = null, $link = null) {
    global $USER_NOTIFICATION_TYPES;
    $icon = $USER_NOTIFICATION_TYPES[$type]['icon'] ?? 'bi-bell';
    $count = 0;
    $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_user_notifications (member_id, type, title, message, link, icon) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($member_ids as $mid) {
        mysqli_stmt_bind_param($stmt, 'ssssss', $mid, $type, $title, $message, $link, $icon);
        if (mysqli_stmt_execute($stmt)) $count++;
    }
    mysqli_stmt_close($stmt);
    return $count;
}

/**
 * 안읽은 알림 수
 */
function get_user_unread_count($connect, $member_id) {
    $stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_user_notifications WHERE member_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, 's', $member_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    return intval($result['cnt'] ?? 0);
}

/**
 * 알림 목록 (페이징)
 */
function get_user_notifications($connect, $member_id, $page = 1, $limit = 20, $type = 'all') {
    $offset = ($page - 1) * $limit;
    if ($type !== 'all') {
        $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_user_notifications WHERE member_id = ? AND type = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        mysqli_stmt_bind_param($stmt, 'ssii', $member_id, $type, $limit, $offset);
    } else {
        $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_user_notifications WHERE member_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        mysqli_stmt_bind_param($stmt, 'sii', $member_id, $limit, $offset);
    }
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
 * 알림 총 수 (페이징용)
 */
function get_user_notification_count($connect, $member_id, $type = 'all') {
    if ($type !== 'all') {
        $stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_user_notifications WHERE member_id = ? AND type = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $member_id, $type);
    } else {
        $stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_user_notifications WHERE member_id = ?");
        mysqli_stmt_bind_param($stmt, 's', $member_id);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    return intval($result['cnt'] ?? 0);
}

/**
 * 읽음 처리
 */
function mark_user_notification_read($connect, $id, $member_id) {
    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_user_notifications SET is_read = 1 WHERE id = ? AND member_id = ?");
    mysqli_stmt_bind_param($stmt, 'is', $id, $member_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * 전체 읽음 처리
 */
function mark_user_all_read($connect, $member_id) {
    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_user_notifications SET is_read = 1 WHERE member_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, 's', $member_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * 시간 포맷
 */
function user_time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return '방금';
    if ($diff < 3600) return floor($diff / 60) . '분전';
    if ($diff < 86400) return floor($diff / 3600) . '시간전';
    if ($diff < 604800) return floor($diff / 86400) . '일전';
    return date('m/d', strtotime($datetime));
}
