<?php
/**
 * 사용자 알림 AJAX JSON API
 * gangnamunni_user_notifications 테이블 기반 사용자 알림 엔드포인트
 *
 * GET  ?action=count              -> 읽지 않은 알림 수
 * GET  ?action=list&page=&type=&limit= -> 알림 목록 (페이징)
 * POST ?action=read&id=N          -> 단건 읽음 처리
 * POST ?action=read_all           -> 전체 읽음 처리
 *
 * @created 2026-02-27
 */

// ── 초기화 ──────────────────────────────────────────────────
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/user_notifications.php';
header('Content-Type: application/json; charset=utf-8');

// ── 인증 ────────────────────────────────────────────────────
if (!is_gangnamunni_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$member = get_gangnamunni_member();
$member_id = $member['mb_id'] ?? '';

if ($member_id === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── DB 연결 확인 ────────────────────────────────────────────
// init.php 에서 $connect 가 제공된다고 가정
if (!isset($connect) || !$connect) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '서버 오류가 발생했습니다.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 테이블 존재 여부 확인 (없으면 빈 결과 반환) ─────────────
$table_exists = true;
$check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_user_notifications'");
if (!$check || mysqli_num_rows($check) === 0) {
    $table_exists = false;
}

// ── 허용 타입 목록 ──────────────────────────────────────────
$allowed_types = ['all', 'general', 'event', 'raffle', 'system', 'promotion'];

// ── 라우팅 ──────────────────────────────────────────────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {

        // ── 읽지 않은 알림 수 ────────────────────────────────
        case 'count':
            $unread = $table_exists ? get_user_unread_count($connect, $member_id) : 0;
            echo json_encode([
                'success' => true,
                'unread'  => $unread,
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ── 알림 목록 (페이징) ───────────────────────────────
        case 'list':
            $page  = max(1, intval($_GET['page'] ?? 1));
            $limit = max(1, min(100, intval($_GET['limit'] ?? 20)));
            $type  = $_GET['type'] ?? 'all';

            // 타입 유효성 검증
            if (!in_array($type, $allowed_types, true)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => '잘못된 요청입니다.',
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            if (!$table_exists) {
                echo json_encode([
                    'success'       => true,
                    'notifications' => [],
                    'total'         => 0,
                    'page'          => $page,
                    'pages'         => 0,
                    'unread'        => 0,
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            $notifications_raw = get_user_notifications($connect, $member_id, $page, $limit, $type);
            $total  = get_user_notification_count($connect, $member_id, $type);
            $unread = get_user_unread_count($connect, $member_id);
            $pages  = ($total > 0) ? intval(ceil($total / $limit)) : 0;

            // 응답 형식 정규화
            $notifications = [];
            foreach ($notifications_raw as $n) {
                $notifications[] = [
                    'id'         => intval($n['id']),
                    'type'       => $n['type'],
                    'title'      => $n['title'],
                    'message'    => $n['message'] ?? '',
                    'link'       => $n['link'] ?? '',
                    'icon'       => $n['icon'] ?? 'bi-bell',
                    'is_read'    => intval($n['is_read']),
                    'created_at' => $n['created_at'],
                    'time_ago'   => user_time_ago($n['created_at']),
                ];
            }

            echo json_encode([
                'success'       => true,
                'notifications' => $notifications,
                'total'         => $total,
                'page'          => $page,
                'pages'         => $pages,
                'unread'        => $unread,
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ── 단건 읽음 처리 ──────────────────────────────────
        case 'read':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => '잘못된 요청입니다.',
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => '잘못된 요청입니다.',
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            if (!$table_exists) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => '잘못된 요청입니다.',
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            // 알림이 현재 사용자 소유인지 검증
            $verify_stmt = mysqli_prepare($connect,
                "SELECT id FROM gangnamunni_user_notifications WHERE id = ? AND member_id = ?"
            );
            mysqli_stmt_bind_param($verify_stmt, 'is', $id, $member_id);
            mysqli_stmt_execute($verify_stmt);
            $verify_result = mysqli_stmt_get_result($verify_stmt);
            $exists = mysqli_fetch_assoc($verify_result);
            mysqli_stmt_close($verify_stmt);

            if (!$exists) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => '잘못된 요청입니다.',
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            mark_user_notification_read($connect, $id, $member_id);

            echo json_encode([
                'success' => true,
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ── 전체 읽음 처리 ──────────────────────────────────
        case 'read_all':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => '잘못된 요청입니다.',
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            if (!$table_exists) {
                echo json_encode([
                    'success' => true,
                    'updated' => 0,
                ], JSON_UNESCAPED_UNICODE);
                break;
            }

            // mark_user_all_read 는 affected_rows 를 반환하지 않으므로
            // 먼저 미읽 수를 조회한 후 업데이트 실행
            $before_count = get_user_unread_count($connect, $member_id);
            mark_user_all_read($connect, $member_id);

            echo json_encode([
                'success' => true,
                'updated' => $before_count,
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ── 알 수 없는 액션 ─────────────────────────────────
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => '잘못된 요청입니다.',
            ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => '서버 오류가 발생했습니다.',
    ], JSON_UNESCAPED_UNICODE);
}

// ── DB 닫기 ─────────────────────────────────────────────────
if (isset($connect) && $connect) {
    mysqli_close($connect);
}
