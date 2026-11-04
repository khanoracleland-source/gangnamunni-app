<?php
/** InicisGateway unit checks: no DB, no network. Run: php tests/payments/inicis_gateway.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/booking_payment_inicis.php';
$count = 0;
function expect($condition, string $label): void { global $count; if (!$condition) throw new RuntimeException('FAIL: ' . $label); $count++; echo "PASS: $label\n"; }
function rejects(callable $work, string $label, string $class = Throwable::class, ?string $message = null): void {
    try { $work(); } catch (Throwable $e) {
        if (!($e instanceof $class)) throw new RuntimeException("FAIL: $label (wrong exception " . get_class($e) . ': ' . $e->getMessage() . ')');
        if ($message !== null && $e->getMessage() !== $message) throw new RuntimeException("FAIL: $label (message " . $e->getMessage() . ')');
        expect(true, $label); return;
    }
    expect(false, $label);
}

$MID = 'seoulbtapp'; $KEY = 'apikey-fixture'; $IP = '10.0.0.5'; $TID = 'INIproCARDseoulbtapp20261104150335370108'; $OID = 'BK_20261104144655_141_c568b4436eaa';
$rows = [$OID => ['id' => 40, 'merchant_uid' => $OID, 'amount' => 10000, 'refunded_amount' => 0, 'status' => 'paid', 'provider' => 'inicis',
    'provider_transaction_id' => $TID, 'paid_at' => '2026-11-04 15:03:35']];
$ledger = function (string $m) use (&$rows): ?array { return $rows[$m] ?? null; };
$calls = [];
$reply = ['status' => 200, 'body' => json_encode(['resultCode' => '00', 'resultMsg' => '정상처리', 'cancelDate' => '20261104', 'cancelTime' => '153000'])];
$transport = function (string $url, array $form, int $t) use (&$calls, &$reply) { $calls[] = [$url, $form]; if ($reply instanceof Throwable) throw $reply; return $reply; };
$gw = new InicisGateway($MID, $KEY, 'live', $IP, $ledger, $transport);

// --- configuration
expect($gw->provider() === 'inicis' && $gw->mode() === 'live' && !$gw->queriesProvider(), 'live gateway: provider inicis, ledger-backed');
expect((new InicisGateway('INIpayTest', $KEY, 'test', $IP, $ledger, $transport))->provider() === 'inicis_test', 'test MID records inicis_test');
expect((new InicisGateway('INIpayTest', $KEY, 'test', $IP, $ledger, $transport))->refundUrl() === 'https://stginiapi.inicis.com/api/v1/refund', 'test mode uses staging INIAPI host');
expect($gw->refundUrl() === 'https://iniapi.inicis.com/api/v1/refund', 'live mode uses production INIAPI host');
rejects(fn() => new InicisGateway('INIpayTest', $KEY, 'live', $IP, $ledger, $transport), 'live refuses a test MID', InvalidArgumentException::class);
rejects(fn() => new InicisGateway($MID, $KEY, 'test', $IP, $ledger, $transport), 'test refuses a real MID', InvalidArgumentException::class);
rejects(fn() => new InicisGateway($MID, '', 'live', $IP, $ledger, $transport), 'INIAPI key required', InvalidArgumentException::class);
rejects(fn() => new InicisGateway($MID, $KEY, 'live', 'not-an-ip', $ledger, $transport), 'clientIp must be an IP', InvalidArgumentException::class);
rejects(fn() => $gw->confirm($OID, $TID, 10000), 'PHP approval is not connected (Nest does it)', BookingPaymentGatewayUnavailableException::class);

// --- hashData per manual: SHA512(INIAPIKey + type + paymethod + timestamp + clientIp + mid + tid)
$form = $gw->refundForm($TID, '예약 취소 환불 승인 (예약 #141)', '20261104153000');
expect($form['hashData'] === hash('sha512', $KEY . 'Refund' . 'Card' . '20261104153000' . $IP . $MID . $TID), 'hashData concatenation order');
expect($form['type'] === 'Refund' && $form['paymethod'] === 'Card' && $form['mid'] === $MID && $form['tid'] === $TID && $form['clientIp'] === $IP, 'refund form fields');
expect(strlen($form['msg']) <= 80 && $form['msg'] !== '', 'cancel reason fits 80 bytes');
$long = $gw->refundForm($TID, str_repeat('예약 취소 환불 승인 ', 10))['msg'];   // 180+ bytes of Korean
expect(strlen($long) <= 80 && mb_check_encoding($long, 'UTF-8') && mb_strlen($long, 'UTF-8') >= 20, 'long Korean reason is cut to 80 BYTES without breaking a character');
expect($gw->refundForm($TID, '   ')['msg'] === 'refund', 'empty reason gets a placeholder');
expect(preg_match('/^\d{14}$/', $gw->refundForm($TID, 'x')['timestamp']) === 1, 'timestamp is YYYYMMDDhhmmss');
expect(strpos(json_encode($form), $KEY) === false, 'the API key never appears in the form');

// --- payment(): normalized from the ledger
$p = $gw->payment($OID);
expect($p['status'] === 'paid' && $p['amount'] === 10000 && $p['cancelled_amount'] === 0 && $p['transaction_id'] === $TID && $p['currency'] === 'KRW', 'paid ledger row -> paid');
expect(is_int($p['paid_at']) && $p['paid_at'] === (new DateTime('2026-11-04 15:03:35', new DateTimeZone('Asia/Seoul')))->getTimestamp(), 'paid_at is KST unix time');
$u = $gw->payment('BK_unknown');
expect($u['status'] === 'ready' && $u['raw_status'] === 'NOT_FOUND' && $u['cancelled_amount'] === 0, 'unknown order -> ready/NOT_FOUND (payment/check.php probe)');
$rows['BK_pending'] = ['id' => 41, 'merchant_uid' => 'BK_pending', 'amount' => 5000, 'refunded_amount' => 0, 'status' => 'pending', 'provider' => null, 'provider_transaction_id' => null, 'paid_at' => null];
expect($gw->payment('BK_pending')['status'] === 'ready', 'pending ledger row -> ready (no money moved)');
$rows['BK_refunded'] = ['id' => 42, 'merchant_uid' => 'BK_refunded', 'amount' => 5000, 'refunded_amount' => 5000, 'status' => 'refunded', 'provider' => 'inicis', 'provider_transaction_id' => 'T2', 'paid_at' => '2026-11-01 10:00:00'];
$r = $gw->payment('BK_refunded');
expect($r['status'] === 'cancelled' && $r['cancelled_amount'] === 5000, 'refunded ledger row -> cancelled with full amount');

// --- cancel(): full refund only
rejects(fn() => $gw->cancel($OID, $TID, 4000, 10000, 'partial', str_repeat('a', 64)), 'partial refund refused before any call', BookingPaymentRejectedException::class, 'PARTIAL_NOT_SUPPORTED');
rejects(fn() => $gw->cancel($OID, 'OTHERTID', 10000, 10000, 'x', str_repeat('a', 64)), 'tid must match the ledger', BookingPaymentRejectedException::class, 'TID_MISMATCH');
expect($calls === [], 'refused requests never reach Inicis');
$res = $gw->cancel($OID, $TID, 10000, 10000, '예약 취소 환불 승인 (예약 #141)', str_repeat('a', 64));
expect(count($calls) === 1 && $calls[0][0] === 'https://iniapi.inicis.com/api/v1/refund', 'refund POSTs to INIAPI');
expect($calls[0][1]['tid'] === $TID && $calls[0][1]['mid'] === $MID && strlen($calls[0][1]['hashData']) === 128, 'refund form carries tid, mid, sha512 hash');
expect($res['cancelled_amount'] === 10000 && $res['cancel_date'] === '20261104', 'success returns the cancelled amount');
$after = $gw->payment($OID);
expect($after['status'] === 'cancelled' && $after['cancelled_amount'] === 10000, 'after a successful cancel payment() reports it even before the ledger is written');

// --- cancel(): rejection vs unknown outcome
$gw2 = new InicisGateway($MID, $KEY, 'live', $IP, $ledger, $transport);
$reply = ['status' => 200, 'body' => json_encode(['resultCode' => '01', 'resultMsg' => '취소 불가', 'detailResultCode' => 'E1234'])];
rejects(fn() => $gw2->cancel($OID, $TID, 10000, 10000, 'x', str_repeat('b', 64)), 'resultCode != 00 is an explicit rejection', BookingPaymentRejectedException::class, 'INICIS_01');
expect($gw2->payment($OID)['cancelled_amount'] === 0, 'a rejected cancel is not remembered');
$reply = new RuntimeException('timeout');
rejects(fn() => $gw2->cancel($OID, $TID, 10000, 10000, 'x', str_repeat('c', 64)), 'transport failure = unknown outcome (RuntimeException, not rejection)', RuntimeException::class);
$reply = ['status' => 502, 'body' => '<html>bad gateway</html>'];
rejects(fn() => $gw2->cancel($OID, $TID, 10000, 10000, 'x', str_repeat('d', 64)), 'non-JSON / non-200 = unknown outcome', RuntimeException::class);
try { $gw2->cancel($OID, $TID, 10000, 10000, 'x', str_repeat('d', 64)); } catch (BookingPaymentRejectedException $e) { throw new RuntimeException('FAIL: unknown outcome must not be a rejection'); } catch (RuntimeException $e) {}
expect(true, 'unknown outcome is never reported as a rejection');

echo "\nAll $count checks passed.\n";
