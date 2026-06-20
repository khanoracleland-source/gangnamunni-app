<?php
include_once('../../common.php');

// FCM 신규버전 대응 함수
function sendPushNotification($tokens, $title, $body, $jsonKeyFilePath) {
    
    global $g5;

    $app = sql_fetch("SELECT * FROM rb_app");
    
    $accessToken = getAccessToken($jsonKeyFilePath);
    $successCount = 0;
    $failedTokens = [];

    foreach ($tokens as $token) {
        $data = [
            "message" => [
                "token" => $token,
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ]
            ],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/v1/projects/' . $app['ap_pid'] . '/messages:send'); // 프로젝트ID 외부화
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ]);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        } else {
            $response = json_decode($result, true);
            if (isset($response['name'])) {
                $successCount++;
            } else {
                echo "FCM Response Error: " . json_encode($response) . "\n";
                if (isset($response['error']['details'][0]['errorCode']) && $response['error']['details'][0]['errorCode'] == 'UNREGISTERED') {
                    $failedTokens[] = $token;
                }
            }
        }
        curl_close($ch);
    }

    // 유효하지 않은 토큰을 데이터베이스에서 제거
    /*
    if (!empty($failedTokens)) {
        echo "유효하지 않은 토큰: " . json_encode($failedTokens) . "\n";
        foreach ($failedTokens as $failedToken) {
            $sql = "DELETE FROM {$g5['member_table']} WHERE mb_10 = '" . trim($failedToken) . "'";
            sql_query($sql);
        }
    }

    echo "$successCount 명에게 푸시알림을 전송 했습니다.";
    */
}



// POST 데이터 받기
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 데이터 검증
if (!isset($data['tokens'], $data['title'], $data['body'], $data['jsonKeyFilePath'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

// 푸시 알림 보내기
sendPushNotification($data['tokens'], $data['title'], $data['body'], $data['jsonKeyFilePath']);

?>
