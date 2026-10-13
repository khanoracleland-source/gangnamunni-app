<?php
/**
 * 후기 저장 API v2
 * POST JSON
 * 다축 별점, 의사, 시술비용/날짜, Before/After 이미지, AI 검수
 */
require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
apiRateLimit('save_review', 10, 60);
$authUser = getAuthUser(true);
requireCsrf();

$body = getJsonBody();
if (!$body) jsonError('잘못된 요청 데이터입니다.');

// 필수 필드
$hospital_id = intval($body['hospital_id'] ?? 0);
if ($hospital_id <= 0) jsonError('병원 정보가 필요합니다.');

$review_id = intval($body['review_id'] ?? 0);
$user_id = $authUser['user_id'];
$title = sanitizeInput($body['title'] ?? '');
$content = sanitizeInput($body['content'] ?? '');
$editor_content = sanitizeHtml($body['editor_content'] ?? '');
$treatments = sanitizeInput($body['treatments'] ?? '');

// 다축 별점
$rating = floatval($body['rating'] ?? 0);
$rating_result = max(0, min(5, intval($body['rating_result'] ?? 0)));
$rating_consultation = max(0, min(5, intval($body['rating_consultation'] ?? 0)));
$rating_facility = max(0, min(5, intval($body['rating_facility'] ?? 0)));
$rating_wait = max(0, min(5, intval($body['rating_wait'] ?? 0)));
$rating_cost = max(0, min(5, intval($body['rating_cost'] ?? 0)));
$rating_recovery = max(0, min(5, intval($body['rating_recovery'] ?? 0)));

// 별점 최소 1개 체크
$axis_arr = [$rating_result, $rating_consultation, $rating_facility, $rating_wait, $rating_cost, $rating_recovery];
$rated_count = count(array_filter($axis_arr, function($v) { return $v > 0; }));

// 에디터 설정 로드
$require_rating = true;
try {
    $setting = Database::getInstance()->fetchOne("SELECT setting_value FROM editor_settings WHERE setting_key = 'require_rating' AND is_active = 1");
    if ($setting && ($setting['setting_value'] === '0' || $setting['setting_value'] === 'false')) {
        $require_rating = false;
    }
} catch (Exception $e) {}

if ($require_rating && $rated_count === 0) {
    jsonError('만족도를 1개 이상 평가해주세요.');
}

// 평균 별점 재계산
if ($rated_count > 0) {
    $rating = round(array_sum($axis_arr) / $rated_count, 1);
}

// 의사 정보
$doctor_id = !empty($body['doctor_id']) ? intval($body['doctor_id']) : null;
$doctor_name = !empty($body['doctor_name']) ? sanitizeInput($body['doctor_name']) : null;

// 시술 정보
$procedure_date = !empty($body['procedure_date']) ? sanitizeInput($body['procedure_date']) : null;
$procedure_cost = !empty($body['procedure_cost']) ? intval($body['procedure_cost']) : null;

// 최소 글자수
$min_len = 10;
try {
    $setting = Database::getInstance()->fetchOne("SELECT setting_value FROM editor_settings WHERE setting_key = 'min_content_length' AND is_active = 1");
    if ($setting) $min_len = intval($setting['setting_value']);
} catch (Exception $e) {}

if (mb_strlen(strip_tags($content)) < $min_len) {
    jsonError("후기 내용을 {$min_len}자 이상 작성해주세요.");
}

// 이미지 (카테고리 포함)
// 클라이언트가 보낸 URL 은 그대로 신뢰하지 않는다 — 업로드 경로/http(s) 이외
// (javascript:, data:, 따옴표 주입 등)는 버린다. 저장형 XSS 차단.
$images_raw = $body['images'] ?? [];
if (!is_array($images_raw)) $images_raw = [];
$images = [];
foreach ($images_raw as $_img) {
    $_url = sanitizeImageUrl(is_array($_img) ? ($_img['url'] ?? '') : $_img);
    if ($_url === '') continue;
    $_cat = is_array($_img) ? ($_img['category'] ?? 'general') : 'general';
    if (!in_array($_cat, ['before', 'after', 'general'], true)) $_cat = 'general';
    $images[] = ['url' => $_url, 'category' => $_cat];
}
$images = array_slice($images, 0, 20); // before 5 + after 5 + general 10

$db = Database::getInstance();

// AI 검수
$ai_check = null;
$ai_enabled = true;
try {
    $setting = $db->fetchOne("SELECT setting_value FROM editor_settings WHERE setting_key = 'ai_check_enabled' AND is_active = 1");
    if ($setting && ($setting['setting_value'] === '0' || $setting['setting_value'] === 'false')) {
        $ai_enabled = false;
    }
} catch (Exception $e) {}

if ($ai_enabled) {
    $ai_check = performAICheck($content, $title, $rating, $images);
}

// 자동 승인 설정
$auto_approve = false;
try {
    $setting = $db->fetchOne("SELECT setting_value FROM editor_settings WHERE setting_key = 'auto_approve' AND is_active = 1");
    if ($setting && ($setting['setting_value'] === '1' || $setting['setting_value'] === 'true')) {
        $auto_approve = true;
    }
} catch (Exception $e) {}

// AI 검수 실패 시 자동 승인 불가
$is_active = 0;
$review_status = 'published';
if ($ai_check && !$ai_check['passed']) {
    $is_active = 0;
    $review_status = 'published';
} elseif (!$auto_approve) {
    $is_active = 0;
    $review_status = 'published';
}

try {
    $pdo = $db->getPdo();
    $pdo->beginTransaction();

    if ($review_id > 0) {
        // 수정 모드
        $existing = $db->fetchOne("SELECT user_id, hospital_id, is_active FROM hospital_reviews WHERE id = ?", [$review_id]);
        if (!$existing) jsonError('후기를 찾을 수 없습니다.', 404);
        requireOwnership($existing['user_id'] ?? '', $authUser);

        $db->execute(
            "UPDATE hospital_reviews SET
                title = ?, content = ?, editor_content = ?, editor_type = 'quill',
                rating = ?, rating_result = ?, rating_consultation = ?, rating_facility = ?,
                rating_wait = ?, rating_cost = ?, rating_recovery = ?,
                doctor_id = ?, doctor_name = ?,
                procedure_date = ?, procedure_cost = ?,
                treatments = ?, review_status = ?, is_active = 0,
                ai_score = ?, updated_at = NOW()
             WHERE id = ?",
            [$title, $content, $editor_content,
             $rating, $rating_result, $rating_consultation, $rating_facility,
             $rating_wait, $rating_cost, $rating_recovery,
             $doctor_id, $doctor_name,
             $procedure_date, $procedure_cost,
             $treatments, $review_status,
             $ai_check ? $ai_check['score'] : null, $review_id]
        );
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
        // 신규
        $hospital = $db->fetchOne("SELECT name FROM gangnamunni_hospitals WHERE id = ?", [$hospital_id]);
        $user_name = '';
        if (!empty($user_id)) {
            $member = $db->fetchOne("SELECT mb_nick FROM g5_member WHERE mb_id = ?", [$user_id]);
            $user_name = $member['mb_nick'] ?? $user_id;
        }
        if (empty($user_name)) $user_name = '익명';

        $db->execute(
            "INSERT INTO hospital_reviews
                (hospital_id, title, user_name, user_id, content, editor_content, editor_type,
                 rating, rating_result, rating_consultation, rating_facility,
                 rating_wait, rating_cost, rating_recovery,
                 doctor_id, doctor_name, procedure_date, procedure_cost,
                 treatments, is_active, review_status, ai_score, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'quill',
                     ?, ?, ?, ?,
                     ?, ?, ?,
                     ?, ?, ?, ?,
                     ?, ?, ?, ?, NOW())",
            [$hospital_id, $title, $user_name, $user_id, $content, $editor_content,
             $rating, $rating_result, $rating_consultation, $rating_facility,
             $rating_wait, $rating_cost, $rating_recovery,
             $doctor_id, $doctor_name, $procedure_date, $procedure_cost,
             $treatments, $is_active, $review_status, $ai_check ? $ai_check['score'] : null]
        );
        $review_id = $db->lastInsertId();

        // Inactive reviews do not affect the cached hospital rating.
        if (false) {

        // 병원 통계 업데이트 (활성 후기만)
        $db->execute(
            "UPDATE gangnamunni_hospitals SET review_count = (
                SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1
             ) WHERE id = ?",
            [$hospital_id, $hospital_id]
        );
        $db->execute(
            "UPDATE gangnamunni_hospitals SET rating = (
                SELECT COALESCE(AVG(rating), 0) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1
             ) WHERE id = ?",
            [$hospital_id, $hospital_id]
        );
    }

    // 이미지 저장 (카테고리 포함)
        }
    foreach ($images as $idx => $img) {
        // $images 는 위에서 sanitizeImageUrl() 로 정규화된 ['url','category'] 배열이다
        $img_url = $img['url'];
        $category = $img['category'];
        if ($img_url === '') continue;
        $db->execute(
            "INSERT INTO review_images (review_id, image_url, photo_category, image_order, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$review_id, $img_url, $category, $idx]
        );
    }

    // AI 검수 로그 저장
    if ($ai_check) {
        try {
            $db->execute(
                "INSERT INTO review_ai_logs (review_id, score, passed, messages, checked_at)
                 VALUES (?, ?, ?, ?, NOW())",
                [$review_id, $ai_check['score'], $ai_check['passed'] ? 1 : 0, json_encode($ai_check['messages'], JSON_UNESCAPED_UNICODE)]
            );
        } catch (Exception $e) {
            // 로그 테이블 없어도 계속
        }
    }

    $pdo->commit();

    $result = [
        'review_id' => (int)$review_id,
        'mode' => ($review_id > 0 && isset($body['review_id']) && $body['review_id'] > 0) ? 'edit' : 'create',
        'status' => $review_status
    ];
    if ($ai_check) {
        $result['ai_check'] = [
            'passed' => $ai_check['passed'],
            'score' => $ai_check['score'],
            'status' => $review_status,
            'messages' => $ai_check['messages']
        ];
    }

    $msg = $review_status === 'pending' ? '후기가 등록되었습니다. 검수 후 게시됩니다.' : '후기가 등록되었습니다.';
    jsonSuccess($result, $msg);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('save_review_v2 error: ' . $e->getMessage());
    jsonError('후기 저장 중 오류가 발생했습니다.', 500);
}

/**
 * AI 후기 검수 (규칙 기반 + 점수화)
 */
function performAICheck($content, $title, $rating, $images) {
    $score = 100;
    $messages = [];
    $passed = true;

    // 1. 내용 길이 체크
    $len = mb_strlen(strip_tags($content));
    if ($len < 20) {
        $score -= 30;
        $messages[] = '내용이 너무 짧습니다 (최소 20자 권장)';
    } elseif ($len < 50) {
        $score -= 10;
        $messages[] = '더 자세한 후기를 작성하면 다른 분들에게 도움이 됩니다';
    }

    // 2. 스팸 키워드 체크
    $spam_keywords = ['카톡', '텔레그램', 'telegram', '010-', '광고', '홍보', '할인코드', '무료체험', '선착순', 'http://', 'bit.ly', '클릭'];
    $spam_count = 0;
    foreach ($spam_keywords as $kw) {
        if (mb_stripos($content . ' ' . $title, $kw) !== false) {
            $spam_count++;
        }
    }
    if ($spam_count >= 3) {
        $score -= 50;
        $messages[] = '스팸/광고성 내용이 감지되었습니다';
        $passed = false;
    } elseif ($spam_count >= 1) {
        $score -= 15;
        $messages[] = '일부 광고성 표현이 포함되어 있습니다';
    }

    // 3. 비속어 체크
    $bad_words = ['시발', '씨발', 'ㅅㅂ', '지랄', '병신', 'ㅂㅅ', '개새끼', '미친놈', '미친년'];
    foreach ($bad_words as $bw) {
        if (mb_stripos($content . ' ' . $title, $bw) !== false) {
            $score -= 40;
            $messages[] = '부적절한 표현이 포함되어 있습니다';
            $passed = false;
            break;
        }
    }

    // 4. 반복 문자 체크 (ㅋㅋㅋㅋㅋ, ㅎㅎㅎㅎㅎ 등)
    if (preg_match('/(.)\1{9,}/u', $content)) {
        $score -= 20;
        $messages[] = '과도한 반복 문자가 포함되어 있습니다';
    }

    // 5. 복사 붙여넣기 의심 (동일 문장 반복)
    $sentences = preg_split('/[.!?。]\s*/u', $content);
    $unique_sentences = array_unique(array_filter($sentences, function($s) { return mb_strlen(trim($s)) > 10; }));
    $total_sentences = count(array_filter($sentences, function($s) { return mb_strlen(trim($s)) > 10; }));
    if ($total_sentences > 3 && count($unique_sentences) < $total_sentences * 0.5) {
        $score -= 25;
        $messages[] = '중복된 문장이 많습니다';
    }

    // 6. 사진 포함 보너스
    $img_count = is_array($images) ? count($images) : 0;
    if ($img_count > 0) {
        $score = min(100, $score + 5);
        if ($img_count >= 3) {
            $score = min(100, $score + 5);
        }
    }

    // 7. 제목 유무
    if (empty(trim($title))) {
        $score -= 5;
    }

    // 점수 범위 조정
    $score = max(0, min(100, $score));

    // 70점 미만이면 수동 검수 필요
    if ($score < 70) {
        $passed = false;
    }

    if ($passed && empty($messages)) {
        $messages[] = '검수를 통과했습니다';
    }

    return [
        'score' => $score,
        'passed' => $passed,
        'messages' => $messages
    ];
}
