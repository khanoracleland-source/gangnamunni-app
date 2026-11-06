<?php
/**
 * Inicis card checkout (PHP/iOS side): checkout parameters, approval, net cancel and the whole P_NEXT_URL decision flow.
 * No database and no network: DB-facing steps are injected ($io) and Inicis is a fake transport behind the real InicisGateway.
 * Run: php tests/payments/inicis_checkout_flow.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$inc = __DIR__ . '/../../hairwang_full/gangnamunni/includes';
require_once $inc . '/booking_payment_config.php';
require_once $inc . '/booking_payment_inicis_flow.php';
$count = 0;
function expect($condition, string $label): void { global $count; if (!$condition) throw new RuntimeException('FAIL: ' . $label); $count++; echo "PASS: $label\n"; }
function rejects(callable $work, string $label, string $class = Throwable::class): void {
    try { $work(); } catch (Throwable $e) {
        if (!($e instanceof $class)) throw new RuntimeException("FAIL: $label (wrong exception " . get_class($e) . ': ' . $e->getMessage() . ')');
        expect(true, $label); return;
    }
    expect(false, $label);
}

$MID = 'seoulbtapp'; $KEY = 'apikey-fixture'; $IP = '10.0.0.5'; $HASH = 'hash-key-fixture-3CB8183A'; $BASE = 'https://seoulbeauty.app/gangnamunni';
$OID = 'BK_20261105140000_141_c568b4436eaa'; $AUTH = 'INIproAUTHseoulbtapp2026110514000012345';
$TID = 'INIproCARDseoulbtapp20261105140005111111';

$calls = []; $answers = [];
$transport = function (string $url, array $form, int $timeout) use (&$calls, &$answers) {
    $calls[] = ['url' => $url, 'form' => $form, 'timeout' => $timeout];
    $path = substr($url, strrpos($url, '/') + 1);
    $a = $answers[$path] ?? null;
    if ($a instanceof Throwable) throw $a;
    return $a ?? ['status' => 200, 'body' => 'P_STATUS=99'];
};
$noLedger = function () { return null; };
$gw = new InicisGateway($MID, $KEY, 'live', $IP, $noLedger, $transport, 15, $HASH);
$nvp = function (array $o): array { return ['status' => 200, 'body' => implode('&', array_map(function ($k, $v) { return "$k=$v"; }, array_keys($o), $o))]; };
$approveOk = function (array $over = []) use ($MID, $OID, $TID) {
    return array_merge(['P_STATUS' => '00', 'P_RMESG1' => 'ok', 'P_MID' => $MID, 'P_OID' => $OID, 'P_AMT' => '1000', 'P_APPL_TID' => $TID, 'P_APPL_DT' => '20251105', 'P_APPL_TM' => '140501', 'P_TYPE' => 'CARD'], $over);
};

// ---- chkFake / config
expect(InicisGateway::chkFake('1000', 'INIpayTest2026', '1700000000000', 'k') === base64_encode(hex2bin(hash('sha512', '1000INIpayTest20261700000000000k'))), 'P_CHKFAKE = base64(raw sha512(amt+oid+timestamp+hashkey))');
expect(!(new InicisGateway($MID, $KEY, 'live', $IP, $noLedger, $transport))->canCheckout(), 'no hash key -> checkout not available');
expect($gw->canCheckout() && $gw->mid() === $MID, 'hash key set -> checkout available');

// ---- checkoutParams
$order = ['id' => 40, 'merchant_uid' => $OID, 'booking_id' => 141, 'amount' => 1000, 'status' => 'pending', 'points_used' => 0, 'patient_mb_id' => 'apple_8407085f'];
$booking = ['treatment' => 'Botox 50U', 'name' => 'Kim'];
rejects(function () use ($MID, $KEY, $IP, $noLedger, $order, $booking) { (new InicisGateway($MID, $KEY, 'live', $IP, $noLedger))->checkoutParams($order, $booking, false, 'https://x.test'); }, 'checkoutParams refuses without a hash key', BookingPaymentGatewayUnavailableException::class);
$p = $gw->checkoutParams($order, $booking, false, $BASE . '/', 'wellness', '1762322400000');
expect($p['P_MID'] === $MID && $p['P_OID'] === $OID && $p['P_AMT'] === '1000' && $p['P_DEVICE_TYPE'] === 'WEB', 'web params: MID, OID, amount, WEB');
expect($p['P_NEXT_URL'] === $BASE . '/booking_payment_inicis_return.php' && $p['P_CLOSE_URL'] === $BASE . '/booking_payment.php?booking_id=141', 'P_NEXT_URL / P_CLOSE_URL from the https base');
expect($p['P_CHKFAKE'] === InicisGateway::chkFake('1000', $OID, '1762322400000', $HASH) && $p['P_TIMESTAMP'] === '1762322400000', 'P_CHKFAKE signs amount+oid+timestamp');
expect($p['P_NOTI'] === $OID, 'P_NOTI carries the order id (the mobile result returns it)');
expect($p['P_RESERVED'] === '{}', 'web: empty P_RESERVED object');
expect(strpos(json_encode($p), $HASH) === false, 'hash key never appears in the params');
$pm = $gw->checkoutParams($order, $booking, true, $BASE, 'wellness', '1762322400000');
expect($pm['P_DEVICE_TYPE'] === 'MOBILE' && $pm['P_RESERVED'] === '{"app_scheme":"wellness://payment"}', 'mobile: MOBILE + app_scheme');
$long = $gw->checkoutParams($order, ['treatment' => str_repeat('보', 40), 'name' => str_repeat('이', 40)], false, $BASE);
expect(strlen($long['P_GOODS']) <= 40 && strlen($long['P_UNAME']) <= 30 && mb_check_encoding($long['P_GOODS'], 'UTF-8') && mb_check_encoding($long['P_UNAME'], 'UTF-8'), 'goods/name clipped by bytes without splitting a character');
expect(preg_match('/^\d{13}$/', $gw->checkoutParams($order, $booking, false, $BASE)['P_TIMESTAMP']) === 1, 'default timestamp is 13 digits');
foreach ([['amount' => 0], ['amount' => -5], ['merchant_uid' => 'x'], ['merchant_uid' => $OID . '0'], ['booking_id' => 0]] as $bad) {
    rejects(function () use ($gw, $order, $booking, $bad, $BASE) { $gw->checkoutParams(array_merge($order, $bad), $booking, false, $BASE); }, 'checkoutParams refuses bad order ' . json_encode($bad), InvalidArgumentException::class);
}
rejects(function () use ($gw, $order, $booking) { $gw->checkoutParams($order, $booking, false, 'http://seoulbeauty.app/gangnamunni'); }, 'refuses a non-https base', InvalidArgumentException::class);
rejects(function () use ($gw, $order, $booking, $BASE) { $gw->checkoutParams($order, $booking, true, $BASE, 'bad scheme;'); }, 'refuses a bad app scheme', InvalidArgumentException::class);

// ---- approve / netCancel
$calls = []; $answers = ['payAppl.ini' => $nvp($approveOk())];
$r = $gw->approve('fc', $AUTH, 1000);
expect($calls[0]['url'] === 'https://fcpaypro.inicis.com/payment/v1/rest/payAppl.ini' && $calls[0]['form'] === ['P_MID' => $MID, 'P_AUTH_TID' => $AUTH, 'P_AMT' => '1000', 'P_CHARSET' => 'UTF-8'], 'approve posts the 4 fields to the IDC host');
expect($r['P_STATUS'] === '00' && $r['P_APPL_TID'] === $TID && $calls[0]['timeout'] <= 10, 'approve returns the parsed answer, timeout <= 10 s');
foreach (['ks', 'stg'] as $idc) { $calls = []; $gw->approve($idc, $AUTH, 1000); expect($calls[0]['url'] === "https://{$idc}paypro.inicis.com/payment/v1/rest/payAppl.ini", "approve host for $idc"); }
foreach (['', 'evil.', 'fc.evil.com/', 'FC', 'fc '] as $bad) {
    $calls = [];
    rejects(function () use ($gw, $AUTH, $bad) { $gw->approve($bad, $AUTH, 1000); }, 'approve refuses IDC "' . $bad . '"', InvalidArgumentException::class);
    expect($calls === [], 'no call for IDC "' . $bad . '"');
}
$answers = ['payAppl.ini' => new RuntimeException('timeout')]; rejects(function () use ($gw, $AUTH) { $gw->approve('fc', $AUTH, 1000); }, 'transport failure = unknown outcome', InicisUnknownOutcomeException::class);
$answers = ['payAppl.ini' => ['status' => 500, 'body' => 'oops']]; rejects(function () use ($gw, $AUTH) { $gw->approve('fc', $AUTH, 1000); }, 'HTTP 500 = unknown outcome', InicisUnknownOutcomeException::class);
$answers = ['payAppl.ini' => ['status' => 200, 'body' => '<html>']]; rejects(function () use ($gw, $AUTH) { $gw->approve('fc', $AUTH, 1000); }, 'unparsable answer = unknown outcome', InicisUnknownOutcomeException::class);
expect(InicisGateway::parseNvp('P_STATUS=00&P_RMESG1=a=b&junk&=x&P_AMT=1000') === ['P_STATUS' => '00', 'P_RMESG1' => 'a=b', 'P_AMT' => '1000'], 'NVP parser keeps "=" inside values, skips junk');
$calls = []; $answers = ['payNetCancel.ini' => $nvp(['P_STATUS' => '00'])];
$gw->netCancel('fc', $AUTH, 1000, $OID, 'ledger update failed', '1762322400000');
$f = $calls[0]['form'];
expect($calls[0]['url'] === 'https://fcpaypro.inicis.com/payment/v1/rest/payNetCancel.ini' && $f['P_CHKFAKE'] === InicisGateway::chkFake('1000', $OID, '1762322400000', $HASH) && $f['P_OID'] === $OID && $f['P_AUTH_TID'] === $AUTH, 'netCancel posts the signed cancel to payNetCancel.ini');

// ---- member allow list
$settings = function (array $s) { return function (string $k) use ($s) { return $s[$k] ?? ''; }; };
expect(booking_payment_inicis_member_allowed('live', 'apple_8407085f', $settings(['INICIS_LIVE_MEMBERS' => 'a, apple_8407085f'])), 'live: listed member passes');
expect(!booking_payment_inicis_member_allowed('live', 'other', $settings(['INICIS_LIVE_MEMBERS' => 'apple_8407085f'])), 'live: unlisted member refused');
expect(!booking_payment_inicis_member_allowed('live', 'x', $settings([])), 'live: empty list = nobody');
expect(booking_payment_inicis_member_allowed('live', 'anyone', $settings(['INICIS_LIVE_MEMBERS' => '*'])), 'live: * = every member');
expect(!booking_payment_inicis_member_allowed('live', '', $settings(['INICIS_LIVE_MEMBERS' => '*'])), 'anonymous never passes');
expect(!booking_payment_inicis_member_allowed('test', 'anyone', $settings(['INICIS_TEST_MEMBERS' => '*'])), 'test mode: * is not a wildcard');
expect(booking_payment_inicis_member_allowed('test', 'kim', $settings(['INICIS_TEST_MEMBERS' => 'kim'])), 'test mode uses INICIS_TEST_MEMBERS');
expect(!booking_payment_inicis_member_allowed('test', 'kim', $settings(['INICIS_LIVE_MEMBERS' => 'kim'])), 'test mode ignores INICIS_LIVE_MEMBERS');

// ---- P_NEXT_URL flow
$st = null;
$fresh = function () use ($order) { return ['order' => $order, 'booking' => 'confirmed', 'attempts' => [], 'recent' => 0, 'alerts' => [], 'applied' => [], 'applyFails' => false, 'lockBusy' => false, 'throwOnOrder' => false, 'allowed' => true]; };
$io = function () use (&$st): array {
    return [
        'lock' => function (string $m, callable $work) use (&$st) { if ($st['lockBusy']) throw new RuntimeException('Payment is busy. Retry later.'); return $work(); },
        'order' => function (string $m) use (&$st): ?array { if ($st['throwOnOrder']) throw new RuntimeException('db down'); return $st['order'] && $st['order']['merchant_uid'] === $m ? $st['order'] : null; },
        'booking_status' => function (int $id) use (&$st): string { return $st['booking']; },
        'recent_attempts' => function (int $id) use (&$st): int { return $st['recent']; },
        'attempt' => function (array $o, string $key, int $amt, string $outcome, ?string $reason) use (&$st): void { $st['attempts'][$key] = ['outcome' => $outcome, 'reason' => $reason, 'amount' => $amt]; },
        'apply' => function (array $o, array $remote) use (&$st): array { if ($st['applyFails']) throw new RuntimeException('Payment verification update failed.'); $st['applied'][] = $remote; $st['order']['status'] = 'paid'; return $st['order']; },
        'allowed' => function (string $mode, string $mb) use (&$st): bool { return $st['allowed'] && $mb !== ''; },
        'alert' => function (string $code, string $detail) use (&$st): void { $st['alerts'][] = [$code, $detail]; },
        'now' => function (): int { return 1762322400 + 3600; },
    ];
};
$auth = function (array $over = []) use ($MID, $OID, $AUTH) { return array_merge(['P_STATUS' => '00', 'P_MID' => $MID, 'P_OID' => $OID, 'P_AMT' => '1000', 'P_AUTH_TID' => $AUTH, 'P_IDCNAME' => 'fc'], $over); };
$run = function (array $post) use ($gw, $BASE, $io): string { return booking_payment_inicis_return($gw, $post, $BASE, $io()); };
$pay = function (string $code) use ($BASE) { return $BASE . '/booking_payment.php?booking_id=141&pay_error=' . $code; };
$success = $BASE . '/booking_payment_success.php?booking_id=141';
$setup = function (?array $ans = null) use (&$st, $fresh, &$calls, &$answers, $nvp, $approveOk) { $st = $fresh(); $calls = []; $answers = $ans ?? ['payAppl.ini' => $nvp($approveOk()), 'payNetCancel.ini' => $nvp(['P_STATUS' => '00'])]; };
$paths = function () use (&$calls) { return array_map(function ($c) { return substr($c['url'], strrpos($c['url'], '/') + 1); }, $calls); };

$setup(); $to = $run($auth());
expect($to === $success, 'happy path: redirected to success');
expect($paths() === ['payAppl.ini'], 'happy path: one call to Inicis');
expect(count($st['applied']) === 1 && $st['applied'][0]['transaction_id'] === $TID && $st['applied'][0]['amount'] === 1000 && $st['applied'][0]['status'] === 'paid' && $st['applied'][0]['payment_id'] === $OID && $st['applied'][0]['currency'] === 'KRW', 'ledger gets TID, amount, order id');
expect($st['applied'][0]['paid_at'] === (new DateTime('2025-11-05 14:05:01', new DateTimeZone('Asia/Seoul')))->getTimestamp(), 'paid_at from P_APPL_DT+TM in KST');
expect($st['attempts'] === [$AUTH => ['outcome' => 'paid', 'reason' => 'CONFIRMED', 'amount' => 1000]], 'one evidence row per auth TID, paid/CONFIRMED');
expect($st['alerts'] === [], 'happy path: no alert');

$setup(); $st['order']['status'] = 'paid';
expect($run($auth()) === $success && $calls === [] && $st['attempts'] === [], 'reload after payment: success, no call, no row');
$setup(); $st['order']['status'] = 'partially_refunded'; expect($run($auth()) === $success && $calls === [], 'partially refunded also lands on success');

$setup(); expect($run($auth(['P_STATUS' => '01', 'P_AUTH_TID' => ''])) === $pay('AUTH_FAILED') && $st['attempts'] === [] && $calls === [], 'cancelled layer (no TID): AUTH_FAILED, no row');
$setup(); expect($run($auth(['P_AUTH_TID' => ''])) === $pay('AUTH_INVALID') && $st['attempts'] === [] && $calls === [], 'status 00 but no TID: AUTH_INVALID');
$setup(); expect($run($auth(['P_AUTH_TID' => str_repeat('a', 192)])) === $pay('AUTH_INVALID') && $st['attempts'] === [], 'TID over 191 chars refused');
$setup(); expect($run($auth(['P_STATUS' => '01'])) === $pay('AUTH_FAILED') && $calls === [] && $st['attempts'][$AUTH]['outcome'] === 'rejected', 'failed auth with TID recorded as rejected');

foreach ([
    'amount differs' => [['P_AMT' => '10'], 'AMOUNT_MISMATCH'], 'amount not numeric' => [['P_AMT' => '1e3'], 'AMOUNT_MISMATCH'], 'amount empty' => [['P_AMT' => ''], 'AMOUNT_MISMATCH'],
    'other mid' => [['P_MID' => 'OTHERMID'], 'MID_MISMATCH'], 'evil IDC' => [['P_IDCNAME' => 'evil.example.com/x?'], 'AUTH_INVALID'], 'empty IDC' => [['P_IDCNAME' => ''], 'AUTH_INVALID'],
] as $label => $case) {
    $setup(); $to = $run($auth($case[0]));
    expect($to === $pay($case[1]) && $calls === [] && $st['applied'] === [], "refused before approval ($label) -> {$case[1]}");
}
$setup(); $st['order']['status'] = 'failed'; expect($run($auth()) === $pay('ORDER_STATE') && $calls === [], 'non-pending order refused');
$setup(); $st['booking'] = 'cancelled'; expect($run($auth()) === $pay('BOOKING_NOT_CONFIRMED') && $calls === [], 'unconfirmed booking refused');
$setup(); $st['order']['points_used'] = 500; expect($run($auth()) === $pay('POINTS_NOT_SUPPORTED') && $calls === [], 'order with points refused');
$setup(); $st['allowed'] = false; expect($run($auth()) === $pay('UNAVAILABLE') && $calls === [], 'member not on allow list refused');
$setup(); $st['order']['patient_mb_id'] = ''; expect($run($auth()) === $pay('UNAVAILABLE') && $calls === [], 'order without member refused');

$setup(); $st['recent'] = 30; expect($run($auth()) === $pay('TOO_MANY_ATTEMPTS') && $calls === [] && $st['attempts'] === [], '30 auths in the hour: refused');
$setup(); $st['recent'] = 29; expect($run($auth()) === $success, 'the 30th still goes through');

$setup(['payAppl.ini' => $nvp(['P_STATUS' => '01', 'P_RMESG1' => 'declined'])]);
expect($run($auth()) === $pay('REJECTED') && $paths() === ['payAppl.ini'] && $st['attempts'][$AUTH]['reason'] === 'APPROVE_01' && $st['applied'] === [] && $st['alerts'] === [], 'approval declined: REJECTED, no net cancel');

$setup(['payAppl.ini' => new RuntimeException('timeout'), 'payNetCancel.ini' => $nvp(['P_STATUS' => '00'])]);
expect($run($auth()) === $pay('UNRESOLVED') && $paths() === ['payAppl.ini', 'payNetCancel.ini'] && $st['applied'] === [], 'approval timeout: net cancel sent, UNRESOLVED');
expect($st['attempts'][$AUTH]['outcome'] === 'pending' && $st['attempts'][$AUTH]['reason'] === 'UNRESOLVED' && $st['alerts'][0][0] === 'UNRESOLVED_CANCELLED', 'timeout: pending/UNRESOLVED + alert');
$setup(['payAppl.ini' => new RuntimeException('timeout'), 'payNetCancel.ini' => new RuntimeException('down')]);
expect($run($auth()) === $pay('UNRESOLVED') && array_column($st['alerts'], 0) === ['NET_CANCEL_FAILED', 'UNRESOLVED_NOT_CANCELLED'], 'timeout + net cancel down: both alerts');

foreach (['other order id' => ['P_OID' => 'BK_20261105140000_999_aaaaaaaaaaaa'], 'other amount' => ['P_AMT' => '10'], 'other merchant' => ['P_MID' => 'OTHERMID'], 'no transaction id' => ['P_APPL_TID' => '']] as $label => $over) {
    $setup(['payAppl.ini' => $nvp($approveOk($over)), 'payNetCancel.ini' => $nvp(['P_STATUS' => '00'])]);
    expect($run($auth()) === $pay('RESULT_MISMATCH') && $paths() === ['payAppl.ini', 'payNetCancel.ini'] && $st['applied'] === [] && $st['alerts'][0][0] === 'RESULT_MISMATCH', "approval answer with $label: net-cancelled");
}

$setup(); $st['applyFails'] = true; $to = $run($auth());
expect($to === $pay('FINALIZE_FAILED') && $paths() === ['payAppl.ini', 'payNetCancel.ini'] && $st['order']['status'] === 'pending' && $st['attempts'][$AUTH]['reason'] === 'FINALIZE_FAILED' && $st['alerts'][0][0] === 'FINALIZE_FAILED', 'ledger fails after approval: net-cancelled, pending, alert');
$cb = $calls[1]['form'];
expect($cb['P_AUTH_TID'] === $AUTH && $cb['P_OID'] === $OID && $cb['P_AMT'] === '1000' && $cb['P_CHKFAKE'] === InicisGateway::chkFake('1000', $OID, $cb['P_TIMESTAMP'], $HASH), 'net cancel carries TID, oid, amount and valid signature');
$setup(['payAppl.ini' => $nvp($approveOk()), 'payNetCancel.ini' => new RuntimeException('down')]); $st['applyFails'] = true;
expect($run($auth()) === $pay('FINALIZE_FAILED_UNCANCELLED') && $st['attempts'][$AUTH]['reason'] === 'FINALIZE_FAILED_UNCANCELLED' && array_column($st['alerts'], 0) === ['NET_CANCEL_FAILED', 'FINALIZE_FAILED_UNCANCELLED'], 'ledger + net cancel both fail: UNCANCELLED, both alerts');
$setup(['payAppl.ini' => $nvp($approveOk()), 'payNetCancel.ini' => $nvp(['P_STATUS' => '01'])]); $st['applyFails'] = true;
expect($run($auth()) === $pay('FINALIZE_FAILED_UNCANCELLED') && array_column($st['alerts'], 0) === ['NET_CANCEL_UNCONFIRMED', 'FINALIZE_FAILED_UNCANCELLED'], 'net cancel answered non-00 is NOT treated as done');

foreach (['ks', 'stg'] as $idc) { $setup(); $run($auth(['P_IDCNAME' => $idc])); expect($calls[0]['url'] === "https://{$idc}paypro.inicis.com/payment/v1/rest/payAppl.ini", "approval host follows P_IDCNAME ($idc)"); }

$setup(); $st['order'] = null; expect($run($auth()) === $pay('UNKNOWN_ORDER') && $calls === [], 'unknown order id: no call');
foreach (['', 'x', "BK_1' OR 1=1", 'BK_20261105140000_141_ABCDEF012345', $OID . '0'] as $badOid) {
    $setup(); $to = $run($auth(['P_OID' => $badOid]));
    expect($to === $BASE . '/my_bookings.php?pay_error=UNAVAILABLE' && $calls === [] && $st['attempts'] === [], 'malformed order id never looked up: "' . $badOid . '"');
}
$setup(); $to = $run($auth(['P_OID' => '', 'P_NOTI' => $OID]));
expect($to === $success && $paths() === ['payAppl.ini'], 'mobile result without P_OID: the order id comes back in P_NOTI');
$setup(); $to = $run($auth(['P_OID' => '', 'P_NOTI' => '141']));
expect($to === $BASE . '/my_bookings.php?pay_error=UNAVAILABLE' && $calls === [], 'no valid order id in P_OID or P_NOTI: nothing is looked up');
$setup(); $st['lockBusy'] = true; expect($run($auth()) === $pay('BUSY') && $calls === [], 'busy lock: BUSY, no approval');
$setup(); $st['throwOnOrder'] = true; expect($run($auth()) === $pay('ERROR') && $calls === [], 'unexpected exception: ERROR, no approval');
$setup(); $to = $run($auth(['P_RMESG1' => '<script>alert(1)</script>']));
expect(strpos($to, '<') === false && strpos($to, 'script') === false, 'nothing from Inicis is echoed into the redirect');

echo "\nAll $count checks passed.\n";
