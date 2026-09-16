<?php
/** Run in the local gangnamunni container. Uses and deletes ONLY a random test DB. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../../hairwang_full/gangnamunni/includes/booking_payment_service.php';
putenv('PORTONE_API_KEY=fixture-key');
putenv('PORTONE_API_SECRET=fixture-secret');
putenv('PORTONE_SANDBOX=1');
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
$order = ['merchant_uid'=>'BK_fixture', 'amount'=>100000, 'provider'=>null, 'provider_transaction_id'=>null];
$remote = ['merchant_uid'=>'BK_fixture','imp_uid'=>'imp_fixture','amount'=>100000,'currency'=>'KRW','sandbox'=>true,'status'=>'paid','cancel_amount'=>0,'paid_at'=>time()-86400];
expect(booking_portone_validate($order, $remote) === 'portone_test', 'sandbox payments isolated');
foreach (['merchant_uid'=>'BK_other','amount'=>1,'currency'=>'USD','sandbox'=>false,'cancel_amount'=>100001] as $key=>$value) {
    rejects(fn()=>booking_portone_validate($order, array_replace($remote, [$key=>$value])), 'reject mismatched ' . $key);
}
rejects(fn()=>booking_portone_validate($order, array_diff_key($remote,['sandbox'=>1])), 'missing environment fails closed');
$broken = new BookingPortOneClient(fn()=>[200, '{"code":-1,"response":null}']);
rejects(fn()=>$broken->payment('imp_fixture'), 'HTTP 200 with API error rejected');
$broken = new BookingPortOneClient(fn()=>[200, '<html>bad gateway</html>']);
rejects(fn()=>$broken->payment('imp_fixture'), 'malformed API response rejected');
$broken = new BookingPortOneClient(fn()=>[503, '{}']);
rejects(fn()=>$broken->payment('imp_fixture'), 'HTTP failure rejected');
rejects(fn()=>$broken->payment('../users/getToken'), 'payment path injection rejected');

foreach (['refunded','cancelled','failed'] as $state) {
    expect(booking_payment_display_state(['status'=>$state,'provider'=>'portone_test','amount'=>100000],true)===$state, 'terminal state overrides preview: '.$state);
}
expect(booking_payment_display_state(['status'=>'paid','provider'=>'portone_test','amount'=>100000])==='test_paid', 'sandbox is labelled as test payment, not unpaid preview');
putenv('G5_PG_PAYMENTS_ENABLED=1'); putenv('PORTONE_CHANNEL_CARD=channel-fixture'); putenv('PORTONE_IMP_CODE=imp-fixture');
putenv('BOOKING_PAYMENT_BASE_URL=http://localhost/gangnamunni');
expect(!booking_payment_checkout_ready(),'invalid public URL disables real checkout');
putenv('BOOKING_PAYMENT_BASE_URL=https://fixture.invalid/gangnamunni');
expect(booking_payment_checkout_ready(),'complete sandbox configuration enables checkout');
putenv('PORTONE_API_SECRET=');
expect(!booking_payment_checkout_ready(),'missing secret disables real checkout');
putenv('PORTONE_API_SECRET=fixture-secret'); putenv('G5_PG_PAYMENTS_ENABLED=0'); putenv('PORTONE_CHANNEL_CARD=');
rejects(fn()=>booking_portone_validate($order,array_replace($remote,['paid_at'=>0])), 'missing provider payment time rejected');

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
    $remote['merchant_uid'] = $merchant;
    $cancelCalls = 0;
    $transport = function($method,$path,$body,$headers) use (&$remote,&$cancelCalls) {
        if ($path === '/users/getToken') return [200,json_encode(['code'=>0,'response'=>['access_token'=>'fixture-token']])];
        if ($path === '/payments/cancel') {
            expect($body['checksum'] === 100000-$remote['cancel_amount'], 'refund checksum uses remaining balance');
            $cancelCalls++;
            $remote['cancel_amount'] += $body['amount'];
            if ($remote['cancel_amount'] === 100000) $remote['status']='cancelled';
        }
        return [200,json_encode(['code'=>0,'response'=>$remote])];
    };
    $client = new BookingPortOneClient($transport);
    rejects(fn()=>booking_payment_sync($db,$merchant,'imp_wrong','webhook',$client), 'provider response must match the requested transaction');
    $paid = booking_payment_sync($db,$merchant,'imp_fixture','webhook',$client);
    expect($paid['status'] === 'paid', 'provider-confirmed payment transitions to paid');
    expect($paid['paid_at'] === gmdate('Y-m-d H:i:s', $remote['paid_at'] + 9*3600), 'provider payment date preserved for settlement');
    putenv('PORTONE_SANDBOX=0');
    booking_payment_sync($db,$merchant,'imp_fixture','webhook',$client);
    expect(true, 'existing sandbox payment still reconciles after switching to live');
    putenv('PORTONE_SANDBOX=1');
    booking_payment_sync($db,$merchant,'imp_fixture','webhook',$client);
    expect((int)$db->query('SELECT COUNT(*) AS n FROM gangnamunni_payment_events')->fetch_assoc()['n'] === 1, 'duplicate callback creates no second event');
    $r1 = str_repeat('a',64);
    $partial = booking_payment_refund($db,$merchant,30000,'fixture refund',$r1,1,$client);
    expect($partial['status'] === 'partially_refunded' && (int)$partial['refunded_amount'] === 30000, 'partial refund preserves remaining balance');
    booking_payment_refund($db,$merchant,30000,'fixture refund',$r1,1,$client);
    expect($cancelCalls === 1, 'same refund request does not charge cancellation twice');
    booking_payment_sync($db,$merchant,'imp_fixture','webhook',$client);
    expect((int)$db->query('SELECT COUNT(*) AS n FROM gangnamunni_payment_events')->fetch_assoc()['n'] === 2, 'duplicate refund webhook is idempotent');
    rejects(fn()=>booking_payment_refund($db,$merchant,80000,'too much',str_repeat('b',64),1,$client), 'over-refund rejected before provider call');
    $full = booking_payment_refund($db,$merchant,70000,'fixture remainder',str_repeat('c',64),1,$client);
    expect($full['status'] === 'refunded' && (int)$full['refunded_amount'] === 100000, 'full refund accounted correctly');
    booking_payment_sync($db,$merchant,'imp_fixture','webhook',$client);
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
    expect((int)$db->query("SELECT COUNT(*) AS n FROM gangnamunni_platform_payments WHERE status IN ('paid','partially_refunded','refunded') AND provider NOT IN ('mock_booking_payment','portone_test')")->fetch_assoc()['n'] === 0, 'sandbox and previews excluded from revenue');
    $is_member = true; $member = ['mb_id'=>'unrelated','mb_hp'=>'01099999999'];
    expect(booking_payment_owned($db,1) === null, 'unrelated user cannot access payment');
    $member = ['mb_id'=>'fixture'];
    expect(booking_payment_owned($db,1)['id'] == 1, 'owner can access payment');
    // The same real provider transaction cannot be used to pay a second order.
    $remote['merchant_uid']=$second['merchant_uid']; $remote['cancel_amount']=0; $remote['status']='paid';
    rejects(fn()=>booking_payment_sync($db,$second['merchant_uid'],'imp_fixture','webhook',$client), 'transaction reuse across orders rejected');
    expect(booking_payment_order($db,2)['status'] === 'pending', 'rejected transaction leaves order unchanged');
    $remote['imp_uid']='imp_second';
    booking_payment_sync($db,$second['merchant_uid'],'imp_second','webhook',$client);
    $timeoutClient = new BookingPortOneClient(function($method,$path,$body,$headers) use ($transport) {
        $response = $transport($method,$path,$body,$headers);
        if ($path === '/payments/cancel') throw new RuntimeException('Simulated network timeout AFTER refund.');
        return $response;
    });
    $timeoutId = str_repeat('d',64);
    rejects(fn()=>booking_payment_refund($db,$second['merchant_uid'],20000,'timeout test',$timeoutId,1,$timeoutClient), 'ambiguous cancellation does not claim success');
    $callsBefore = $cancelCalls;
    $recovered = booking_payment_refund($db,$second['merchant_uid'],20000,'timeout test',$timeoutId,1,$client);
    expect((int)$recovered['refunded_amount'] === 20000 && $cancelCalls === $callsBefore, 'timeout retry recovers without another refund');

    $db->query("INSERT INTO gangnamunni_bookings VALUES (4,1,'Fixture Hospital','fixture','Fixture','01000000000','Fixture treatment',100000,'confirmed')");
    $fourth = platform_payment_create_booking($db,4);
    $remote['merchant_uid']=$fourth['merchant_uid']; $remote['imp_uid']='imp_fourth';
    $remote['status']='cancelled'; $remote['cancel_amount']=100000;
    $db->query("CREATE TRIGGER fail_refund_fixture BEFORE INSERT ON gangnamunni_payment_events FOR EACH ROW BEGIN IF NEW.event_type='refund_verified' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected refund persistence failure'; END IF; END");
    rejects(fn()=>booking_payment_sync($db,$fourth['merchant_uid'],'imp_fourth','webhook',$client), 'refund database failure surfaced');
    expect(booking_payment_order($db,4)['status'] === 'pending', 'first cancelled observation rolls payment and refund back together');
    $db->query('DROP TRIGGER fail_refund_fixture');
    $cancelledFirst = booking_payment_sync($db,$fourth['merchant_uid'],'imp_fourth','webhook',$client);
    expect($cancelledFirst['status']==='refunded' && (int)$cancelledFirst['refunded_amount']===100000, 'first fully cancelled observation records payment and refund together');
    $db->query("INSERT INTO gangnamunni_bookings VALUES (5,1,'Fixture Hospital','fixture','Fixture','01000000000','Fixture treatment',100000,'confirmed')");
    $fourth = platform_payment_create_booking($db,5);
    $remote['merchant_uid']=$fourth['merchant_uid']; $remote['imp_uid']='imp_fifth';
    $remote['status']='paid'; $remote['cancel_amount']=0;
    booking_payment_sync($db,$fourth['merchant_uid'],'imp_fifth','webhook',$client);
    $rejectClient = new BookingPortOneClient(function($method,$path,$body,$headers) use ($transport) {
        if ($path === '/payments/cancel') return [200,'{"code":-1,"message":"fixture rejection","response":null}'];
        return $transport($method,$path,$body,$headers);
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
    $db->query("UPDATE gangnamunni_platform_payments SET provider='portone', status='paid',refunded_amount=0,paid_at=DATE_SUB(CURDATE(),INTERVAL 1 MONTH) WHERE booking_id=1");
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
