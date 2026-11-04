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
        } elseif (($_POST['action'] ?? '') === 'confirm_console') {
            // 이니시스 콘솔에서 취소를 직접 확인한 경우에만: PG 에 요청하지 않고 원장에 환불을 기록한다.
            booking_payment_refund_confirm_manual(
                $connect,
                $merchant,
                (string)($_POST['request_id'] ?? ''),
                (int)$_SESSION['ad_admin_id'],
                trim((string)($_POST['note'] ?? ''))
            );
        } else {
            throw new RuntimeException('Unsupported action.');
        }
        // PRG prevents browser refresh from repeating a financial action.
        header('Location: admin_booking_payments.php?updated=1');
        exit;
    } catch (BookingPaymentRejectedException $e) {
        error_log('[admin_booking_payments] rejected: ' . $e->getMessage());
        $code = preg_replace('/[^A-Z0-9_]/', '', $e->getMessage());
        $message = $code === 'PARTIAL_NOT_SUPPORTED'
            ? '부분 환불은 지원하지 않습니다. 남은 금액 전액을 입력해주세요.'
            : 'PG 가 환불을 거절했습니다 (' . $code . '). 이니시스 가맹점관리자에서 이미 취소된 건이면 "콘솔 취소 확인 반영"으로 원장에 기록하세요.';
    } catch (BookingPaymentGatewayUnavailableException $e) {
        $message = '결제 연동이 설정되지 않아 처리할 수 없습니다.';
    } catch (Throwable $e) {
        error_log('[admin_booking_payments] ' . $e->getMessage());
        $message = '처리를 확인하지 못했습니다. 상태를 조회한 뒤 확인해주세요. 중복 환불 요청은 차단됩니다.';
    }
}
$rows = $connect->query("SELECT p.*, b.status AS booking_status FROM gangnamunni_platform_payments p LEFT JOIN gangnamunni_bookings b ON b.id=p.booking_id WHERE p.payment_type='booking' ORDER BY p.id DESC LIMIT 100");
// 결과 미확정(pending)·거절(failed) 환불 요청: 콘솔 확인 후 반영 버튼을 띄운다.
$unresolved = [];
$ur = $connect->query("SELECT request_id, payment_id, amount, status, reason, created_at FROM gangnamunni_payment_refund_requests WHERE status IN ('pending','failed') ORDER BY created_at DESC");
while ($ur && ($u = $ur->fetch_assoc())) $unresolved[(int)$u['payment_id']][] = $u;
/** @param mixed $value */
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
                            <?php if (!$row['provider'] || booking_payment_is_pg($row['provider'])): ?>
                                <form method="post"><input type="hidden" name="csrf_token" value="<?= payment_admin_h($_SESSION['csrf_token']) ?>"><input type="hidden" name="merchant_uid" value="<?= payment_admin_h($row['merchant_uid']) ?>"><button name="action" value="sync">상태 조회</button></form>
                            <?php endif; ?>
                            <?php if (booking_payment_is_pg($row['provider']) && in_array($row['status'], ['paid', 'partially_refunded'], true)): ?>
                                <form method="post" onsubmit="return confirm('입력한 금액을 환불하시겠습니까?')">
                                    <input type="hidden" name="csrf_token" value="<?= payment_admin_h($_SESSION['csrf_token']) ?>"><input type="hidden" name="merchant_uid" value="<?= payment_admin_h($row['merchant_uid']) ?>"><input type="hidden" name="request_id" value="<?= bin2hex(random_bytes(32)) ?>">
                                    <input aria-label="환불 금액" name="amount" type="number" min="1" max="<?= (int)$row['amount'] - (int)$row['refunded_amount'] ?>" required placeholder="환불 금액">
                                    <input aria-label="환불 사유" name="reason" maxlength="150" required placeholder="환불 사유"><button name="action" value="refund">환불</button>
                                </form>
                            <?php endif; ?>
                            <?php foreach ($unresolved[(int)$row['id']] ?? [] as $u): if (!in_array($row['status'], ['paid', 'partially_refunded'], true)) continue; ?>
                                <div>
                                    <small>미확정 환불 요청 <?= payment_admin_h($u['status']) ?> · <?= number_format((int)$u['amount']) ?>원 · <?= payment_admin_h($u['created_at']) ?></small>
                                    <?php if ($u['status'] === 'pending'): ?>
                                        <!-- 같은 요청 ID 로 PG 에 다시 보낸다(booking_payment_refund 의 재시도 경로). 폼 값은 모두 서버가 준 것이며 JS 는 쓰지 않는다. -->
                                        <form method="post" onsubmit="return confirm('같은 요청 ID 로 PG 에 환불을 다시 보냅니다. 계속하시겠습니까?')">
                                            <input type="hidden" name="csrf_token" value="<?= payment_admin_h($_SESSION['csrf_token']) ?>"><input type="hidden" name="merchant_uid" value="<?= payment_admin_h($row['merchant_uid']) ?>"><input type="hidden" name="request_id" value="<?= payment_admin_h($u['request_id']) ?>">
                                            <input type="hidden" name="amount" value="<?= (int)$u['amount'] ?>"><input type="hidden" name="reason" value="<?= payment_admin_h($u['reason']) ?>">
                                            <button name="action" value="refund">다시 보내기</button>
                                        </form>
                                    <?php endif; ?>
                                    <!-- PG 에 요청하지 않고 원장에만 기록한다: 이니시스 콘솔에서 취소가 보일 때만. 이 경고는 다른 폼과 절대 공유하지 않는다. -->
                                    <form method="post" onsubmit="return confirm('이니시스 가맹점관리자에서 이 결제의 취소(Refund)가 확인되었습니까?\n확인된 경우에만 진행하세요. PG 에 요청을 보내지 않고 원장에만 환불로 기록합니다.')">
                                        <input type="hidden" name="csrf_token" value="<?= payment_admin_h($_SESSION['csrf_token']) ?>"><input type="hidden" name="merchant_uid" value="<?= payment_admin_h($row['merchant_uid']) ?>"><input type="hidden" name="request_id" value="<?= payment_admin_h($u['request_id']) ?>">
                                        <input aria-label="콘솔 확인 메모" name="note" maxlength="150" required placeholder="콘솔 확인 메모(취소일시 등)"><button name="action" value="confirm_console">콘솔 취소 확인 반영</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </td>
                    </tr><?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

</html>