<?php
/**
 * 후기 좋아요 토글 API
 * POST JSON { review_id, csrf_token }
 * 응답: { success, liked, like_count }
 */
require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
$authUser = getAuthUser(true);

$body = getJsonBody();
if (!$body) jsonError('잘못된 요청 데이터입니다.');
if (!validateCsrfToken((string)($body['csrf_token'] ?? ''))) {
    jsonError('유효하지 않은 요청입니다. 페이지를 새로고침 해주세요.', 403);
}

$review_id = intval($body['review_id'] ?? 0);
$user_id = $authUser['user_id'];

if ($review_id <= 0) jsonError('후기 ID가 필요합니다.');

$db = Database::getInstance();

// 기존 좋아요 확인
$existing = $db->fetchOne(
    "SELECT id FROM review_likes WHERE review_id = ? AND user_id = ?",
    [$review_id, $user_id]
);

if ($existing) {
    // 좋아요 취소
    $db->execute("DELETE FROM review_likes WHERE id = ?", [$existing['id']]);
    $liked = false;
} else {
    // 좋아요 추가
    $db->execute(
        "INSERT INTO review_likes (review_id, user_id) VALUES (?, ?)",
        [$review_id, $user_id]
    );
    $liked = true;
}

// like_count 업데이트
$like_count = (int)$db->fetchValue(
    "SELECT COUNT(*) FROM review_likes WHERE review_id = ?",
    [$review_id]
);

$db->execute(
    "UPDATE hospital_reviews SET like_count = ? WHERE id = ?",
    [$like_count, $review_id]
);

jsonSuccess([
    'liked' => $liked,
    'like_count' => $like_count
]);
