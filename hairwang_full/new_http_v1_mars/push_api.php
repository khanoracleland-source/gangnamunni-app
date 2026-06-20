<?php
/**
 * ========================================
 * 푸시 알림 API (개선 버전)
 * ========================================
 *
 * 이 파일은 HTTP 요청을 통해 푸시 알림을 보낼 수 있는 API를 제공합니다.
 * GET 또는 POST 요청으로 호출할 수 있습니다.
 *
 * @file    push_api.php
 * @updated 2025-12-18
 * @version 2.1.0
 * @changes
 *   - config.php를 사용하여 Firebase JSON 파일과 토픽 설정 관리
 *   - devices.php의 알림 전송 방식과 동일하게 개선
 *   - 더 상세한 응답 메시지 및 에러 핸들링
 *   - 전송 타임스탬프 추가
 *   - 로그인 인증 시스템 추가 (세션 또는 API 키 방식)
 *
 * @authentication
 *   방법 1: 세션 인증 (웹 브라우저에서 호출 시)
 *     - 먼저 login.php에서 로그인 필요
 *     - 세션 쿠키로 자동 인증
 *
 *   방법 2: API 키 인증 (프로그램에서 호출 시)
 *     - HTTP 헤더에 API 키 포함: X-API-Key: your_api_key_here
 *     - 또는 GET/POST 파라미터: api_key=your_api_key_here
 */

// 환경 변수 로더
require_once __DIR__ . '/env.php';

// 인증 시스템 로드
require_once __DIR__ . '/auth.php';

// config.php 로드
require_once __DIR__ . '/config.php';

// 오류 보고 설정
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 헤더 설정 - JSON 응답
header('Content-Type: application/json');

// 응답 배열 초기화
$response = [
    'success' => false,
    'message' => '',
    'data' => [],
    'timestamp' => date('Y-m-d H:i:s')  // 전송 시각 추가
];

/**
 * ========================================
 * 인증 체크
 * ========================================
 */

// API 키 설정 (.env 파일에서 로드)
define('API_KEY', env('PUSH_API_KEY', 'mars_push_api_key_2025'));

// 인증 방식 1: 세션 인증 (웹 브라우저에서 접근)
$is_authenticated_session = is_logged_in();

// 인증 방식 2: API 키 인증 (프로그램에서 접근)
$api_key_header = isset($_SERVER['HTTP_X_API_KEY']) ? $_SERVER['HTTP_X_API_KEY'] : '';
$api_key_param = isset($_REQUEST['api_key']) ? $_REQUEST['api_key'] : '';
$is_authenticated_api = ($api_key_header === API_KEY) || ($api_key_param === API_KEY);

// 둘 중 하나라도 인증되면 접근 허용
if (!$is_authenticated_session && !$is_authenticated_api) {
    $response['message'] = '인증이 필요합니다. 로그인하거나 유효한 API 키를 제공해주세요.';
    $response['data'] = [
        'error_code' => 'AUTHENTICATION_REQUIRED',
        'help' => '세션 인증: login.php에서 로그인 | API 키 인증: X-API-Key 헤더 또는 api_key 파라미터 사용'
    ];
    echo json_encode($response);
    exit;
}

// GET 또는 POST 요청 파라미터 처리
$push_type = isset($_REQUEST['push_type']) ? $_REQUEST['push_type'] : '';
$app = isset($_REQUEST['app']) ? $_REQUEST['app'] : '';
$user_token = isset($_REQUEST['user_token']) ? $_REQUEST['user_token'] : '';
$title = isset($_REQUEST['title']) ? $_REQUEST['title'] : '';
$message = isset($_REQUEST['message']) ? $_REQUEST['message'] : '';
$memo = isset($_REQUEST['memo']) ? $_REQUEST['memo'] : ''; // message와 호환을 위해
$url = isset($_REQUEST['url']) ? $_REQUEST['url'] : '';
$file_url = isset($_REQUEST['file_url']) ? $_REQUEST['file_url'] : '';
$image_url = isset($_REQUEST['image_url']) ? $_REQUEST['image_url'] : ''; // file_url과 호환을 위해
$topic = isset($_REQUEST['topic']) ? $_REQUEST['topic'] : '';
$target_devices = isset($_REQUEST['target_devices']) ? $_REQUEST['target_devices'] : []; // 전체 푸시 대상 기기 유형

// 추가 인텐트 파라미터
$order_id = isset($_REQUEST['order_id']) ? $_REQUEST['order_id'] : '';
$arg1 = isset($_REQUEST['arg1']) ? $_REQUEST['arg1'] : '$arg1';
$arg2 = isset($_REQUEST['arg2']) ? $_REQUEST['arg2'] : '1'; // 기본값 1 (팝업 표시)
$arg3 = isset($_REQUEST['arg3']) ? $_REQUEST['arg3'] : get_arg3_value();

// memo가 비어있고 message가 있으면 memo에 message 값 할당
if (empty($memo) && !empty($message)) {
    $memo = $message;
}

// file_url이 비어있고 image_url이 있으면 file_url에 image_url 값 할당
if (empty($file_url) && !empty($image_url)) {
    $file_url = $image_url;
}

// ========================================
// 푸시 타입에 따른 처리
// ========================================

if ($push_type === 'group') {
    // 디버깅: 수신된 모든 파라미터 기록
    error_log('Group push request - Parameters: ' . print_r($_REQUEST, true));

    // 전체 푸시 처리
    if (empty($title) || empty($memo)) {
        $response['message'] = '필수 파라미터가 누락되었습니다. title, memo는 필수 입력 항목입니다.';
        echo json_encode($response);
        exit;
    }

    // config.php에서 토픽 이름 가져오기 (하드코딩 제거)
    $topic_name = get_topic_name();

    // 토픽 이름 유효성 검사
    if (!validate_topic_name($topic_name)) {
        $response['message'] = '토픽 이름이 올바르지 않습니다. 설정을 확인하세요.';
        $response['data'] = [
            'topic' => $topic_name,
            'error' => '토픽 이름은 영문자, 숫자, 밑줄(_), 하이픈(-)만 사용 가능합니다.'
        ];
        echo json_encode($response);
        exit;
    }

    // 토픽으로 전체 푸시 전송
    $result = send_topic_push($topic_name, $title, $memo, $url, $file_url, $arg1, $arg2);

    // 결과 처리
    if ($result) {
        $response['success'] = true;
        $response['message'] = '전체 푸시 알림이 성공적으로 전송되었습니다.';
        $response['data'] = [
            'push_type' => 'topic',
            'topic' => $topic_name,
            'title' => $title,
            'memo' => $memo,
            'url' => $url,
            'file_url' => $file_url,
            'success_count' => 1,  // 토픽 전송은 성공/실패만 반환
            'fail_count' => 0
        ];
    } else {
        // 디버깅: 더 자세한 오류 정보
        $error_logs = error_get_last();
        $response['message'] = '전체 푸시 알림 전송에 실패했습니다.';
        $response['data'] = [
            'push_type' => 'topic',
            'topic' => $topic_name,
            'debug_info' => '클라이언트 앱에서 "' . $topic_name . '" 토픽을 구독했는지 확인하세요.',
            'error' => $error_logs ? $error_logs['message'] : '알 수 없는 오류',
            'firebase_config' => get_firebase_json_path(),
            'success_count' => 0,
            'fail_count' => 1
        ];
    }
}
else if (!empty($topic)) {
    // 토픽 푸시 처리
    if (empty($title) || empty($memo)) {
        $response['message'] = '필수 파라미터가 누락되었습니다. topic, title, memo(또는 message)는 필수 입력 항목입니다.';
        echo json_encode($response);
        exit;
    }
    
    // 토픽 푸시 발송
    $result = send_topic_push($topic, $title, $memo, $url, $file_url, $arg1, $arg2);
    
    // 결과 처리
    if ($result) {
        $response['success'] = true;
        $response['message'] = '토픽 푸시 알림이 성공적으로 전송되었습니다.';
        $response['data'] = [
            'topic' => $topic,
            'title' => $title,
            'memo' => $memo,
            'url' => $url,
            'file_url' => $file_url
        ];
    } else {
        $response['message'] = '토픽 푸시 알림 전송에 실패했습니다.';
    }
}
else {
    // 필수 파라미터 검증
    if (empty($app) || empty($user_token) || empty($title) || empty($memo)) {
        $response['message'] = '필수 파라미터가 누락되었습니다. app, user_token, title, memo(또는 message)는 필수 입력 항목입니다.';
        echo json_encode($response);
        exit;
    }
    
    // 앱 유형 검증
    if ($app !== 'android' && $app !== 'ios') {
        $response['message'] = '앱 유형은 android 또는 ios만 가능합니다.';
        echo json_encode($response);
        exit;
    }
    
    // 개별 푸시 발송
    error_log("=== 개별 푸시 발송 시작 ===");
    error_log("App: $app, Token: " . substr($user_token, 0, 20) . "..., Title: $title");

    $result = send_push_v1_jwt($app, $user_token, $title, $memo, $url, $file_url, $arg1, $arg2);

    error_log("푸시 발송 결과: " . ($result > 0 ? "성공 ($result)" : "실패 ($result)"));

    // 결과 처리
    if ($result > 0) {
        $response['success'] = true;
        $response['message'] = '푸시 알림이 성공적으로 전송되었습니다.';
        $response['data'] = [
            'app' => $app,
            'user_token' => $user_token,
            'title' => $title,
            'memo' => $memo,
            'url' => $url,
            'file_url' => $file_url,
            'order_id' => $order_id,
            'arg1' => $arg1,
            'arg2' => $arg2,
            'arg3' => $arg3
        ];
    } else {
        // 디버깅: 더 자세한 오류 정보
        error_log("=== 푸시 발송 실패 - 상세 정보 수집 중 ===");
        $error_logs = error_get_last();
        $response['message'] = '푸시 알림 전송에 실패했습니다.';
        error_log("Error logs: " . print_r($error_logs, true));
        $response['data'] = [
            'app' => $app,
            'user_token' => substr($user_token, 0, 20) . '...', // 토큰 일부만 표시
            'title' => $title,
            'memo' => $memo,
            'url' => $url,
            'error' => $error_logs ? $error_logs['message'] : '알 수 없는 오류',
            'firebase_config' => get_firebase_json_path(),
            'project_id' => get_project_id(),
            'arg3' => get_arg3_value(),
            'default_url' => get_default_url(),
            'debug_hint' => 'Firebase 키 파일 경로와 프로젝트 ID를 확인하세요.'
        ];
        error_log("Response data 배열 생성 완료: " . print_r($response['data'], true));
    }
}

// JSON 응답 반환
error_log("최종 응답 전송: " . json_encode($response));
echo json_encode($response);
exit;

/**
 * 푸시 알림 발송 함수 (JWT 인증 사용)
 *
 * config.php의 설정을 사용하여 개별 푸시 알림을 전송합니다.
 *
 * @param string $app        앱 유형 (android 또는 ios)
 * @param string $user_token 사용자 FCM 토큰
 * @param string $title      알림 제목
 * @param string $memo       알림 내용
 * @param string $url2       알림 클릭 시 이동할 URL
 * @param string $file_url   이미지 URL (iOS Notification Service Extension에서 사용)
 * @param string $arg1       인텐트 파라미터 1 (기본값: '$arg1')
 * @param string $arg2       인텐트 파라미터 2 (기본값: '1')
 *
 * @return int 성공 수량
 */
function send_push_v1_jwt($app, $user_token, $title, $memo, $url2, $file_url = '', $arg1 = '$arg1', $arg2 = '1')
{
    error_log(">>> send_push_v1_jwt 함수 호출됨 <<<");
    error_log("Parameters - App: $app, Token: " . substr($user_token, 0, 30) . "..., Title: $title, URL: $url2, file_url: $file_url");

    global $dc, $Dcs, $member;

    // 인텐트 파라미터 설정
    $arg3 = get_arg3_value();

    // URL 값 확인 및 기본 값 지정
	if ($url2 === null || $url2 == "" || $url2 == "http://" || $url2 == "https://") {
		$url2 = get_default_url() . "?call=push";
	} else {
        // URL에 ?call=push 추가
        $url2 = rtrim($url2, '?&') . (strpos($url2, '?') !== false ? '&' : '?') . 'call=push';
    }

    // config.php에서 프로젝트 ID 가져오기
    $project_id = get_project_id();

    // FCM 서버 URL (프로젝트 ID 동적 적용)
    $url = 'https://fcm.googleapis.com/v1/projects/' . $project_id . '/messages:send';

    // config.php에서 서비스 계정 파일 경로 가져오기
    $serviceAccountPath = get_firebase_json_path();

    // 디버깅: 서비스 계정 파일 확인
    if (!file_exists($serviceAccountPath)) {
        error_log("Error: Service account file not found: " . $serviceAccountPath);
        return 0;
    }

    // 액세스 토큰 생성
    $accessToken = getAccessToken($serviceAccountPath);
    
    if (empty($accessToken)) {
        error_log("Error: Failed to get access token");
        return 0;
    }

    // 안드로이드 푸시 발송
    if ($app == 'android') {
        $pushdata = [
            'message' => [
                'token' => $user_token,
                'data' => [
                    'title' => $title,
                    'message' => $memo,
                    'url' => $url2,
                    'file_url' => $file_url,  // 이미지 URL 추가
                    'arg1' => $arg1,
                    'arg2' => $arg2,
                    'arg3' => $arg3,
                ]
            ]
        ];
    }
    // 애플 푸시 발송
    else if ($app == 'ios') {
        $pushdata = [
            'message' => [
                'token' => $user_token,
                'notification' => [
                    'title' => $title,
                    'body' => $memo,
                ],
                'data' => [
                    'url' => $url2,
                    'file_url' => $file_url,  // ⭐ 이미지 URL 추가
                    'arg1' => $arg1,
                    'arg2' => $arg2,
                    'arg3' => $arg3,
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'mutable-content' => 1,  // ⭐ Notification Service Extension 실행 필수
                            'alert' => [
                                'title' => $title,
                                'body' => $memo
                            ],
                            'sound' => 'default'
                        ],
                        // data 필드들을 aps 외부에도 포함 (iOS 호환성)
                        'url' => $url2,
                        'file_url' => $file_url,  // ⭐ 이미지 URL 추가
                        'arg1' => $arg1,
                        'arg2' => $arg2,
                        'arg3' => $arg3
                    ]
                ]
            ]
        ];
    }


    // HTTP 요청 헤더 설정
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    // cURL 초기화 및 설정
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pushdata));

    // FCM 서버로 푸시 요청 전송
    $response = curl_exec($ch);

    // cURL 에러 처리
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        error_log("cURL Error: " . $error_msg);
        return 0;
    }

    curl_close($ch);

    // 푸시 전송 결과 반환
    $obj = json_decode($response);

    // 디버깅: 응답 로그
    error_log("FCM Response: " . $response);

    if (!$obj || !isset($obj->{"name"})) {
        error_log("FCM Response Error: " . $response);
        // 에러 상세 정보 로그
        if ($obj && isset($obj->error)) {
            error_log("FCM Error Details: " . json_encode($obj->error));
        }
        return 0;
    }

    // 푸시 전송 결과 반환 : 성공 수량 반환
    $cnt = isset($obj->{"success"}) ? $obj->{"success"} : 1;

    error_log("FCM Push Success: " . $cnt);
    return $cnt;
}

/**
 * 액세스 토큰 생성 함수
 * 
 * @param string $serviceAccountPath 서비스 계정 JSON 파일 경로
 * 
 * @return string 액세스 토큰 또는 실패 시 빈 문자열
 */
function getAccessToken($serviceAccountPath) {
    // 서비스 계정 파일 확인
    if (!file_exists($serviceAccountPath)) {
        return '';
    }

    // JSON 파일 읽기 및 파싱
    $jsonContent = file_get_contents($serviceAccountPath);
    if ($jsonContent === false) {
        return '';
    }

    $jwt = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return '';
    }

    // JWT 구성
    $now = time();
    $token = [
        'iss' => $jwt['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $jwt['token_uri'],
        'exp' => $now + 3600,
        'iat' => $now
    ];

    // 서명 생성 준비
    $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = base64_encode(json_encode($token));
    $signature = '';

    $privateKey = $jwt['private_key'];

    // 서명 생성
    if (!openssl_sign("$header.$payload", $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        return '';
    }

    $jwtToken = "$header.$payload." . base64_encode($signature);

    // 액세스 토큰 요청
    $ch = curl_init($jwt['token_uri']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwtToken
    ]));

    $response = curl_exec($ch);

    // cURL 에러 처리
    if (curl_errno($ch)) {
        curl_close($ch);
        return '';
    }

    curl_close($ch);
    $data = json_decode($response, true);

    // 액세스 토큰 확인
    if (!isset($data['access_token'])) {
        return '';
    }

    return $data['access_token'];
}

/**
 * 토픽 푸시 알림 발송 함수
 *
 * config.php의 설정을 사용하여 토픽 기반 전체 푸시 알림을 전송합니다.
 *
 * @param string $topic      토픽 이름
 * @param string $title      알림 제목
 * @param string $memo       알림 내용
 * @param string $url        알림 클릭 시 이동할 URL
 * @param string $file_url   알림에 표시할 이미지 URL
 * @param string $arg1       인텐트 파라미터 1 (기본값: '$arg1')
 * @param string $arg2       인텐트 파라미터 2 (기본값: '1')
 *
 * @return bool 성공 여부
 */
function send_topic_push($topic, $title, $memo, $url, $file_url = '', $arg1 = '$arg1', $arg2 = '1') {
    // URL 값 확인 및 기본 값 지정
    if ($url === null || $url == "" || $url == "http://" || $url == "https://") {
        $url = get_default_url() . "?call=push";
    } else {
        // URL에 ?call=push 추가
        $url = rtrim($url, '?&') . (strpos($url, '?') !== false ? '&' : '?') . 'call=push';
    }

    // 인텐트 파라미터 설정
    $arg3 = get_arg3_value();  // 보낸 사람

    // config.php에서 프로젝트 ID 가져오기
    $project_id = get_project_id();

    // FCM 서버 URL (프로젝트 ID 동적 적용)
    $fcm_url = 'https://fcm.googleapis.com/v1/projects/' . $project_id . '/messages:send';

    // config.php에서 서비스 계정 파일 경로 가져오기
    $serviceAccountPath = get_firebase_json_path();

    // 디버깅: 서비스 계정 파일 확인
    if (!file_exists($serviceAccountPath)) {
        error_log("Error: Service account file not found: " . $serviceAccountPath);
        return false;
    }

    // 액세스 토큰 생성
    $accessToken = getAccessToken($serviceAccountPath);
    
    if (empty($accessToken)) {
        error_log("Error: Failed to get access token");
        return false;
    }

    // 토픽 푸시 데이터 구성
    $pushdata = [
        'message' => [
            'topic' => $topic,
            'notification' => [
                'title' => $title,
                'body' => $memo
            ],
            'data' => [
                'title' => $title,
                'message' => $memo,
                'url' => $url,
                'file_url' => $file_url,  // 이미지 URL (Android/iOS 공통)
                'arg1' => $arg1,
                'arg2' => $arg2,
                'arg3' => $arg3,
            ],
            'android' => [
                'priority' => 'high'
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'mutable-content' => 1,  // ⭐ iOS Notification Service Extension 실행 필수
                        'alert' => [
                            'title' => $title,
                            'body' => $memo
                        ],
                        'sound' => 'default'
                    ],
                    // data 필드들을 aps 외부에도 포함 (iOS 호환성)
                    'url' => $url,
                    'file_url' => $file_url,  // ⭐ 이미지 URL
                    'arg1' => $arg1,
                    'arg2' => $arg2,
                    'arg3' => $arg3
                ]
            ]
        ]
    ];

    // HTTP 요청 헤더 설정
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    // cURL 초기화 및 설정
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fcm_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pushdata));

    // FCM 서버로 푸시 요청 전송
    $response = curl_exec($ch);

    // cURL 에러 처리
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        error_log("cURL Error: " . $error);
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    // 푸시 전송 결과 확인
    $obj = json_decode($response, true);
    
    if (!$obj) {
        error_log("Error: Invalid JSON response: " . $response);
        return false;
    }
    
    if (!isset($obj['name'])) {
        error_log("Error: FCM response error: " . $response);
        return false;
    }

    return true;
}
?> 