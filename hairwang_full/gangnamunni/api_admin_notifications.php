<?php
/**
 * 관리자 알림 API — 상단바 벨 드롭다운용
 * GET: 최근 알림 + 미읽은 건수
 * POST: 읽음 처리
 * @created 2026-04-06
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false]); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// 현재 관리자 ID
$admin_id = $_SESSION['ad_admin_id'] ?? 0;
if (!$admin_id) {
    echo json_encode(['success' => false, 'error' => '로그인 필요']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $limit = min(intval($_GET['limit'] ?? 10), 50);

    // 최근 알림
    $stmt = mysqli_prepare($connect,
        "SELECT * FROM gangnamunni_user_notifications WHERE member_id=? ORDER BY created_at DESC LIMIT ?"
    );
    $admin_id_str = strval($admin_id);
    mysqli_stmt_bind_param($stmt, 'si', $admin_id_str, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) $items[] = $row;
    mysqli_stmt_close($stmt);

    // 미읽은 건수
    $stmt2 = mysqli_prepare($connect,
        "SELECT COUNT(*) as cnt FROM gangnamunni_user_notifications WHERE member_id=? AND is_read=0"
    );
    mysqli_stmt_bind_param($stmt2, 's', $admin_id_str);
    mysqli_stmt_execute($stmt2);
    $unread = intval(mysqli_stmt_get_result($stmt2)->fetch_assoc()['cnt']);
    mysqli_stmt_close($stmt2);

    echo json_encode(['success' => true, 'items' => $items, 'unread' => $unread]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);
    $admin_id_str = strval($admin_id);

    if ($id > 0) {
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_user_notifications SET is_read=1 WHERE id=? AND member_id=?");
        mysqli_stmt_bind_param($stmt, 'is', $id, $admin_id_str);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    } elseif (($input['action'] ?? '') === 'read_all') {
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_user_notifications SET is_read=1 WHERE member_id=? AND is_read=0");
        mysqli_stmt_bind_param($stmt, 's', $admin_id_str);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    echo json_encode(['success' => true]);
}

mysqli_close($connect);
