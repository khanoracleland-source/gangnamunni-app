<?php
/**
 * FCM 푸시 알림 전송 모듈
 * Firebase Cloud Messaging (Legacy + HTTP v1 API 지원)
 *
 * - HTTP v1 API: 서비스 계정 JSON 파일 사용 (우선)
 * - Legacy API: FCM 서버키 사용 (gangnamunni_theme_settings.fcm_server_key)
 *
 * DB 테이블: gangnamunni_admin_fcm_tokens
 * 설정 테이블: gangnamunni_theme_settings (fcm_server_key, fcm_project_id, fcm_enabled)
 *
 * @created 2026-02-26
 * @updated 2026-02-26 is_active 컬럼 + cleanup + 로깅 강화
 */

// 서비스 계정 JSON 파일 경로 (HTTP v1 API)
if (!defined('FCM_SERVICE_ACCOUNT_PATH')) {
    $configuredPath = getenv('FCM_SERVICE_ACCOUNT_PATH');
    define(
        'FCM_SERVICE_ACCOUNT_PATH',
        $configuredPath ?: dirname(__DIR__, 3) . '/hospital-api/storage/app/firebase/service-account.json'
    );
}

// FCM 설정 키 (gangnamunni_theme_settings 테이블)
if (!defined('FCM_SERVER_KEY_SETTING')) {
    define('FCM_SERVER_KEY_SETTING', 'fcm_server_key');
}
if (!defined('FCM_PROJECT_ID_SETTING')) {
    define('FCM_PROJECT_ID_SETTING', 'fcm_project_id');
}
if (!defined('FCM_ENABLED_SETTING')) {
    define('FCM_ENABLED_SETTING', 'fcm_enabled');
}
if (!defined('SEOULBEAUTY_ANDROID_BROADCAST_TOPIC')) {
    define('SEOULBEAUTY_ANDROID_BROADCAST_TOPIC', 'seoulbeauty_android_all');
}

/**
 * FCM 활성화 여부 확인
 */
function is_fcm_enabled($connect) {
    static $cached = null;
    if ($cached !== null) return $cached;

    $stmt = mysqli_prepare($connect, "SELECT setting_value FROM gangnamunni_theme_settings WHERE setting_key = ?");
    if (!$stmt) return true; // 설정 없으면 기본 활성화
    $key = FCM_ENABLED_SETTING;
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $cached = ($row === null) ? true : ($row['setting_value'] === '1');
    return $cached;
}

/**
 * FCM 서버키 가져오기 (Legacy API)
 */
function get_fcm_server_key($connect) {
    static $cached_key = null;
    if ($cached_key !== null) return $cached_key;

    $stmt = mysqli_prepare($connect, "SELECT setting_value FROM gangnamunni_theme_settings WHERE setting_key = ?");
    if (!$stmt) return null;
    $key = FCM_SERVER_KEY_SETTING;
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $cached_key = $row['setting_value'] ?? null;
    return $cached_key;
}

/**
 * FCM 프로젝트 ID 가져오기
 */
function get_fcm_project_id($connect) {
    static $cached_id = null;
    if ($cached_id !== null) return $cached_id;

    // 서비스 계정 파일에서 먼저 시도
    if (file_exists(FCM_SERVICE_ACCOUNT_PATH)) {
        $sa = json_decode(file_get_contents(FCM_SERVICE_ACCOUNT_PATH), true);
        if (!empty($sa['project_id'])) {
            $cached_id = $sa['project_id'];
            return $cached_id;
        }
    }

    // DB 설정에서 가져오기
    $stmt = mysqli_prepare($connect, "SELECT setting_value FROM gangnamunni_theme_settings WHERE setting_key = ?");
    if (!$stmt) return null;
    $key = FCM_PROJECT_ID_SETTING;
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $cached_id = $row['setting_value'] ?? null;
    return $cached_id;
}

/**
 * Google OAuth2 액세스 토큰 생성 (서비스 계정 JWT 사용)
 * HTTP v1 API에 필요
 */
function get_google_access_token() {
    if (!file_exists(FCM_SERVICE_ACCOUNT_PATH)) {
        return null;
    }

    $sa = json_decode(file_get_contents(FCM_SERVICE_ACCOUNT_PATH), true);
    if (empty($sa['private_key']) || empty($sa['client_email'])) {
        return null;
    }

    // JWT 생성
    $now = time();
    $header = base64url_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode([
        'iss' => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ]));

    $signing_input = $header . '.' . $payload;
    $signature = '';
    $key = openssl_pkey_get_private($sa['private_key']);
    if (!$key) {
        error_log('[FCM] 서비스 계정 개인 키 파싱 실패');
        return null;
    }
    openssl_sign($signing_input, $signature, $key, OPENSSL_ALGO_SHA256);
    $jwt = $signing_input . '.' . base64url_encode($signature);

    // 토큰 교환
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('[FCM] OAuth2 토큰 교환 실패: HTTP ' . $httpCode . ' - ' . $response);
        return null;
    }

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

/**
 * Base64url 인코딩 (JWT 용)
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * FCM HTTP v1 API로 전송
 */
function send_fcm_v1($project_id, $access_token, $token, $title, $body, $data = []) {
    return send_fcm_v1_target(
        $project_id,
        $access_token,
        'token',
        $token,
        $title,
        $body,
        $data
    );
}

/**
 * FCM HTTP v1 API로 topic에 전송
 */
function send_fcm_v1_topic($project_id, $access_token, $topic, $title, $body, $data = []) {
    return send_fcm_v1_target(
        $project_id,
        $access_token,
        'topic',
        $topic,
        $title,
        $body,
        $data
    );
}

/**
 * FCM HTTP v1 API common token/topic sender.
 */
function send_fcm_v1_target(
    $project_id,
    $access_token,
    $target_type,
    $target,
    $title,
    $body,
    $data = []
) {
    if (!in_array($target_type, ['token', 'topic'], true) || trim((string)$target) === '') {
        return ['success' => false, 'http_code' => 0, 'response' => ['error' => 'Invalid FCM target']];
    }

    $url = 'https://fcm.googleapis.com/v1/projects/' . $project_id . '/messages:send';

    $message = [
        'message' => [
            $target_type => $target,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                    'channel_id' => 'notification',
                ],
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'badge' => 1,
                    ],
                ],
            ],
        ],
    ];

    if (!empty($data)) {
        // FCM data 필드는 모든 값이 문자열이어야 함
        $string_data = [];
        foreach ($data as $k => $v) {
            $string_data[$k] = is_string($v) ? $v : json_encode($v);
        }
        $string_data['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';
        $string_data['source'] = 'gangnamunni_admin';
        $message['message']['data'] = $string_data;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'success' => $httpCode === 200,
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
    ];
}

/**
 * Send one broadcast request to all Android installations subscribed to topic.
 */
function send_push_to_android_broadcast($connect, $title, $body, $link = null) {
    if (!is_fcm_enabled($connect)) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'reason' => 'FCM disabled'];
    }

    $accessToken = get_google_access_token();
    $projectId = get_fcm_project_id($connect);
    if (!$accessToken || !$projectId) {
        return ['sent' => 0, 'failed' => 1, 'error' => 'Firebase credentials unavailable'];
    }

    $data = ['type' => 'broadcast_notification'];
    if ($link) {
        $data['url'] = $link;
        $data['webview_url'] = $link;
    }

    $result = send_fcm_v1_topic(
        $projectId,
        $accessToken,
        SEOULBEAUTY_ANDROID_BROADCAST_TOPIC,
        $title,
        $body,
        $data
    );

    return [
        'sent' => $result['success'] ? 1 : 0,
        'failed' => $result['success'] ? 0 : 1,
        'api' => 'v1',
        'mode' => 'topic',
        'topic' => SEOULBEAUTY_ANDROID_BROADCAST_TOPIC,
        'response' => $result['response'] ?? null,
    ];
}

/**
 * FCM Legacy API로 전송
 */
function send_fcm_legacy($server_key, $token, $title, $body, $data = []) {
    $url = 'https://fcm.googleapis.com/fcm/send';

    $payload = [
        'to' => $token,
        'notification' => [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => '1',
        ],
        'priority' => 'high',
    ];

    if (!empty($data)) {
        $string_data = [];
        foreach ($data as $k => $v) {
            $string_data[$k] = is_string($v) ? $v : json_encode($v);
        }
        $string_data['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';
        $string_data['source'] = 'gangnamunni_admin';
        $payload['data'] = $string_data;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: key=' . $server_key,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'success' => $httpCode === 200,
        'http_code' => $httpCode,
        'response' => json_decode($response, true),
    ];
}

/**
 * 특정 관리자에게 푸시 알림 전송
 *
 * @param mysqli $connect DB 연결
 * @param int|null $admin_id 관리자 ID (null=전체 활성 토큰)
 * @param string $title 알림 제목
 * @param string $body 알림 본문
 * @param string|null $link 클릭 시 이동할 URL
 * @param string|null $icon 아이콘 (Bootstrap Icons 클래스명)
 * @return array 전송 결과 ['sent'=>int, 'failed'=>int, ...]
 */
function send_push_notification($connect, $admin_id, $title, $body, $link = null, $icon = null) {
    // FCM 비활성화 상태면 바로 리턴
    if (!is_fcm_enabled($connect)) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'reason' => 'FCM disabled'];
    }

    // 해당 관리자(또는 전체)의 활성 FCM 토큰 조회
    if ($admin_id !== null) {
        $stmt = mysqli_prepare($connect, "SELECT fcm_token, platform, device_info FROM gangnamunni_admin_fcm_tokens WHERE admin_id = ? AND is_active = 1");
        if (!$stmt) {
            return ['error' => 'DB prepare 실패', 'sent' => 0];
        }
        mysqli_stmt_bind_param($stmt, 'i', $admin_id);
    } else {
        $stmt = mysqli_prepare($connect, "SELECT fcm_token, platform, device_info FROM gangnamunni_admin_fcm_tokens WHERE is_active = 1");
        if (!$stmt) {
            return ['error' => 'DB prepare 실패', 'sent' => 0];
        }
    }
    mysqli_stmt_execute($stmt);
    $token_result = mysqli_stmt_get_result($stmt);

    $tokens = [];
    while ($row = mysqli_fetch_assoc($token_result)) {
        $tokens[] = $row;
    }
    mysqli_stmt_close($stmt);

    if (empty($tokens)) {
        return ['sent' => 0, 'failed' => 0, 'admin_id' => $admin_id, 'reason' => '등록된 활성 FCM 토큰 없음'];
    }

    // 알림 데이터 페이로드
    $data = [
        'type' => 'admin_notification',
        'admin_id' => (string)($admin_id ?? 0),
    ];
    if ($link) {
        $data['webview_url'] = $link;
        $data['url'] = $link;
    }
    if ($icon) {
        $data['icon'] = $icon;
    }

    // API 모드 결정: HTTP v1 API 우선, Legacy API 폴백
    $use_v1 = false;
    $access_token = null;
    $project_id = null;

    if (file_exists(FCM_SERVICE_ACCOUNT_PATH)) {
        $access_token = get_google_access_token();
        $project_id = get_fcm_project_id($connect);
        if ($access_token && $project_id) {
            $use_v1 = true;
        }
    }

    // Legacy API 폴백
    $server_key = null;
    if (!$use_v1) {
        $server_key = get_fcm_server_key($connect);
        if (!$server_key) {
            return ['error' => 'FCM 서버키 미설정 (gangnamunni_theme_settings.fcm_server_key)', 'sent' => 0];
        }
    }

    $sent = 0;
    $failed = 0;
    $failed_tokens = [];

    foreach ($tokens as $t) {
        $token = $t['fcm_token'];

        if ($use_v1) {
            $result = send_fcm_v1($project_id, $access_token, $token, $title, $body, $data);
        } else {
            $result = send_fcm_legacy($server_key, $token, $title, $body, $data);
        }

        if ($result['success']) {
            $sent++;
        } else {
            $failed++;
            $failed_tokens[] = $token;

            // 유효하지 않은 토큰 비활성화/삭제
            $http_code = $result['http_code'] ?? 0;
            $resp = $result['response'] ?? [];

            // v1 API 에러코드
            $v1_error = $resp['error']['details'][0]['errorCode'] ?? '';
            // Legacy API 에러코드
            $legacy_error = $resp['results'][0]['error'] ?? '';
            $error_code = $v1_error ?: $legacy_error;

            if ($http_code === 404 || in_array($error_code, [
                'UNREGISTERED', 'InvalidRegistration', 'NotRegistered', 'MismatchSenderId'
            ])) {
                deactivate_fcm_token($connect, $token);
                error_log("[FCM] 만료된 토큰 비활성화: " . substr($token, 0, 20) . "... (error: $error_code)");
            }
        }
    }

    return [
        'sent' => $sent,
        'failed' => $failed,
        'failed_tokens' => $failed_tokens,
        'api' => $use_v1 ? 'v1' : 'legacy',
        'admin_id' => $admin_id,
    ];
}

/**
 * Send a push notification to Gnuboard members registered in g5_push_tokens.
 *
 * Passing null as $memberIds sends to every active token.
 */
function send_push_to_members($connect, $memberIds, $title, $body, $link = null) {
    if (!is_fcm_enabled($connect)) {
        return ['sent' => 0, 'failed' => 0, 'skipped' => true, 'reason' => 'FCM disabled'];
    }

    $sql = "SELECT DISTINCT pt_token, mb_id
            FROM g5_push_tokens
            WHERE pt_use = 1 AND pt_token <> ''";

    $memberIds = is_array($memberIds)
        ? array_values(array_filter(array_map('strval', $memberIds), 'strlen'))
        : null;

    if (is_array($memberIds)) {
        if (empty($memberIds)) {
            return ['sent' => 0, 'failed' => 0, 'reason' => 'No recipients'];
        }

        $escapedIds = array_map(
            static fn ($memberId) => "'" . mysqli_real_escape_string($connect, $memberId) . "'",
            $memberIds
        );
        $sql .= ' AND mb_id IN (' . implode(',', $escapedIds) . ')';
    }

    $tokenResult = mysqli_query($connect, $sql);
    if (!$tokenResult) {
        return ['sent' => 0, 'failed' => 0, 'error' => 'Token query failed'];
    }

    $accessToken = get_google_access_token();
    $projectId = get_fcm_project_id($connect);
    if (!$accessToken || !$projectId) {
        return ['sent' => 0, 'failed' => 0, 'error' => 'Firebase credentials unavailable'];
    }

    $sent = 0;
    $failed = 0;
    $data = ['type' => 'member_notification'];
    if ($link) {
        $data['url'] = $link;
        $data['webview_url'] = $link;
    }

    while ($row = mysqli_fetch_assoc($tokenResult)) {
        $result = send_fcm_v1(
            $projectId,
            $accessToken,
            $row['pt_token'],
            $title,
            $body,
            $data
        );

        if ($result['success']) {
            $sent++;
            continue;
        }

        $failed++;
        $httpCode = $result['http_code'] ?? 0;
        $response = $result['response'] ?? [];
        $errorCode = $response['error']['details'][0]['errorCode'] ?? '';

        if ($httpCode === 404 || $errorCode === 'UNREGISTERED') {
            $stmt = mysqli_prepare($connect, 'UPDATE g5_push_tokens SET pt_use = 0 WHERE pt_token = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $row['pt_token']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }

    return [
        'sent' => $sent,
        'failed' => $failed,
        'api' => 'v1',
        'recipient_filter' => $memberIds,
    ];
}

/**
 * 모든 관리자에게 푸시 알림 전송 (알림 타입별 필터링 포함)
 *
 * @param mysqli $connect DB 연결
 * @param string $title 알림 제목
 * @param string $body 알림 본문
 * @param string|null $link 클릭 시 이동 URL
 * @param string $type 알림 타입 (notification type)
 * @return array 전송 결과 요약
 */
function send_push_to_all_admins($connect, $title, $body, $link = null, $type = '') {
    // admin_notifications.php의 should_receive_notification 사용
    if (!function_exists('should_receive_notification')) {
        require_once __DIR__ . '/admin_notifications.php';
    }

    // 활성 관리자 목록 조회
    $admins = mysqli_query($connect, "SELECT id, permissions FROM gangnamunni_admins WHERE is_active = 1");
    if (!$admins) {
        return ['error' => '관리자 조회 실패', 'total_sent' => 0];
    }

    $total_sent = 0;
    $total_failed = 0;
    $results = [];

    while ($a = mysqli_fetch_assoc($admins)) {
        // 알림 타입 필터링 (type이 있으면 수신 여부 체크)
        if ($type && !should_receive_notification($a, $type)) {
            continue;
        }

        $result = send_push_notification($connect, $a['id'], $title, $body, $link);
        $total_sent += $result['sent'] ?? 0;
        $total_failed += $result['failed'] ?? 0;
        $results[] = $result;
    }

    return [
        'total_sent' => $total_sent,
        'total_failed' => $total_failed,
        'details' => $results,
    ];
}

/**
 * FCM 토큰 등록/업데이트 (UPSERT)
 *
 * @param mysqli $connect DB 연결
 * @param int $admin_id 관리자 ID
 * @param string $token FCM 토큰
 * @param string $platform 플랫폼 (android/ios/web)
 * @param string $device_info 기기 정보
 * @return array 결과
 */
function register_fcm_token($connect, $admin_id, $token, $platform = 'android', $device_info = '') {
    if (empty($token) || !$admin_id) {
        return ['success' => false, 'error' => '필수 파라미터 누락'];
    }

    // 플랫폼 검증
    if (!in_array($platform, ['android', 'ios', 'web'])) {
        $platform = 'android';
    }

    // UPSERT: 토큰이 이미 있으면 업데이트, 없으면 삽입
    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_admin_fcm_tokens (admin_id, fcm_token, platform, device_info, is_active)
         VALUES (?, ?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE admin_id = VALUES(admin_id), platform = VALUES(platform),
                                  device_info = VALUES(device_info), is_active = 1, updated_at = NOW()"
    );
    if (!$stmt) {
        return ['success' => false, 'error' => 'DB prepare 실패: ' . mysqli_error($connect)];
    }
    mysqli_stmt_bind_param($stmt, 'isss', $admin_id, $token, $platform, $device_info);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 관리자당 최대 5개 토큰 유지
    if ($ok) {
        cleanup_old_tokens($connect, $admin_id);
    }

    return ['success' => $ok, 'admin_id' => $admin_id];
}

/**
 * FCM 토큰 비활성화 (is_active = 0)
 */
function deactivate_fcm_token($connect, $token) {
    $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_admin_fcm_tokens SET is_active = 0 WHERE fcm_token = ?");
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, 's', $token);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * FCM 토큰 해제 (DELETE)
 */
function unregister_fcm_token($connect, $token) {
    if (empty($token)) {
        return ['success' => false, 'error' => '토큰 누락'];
    }

    $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_admin_fcm_tokens WHERE fcm_token = ?");
    if (!$stmt) {
        return ['success' => false, 'error' => 'DB prepare 실패'];
    }
    mysqli_stmt_bind_param($stmt, 's', $token);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    return ['success' => $ok, 'deleted' => $affected];
}

/**
 * 관리자당 오래된 토큰 정리 (최대 max개 유지)
 * 가장 최근 업데이트된 max개만 남기고 나머지 삭제
 */
function cleanup_old_tokens($connect, $admin_id, $max = 5) {
    // 활성 토큰 수 확인
    $stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_admin_fcm_tokens WHERE admin_id = ? AND is_active = 1");
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, 'i', $admin_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $count = intval($row['cnt'] ?? 0);
    if ($count <= $max) return;

    // 최신 max개의 ID를 구해서 나머지 삭제
    $stmt = mysqli_prepare($connect,
        "SELECT id FROM gangnamunni_admin_fcm_tokens WHERE admin_id = ? AND is_active = 1 ORDER BY updated_at DESC LIMIT ?"
    );
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, 'ii', $admin_id, $max);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $keep_ids = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $keep_ids[] = intval($row['id']);
    }
    mysqli_stmt_close($stmt);

    if (!empty($keep_ids)) {
        $keep_list = implode(',', $keep_ids);
        mysqli_query($connect,
            "DELETE FROM gangnamunni_admin_fcm_tokens WHERE admin_id = $admin_id AND is_active = 1 AND id NOT IN ($keep_list)"
        );
    }
}

/**
 * 관리자의 모든 FCM 토큰 조회
 */
function get_admin_fcm_tokens($connect, $admin_id) {
    $stmt = mysqli_prepare($connect,
        "SELECT id, fcm_token, platform, device_info, is_active, created_at, updated_at
         FROM gangnamunni_admin_fcm_tokens WHERE admin_id = ? ORDER BY updated_at DESC"
    );
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, 'i', $admin_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $tokens = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $tokens[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $tokens;
}

/**
 * FCM 통계 조회 (관리 대시보드용)
 */
function get_fcm_stats($connect) {
    $stats = [];

    // 전체 토큰 수
    $result = mysqli_query($connect, "SELECT COUNT(*) as total, SUM(is_active = 1) as active, SUM(is_active = 0) as inactive FROM gangnamunni_admin_fcm_tokens");
    $row = mysqli_fetch_assoc($result);
    $stats['tokens'] = [
        'total' => intval($row['total'] ?? 0),
        'active' => intval($row['active'] ?? 0),
        'inactive' => intval($row['inactive'] ?? 0),
    ];

    // 플랫폼별
    $result = mysqli_query($connect, "SELECT platform, COUNT(*) as cnt FROM gangnamunni_admin_fcm_tokens WHERE is_active = 1 GROUP BY platform");
    $platforms = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $platforms[$row['platform']] = intval($row['cnt']);
    }
    $stats['platforms'] = $platforms;

    // 등록된 관리자 수
    $result = mysqli_query($connect, "SELECT COUNT(DISTINCT admin_id) as cnt FROM gangnamunni_admin_fcm_tokens WHERE is_active = 1");
    $row = mysqli_fetch_assoc($result);
    $stats['admins_with_tokens'] = intval($row['cnt'] ?? 0);

    return $stats;
}
