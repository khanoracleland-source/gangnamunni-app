<?php
/**
 * Run in the local gangnamunni container. Uses and deletes ONLY a random test DB.
 *
 *   G5_MYSQL_HOST=127.0.0.1 G5_MYSQL_PORT=3306 G5_MYSQL_USER=root G5_MYSQL_PASSWORD=... \
 *   php tests/payments/points_hold_sync.php
 *
 * booking_payment_confirm() already refuses POINT_HOLD_EXPIRED before charging, but the
 * webhook / server-query path (booking_payment_sync -> booking_payment_apply_remote) had
 * no such guard: it marked the order paid and then called points_checkout_capture(),
 * whose UPDATE ... WHERE status='held' quietly changed zero rows when the hold was gone.
 * The result was an order paid at the point-discounted price with the points still in the
 * member's balance — the platform silently ate the discount. These tests pin that down,
 * and that a matching hold is still captured normally.
 *
 * Kept in its own file on purpose: points_settings() memoises the settings table in a
 * static, so the points schema has to exist before the first points call in the process.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$root = __DIR__ . '/../../hairwang_full/gangnamunni';
require_once $root . '/includes/booking_payment_service.php';
require_once $root . '/includes/points_checkout.php';

$count = 0;
function expect($condition, string $label): void {
    global $count;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $count++; echo "PASS: $label\n";
}
function rejects(callable $work, string $label): void {
    try { $work(); } catch (Throwable $e) { expect(true, $label); return; }
    expect(false, $label);
}

final class PointsFakeGateway implements BookingPaymentGateway {
    private $payment;
    public function __construct(callable $payment) { $this->payment = $payment; }
    public function provider(): string { return 'toss_test'; }
    public function payment(string $merchantUid): array { return ($this->payment)($merchantUid); }
    public function confirm(string $m, string $k, int $a): array { throw new RuntimeException('not used'); }
    public function cancel(string $m, string $t, int $a, int $r, string $re, string $rq): array { throw new RuntimeException('not used'); }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(getenv('G5_MYSQL_HOST') ?: '127.0.0.1', getenv('G5_MYSQL_USER') ?: 'root',
    getenv('G5_MYSQL_PASSWORD') ?: '', '', (int)(getenv('G5_MYSQL_PORT') ?: 3306));
$name = 'codex_points_hold_' . bin2hex(random_bytes(5));
$db->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");

try {
    $db->select_db($name);

    // -- Schema ---------------------------------------------------------
    $schema = file_get_contents($root . '/sql/platform_payments_schema.sql');
    $schema = substr($schema, strpos($schema, 'CREATE TABLE IF NOT EXISTS gangnamunni_platform_payments'));
    $schema .= file_get_contents($root . '/sql/booking_payment_previews.sql');
    $schema .= file_get_contents($root . '/sql/payment_attempts.sql');
    foreach (explode(';', $schema) as $sql) if (trim($sql) !== '') $db->query($sql);

    $db->query("CREATE TABLE gangnamunni_bookings (id BIGINT PRIMARY KEY, hospital_id BIGINT, hospital_name VARCHAR(255),
        mb_id VARCHAR(100), name VARCHAR(100), phone VARCHAR(30), treatment VARCHAR(255), price INT, status VARCHAR(30))");
    $db->query("INSERT INTO gangnamunni_bookings VALUES
        (1,1,'Fixture','fixture','Fixture','01000000000','Fixture treatment',100000,'confirmed'),
        (2,1,'Fixture','fixture','Fixture','01000000000','Fixture treatment',100000,'confirmed')");

    // Point v2 migration columns (the same ones the production migration adds).
    $has = function (string $table, string $column) use ($db): bool {
        return $db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'")->num_rows > 0;
    };
    if (!$has('gangnamunni_platform_payments', 'points_used')) {
        $db->query("ALTER TABLE gangnamunni_platform_payments ADD COLUMN points_used INT NOT NULL DEFAULT 0");
    }
    if (!$has('gangnamunni_platform_payments', 'gross_amount')) {
        $db->query("ALTER TABLE gangnamunni_platform_payments ADD COLUMN gross_amount INT NULL");
    }

    $db->query("CREATE TABLE gangnamunni_point_payment_holds (
        id INT AUTO_INCREMENT PRIMARY KEY, payment_id BIGINT UNSIGNED NOT NULL, booking_id BIGINT UNSIGNED NOT NULL,
        member_id INT NOT NULL, points INT NOT NULL,
        status ENUM('held','captured','released','restored') NOT NULL DEFAULT 'held',
        use_point_id INT DEFAULT NULL, restore_point_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_payment (payment_id, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS gangnamunni_point_settings (skey VARCHAR(64) PRIMARY KEY, sval VARCHAR(255))");
    // Must exist before the first points_* call -- points_settings() memoises, and
    // points_ensure_schema() refuses to run unless schema_version is current.
    $db->query("INSERT INTO gangnamunni_point_settings (skey, sval) VALUES
        ('schema_version','" . POINTS_SCHEMA_VERSION . "'), ('v2_schema_version','1')");
    expect(points_v2_schema_ready($db) === true, 'the points v2 schema registers as ready');

    // -- Fixtures -------------------------------------------------------
    $discount = function (int $bookingId, int $points) use ($db): string {
        $merchant = (string)platform_payment_create_booking($db, $bookingId)['merchant_uid'];
        $gross = (int)booking_payment_by_merchant($db, $merchant)['amount'];
        $net = $gross - $points;
        $stmt = $db->prepare("UPDATE gangnamunni_platform_payments
            SET gross_amount = ?, points_used = ?, amount = ? WHERE merchant_uid = ?");
        $stmt->bind_param('iiis', $gross, $points, $net, $merchant);
        $stmt->execute();
        return $merchant;
    };
    $paidAt = time() - 60;
    $gatewayFor = function (string $merchant, int $amount) use ($paidAt): PointsFakeGateway {
        return new PointsFakeGateway(fn($m) => ['payment_id' => $m, 'transaction_id' => 'tx_' . substr(md5($m), 0, 8),
            'status' => 'paid', 'raw_status' => 'DONE', 'amount' => $amount, 'cancelled_amount' => 0,
            'currency' => 'KRW', 'paid_at' => $paidAt]);
    };
    $holds = function (string $merchant) use ($db): array {
        $rows = [];
        $res = $db->query("SELECT h.points, h.status FROM gangnamunni_point_payment_holds h
            JOIN gangnamunni_platform_payments p ON p.id = h.payment_id
            WHERE p.merchant_uid = '" . $db->real_escape_string($merchant) . "' ORDER BY h.id");
        while ($row = $res->fetch_assoc()) $rows[] = $row['points'] . ':' . $row['status'];
        return $rows;
    };

    // -- 1. Discounted order with NO hold: the reported hole ------------
    $orphan = $discount(1, 1000);
    expect((int)booking_payment_by_merchant($db, $orphan)['amount'] === 99000, 'the order carries the discounted amount');
    expect($holds($orphan) === [], 'no point hold backs that discount');

    rejects(fn() => booking_payment_sync($db, $orphan, 'webhook', $gatewayFor($orphan, 99000)),
        'sync refuses to settle a discounted order whose point hold is gone');
    expect(booking_payment_by_merchant($db, $orphan)['status'] === 'pending',
        'the unbacked order stays pending instead of silently becoming paid');
    expect((int)booking_payment_by_merchant($db, $orphan)['points_used'] === 1000,
        'the rollback leaves the order untouched for review');

    // -- 2. Same order once a matching hold exists: normal path ---------
    $pid = (int)booking_payment_by_merchant($db, $orphan)['id'];
    $db->query("INSERT INTO gangnamunni_point_payment_holds (payment_id, booking_id, member_id, points, status)
        VALUES ({$pid}, 1, 7, 1000, 'held')");
    $synced = booking_payment_sync($db, $orphan, 'webhook', $gatewayFor($orphan, 99000));
    expect($synced['status'] === 'paid', 'a discounted order with a live hold settles normally');
    expect($holds($orphan) === ['1000:captured'], 'settling captures the hold');

    $again = booking_payment_sync($db, $orphan, 'webhook', $gatewayFor($orphan, 99000));
    expect($again['status'] === 'paid' && $holds($orphan) === ['1000:captured'], 'replaying the webhook is idempotent');

    // -- 3. Hold that does not cover the discount -----------------------
    $short = $discount(2, 1000);
    $pid2 = (int)booking_payment_by_merchant($db, $short)['id'];
    $db->query("INSERT INTO gangnamunni_point_payment_holds (payment_id, booking_id, member_id, points, status)
        VALUES ({$pid2}, 2, 7, 400, 'held')");
    rejects(fn() => booking_payment_sync($db, $short, 'webhook', $gatewayFor($short, 99000)),
        'a hold smaller than the discount is refused, not accepted as partial cover');
    expect(booking_payment_by_merchant($db, $short)['status'] === 'pending', 'the short-held order stays pending');

    // -- 4. Orders without points are unaffected ------------------------
    $db->query("INSERT INTO gangnamunni_bookings VALUES (3,1,'Fixture','fixture','Fixture','01000000000','Fixture treatment',100000,'confirmed')");
    $plain = (string)platform_payment_create_booking($db, 3)['merchant_uid'];
    $r = booking_payment_sync($db, $plain, 'webhook', $gatewayFor($plain, 100000));
    expect($r['status'] === 'paid', 'an order with no points_used settles with no hold at all');

    echo "\n{$count} checks passed\n";
} finally {
    $db->query("DROP DATABASE `$name`");
}
