<?php
/**
 * 댓글 삭제 API
 * POST JSON { comment_id, user_id }
 */
require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
$authUser = getAuthUser(true);
requireCsrf();

$body = getJsonBody();
if (!$body) jsonError('잘못된 요청 데이터입니다.');

$comment_id = intval($body['comment_id'] ?? 0);
if ($comment_id <= 0) jsonError('댓글 ID가 필요합니다.');

$db = Database::getInstance();

$comment = $db->fetchOne(
    "SELECT id, review_id, user_id FROM review_comments WHERE id = ? AND is_active = 1",
    [$comment_id]
);

if (!$comment) jsonError('댓글을 찾을 수 없습니다.', 404);

// 작성자 또는 관리자만 삭제 가능
requireOwnership($comment['user_id'] ?? '', $authUser);

// 소프트 삭제
$db->execute(
    "UPDATE review_comments SET is_active = 0, updated_at = NOW() WHERE id = ?",
    [$comment_id]
);

// comment_count 업데이트
$db->execute(
    "UPDATE hospital_reviews SET comment_count = (
        SELECT COUNT(*) FROM review_comments WHERE review_id = ? AND is_active = 1
     ) WHERE id = ?",
    [$comment['review_id'], $comment['review_id']]
);

jsonSuccess([], '댓글이 삭제되었습니다.');
