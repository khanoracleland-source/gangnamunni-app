<?php
/**
 * 관리자 - 후기 승인/거부 API
 * POST JSON { review_id, action: "approve"|"reject" }
 */
require_once dirname(__DIR__) . '/bootstrap.php';

requireMethod('POST');
requireAdmin(10);
requireCsrf();

$body = getJsonBody();
if (!$body) jsonError('잘못된 요청 데이터입니다.');

$review_id = intval($body['review_id'] ?? 0);
$action = $body['action'] ?? '';

if ($review_id <= 0) jsonError('후기 ID가 필요합니다.');
if (!in_array($action, ['approve', 'reject'])) {
    jsonError('유효한 액션을 선택해주세요. (approve/reject)');
}

$db = Database::getInstance();

$review = $db->fetchOne("SELECT id, hospital_id FROM hospital_reviews WHERE id = ?", [$review_id]);
if (!$review) jsonError('후기를 찾을 수 없습니다.', 404);

if ($action === 'approve') {
    // 공개 기준은 review_status='published' (프론트 reviews.php 와 일치) + is_active=1
    $db->execute(
        "UPDATE hospital_reviews SET review_status = 'published', is_active = 1, updated_at = NOW() WHERE id = ?",
        [$review_id]
    );
    $message = '후기가 승인되었습니다.';
} else {
    $db->execute(
        "UPDATE hospital_reviews SET review_status = 'rejected', is_active = 0, updated_at = NOW() WHERE id = ?",
        [$review_id]
    );
    $message = '후기가 거부되었습니다.';
}

// 병원 통계 업데이트
$hospital_id = $review['hospital_id'];
$db->execute(
    "UPDATE gangnamunni_hospitals SET
        review_count = (SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id = ? AND review_status = 'published'),
        rating = (SELECT COALESCE(AVG(rating), 0) FROM hospital_reviews WHERE hospital_id = ? AND review_status = 'published')
     WHERE id = ?",
    [$hospital_id, $hospital_id, $hospital_id]
);

// 관련 신고도 처리
$db->execute(
    "UPDATE review_reports SET status = 'reviewed', processed_at = NOW()
     WHERE review_id = ? AND status = 'pending'",
    [$review_id]
);

jsonSuccess([], $message);
