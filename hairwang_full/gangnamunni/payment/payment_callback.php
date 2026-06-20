<?php
/**
 * 결제 콜백 처리
 */

require_once __DIR__ . '/../../dbconfig.php';
require_once __DIR__ . '/portone_config.php';

header('Content-Type: application/json; charset=utf-8');

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');

$imp_uid = $_POST['imp_uid'] ?? '';
$merchant_uid = $_POST['merchant_uid'] ?? '';

if (!$imp_uid || !$merchant_uid) {
    echo json_encode(['success' => false, 'message' => '필수 파라미터 누락']);
    exit;
}

// PortOne에서 결제 정보 조회
$payment_info = getPaymentInfo($imp_uid);

if (!$payment_info['success']) {
    echo json_encode(['success' => false, 'message' => '결제 정보 조회 실패']);
    exit;
}

$payment = $payment_info['payment'];

// DB에서 주문 정보 조회
$query = "SELECT * FROM gangnamunni_ad_payments WHERE merchant_uid = '$merchant_uid'";
$result = mysqli_query($connect, $query);
$order = mysqli_fetch_assoc($result);

if (!$order) {
    echo json_encode(['success' => false, 'message' => '주문 정보를 찾을 수 없습니다']);
    exit;
}

// 결제 금액 검증
if ($payment['amount'] != $order['amount']) {
    logPayment("결제 금액 불일치: 예상 {$order['amount']}원, 실제 {$payment['amount']}원", 'ERROR');
    echo json_encode(['success' => false, 'message' => '결제 금액이 일치하지 않습니다']);
    exit;
}

// 결제 상태 확인
if ($payment['status'] === 'paid') {
    // 결제 정보 업데이트
    $update_query = "UPDATE gangnamunni_ad_payments SET
        imp_uid = '$imp_uid',
        status = 'paid',
        paid_amount = {$payment['amount']},
        pg_provider = '{$payment['pg_provider']}',
        pg_tid = '{$payment['pg_tid']}',
        paid_at = NOW(),
        updated_at = NOW()
        WHERE merchant_uid = '$merchant_uid'";

    mysqli_query($connect, $update_query);

    // 광고 활성화 (기간 설정)
    $ad_id = $order['ad_id'];
    $product = getAdProduct($order['product_id']);
    $end_date = date('Y-m-d H:i:s', strtotime("+{$product['duration_days']} days"));

    $activate_query = "UPDATE gangnamunni_ads SET
        is_active = 1,
        paid_at = NOW(),
        start_date = NOW(),
        end_date = '$end_date',
        updated_at = NOW()
        WHERE id = $ad_id";

    mysqli_query($connect, $activate_query);

    logPayment("결제 완료: {$merchant_uid} ({$payment['amount']}원)");

    echo json_encode([
        'success' => true,
        'message' => '결제가 완료되었습니다',
        'ad_id' => $ad_id,
        'end_date' => $end_date,
    ]);
} else {
    logPayment("결제 상태 이상: {$payment['status']}", 'ERROR');
    echo json_encode(['success' => false, 'message' => '결제가 완료되지 않았습니다']);
}
