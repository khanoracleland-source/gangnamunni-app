<?php
session_start();
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/booking_payment_service.php';
// Financial mutations are platform owner operations; no hospital-ID from the request grants access.
if (!check_admin_login() || ($_SESSION['ad_admin_role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    exit('접근 권한이 없습니다.');
}
header('Cache-Control: no-store');
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    booking_payment_require_post();
    try {
        $merchant = (string)($_POST['merchant_uid'] ?? '');
        if (($_POST['action'] ?? '') === 'sync') {
            $order = booking_payment_by_merchant($connect, $merchant);
            booking_payment_sync($connect, $merchant);
        } elseif (($_POST['action'] ?? '') === 'refund') {
            booking_payment_refund(
                $connect,
                $merchant,
                (int)($_POST['amount'] ?? 0),
                trim((string)($_POST['reason'] ?? '')),
                (string)($_POST['request_id'] ?? ''),
                (int)$_SESSION['ad_admin_id']
            );
        } else {
            throw new RuntimeException('Unsupported action.');
        }
        // PRG prevents browser refresh from repeating a financial action.
        header('Location: admin_booking_payments.php?updated=1');
        exit;
    } catch (Throwable $e) {
        error_log('[admin_booking_payments] ' . $e->getMessage());
        $message = '처리를 확인하지 못했습니다. 상태를 조회한 뒤 확인해주세요. 중복 환불 요청은 차단됩니다.';
    }
}
$rows = $connect->query("SELECT p.*, b.status AS booking_status FROM gangnamunni_platform_payments p LEFT JOIN gangnamunni_bookings b ON b.id=p.booking_id WHERE p.payment_type='booking' ORDER BY p.id DESC LIMIT 100");
function payment_admin_h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ko">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>예약 결제 관리</title>
<style>
    body {
        font: 15px system-ui;
        margin: 24px;
        color: #242424
    }

    main {
        max-width: 1200px;
        margin: auto
    }

    table {
        width: 100%;
        border-collapse: collapse
    }

    td,
    th {
        padding: 12px;
        border-bottom: 1px solid #ddd;
        text-align: left
    }

    input,
    button {
        padding: 8px;
        margin: 3px
    }

    form {
        display: flex;
        flex-wrap: wrap
    }

    small {
        display: block;
        color: #666
    }

    .scroll {
        overflow: auto
    }
</style>
<main><a href="admin_booking_revenue.php">예약 매출</a>
    <h1>예약 결제 관리</h1>
    <p>실제 결제와 테스트 결제를 구분하여 조회하고, 제공자에서 확인된 금액만 환불합니다.</p>
    <?php if ($message): ?><p role="alert"><?= payment_admin_h($message) ?></p><?php endif; ?>
    <?php if (isset($_GET['updated'])): ?><p role="status">처리 결과가 갱신되었습니다.</p><?php endif; ?>
    <div class="scroll">
        <table>
            <thead>
                <tr>
                    <th>예약</th>
                    <th>구분 / 상태</th>
                    <th>결제 / 환불</th>
                    <th>처리</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= (int)$row['booking_id'] ?><small><?= payment_admin_h($row['merchant_uid']) ?></small><small>예약: <?= payment_admin_h($row['booking_status']) ?></small></td>
                        <td><?= payment_admin_h($row['provider'] ?: '결제 대기') ?> / <?= payment_admin_h($row['status']) ?></td>
                        <td><?= number_format($row['amount']) ?> / <?= number_format($row['refunded_amount']) ?>원</td>
                        <td>
                            <?php if (!$row['provider'] || in_array($row['provider'], ['portone', 'portone_test'], true)): ?>
                                <form method="post"><input type="hidden" name="csrf_token" value="<?= payment_admin_h($_SESSION['csrf_token']) ?>"><input type="hidden" name="merchant_uid" value="<?= payment_admin_h($row['merchant_uid']) ?>"><button name="action" value="sync">상태 조회</button></form>
                            <?php endif; ?>
                            <?php if (in_array($row['provider'], ['portone', 'portone_test'], true) && in_array($row['status'], ['paid', 'partially_refunded'], true)): ?>
                                <form method="post" onsubmit="return confirm('입력한 금액을 환불하시겠습니까?')">
                                    <input type="hidden" name="csrf_token" value="<?= payment_admin_h($_SESSION['csrf_token']) ?>"><input type="hidden" name="merchant_uid" value="<?= payment_admin_h($row['merchant_uid']) ?>"><input type="hidden" name="request_id" value="<?= bin2hex(random_bytes(32)) ?>">
                                    <input aria-label="환불 금액" name="amount" type="number" min="1" max="<?= (int)$row['amount'] - (int)$row['refunded_amount'] ?>" required placeholder="환불 금액">
                                    <input aria-label="환불 사유" name="reason" maxlength="150" required placeholder="환불 사유"><button name="action" value="refund">환불</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr><?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

</html>