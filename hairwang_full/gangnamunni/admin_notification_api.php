<?php
/**
 * 알림 AJAX API 엔드포인트
 * GET  ?action=count       → 읽지않은 개수
 * GET  ?action=recent      → 최근 알림 10건
 * POST ?action=read&id=N   → 알림 읽음 처리
 * POST ?action=read_all    → 전체 읽음
 * @created 2026-02-26
 */
session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
header('Content-Type: application/json; charset=utf-8');

// 로그인 체크
if (empty($_SESSION['ad_admin_logged_in']) || empty($_SESSION['ad_admin_id'])) {
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/admin_notifications.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo json_encode(['error' => 'db_error']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$admin_id = intval($_SESSION['ad_admin_id']);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'count':
        $count = get_unread_count($connect, $admin_id);
        echo json_encode(['count' => $count]);
        break;

    case 'recent':
        $notifications = get_recent_notifications($connect, $admin_id, 10);
        $items = [];
        foreach ($notifications as $n) {
            $items[] = [
                'id' => $n['id'],
                'type' => $n['type'],
                'title' => $n['title'],
                'message' => $n['message'],
                'link' => $n['link'],
                'icon' => $n['icon'],
                'is_read' => (int)$n['is_read'],
                'time_ago' => time_ago($n['created_at']),
                'created_at' => $n['created_at'],
            ];
        }
        $count = get_unread_count($connect, $admin_id);
        echo json_encode(['count' => $count, 'items' => $items]);
        break;

    case 'read':
        $nid = intval($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($nid > 0) {
            mark_notification_read($connect, $nid, $admin_id);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['error' => 'invalid_id']);
        }
        break;

    case 'read_all':
        mark_all_read($connect, $admin_id);
        echo json_encode(['ok' => true, 'count' => 0]);
        break;

    default:
        echo json_encode(['error' => 'unknown_action']);
}

mysqli_close($connect);
