<?php
/**
 * Auto-test Node API용 단기 서명 관리자 세션 쿠키.
 * 비밀 키는 웹 루트 내부의 보호된 data/session 디렉터리에 저장한다.
 */

function auto_test_base64url_encode($value) {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function auto_test_is_enabled() {
    return strtolower(trim((string)getenv('AUTO_TEST_ENABLED'))) === 'true';
}

function require_auto_test_enabled() {
    if (auto_test_is_enabled()) return;

    http_response_code(503);
    header('Retry-After: 3600');
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html lang="ko"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>자동 테스트 일시 중지</title>';
    echo '<style>body{font-family:sans-serif;background:#f5f7fa;margin:0;padding:80px 20px;text-align:center;color:#333}';
    echo '.box{max-width:560px;margin:auto;background:#fff;padding:36px;border-radius:16px;box-shadow:0 8px 28px rgba(0,0,0,.08)}';
    echo 'h2{margin:0 0 14px;color:#b45309}p{line-height:1.7;color:#666}a{color:#4f46e5}</style></head><body>';
    echo '<div class="box"><h2>자동 테스트 기능이 일시 중지되었습니다</h2>';
    echo '<p>관리자 자동 테스트 v1/v2 및 관련 실행 API는 현재 사용할 수 없습니다.</p>';
    echo '<a href="dashboard.php">대시보드로 돌아가기</a></div></body></html>';
    exit;
}

function auto_test_api_secret() {
    $env_secret = trim((string)getenv('AUTO_TEST_API_SECRET'));
    if (strlen($env_secret) >= 32) return $env_secret;

    $secret_dir = dirname(__DIR__, 2) . '/data/session';
    $secret_path = $secret_dir . '/auto-test-api-secret';
    if (!is_dir($secret_dir) && !@mkdir($secret_dir, 0700, true) && !is_dir($secret_dir)) {
        throw new RuntimeException('Auto-test 비밀 키 디렉터리를 생성할 수 없습니다.');
    }

    if (!is_file($secret_path)) {
        $handle = @fopen($secret_path, 'x');
        if ($handle) {
            $secret = bin2hex(random_bytes(48));
            if (fwrite($handle, $secret) !== strlen($secret)) {
                fclose($handle);
                @unlink($secret_path);
                throw new RuntimeException('Auto-test 비밀 키 파일을 기록할 수 없습니다.');
            }
            fclose($handle);
            @chmod($secret_path, 0600);
        }
    }

    $secret = @file_get_contents($secret_path);
    if ($secret === false || strlen(trim($secret)) < 32) {
        throw new RuntimeException('Auto-test API 비밀 키가 없거나 유효하지 않습니다.');
    }
    return trim($secret);
}

function issue_auto_test_api_session($scope, $csrf_token) {
    $settings = [
        'v1' => [
            'cookie' => 'hms_auto_test_v1',
            'path' => '/auto-manual-api/api/auto-test',
        ],
        'v2' => [
            'cookie' => 'hms_auto_test_v2',
            'path' => '/auto-test-v2-api/api/v2',
        ],
    ];
    if (!isset($settings[$scope])) throw new InvalidArgumentException('알 수 없는 auto-test 범위입니다.');

    $now = time();
    $admin = get_current_admin();
    $payload = auto_test_base64url_encode(json_encode([
        'sub' => (int)($admin['id'] ?? 0),
        'role' => (string)($admin['role'] ?? ''),
        'scope' => $scope,
        'csrf' => hash('sha256', (string)$csrf_token),
        'iat' => $now,
        'exp' => $now + 1800,
        'nonce' => bin2hex(random_bytes(12)),
    ], JSON_UNESCAPED_SLASHES));
    $signature = auto_test_base64url_encode(hash_hmac('sha256', $payload, auto_test_api_secret(), true));
    $token = $payload . '.' . $signature;

    $is_https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    setcookie($settings[$scope]['cookie'], $token, [
        'expires' => $now + 1800,
        'path' => $settings[$scope]['path'],
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
