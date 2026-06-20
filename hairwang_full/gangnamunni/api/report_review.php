<?php
/**
 * 후기 신고 API
 * POST JSON { review_id, reporter_id, reason, description? }
 */
require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
apiRateLimit('report', 5, 60);
$authUser = getAuthUser(true);

$body = getJsonBody();
if (!$body) jsonError('잘못된 요청 데이터입니다.');

$review_id = intval($body['review_id'] ?? 0);
$reporter_id = $authUser['user_id'];
$reason = $body['reason'] ?? '';
$description = sanitizeInput($body['description'] ?? '');

if ($review_id <= 0) jsonError('후기 ID가 필요합니다.');

$valid_reasons = ['spam', 'abuse', 'fake', 'inappropriate', 'other'];
if (!in_array($reason, $valid_reasons)) {
    jsonError('유효한 신고 사유를 선택해주세요.');
}

$db = Database::getInstance();

// 후기 존재 확인
$review = $db->fetchOne(
    "SELECT id FROM hospital_reviews WHERE id = ? AND is_active = 1",
    [$review_id]
);
if (!$review) jsonError('후기를 찾을 수 없습니다.', 404);

// 중복 신고 확인
$existing = $db->fetchOne(
    "SELECT id FROM review_reports WHERE review_id = ? AND reporter_id = ?",
    [$review_id, $reporter_id]
);
if ($existing) jsonError('이미 신고한 후기입니다.');

$db->execute(
    "INSERT INTO review_reports (review_id, reporter_id, reason, description, created_at)
     VALUES (?, ?, ?, ?, NOW())",
    [$review_id, $reporter_id, $reason, $description]
);

jsonSuccess([], '신고가 접수되었습니다. 검토 후 처리됩니다.');
