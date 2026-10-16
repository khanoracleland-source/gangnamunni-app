<?php
// ============================================================================
// [Figma Live Compare - 2026-06-15] 부가요소 ON/OFF 토글 API
// 원인 : Figma 원본에 없는 구현 부가요소(카테고리/프로모션/이벤트/광고)를
//        삭제하지 않고 표시/숨김 제어 — 프론트가 figma_block_visible()로 읽음
// 변경 : figma_block_toggles 테이블 read/write (관리자 전용)
// 영향 : figma_compare.html 토글 + index.php/hospitals.php 조건부 출력
// ============================================================================
session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/includes/admin_auth.php';

// 관리자만 변경 가능
$is_admin = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['ok'=>false,'error'=>'db']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// 테이블 보장
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS `figma_block_toggles` (
  `block_key` VARCHAR(64) PRIMARY KEY,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `label` VARCHAR(128) DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$action = $_GET['action'] ?? '';

// ── 조회 (목록) — 비관리자도 읽기 허용(프론트 표시용) ──
if ($action === 'list' || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $toggles = [];
    $r = mysqli_query($connect, "SELECT block_key, is_visible FROM figma_block_toggles");
    while ($r && ($row = mysqli_fetch_assoc($r))) {
        $toggles[$row['block_key']] = (int)$row['is_visible'];
    }
    mysqli_close($connect);
    echo json_encode(['ok'=>true, 'toggles'=>$toggles]);
    exit;
}

// ── 저장 (POST) — 관리자만 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_admin) { echo json_encode(['ok'=>false,'error'=>'관리자만 변경 가능']); exit; }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $key = trim($data['block_key'] ?? '');
    $vis = isset($data['is_visible']) ? (int)!!$data['is_visible'] : 1;
    if ($key === '' || !preg_match('/^[a-z0-9_]{1,64}$/', $key)) {
        echo json_encode(['ok'=>false,'error'=>'invalid key']); exit;
    }
    $stmt = mysqli_prepare($connect,
        "INSERT INTO figma_block_toggles (block_key, is_visible) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE is_visible = VALUES(is_visible)");
    mysqli_stmt_bind_param($stmt, 'si', $key, $vis);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    mysqli_close($connect);
    echo json_encode(['ok'=>(bool)$ok, 'block_key'=>$key, 'is_visible'=>$vis]);
    exit;
}

mysqli_close($connect);
echo json_encode(['ok'=>false,'error'=>'unsupported']);
