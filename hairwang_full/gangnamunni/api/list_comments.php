<?php
/**
 * 댓글 목록 API
 * GET ?review_id=1
 */
require_once __DIR__ . '/bootstrap.php';

requireMethod('GET');

$review_id = intval($_GET['review_id'] ?? 0);
if ($review_id <= 0) jsonError('후기 ID가 필요합니다.');

$db = Database::getInstance();

$comments = $db->fetchAll(
    "SELECT id, parent_id, user_id, user_name, content, like_count, created_at
     FROM review_comments
     WHERE review_id = ? AND is_active = 1
     ORDER BY created_at ASC",
    [$review_id]
);

// 트리 구조로 변환
$tree = [];
$map = [];

foreach ($comments as &$c) {
    $c['like_count'] = (int)$c['like_count'];
    $c['time_ago'] = formatRelativeTime($c['created_at']);
    $c['replies'] = [];
    $map[$c['id']] = &$c;
}
unset($c);

foreach ($comments as &$c) {
    if ($c['parent_id'] && isset($map[$c['parent_id']])) {
        $map[$c['parent_id']]['replies'][] = &$c;
    } else {
        $tree[] = &$c;
    }
}
unset($c);

jsonSuccess([
    'comments' => $tree,
    'total' => count($comments)
]);
