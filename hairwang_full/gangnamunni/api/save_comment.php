<?php
/**
 * 댓글 저장 API
 * POST JSON { review_id, content, user_id, user_name, parent_id? }
 */
require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
apiRateLimit('comment', 20, 60);
$authUser = getAuthUser(true);
requireCsrf();

$body = getJsonBody();
if (!$body) jsonError('잘못된 요청 데이터입니다.');

$review_id = intval($body['review_id'] ?? 0);
$content = sanitizeInput($body['content'] ?? '');
$user_id = $authUser['user_id'];
$user_name = $authUser['user_name'];
$parent_id = intval($body['parent_id'] ?? 0);

if ($review_id <= 0) jsonError('후기 ID가 필요합니다.');
if (mb_strlen($content) < 1) jsonError('댓글 내용을 입력해주세요.');

$db = Database::getInstance();

// 후기 존재 확인
$review = $db->fetchOne(
    "SELECT id FROM hospital_reviews WHERE id = ? AND is_active = 1",
    [$review_id]
);
if (!$review) jsonError('후기를 찾을 수 없습니다.', 404);

// 부모 댓글 확인 (대댓글인 경우)
if ($parent_id > 0) {
    $parent = $db->fetchOne(
        "SELECT id FROM review_comments WHERE id = ? AND review_id = ? AND is_active = 1",
        [$parent_id, $review_id]
    );
    if (!$parent) jsonError('원본 댓글을 찾을 수 없습니다.', 404);
}

// 댓글 저장
$db->execute(
    "INSERT INTO review_comments (review_id, parent_id, user_id, user_name, content, created_at)
     VALUES (?, ?, ?, ?, ?, NOW())",
    [$review_id, $parent_id ?: null, $user_id ?: null, $user_name, $content]
);
$comment_id = $db->lastInsertId();

// comment_count 업데이트
$db->execute(
    "UPDATE hospital_reviews SET comment_count = (
        SELECT COUNT(*) FROM review_comments WHERE review_id = ? AND is_active = 1
     ) WHERE id = ?",
    [$review_id, $review_id]
);

jsonSuccess([
    'comment_id' => (int)$comment_id
], '댓글이 등록되었습니다.');
