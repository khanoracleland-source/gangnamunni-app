<?php
/**
 * ========================================
 * Firebase Cloud Messaging (FCM) HTTP v1 API
 * 푸시 알림 전송 라이브러리
 * ========================================
 *
 * @version 3.0.0
 * @updated 2026-01-29
 * @author Claude Sonnet 4.5
 *
 * @changes
 *   - HTTP v1 API 완전 구현
 *   - send_fcm_push() 함수 구현 완료
 *   - Google OAuth 2.0 Access Token 자동 발급
 *   - 에러 처리 강화
 */

// config.php 로드
require_once __DIR__ . '/config.php';

/**
 * ========================================
 * FCM HTTP v1 Access Token 발급
 * ========================================
 *
 * @return string|false Access Token 또는 실패 시 false
 */
function get_fcm_access_token() {
    global $firebase_json_path;

    try {
        // Firebase JSON 파일 존재 확인
        if (!file_exists($firebase_json_path)) {
            throw new Exception("Firebase JSON file not found: {$firebase_json_path}");
        }

        // JSON 파일 읽기
        $json_content = @file_get_contents($firebase_json_path);
        if ($json_content === false) {
            throw new Exception("Failed to read Firebase JSON file");
        }

        $service_account = json_decode($json_content, true);

        if (!$service_account || !isset($service_account['private_key'])) {
            throw new Exception("Invalid Firebase JSON file structure");
        }
    } catch (Exception $e) {
        error_log("FCM Error: " . $e->getMessage());
        return false;
    }

    // JWT 생성을 위한 헤더
    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT'
    ];

    // JWT Payload
    $now = time();
    $payload = [
        'iss' => $service_account['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600 // 1시간 유효
    ];

    // Base64 URL Encode
    $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($header)));
    $base64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));

    // 서명할 데이터
    $signature_input = $base64_header . '.' . $base64_payload;

    // RSA 서명 생성
    $private_key = openssl_pkey_get_private($service_account['private_key']);
    openssl_sign($signature_input, $signature, $private_key, OPENSSL_ALGO_SHA256);
    openssl_free_key($private_key);

    $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    // JWT 토큰 완성
    $jwt = $signature_input . '.' . $base64_signature;

    // Google OAuth 2.0 엔드포인트에 JWT 전송
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("FCM Error: Failed to get access token. HTTP Code: {$http_code}, Response: {$response}");
        return false;
    }

    $response_data = json_decode($response, true);

    if (!isset($response_data['access_token'])) {
        error_log("FCM Error: Access token not found in response");
        return false;
    }

    return $response_data['access_token'];
}

/**
 * ========================================
 * FCM 푸시 알림 전송 (HTTP v1 API)
 * ========================================
 *
 * @param string $token FCM 토큰 (개별 전송) 또는 null (토픽 전송)
 * @param string $title 알림 제목
 * @param string $body 알림 내용
 * @param string $url 클릭 시 이동할 URL (선택)
 * @param string $image_url 이미지 URL (선택)
 * @param string $topic 토픽 이름 (토픽 전송 시)
 * @param array $data 추가 데이터 (선택)
 * @return array 전송 결과 ['success' => bool, 'message' => string, 'response' => array]
 */
function send_fcm_push($token = null, $title = '', $body = '', $url = '', $image_url = '', $topic = null, $data = []) {
    global $firebase_json_path;

    try {
        // Access Token 발급
        $access_token = get_fcm_access_token();

        if (!$access_token) {
            throw new Exception('Failed to get FCM access token');
        }

        // Firebase JSON에서 Project ID 가져오기
        $json_content = @file_get_contents($firebase_json_path);
        if ($json_content === false) {
            throw new Exception('Failed to read Firebase JSON file');
        }

        $service_account = json_decode($json_content, true);
        if (!isset($service_account['project_id'])) {
            throw new Exception('Project ID not found in Firebase JSON');
        }

        $project_id = $service_account['project_id'];
    } catch (Exception $e) {
        error_log("FCM Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'response' => null
        ];
    }

    // FCM HTTP v1 API 엔드포인트
    $fcm_url = "https://fcm.googleapis.com/v1/projects/{$project_id}/messages:send";

    // 메시지 페이로드 구성
    $message = [
        'notification' => [
            'title' => $title,
            'body' => $body
        ]
    ];

    // 이미지 추가 (있는 경우)
    if (!empty($image_url)) {
        $message['notification']['image'] = $image_url;
    }

    // 추가 데이터 추가
    if (!empty($url)) {
        $data['url'] = $url;
    }
    if (!empty($data)) {
        $message['data'] = $data;
    }

    // Android 특화 설정
    $message['android'] = [
        'priority' => 'high',
        'notification' => [
            'sound' => 'default',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        ]
    ];

    // iOS 특화 설정
    $message['apns'] = [
        'headers' => [
            'apns-priority' => '10'
        ],
        'payload' => [
            'aps' => [
                'alert' => [
                    'title' => $title,
                    'body' => $body
                ],
                'sound' => 'default',
                'badge' => 1
            ]
        ]
    ];

    // 전송 대상 설정 (토큰 또는 토픽)
    if (!empty($token)) {
        // 개별 전송
        $message['token'] = $token;
    } elseif (!empty($topic)) {
        // 토픽 전송
        $message['topic'] = $topic;
    } else {
        return [
            'success' => false,
            'message' => 'Either token or topic must be provided',
            'response' => null
        ];
    }

    // 최종 페이로드
    $payload = [
        'message' => $message
    ];

    // HTTP 요청
    try {
        $ch = curl_init($fcm_url);
        if ($ch === false) {
            throw new Exception('Failed to initialize cURL');
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error: {$error}");
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 응답 파싱
        $response_data = json_decode($response, true);

        // 결과 반환
        if ($http_code === 200) {
            return [
                'success' => true,
                'message' => 'Push notification sent successfully',
                'response' => $response_data
            ];
        } else {
            // 에러 로그
            error_log("FCM Error: HTTP {$http_code}, Response: {$response}");

            return [
                'success' => false,
                'message' => "Failed to send push notification. HTTP Code: {$http_code}",
                'response' => $response_data
            ];
        }
    } catch (Exception $e) {
        error_log("FCM Error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage(),
            'response' => null
        ];
    }
}

/**
 * ========================================
 * 토픽 구독/구독 해제
 * ========================================
 */

/**
 * 토픽 구독
 *
 * @param array $tokens FCM 토큰 배열
 * @param string $topic 토픽 이름
 * @return array 결과
 */
function subscribe_to_topic($tokens, $topic) {
    $access_token = get_fcm_access_token();

    if (!$access_token) {
        return ['success' => false, 'message' => 'Failed to get access token'];
    }

    $url = 'https://iid.googleapis.com/iid/v1:batchAdd';

    $payload = [
        'to' => '/topics/' . $topic,
        'registration_tokens' => is_array($tokens) ? $tokens : [$tokens]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'success' => ($http_code === 200),
        'http_code' => $http_code,
        'response' => json_decode($response, true)
    ];
}

/**
 * 토픽 구독 해제
 *
 * @param array $tokens FCM 토큰 배열
 * @param string $topic 토픽 이름
 * @return array 결과
 */
function unsubscribe_from_topic($tokens, $topic) {
    $access_token = get_fcm_access_token();

    if (!$access_token) {
        return ['success' => false, 'message' => 'Failed to get access token'];
    }

    $url = 'https://iid.googleapis.com/iid/v1:batchRemove';

    $payload = [
        'to' => '/topics/' . $topic,
        'registration_tokens' => is_array($tokens) ? $tokens : [$tokens]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'success' => ($http_code === 200),
        'http_code' => $http_code,
        'response' => json_decode($response, true)
    ];
}

/**
 * ========================================
 * 사용 예시
 * ========================================
 *
 * // 개별 전송
 * $result = send_fcm_push(
 *     'FCM_TOKEN_HERE',
 *     '알림 제목',
 *     '알림 내용',
 *     'https://example.com',
 *     'https://example.com/image.jpg'
 * );
 *
 * // 토픽 전송
 * $result = send_fcm_push(
 *     null,
 *     '전체 알림',
 *     '전체 사용자에게 전송',
 *     '',
 *     '',
 *     'news_test'
 * );
 *
 * if ($result['success']) {
 *     echo "전송 성공!";
 * } else {
 *     echo "전송 실패: " . $result['message'];
 * }
 */
?>
