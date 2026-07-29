<?php
/**
 * PortOne (구 아임포트) 결제 시스템 설정
 *
 * 공식 문서: https://developers.portone.io/
 */

// PortOne credentials must be injected by the runtime, never committed.
define('PORTONE_API_KEY', (string)(getenv('PORTONE_API_KEY') ?: ''));
define('PORTONE_API_SECRET', (string)(getenv('PORTONE_API_SECRET') ?: ''));
define('PORTONE_IMP_CODE', (string)(getenv('PORTONE_IMP_CODE') ?: ''));

// API 엔드포인트
define('PORTONE_API_URL', 'https://api.iamport.kr');

// 결제 환경 (test: 테스트, production: 운영)
define('PORTONE_ENV', 'test');

// 광고 상품 정보
$AD_PRODUCTS = [
    'banner_1month' => [
        'name' => '배너 광고 1개월',
        'price' => 300000, // 30만원
        'duration_days' => 30,
        'position' => 'banner',
        'description' => '메인 페이지 상단 배너 광고 (1개월)',
    ],
    'banner_3months' => [
        'name' => '배너 광고 3개월',
        'price' => 800000, // 80만원 (10% 할인)
        'duration_days' => 90,
        'position' => 'banner',
        'description' => '메인 페이지 상단 배너 광고 (3개월)',
    ],
    'premium_1month' => [
        'name' => '프리미엄 광고 1개월',
        'price' => 500000, // 50만원
        'duration_days' => 30,
        'position' => 'premium',
        'description' => '검색 결과 최상단 노출 (1개월)',
    ],
    'premium_3months' => [
        'name' => '프리미엄 광고 3개월',
        'price' => 1350000, // 135만원 (10% 할인)
        'duration_days' => 90,
        'position' => 'premium',
        'description' => '검색 결과 최상단 노출 (3개월)',
    ],
];

/**
 * PortOne API 토큰 발급
 */
function getPortOneAccessToken() {
    if (PORTONE_API_KEY === '' || PORTONE_API_SECRET === '') {
        throw new RuntimeException('PortOne credentials are not configured');
    }
    $url = PORTONE_API_URL . '/users/getToken';
    $data = [
        'imp_key' => PORTONE_API_KEY,
        'imp_secret' => PORTONE_API_SECRET,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception('PortOne 토큰 발급 실패');
    }

    $result = json_decode($response, true);

    if ($result['code'] !== 0) {
        throw new Exception('PortOne 토큰 발급 실패: ' . ($result['message'] ?? 'Unknown error'));
    }

    return $result['response']['access_token'];
}

/**
 * 결제 정보 조회
 */
function getPaymentInfo($imp_uid) {
    try {
        $token = getPortOneAccessToken();
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }

    $url = PORTONE_API_URL . '/payments/' . $imp_uid;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($result['code'] !== 0) {
        return ['success' => false, 'message' => $result['message'] ?? 'Unknown error'];
    }

    return ['success' => true, 'payment' => $result['response']];
}

/**
 * 결제 취소/환불
 */
function cancelPayment($imp_uid, $merchant_uid, $amount, $reason) {
    try {
        $token = getPortOneAccessToken();
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }

    $url = PORTONE_API_URL . '/payments/cancel';
    $data = [
        'imp_uid' => $imp_uid,
        'merchant_uid' => $merchant_uid,
        'amount' => $amount,
        'reason' => $reason,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($result['code'] !== 0) {
        return ['success' => false, 'message' => $result['message'] ?? 'Unknown error'];
    }

    return ['success' => true, 'cancel' => $result['response']];
}

/**
 * 주문번호 생성
 */
function generateMerchantUid($prefix = 'AD') {
    return $prefix . '_' . date('YmdHis') . '_' . rand(1000, 9999);
}

/**
 * 광고 상품 정보 가져오기
 */
function getAdProduct($product_id) {
    global $AD_PRODUCTS;
    return $AD_PRODUCTS[$product_id] ?? null;
}

/**
 * 결제 로그 기록
 */
function logPayment($message, $level = 'INFO') {
    $log_dir = __DIR__ . '/logs/';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $log_file = $log_dir . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";

    file_put_contents($log_file, $log_entry, FILE_APPEND);
}
