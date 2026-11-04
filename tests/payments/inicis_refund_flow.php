<?php
/**
 * Inicis refund paths through the real ledger (random test DB, fake HTTP transport, real InicisGateway):
 *  - unknown outcome stays pending, retry with the SAME request id re-sends the refund (ledger-backed gateway)
 *  - retry rejected -> failed; retry unknown again -> still pending; a NEW request id is refused while one is pending
 *  - partial amount is refused before any call
 *  - booking_payment_refund_confirm_manual(): records the console-confirmed refund once, booking/points follow, idempotent
 * Run in the local gangnamunni container: php tests/payments/inicis_refund_flow.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/booking_payment_service.php';
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/booking_payment_inicis.php';
$count = 0;
function expect($condition, string $label): void { global $count; if (!$condition) throw new RuntimeException('FAIL: ' . $label); $count++; echo "PASS: $label\n"; }
function rejects(callable $work, string $label, string $class = Throwable::class): void {
    try { $work(); } catch (Throwable $e) {
        if (!($e instanceof $class)) throw new RuntimeException("FAIL: $label (wrong exception " . get_class($e) . ': ' . $e->getMessage() . ')');
        expect(true, $label); return;
    }
    expect(false, $label);
}
expect(booking_payment_gateway() === null, 'no Inicis settings in this environment (factory returns null)');
expect(booking_payment_is_pg('inicis') && booking_payment_is_pg('inicis_test') && booking_payment_is_test('inicis_test') && !booking_payment_is_test('inicis'), 'provider lists: inicis live, inicis_test sandbox');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(getenv('G5_MYSQL_HOST') ?: '127.0.0.1', getenv('G5_MYSQL_USER') ?: 'root', getenv('G5_MYSQL_PASSWORD') ?: '', '', (int)(getenv('G5_MYSQL_PORT') ?: 3306));
$name = 'codex_inicis_refund_' . bin2hex(random_bytes(5));
$db->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
try {
    $db->select_db($name);
    $db->query("SET time_zone='+09:00'");
    $schema = file_get_contents(__DIR__ . '/../../hairwang_full/gangnamunni/sql/platform_payments_schema.sql');
    $schema = substr($schema, strpos($schema, 'CREATE TABLE IF NOT EXISTS gangnamunni_platform_payments'));
    $schema .= file_get_contents(__DIR__ . '/../../hairwang_full/gangnamunni/sql/booking_payment_previews.sql');
    $schema .= file_get_contents(__DIR__ . '/../../hairwang_full/gangnamunni/sql/payment_attempts.sql');
    foreach (explode(';', $schema) as $sql) if (trim($sql) !== '') $db->query($sql);
    $db->query("CREATE TABLE gangnamunni_bookings (id BIGINT PRIMARY KEY, hospital_id BIGINT, hospital_name VARCHAR(255), mb_id VARCHAR(100), name VARCHAR(100), phone VARCHAR(30),
        treatment VARCHAR(255), price INT, status VARCHAR(30), refund_status VARCHAR(20) NOT NULL DEFAULT 'not_applicable', updated_at DATETIME NULL)");
    foreach ([1, 2, 3, 4] as $b) $db->query("INSERT INTO gangnamunni_bookings (id,hospital_id,hospital_name,mb_id,name,phone,treatment,price,status) VALUES ($b,1,'Fixture Hospital','fixture','Fixture','01000000000','Fixture treatment',10000,'cancelled')");

    // Fake Inicis: programmable reply; counts calls; remembers forms.
    $reply = null; $calls = [];
    $transport = function (string $url, array $form, int $t) use (&$reply, &$calls) {
        $calls[] = $form;
        if ($reply instanceof Throwable) throw $reply;
        return $reply;
    };
    $ok = ['status' => 200, 'body' => json_encode(['resultCode' => '00', 'resultMsg' => 'ok', 'cancelDate' => '20261104', 'cancelTime' => '160000'])];
    $gw = new InicisGateway('INIpayTest', 'apikey-fixture', 'test', '10.0.0.5', booking_payment_inicis_ledger_reader($db), $transport);
    expect($gw->provider() === 'inicis_test' && !$gw->queriesProvider(), 'test gateway is ledger-backed');

    // A paid order the way the Nest API writes it.
    $paidOrder = function (int $bookingId, string $tid) use ($db): string {
        $m = platform_payment_create_booking($db, $bookingId)['merchant_uid'];
        platform_payment_mark_verified($db, $m, 'inicis_test', $tid, 'paid:' . $tid, 10000, hash('sha256', $tid), 'server_query', time() - 3600);
        $o = booking_payment_by_merchant($db, $m);
        expect($o['status'] === 'paid' && (int)$o['amount'] === 10000, "order for booking #$bookingId is paid");
        return $m;
    };
    $events = fn(string $type) => (int)$db->query("SELECT COUNT(*) AS n FROM gangnamunni_payment_events WHERE event_type='$type'")->fetch_assoc()['n'];
    $request = fn(string $id) => $db->query("SELECT status FROM gangnamunni_payment_refund_requests WHERE request_id='$id'")->fetch_assoc()['status'] ?? null;
    $booking = fn(int $id) => $db->query("SELECT status, refund_status FROM gangnamunni_bookings WHERE id=$id")->fetch_assoc();

    // ---- 1. unknown outcome -> pending; retry with the same id re-sends and succeeds
    $m1 = $paidOrder(1, 'TID1'); $r1 = str_repeat('1', 64);
    $reply = new RuntimeException('timeout');
    rejects(fn() => booking_payment_refund($db, $m1, 10000, '예약 취소 환불 승인 (예약 #1)', $r1, 7, $gw), 'unknown HTTP outcome is not a success', RuntimeException::class);
    expect($request($r1) === 'pending' && count($calls) === 1 && booking_payment_by_merchant($db, $m1)['status'] === 'paid', 'request stays pending, ledger untouched');
    rejects(fn() => booking_payment_refund($db, $m1, 10000, 'another id', str_repeat('9', 64), 7, $gw), 'a NEW request id is refused while one is pending', RuntimeException::class);
    expect(count($calls) === 1, 'the refused new request never reaches Inicis');
    $reply = $ok;
    $done = booking_payment_refund($db, $m1, 10000, '예약 취소 환불 승인 (예약 #1)', $r1, 7, $gw);
    expect(count($calls) === 2 && $calls[1]['tid'] === 'TID1', 'retry with the same request id re-sends the refund to Inicis');
    expect($done['status'] === 'refunded' && (int)$done['refunded_amount'] === 10000 && $request($r1) === 'succeeded', 'ledger refunded, request succeeded');
    expect($events('refund_verified') === 1, 'exactly one refund event');
    expect($booking(1)['status'] === 'refunded' && $booking(1)['refund_status'] === 'refunded', 'cancelled booking becomes refunded');
    expect((int)$db->query("SELECT COUNT(*) AS n FROM gangnamunni_refund_audit WHERE payment_id=(SELECT id FROM gangnamunni_platform_payments WHERE merchant_uid='$m1') AND actor_type='admin' AND actor_id=7")->fetch_assoc()['n'] === 1, 'refund audit row for the admin');
    $again = booking_payment_refund($db, $m1, 10000, 'x', $r1, 7, $gw);
    expect($again['status'] === 'refunded' && count($calls) === 2, 'repeating a succeeded request is a no-op');

    // ---- 2. unknown outcome -> retry rejected -> failed (money may or may not have moved: console check + manual confirm)
    $m2 = $paidOrder(2, 'TID2'); $r2 = str_repeat('2', 64);
    $reply = new RuntimeException('timeout');
    rejects(fn() => booking_payment_refund($db, $m2, 10000, 'r', $r2, 7, $gw), 'second order: unknown outcome', RuntimeException::class);
    $reply = ['status' => 200, 'body' => json_encode(['resultCode' => '01', 'resultMsg' => 'already cancelled'])];
    rejects(fn() => booking_payment_refund($db, $m2, 10000, 'r', $r2, 7, $gw), 'retry rejected by Inicis', BookingPaymentRejectedException::class);
    expect($request($r2) === 'failed' && booking_payment_by_merchant($db, $m2)['status'] === 'paid' && $events('refund_verified') === 1, 'rejected retry -> failed, ledger still paid');
    rejects(fn() => booking_payment_refund($db, $m2, 10000, 'r', $r2, 7, $gw), 'a failed request id cannot be reused', RuntimeException::class);
    // console shows the cancel -> admin confirms by hand
    $callsBefore = count($calls);
    rejects(fn() => booking_payment_refund_confirm_manual($db, $m2, str_repeat('8', 64), 7, 'note'), 'manual confirm needs an existing request of this payment', RuntimeException::class);
    rejects(fn() => booking_payment_refund_confirm_manual($db, $m1, $r2, 7, 'note'), 'manual confirm refuses a request of another payment', RuntimeException::class);
    rejects(fn() => booking_payment_refund_confirm_manual($db, $m2, $r2, 0, 'note'), 'manual confirm needs an admin id', InvalidArgumentException::class);
    rejects(fn() => booking_payment_refund_confirm_manual($db, $m2, $r2, 7, '   '), 'manual confirm needs a note', InvalidArgumentException::class);
    $man = booking_payment_refund_confirm_manual($db, $m2, $r2, 7, '콘솔 Refund 2026-11-04 16:00 확인');
    expect(count($calls) === $callsBefore, 'manual confirm sends nothing to Inicis');
    expect($man['status'] === 'refunded' && (int)$man['refunded_amount'] === 10000 && $request($r2) === 'succeeded', 'manual confirm records the refund and closes the request');
    expect($events('refund_verified') === 2 && $booking(2)['status'] === 'refunded' && $booking(2)['refund_status'] === 'refunded', 'manual confirm goes through apply_remote: event + booking sync');
    expect((int)$db->query("SELECT COUNT(*) AS n FROM gangnamunni_refund_audit WHERE actor_name LIKE '%console-confirmed%'")->fetch_assoc()['n'] === 1, 'manual confirm leaves an audit row');
    $twice = booking_payment_refund_confirm_manual($db, $m2, $r2, 7, 'again');
    expect($twice['status'] === 'refunded' && $events('refund_verified') === 2, 'manual confirm is idempotent (no second event)');

    // ---- 3. unknown outcome twice -> still pending; manual confirm also accepts a pending request
    $m3 = $paidOrder(3, 'TID3'); $r3 = str_repeat('3', 64);
    $reply = new RuntimeException('timeout');
    rejects(fn() => booking_payment_refund($db, $m3, 10000, 'r', $r3, 7, $gw), 'third order: unknown outcome', RuntimeException::class);
    $reply = ['status' => 502, 'body' => 'bad gateway'];
    rejects(fn() => booking_payment_refund($db, $m3, 10000, 'r', $r3, 7, $gw), 'retry with a broken response is still unknown', RuntimeException::class);
    expect($request($r3) === 'pending' && booking_payment_by_merchant($db, $m3)['status'] === 'paid', 'still pending after a second unknown outcome');
    $man3 = booking_payment_refund_confirm_manual($db, $m3, $r3, 7, '콘솔 확인');
    expect($man3['status'] === 'refunded' && $request($r3) === 'succeeded', 'a pending request can be console-confirmed too');

    // ---- 4. partial amount refused before any call; full-only policy
    $m4 = $paidOrder(4, 'TID4'); $r4 = str_repeat('4', 64);
    $callsBefore = count($calls); $reply = $ok;
    rejects(fn() => booking_payment_refund($db, $m4, 4000, 'partial', $r4, 7, $gw), 'partial refund is rejected', BookingPaymentRejectedException::class);
    expect(count($calls) === $callsBefore && $request($r4) === 'failed' && booking_payment_by_merchant($db, $m4)['status'] === 'paid', 'partial: no Inicis call, request failed, ledger paid');
    $full = booking_payment_refund($db, $m4, 10000, 'full', str_repeat('5', 64), 7, $gw);
    expect($full['status'] === 'refunded' && count($calls) === $callsBefore + 1, 'full refund after the rejected partial works with a new request id');
    rejects(fn() => booking_payment_refund_confirm_manual($db, $m4, $r4, 7, 'note'), 'manual confirm refuses once the ledger is already refunded', RuntimeException::class);

    echo "\nAll $count checks passed.\n";
} finally {
    $db->query("DROP DATABASE `$name`");
}
