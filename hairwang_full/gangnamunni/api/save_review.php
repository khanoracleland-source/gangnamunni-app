<?php
/**
 * 후기 저장 API
 * POST JSON
 * 신규: { hospital_id, title, content, editor_content, rating, treatments, images[] }
 * 수정: + review_id
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/ai_review_checker.php';

requireMethod('POST');
apiRateLimit('save_review', 10, 60);
$authUser = getAuthUser(true);
requireCsrf();

$body = getJsonBody();
if (!$body) {
    jsonError('잘못된 요청 데이터입니다.');
}

// 필수 필드 검증
$hospital_id = intval($body['hospital_id'] ?? 0);
$rating = intval($body['rating'] ?? 0);

if ($hospital_id <= 0) jsonError('병원 정보가 필요합니다.');
if ($rating < 1 || $rating > 5) jsonError('별점을 선택해주세요. (1~5)');

$review_id = intval($body['review_id'] ?? 0);
// 세션에서 인증된 사용자 사용 (클라이언트 전달값 무시)
$user_id = $authUser['user_id'];
$title = sanitizeInput($body['title'] ?? '');
$content = sanitizeInput($body['content'] ?? '');
$editor_content = sanitizeHtml($body['editor_content'] ?? '');
$treatments = sanitizeInput($body['treatments'] ?? '');
$images = $body['images'] ?? [];

if (mb_strlen(strip_tags($content)) < 10) {
    jsonError('후기 내용을 10자 이상 작성해주세요.');
}

// 이미지 배열 유효성 검사
if (!is_array($images)) $images = [];
$images = array_slice($images, 0, 10); // 최대 10개

$db = Database::getInstance();

try {
    $pdo = $db->getPdo();
    $pdo->beginTransaction();

    if ($review_id > 0) {
        // 수정 모드 - 소유권 확인
        $existing = $db->fetchOne(
            "SELECT user_name, hospital_id, is_active FROM hospital_reviews WHERE id = ?",
            [$review_id]
        );
        if (!$existing) jsonError('후기를 찾을 수 없습니다.', 404);
        // 작성자 또는 관리자만 수정 가능
        requireOwnership($existing['user_name'] ?? '', $authUser);

        $db->execute(
            "UPDATE hospital_reviews SET
                title = ?, content = ?, editor_content = ?,
                editor_type = 'quill', rating = ?, treatments = ?,
                review_status = 'published', is_active = 0,
                updated_at = NOW()
             WHERE id = ? AND is_active = 1",
            [$title, $content, $editor_content, $rating, $treatments, $review_id]
        );

        // 기존 이미지 삭제 후 재삽입
        $db->execute("DELETE FROM review_images WHERE review_id = ?", [$review_id]);
        if (!empty($existing['is_active'])) {
            $old_hospital_id = (int)$existing['hospital_id'];
            $db->execute(
                "UPDATE gangnamunni_hospitals SET
                    review_count=(SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id=? AND is_active=1),
                    rating=(SELECT COALESCE(AVG(LEAST(5, GREATEST(1, rating))),0) FROM hospital_reviews WHERE hospital_id=? AND is_active=1)
                 WHERE id=?",
                [$old_hospital_id, $old_hospital_id, $old_hospital_id]
            );
        }
    } else {
        // 신규 작성
        // 병원명 조회
        $hospital = $db->fetchOne("SELECT name FROM gangnamunni_hospitals WHERE id = ?", [$hospital_id]);
        $hospital_name = $hospital['name'] ?? '';

        // user_name 처리
        $user_name = '';
        if (!empty($user_id)) {
            $member = $db->fetchOne("SELECT mb_nick FROM g5_member WHERE mb_id = ?", [$user_id]);
            $user_name = $member['mb_nick'] ?? $user_id;
        }
        if (empty($user_name)) {
            $user_name = '익명';
        }

        $db->execute(
            "INSERT INTO hospital_reviews
                (hospital_id, title, user_name, content, editor_content, editor_type, rating, treatments, review_status, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, 'quill', ?, ?, 'published', 0, NOW())",
            [$hospital_id, $title, $user_name, $content, $editor_content, $rating, $treatments]
        );
        $review_id = $db->lastInsertId();

        // === AI 후기 검수 ===
        $aiResult = performAICheck([
            'hospital_name' => $hospital_name,
            'title'         => $title,
            'content'       => $content,
            'rating'        => $rating,
            'images'        => $images,
            'user_name'     => $user_name,
            'review_id'     => (int)$review_id,
        ]);
        $ai_score = $aiResult['score'] ?? 0;

        // ai_score + review_status 업데이트
        $review_status = 'published';
        $db->execute(
            "UPDATE hospital_reviews SET ai_score = ?, review_status = ? WHERE id = ?",
            [$ai_score, $review_status, $review_id]
        );

        // 리뷰어 통계 갱신
        postReviewAIUpdate((int)$review_id, $ai_score, $user_name);

        // Inactive reviews do not affect the cached hospital rating.
        if (false) {


        // 병원 review_count 업데이트
        $db->execute(
            "UPDATE gangnamunni_hospitals SET review_count = (
                SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1
             ) WHERE id = ?",
            [$hospital_id, $hospital_id]
        );

        // 병원 평균 rating 업데이트
        $db->execute(
            "UPDATE gangnamunni_hospitals SET rating = (
                SELECT COALESCE(AVG(rating), 0) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1
             ) WHERE id = ?",
            [$hospital_id, $hospital_id]
        );
    }

    // 이미지 저장
        }
    foreach ($images as $idx => $img_url) {
        if (empty($img_url)) continue;
        $db->execute(
            "INSERT INTO review_images (review_id, image_url, image_order, created_at)
             VALUES (?, ?, ?, NOW())",
            [$review_id, $img_url, $idx]
        );
    }

    $pdo->commit();

    jsonSuccess([
        'review_id' => (int)$review_id,
        'mode' => $review_id > 0 && isset($body['review_id']) && $body['review_id'] > 0 ? 'edit' : 'create',
        'ai' => isset($aiResult) ? ['score' => $aiResult['score'] ?? null, 'passed' => $aiResult['passed'] ?? null, 'category' => $aiResult['category'] ?? null, 'method' => $aiResult['method'] ?? null] : null
    ], $review_id > 0 ? '후기가 수정되었습니다.' : '후기가 등록되었습니다.');

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('후기 저장 중 오류가 발생했습니다.', 500);
}
