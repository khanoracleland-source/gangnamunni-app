<?php
/**
 * Builds idempotent hospital settlement batches for the previous closed month.
 *
 * This script schedules liabilities; it does NOT claim a bank transfer happened.
 * A future payout adapter must move scheduled -> processing -> paid only after
 * server-to-server provider verification.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/includes/booking_payment_db_config.php';
date_default_timezone_set('Asia/Seoul');

$today = date('Y-m-d');
$is_month_end = ($today === date('Y-m-t'));
$target_time = strtotime('last day of previous month'); // Never close a month while it is still accepting payments.
$period_start = date('Y-m-01', $target_time);
$period_end = date('Y-m-t', $target_time);

$db = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$db) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}
mysqli_set_charset($db, 'utf8mb4');
$lock = mysqli_query($db, "SELECT GET_LOCK('seoul_beauty_monthly_settlement',0) AS acquired");
$lock_row = $lock ? mysqli_fetch_assoc($lock) : null;
if ((int)($lock_row['acquired'] ?? 0) !== 1) {
    fwrite(STDERR, "Another settlement process is running.\n");
    exit(1);
}

$required = ['gangnamunni_platform_payments', 'gangnamunni_payment_events', 'gangnamunni_hospital_settlements', 'gangnamunni_hospital_settlement_items'];
foreach ($required as $table) {
    $safe = mysqli_real_escape_string($db, $table);
    $result = mysqli_query($db, "SHOW TABLES LIKE '{$safe}'");
    if (!$result || mysqli_num_rows($result) === 0) {
        fwrite(STDERR, "Missing settlement schema: {$table}\n");
        exit(1);
    }
}

$hospitals = mysqli_prepare($db,
    "SELECT DISTINCT hospital_id FROM (
        SELECT p.hospital_id
        FROM gangnamunni_platform_payments p
        WHERE p.payment_type='booking' AND p.funds_recipient='hospital' AND p.provider NOT IN ('mock_booking_payment','portone_test')
          AND p.status IN ('paid','partially_refunded','refunded')
          AND p.paid_at>=? AND p.paid_at<DATE_ADD(?,INTERVAL 1 DAY)
        UNION
        SELECT p.hospital_id
        FROM gangnamunni_payment_events e
        JOIN gangnamunni_platform_payments p ON p.id=e.payment_id
        WHERE e.event_type='refund_verified' AND p.provider NOT IN ('mock_booking_payment','portone_test')
          AND e.received_at>=? AND e.received_at<DATE_ADD(?,INTERVAL 1 DAY)
        UNION
        SELECT hospital_id FROM gangnamunni_hospital_settlements
        WHERE period_end=DATE_SUB(?,INTERVAL 1 DAY)
          AND CAST(treatment_gross AS SIGNED)-CAST(refunds AS SIGNED)-CAST(platform_fee AS SIGNED)+adjustments+carry_forward_amount<0
    ) eligible");
mysqli_stmt_bind_param($hospitals, 'sssss', $period_start, $period_end, $period_start, $period_end, $period_start);
mysqli_stmt_execute($hospitals);
$hospital_rows = mysqli_stmt_get_result($hospitals);
$created = 0;

while ($row = mysqli_fetch_assoc($hospital_rows)) {
    $hospital_id = (int)$row['hospital_id'];
    mysqli_begin_transaction($db);
    try {
        $payout_key = hash('sha256', 'hospital-settlement|' . $hospital_id . '|' . $period_start . '|' . $period_end . '|KRW');
        $insert = mysqli_prepare($db,
            "INSERT INTO gangnamunni_hospital_settlements
                (hospital_id,period_start,period_end,scheduled_payout_date,payout_idempotency_key,status)
             VALUES (?,?,?,?,?, 'scheduled')
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        mysqli_stmt_bind_param($insert, 'issss', $hospital_id, $period_start, $period_end, $period_end, $payout_key);
        if (!mysqli_stmt_execute($insert)) throw new RuntimeException(mysqli_stmt_error($insert));
        $settlement_id = mysqli_insert_id($db);
        mysqli_stmt_close($insert);

        $batch = $db->query("SELECT status FROM gangnamunni_hospital_settlements WHERE id=" . (int)$settlement_id . " FOR UPDATE")->fetch_assoc();
        if (!in_array($batch['status'], ['draft','scheduled'], true)) {
            mysqli_commit($db);
            continue; // A closed/approved batch and its items are immutable.
        }

        $tainted = $db->query("SELECT 1 FROM gangnamunni_hospital_settlement_items i
            JOIN gangnamunni_platform_payments p ON i.source_type='booking_payment' AND i.source_id=p.id
            WHERE i.settlement_id=" . (int)$settlement_id . " AND p.provider IN ('mock_booking_payment','portone_test') LIMIT 1");
        if ($tainted->num_rows) {
            $db->query("UPDATE gangnamunni_hospital_settlements SET status='held',failure_reason='Historical test payments require review' WHERE id=" . (int)$settlement_id);
            mysqli_commit($db);
            continue;
        }

        $items = mysqli_prepare($db,
            "INSERT INTO gangnamunni_hospital_settlement_items
                (settlement_id,source_type,source_id,amount,description,occurred_at)
             SELECT ?, 'booking_payment', p.id, p.amount,
                    CONCAT('Booking #',p.booking_id,' / ',p.treatment_name), p.paid_at
             FROM gangnamunni_platform_payments p
             WHERE p.hospital_id=? AND p.payment_type='booking' AND p.funds_recipient='hospital' AND p.provider NOT IN ('mock_booking_payment','portone_test')
               AND p.status IN ('paid','partially_refunded','refunded')
               AND p.paid_at >= ? AND p.paid_at < DATE_ADD(?, INTERVAL 1 DAY)
             ON DUPLICATE KEY UPDATE id=gangnamunni_hospital_settlement_items.id");
        mysqli_stmt_bind_param($items, 'iiss', $settlement_id, $hospital_id, $period_start, $period_end);
        if (!mysqli_stmt_execute($items)) throw new RuntimeException(mysqli_stmt_error($items));
        mysqli_stmt_close($items);

        $refunds = mysqli_prepare($db,
            "INSERT INTO gangnamunni_hospital_settlement_items
                (settlement_id,source_type,source_id,amount,description,occurred_at)
             SELECT ?, 'refund', e.id, -CAST(e.amount AS SIGNED),
                    CONCAT('Refund for payment #',p.id),e.received_at
             FROM gangnamunni_payment_events e
             JOIN gangnamunni_platform_payments p ON p.id=e.payment_id
             WHERE p.hospital_id=? AND e.event_type='refund_verified' AND p.provider NOT IN ('mock_booking_payment','portone_test')
               AND e.received_at>=? AND e.received_at<DATE_ADD(?,INTERVAL 1 DAY)
             ON DUPLICATE KEY UPDATE id=gangnamunni_hospital_settlement_items.id");
        mysqli_stmt_bind_param($refunds, 'iiss', $settlement_id, $hospital_id, $period_start, $period_end);
        if (!mysqli_stmt_execute($refunds)) throw new RuntimeException(mysqli_stmt_error($refunds));
        mysqli_stmt_close($refunds);

        $update = mysqli_prepare($db,
            "UPDATE gangnamunni_hospital_settlements s
             SET treatment_gross=(
                    SELECT COALESCE(SUM(i.amount),0)
                    FROM gangnamunni_hospital_settlement_items i
                    WHERE i.settlement_id=s.id AND i.source_type='booking_payment'
                 ),
                 refunds=(
                    SELECT COALESCE(ABS(SUM(i.amount)),0)
                    FROM gangnamunni_hospital_settlement_items i
                    WHERE i.settlement_id=s.id AND i.source_type='refund'
                 )
             WHERE s.id=? AND s.status IN ('draft','scheduled')");
        mysqli_stmt_bind_param($update, 'i', $settlement_id);
        if (!mysqli_stmt_execute($update)) throw new RuntimeException(mysqli_stmt_error($update));
        mysqli_stmt_close($update);
        // Carry only negative liabilities; positive prior payouts belong to their own batch.
        $carry = $db->prepare("SELECT LEAST(0,CAST(treatment_gross AS SIGNED)-CAST(refunds AS SIGNED)-CAST(platform_fee AS SIGNED)+adjustments+carry_forward_amount) AS balance
            FROM gangnamunni_hospital_settlements WHERE hospital_id=? AND period_end=DATE_SUB(?, INTERVAL 1 DAY)");
        $carry->bind_param('is', $hospital_id, $period_start);
        $carry->execute();
        $carry_amount = (int)($carry->get_result()->fetch_assoc()['balance'] ?? 0);
        $carry_update = $db->prepare("UPDATE gangnamunni_hospital_settlements SET carry_forward_amount=? WHERE id=?");
        $carry_update->bind_param('ii', $carry_amount, $settlement_id);
        $carry_update->execute();
        $payout = mysqli_prepare($db,
            "UPDATE gangnamunni_hospital_settlements
             SET payout_amount=GREATEST(0,CAST(treatment_gross AS SIGNED)-CAST(refunds AS SIGNED)-CAST(platform_fee AS SIGNED)+adjustments+carry_forward_amount)
             WHERE id=? AND status IN ('draft','scheduled')");
        mysqli_stmt_bind_param($payout, 'i', $settlement_id);
        if (!mysqli_stmt_execute($payout)) throw new RuntimeException(mysqli_stmt_error($payout));
        mysqli_stmt_close($payout);

        mysqli_commit($db);
        $created++;
    } catch (Throwable $error) {
        mysqli_rollback($db);
        fwrite(STDERR, "Hospital {$hospital_id}: settlement failed: " . $error->getMessage() . "\n");
        exit(1);
    }
}
mysqli_stmt_close($hospitals);
mysqli_query($db, "SELECT RELEASE_LOCK('seoul_beauty_monthly_settlement')");
mysqli_close($db);
fwrite(STDOUT, "Settlement batches ready: {$created}\n");
