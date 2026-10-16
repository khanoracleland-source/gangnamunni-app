<?php
/**
 * 서울미녀 (gangnamunni) - 통합 AI 프록시
 *
 * ADSC AiProxyController.php + AiApiKeyController.php 기반 경량 PHP 포팅
 * - 다중 AI 프로바이더 (Anthropic, OpenAI, Google)
 * - API 키 AES-256-CBC 암호화 저장
 * - 우선순위 기반 키 선택 + failover
 * - Rate limit 감지 및 자동 전환
 * - 사용량 로그 (토큰, 비용 추정)
 *
 * @requires PHP 8.0+, openssl, curl
 */

// DB 연결 (이미 $connect 전역변수가 있으면 재사용)
if (!isset($connect) || !$connect) {
    // dbconfig.php에서 상수 로드
    $dbconfig = __DIR__ . '/../dbconfig.php';
    if (file_exists($dbconfig)) {
        require_once $dbconfig;
    }
    $connect = @mysqli_connect(
        defined('G5_MYSQL_HOST') ? G5_MYSQL_HOST : 'localhost',
        defined('G5_MYSQL_USER') ? G5_MYSQL_USER : 'root',
        defined('G5_MYSQL_PASSWORD') ? G5_MYSQL_PASSWORD : '',
        defined('G5_MYSQL_DB') ? G5_MYSQL_DB : 'hms_saas',
        defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306
    );
    if ($connect) {
        mysqli_set_charset($connect, 'utf8mb4');
    }
}

/**
 * 암호화 시크릿 키 (운영 환경에서는 반드시 변경!)
 * data/ai_config.json 또는 editor_settings 테이블에서 로드 가능
 */
function aiGetEncryptionSecret(): string
{
    static $secret = null;
    if ($secret !== null) return $secret;

    // data/ai_config.json에서 로드 시도
    $configFile = __DIR__ . '/../data/ai_config.json';
    if (file_exists($configFile)) {
        $cfg = json_decode(file_get_contents($configFile), true);
        if (!empty($cfg['encryption_secret'])) {
            $secret = $cfg['encryption_secret'];
            return $secret;
        }
    }

    // data/ai_config.json 없으면 랜덤 시크릿 생성 후 파일에 저장
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0750, true);
    }
    $secret = bin2hex(random_bytes(32));
    @file_put_contents($configFile, json_encode(['encryption_secret' => $secret], JSON_PRETTY_PRINT));
    return $secret;
}

/**
 * API 키 암호화 (AES-256-CBC)
 */
function aiEncryptKey(string $plainKey): string
{
    $method = 'aes-256-cbc';
    $secret = aiGetEncryptionSecret();
    $key = hash('sha256', $secret, true);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));

    $encrypted = openssl_encrypt($plainKey, $method, $key, OPENSSL_RAW_DATA, $iv);

    // IV + ciphertext를 합쳐서 base64
    return base64_encode($iv . $encrypted);
}

/**
 * API 키 복호화
 */
function aiDecryptKey(string $encryptedBase64): ?string
{
    $method = 'aes-256-cbc';
    $secret = aiGetEncryptionSecret();
    $key = hash('sha256', $secret, true);
    $ivLen = openssl_cipher_iv_length($method);

    $raw = base64_decode($encryptedBase64, true);
    if ($raw === false || strlen($raw) <= $ivLen) {
        return null;
    }

    $iv = substr($raw, 0, $ivLen);
    $cipherText = substr($raw, $ivLen);

    $decrypted = openssl_decrypt($cipherText, $method, $key, OPENSSL_RAW_DATA, $iv);

    return ($decrypted !== false) ? $decrypted : null;
}

/**
 * API 키 마스킹 (앞 6자 + **** + 뒤 4자)
 */
function aiMaskKey(string $key): string
{
    $len = mb_strlen($key);
    if ($len <= 4) return '****';
    if ($len > 12) {
        return mb_substr($key, 0, 6) . '****' . mb_substr($key, -4);
    }
    return str_repeat('*', $len - 4) . mb_substr($key, -4);
}

// ========================================================================
// 기본 모델 매핑
// ========================================================================
define('AI_DEFAULT_MODELS', [
    'anthropic' => 'claude-haiku-4-5-20251001',
    'openai'    => 'gpt-4o-mini',
    'google'    => 'gemini-2.0-flash',
    'deepseek'  => 'deepseek-chat', // [추가 2026-06-19] 최저가 옵션 (OpenAI 호환 API)
]);

define('AI_REQUEST_TIMEOUT', 60);

// ========================================================================
// 키 관리 함수
// ========================================================================

/**
 * 활성 AI 키 조회 (우선순위 기반)
 * rate_limited_until이 지나지 않은 키는 건너뜀
 */
function aiFindActiveKey(mysqli $conn, string $provider): ?array
{
    $provider = mysqli_real_escape_string($conn, $provider);
    $sql = "SELECT id, provider, api_key_encrypted, model_name, priority,
                   usage_count, daily_usage_count, daily_usage_reset_at,
                   rate_limited_until, config_json
            FROM gangnamunni_ai_keys
            WHERE provider = '{$provider}'
              AND is_active = 1
              AND (rate_limited_until IS NULL OR rate_limited_until < NOW())
            ORDER BY priority ASC
            LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row;
    }
    return null;
}

/**
 * 모든 활성 키 조회 (failover용)
 */
function aiFindAllActiveKeys(mysqli $conn, string $provider): array
{
    $provider = mysqli_real_escape_string($conn, $provider);
    $sql = "SELECT id, provider, api_key_encrypted, model_name, priority,
                   usage_count, daily_usage_count, daily_usage_reset_at,
                   rate_limited_until, config_json
            FROM gangnamunni_ai_keys
            WHERE provider = '{$provider}'
              AND is_active = 1
            ORDER BY priority ASC";
    $result = mysqli_query($conn, $sql);
    $keys = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            // rate limited 건너뜀
            if (!empty($row['rate_limited_until']) && strtotime($row['rate_limited_until']) > time()) {
                continue;
            }
            $keys[] = $row;
        }
    }
    return $keys;
}

/**
 * Rate limit 기록 (1시간 후 자동 해제)
 */
function aiHandleRateLimit(mysqli $conn, int $keyId): void
{
    $sql = "UPDATE gangnamunni_ai_keys
            SET rate_limited_until = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                updated_at = NOW()
            WHERE id = {$keyId}";
    mysqli_query($conn, $sql);
}

/**
 * 사용량 증가
 */
function aiIncrementUsage(mysqli $conn, int $keyId): void
{
    // 일일 카운트 리셋 체크
    $sql = "UPDATE gangnamunni_ai_keys
            SET usage_count = usage_count + 1,
                daily_usage_count = IF(daily_usage_reset_at = CURDATE(), daily_usage_count + 1, 1),
                daily_usage_reset_at = CURDATE(),
                updated_at = NOW()
            WHERE id = {$keyId}";
    mysqli_query($conn, $sql);
}

// ========================================================================
// 사용 로그
// ========================================================================

/**
 * AI 사용 로그 기록
 */
function aiLogUsage(mysqli $conn, array $params): void
{
    $provider = mysqli_real_escape_string($conn, $params['provider'] ?? '');
    $model = mysqli_real_escape_string($conn, $params['model'] ?? '');
    $action = mysqli_real_escape_string($conn, $params['action_type'] ?? 'unknown');
    $inputTokens = (int)($params['input_tokens'] ?? 0);
    $outputTokens = (int)($params['output_tokens'] ?? 0);
    $targetId = (int)($params['target_id'] ?? 0);
    $targetType = mysqli_real_escape_string($conn, $params['target_type'] ?? '');
    $score = isset($params['score']) ? (int)$params['score'] : 'NULL';
    $passed = isset($params['passed']) ? ((int)$params['passed']) : 'NULL';
    $summary = mysqli_real_escape_string($conn, $params['result_summary'] ?? '');

    $sql = "INSERT INTO gangnamunni_ai_usage_log
            (provider, model_name, action_type, input_tokens, output_tokens,
             target_id, target_type, score, passed, result_summary, created_at)
            VALUES ('{$provider}', '{$model}', '{$action}', {$inputTokens}, {$outputTokens},
                    {$targetId}, '{$targetType}', {$score}, {$passed}, '{$summary}', NOW())";
    mysqli_query($conn, $sql);
}

/**
 * ────────────────────────────────────────────────────────────────────────
 * 호출자 단위 사용량 제한 (2026-10-15)
 *
 * callAI() 자체에는 호출자 단위 한도가 없다. 비용 통제는 hospital_id 가
 * 주어진 경우의 병원 지갑 차감(ai_cost_from_wallet)뿐이고, hospital_id 없이
 * 부르는 엔드포인트는 전부 플랫폼 키로 무료·무제한이 된다. 회원이 접근할 수
 * 있는 엔드포인트(api/ai_suggest.php)가 여기에 해당했다.
 *
 * gnu_login_rate_hit()(auth/login.php)과 같은 파일 락 방식. 저장 위치는
 * 웹에서 차단된 data/ 하위이며, 1% 확률로 하루 지난 파일을 청소한다.
 *
 * @param string $scope  제한 종류 (member / ip / action 등)
 * @param string $value  식별자 (mb_id, IP …)
 * @param int    $limit  $window 초 동안 허용 횟수
 * @param int    $window 초 단위 창 크기
 * @param bool   $record false 면 세지 않고 현재 상태만 확인
 * @return array ['allowed'=>bool, 'retry'=>int]  retry = 몇 초 뒤 재시도 가능
 */
function aiRateHit(string $scope, string $value, int $limit, int $window, bool $record = true): array
{
    $base = defined('G5_DATA_PATH') ? G5_DATA_PATH : __DIR__ . '/../data';
    if (!is_dir($base)) $base = sys_get_temp_dir();
    $dir = $base . '/cache/ai_rate';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        // 디렉터리를 만들 수 없으면 fail-closed — 무제한 호출보다 거부가 안전하다.
        return ['allowed' => false, 'retry' => 60];
    }

    $path = $dir . '/' . hash('sha256', $scope . '|' . strtolower($value)) . '.json';
    $fp = @fopen($path, 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        if ($fp) fclose($fp);
        return ['allowed' => false, 'retry' => 60];
    }

    $data = json_decode(stream_get_contents($fp) ?: '', true);
    $now = time();
    $hits = is_array($data['hits'] ?? null) ? $data['hits'] : [];
    $hits = array_values(array_filter($hits, function ($ts) use ($now, $window) {
        return (int)$ts > $now - $window;
    }));

    $retry = count($hits) >= $limit ? max(1, $window - ($now - (int)$hits[0])) : 0;
    if (!$retry && $record) $hits[] = $now;

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode(['hits' => $hits]));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    if (random_int(1, 100) === 1) {
        foreach (glob($dir . '/*.json') ?: [] as $old) {
            if (@filemtime($old) < $now - 86400) @unlink($old);
        }
    }

    return ['allowed' => $retry === 0, 'retry' => $retry];
}

/**
 * 회원/관리자용 AI 엔드포인트의 표준 한도 검사.
 * 분당 버스트 + 일간 총량을 호출자별로, 그리고 IP별로(다중 계정 대비) 함께 본다.
 *
 * @return array ['allowed'=>bool, 'retry'=>int, 'reason'=>string]
 */
function aiCheckCallerQuota(string $callerId, bool $isAdmin = false): array
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $perMinute = $isAdmin ? 60 : 10;
    $perDay    = $isAdmin ? 1000 : 100;

    $checks = [
        ['caller_min', $callerId, $perMinute, 60,    'burst'],
        ['caller_day', $callerId, $perDay,    86400, 'daily'],
        // 계정을 여러 개 만들어 돌리는 경우까지 덮는다.
        ['ip_min',     $ip,       30,         60,    'ip_burst'],
        ['ip_day',     $ip,       300,        86400, 'ip_daily'],
    ];

    // 먼저 전부 확인만 한다 — 한 곳이라도 막히면 다른 카운터를 올리지 않는다.
    foreach ($checks as $c) {
        if ($c[1] === '') continue;
        $state = aiRateHit($c[0], $c[1], $c[2], $c[3], false);
        if (!$state['allowed']) {
            return ['allowed' => false, 'retry' => $state['retry'], 'reason' => $c[4]];
        }
    }
    foreach ($checks as $c) {
        if ($c[1] === '') continue;
        aiRateHit($c[0], $c[1], $c[2], $c[3], true);
    }

    return ['allowed' => true, 'retry' => 0, 'reason' => ''];
}

// ========================================================================
// AI 호출 메인 함수
// ========================================================================

/**
 * 통합 AI 호출 함수
 *
 * @param string $actionType  용도 (review_check, ad_check, test 등)
 * @param string $prompt      사용자 프롬프트 (또는 시스템 + 사용자 합침)
 * @param array  $options     추가 옵션
 *   - provider: anthropic|openai|google (기본: anthropic)
 *   - model: 모델명 (기본: 프로바이더별 기본)
 *   - max_tokens: 최대 출력 토큰 (기본: 1024)
 *   - system: 시스템 프롬프트 (선택)
 *   - json_mode: true면 JSON 응답 기대
 *   - target_id: 대상 ID (로그용)
 *   - target_type: 대상 유형 (로그용)
 *
 * @return array ['success'=>bool, 'content'=>string, 'tokens'=>[input,output], 'model'=>string, 'error'=>string]
 */
function callAI(string $actionType, string $prompt, array $options = []): array
{
    global $connect;

    $provider = $options['provider'] ?? 'anthropic';
    $model = $options['model'] ?? (AI_DEFAULT_MODELS[$provider] ?? 'default');
    $maxTokens = max(1, min((int)($options['max_tokens'] ?? 1024), 8192));
    $systemPrompt = $options['system'] ?? null;
    $hospitalId = (int)($options['hospital_id'] ?? 0);

    // 병원별 AI 설정 체크
    $useOwnKey = false;
    $ownApiKey = null;
    $chargeWallet = false;

    if ($hospitalId > 0) {
        // getHospitalAiSettings()/chargeAiFromWallet() 는 cpv_helper.php 에 있다.
        // 예전에는 function_exists() 로만 확인해서, 이 헬퍼를 include 하지 않은 호출부
        // (api_ai_review.php, api_spell_check.php, ai_*_checker.php …)에서는 병원의
        // ai_enabled=0 설정과 지갑 차감이 통째로 건너뛰어지고 플랫폼 키로 무료 호출됐다.
        if (!function_exists('getHospitalAiSettings')) {
            $cpvHelper = __DIR__ . '/cpv_helper.php';
            if (is_file($cpvHelper)) require_once $cpvHelper;
        }
        $hospitalAi = function_exists('getHospitalAiSettings') ? getHospitalAiSettings($hospitalId) : null;
        // 설정을 읽지 못하면(헬퍼 없음 / DB 오류 / 없는 병원) fail-closed — 병원 비용으로 무단 호출 금지.
        if (!is_array($hospitalAi)) {
            error_log('[callAI] hospital AI settings unavailable; hospital_id=' . $hospitalId . '; action=' . $actionType);
            return [
                'success' => false,
                'content' => '',
                'tokens'  => ['input' => 0, 'output' => 0],
                'model'   => $model,
                'error'   => '병원 AI 설정을 확인할 수 없습니다.',
            ];
        }
        if ($hospitalAi) {
            // 병원 AI 비활성이면 거부
            if (empty($hospitalAi['ai_enabled']) || $hospitalAi['ai_enabled'] !== '1') {
                return [
                    'success' => false,
                    'content' => '',
                    'tokens'  => ['input' => 0, 'output' => 0],
                    'model'   => $model,
                    'error'   => '이 병원은 AI 기능이 비활성화되어 있습니다.',
                ];
            }

            // 자체 키 사용 여부
            if (!empty($hospitalAi['ai_use_own_key']) && $hospitalAi['ai_use_own_key'] === '1' && !empty($hospitalAi['ai_own_api_key'])) {
                $useOwnKey = true;
                $ownApiKey = aiDecryptKey($hospitalAi['ai_own_api_key']);
                if (!empty($hospitalAi['ai_own_provider'])) {
                    $provider = $hospitalAi['ai_own_provider'];
                    $model = $options['model'] ?? (AI_DEFAULT_MODELS[$provider] ?? 'default');
                }
            }

            // 플랫폼 키 사용 시 지갑 차감 여부
            if (!$useOwnKey && !empty($hospitalAi['ai_cost_from_wallet']) && $hospitalAi['ai_cost_from_wallet'] === '1') {
                $chargeWallet = true;
            }
        }
    }

    // 플랫폼 키 사용 + 지갑 차감 필요 시 잔액 사전 체크
    if ($chargeWallet && !function_exists('chargeAiFromWallet')) {
        return [
            'success' => false,
            'content' => '',
            'tokens'  => ['input' => 0, 'output' => 0],
            'model'   => $model,
            'error'   => 'AI 비용 차감 모듈을 불러올 수 없습니다.',
        ];
    }
    if ($chargeWallet) {
        $chargeResult = chargeAiFromWallet($hospitalId, $actionType);
        if (!$chargeResult['success']) {
            return [
                'success' => false,
                'content' => '',
                'tokens'  => ['input' => 0, 'output' => 0],
                'model'   => $model,
                'error'   => $chargeResult['message'],
            ];
        }
    }

    // 메시지 배열 구성
    $messages = [];
    if ($systemPrompt) {
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    }
    $messages[] = ['role' => 'user', 'content' => $prompt];

    // 자체 키 사용 시 단일 키로 호출
    if ($useOwnKey && $ownApiKey) {
        try {
            $result = match ($provider) {
                'anthropic' => aiProxyAnthropic($ownApiKey, $messages, $model, $maxTokens),
                'openai'    => aiProxyOpenAI($ownApiKey, $messages, $model, $maxTokens),
                'google'    => aiProxyGoogle($ownApiKey, $messages, $model, $maxTokens),
                'deepseek'  => aiProxyDeepSeek($ownApiKey, $messages, $model, $maxTokens),
                default     => throw new RuntimeException("Unsupported provider: {$provider}"),
            };

            aiLogUsage($connect, [
                'provider'       => $provider,
                'model'          => $result['model'] ?? $model,
                'action_type'    => $actionType,
                'input_tokens'   => $result['tokens']['input'] ?? 0,
                'output_tokens'  => $result['tokens']['output'] ?? 0,
                'target_id'      => $options['target_id'] ?? 0,
                'target_type'    => $options['target_type'] ?? '',
                'result_summary' => mb_substr($result['content'] ?? '', 0, 200),
            ]);

            return [
                'success' => true,
                'content' => $result['content'] ?? '',
                'tokens'  => $result['tokens'] ?? ['input' => 0, 'output' => 0],
                'model'   => $result['model'] ?? $model,
                'error'   => '',
            ];
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'content' => '',
                'tokens'  => ['input' => 0, 'output' => 0],
                'model'   => $model,
                'error'   => '병원 자체 키 오류: ' . $e->getMessage(),
            ];
        }
    }

    // 플랫폼 키 목록 (failover)
    $keys = aiFindAllActiveKeys($connect, $provider);
    if (empty($keys)) {
        // [개선 2026-06-19] 영문 기술 메시지 → 사용자 친화 한글 안내
        $provLabel = ['anthropic'=>'Anthropic(Claude)','openai'=>'OpenAI','google'=>'Google(Gemini)','deepseek'=>'DeepSeek'][$provider] ?? $provider;
        return [
            'success' => false,
            'content' => '',
            'tokens'  => ['input' => 0, 'output' => 0],
            'model'   => $model,
            'error'   => "AI 키가 등록되어 있지 않습니다 ({$provLabel}). 관리자 → AI 자동화 설정 → API 키 관리에서 키를 등록하면 AI 기능이 동작합니다.",
        ];
    }

    $lastError = '';

    // 키 순회 (failover)
    foreach ($keys as $keyRecord) {
        $apiKey = aiDecryptKey($keyRecord['api_key_encrypted']);
        if (empty($apiKey)) {
            $lastError = 'Failed to decrypt API key (id=' . $keyRecord['id'] . ')';
            continue;
        }

        // 키에 모델 지정이 있으면 사용
        if (!empty($keyRecord['model_name']) && empty($options['model'])) {
            $model = $keyRecord['model_name'];
        }

        try {
            $result = match ($provider) {
                'anthropic' => aiProxyAnthropic($apiKey, $messages, $model, $maxTokens),
                'openai'    => aiProxyOpenAI($apiKey, $messages, $model, $maxTokens),
                'google'    => aiProxyGoogle($apiKey, $messages, $model, $maxTokens),
                'deepseek'  => aiProxyDeepSeek($apiKey, $messages, $model, $maxTokens),
                default     => throw new RuntimeException("Unsupported provider: {$provider}"),
            };

            // 성공 → 사용량 기록
            aiIncrementUsage($connect, (int)$keyRecord['id']);

            // 로그
            aiLogUsage($connect, [
                'provider'       => $provider,
                'model'          => $result['model'] ?? $model,
                'action_type'    => $actionType,
                'input_tokens'   => $result['tokens']['input'] ?? 0,
                'output_tokens'  => $result['tokens']['output'] ?? 0,
                'target_id'      => $options['target_id'] ?? 0,
                'target_type'    => $options['target_type'] ?? '',
                'result_summary' => mb_substr($result['content'] ?? '', 0, 200),
            ]);

            return [
                'success' => true,
                'content' => $result['content'] ?? '',
                'tokens'  => $result['tokens'] ?? ['input' => 0, 'output' => 0],
                'model'   => $result['model'] ?? $model,
                'error'   => '',
            ];
        } catch (RuntimeException $e) {
            $lastError = $e->getMessage();

            // Rate limit → 이 키 표시 후 다음 키 시도
            if (stripos($lastError, 'rate') !== false || stripos($lastError, '429') !== false) {
                aiHandleRateLimit($connect, (int)$keyRecord['id']);
                continue;
            }

            // 인증 오류 → 다음 키
            if (stripos($lastError, '401') !== false || stripos($lastError, 'auth') !== false) {
                continue;
            }

            // 기타 오류 → 중단
            break;
        }
    }

    // 모든 키 실패
    return [
        'success' => false,
        'content' => '',
        'tokens'  => ['input' => 0, 'output' => 0],
        'model'   => $model,
        'error'   => $lastError,
    ];
}

// ========================================================================
// 프로바이더별 프록시
// ========================================================================

/**
 * Anthropic Claude API
 */
function aiProxyAnthropic(string $apiKey, array $messages, string $model, int $maxTokens): array
{
    // system 분리
    $systemPrompt = null;
    $filtered = [];
    foreach ($messages as $msg) {
        if (($msg['role'] ?? '') === 'system') {
            $systemPrompt = $msg['content'] ?? '';
        } else {
            $filtered[] = [
                'role'    => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? '',
            ];
        }
    }

    $payload = [
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'messages'   => $filtered,
    ];
    if ($systemPrompt !== null) {
        $payload['system'] = $systemPrompt;
    }

    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $apiKey,
        'anthropic-version: 2023-06-01',
    ];

    $resp = aiHttpPost('https://api.anthropic.com/v1/messages', $payload, $headers);
    $data = json_decode($resp['body'], true);

    if ($resp['http_code'] !== 200 || $data === null) {
        $err = $data['error']['message'] ?? $data['message'] ?? ('HTTP ' . $resp['http_code']);
        throw new RuntimeException($err);
    }

    $content = '';
    foreach (($data['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $content .= $block['text'] ?? '';
        }
    }

    return [
        'content' => $content,
        'model'   => $data['model'] ?? $model,
        'tokens'  => [
            'input'  => $data['usage']['input_tokens'] ?? 0,
            'output' => $data['usage']['output_tokens'] ?? 0,
        ],
    ];
}

/**
 * OpenAI GPT API
 */
function aiProxyOpenAI(string $apiKey, array $messages, string $model, int $maxTokens): array
{
    $formatted = [];
    foreach ($messages as $msg) {
        $formatted[] = [
            'role'    => $msg['role'] ?? 'user',
            'content' => $msg['content'] ?? '',
        ];
    }

    $payload = [
        'model'      => $model,
        'messages'   => $formatted,
        'max_tokens' => $maxTokens,
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    $resp = aiHttpPost('https://api.openai.com/v1/chat/completions', $payload, $headers);
    $data = json_decode($resp['body'], true);

    if ($resp['http_code'] !== 200 || $data === null) {
        $err = $data['error']['message'] ?? $data['message'] ?? ('HTTP ' . $resp['http_code']);
        throw new RuntimeException($err);
    }

    return [
        'content' => $data['choices'][0]['message']['content'] ?? '',
        'model'   => $data['model'] ?? $model,
        'tokens'  => [
            'input'  => $data['usage']['prompt_tokens'] ?? 0,
            'output' => $data['usage']['completion_tokens'] ?? 0,
        ],
    ];
}

/**
 * DeepSeek API (OpenAI 호환 — 최저가 옵션) [추가 2026-06-19]
 * base만 다르고 페이로드/응답 구조는 OpenAI Chat Completions와 동일.
 */
function aiProxyDeepSeek(string $apiKey, array $messages, string $model, int $maxTokens): array
{
    $formatted = [];
    foreach ($messages as $msg) {
        $formatted[] = ['role' => $msg['role'] ?? 'user', 'content' => $msg['content'] ?? ''];
    }
    $payload = ['model' => $model ?: 'deepseek-chat', 'messages' => $formatted, 'max_tokens' => $maxTokens];
    $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];

    $resp = aiHttpPost('https://api.deepseek.com/v1/chat/completions', $payload, $headers);
    $data = json_decode($resp['body'], true);
    if ($resp['http_code'] !== 200 || $data === null) {
        $err = $data['error']['message'] ?? $data['message'] ?? ('HTTP ' . $resp['http_code']);
        throw new RuntimeException($err);
    }
    return [
        'content' => $data['choices'][0]['message']['content'] ?? '',
        'model'   => $data['model'] ?? $model,
        'tokens'  => [
            'input'  => $data['usage']['prompt_tokens'] ?? 0,
            'output' => $data['usage']['completion_tokens'] ?? 0,
        ],
    ];
}

/**
 * Google Gemini API
 */
function aiProxyGoogle(string $apiKey, array $messages, string $model, int $maxTokens): array
{
    $systemInstruction = null;
    $contents = [];
    foreach ($messages as $msg) {
        $role = $msg['role'] ?? 'user';
        $text = $msg['content'] ?? '';

        if ($role === 'system') {
            $systemInstruction = $text;
            continue;
        }

        $geminiRole = ($role === 'assistant') ? 'model' : 'user';
        $contents[] = [
            'role'  => $geminiRole,
            'parts' => [['text' => $text]],
        ];
    }

    $payload = [
        'contents'         => $contents,
        'generationConfig' => ['maxOutputTokens' => $maxTokens],
    ];
    if ($systemInstruction !== null) {
        $payload['systemInstruction'] = ['parts' => [['text' => $systemInstruction]]];
    }

    $url = sprintf(
        'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
        urlencode($model),
        urlencode($apiKey)
    );

    $resp = aiHttpPost($url, $payload, ['Content-Type: application/json']);
    $data = json_decode($resp['body'], true);

    if ($resp['http_code'] !== 200 || $data === null) {
        $err = $data['error']['message'] ?? $data['message'] ?? ('HTTP ' . $resp['http_code']);
        throw new RuntimeException($err);
    }

    $content = '';
    $candidates = $data['candidates'] ?? [];
    if (!empty($candidates)) {
        foreach (($candidates[0]['content']['parts'] ?? []) as $part) {
            $content .= $part['text'] ?? '';
        }
    }

    $usage = $data['usageMetadata'] ?? [];
    return [
        'content' => $content,
        'model'   => $model,
        'tokens'  => [
            'input'  => $usage['promptTokenCount'] ?? 0,
            'output' => $usage['candidatesTokenCount'] ?? 0,
        ],
    ];
}

// ========================================================================
// HTTP 헬퍼
// ========================================================================

/**
 * cURL HTTP POST (stream_context 폴백)
 */
function aiHttpPost(string $url, array $payload, array $headers): array
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => AI_REQUEST_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('HTTP request failed: ' . $curlError);
        }

        return ['http_code' => $httpCode, 'body' => $body];
    }

    // stream_context 폴백
    $headerStr = implode("\r\n", $headers) . "\r\n";
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => $headerStr,
            'content'       => $json,
            'timeout'       => AI_REQUEST_TIMEOUT,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    $httpCode = 0;
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('/^HTTP\/[\d.]+\s+(\d+)/', $h, $m)) {
                $httpCode = (int)$m[1];
            }
        }
    }

    if ($body === false) {
        throw new RuntimeException('HTTP request failed (stream).');
    }

    return ['http_code' => $httpCode, 'body' => $body];
}

// ========================================================================
// 키 테스트 함수
// ========================================================================

/**
 * API 키 유효성 테스트 (최소 API 호출)
 */
function aiTestKey(string $provider, string $apiKey): array
{
    try {
        switch ($provider) {
            case 'anthropic':
                $resp = aiHttpPost('https://api.anthropic.com/v1/messages', [
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 1,
                    'messages'   => [['role' => 'user', 'content' => 'hi']],
                ], [
                    'Content-Type: application/json',
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01',
                ]);
                break;

            case 'openai':
                // GET /v1/models 대신 최소 chat 요청
                $ch = curl_init('https://api.openai.com/v1/models');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $body = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $resp = ['http_code' => $code, 'body' => $body ?: ''];
                break;

            case 'google':
                $url = 'https://generativelanguage.googleapis.com/v1/models?key=' . urlencode($apiKey);
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $body = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $resp = ['http_code' => $code, 'body' => $body ?: ''];
                break;

            case 'deepseek': // [추가 2026-06-19] OpenAI 호환 — /v1/models 로 유효성 확인
                $ch = curl_init('https://api.deepseek.com/v1/models');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                $body = curl_exec($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $resp = ['http_code' => $code, 'body' => $body ?: ''];
                break;

            default:
                return ['success' => false, 'message' => 'Unsupported provider: ' . $provider];
        }

        if ($resp['http_code'] === 200 || $resp['http_code'] === 201) {
            return ['success' => true, 'message' => $provider . ' API key is valid.'];
        }
        if ($resp['http_code'] === 429) {
            return ['success' => true, 'message' => $provider . ' key valid (rate limited).'];
        }

        $data = json_decode($resp['body'], true);
        $errMsg = $data['error']['message'] ?? $data['message'] ?? ('HTTP ' . $resp['http_code']);
        return ['success' => false, 'message' => $provider . ' key invalid: ' . $errMsg];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Test failed: ' . $e->getMessage()];
    }
}

// ========================================================================
// AI 설정 ON/OFF 체크
// ========================================================================

/**
 * AI 설정 값 조회 (editor_settings 테이블)
 */
function aiGetSetting(mysqli $conn, string $key, $default = null)
{
    $key = mysqli_real_escape_string($conn, $key);
    $sql = "SELECT setting_value FROM editor_settings WHERE setting_key = '{$key}' LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    return $default;
}

/**
 * AI 기능 활성화 여부
 */
function aiIsEnabled(mysqli $conn, string $feature = 'ai_check_enabled'): bool
{
    $val = aiGetSetting($conn, $feature, '0');
    return $val === '1' || $val === 'true';
}
