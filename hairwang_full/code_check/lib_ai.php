<?php
/**
 * AI 분석 라이브러리
 * 오류 하나를 받아 원인 분석 + 수정안 반환
 * 다른 프로젝트에서도 재사용 가능 (config.php만 교체)
 */

function cc_ai_analyze_error(array $err, mysqli $db): array {
    if (CC_AI_PROVIDER === 'skip') {
        return ['analysis' => '', 'fix_suggestion' => '', 'ai_used' => false];
    }

    // AI API 키 로드 (gangnamunni ai_settings 또는 환경변수)
    $api_key = cc_get_ai_key($db);
    if (!$api_key) {
        return ['analysis' => 'AI 키 없음', 'fix_suggestion' => '', 'ai_used' => false];
    }

    $prompt = cc_build_prompt($err);
    $result = cc_call_anthropic($api_key, $prompt);

    return [
        'analysis'       => $result['analysis'] ?? '',
        'fix_suggestion' => $result['fix'] ?? '',
        'ai_used'        => true,
        'ai_model'       => CC_AI_MODEL,
    ];
}

function cc_build_prompt(array $err): string {
    $file    = basename($err['file']);
    $line    = $err['line_no'] ?? '?';
    $msg     = $err['error_msg'] ?? '';
    $ctx     = $err['context'] ?? '';

    return <<<PROMPT
PHP 파일에서 문법 오류가 발생했습니다. 원인을 분석하고 수정 방법을 알려주세요.

파일: {$file}
오류 위치: {$line}번째 줄
오류 메시지: {$msg}

코드 컨텍스트:
```php
{$ctx}
```

다음 형식으로 답변해주세요:
【원인】(1~2문장)
【수정】(구체적인 코드 수정 방법)
PROMPT;
}

function cc_call_anthropic(string $api_key, string $prompt): array {
    $payload = json_encode([
        'model'      => CC_AI_MODEL,
        'max_tokens' => 400,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($resp, true);
    $text = $data['content'][0]['text'] ?? '';

    // 파싱
    $analysis = '';
    $fix = '';
    if (preg_match('/【원인】(.+?)(?=【수정】|$)/s', $text, $m)) $analysis = trim($m[1]);
    if (preg_match('/【수정】(.+)/s', $text, $m))                 $fix      = trim($m[1]);

    return ['analysis' => $analysis ?: $text, 'fix' => $fix];
}

function cc_get_ai_key(mysqli $db): string {
    // 1) 환경변수 우선
    if ($k = getenv('ANTHROPIC_API_KEY')) return $k;

    // 2) DB에서 복호화된 키 읽기 (gangnamunni_ai_settings)
    $stmt = $db->prepare("SELECT setting_value FROM gangnamunni_ai_settings WHERE setting_key='anthropic_api_key' LIMIT 1");
    if (!$stmt) return '';
    $stmt->execute();
    $stmt->bind_result($enc);
    $stmt->fetch();
    $stmt->close();
    if (!$enc) return '';

    // AES-256-CBC 복호화 (기존 ai_proxy.php 방식)
    $secret = defined('AI_ENCRYPT_KEY') ? AI_ENCRYPT_KEY : 'sb_ai_encrypt_2026';
    $decoded = base64_decode($enc);
    $iv = substr($decoded, 0, 16);
    $cipher = substr($decoded, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', hash('sha256', $secret, true), OPENSSL_RAW_DATA, $iv);
    return $plain ?: '';
}
