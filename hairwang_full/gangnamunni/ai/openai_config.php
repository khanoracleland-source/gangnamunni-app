<?php
/**
 * AI 멀티 프로바이더 설정
 *
 * 지원 프로바이더:
 *   - openai: GPT-4o + DALL-E 3
 *   - zhipu:  GLM-4 + CogView-3 (智谱AI)
 *   - deepseek: DeepSeek-Chat (텍스트 전용)
 *   - openrouter: 100+ 모델 (OpenRouter.ai)
 *   - ollama: 로컬 모델 (무료)
 *   - custom: 사용자 지정 OpenAI-호환 API
 */

// ──────────────────────────────────────────
// DB 기반 설정 로드 (admin_ai_settings.php 에서 저장)
// ──────────────────────────────────────────
function loadAiSettings($connect) {
    $settings = [];
    $r = mysqli_query($connect, "SELECT setting_key, setting_value FROM editor_settings WHERE setting_key LIKE 'ai_%'");
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings;
}

// DB 연결이 있으면 DB 설정 사용, 없으면 기본값
$_ai_settings = [];
if (isset($connect) && $connect) {
    $_ai_settings = loadAiSettings($connect);
}

// ──────────────────────────────────────────
// 프로바이더 설정
// ──────────────────────────────────────────
// 활성 텍스트 프로바이더
if (!defined('AI_TEXT_PROVIDER'))
    define('AI_TEXT_PROVIDER', $_ai_settings['ai_text_provider'] ?? 'openai');

// 활성 이미지 프로바이더
if (!defined('AI_IMAGE_PROVIDER'))
    define('AI_IMAGE_PROVIDER', $_ai_settings['ai_image_provider'] ?? 'openai');

// 테스트 모드
// [개선 2026-06-19] 유효한 키가 하나라도 있으면 TEST_MODE를 강제 OFF(실동작).
//   - 기존: ai_test_mode 기본 '1' → 키가 있어도 무조건 가짜 데이터 반환 (MVP/데모 금지 원칙 위반)
//   - 변경: ① 관리자가 ai_test_mode='1' 명시 AND ② editor_settings에 유효 텍스트 키 없음 → 그때만 TEST
//   - 유효 키 판정은 활성 텍스트 프로바이더의 키가 비어있지 않고 placeholder('sk-proj-...')가 아닌지로 함
if (!defined('AI_TEST_MODE')) {
    $_ai_active_prov = $_ai_settings['ai_text_provider'] ?? 'openai';
    $_ai_prov_keymap = [
        'openai' => 'ai_openai_key', 'zhipu' => 'ai_zhipu_key', 'deepseek' => 'ai_deepseek_key',
        'openrouter' => 'ai_openrouter_key', 'custom' => 'ai_custom_key', 'ollama' => 'ai_ollama_base',
    ];
    $_ai_keyname = $_ai_prov_keymap[$_ai_active_prov] ?? 'ai_openai_key';
    $_ai_keyval = trim((string)($_ai_settings[$_ai_keyname] ?? ''));
    // placeholder / 빈값 / 잘린 키(8자 이하)면 '키 없음'으로 간주
    $_ai_has_valid_key = ($_ai_keyval !== '' && $_ai_keyval !== 'sk-proj-...' && strlen($_ai_keyval) > 8);
    $_ai_test_setting = ($_ai_settings['ai_test_mode'] ?? '1') === '1';
    // 유효 키가 있으면 설정과 무관하게 실동작. 키 없으면 설정값 따름(기본 테스트).
    define('AI_TEST_MODE', $_ai_has_valid_key ? false : $_ai_test_setting);
}

// ──────────────────────────────────────────
// 프로바이더별 API 설정
// ──────────────────────────────────────────
$AI_PROVIDERS = [
    'openai' => [
        'name' => 'OpenAI',
        'api_base' => 'https://api.openai.com/v1',
        'api_key' => $_ai_settings['ai_openai_key'] ?? 'sk-proj-...',
        'text_model' => $_ai_settings['ai_openai_text_model'] ?? 'gpt-4o',
        'image_model' => 'dall-e-3',
        'supports_image' => true,
        'auth_header' => 'Bearer',
    ],
    'zhipu' => [
        'name' => 'Zhipu GLM (智谱AI)',
        'api_base' => 'https://open.bigmodel.cn/api/paas/v4',
        'api_key' => $_ai_settings['ai_zhipu_key'] ?? '',
        'text_model' => $_ai_settings['ai_zhipu_text_model'] ?? 'glm-4-flash',
        'image_model' => 'cogview-3',
        'supports_image' => true,
        'auth_header' => 'Bearer',
        'models' => ['glm-4-flash', 'glm-4-plus', 'glm-4-0520', 'glm-4-air', 'glm-4-airx', 'glm-4-long'],
    ],
    'deepseek' => [
        'name' => 'DeepSeek',
        'api_base' => 'https://api.deepseek.com/v1',
        'api_key' => $_ai_settings['ai_deepseek_key'] ?? '',
        'text_model' => $_ai_settings['ai_deepseek_text_model'] ?? 'deepseek-chat',
        'image_model' => '',
        'supports_image' => false,
        'auth_header' => 'Bearer',
        'models' => ['deepseek-chat', 'deepseek-reasoner'],
    ],
    'openrouter' => [
        'name' => 'OpenRouter',
        'api_base' => 'https://openrouter.ai/api/v1',
        'api_key' => $_ai_settings['ai_openrouter_key'] ?? '',
        'text_model' => $_ai_settings['ai_openrouter_text_model'] ?? 'anthropic/claude-3.5-sonnet',
        'image_model' => $_ai_settings['ai_openrouter_image_model'] ?? 'black-forest-labs/flux-1.1-pro',
        'supports_image' => true,
        'auth_header' => 'Bearer',
        'extra_headers' => [
            'HTTP-Referer' => 'https://hms.seoulbeauty.app',
            'X-Title' => 'Seoul Beauty Admin',
        ],
    ],
    'ollama' => [
        'name' => 'Ollama (로컬)',
        'api_base' => $_ai_settings['ai_ollama_base'] ?? 'http://localhost:11434/v1',
        'api_key' => 'ollama',
        'text_model' => $_ai_settings['ai_ollama_text_model'] ?? 'llama3',
        'image_model' => '',
        'supports_image' => false,
        'auth_header' => 'Bearer',
    ],
    'custom' => [
        'name' => '사용자 지정',
        'api_base' => $_ai_settings['ai_custom_base'] ?? '',
        'api_key' => $_ai_settings['ai_custom_key'] ?? '',
        'text_model' => $_ai_settings['ai_custom_text_model'] ?? '',
        'image_model' => $_ai_settings['ai_custom_image_model'] ?? '',
        'supports_image' => !empty($_ai_settings['ai_custom_image_model']),
        'auth_header' => $_ai_settings['ai_custom_auth'] ?? 'Bearer',
    ],
];

// 레거시 호환: OPENAI_* 상수
if (!defined('OPENAI_API_KEY'))
    define('OPENAI_API_KEY', $AI_PROVIDERS['openai']['api_key']);
if (!defined('OPENAI_API_BASE'))
    define('OPENAI_API_BASE', $AI_PROVIDERS['openai']['api_base']);
if (!defined('OPENAI_MODEL_TEXT'))
    define('OPENAI_MODEL_TEXT', $AI_PROVIDERS['openai']['text_model']);
if (!defined('OPENAI_MODEL_IMAGE'))
    define('OPENAI_MODEL_IMAGE', 'dall-e-3');
if (!defined('OPENAI_TEST_MODE'))
    define('OPENAI_TEST_MODE', AI_TEST_MODE);

// ──────────────────────────────────────────
// 통합 API 호출 함수
// ──────────────────────────────────────────

/**
 * 텍스트 AI API 호출 (OpenAI-호환 Chat Completions)
 */
function callTextAI($messages, $options = []) {
    global $AI_PROVIDERS;
    $provider = AI_TEXT_PROVIDER;
    $config = $AI_PROVIDERS[$provider] ?? $AI_PROVIDERS['openai'];

    if (empty($config['api_key']) || $config['api_key'] === 'sk-proj-...') {
        return ['error' => "{$config['name']} API 키가 설정되지 않았습니다. AI 설정에서 키를 입력해주세요."];
    }

    $data = [
        'model' => $options['model'] ?? $config['text_model'],
        'messages' => $messages,
        'temperature' => $options['temperature'] ?? 0.7,
        'max_tokens' => $options['max_tokens'] ?? 1000,
    ];

    return callProviderAPI($config, 'chat/completions', $data);
}

/**
 * 이미지 AI API 호출
 */
function callImageAI($prompt, $options = []) {
    global $AI_PROVIDERS;
    $provider = AI_IMAGE_PROVIDER;
    $config = $AI_PROVIDERS[$provider] ?? $AI_PROVIDERS['openai'];

    if (!$config['supports_image']) {
        // 폴백: OpenAI 이미지 사용
        if (!empty($AI_PROVIDERS['openai']['api_key']) && $AI_PROVIDERS['openai']['api_key'] !== 'sk-proj-...') {
            $config = $AI_PROVIDERS['openai'];
        } else {
            return ['error' => "{$config['name']}은(는) 이미지 생성을 지원하지 않습니다."];
        }
    }

    if (empty($config['api_key']) || $config['api_key'] === 'sk-proj-...') {
        return ['error' => "{$config['name']} API 키가 설정되지 않았습니다."];
    }

    // Zhipu CogView 이미지 생성
    if ($provider === 'zhipu') {
        $data = [
            'model' => $config['image_model'],
            'prompt' => $prompt,
        ];
        if (!empty($options['size'])) {
            // CogView는 size 대신 width/height 사용할 수도 있음
            $data['size'] = $options['size'];
        }
        return callProviderAPI($config, 'images/generations', $data);
    }

    // OpenRouter 이미지 (Flux 등)
    if ($provider === 'openrouter') {
        $data = [
            'model' => $config['image_model'],
            'prompt' => $prompt,
            'n' => 1,
        ];
        if (!empty($options['size'])) $data['size'] = $options['size'];
        return callProviderAPI($config, 'images/generations', $data);
    }

    // OpenAI DALL-E
    $data = [
        'model' => $config['image_model'],
        'prompt' => $prompt,
        'n' => 1,
        'size' => $options['size'] ?? '1024x1024',
        'quality' => $options['quality'] ?? 'standard',
        'style' => $options['style'] ?? 'vivid',
    ];

    return callProviderAPI($config, 'images/generations', $data);
}

/**
 * 프로바이더 API 호출 (내부 헬퍼)
 */
function callProviderAPI($config, $endpoint, $data) {
    $url = rtrim($config['api_base'], '/') . '/' . $endpoint;

    $headers = [
        'Content-Type: application/json',
        'Authorization: ' . ($config['auth_header'] ?? 'Bearer') . ' ' . $config['api_key'],
    ];

    // 추가 헤더 (OpenRouter 등)
    if (!empty($config['extra_headers'])) {
        foreach ($config['extra_headers'] as $key => $value) {
            $headers[] = "{$key}: {$value}";
        }
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("AI API cURL Error: {$curl_error}");
        return ['error' => '네트워크 오류: ' . $curl_error];
    }

    if ($http_code !== 200) {
        $err_data = json_decode($response, true);
        $err_msg = $err_data['error']['message'] ?? $response;
        error_log("AI API Error ({$config['name']}): HTTP {$http_code} - {$err_msg}");
        return ['error' => "{$config['name']} API 오류 (HTTP {$http_code}): " . mb_substr($err_msg, 0, 200)];
    }

    return json_decode($response, true);
}

// ──────────────────────────────────────────
// 고수준 함수 (기존 코드 호환)
// ──────────────────────────────────────────

// ──────────────────────────────────────────
// [개선 2026-06-19] 서브시스템 A(ai_proxy.php / gangnamunni_ai_keys) 폴백 헬퍼
//   editor_settings에 키가 없어도 이미 등록된 공용 키(callAI)로 실제 생성한다.
//   callAI 미존재 또는 키 없음 → null 반환(상위에서 안내 메시지 처리).
// ──────────────────────────────────────────
function _aiEnsureProxy() {
    if (function_exists('callAI')) return true;
    $p = __DIR__ . '/../includes/ai_proxy.php';
    if (file_exists($p)) { require_once $p; }
    return function_exists('callAI');
}
function _aiExtractJson($text) {
    $s = strpos($text, '{'); $e = strrpos($text, '}');
    if ($s === false || $e === false || $e < $s) return null;
    return json_decode(substr($text, $s, $e - $s + 1), true);
}
function _aiCopyFallbackViaCallAI($hospital_name, $treatment, $tone, $target_audience) {
    if (!_aiEnsureProxy()) return null;
    $prompt = "병원: {$hospital_name}, 시술: {$treatment}, 톤: {$tone}, 타겟: {$target_audience}\n"
        . "3가지 광고 카피를 JSON으로만 생성:\n"
        . '{"version1":{"title":"15자이내","subtitle":"20자이내","description":"50자이내","cta":"10자이내"},"version2":{...},"version3":{...}}';
    $res = callAI('ad_copy', $prompt, ['max_tokens' => 1000, 'temperature' => 0.8]);
    if (empty($res['success']) || empty($res['content'])) return null;
    $c = _aiExtractJson($res['content']);
    if (!$c || empty($c['version1'])) return null;
    return [
        'title' => $c['version1']['title'] ?? '', 'subtitle' => $c['version1']['subtitle'] ?? '',
        'description' => $c['version1']['description'] ?? '', 'cta' => $c['version1']['cta'] ?? '',
        'variants' => [$c['version2'] ?? [], $c['version3'] ?? []],
        '_via' => 'callAI',
    ];
}
function _aiImageFallbackViaCallAI($prompt, $options = []) {
    // 이미지 생성은 ai_proxy의 image 액션이 지원될 때만. 미지원이면 null → 상위에서 안내.
    if (!_aiEnsureProxy()) return null;
    $res = callAI('image', $prompt, $options + ['type' => 'image']);
    if (empty($res['success'])) return null;
    $url = $res['image_url'] ?? $res['url'] ?? ($res['content'] ?? '');
    if (!$url || strpos($url, 'http') !== 0) return null;
    return ['url' => $url, 'revised_prompt' => $prompt, '_via' => 'callAI'];
}
function _aiABFallbackViaCallAI($original_title, $original_subtitle) {
    if (!_aiEnsureProxy()) return null;
    $prompt = "A/B 테스트 카피: 원본 제목=\"{$original_title}\", 부제목=\"{$original_subtitle}\"\n"
        . 'JSON으로만: {"variant_a":{"title":"...","subtitle":"..."},"variant_b":{"title":"긴급성추가","subtitle":"숫자강조"}}';
    $res = callAI('ab_test', $prompt, ['max_tokens' => 500, 'temperature' => 0.7]);
    if (empty($res['success']) || empty($res['content'])) return null;
    $c = _aiExtractJson($res['content']);
    return ($c && !empty($c['variant_a'])) ? $c : null;
}

/**
 * 광고 카피 생성 (멀티 프로바이더)
 */
function generateAdCopy($hospital_name, $treatment, $options = []) {
    $tone = $options['tone'] ?? 'professional';
    $target_audience = $options['target_audience'] ?? '20-30대 여성';

    // [개선 2026-06-19] TEST_MODE(=editor_settings 키 없음)여도 서브시스템 A(gangnamunni_ai_keys)에
    //   유효 키가 있으면 callAI로 실제 생성. 둘 다 없을 때만 명시적 안내(가짜 데이터 대신).
    if (AI_TEST_MODE) {
        $fb = _aiCopyFallbackViaCallAI($hospital_name, $treatment, $tone, $target_audience);
        if ($fb !== null) return $fb; // callAI 성공 → 실제 결과
        return ['error' => 'AI 키가 설정되지 않았습니다. 관리자 → AI 자동화 설정에서 API 키를 등록하면 실제 생성됩니다.'];
    }

    $messages = [
        ['role' => 'system', 'content' => '당신은 전문 광고 카피라이터입니다. 항상 JSON 형식으로 응답하세요.'],
        ['role' => 'user', 'content' => "
병원: {$hospital_name}, 시술: {$treatment}, 톤: {$tone}, 타겟: {$target_audience}

3가지 광고 카피를 JSON으로 생성:
{\"version1\":{\"title\":\"15자이내\",\"subtitle\":\"20자이내\",\"description\":\"50자이내\",\"cta\":\"10자이내\"},\"version2\":{...},\"version3\":{...}}
"]
    ];

    $response = callTextAI($messages, ['temperature' => 0.8, 'max_tokens' => 1000]);

    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }

    $content = $response['choices'][0]['message']['content'] ?? '';
    $json_start = strpos($content, '{');
    $json_end = strrpos($content, '}');

    if ($json_start !== false && $json_end !== false) {
        $json = substr($content, $json_start, $json_end - $json_start + 1);
        $copies = json_decode($json, true);
        if ($copies) {
            return [
                'title' => $copies['version1']['title'] ?? '',
                'subtitle' => $copies['version1']['subtitle'] ?? '',
                'description' => $copies['version1']['description'] ?? '',
                'cta' => $copies['version1']['cta'] ?? '',
                'variants' => [$copies['version2'] ?? [], $copies['version3'] ?? []]
            ];
        }
    }

    return ['error' => 'AI 응답 파싱 실패'];
}

/**
 * 이미지 생성 (멀티 프로바이더)
 */
function generateImage($prompt, $options = []) {
    // [개선 2026-06-19] TEST_MODE여도 서브시스템 A에 이미지 키(openai/dall-e 등)가 있으면 callAI로 실제 생성.
    //   키가 전혀 없을 때만 placeholder 대신 명시적 안내 반환(가짜 결과 금지).
    if (AI_TEST_MODE) {
        $fb = _aiImageFallbackViaCallAI($prompt, $options);
        if ($fb !== null) return $fb;
        return ['error' => 'AI 이미지 키가 설정되지 않았습니다. 관리자 → AI 자동화 설정에서 이미지 생성용 키(OpenAI 등)를 등록하세요.'];
    }

    $response = callImageAI($prompt, $options);

    if (isset($response['error'])) {
        return ['error' => $response['error']];
    }

    return [
        'url' => $response['data'][0]['url'] ?? '',
        'revised_prompt' => $response['data'][0]['revised_prompt'] ?? $prompt,
    ];
}

// 레거시 호환
function generateAdImage($prompt, $options = []) {
    return generateImage($prompt, $options);
}

/**
 * A/B 테스트 변형 생성
 */
function generateABTestVariants($original_title, $original_subtitle) {
    // [개선 2026-06-19] TEST_MODE여도 callAI 폴백으로 실제 변형 생성 시도.
    if (AI_TEST_MODE) {
        $fb = _aiABFallbackViaCallAI($original_title, $original_subtitle);
        if ($fb !== null) return $fb;
        // callAI도 불가 → 최소한의 규칙 기반 변형(가짜 아님, 결정적 변형)
        return [
            'variant_a' => ['title' => $original_title, 'subtitle' => $original_subtitle],
            'variant_b' => ['title' => $original_title . ' 특별 혜택', 'subtitle' => $original_subtitle . ' (선착순 100명)'],
        ];
    }

    $messages = [
        ['role' => 'system', 'content' => 'JSON으로만 응답하세요.'],
        ['role' => 'user', 'content' => "A/B 테스트 카피: 원본 제목=\"{$original_title}\", 부제목=\"{$original_subtitle}\"\n\nJSON: {\"variant_a\":{\"title\":\"...\",\"subtitle\":\"...\"},\"variant_b\":{\"title\":\"긴급성추가\",\"subtitle\":\"숫자강조\"}}"]
    ];

    $response = callTextAI($messages, ['temperature' => 0.7, 'max_tokens' => 500]);
    if (isset($response['error'])) return false;

    $content = $response['choices'][0]['message']['content'] ?? '';
    $json_start = strpos($content, '{');
    $json_end = strrpos($content, '}');
    if ($json_start !== false && $json_end !== false) {
        return json_decode(substr($content, $json_start, $json_end - $json_start + 1), true);
    }
    return false;
}

/**
 * 이미지 URL을 서버에 저장
 */
function saveImageLocally($image_url, $filename) {
    $upload_dir = __DIR__ . '/../uploads/ai_generated/';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);

    $local_path = $upload_dir . $filename;
    $ch = curl_init($image_url);
    $fp = fopen($local_path, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($http_code !== 200) { @unlink($local_path); return false; }
    return 'uploads/ai_generated/' . $filename;
}

/**
 * 현재 활성 프로바이더 정보 반환 (관리 화면용)
 */
function getActiveProviders() {
    global $AI_PROVIDERS;
    return [
        'text' => [
            'provider' => AI_TEXT_PROVIDER,
            'name' => $AI_PROVIDERS[AI_TEXT_PROVIDER]['name'] ?? 'Unknown',
            'model' => $AI_PROVIDERS[AI_TEXT_PROVIDER]['text_model'] ?? '',
            'has_key' => !empty($AI_PROVIDERS[AI_TEXT_PROVIDER]['api_key']) && $AI_PROVIDERS[AI_TEXT_PROVIDER]['api_key'] !== 'sk-proj-...',
        ],
        'image' => [
            'provider' => AI_IMAGE_PROVIDER,
            'name' => $AI_PROVIDERS[AI_IMAGE_PROVIDER]['name'] ?? 'Unknown',
            'model' => $AI_PROVIDERS[AI_IMAGE_PROVIDER]['image_model'] ?? '',
            'has_key' => !empty($AI_PROVIDERS[AI_IMAGE_PROVIDER]['api_key']) && $AI_PROVIDERS[AI_IMAGE_PROVIDER]['api_key'] !== 'sk-proj-...',
            'supports_image' => $AI_PROVIDERS[AI_IMAGE_PROVIDER]['supports_image'] ?? false,
        ],
        'test_mode' => AI_TEST_MODE,
    ];
}
