<?php
/**
 * 후기 목록 API
 * GET ?hospital_id=1&page=1&limit=20&sort=latest
 * 응답: { success, reviews[], total, page, has_more }
 */
require_once __DIR__ . '/bootstrap.php';

requireMethod('GET');

$hospital_id = intval($_GET['hospital_id'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
$sort = $_GET['sort'] ?? 'latest';

$offset = ($page - 1) * $limit;

// 정렬
$sort_sql = 'r.created_at DESC';
switch ($sort) {
    case 'latest':  $sort_sql = 'r.created_at DESC'; break;
    case 'oldest':  $sort_sql = 'r.created_at ASC'; break;
    case 'rating':  $sort_sql = 'r.rating DESC, r.created_at DESC'; break;
    case 'popular': $sort_sql = 'r.like_count DESC, r.created_at DESC'; break;
}

$db = Database::getInstance();

// WHERE 조건
$where = 'r.is_active = 1';
$params = [];

if ($hospital_id > 0) {
    $where .= ' AND r.hospital_id = ?';
    $params[] = $hospital_id;
}

// 총 개수
$total = (int)$db->fetchValue("SELECT COUNT(*) FROM hospital_reviews r WHERE {$where}", $params);

// 목록 조회
$reviews = $db->fetchAll(
    "SELECT r.id, r.hospital_id, r.title, r.user_name, r.user_avatar,
            r.content, r.rating, r.treatments, r.like_count, r.comment_count,
            r.is_verified, r.created_at
     FROM hospital_reviews r
     WHERE {$where}
     ORDER BY {$sort_sql}
     LIMIT {$offset}, {$limit}",
    $params
);

// 각 후기에 이미지 추가
foreach ($reviews as &$review) {
    $review['images'] = $db->fetchAll(
        "SELECT image_url, thumbnail_url FROM review_images WHERE review_id = ? ORDER BY image_order ASC LIMIT 5",
        [$review['id']]
    );
    $review['rating'] = (int)$review['rating'];
    $review['like_count'] = (int)$review['like_count'];
    $review['comment_count'] = (int)$review['comment_count'];
    $review['is_verified'] = (bool)$review['is_verified'];
    $review['time_ago'] = formatRelativeTime($review['created_at']);
}
unset($review);

jsonSuccess([
    'reviews' => $reviews,
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
    'has_more' => ($offset + $limit) < $total
]);
