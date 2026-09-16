<?php
require_once __DIR__ . '/booking_payment_config.php';
function booking_payment_json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function booking_payment_require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        booking_payment_json(['success' => false, 'message' => 'POST 요청만 허용됩니다.'], 405);
    }
    $token = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || !hash_equals($token, (string)($_POST['csrf_token'] ?? ''))) {
        booking_payment_json(['success' => false, 'message' => '페이지를 새로고침해주세요.'], 403);
    }
}
function booking_payment_owned(mysqli $db, int $id): ?array {
    global $is_member, $member;
    $member_id = !empty($is_member) ? (string)($member['mb_id'] ?? '') : '';
    $phone = $member_id !== '' ? ($member['mb_hp'] ?? $_SESSION['user_phone'] ?? '') : ($_SESSION['user_phone'] ?? '');
    $phone = preg_replace('/[^0-9]/', '', (string)$phone);
    if ($id <= 0 || ($member_id === '' && $phone === '')) return null;
    $stmt = $db->prepare("SELECT * FROM gangnamunni_bookings WHERE id=? AND
        ((?<>'' AND mb_id=?) OR ((mb_id IS NULL OR mb_id='') AND ?<>'' AND REPLACE(REPLACE(phone,'-',''),' ','')=?)) LIMIT 1");
    $stmt->bind_param('issss', $id, $member_id, $member_id, $phone, $phone);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
function booking_payment_order(mysqli $db, int $id): ?array {
    $stmt = $db->prepare("SELECT * FROM gangnamunni_platform_payments WHERE payment_type='booking' AND booking_id=? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
function booking_payment_previews(mysqli $db, array $ids): array {
    if (!$ids) return [];
    $exists = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gangnamunni_booking_payment_previews'");
    if (!$exists || !$exists->num_rows) return [];
    $ids = implode(',', array_map('intval', $ids));
    $rows = $db->query("SELECT booking_id,created_at FROM gangnamunni_booking_payment_previews WHERE booking_id IN ($ids)");
    $map = [];
    while ($row = $rows->fetch_assoc()) $map[(int)$row['booking_id']] = $row;
    return $map;
}

function booking_has_real_payment(mysqli $db, int $id): bool {
    $order = booking_payment_order($db, $id);
    return $order && $order['provider'] === 'portone' && $order['status'] === 'paid';
}

function booking_payment_record_preview(mysqli $db, int $id): void {
    if (!booking_payment_preview_enabled()) throw new RuntimeException('Preview disabled.');
    $db->begin_transaction();
    try {
        $stmt = $db->prepare("SELECT status FROM gangnamunni_bookings WHERE id=? FOR UPDATE");
        $stmt->bind_param('i', $id); $stmt->execute();
        if (($stmt->get_result()->fetch_assoc()['status'] ?? '') !== 'confirmed') throw new RuntimeException('Booking is no longer confirmed.');
        $order = booking_payment_order($db, $id);
        if (!$order || $order['status'] !== 'pending') throw new RuntimeException('Order is no longer pending.');
        $stmt = $db->prepare("INSERT INTO gangnamunni_booking_payment_previews (booking_id) VALUES (?) ON DUPLICATE KEY UPDATE booking_id=booking_id");
        $stmt->bind_param('i', $id); $stmt->execute();
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}
