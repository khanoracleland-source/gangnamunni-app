<?php
/**
 * Builds idempotent hospital settlement batches on the final calendar day.
 *
 * This script schedules liabilities; it does NOT claim a bank transfer happened.
 * A future payout adapter must move scheduled -> processing -> paid only after
 * server-to-server provider verification.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
date_default_timezone_set('Asia/Seoul');

$today = date('Y-m-d');
if ($today !== date('Y-m-t')) {
    fwrite(STDOUT, "Not month end; no settlement created.\n");
    exit(0);
}

$db = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$db) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}
mysqli_set_charset($db, 'utf8mb4');

$required = ['gangnamunni_platform_payments', 'gangnamunni_hospital_settlements', 'gangnamunni_hospital_settlement_items'];
foreach ($required as $table) {
    $safe = mysqli_real_escape_string($db, $table);
    $result = mysqli_query($db, "SHOW TABLES LIKE '{$safe}'");
    if (!$result || mysqli_num_rows($result) === 0) {
        fwrite(STDERR, "Missing settlement schema: {$table}\n");
        exit(1);
    }
}

$period_start = date('Y-m-01');
$period_end = $today;
$hospitals = mysqli_prepare($db,
    "SELECT DISTINCT hospital_id
     FROM gangnamunni_platform_payments
     WHERE payment_type='booking' AND funds_recipient='hospital'
       AND status IN ('paid','partially_refunded')
       AND paid_at >= ? AND paid_at < DATE_ADD(?, INTERVAL 1 DAY)");
mysqli_stmt_bind_param($hospitals, 'ss', $period_start, $period_end);
mysqli_stmt_execute($hospitals);
$hospital_rows = mysqli_stmt_get_result($hospitals);
$created = 0;

while ($row = mysqli_fetch_assoc($hospital_rows)) {
    $hospital_id = (int)$row['hospital_id'];
    mysqli_begin_transaction($db);
    try {
        $insert = mysqli_prepare($db,
            "INSERT INTO gangnamunni_hospital_settlements
                (hospital_id,period_start,period_end,scheduled_payout_date,status)
             VALUES (?,?,?,?, 'scheduled')
             ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
        mysqli_stmt_bind_param($insert, 'isss', $hospital_id, $period_start, $period_end, $period_end);
        if (!mysqli_stmt_execute($insert)) throw new RuntimeException(mysqli_stmt_error($insert));
        $settlement_id = mysqli_insert_id($db);
        mysqli_stmt_close($insert);

        $items = mysqli_prepare($db,
            "INSERT IGNORE INTO gangnamunni_hospital_settlement_items
                (settlement_id,source_type,source_id,amount,description,occurred_at)
             SELECT ?, 'booking_payment', p.id, (p.amount-p.refunded_amount),
                    CONCAT('Booking #',p.booking_id,' / ',p.treatment_name), p.paid_at
             FROM gangnamunni_platform_payments p
             WHERE p.hospital_id=? AND p.payment_type='booking' AND p.funds_recipient='hospital'
               AND p.status IN ('paid','partially_refunded')
               AND p.paid_at >= ? AND p.paid_at < DATE_ADD(?, INTERVAL 1 DAY)");
        mysqli_stmt_bind_param($items, 'iiss', $settlement_id, $hospital_id, $period_start, $period_end);
        if (!mysqli_stmt_execute($items)) throw new RuntimeException(mysqli_stmt_error($items));
        mysqli_stmt_close($items);

        $update = mysqli_prepare($db,
            "UPDATE gangnamunni_hospital_settlements s
             SET treatment_gross=(
                    SELECT COALESCE(SUM(p.amount),0) FROM gangnamunni_platform_payments p
                    WHERE p.hospital_id=s.hospital_id
                      AND p.payment_type='booking' AND p.funds_recipient='hospital'
                      AND p.status IN ('paid','partially_refunded')
                      AND p.paid_at>=s.period_start
                      AND p.paid_at<DATE_ADD(s.period_end,INTERVAL 1 DAY)
                 ),
                 refunds=(
                    SELECT COALESCE(SUM(p.refunded_amount),0) FROM gangnamunni_platform_payments p
                    WHERE p.hospital_id=s.hospital_id
                      AND p.payment_type='booking' AND p.funds_recipient='hospital'
                      AND p.status IN ('paid','partially_refunded')
                      AND p.paid_at>=s.period_start
                      AND p.paid_at<DATE_ADD(s.period_end,INTERVAL 1 DAY)
                 ),
                 payout_amount=GREATEST(0,treatment_gross-refunds-platform_fee+adjustments)
             WHERE s.id=? AND s.status IN ('draft','scheduled')");
        mysqli_stmt_bind_param($update, 'i', $settlement_id);
        if (!mysqli_stmt_execute($update)) throw new RuntimeException(mysqli_stmt_error($update));
        mysqli_stmt_close($update);

        mysqli_commit($db);
        $created++;
    } catch (Throwable $error) {
        mysqli_rollback($db);
        fwrite(STDERR, "Hospital {$hospital_id}: settlement failed.\n");
        exit(1);
    }
}
mysqli_stmt_close($hospitals);
mysqli_close($db);
fwrite(STDOUT, "Settlement batches ready: {$created}\n");
