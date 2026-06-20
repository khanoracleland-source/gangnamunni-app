<?php
/**
 * AI 맞춤법 검사 API
 * POST: 텍스트를 받아서 AI로 맞춤법/문법 검사 후 수정 제안 반환
 * callAI()로 실제 AI 프로바이더 호출
 * @created 2026-04-06
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/includes/ai_proxy.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false, 'error' => 'DB 연결 실패']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST 요청만 가능합니다']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$text = trim($input['text'] ?? '');
$context = trim($input['context'] ?? 'general'); // general, review, event, ad, community

if (empty($text)) {
    echo json_encode(['success' => false, 'error' => '검사할 텍스트를 입력해주세요']);
    exit;
}

if (mb_strlen($text) > 5000) {
    echo json_encode(['success' => false, 'error' => '텍스트는 5000자 이하만 가능합니다']);
    exit;
}

// 맞춤법 검사 전용 프롬프트
$system_prompt = <<<PROMPT
당신은 한국어 맞춤법/문법 전문 교정 AI입니다.
사용자가 입력한 텍스트를 분석하여 다음을 JSON으로 반환하세요:

{
  "corrected": "교정된 전체 텍스트",
  "changes": [
    {
      "original": "잘못된 부분",
      "corrected": "올바른 표현",
      "type": "spelling|grammar|spacing|punctuation|style",
      "explanation": "수정 이유 (한 줄)"
    }
  ],
  "score": 0~100,
  "summary": "전체 요약 (한 줄)"
}

규칙:
- 맞춤법 오류: 오타, 띄어쓰기, 조사 오류
- 문법 오류: 어미, 시제, 존칭 오류
- 문장 부호: 마침표, 쉼표, 따옴표
- 문체 개선: 어색한 표현, 중복 표현 (type=style)
- 수정 사항이 없으면 changes를 빈 배열로, score=100
- 반드시 유효한 JSON만 출력하세요
PROMPT;

$user_prompt = "다음 텍스트의 맞춤법과 문법을 검사해주세요:\n\n" . $text;

// AI 호출
$result = callAI('spell_check', $user_prompt, [
    'system' => $system_prompt,
    'max_tokens' => 2048,
]);

if (!$result['success']) {
    echo json_encode([
        'success' => false,
        'error' => $result['error'] ?? 'AI 호출 실패',
    ]);
    exit;
}

// AI 응답 파싱
$ai_content = $result['content'] ?? '';

// JSON 추출 (```json ... ``` 감싸진 경우)
if (preg_match('/```json\s*(.*?)\s*```/s', $ai_content, $m)) {
    $ai_content = $m[1];
} elseif (preg_match('/\{.*\}/s', $ai_content, $m)) {
    $ai_content = $m[0];
}

$parsed = json_decode($ai_content, true);

if (!$parsed) {
    // JSON 파싱 실패 시 원본 그대로 반환
    echo json_encode([
        'success' => true,
        'corrected' => $text,
        'changes' => [],
        'score' => 100,
        'summary' => 'AI 응답 파싱 실패 — 원본 유지',
        'raw' => $ai_content,
        'tokens' => $result['tokens'] ?? [],
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'corrected' => $parsed['corrected'] ?? $text,
    'changes' => $parsed['changes'] ?? [],
    'score' => intval($parsed['score'] ?? 100),
    'summary' => $parsed['summary'] ?? '',
    'tokens' => $result['tokens'] ?? [],
    'model' => $result['model'] ?? '',
]);
