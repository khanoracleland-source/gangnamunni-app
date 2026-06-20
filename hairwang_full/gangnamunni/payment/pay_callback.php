<?php
session_start();
require_once dirname(__DIR__) . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');

// 영카트 결제 완료 후 콜백 처리
// 실제 PG 연동 시 이니시스/KCP 등에서 호출
$od_id = $_GET['od_id'] ?? $_POST['od_id'] ?? '';
$result_code = $_GET['resultCode'] ?? $_POST['resultCode'] ?? '0000';

header('Content-Type: application/json; charset=utf-8');

if (empty($od_id)) {
    echo json_encode(['success' => false, 'message' => '주문번호 없음']);
    exit;
}

$od_id_esc = mysqli_real_escape_string($connect, $od_id);
$order = null;
$r = mysqli_query($connect, "SELECT * FROM g5_shop_order WHERE od_id = '{$od_id_esc}'");
if ($r) $order = mysqli_fetch_assoc($r);

if (!$order) {
    echo json_encode(['success' => false, 'message' => '주문을 찾을 수 없음']);
    exit;
}

if ($result_code === '0000') {
    // 결제 성공
    mysqli_query($connect, "UPDATE g5_shop_order SET od_status = '완료' WHERE od_id = '{$od_id_esc}'");
    mysqli_query($connect, "UPDATE g5_shop_cart SET ct_status = '완료' WHERE od_id = '{$od_id_esc}'");

    echo json_encode([
        'success' => true,
        'message' => '결제 완료',
        'od_id' => $od_id,
        'amount' => $order['od_receipt_price']
    ]);
} else {
    mysqli_query($connect, "UPDATE g5_shop_order SET od_status = '취소' WHERE od_id = '{$od_id_esc}'");
    echo json_encode(['success' => false, 'message' => '결제 실패', 'code' => $result_code]);
}
?>
