<?php
/**
 * 서울미녀 (gangnamunni) - 광고 AI 검수
 *
 * performAdAICheck() - 광고 내용을 LLM으로 분석하여 위반 항목 검출
 * - AI 설정 OFF → 규칙 기반 fallback
 * - AI 설정 ON → LLM 프롬프트 기반 검수
 *
 * @requires includes/ai_proxy.php
 */

require_once __DIR__ . '/ai_proxy.php';

/**
 * 광고 AI 검수 메인 함수
 *
 * @param array $adData [
 *   'title'    => string,
 *   'subtitle' => string,
 *   'ad_type'  => string (banner|premium),
 *   'link_url' => string,
 *   'ad_id'    => int
 * ]
 *
 * @return array [
 *   'score'      => int (0-100),
 *   'passed'     => bool,
 *   'violations' => array,
 *   'reason'     => string,
 *   'method'     => string (ai|rule)
 * ]
 */
function performAdAICheck(array $adData): array
{
    global $connect;

    $aiEnabled = aiIsEnabled($connect, 'ai_ad_check');

    if ($aiEnabled) {
        $result = performAdLLMCheck($adData);
        if ($result !== null) {
            return $result;
        }
    }

    return performAdRuleCheck($adData);
}

/**
 * LLM 기반 광고 검수
 */
function performAdLLMCheck(array $data): ?array
{
    $title = $data['title'] ?? '';
    $subtitle = $data['subtitle'] ?? '';
    $adType = $data['ad_type'] ?? '';
    $linkUrl = $data['link_url'] ?? '';
    $adId = (int)($data['ad_id'] ?? 0);

    $systemPrompt = <<<PROMPT
당신은 의료 광고 검수 전문가입니다.
아래 광고를 분석하여 반드시 JSON으로만 응답하세요. 다른 텍스트는 포함하지 마세요.

[검수 기준]
1. 과대광고: "100% 보장", "부작용 없음", "최저가" 등
2. 의료법 위반: 비교광고, 환자 유인, 할인 미끼
3. 허위/과장: 근거 없는 효과 주장
4. 필수 고지 누락: 부작용/주의사항 안내 미포함

[응답 JSON 형식]
{"score":0~100,"passed":true/false,"violations":["위반1","위반2"],"reason":"한줄 설명"}
PROMPT;

    $userPrompt = <<<PROMPT
[광고 정보]
- 제목: {$title}
- 부제: {$subtitle}
- 유형: {$adType}
- 링크: {$linkUrl}
PROMPT;

    $result = callAI('ad_check', $userPrompt, [
        'system'      => $systemPrompt,
        'provider'    => 'anthropic',
        'max_tokens'  => 300,
        'target_id'   => $adId,
        'target_type' => 'ad',
    ]);

    if (!$result['success']) {
        return null;
    }

    $aiContent = trim($result['content']);
    if (preg_match('/\{[^{}]*"score"[^{}]*\}/s', $aiContent, $matches)) {
        $aiContent = $matches[0];
    }

    $parsed = json_decode($aiContent, true);
    if (!$parsed || !isset($parsed['score'])) {
        return null;
    }

    return [
        'score'      => max(0, min(100, (int)$parsed['score'])),
        'passed'     => $parsed['passed'] ?? ($parsed['score'] >= 70),
        'violations' => $parsed['violations'] ?? [],
        'reason'     => $parsed['reason'] ?? '',
        'method'     => 'ai',
    ];
}

/**
 * 규칙 기반 광고 검수 (AI OFF 또는 fallback)
 */
function performAdRuleCheck(array $data): array
{
    $title = $data['title'] ?? '';
    $subtitle = $data['subtitle'] ?? '';
    $text = $title . ' ' . $subtitle;

    $score = 70;
    $violations = [];

    // 과대광고 키워드
    $exaggeration = ['100% 보장', '부작용 없음', '부작용 제로', '무조건', '완벽한 결과',
                     '최저가', '최저 비용', '업계 최초', '세계 최초', '전국 1위'];
    foreach ($exaggeration as $kw) {
        if (mb_stripos($text, $kw) !== false) {
            $score -= 20;
            $violations[] = "과대광고: '{$kw}'";
        }
    }

    // 의료법 위반 키워드
    $illegal = ['타 병원보다', '다른 병원', '경쟁 병원', '특가', '반값', '50% 할인',
                '무료 시술', '선착순', '한정 수량'];
    foreach ($illegal as $kw) {
        if (mb_stripos($text, $kw) !== false) {
            $score -= 15;
            $violations[] = "의료법 위반 의심: '{$kw}'";
        }
    }

    // 허위/과장
    $falseAds = ['즉시 효과', '영구적', '흉터 없음', '통증 없음', '100%', '0%'];
    foreach ($falseAds as $kw) {
        if (mb_stripos($text, $kw) !== false) {
            $score -= 15;
            $violations[] = "허위/과장: '{$kw}'";
        }
    }

    $score = max(0, min(100, $score));
    $passed = $score >= 50 && count($violations) === 0;

    return [
        'score'      => $score,
        'passed'     => $passed,
        'violations' => $violations,
        'reason'     => empty($violations) ? '규칙 기반 검수 통과' : count($violations) . '건 위반 감지',
        'method'     => 'rule',
    ];
}
