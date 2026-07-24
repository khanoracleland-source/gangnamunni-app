<?php
/**
 * 포인트 API
 * GET ?action=balance — 잔액 조회
 * GET ?action=history — 적립/사용 내역
 * POST {action: 'earn', ...} — 일반 사용자에게 금지(적립은 server-side oqimlarda bajariladi)
 * POST {action: 'use', amount, reason, booking_id} — 사용
 */
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) jsonError('DB 연결 실패', 500);
mysqli_set_charset($connect, 'utf8mb4');

// 포인트 원장(#포인트제도): 스키마/증감은 모두 ledger 를 단일 진입점으로 사용
require_once __DIR__ . '/includes/points_ledger.php';
try {
    points_ensure_schema($connect);
} catch (\Throwable $e) {
    error_log('[api_point] schema unavailable: ' . $e->getMessage());
    jsonError('포인트 기능을 사용할 수 없습니다', 503);
}

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
    return points_balance($connect, $member_id);
}

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'balance';

    if ($action === 'balance') {
        $balance = getBalance($connect, $member_id);
        // 이번 달 적립/사용 — 원장 유형으로 분류(적립계열=earned, 그 외=used), 금액은 절대값
        $month_stmt = mysqli_prepare($connect,
            "SELECT
                COALESCE(SUM(CASE WHEN type IN ('earn','refund','admin_add','admin') THEN ABS(amount) ELSE 0 END), 0) as earned,
                COALESCE(SUM(CASE WHEN type NOT IN ('earn','refund','admin_add','admin') THEN ABS(amount) ELSE 0 END), 0) as used
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
            "SELECT * FROM gangnamunni_points WHERE member_id = ? ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?");
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
        // 적립은 가입/예약/후기 승인 kabi ishonchli server-side oqimlarda bajariladi.
        // 로그인한 일반 사용자가 임의 amount/reference yuborib balansini oshira olmaydi.
        jsonError('사용자 API를 통한 포인트 적립은 허용되지 않습니다', 403);

    } elseif ($action === 'use') {
        // 포인트 사용(차감)은 반드시 실제 결제/예약 등 server-side 흐름에서만 수행한다.
        // 클라이언트가 임의로 use 를 호출해 잔액을 소모(향후 결제 연동 시 이중사용 위험)하지
        // 못하도록, earn 과 동일하게 사용자 API 경로에서는 금지한다.
        jsonError('사용자 API를 통한 포인트 사용은 허용되지 않습니다', 403);
    }
}

mysqli_close($connect);
jsonError('잘못된 요청');
