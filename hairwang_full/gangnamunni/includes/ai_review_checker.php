<?php
/**
 * 서울미녀 (gangnamunni) - LLM 기반 후기 AI 검수
 *
 * performAICheck() - 후기 내용을 LLM으로 분석하여 품질 점수 산출
 * - AI 설정 OFF → 규칙 기반 fallback
 * - AI 설정 ON → LLM 프롬프트 기반 검수
 *
 * @requires includes/ai_proxy.php
 */

require_once __DIR__ . '/ai_proxy.php';
require_once __DIR__ . '/reviewer_stats.php';

/**
 * 후기 AI 검수 메인 함수
 *
 * @param array $reviewData [
 *   'hospital_name' => string,
 *   'title'         => string,
 *   'content'       => string,
 *   'rating'        => int (1-5),
 *   'images'        => array,
 *   'user_name'     => string,
 *   'review_id'     => int
 * ]
 *
 * @return array [
 *   'score'       => int (0-100),
 *   'passed'      => bool,
 *   'category'    => string (spam|profanity|low_quality|mismatch|ok),
 *   'reason'      => string,
 *   'suggestions' => array,
 *   'method'      => string (ai|rule)
 * ]
 */
function performAICheck(array $reviewData): array
{
    global $connect;

    // AI 활성화 체크
    $aiEnabled = aiIsEnabled($connect, 'ai_check_enabled');

    if ($aiEnabled) {
        $result = performLLMCheck($reviewData);
        if ($result !== null) {
            return $result;
        }
        // LLM 실패 시 규칙 기반 fallback
    }

    return performRuleBasedCheck($reviewData);
}

/**
 * LLM 기반 검수
 */
function performLLMCheck(array $data): ?array
{
    global $connect;

    $hospitalName = $data['hospital_name'] ?? '';
    $title = $data['title'] ?? '';
    $content = $data['content'] ?? '';
    $rating = (int)($data['rating'] ?? 0);
    $photoCount = is_array($data['images'] ?? null) ? count($data['images']) : 0;
    $reviewId = (int)($data['review_id'] ?? 0);

    $systemPrompt = <<<PROMPT
당신은 성형외과 후기 품질 검수 전문가입니다.
아래 후기를 분석하여 반드시 JSON으로만 응답하세요. 다른 텍스트는 포함하지 마세요.

[검수 기준]
1. 스팸/광고: 연락처, 외부링크, 할인코드, 홍보성 문구
2. 비속어/부적절: 욕설, 혐오, 비하 표현
3. 내용 품질: 구체적 시술 경험 vs 무의미 텍스트
4. 별점-내용 일치: 5점인데 불만 내용 등 불일치
5. 의심 패턴: 너무 짧은 칭찬, 복붙 의심, 경쟁병원 비방

[응답 JSON 형식]
{"score":0~100,"passed":true/false,"category":"spam|profanity|low_quality|mismatch|ok","reason":"한줄 설명","suggestions":["개선1","개선2"]}
PROMPT;

    $userPrompt = <<<PROMPT
[후기 정보]
- 병원: {$hospitalName}
- 제목: {$title}
- 내용: {$content}
- 별점: {$rating}/5
- 사진: {$photoCount}장
PROMPT;

    $result = callAI('review_check', $userPrompt, [
        'system'      => $systemPrompt,
        'provider'    => 'anthropic',
        'max_tokens'  => 300,
        'target_id'   => $reviewId,
        'target_type' => 'review',
    ]);

    if (!$result['success']) {
        return null; // fallback to rule-based
    }

    // JSON 파싱
    $aiContent = trim($result['content']);

    // JSON 블록 추출 (코드블록 안에 있을 수 있음)
    if (preg_match('/\{[^{}]*"score"[^{}]*\}/s', $aiContent, $matches)) {
        $aiContent = $matches[0];
    }

    $parsed = json_decode($aiContent, true);
    if (!$parsed || !isset($parsed['score'])) {
        // 파싱 실패 → fallback
        return null;
    }

    $score = max(0, min(100, (int)$parsed['score']));
    $passed = $parsed['passed'] ?? ($score >= 70);
    $category = $parsed['category'] ?? 'ok';
    $reason = $parsed['reason'] ?? '';
    $suggestions = $parsed['suggestions'] ?? [];

    // AI 사용 로그 업데이트 (score, passed)
    if ($reviewId > 0) {
        $scoreEsc = (int)$score;
        $passedInt = $passed ? 1 : 0;
        mysqli_query($connect,
            "UPDATE gangnamunni_ai_usage_log
             SET score = {$scoreEsc}, passed = {$passedInt}
             WHERE target_type = 'review' AND target_id = {$reviewId}
             ORDER BY id DESC LIMIT 1"
        );
    }

    return [
        'score'       => $score,
        'passed'      => (bool)$passed,
        'category'    => $category,
        'reason'      => $reason,
        'suggestions' => $suggestions,
        'method'      => 'ai',
        'model'       => $result['model'] ?? '',
        'tokens'      => $result['tokens'] ?? ['input' => 0, 'output' => 0],
    ];
}

/**
 * 규칙 기반 검수 (AI OFF 또는 fallback)
 */
function performRuleBasedCheck(array $data): array
{
    $content = $data['content'] ?? '';
    $title = $data['title'] ?? '';
    $rating = (int)($data['rating'] ?? 0);
    $photoCount = is_array($data['images'] ?? null) ? count($data['images']) : 0;
    $text = $title . ' ' . $content;

    $score = 50; // 기본 50점
    $category = 'ok';
    $reason = '';
    $suggestions = [];

    // 1. 스팸 키워드 체크
    $spamKeywords = ['카톡', '카카오톡', 'kakao', '연락주세요', '할인코드', '쿠폰', '이벤트 참여',
                     'http://', 'https://', 'www.', '텔레그램', 'telegram', '010-', '02-'];
    foreach ($spamKeywords as $kw) {
        if (mb_stripos($text, $kw) !== false) {
            $score -= 30;
            $category = 'spam';
            $reason = "스팸 키워드 감지: {$kw}";
            break;
        }
    }

    // 2. 비속어 체크
    $profanityKeywords = ['시발', '씨발', 'ㅅㅂ', '개새끼', 'ㄱㅅㄲ', '병신', 'ㅂㅅ',
                          '지랄', 'ㅈㄹ', '미친', '꺼져', '죽어'];
    foreach ($profanityKeywords as $kw) {
        if (mb_stripos($text, $kw) !== false) {
            $score -= 40;
            $category = 'profanity';
            $reason = "부적절한 표현 감지";
            break;
        }
    }

    // 3. 내용 길이 점수
    $contentLen = mb_strlen(strip_tags($content));
    if ($contentLen >= 200) {
        $score += 20;
    } elseif ($contentLen >= 100) {
        $score += 10;
    } elseif ($contentLen < 30) {
        $score -= 15;
        if ($category === 'ok') {
            $category = 'low_quality';
            $reason = '내용이 너무 짧습니다';
        }
        $suggestions[] = '구체적인 시술 경험을 작성해주세요';
    }

    // 4. 사진 보너스
    if ($photoCount >= 3) {
        $score += 15;
    } elseif ($photoCount >= 1) {
        $score += 5;
    } else {
        $suggestions[] = '사진을 추가하면 신뢰도가 높아집니다';
    }

    // 5. 별점-내용 불일치 (간단 체크)
    $negativeWords = ['불만', '후회', '실망', '최악', '다시는', '안 갈'];
    $hasNegative = false;
    foreach ($negativeWords as $nw) {
        if (mb_stripos($text, $nw) !== false) {
            $hasNegative = true;
            break;
        }
    }
    if ($rating >= 4 && $hasNegative) {
        $score -= 10;
        if ($category === 'ok') {
            $category = 'mismatch';
            $reason = '별점과 내용이 일치하지 않을 수 있습니다';
        }
    }

    // 점수 범위 보정
    $score = max(0, min(100, $score));
    $passed = $score >= 50;

    if ($category === 'ok' && empty($reason)) {
        $reason = '규칙 기반 검수 통과';
    }

    return [
        'score'       => $score,
        'passed'      => $passed,
        'category'    => $category,
        'reason'      => $reason,
        'suggestions' => $suggestions,
        'method'      => 'rule',
    ];
}

/**
 * 후기 저장 후 호출: AI 점수 업데이트 + 리뷰어 통계 갱신
 *
 * @param int $reviewId    후기 ID
 * @param int $aiScore     AI 점수 (0-100)
 * @param string $userName 사용자명
 */
function postReviewAIUpdate(int $reviewId, int $aiScore, string $userName): void
{
    global $connect;

    // hospital_reviews에 ai_score 업데이트
    $sql = "UPDATE hospital_reviews SET ai_score = {$aiScore} WHERE id = {$reviewId}";
    mysqli_query($connect, $sql);

    // 리뷰어 통계 갱신
    updateReviewerStats($connect, $userName);
}
