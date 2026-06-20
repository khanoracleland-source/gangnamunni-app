<?php
// ============================================================================
// [Figma Review - 2026-06-05] 피그마 검수 메모 목록 API
// 원인 : admin_figma_review.php 대시보드용 + Claude 작업 큐 조회용
// 변경 : 필터링 + 페이징 지원 (status/priority/group)
// 영향 범위 : admin_figma_review.php
// ============================================================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo json_encode(['ok' => false, 'error' => 'DB connect failed: ' . mysqli_connect_error()]);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$status   = trim($_GET['status'] ?? '');
$priority = trim($_GET['priority'] ?? '');
$group    = trim($_GET['group'] ?? '');
$limit    = max(1, min(500, (int)($_GET['limit'] ?? 100)));

$where = ['1=1'];
if ($status !== '' && in_array($status, ['pending','in_progress','done','rejected'])) {
    $where[] = "status = '" . mysqli_real_escape_string($connect, $status) . "'";
}
if ($priority !== '' && in_array($priority, ['high','medium','low'])) {
    $where[] = "priority = '" . mysqli_real_escape_string($connect, $priority) . "'";
}
if ($group !== '') {
    $where[] = "figma_group = '" . mysqli_real_escape_string($connect, $group) . "'";
}
$where_sql = implode(' AND ', $where);

$sql = "SELECT * FROM figma_review_notes WHERE $where_sql
        ORDER BY FIELD(priority,'high','medium','low'), updated_at DESC
        LIMIT $limit";
$r = mysqli_query($connect, $sql);
$list = [];
while ($r && ($row = mysqli_fetch_assoc($r))) {
    $list[] = $row;
}

// 통계
$stats = [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'done' => 0,
    'rejected' => 0,
    'wrong_match' => 0,
    'high_priority' => 0,
];
$r2 = mysqli_query($connect, "SELECT status, priority, is_wrong_match, COUNT(*) as cnt FROM figma_review_notes GROUP BY status, priority, is_wrong_match");
while ($r2 && ($row = mysqli_fetch_assoc($r2))) {
    $stats['total'] += (int)$row['cnt'];
    $stats[$row['status']] = ($stats[$row['status']] ?? 0) + (int)$row['cnt'];
    if ($row['is_wrong_match']) $stats['wrong_match'] += (int)$row['cnt'];
    if ($row['priority'] === 'high') $stats['high_priority'] += (int)$row['cnt'];
}

echo json_encode([
    'ok' => true,
    'count' => count($list),
    'list' => $list,
    'stats' => $stats,
]);
