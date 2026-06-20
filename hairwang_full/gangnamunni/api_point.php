<?php
/**
 * 포인트 API
 * GET ?action=balance — 잔액 조회
 * GET ?action=history — 적립/사용 내역
 * POST {action: 'earn', amount, reason} — 적립 (시스템 호출)
 * POST {action: 'use', amount, reason, booking_id} — 사용
 */
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) jsonError('DB 연결 실패', 500);
mysqli_set_charset($connect, 'utf8mb4');

// 테이블 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    type ENUM('earn','use','expire','admin') NOT NULL,
    amount INT NOT NULL COMMENT '양수=적립, 음수=사용',
    balance INT NOT NULL DEFAULT 0 COMMENT '거래 후 잔액',
    reason VARCHAR(200),
    reference_type VARCHAR(50) DEFAULT NULL COMMENT 'booking, review, event, admin',
    reference_id INT DEFAULT NULL,
    expires_at DATE DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_member (member_id, created_at),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 로그인 체크
$g5_common = dirname(__DIR__) . '/common.php';
if (file_exists($g5_common) && !defined('G5_PATH')) {
    include_once $g5_common;
}
$is_logged = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$member_id = $is_logged ? (int)($member['mb_no'] ?? 0) : 0;

if (!$is_logged) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'balance') {
        jsonSuccess(['balance' => 0, 'logged_in' => false]);
    }
    jsonError('로그인이 필요합니다', 401);
}

$method = $_SERVER['REQUEST_METHOD'];

function getBalance($connect, $member_id) {
    $stmt = mysqli_prepare($connect, "SELECT COALESCE(SUM(amount), 0) as balance FROM gangnamunni_points WHERE member_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $member_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int)($row['balance'] ?? 0);
}

function addPointTransaction($connect, $member_id, $type, $amount, $reason, $ref_type = null, $ref_id = null, $expires_at = null) {
    $balance = getBalance($connect, $member_id) + $amount;
    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_points (member_id, type, amount, balance, reason, reference_type, reference_id, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'isiissis', $member_id, $type, $amount, $balance, $reason, $ref_type, $ref_id, $expires_at);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok ? $balance : false;
}

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'balance';

    if ($action === 'balance') {
        $balance = getBalance($connect, $member_id);
        // 이번 달 적립/사용
        $month_stmt = mysqli_prepare($connect,
            "SELECT
                COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) as earned,
                COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as used
             FROM gangnamunni_points
             WHERE member_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
        mysqli_stmt_bind_param($month_stmt, 'i', $member_id);
        mysqli_stmt_execute($month_stmt);
        $month = mysqli_fetch_assoc(mysqli_stmt_get_result($month_stmt));
        mysqli_stmt_close($month_stmt);

        jsonSuccess([
            'balance' => $balance,
            'month_earned' => (int)($month['earned'] ?? 0),
            'month_used' => (int)($month['used'] ?? 0),
            'logged_in' => true
        ]);

    } elseif ($action === 'history') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $stmt = mysqli_prepare($connect,
            "SELECT * FROM gangnamunni_points WHERE member_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        mysqli_stmt_bind_param($stmt, 'iii', $member_id, $limit, $offset);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $history = [];
        while ($row = mysqli_fetch_assoc($res)) $history[] = $row;
        mysqli_stmt_close($stmt);

        jsonSuccess(['history' => $history, 'page' => $page]);
    }

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'earn') {
        $amount = abs((int)($input['amount'] ?? 0));
        $reason = $input['reason'] ?? '포인트 적립';
        $ref_type = $input['reference_type'] ?? null;
        $ref_id = isset($input['reference_id']) ? (int)$input['reference_id'] : null;
        $expires = $input['expires_at'] ?? date('Y-m-d', strtotime('+1 year'));

        if ($amount <= 0) jsonError('적립 금액이 올바르지 않습니다');

        $balance = addPointTransaction($connect, $member_id, 'earn', $amount, $reason, $ref_type, $ref_id, $expires);
        if ($balance !== false) {
            jsonSuccess(['balance' => $balance, 'earned' => $amount]);
        }
        jsonError('포인트 적립 실패');

    } elseif ($action === 'use') {
        $amount = abs((int)($input['amount'] ?? 0));
        $reason = $input['reason'] ?? '포인트 사용';
        $ref_type = $input['reference_type'] ?? null;
        $ref_id = isset($input['reference_id']) ? (int)$input['reference_id'] : null;

        if ($amount <= 0) jsonError('사용 금액이 올바르지 않습니다');

        $current = getBalance($connect, $member_id);
        if ($current < $amount) jsonError('포인트가 부족합니다 (보유: ' . number_format($current) . 'P)');

        $balance = addPointTransaction($connect, $member_id, 'use', -$amount, $reason, $ref_type, $ref_id);
        if ($balance !== false) {
            jsonSuccess(['balance' => $balance, 'used' => $amount]);
        }
        jsonError('포인트 사용 실패');
    }
}

mysqli_close($connect);
jsonError('잘못된 요청');
