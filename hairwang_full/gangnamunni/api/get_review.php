<?php
/**
 * 후기 상세 API
 * GET ?review_id=1
 * 응답: { success, review: { ...data, images[], comments[] } }
 */
require_once __DIR__ . '/bootstrap.php';

requireMethod('GET');

$review_id = intval($_GET['review_id'] ?? 0);
if ($review_id <= 0) {
    jsonError('후기 ID가 필요합니다.');
}

$db = Database::getInstance();

// 후기 조회
$review = $db->fetchOne(
    "SELECT r.*, h.name as hospital_name
     FROM hospital_reviews r
     LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
     WHERE r.id = ? AND r.is_active = 1",
    [$review_id]
);

if (!$review) {
    jsonError('후기를 찾을 수 없습니다.', 404);
}

// 이미지 조회
$review['images'] = $db->fetchAll(
    "SELECT id, image_url, thumbnail_url, image_order, file_size, original_name
     FROM review_images WHERE review_id = ? ORDER BY image_order ASC",
    [$review_id]
);

// 댓글 조회
$review['comments'] = $db->fetchAll(
    "SELECT id, parent_id, user_name, content, like_count, created_at
     FROM review_comments
     WHERE review_id = ? AND is_active = 1
     ORDER BY created_at ASC",
    [$review_id]
);

// 댓글에 상대 시간 추가
foreach ($review['comments'] as &$comment) {
    $comment['time_ago'] = formatRelativeTime($comment['created_at']);
}
unset($comment);

// 타입 변환
$review['rating'] = (int)$review['rating'];
$review['like_count'] = (int)$review['like_count'];
$review['comment_count'] = (int)$review['comment_count'];
$review['is_verified'] = (bool)$review['is_verified'];
$review['time_ago'] = formatRelativeTime($review['created_at']);

jsonSuccess(['review' => $review]);
