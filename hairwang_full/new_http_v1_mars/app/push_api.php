<?php
/**
 * 푸시 알림 API
 * 
 * 이 파일은 HTTP 요청을 통해 푸시 알림을 보낼 수 있는 API를 제공합니다.
 * 모든 인텐트 파라미터를 전달할 수 있도록 구현되었습니다.
 * 
 * @version 1.0.0
 */

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
$arg3 = isset($_REQUEST['arg3']) ? $_REQUEST['arg3'] : 'MARS';

// memo가 비어있고 message가 있으면 memo에 message 값 할당
if (empty($memo) && !empty($message)) {
    $memo = $message;
}

// file_url이 비어있고 image_url이 있으면 file_url에 image_url 값 할당
if (empty($file_url) && !empty($image_url)) {
    $file_url = $image_url;
}

// 푸시 타입에 따른 처리
if ($push_type === 'group') {
    // 전체 푸시 처리
    if (empty($title) || empty($memo)) {
        $response['message'] = '필수 파라미터가 누락되었습니다. title, memo는 필수 입력 항목입니다.';
        echo json_encode($response);
        exit;
    }
    
    // 디버깅: 요청 정보 기록
    error_log('Topic push request - Topic: news, Title: ' . $title . ', Memo: ' . $memo);
    
    // 토픽 이름은 영문자, 숫자, 밑줄(_)만 포함해야 함
    $topic_name = 'news_test';
    
    // 'news' 토픽으로 전체 푸시 전송
    $result = send_topic_push($topic_name, $title, $memo, $url, $file_url, '', '$arg1', '1', 'MARS');
    
    // 결과 처리
    if ($result) {
        $response['success'] = true;
        $response['message'] = '전체 푸시 알림이 성공적으로 전송되었습니다.';
        $response['data'] = [
            'topic' => $topic_name,
            'title' => $title,
            'memo' => $memo,
            'url' => $url,
            'file_url' => $file_url
        ];
    } else {
        // 오류 로그 확인
        $error_logs = error_get_last();
        $response['message'] = '전체 푸시 알림 전송에 실패했습니다.';
        $response['data'] = [
            'debug_info' => '클라이언트 앱에서 "news" 토픽을 구독했는지 확인하세요.',
            'error' => $error_logs ? $error_logs['message'] : '알 수 없는 오류'
        ];
    }
}
else if (!empty($topic)) {
    // 토픽 푸시 처리 (기존 코드)
    // 필수 파라미터 검증
    if (empty($title) && empty($memo)) {
        $response['message'] = '필수 파라미터가 누락되었습니다. topic, title, memo(또는 message) 중 하나는 필수 입력 항목입니다.';
        echo json_encode($response);
        exit;
    }

    // 푸시 알림 발송
    $result = send_topic_push($topic, $title, $memo, $url, $file_url, $order_id, $arg1, $arg2, $arg3);

    // 결과 처리
    if ($result) {
        $response['success'] = true;
        $response['message'] = '푸시 알림이 성공적으로 전송되었습니다.';
        $response['data'] = [
            'topic' => $topic,
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
        $response['message'] = '푸시 알림 전송에 실패했습니다.';
    }
}
else {
    // 개별 푸시 발송
    $result = send_push_v1_jwt($app, $user_token, $title, $memo, $url, $file_url, $order_id, $arg1, $arg2, $arg3);

    // 결과 처리
    if ($result) {
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
        $response['message'] = '푸시 알림 전송에 실패했습니다.';
    }
}

// JSON 응답 반환
echo json_encode($response);
exit;

/**
 * 푸시 알림 발송 함수 (JWT 인증 사용)
 * 
 * @param string $app        앱 유형 (android 또는 ios)
 * @param string $user_token 사용자 FCM 토큰
 * @param string $title      알림 제목
 * @param string $memo       알림 내용
 * @param string $url        알림 클릭 시 이동할 URL
 * @param string $file_url   알림에 표시할 이미지 URL
 * @param string $order_id   주문 ID
 * @param string $arg1       추가 파라미터 1
 * @param string $arg2       추가 파라미터 2 (팝업 표시 여부: '1' 또는 '0')
 * @param string $arg3       추가 파라미터 3 (발신자 정보)
 * 
 * @return bool 성공 여부
 */
function send_push_v1_jwt($app, $user_token, $title, $memo, $url, $file_url = '', $order_id = '', $arg1 = '$arg1', $arg2 = '1', $arg3 = 'MARS') {
    // $url 값 확인 및 기본 값 지정
    if ($url === null || $url == "" || $url == "http://" || $url == "https://") {
        $url = "https://edumars.net";
    }

    // FCM 서버 URL
    $fcm_url = 'https://fcm.googleapis.com/v1/projects/mars-38372/messages:send';

    // 서비스 계정 키 파일의 경로
    $serviceAccountPath = __DIR__ . '/mars-38372-firebase-adminsdk-60l5a-23f211e854.json';

    // 디버깅: 서비스 계정 파일 확인
    if (!file_exists($serviceAccountPath)) {
        return false;
    }

    // 액세스 토큰 생성
    $accessToken = getAccessToken($serviceAccountPath);
    
    if (empty($accessToken)) {
        return false;
    }

    // 안드로이드 푸시 발송
    if ($app == 'android') {
        $pushdata = [
            'message' => [
                'token' => $user_token,
                'data' => [
                    'title' => $title,
                    'message' => $memo,
                    'order_id' => $order_id,
                    'arg1' => $arg1,
                    'arg2' => $arg2, // 팝업 방식을 원하면 1 아니면 0
                    'arg3' => $arg3, // 보낸 사람
                    'url' => $url,
                    'param_intent_url' => $url
                ]
            ]
        ];
        
        // 이미지 URL이 있는 경우 추가
        if (!empty($file_url)) {
            $pushdata['message']['data']['file_url'] = $file_url;
        }
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
                    'order_id' => $order_id,
                    'arg1' => $arg1,
                    'arg2' => $arg2,
                    'arg3' => $arg3,
                    'url' => $url,
                    'param_intent_url' => $url
                ],
                'apns' => [
                    // APNs 설정을 통해 iOS에서 사운드를 관리
                    'payload' => [
                        'aps' => [
                            'sound' => 'default' // iOS 푸시 사운드를 여기서 설정
                        ]
                    ]
                ]
            ]
        ];
        
        // 이미지 URL이 있는 경우 추가
        if (!empty($file_url)) {
            $pushdata['message']['data']['file_url'] = $file_url;
        }
    }

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
        curl_close($ch);
        return false;
    }

    curl_close($ch);

    // 푸시 전송 결과 확인
    $obj = json_decode($response, true);
    
    if (!$obj || !isset($obj['name'])) {
        return false;
    }

    return true;
}

/**
 * 토픽 푸시 알림 발송 함수
 * 
 * @param string $topic      토픽 이름
 * @param string $title      알림 제목
 * @param string $memo       알림 내용
 * @param string $url        알림 클릭 시 이동할 URL
 * @param string $file_url   알림에 표시할 이미지 URL
 * @param string $order_id   주문 ID
 * @param string $arg1       추가 파라미터 1
 * @param string $arg2       추가 파라미터 2 (팝업 표시 여부: '1' 또는 '0')
 * @param string $arg3       추가 파라미터 3 (발신자 정보)
 * 
 * @return bool 성공 여부
 */
function send_topic_push($topic, $title, $memo, $url, $file_url = '', $order_id = '', $arg1 = '$arg1', $arg2 = '1', $arg3 = 'MARS') {
    // URL 값 확인 및 기본 값 지정
    if ($url === null || $url == "" || $url == "http://" || $url == "https://") {
        $url = "https://edumars.net";
    }

    // FCM 서버 URL
    $fcm_url = 'https://fcm.googleapis.com/v1/projects/mars-38372/messages:send';

    // 서비스 계정 키 파일의 경로
    $serviceAccountPath = __DIR__ . '/mars-38372-firebase-adminsdk-60l5a-23f211e854.json';

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
                'arg1' => $arg1,
                'arg2' => $arg2, // 팝업 방식을 원하면 1 아니면 0
                'arg3' => $arg3, // 보낸 사람
                'url' => $url,
            ],
            'android' => [
                'priority' => 'high'
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
    if (!empty($file_url)) {
        $pushdata['message']['data']['file_url'] = $file_url;
    }

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
?> 