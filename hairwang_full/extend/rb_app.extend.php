<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

add_replace('admin_menu', 'add_admin_bbs_menu_app', 1, 1); // 관리자 메뉴를 추가함

function add_admin_bbs_menu_app($admin_menu){ // 메뉴추가
    $admin_menu['menu000'][] = array(
        '000600', '앱 관리', G5_ADMIN_URL.'/rb/app_form.php', 'rb_config'
    );
    return $admin_menu;
}

$app = sql_fetch (" select * from rb_app "); // 앱관리 테이블 조회

// FCM 신규버전 대응 함수
function getAccessToken($jsonKeyFilePath) {
    $jwt = generateJWT($jsonKeyFilePath);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]));

    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
    $response = json_decode($result, true);
    if (isset($response['error'])) {
        throw new Exception('Error: ' . $response['error_description']);
    }
    return $response['access_token'];
}

// FCM 신규버전 대응 함수
function generateJWT($jsonKeyFilePath) {
    $now = time();
    $key = json_decode(file_get_contents($jsonKeyFilePath), true);

    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT',
    ];

    $payload = [
        'iss' => $key['client_email'],
        'sub' => $key['client_email'],
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    ];

    $base64UrlHeader = base64UrlEncode(json_encode($header));
    $base64UrlPayload = base64UrlEncode(json_encode($payload));
    openssl_sign($base64UrlHeader . '.' . $base64UrlPayload, $signature, $key['private_key'], OPENSSL_ALGO_SHA256);
    $base64UrlSignature = base64UrlEncode($signature);

    return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
}

// FCM 신규버전 대응 함수
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}