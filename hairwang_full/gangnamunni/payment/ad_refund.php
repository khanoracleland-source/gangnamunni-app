<?php
/**
 * 광고 환불 처리
 * PortOne API를 통한 결제 취소
 */

session_start();

require_once __DIR__ . '/portone_config.php';
require_once __DIR__ . '/../../dbconfig.php';

// DB 연결
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');

// 로그인 체크
$is_logged_in = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
if (!$is_logged_in) {
    die(json_encode(['success' => false, 'message' => '로그인이 필요합니다']));
}

// 광고 ID 확인
$ad_id = intval($_GET['ad_id'] ?? 0);
if ($ad_id <= 0) {
    die(json_encode(['success' => false, 'message' => '잘못된 광고 ID입니다']));
}

// 결제 정보 조회
$query = "SELECT * FROM gangnamunni_ad_payments WHERE ad_id = {$ad_id} AND status = 'paid' ORDER BY id DESC LIMIT 1";
$result = mysqli_query($connect, $query);
$payment = mysqli_fetch_assoc($result);

if (!$payment) {
    die(json_encode(['success' => false, 'message' => '결제 정보를 찾을 수 없습니다']));
}

// 환불 사유 (기본값)
$refund_reason = $_POST['refund_reason'] ?? '사용자 요청에 의한 환불';

try {
    // PortOne API를 통한 환불 처리
    $cancel_result = cancelPayment($payment['imp_uid'], $refund_reason);

    if (!$cancel_result) {
        throw new Exception('PortOne API 환불 요청 실패');
    }

    // 환불 성공 - DB 업데이트
    mysqli_begin_transaction($connect);

    try {
        // 1. 결제 내역 취소 상태로 변경
        $update_payment_query = "
            UPDATE gangnamunni_ad_payments
            SET status = 'cancelled',
                cancel_reason = '" . mysqli_real_escape_string($connect, $refund_reason) . "',
                cancelled_at = NOW()
            WHERE id = {$payment['id']}
        ";
        mysqli_query($connect, $update_payment_query);

        // 2. 광고 비활성화 및 결제 정보 초기화
        $update_ad_query = "
            UPDATE gangnamunni_ads
            SET is_active = 0,
                paid_at = NULL,
                start_date = NULL,
                end_date = NULL
            WHERE id = {$ad_id}
        ";
        mysqli_query($connect, $update_ad_query);

        mysqli_commit($connect);

        // 환불 로그 기록
        $log_file = __DIR__ . '/logs/refund_' . date('Y-m-d') . '.log';
        $log_dir = dirname($log_file);
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $log_message = sprintf(
            "[%s] 환불 완료 - 광고ID: %d, 결제ID: %d, imp_uid: %s, 금액: %d, 사유: %s\n",
            date('Y-m-d H:i:s'),
            $ad_id,
            $payment['id'],
            $payment['imp_uid'],
            $payment['paid_amount'],
            $refund_reason
        );
        file_put_contents($log_file, $log_message, FILE_APPEND);

        // 성공 응답
        header('Location: ../admin_ads.php?refund_success=1');
        exit;

    } catch (Exception $e) {
        mysqli_rollback($connect);
        throw $e;
    }

} catch (Exception $e) {
    // 오류 로그
    $error_log_file = __DIR__ . '/logs/refund_error_' . date('Y-m-d') . '.log';
    $error_log_dir = dirname($error_log_file);
    if (!file_exists($error_log_dir)) {
        mkdir($error_log_dir, 0755, true);
    }

    $error_message = sprintf(
        "[%s] 환불 실패 - 광고ID: %d, imp_uid: %s, 오류: %s\n",
        date('Y-m-d H:i:s'),
        $ad_id,
        $payment['imp_uid'],
        $e->getMessage()
    );
    file_put_contents($error_log_file, $error_message, FILE_APPEND);

    // 실패 응답
    header('Location: ../admin_ads.php?refund_error=' . urlencode($e->getMessage()));
    exit;
}

mysqli_close($connect);
?>
