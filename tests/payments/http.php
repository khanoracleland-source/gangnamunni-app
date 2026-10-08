<?php
/** HTTP contract checks for actual endpoints with isolated identity/DB fixtures.
 * Runs only from CLI; no production auth bypass or public fixture endpoint is installed.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli(getenv('G5_MYSQL_HOST') ?: '127.0.0.1', getenv('G5_MYSQL_USER') ?: 'root', getenv('G5_MYSQL_PASSWORD') ?: '', '', (int)(getenv('G5_MYSQL_PORT') ?: 3306));
$name = 'codex_payment_http_' . bin2hex(random_bytes(5));
$dir = sys_get_temp_dir() . '/' . $name;
mkdir($dir,0700);
$db->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
$server = null;
$count = 0;
function check_http($ok, string $label): void {
    global $count;
    if (!$ok) throw new RuntimeException('FAIL: '.$label);
    echo 'PASS: '.$label."\n"; $count++;
}
try {
    $db->select_db($name);
    $root = realpath(__DIR__.'/../../hairwang_full/gangnamunni');
    $schema = file_get_contents($root.'/sql/platform_payments_schema.sql');
    $schema = substr($schema,strpos($schema,'CREATE TABLE IF NOT EXISTS gangnamunni_platform_payments'));
    $schema .= file_get_contents($root.'/sql/booking_payment_previews.sql');
    foreach(explode(';',$schema) as $sql) if(trim($sql)!=='') $db->query($sql);
    $db->query("CREATE TABLE gangnamunni_bookings (id BIGINT PRIMARY KEY,hospital_id BIGINT,hospital_name VARCHAR(255),mb_id VARCHAR(100),name VARCHAR(100),phone VARCHAR(30),treatment VARCHAR(255),price INT,status VARCHAR(30),booking_date DATE,booking_time TIME)");
    $db->query("CREATE TABLE gangnamunni_hospitals (id BIGINT PRIMARY KEY,name VARCHAR(255),profile_image_url VARCHAR(255),address VARCHAR(255))");
    $db->query("INSERT INTO gangnamunni_hospitals VALUES (1,'Fixture Hospital','','Seoul')");
    $db->query("INSERT INTO gangnamunni_bookings VALUES (1,1,'Fixture Hospital','owner','Fixture','01000000000','Treatment',100000,'confirmed','2026-10-01','10:00:00')");
    require_once $root.'/includes/payment_core.php';
    platform_payment_create_booking($db,1);
    $endpoints = ['booking_payment.php','booking_payment_success.php','booking_payment_mock_complete.php','booking_payment_request.php','booking_payment_verify.php','api_wallet.php'];
    foreach($endpoints as $file) {
        $source=file_get_contents($root.'/'.$file);
        foreach (["require_once __DIR__ . '/includes/init.php';", "@include_once __DIR__ . '/includes/sso_auto_login.php';", "require_once __DIR__ . '/../dbconfig.php';", 'session_start();'] as $bootstrap) {
            $source=str_replace($bootstrap,'',$source);
        }
        $source=str_replace('__DIR__',var_export($root,true),$source);
        file_put_contents($dir.'/'.$file,$source);
    }
    $router= <<<'ROUTER'
<?php
if (!str_starts_with((string)getenv('PAYMENT_HTTP_DATABASE'),'codex_payment_http_')) { http_response_code(500); exit; }
putenv('G5_PG_PAYMENTS_ENABLED=0'); putenv('BOOKING_PAYMENT_METHODS='); putenv('BOOKING_PAYMENT_PREVIEW_ENABLED=1');
if (($_SERVER['HTTP_X_FIXTURE_PREVIEW'] ?? '') === 'disabled') putenv('BOOKING_PAYMENT_PREVIEW_ENABLED=0');
$dbname=getenv('PAYMENT_HTTP_DATABASE');
foreach (['HOST','USER','PASSWORD','PORT'] as $key) define('G5_MYSQL_'.$key,getenv('G5_MYSQL_'.$key));
define('G5_MYSQL_DB',$dbname);
session_save_path(__DIR__); session_start();
$_SESSION=['csrf_token'=>'fixture-csrf','wallet_csrf_token'=>'wallet-fixture'];
$identity=$_SERVER['HTTP_X_FIXTURE_IDENTITY'] ?? '';
$is_member=$identity !== '';
$member=$is_member ? ['mb_id'=>$identity,'mb_name'=>'Fixture','mb_hp'=>'01000000000'] : [];
if ($identity === 'viewer' || $identity === 'super_admin') {
    $_SESSION['ad_admin_logged_in']=true; $_SESSION['ad_admin_role']=$identity; $_SESSION['ad_admin_id']=1;
}
$connect=new mysqli(G5_MYSQL_HOST,G5_MYSQL_USER,G5_MYSQL_PASSWORD,$dbname,(int)G5_MYSQL_PORT);
$connect->set_charset('utf8mb4');
function term($key) { return $key; }
$file=basename(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if (!in_array($file,['booking_payment.php','booking_payment_success.php','booking_payment_mock_complete.php','booking_payment_request.php','booking_payment_verify.php','api_wallet.php'],true)) {http_response_code(404); exit;}
require __DIR__.'/'.$file;
ROUTER;
    file_put_contents($dir.'/router.php',$router);
    putenv('PAYMENT_HTTP_DATABASE='.$name);
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
    if (!$socket) throw new RuntimeException('No loopback port available.');
    $address=stream_socket_get_name($socket,false); fclose($socket);
    $server=proc_open([PHP_BINARY,'-d','display_errors=0','-S',$address,$dir.'/router.php'],[0=>['pipe','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server.log','a']],$pipes,$dir);
    if(!is_resource($server)) throw new RuntimeException('Test HTTP server failed.');
    fclose($pipes[0]);
    for($i=0;$i<50;$i++) {
        $ready=@stream_socket_client('tcp://'.$address,$err,$text,0.1);
        if($ready){fclose($ready);break;}
        usleep(20000);
    }
    $request=function($path,$method='GET',$body=[],$identity='owner',$headers=[]) use($address) {
        $ch=curl_init('http://'.$address.'/'.$path);
        $headers[]='X-Fixture-Identity: '.$identity;
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>5]);
        if($method==='POST') curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($body));
        $result=curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        return [$code,$result,json_decode((string)$result,true)];
    };
    foreach (['booking_payment_mock_complete.php','booking_payment_request.php','booking_payment_verify.php'] as $endpoint) {
        [$code,$body]=$request($endpoint);
        check_http($code===405,'GET rejected: '.$endpoint);
        [$code,$body]=$request($endpoint,'POST',['booking_id'=>1,'provider'=>'card']);
        check_http($code===403,'CSRF required: '.$endpoint);
        [$code,$body]=$request($endpoint,'POST',['booking_id'=>1,'provider'=>'card','csrf_token'=>'fixture-csrf'],'other');
        check_http($code===404,'other owner rejected: '.$endpoint);
    }
    [$code,$body]=$request('booking_payment_success.php?booking_id=1');
    check_http($code===200 && str_contains($body,'결제 확인 중') && !str_contains($body,'결제가 완료되었어요!'),'unpaid success URL does not claim payment');
    [$code,$body]=$request('booking_payment_mock_complete.php','POST',['booking_id'=>1,'csrf_token'=>'fixture-csrf'],'owner',['X-Fixture-Preview: disabled']);
    check_http($code===403,'preview off switch enforced by endpoint');
    $db->query('RENAME TABLE gangnamunni_booking_payment_previews TO fixture_saved_previews');
    [$code,$body,$data]=$request('booking_payment_mock_complete.php','POST',['booking_id'=>1,'csrf_token'=>'fixture-csrf']);
    check_http($code===500 && $data['code']==='MOCK_PAYMENT_FAILED','missing preview migration reproduces the reported server error');
    check_http($db->query('SELECT status FROM gangnamunni_platform_payments LIMIT 1')->fetch_assoc()['status']==='pending','missing preview storage never marks the order paid');
    $db->query('RENAME TABLE fixture_saved_previews TO gangnamunni_booking_payment_previews');
    [$code,$body,$data]=$request('booking_payment_mock_complete.php','POST',['booking_id'=>1,'csrf_token'=>'fixture-csrf']);
    check_http($code===200 && $data['success']===true,'preview endpoint works for owner');
    [$code,$body,$data]=$request('booking_payment_mock_complete.php','POST',['booking_id'=>1,'csrf_token'=>'fixture-csrf']);
    check_http($code===200 && $data['success']===true,'repeat preview endpoint is harmless');
    check_http((int)$db->query('SELECT COUNT(*) AS n FROM gangnamunni_booking_payment_previews')->fetch_assoc()['n']===1,'HTTP preview writes once');
    check_http($db->query('SELECT status FROM gangnamunni_platform_payments LIMIT 1')->fetch_assoc()['status']==='pending','HTTP preview leaves financial order pending');
    [$code,$body]=$request('booking_payment_success.php?booking_id=1');
    check_http($code===200 && str_contains($body,'결제 없이 계속했어요'),'preview success is explicitly unpaid');
    [$code,$body,$data]=$request('booking_payment_request.php','POST',['booking_id'=>1,'csrf_token'=>'fixture-csrf','provider'=>'card']);
    check_http($code===503 && $data['code']==='PAYMENT_UNAVAILABLE','unconfigured checkout fails without pretending payment');
    [$code,$body]=$request('booking_payment.php?booking_id=999');
    check_http($code===404 && !str_contains($body,'Warning:'),'unknown booking renders clean 404');
    [$code,$body]=$request('booking_payment.php?booking_id=1');
    check_http($code===200 && str_contains($body,'id="previewPayBtn"') && !str_contains($body,'id="payBtn"') && str_contains($body,'id="paymentMethodNotice"'),'unconfigured checkout explains unavailable methods and offers preview');
    $db->query("UPDATE gangnamunni_platform_payments SET status='refunded',provider='toss_test',provider_transaction_id='tx_fixture',verified_amount=amount,verified_at=NOW(),verification_source='server_query',paid_at=NOW(),refunded_amount=amount,refunded_at=NOW()");
    [$code,$body]=$request('booking_payment.php?booking_id=1');
    check_http($code===200 && str_contains($body,'환불이 완료된 결제입니다.') && !str_contains($body,'id="payBtn"') && !str_contains($body,'id="previewPayBtn"'),'refunded checkout offers neither recharging nor bypass');
    [$code,$body]=$request('booking_payment_success.php?booking_id=1');
    check_http($code===200 && str_contains($body,'환불 완료') && !str_contains($body,'결제 없이 계속했어요'),'refund overrides earlier preview on success page');
    [$code]=$request('api_wallet.php','GET',[],'');
    check_http($code===401,'wallet rejects unauthenticated request');
    [$code]=$request('api_wallet.php','POST',['action'=>'charge','hospital_id'=>1,'amount'=>100000],'viewer');
    check_http($code===403,'viewer cannot change wallet');
    [$code]=$request('api_wallet.php','POST',['action'=>'charge','hospital_id'=>1,'amount'=>100000],'super_admin');
    check_http($code===403,'wallet mutation requires CSRF even for super admin');
    echo "All $count HTTP checks passed.\n";
} catch (Throwable $e) {
    if (file_exists($dir.'/server.log')) fwrite(STDERR,file_get_contents($dir.'/server.log'));
    throw $e;
} finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    $db->query("DROP DATABASE `$name`");
    foreach(glob($dir.'/*') as $file) unlink($file);
    rmdir($dir);
}
