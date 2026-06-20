<?php
/**
 * AI 실시간 작성 도우미 API
 *
 * 후기/광고 작성 중 AI가 개선 제안을 실시간 제공
 *
 * POST action=suggest : 내용 분석 → 개선 제안 반환
 * POST action=complete : 문장 자동완성 제안
 */
session_start();

// DB 연결
require_once __DIR__ . '/../../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// 로그인 확인 (회원/관리자 모두 허용)
if (empty($_SESSION['ad_admin_logged_in']) && empty($_SESSION['ss_mb_id'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../includes/ai_proxy.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

switch ($action) {

    // ========================================================================
    // 내용 분석 → 개선 제안
    // ========================================================================
    case 'suggest':
        $content = trim($_POST['content'] ?? '');
        $context = trim($_POST['context'] ?? 'review'); // review | ad
        $title = trim($_POST['title'] ?? '');

        if (mb_strlen($content) < 10) {
            echo json_encode(['success' => true, 'suggestions' => [], 'message' => '내용이 너무 짧습니다']);
            break;
        }

        // AI 활성화 체크
        $settingKey = $context === 'ad' ? 'ai_content_suggest' : 'ai_content_suggest';
        $enabled = aiIsEnabled($connect, $settingKey);

        if (!$enabled) {
            // 규칙 기반 제안
            $suggestions = getRuleBasedSuggestions($content, $context);
            echo json_encode([
                'success' => true,
                'suggestions' => $suggestions,
                'method' => 'rule',
            ]);
            break;
        }

        // LLM 제안
        $systemPrompt = getSystemPrompt($context);
        $userPrompt = $title ? "제목: {$title}\n내용: {$content}" : $content;

        $result = callAI('content_suggest', $userPrompt, [
            'system' => $systemPrompt,
            'max_tokens' => 300,
        ]);

        if ($result['success'] && !empty($result['content'])) {
            $parsed = @json_decode($result['content'], true);
            if ($parsed && isset($parsed['suggestions'])) {
                echo json_encode([
                    'success' => true,
                    'suggestions' => $parsed['suggestions'],
                    'score' => $parsed['score'] ?? null,
                    'method' => 'ai',
                    'model' => $result['model'],
                ]);
            } else {
                // JSON 파싱 실패 → 텍스트를 단일 제안으로
                echo json_encode([
                    'success' => true,
                    'suggestions' => [['type' => 'tip', 'text' => $result['content']]],
                    'method' => 'ai',
                ]);
            }
        } else {
            // AI 실패 → 규칙 기반 폴백
            $suggestions = getRuleBasedSuggestions($content, $context);
            echo json_encode([
                'success' => true,
                'suggestions' => $suggestions,
                'method' => 'rule_fallback',
            ]);
        }
        break;

    // ========================================================================
    // 문장 자동완성
    // ========================================================================
    case 'complete':
        $content = trim($_POST['content'] ?? '');
        $context = trim($_POST['context'] ?? 'review');

        if (mb_strlen($content) < 5) {
            echo json_encode(['success' => true, 'completion' => '']);
            break;
        }

        $enabled = aiIsEnabled($connect, 'ai_content_suggest');
        if (!$enabled) {
            echo json_encode(['success' => true, 'completion' => '', 'method' => 'disabled']);
            break;
        }

        $prompt = "다음 한국어 텍스트의 자연스러운 이어질 문장 1개만 제안해주세요. 추가 설명 없이 이어질 문장만 출력하세요.\n\n" . $content;

        $result = callAI('auto_complete', $prompt, [
            'max_tokens' => 100,
        ]);

        echo json_encode([
            'success' => $result['success'],
            'completion' => $result['content'] ?? '',
            'method' => 'ai',
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

// ========================================================================
// Helper Functions
// ========================================================================

function getSystemPrompt(string $context): string
{
    if ($context === 'ad') {
        return <<<PROMPT
당신은 한국 의료 광고 작성을 돕는 AI 어시스턴트입니다.
사용자가 작성 중인 광고 내용을 분석하고, 의료법 준수 + 매력적인 광고 작성을 위한 제안을 JSON으로 반환하세요.

반환 형식:
{"score": 0~100, "suggestions": [{"type": "warning|tip|improve", "text": "제안 내용"}]}

type 설명:
- warning: 의료법 위반 가능성 (과대광고, 비교광고 등)
- tip: 더 효과적인 표현 제안
- improve: 내용 보강 제안

최대 3개 제안. 한국어로 응답하세요.
PROMPT;
    }

    return <<<PROMPT
당신은 한국 성형/미용 후기 작성을 돕는 AI 어시스턴트입니다.
사용자가 작성 중인 후기 내용을 분석하고, 더 좋은 후기 작성을 위한 제안을 JSON으로 반환하세요.

반환 형식:
{"score": 0~100, "suggestions": [{"type": "quality|detail|photo|tip", "text": "제안 내용"}]}

type 설명:
- quality: 내용 품질 개선 (구체적 경험, 기간 언급 등)
- detail: 세부 정보 추가 제안 (수술 부위, 회복 기간 등)
- photo: 사진 관련 제안
- tip: 일반 작성 팁

최대 3개 제안. 한국어로 응답하세요.
PROMPT;
}

/**
 * 규칙 기반 제안 (AI 비활성 시)
 */
function getRuleBasedSuggestions(string $content, string $context): array
{
    $suggestions = [];
    $len = mb_strlen($content);

    // 공통: 길이 체크
    if ($len < 50) {
        $suggestions[] = ['type' => 'quality', 'text' => '내용이 짧습니다. 50자 이상 작성하면 더 유용한 정보를 전달할 수 있어요.'];
    } elseif ($len < 100) {
        $suggestions[] = ['type' => 'tip', 'text' => '좋은 시작입니다! 구체적인 경험을 더 추가하면 신뢰도가 높아져요.'];
    }

    if ($context === 'review') {
        // 후기 관련 제안
        if (!preg_match('/\d+\s*(일|주|개월|달|년)/u', $content)) {
            $suggestions[] = ['type' => 'detail', 'text' => '시술/수술 후 경과 기간을 언급하면 더 도움이 되는 후기가 됩니다.'];
        }
        if (!preg_match('/(의사|선생님|원장|상담)/u', $content)) {
            $suggestions[] = ['type' => 'detail', 'text' => '담당 의사나 상담 경험을 언급하면 다른 분들에게 유용한 정보가 됩니다.'];
        }
        if (!preg_match('/(사진|이미지|비포|애프터|전후)/u', $content)) {
            $suggestions[] = ['type' => 'photo', 'text' => '전후 사진을 첨부하면 후기의 신뢰도가 크게 높아집니다.'];
        }
        if (!preg_match('/(통증|회복|붓기|멍|경과)/u', $content)) {
            $suggestions[] = ['type' => 'detail', 'text' => '회복 과정(통증, 붓기 등)을 공유하면 다른 분들에게 큰 도움이 됩니다.'];
        }
    } elseif ($context === 'ad') {
        // 광고 관련 제안
        $warningPatterns = [
            '/100\s*%\s*(보장|성공|만족)/u' => '100% 보장 표현은 의료법 위반 소지가 있습니다.',
            '/(최고|최초|유일|독보적)/u' => '최상급 표현은 근거 자료가 필요합니다.',
            '/(즉시|바로|당일)\s*(효과|결과)/u' => '즉각적 효과 표현은 과대광고로 판단될 수 있습니다.',
        ];
        foreach ($warningPatterns as $pattern => $msg) {
            if (preg_match($pattern, $content)) {
                $suggestions[] = ['type' => 'warning', 'text' => $msg];
            }
        }
        if (empty($suggestions)) {
            $suggestions[] = ['type' => 'tip', 'text' => '구체적인 시술 정보와 객관적인 효과를 기술하면 신뢰도 높은 광고가 됩니다.'];
        }
    }

    return array_slice($suggestions, 0, 3);
}
