<?php
/**
 * AI 후기 검수 API (관리자용)
 * POST - 특정 후기 재검수
 * GET - 검수 로그 조회
 */
require_once __DIR__ . '/bootstrap.php';

$authUser = getAuthUser(true);
requireAdmin(10);

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 검수 로그 조회
    $review_id = intval($_GET['review_id'] ?? 0);
    if ($review_id <= 0) jsonError('review_id가 필요합니다.');

    try {
        $logs = $db->fetchAll(
            "SELECT * FROM review_ai_logs WHERE review_id = ? ORDER BY checked_at DESC LIMIT 10",
            [$review_id]
        );
        foreach ($logs as &$log) {
            $log['messages'] = json_decode($log['messages'], true) ?: [];
        }
        jsonSuccess(['logs' => $logs]);
    } catch (Exception $e) {
        jsonSuccess(['logs' => []]);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 재검수
    requireCsrf();
    $body = getJsonBody();
    $review_id = intval($body['review_id'] ?? 0);
    $action = $body['action'] ?? 'recheck'; // recheck | approve | reject

    if ($review_id <= 0) jsonError('review_id가 필요합니다.');

    $review = $db->fetchOne("SELECT * FROM hospital_reviews WHERE id = ?", [$review_id]);
    if (!$review) jsonError('후기를 찾을 수 없습니다.', 404);

    if ($action === 'approve') {
        $db->execute(
            "UPDATE hospital_reviews SET is_active = 1, review_status = 'published', updated_at = NOW() WHERE id = ?",
            [$review_id]
        );
        // 병원 통계 업데이트
        $db->execute(
            "UPDATE gangnamunni_hospitals SET
                review_count = (SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1),
                rating = (SELECT COALESCE(AVG(LEAST(5, GREATEST(1, rating))), 0) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1)
             WHERE id = ?",
            [$review['hospital_id'], $review['hospital_id'], $review['hospital_id']]
        );
        jsonSuccess(['status' => 'published'], '후기가 승인되었습니다.');

    } elseif ($action === 'reject') {
        $reason = sanitizeInput($body['reason'] ?? '');
        $db->execute(
            "UPDATE hospital_reviews SET is_active = 0, review_status = 'published', updated_at = NOW() WHERE id = ?",
            [$review_id]
        );
        if (!empty($review['is_active'])) {
            $db->execute(
                "UPDATE gangnamunni_hospitals SET
                    review_count = (SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1),
                    rating = (SELECT COALESCE(AVG(LEAST(5, GREATEST(1, rating))), 0) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1)
                 WHERE id = ?",
                [$review['hospital_id'], $review['hospital_id'], $review['hospital_id']]
            );
        }
        jsonSuccess(['status' => 'rejected'], '후기가 거부되었습니다.');

    } else {
        // 재검수
        $ai_result = performAICheck($review['content'], $review['title'], $review['rating'], []);
        try {
            $db->execute(
                "INSERT INTO review_ai_logs (review_id, score, passed, messages, checked_at) VALUES (?, ?, ?, ?, NOW())",
                [$review_id, $ai_result['score'], $ai_result['passed'] ? 1 : 0, json_encode($ai_result['messages'], JSON_UNESCAPED_UNICODE)]
            );
        } catch (Exception $e) {}

        $db->execute(
            "UPDATE hospital_reviews SET ai_score = ?, updated_at = NOW() WHERE id = ?",
            [$ai_result['score'], $review_id]
        );

        jsonSuccess(['ai_check' => $ai_result], '재검수 완료');
    }
} else {
    jsonError('허용되지 않는 메서드입니다.', 405);
}

/**
 * AI 후기 검수 (규칙 기반)
 */
function performAICheck($content, $title, $rating, $images) {
    $score = 100;
    $messages = [];
    $passed = true;
    $len = mb_strlen(strip_tags($content));
    if ($len < 20) { $score -= 30; $messages[] = '내용이 너무 짧습니다'; }
    elseif ($len < 50) { $score -= 10; $messages[] = '더 자세한 후기 권장'; }
    $spam_keywords = ['카톡','텔레그램','telegram','010-','광고','홍보','할인코드','무료체험','선착순','http://','bit.ly','클릭'];
    $spam_count = 0;
    foreach ($spam_keywords as $kw) {
        if (mb_stripos($content . ' ' . $title, $kw) !== false) $spam_count++;
    }
    if ($spam_count >= 3) { $score -= 50; $messages[] = '스팸/광고성 감지'; $passed = false; }
    elseif ($spam_count >= 1) { $score -= 15; $messages[] = '일부 광고성 표현'; }
    $bad_words = ['시발','씨발','ㅅㅂ','지랄','병신','ㅂㅅ','개새끼'];
    foreach ($bad_words as $bw) {
        if (mb_stripos($content . ' ' . $title, $bw) !== false) { $score -= 40; $messages[] = '부적절한 표현'; $passed = false; break; }
    }
    if (preg_match('/(.)\1{9,}/u', $content)) { $score -= 20; $messages[] = '과도한 반복 문자'; }
    $score = max(0, min(100, $score));
    if ($score < 70) $passed = false;
    if ($passed && empty($messages)) $messages[] = '검수 통과';
    return ['score' => $score, 'passed' => $passed, 'messages' => $messages];
}
