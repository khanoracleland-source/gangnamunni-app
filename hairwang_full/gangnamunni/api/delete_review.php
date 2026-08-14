<?php
/**
 * 후기 삭제 API (소프트 삭제)
 * POST JSON { review_id, user_id }
 */
require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
$authUser = getAuthUser(true);
requireCsrf();

$body = getJsonBody();
if (!$body) jsonError('잘못된 요청 데이터입니다.');

$review_id = intval($body['review_id'] ?? 0);
if ($review_id <= 0) jsonError('후기 ID가 필요합니다.');

$db = Database::getInstance();

// 후기 존재 확인
$review = $db->fetchOne(
    "SELECT id, hospital_id, user_id FROM hospital_reviews WHERE id = ? AND is_active = 1",
    [$review_id]
);

if (!$review) {
    jsonError('후기를 찾을 수 없습니다.', 404);
}

// 작성자 또는 관리자만 삭제 가능
requireOwnership($review['user_id'] ?? '', $authUser);

// 소프트 삭제
$db->execute(
    "UPDATE hospital_reviews SET is_active = 0, updated_at = NOW() WHERE id = ?",
    [$review_id]
);

// 병원 review_count/rating 업데이트
$hospital_id = $review['hospital_id'];
$db->execute(
    "UPDATE gangnamunni_hospitals SET
        review_count = (SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1),
        rating = (SELECT COALESCE(AVG(LEAST(5, GREATEST(1, rating))), 0) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1)
     WHERE id = ?",
    [$hospital_id, $hospital_id, $hospital_id]
);

jsonSuccess([], '후기가 삭제되었습니다.');
