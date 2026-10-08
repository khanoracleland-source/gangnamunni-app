<?php
/** Run in the local gangnamunni container. Uses and deletes ONLY a random test DB. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/booking_payment_service.php';
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

/**
 * In-memory PG used by the ledger tests. It returns the normalized shape a real
 * gateway client must produce; PG-specific HTTP/webhook tests belong with that client.
 */
final class FakeGateway implements BookingPaymentGateway {
    private $provider; private $payment; private $cancel;
    public function __construct(string $provider, callable $payment, ?callable $cancel = null) {
        $this->provider = $provider; $this->payment = $payment; $this->cancel = $cancel;
    }
    public function provider(): string { return $this->provider; }
    public function payment(string $merchantUid): array { return ($this->payment)($merchantUid); }
    public function cancel(string $merchantUid, string $transactionId, int $amount, int $remaining, string $reason, string $requestId): array {
        if (!$this->cancel) throw new RuntimeException('cancel not supported');
        return ($this->cancel)($merchantUid, $transactionId, $amount, $remaining, $reason, $requestId);
    }
}

expect(booking_payment_gateway() === null, 'no PG is connected after PortOne removal');
$order = ['merchant_uid'=>'BK_fixture', 'amount'=>100000, 'provider'=>null, 'provider_transaction_id'=>null];
$remote = ['payment_id'=>'BK_fixture','transaction_id'=>'tx_fixture','amount'=>100000,'currency'=>'KRW',
    'status'=>'paid','cancelled_amount'=>0,'paid_at'=>time()-86400];
expect(booking_payment_validate($order, $remote, 'toss_test') === 'toss_test', 'gateway provider recorded as given');
rejects(fn()=>booking_payment_validate($order, $remote, 'portone'), 'unknown/removed provider rejected');
rejects(fn()=>booking_payment_validate($order, $remote, 'mock_booking_payment'), 'mock cannot pose as a PG');
foreach (['payment_id'=>'BK_other','amount'=>1,'currency'=>'USD','cancelled_amount'=>100001] as $key=>$value) {
    rejects(fn()=>booking_payment_validate($order, array_replace($remote, [$key=>$value]), 'toss_test'), 'reject mismatched ' . $key);
}
rejects(fn()=>booking_payment_validate($order, array_diff_key($remote,['transaction_id'=>1]), 'toss_test'), 'settled payment without transaction id rejected');
rejects(fn()=>booking_payment_validate($order, array_replace($remote,['paid_at'=>0]), 'toss_test'), 'missing provider payment time rejected');
expect(booking_payment_validate($order, ['payment_id'=>'BK_fixture','amount'=>100000,'currency'=>'KRW',
    'status'=>'ready','cancelled_amount'=>0], 'toss_test') === 'toss_test', 'unsettled state validates without transaction data');
$verified = array_replace($order, ['provider'=>'toss_test','provider_transaction_id'=>'tx_fixture']);
rejects(fn()=>booking_payment_validate($verified, $remote, 'toss'), 'sandbox payment cannot be re-claimed by a live gateway');
rejects(fn()=>booking_payment_validate($verified, array_replace($remote,['transaction_id'=>'tx_other']), 'toss_test'), 'verified transaction cannot change');

foreach (['refunded','cancelled','failed'] as $state) {
    expect(booking_payment_display_state(['status'=>$state,'provider'=>'toss_test','amount'=>100000],true)===$state, 'terminal state overrides preview: '.$state);
}
expect(booking_payment_display_state(['status'=>'paid','provider'=>'toss_test','amount'=>100000])==='test_paid', 'sandbox is labelled as test payment, not unpaid preview');
expect(booking_payment_display_state(['status'=>'paid','provider'=>'portone_test','amount'=>100000])==='test_paid', 'legacy PortOne sandbox rows stay labelled as test');
expect(booking_payment_is_live_pg('toss') && !booking_payment_is_live_pg('toss_test') && !booking_payment_is_live_pg('portone'), 'only live PG rows count as real payments');

putenv('BOOKING_PAYMENT_METHODS= kakaopay,card ,bogus,CARD');
expect(array_keys(booking_payment_methods()) === ['card','kakaopay'], 'methods parsed, filtered and ordered');
putenv('G5_PG_PAYMENTS_ENABLED=1'); putenv('BOOKING_PAYMENT_BASE_URL=https://fixture.invalid');
expect(!booking_payment_checkout_ready(), 'checkout stays off while no PG is connected');
putenv('G5_PG_PAYMENTS_ENABLED=0'); putenv('BOOKING_PAYMENT_METHODS=');
rejects(fn()=>booking_payment_sql_list([]), 'empty provider list refused for SQL');
rejects(fn()=>booking_payment_sql_list(["x') OR 1=1 -- "]), 'provider SQL list only accepts constants');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(getenv('G5_MYSQL_HOST') ?: '127.0.0.1', getenv('G5_MYSQL_USER') ?: 'root', getenv('G5_MYSQL_PASSWORD') ?: '', '', (int)(getenv('G5_MYSQL_PORT') ?: 3306));
$name = 'codex_payment_test_' . bin2hex(random_bytes(5));
$db->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
try {
    $db->select_db($name);
    $schema = file_get_contents(__DIR__ . '/../../hairwang_full/gangnamunni/sql/platform_payments_schema.sql');
    $schema = substr($schema, strpos($schema, 'CREATE TABLE IF NOT EXISTS gangnamunni_platform_payments'));
    $schema .= file_get_contents(__DIR__ . '/../../hairwang_full/gangnamunni/sql/booking_payment_previews.sql');
    foreach (explode(';', $schema) as $sql) if (trim($sql) !== '') $db->query($sql);
    $db->query("CREATE TABLE gangnamunni_bookings (id BIGINT PRIMARY KEY, hospital_id BIGINT, hospital_name VARCHAR(255),mb_id VARCHAR(100),name VARCHAR(100),phone VARCHAR(30),treatment VARCHAR(255),price INT,status VARCHAR(30))");
    $db->query("INSERT INTO gangnamunni_bookings VALUES (1,1,'Fixture Hospital','fixture','Fixture','01000000000','Fixture treatment',100000,'confirmed'),(2,1,'Fixture Hospital','fixture','Fixture','01000000000','Fixture treatment',100000,'confirmed')");
    $created = platform_payment_create_booking($db,1);
    $again = platform_payment_create_booking($db,1);
    expect($created['merchant_uid'] === $again['merchant_uid'], 'one financial order per booking');
    $merchant = $created['merchant_uid'];
    // Mutable PG state, rendered in the normalized gateway shape on every call.
    $state = ['id'=>$merchant,'transactionId'=>'tx_fixture','status'=>'paid',
        'cancelled'=>0,'paidAt'=>time()-86400];
    $normalized = function() use (&$state) {
        return ['payment_id'=>$state['id'],'transaction_id'=>$state['transactionId'],'status'=>$state['status'],
            'raw_status'=>strtoupper($state['status']),'amount'=>100000,'cancelled_amount'=>$state['cancelled'],
            'currency'=>'KRW','paid_at'=>$state['paidAt']];
    };
    $cancelCalls = 0;
    $cancel = function($m,$tx,$amount,$remaining,$reason,$requestId) use (&$state,&$cancelCalls) {
        expect($remaining === 100000-$state['cancelled'], 'refund guards on the remaining balance');
        expect($tx === $state['transactionId'] && preg_match('/^[a-f0-9]{64}$/D', $requestId) === 1, 'refund carries transaction id and idempotency key');
        $cancelCalls++;
        $state['cancelled'] += $amount;
        $state['status'] = $state['cancelled'] >= 100000 ? 'cancelled' : 'paid';
        return ['status'=>'DONE'];
    };
    $client = new FakeGateway('toss_test', fn()=>$normalized(), $cancel);
    expect(booking_payment_sync($db,$merchant)['status'] === 'pending', 'without a PG, sync returns the ledger unchanged');
    rejects(fn()=>booking_payment_refund($db,$merchant,1000,'no pg',str_repeat('9',64),1), 'without a PG, refund refuses to run');
    $wrongId = new FakeGateway('toss_test', function() use ($normalized) {
        $payment = $normalized(); $payment['payment_id'] = 'BK_someone_else'; return $payment;
    });
    rejects(fn()=>booking_payment_sync($db,$merchant,'webhook',$wrongId), 'provider response must match the requested payment');
    $paid = booking_payment_sync($db,$merchant,'webhook',$client);
    expect($paid['status'] === 'paid', 'provider-confirmed payment transitions to paid');
    expect($paid['paid_at'] === gmdate('Y-m-d H:i:s', $state['paidAt'] + 9*3600), 'provider payment date preserved for settlement');
    rejects(fn()=>booking_payment_sync($db,$merchant,'webhook',new FakeGateway('toss', fn()=>$normalized())), 'verified sandbox payment is not re-tagged by a live gateway');
    booking_payment_sync($db,$merchant,'webhook',$client);
    expect((int)$db->query('SELECT COUNT(*) AS n FROM gangnamunni_payment_events')->fetch_assoc()['n'] === 1, 'duplicate callback creates no second event');
    $r1 = str_repeat('a',64);
    $partial = booking_payment_refund($db,$merchant,30000,'fixture refund',$r1,1,$client);
    expect($partial['status'] === 'partially_refunded' && (int)$partial['refunded_amount'] === 30000, 'partial refund preserves remaining balance');
    booking_payment_refund($db,$merchant,30000,'fixture refund',$r1,1,$client);
    expect($cancelCalls === 1, 'same refund request does not charge cancellation twice');
    booking_payment_sync($db,$merchant,'webhook',$client);
    expect((int)$db->query('SELECT COUNT(*) AS n FROM gangnamunni_payment_events')->fetch_assoc()['n'] === 2, 'duplicate refund webhook is idempotent');
    rejects(fn()=>booking_payment_refund($db,$merchant,80000,'too much',str_repeat('b',64),1,$client), 'over-refund rejected before provider call');
    $full = booking_payment_refund($db,$merchant,70000,'fixture remainder',str_repeat('c',64),1,$client);
    expect($full['status'] === 'refunded' && (int)$full['refunded_amount'] === 100000, 'full refund accounted correctly');
    booking_payment_sync($db,$merchant,'webhook',$client);
    expect((int)$db->query('SELECT COUNT(*) AS n FROM gangnamunni_payment_events')->fetch_assoc()['n'] === 3, 'full refund replay is harmless');
    $second = platform_payment_create_booking($db,2);
    rejects(fn()=>platform_payment_mark_verified($db,$second['merchant_uid'],'mock_booking_payment','mock','event',100000,str_repeat('a',64)), 'mock cannot enter verified financial ledger');
    booking_payment_record_preview($db,2);
    booking_payment_record_preview($db,2);
    expect((int)$db->query('SELECT COUNT(*) AS n FROM gangnamunni_booking_payment_previews')->fetch_assoc()['n'] === 1, 'preview double-click is idempotent');
    putenv('BOOKING_PAYMENT_PREVIEW_ENABLED=0');
    rejects(fn()=>booking_payment_record_preview($db,2), 'disabled preview rejected server-side');
    putenv('BOOKING_PAYMENT_PREVIEW_ENABLED=1');
    expect(isset(booking_payment_previews($db,[2])[2]), 'preview is stored independently');
    expect(booking_payment_order($db,2)['status'] === 'pending', 'preview does not mark money paid');
    expect((int)$db->query("SELECT COUNT(*) AS n FROM gangnamunni_platform_payments WHERE status IN ('paid','partially_refunded','refunded') AND provider NOT IN (" . booking_payment_sql_list(booking_payment_test_providers()) . ")")->fetch_assoc()['n'] === 0, 'sandbox and previews excluded from revenue');
    $is_member = true; $member = ['mb_id'=>'unrelated','mb_hp'=>'01099999999'];
    expect(booking_payment_owned($db,1) === null, 'unrelated user cannot access payment');
    $member = ['mb_id'=>'fixture'];
    expect(booking_payment_owned($db,1)['id'] == 1, 'owner can access payment');
    // The same real provider transaction cannot be used to pay a second order.
    $state['id']=$second['merchant_uid']; $state['cancelled']=0; $state['status']='paid';
    rejects(fn()=>booking_payment_sync($db,$second['merchant_uid'],'webhook',$client), 'transaction reuse across orders rejected');
    expect(booking_payment_order($db,2)['status'] === 'pending', 'rejected transaction leaves order unchanged');
    $state['transactionId']='tx_second';
    booking_payment_sync($db,$second['merchant_uid'],'webhook',$client);
    $timeoutClient = new FakeGateway('toss_test', fn()=>$normalized(), function(...$args) use ($cancel) {
        $cancel(...$args);
        throw new RuntimeException('Simulated network timeout AFTER refund.');
    });
    $timeoutId = str_repeat('d',64);
    rejects(fn()=>booking_payment_refund($db,$second['merchant_uid'],20000,'timeout test',$timeoutId,1,$timeoutClient), 'ambiguous cancellation does not claim success');
    $callsBefore = $cancelCalls;
    $recovered = booking_payment_refund($db,$second['merchant_uid'],20000,'timeout test',$timeoutId,1,$client);
    expect((int)$recovered['refunded_amount'] === 20000 && $cancelCalls === $callsBefore, 'timeout retry recovers without another refund');

    $db->query("INSERT INTO gangnamunni_bookings VALUES (4,1,'Fixture Hospital','fixture','Fixture','01000000000','Fixture treatment',100000,'confirmed')");
    $fourth = platform_payment_create_booking($db,4);
    $state['id']=$fourth['merchant_uid']; $state['transactionId']='tx_fourth';
    $state['status']='cancelled'; $state['cancelled']=100000;
    $db->query("CREATE TRIGGER fail_refund_fixture BEFORE INSERT ON gangnamunni_payment_events FOR EACH ROW BEGIN IF NEW.event_type='refund_verified' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected refund persistence failure'; END IF; END");
    rejects(fn()=>booking_payment_sync($db,$fourth['merchant_uid'],'webhook',$client), 'refund database failure surfaced');
    expect(booking_payment_order($db,4)['status'] === 'pending', 'first cancelled observation rolls payment and refund back together');
    $db->query('DROP TRIGGER fail_refund_fixture');
    $cancelledFirst = booking_payment_sync($db,$fourth['merchant_uid'],'webhook',$client);
    expect($cancelledFirst['status']==='refunded' && (int)$cancelledFirst['refunded_amount']===100000, 'first fully cancelled observation records payment and refund together');
    $db->query("INSERT INTO gangnamunni_bookings VALUES (5,1,'Fixture Hospital','fixture','Fixture','01000000000','Fixture treatment',100000,'confirmed')");
    $fourth = platform_payment_create_booking($db,5);
    $state['id']=$fourth['merchant_uid']; $state['transactionId']='tx_fifth';
    $state['status']='paid'; $state['cancelled']=0;
    booking_payment_sync($db,$fourth['merchant_uid'],'webhook',$client);
    $rejectClient = new FakeGateway('toss_test', fn()=>$normalized(), function() {
        throw new BookingPaymentRejectedException('fixture rejection');
    });
    rejects(fn()=>booking_payment_refund($db,$fourth['merchant_uid'],1000,'declined',str_repeat('e',64),1,$rejectClient), 'explicit provider rejection is reported');
    $request = $db->query("SELECT status FROM gangnamunni_payment_refund_requests WHERE request_id=REPEAT('e',64)")->fetch_assoc();
    expect($request['status'] === 'failed', 'definitive refund rejection does not remain pending forever');
    $retry = booking_payment_refund($db,$fourth['merchant_uid'],1000,'corrected request',str_repeat('f',64),1,$client);
    expect((int)$retry['refunded_amount']===1000, 'new refund can follow a definite rejection');

    // Settlement runs against this throwaway database, never the application DB.
    $schema = file_get_contents(__DIR__ . '/../../hairwang_full/gangnamunni/sql/monthly_hospital_settlements.sql');
    foreach (explode(';',$schema) as $sql) if (trim($sql) !== '') $db->query($sql);
    $db->query("INSERT INTO gangnamunni_hospital_settlements
        (hospital_id,period_start,period_end,scheduled_payout_date,payout_idempotency_key,refunds,status)
        VALUES (1,DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 2 MONTH),'%Y-%m-01'),LAST_DAY(DATE_SUB(CURDATE(),INTERVAL 2 MONTH)),LAST_DAY(DATE_SUB(CURDATE(),INTERVAL 2 MONTH)),REPEAT('e',64),40000,'scheduled')");
    // A real historical payment; the second sandbox payment stays excluded.
    $db->query("UPDATE gangnamunni_platform_payments SET provider='toss', status='paid',refunded_amount=0,paid_at=DATE_SUB(CURDATE(),INTERVAL 1 MONTH) WHERE booking_id=1");
    $entry = tempnam(sys_get_temp_dir(),'payment-settlement-');
    $cron = realpath(__DIR__ . '/../../hairwang_full/gangnamunni/cron_monthly_hospital_settlements.php');
    file_put_contents($entry, '<?php foreach (["HOST","USER","PASSWORD","PORT"] as $k) define("G5_MYSQL_".$k,getenv("G5_MYSQL_".$k)); define("G5_MYSQL_DB",getenv("PAYMENT_TEST_DATABASE")); require ' . var_export($cron,true) . ';');
    putenv('PAYMENT_TEST_DATABASE='.$name);
    try {
        $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($entry);
        exec($command, $cronOutput, $exitCode);
        expect($exitCode === 0, 'monthly settlement executes on MySQL 8');
        $batch = $db->query("SELECT * FROM gangnamunni_hospital_settlements WHERE period_end=LAST_DAY(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))")->fetch_assoc();
        expect((int)$batch['treatment_gross']===100000 && (int)$batch['carry_forward_amount']===-40000 && (int)$batch['payout_amount']===60000, 'negative prior balance carried and sandbox excluded');
        exec($command, $cronOutput, $exitCode);
        expect((int)$db->query('SELECT COUNT(*) AS n FROM gangnamunni_hospital_settlement_items')->fetch_assoc()['n']===1, 'monthly job rerun is idempotent');
        $db->query("UPDATE gangnamunni_hospital_settlements SET status='processing',approved_by=1,approved_at=NOW() WHERE id=".(int)$batch['id']);
        exec($command, $cronOutput, $exitCode);
        expect($exitCode===0 && (int)$db->query('SELECT COUNT(*) AS n FROM gangnamunni_hospital_settlement_items')->fetch_assoc()['n']===1, 'approved settlement remains immutable');
    } finally { unlink($entry); }

    // Render the actual checkout template with synthetic data, without application login/bootstrap.
    if (in_array('--render', $argv, true)) {
        $db->query("CREATE TABLE gangnamunni_hospitals (id BIGINT PRIMARY KEY,name VARCHAR(255),profile_image_url VARCHAR(255),address VARCHAR(255))");
        $db->query("INSERT INTO gangnamunni_hospitals VALUES (1,'테스트 클리닉','','서울 강남구')");
        $db->query("ALTER TABLE gangnamunni_bookings ADD booking_date DATE, ADD booking_time TIME");
        $db->query("INSERT INTO gangnamunni_bookings VALUES (3,1,'테스트 클리닉','','테스트 고객','01000000000','피부 관리',100000,'confirmed','2026-10-01','10:00:00')");
        platform_payment_create_booking($db,3);
        $connect = $db; $is_member = false; $member = [];
        $_SESSION = ['user_phone'=>'01000000000','csrf_token'=>'fixture-only']; $_GET = ['booking_id'=>3];
        function term($key) { return $key; }
        $directory = realpath(__DIR__.'/../../hairwang_full/gangnamunni');
        $page = file_get_contents($directory.'/booking_payment.php');
        $page = str_replace(["require_once __DIR__ . '/includes/init.php';", "@include_once __DIR__ . '/includes/sso_auto_login.php';"], '', $page);
        $page = str_replace('__DIR__',var_export($directory,true),$page);
        ob_start(); eval('?>'.$page); $html = ob_get_clean();
        expect(strpos($html,'id="previewPayBtn"') !== false && strpos($html,'실제 결제는 준비 중입니다.') !== false, 'actual checkout renders preview and unavailable live state');
        file_put_contents($directory.'/payment-fixture-preview.html',$html);
    }
    echo "All $count checks passed.\n";
} finally {
    $db->query("DROP DATABASE `$name`");
}
