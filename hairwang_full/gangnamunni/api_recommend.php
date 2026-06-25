<?php
/**
 * 개인화 추천 API
 * GET ?action=hospitals — 추천 병원 목록
 * 최근 본 병원 + 찜 목록 + 평점 기반 추천
 */
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) jsonError('DB 연결 실패', 500);
mysqli_set_charset($connect, 'utf8mb4');

// 사용자 행동 로그 테이블
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_user_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    action_type ENUM('view','search','wishlist','booking','review') NOT NULL,
    target_type VARCHAR(50) DEFAULT 'hospital',
    target_id INT DEFAULT NULL,
    metadata TEXT DEFAULT NULL COMMENT 'JSON',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_member (member_id, action_type),
    KEY idx_session (session_id, action_type),
    KEY idx_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 로그인 정보
$g5_common = dirname(__DIR__) . '/common.php';
if (file_exists($g5_common) && !defined('G5_PATH')) {
    include_once $g5_common;
}
$is_logged = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$member_id = $is_logged ? (int)($member['mb_no'] ?? 0) : 0;
$sess_id = session_id();

$action = $_GET['action'] ?? 'hospitals';
$limit = min(intval($_GET['limit'] ?? 10), 20);

if ($action === 'hospitals') {
    $recommendations = [];
    $exclude_ids = [];

    // 1단계: 찜한 병원과 유사한 병원 (같은 지역)
    if ($member_id > 0) {
        $wish_stmt = mysqli_prepare($connect,
            "SELECT DISTINCT h2.id, h2.name, h2.address, h2.rating, h2.review_count, h2.main_image_url, 'similar' as rec_reason
             FROM gangnamunni_wishlist w
             JOIN gangnamunni_hospitals h1 ON w.target_type='hospital' AND w.target_id = h1.id
             JOIN gangnamunni_hospitals h2 ON SUBSTRING_INDEX(h1.address, ' ', 2) = SUBSTRING_INDEX(h2.address, ' ', 2)
                AND h2.id != h1.id
             WHERE w.member_id = ?
             AND COALESCE(h2.is_demo, 0)=0
             AND h2.is_active=1
             AND (h2.status='approved' OR h2.status IS NULL)
             AND h2.id NOT IN (SELECT target_id FROM gangnamunni_wishlist WHERE member_id = ? AND target_type='hospital')
             ORDER BY h2.rating DESC
             LIMIT ?");
        if ($wish_stmt) {
            mysqli_stmt_bind_param($wish_stmt, 'iii', $member_id, $member_id, $limit);
            mysqli_stmt_execute($wish_stmt);
            $res = mysqli_stmt_get_result($wish_stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $recommendations[] = $row;
                $exclude_ids[] = $row['id'];
            }
            mysqli_stmt_close($wish_stmt);
        }
    }

    // 2단계: 인기 병원 (평점 + 후기 수)
    $remaining = $limit - count($recommendations);
    if ($remaining > 0) {
        $exclude_clause = '';
        if ($exclude_ids) {
            $exclude_clause = 'AND h.id NOT IN (' . implode(',', array_map('intval', $exclude_ids)) . ')';
        }

        $pop_sql = "SELECT h.id, h.name, h.address, h.rating, h.review_count, h.main_image_url, 'popular' as rec_reason
                    FROM gangnamunni_hospitals h
                    WHERE COALESCE(h.is_demo, 0)=0
                      AND h.is_active=1
                      AND (h.status='approved' OR h.status IS NULL)
                      {$exclude_clause}
                    ORDER BY (h.main_image_url IS NOT NULL AND h.main_image_url != '') DESC,
                             COALESCE(h.rating, 0) DESC,
                             COALESCE(h.review_count, 0) DESC
                    LIMIT {$remaining}";
        $pop_res = mysqli_query($connect, $pop_sql);
        if ($pop_res) {
            while ($row = mysqli_fetch_assoc($pop_res)) {
                $recommendations[] = $row;
            }
        }
    }

    mysqli_close($connect);

    $result = array_map(function($h) {
        return [
            'id' => (int)$h['id'],
            'name' => $h['name'],
            'address' => $h['address'],
            'rating' => (float)$h['rating'],
            'review_count' => (int)$h['review_count'],
            'image' => $h['main_image_url'],
            'reason' => $h['rec_reason'],
            'url' => 'detail.php?id=' . $h['id']
        ];
    }, $recommendations);

    jsonSuccess(['hospitals' => $result, 'count' => count($result), 'personalized' => $member_id > 0]);

} elseif ($action === 'log') {
    // 행동 로그 기록 (POST)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('POST only');

    $input = json_decode(file_get_contents('php://input'), true);
    $act_type = $input['action_type'] ?? '';
    $target_type = $input['target_type'] ?? 'hospital';
    $target_id = (int)($input['target_id'] ?? 0);

    if (!in_array($act_type, ['view', 'search', 'wishlist', 'booking', 'review'])) {
        jsonError('유효하지 않은 action_type');
    }

    $metadata = isset($input['metadata']) ? json_encode($input['metadata'], JSON_UNESCAPED_UNICODE) : null;

    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_user_actions (member_id, session_id, action_type, target_type, target_id, metadata)
         VALUES (?, ?, ?, ?, ?, ?)");
    $mid = $member_id ?: null;
    mysqli_stmt_bind_param($stmt, 'isssiss', $mid, $sess_id, $act_type, $target_type, $target_id, $metadata);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_close($connect);
    jsonSuccess([], 'logged');
}

mysqli_close($connect);
jsonError('잘못된 요청');
