<?php
/**
 * 푸시 알림 처리 시스템
 *
 * 이 파일은 개별 및 전체 푸시 알림을 처리하는 기능을 제공합니다.
 * Android와 iOS 기기 모두에 푸시 알림을 보낼 수 있습니다.
 *
 * @author  개발자
 * @version 1.1.0
 * @updated 2025-12-18
 * @changes
 *   - 로그인 인증 시스템 추가
 */

// 인증 시스템 로드
require_once __DIR__ . '/auth.php';

// 오류 보고 설정
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 헤더 설정 - JSON 응답
header('Content-Type: application/json');

// 응답 배열 초기화
$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

/**
 * ========================================
 * 인증 체크
 * ========================================
 */

// 로그인 확인 (웹 UI에서 호출되는 경우)
if (!is_logged_in()) {
    $response['message'] = '로그인이 필요합니다.';
    $response['data'] = ['error_code' => 'AUTHENTICATION_REQUIRED'];
    echo json_encode($response);
    exit;
}

// POST 요청 확인
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = '잘못된 요청 방식입니다. POST 요청이 필요합니다.';
    echo json_encode($response);
    exit;
}

// 필수 파라미터 확인
$push_type = isset($_POST['push_type']) ? $_POST['push_type'] : '';
$title = isset($_POST['title']) ? $_POST['title'] : '';
$message = isset($_POST['message']) ? $_POST['message'] : '';
$url = isset($_POST['url']) ? $_POST['url'] : 'https://edumars.net';
$image_url = isset($_POST['image_url']) ? $_POST['image_url'] : '';

// 필수 파라미터 검증
if (empty($title) || empty($message)) {
    $response['message'] = '제목과 내용은 필수 입력 항목입니다.';
    echo json_encode($response);
    exit;
}

// 푸시 타입에 따른 처리
if ($push_type === 'individual') {
    // 개별 푸시 처리
    $device_type = isset($_POST['device_type']) ? $_POST['device_type'] : '';
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    
    if (empty($device_type) || empty($token)) {
        $response['message'] = '기기 유형과 토큰은 필수 입력 항목입니다.';
        echo json_encode($response);
        exit;
    }
    
    // 개별 푸시 발송
    $result = send_individual_push($device_type, $token, $title, $message, $url, $image_url);
    
    if ($result) {
        $response['success'] = true;
        $response['message'] = '푸시 알림이 성공적으로 전송되었습니다.';
        $response['data'] = ['device_type' => $device_type];
    } else {
        $response['message'] = '푸시 알림 전송에 실패했습니다.';
    }
} elseif ($push_type === 'group') {
    // 전체 푸시 처리
    $target_devices = isset($_POST['target_devices']) ? $_POST['target_devices'] : [];
    
    if (empty($target_devices)) {
        $response['message'] = '대상 기기를 하나 이상 선택해야 합니다.';
        echo json_encode($response);
        exit;
    }
    
    // 전체 푸시 발송
    $result = send_group_push($target_devices, $title, $message, $url, $image_url);
    
    if ($result) {
        $response['success'] = true;
        $response['message'] = '전체 푸시 알림이 성공적으로 전송되었습니다.';
        $response['data'] = ['target_devices' => $target_devices, 'sent_count' => $result];
    } else {
        $response['message'] = '전체 푸시 알림 전송에 실패했습니다.';
    }
} else {
    $response['message'] = '알 수 없는 푸시 유형입니다.';
}

// JSON 응답 반환
echo json_encode($response);
exit;

/**
 * 개별 푸시 알림 발송 함수
 * 
 * @param string $device_type 기기 유형 (android 또는 ios)
 * @param string $token       사용자 FCM 토큰
 * @param string $title       알림 제목
 * @param string $message     알림 내용
 * @param string $url         알림 클릭 시 이동할 URL
 * @param string $image_url   알림에 표시할 이미지 URL (선택사항)
 * 
 * @return bool 성공 여부
 */
function send_individual_push($device_type, $token, $title, $message, $url, $image_url = '') {
    // FCM 서버 URL
    $fcm_url = 'https://fcm.googleapis.com/v1/projects/mars-38372/messages:send';
    
    // 서비스 계정 키 파일 경로
    $service_account_path = __DIR__ . '/mars-38372-firebase-adminsdk-60l5a-23f211e854.json';
    
    // 액세스 토큰 생성
    $access_token = get_access_token($service_account_path);
    
    if (empty($access_token)) {
        return false;
    }
    
    // 기기 유형에 따른 메시지 구성
    if ($device_type === 'android') {
        // 안드로이드 푸시 데이터 구성
        $push_data = [
            'message' => [
                'token' => $token,
                'data' => [
                    'title' => $title,
                    'message' => $message,
                    'arg1' => '$arg1',
                    'arg2' => '1', // 팝업 방식을 원하면 1 아니면 0
                    'arg3' => 'MARS', // 보낸 사람
                    'url' => $url,
                ]
            ]
        ];
        
        // 이미지 URL이 있는 경우 추가
        if (!empty($image_url)) {
            $push_data['message']['data']['file_url'] = $image_url;
        }
    } elseif ($device_type === 'ios') {
        // iOS 푸시 데이터 구성
        $push_data = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $message,
                ],
                'data' => [
                    'arg1' => '$arg1',
                    'arg2' => '$arg2',
                    'arg3' => 'MARS',
                    'url' => $url,
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default'
                        ]
                    ]
                ]
            ]
        ];
        
        // 이미지 URL이 있는 경우 추가
        if (!empty($image_url)) {
            $push_data['message']['data']['file_url'] = $image_url;
        }
    } else {
        return false;
    }
    
    // HTTP 요청 헤더 설정
    $headers = [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ];
    
    // cURL 초기화 및 설정
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fcm_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($push_data));
    
    // FCM 서버로 푸시 요청 전송
    $response = curl_exec($ch);
    
    // cURL 에러 처리
    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    // 푸시 전송 결과 확인
    $result = json_decode($response, true);
    
    return isset($result['name']);
}

/**
 * 전체 푸시 알림 발송 함수
 * 
 * @param array  $target_devices 대상 기기 유형 배열 (android 및/또는 ios)
 * @param string $title          알림 제목
 * @param string $message        알림 내용
 * @param string $url            알림 클릭 시 이동할 URL
 * @param string $image_url      알림에 표시할 이미지 URL (선택사항)
 * 
 * @return int|bool 성공적으로 전송된 메시지 수 또는 실패 시 false
 */
function send_group_push($target_devices, $title, $message, $url, $image_url = '') {
    // 데이터베이스 연결 (실제 구현 시 데이터베이스에서 토큰을 가져와야 함)
    // 이 예제에서는 간단히 하기 위해 하드코딩된 토큰 사용
    $tokens = [
        'android' => [
            'android_token_1',
            'android_token_2',
            // 실제 안드로이드 토큰 목록
        ],
        'ios' => [
            'ios_token_1',
            'ios_token_2',
            // 실제 iOS 토큰 목록
        ]
    ];
    
    $success_count = 0;
    
    // 각 기기 유형별로 처리
    foreach ($target_devices as $device_type) {
        if (!isset($tokens[$device_type]) || empty($tokens[$device_type])) {
            continue;
        }
        
        // 각 토큰에 대해 개별 푸시 발송
        foreach ($tokens[$device_type] as $token) {
            $result = send_individual_push($device_type, $token, $title, $message, $url, $image_url);
            
            if ($result) {
                $success_count++;
            }
        }
    }
    
    return $success_count > 0 ? $success_count : false;
}

/**
 * 액세스 토큰 생성 함수
 * 
 * @param string $service_account_path 서비스 계정 JSON 파일 경로
 * 
 * @return string 액세스 토큰 또는 실패 시 빈 문자열
 */
function get_access_token($service_account_path) {
    // 서비스 계정 파일 확인
    if (!file_exists($service_account_path)) {
        return '';
    }
    
    // JSON 파일 읽기 및 파싱
    $json_content = file_get_contents($service_account_path);
    if ($json_content === false) {
        return '';
    }
    
    $jwt = json_decode($json_content, true);
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
    
    $private_key = $jwt['private_key'];
    
    // 서명 생성
    if (!openssl_sign("$header.$payload", $signature, $private_key, OPENSSL_ALGO_SHA256)) {
        return '';
    }
    
    $jwt_token = "$header.$payload." . base64_encode($signature);
    
    // 액세스 토큰 요청
    $ch = curl_init($jwt['token_uri']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt_token
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
?> 