<?php
/**
 * 쿠폰 API
 * GET ?action=list — 사용 가능한 쿠폰 목록
 * POST {action: 'claim', coupon_id} — 쿠폰 받기
 * POST {action: 'use', coupon_id, booking_id} — 쿠폰 사용
 */
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) jsonError('DB 연결 실패', 500);
mysqli_set_charset($connect, 'utf8mb4');

// 테이블 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    discount_type ENUM('percent','fixed') DEFAULT 'fixed',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    min_order_amount DECIMAL(10,2) DEFAULT 0,
    max_discount DECIMAL(10,2) DEFAULT 0,
    total_quantity INT DEFAULT -1 COMMENT '-1=무제한',
    claimed_count INT DEFAULT 0,
    hospital_id INT DEFAULT NULL COMMENT 'NULL=전체, 특정 병원 한정',
    category VARCHAR(100) DEFAULT NULL COMMENT 'NULL=전체, 특정 카테고리 한정',
    starts_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_active (is_active, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_coupon_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT NOT NULL,
    member_id INT NOT NULL,
    status ENUM('available','used','expired') DEFAULT 'available',
    used_at DATETIME DEFAULT NULL,
    booking_id INT DEFAULT NULL,
    claimed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_member (member_id, status),
    KEY idx_coupon (coupon_id),
    UNIQUE KEY idx_unique_claim (coupon_id, member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 로그인 체크
$g5_common = dirname(__DIR__) . '/common.php';
if (file_exists($g5_common) && !defined('G5_PATH')) {
    include_once $g5_common;
}
$is_logged = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$member_id = $is_logged ? (int)($member['mb_no'] ?? 0) : 0;

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        // 사용 가능한 쿠폰 목록
        $sql = "SELECT c.*,
                (SELECT COUNT(*) FROM gangnamunni_coupon_claims cl WHERE cl.coupon_id = c.id AND cl.member_id = ? AND cl.status = 'available') as my_claimed
                FROM gangnamunni_coupons c
                WHERE c.is_active = 1
                AND (c.starts_at IS NULL OR c.starts_at <= NOW())
                AND (c.expires_at IS NULL OR c.expires_at > NOW())
                AND (c.total_quantity = -1 OR c.claimed_count < c.total_quantity)
                ORDER BY c.created_at DESC";
        $stmt = mysqli_prepare($connect, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $member_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $coupons = [];
            while ($row = mysqli_fetch_assoc($res)) {
                $coupons[] = [
                    'id' => (int)$row['id'],
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'discount_type' => $row['discount_type'],
                    'discount_value' => (float)$row['discount_value'],
                    'min_order' => (float)$row['min_order_amount'],
                    'max_discount' => (float)$row['max_discount'],
                    'expires_at' => $row['expires_at'],
                    'remaining' => $row['total_quantity'] == -1 ? null : ($row['total_quantity'] - $row['claimed_count']),
                    'claimed' => (int)$row['my_claimed'] > 0
                ];
            }
            mysqli_stmt_close($stmt);
            jsonSuccess(['coupons' => $coupons]);
        }
    } elseif ($action === 'my') {
        // 내 쿠폰
        if (!$is_logged) jsonError('로그인 필요', 401);
        $status = $_GET['status'] ?? 'available';
        $sql = "SELECT cl.*, c.code, c.name, c.description, c.discount_type, c.discount_value, c.expires_at
                FROM gangnamunni_coupon_claims cl
                JOIN gangnamunni_coupons c ON cl.coupon_id = c.id
                WHERE cl.member_id = ? AND cl.status = ?
                ORDER BY cl.claimed_at DESC";
        $stmt = mysqli_prepare($connect, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'is', $member_id, $status);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $my = [];
            while ($row = mysqli_fetch_assoc($res)) $my[] = $row;
            mysqli_stmt_close($stmt);
            jsonSuccess(['coupons' => $my]);
        }
    }
} elseif ($method === 'POST') {
    if (!$is_logged) jsonError('로그인이 필요합니다', 401);

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'claim') {
        $coupon_id = (int)($input['coupon_id'] ?? 0);
        // 이미 받았는지 체크
        $check = mysqli_prepare($connect, "SELECT id FROM gangnamunni_coupon_claims WHERE coupon_id=? AND member_id=?");
        mysqli_stmt_bind_param($check, 'ii', $coupon_id, $member_id);
        mysqli_stmt_execute($check);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($check))) {
            mysqli_stmt_close($check);
            jsonError('이미 받은 쿠폰입니다');
        }
        mysqli_stmt_close($check);

        // 수량 체크
        $coupon = null;
        $cq = mysqli_prepare($connect, "SELECT * FROM gangnamunni_coupons WHERE id=? AND is_active=1");
        mysqli_stmt_bind_param($cq, 'i', $coupon_id);
        mysqli_stmt_execute($cq);
        $coupon = mysqli_fetch_assoc(mysqli_stmt_get_result($cq));
        mysqli_stmt_close($cq);

        if (!$coupon) jsonError('쿠폰을 찾을 수 없습니다');
        if ($coupon['total_quantity'] != -1 && $coupon['claimed_count'] >= $coupon['total_quantity']) {
            jsonError('쿠폰이 모두 소진되었습니다');
        }

        $ins = mysqli_prepare($connect, "INSERT INTO gangnamunni_coupon_claims (coupon_id, member_id) VALUES (?, ?)");
        mysqli_stmt_bind_param($ins, 'ii', $coupon_id, $member_id);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);

        mysqli_query($connect, "UPDATE gangnamunni_coupons SET claimed_count = claimed_count + 1 WHERE id = {$coupon_id}");

        jsonSuccess([], '쿠폰을 받았습니다');

    } elseif ($action === 'use') {
        $coupon_id = (int)($input['coupon_id'] ?? 0);
        $booking_id = (int)($input['booking_id'] ?? 0);

        $upd = mysqli_prepare($connect, "UPDATE gangnamunni_coupon_claims SET status='used', used_at=NOW(), booking_id=? WHERE coupon_id=? AND member_id=? AND status='available'");
        mysqli_stmt_bind_param($upd, 'iii', $booking_id, $coupon_id, $member_id);
        mysqli_stmt_execute($upd);
        $affected = mysqli_stmt_affected_rows($upd);
        mysqli_stmt_close($upd);

        if ($affected > 0) {
            jsonSuccess([], '쿠폰이 사용되었습니다');
        } else {
            jsonError('사용 가능한 쿠폰이 없습니다');
        }
    }
}

mysqli_close($connect);
jsonError('잘못된 요청');
