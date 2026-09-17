<?php
if (!defined('G5_MYSQL_PASSWORD')) { require_once __DIR__ . '/dbconfig.php'; }
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$allowed_dbs = ['hms_saas', 'hms_saas_test'];
$target_db = isset($_REQUEST['db']) ? $_REQUEST['db'] : 'hms_saas';
if (!in_array($target_db, $allowed_dbs)) $target_db = 'hms_saas';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, $target_db, 3306);
if (!$connect) { echo json_encode(['success'=>false,'error'=>mysqli_connect_error()]); exit; }
mysqli_set_charset($connect, 'utf8mb4');
$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
$result = ['success'=>false,'action'=>$action,'db'=>$target_db];

try {
switch ($action) {

case 'status':
    $tables = ['gangnamunni_hospitals','gangnamunni_ads','gangnamunni_advertisers',
               'gangnamunni_doctors','hospital_images','hospital_reviews',
               'gangnamunni_categories','gangnamunni_bookings'];
    $counts = [];
    foreach ($tables as $t) {
        $r = @mysqli_query($connect, "SELECT COUNT(*) as cnt FROM `{$t}`");
        $counts[$t] = $r ? (int)mysqli_fetch_assoc($r)['cnt'] : 0;
    }
    $result = ['success'=>true,'db'=>$target_db,'counts'=>$counts];
    break;

case 'run_hospital_test':
    $tests = [];
    $name = isset($_POST['hospital_name']) ? $_POST['hospital_name'] : 'TC_Test_' . date('YmdHis');
    $now = date('Y-m-d H:i:s');
    $desc = isset($_POST['description']) ? $_POST['description'] : 'Auto test';
    $addr = isset($_POST['address']) ? $_POST['address'] : 'Seoul Gangnam';
    $ph = isset($_POST['phone']) ? $_POST['phone'] : '02-1234-5678';
    $em = isset($_POST['email']) ? $_POST['email'] : 'test@test.com';
    $bh = isset($_POST['business_hours']) ? $_POST['business_hours'] : '["Mon-Fri 10:00-19:00"]';
    $sub = isset($_POST['subway']) ? $_POST['subway'] : '["Gangnam Station"]';
    $cats = json_encode([1, 3]);

    // gangnamunni_hospitals: 실제 컬럼에 맞게 (is_advertiser, slug 없음)
    $sql = "INSERT INTO gangnamunni_hospitals
        (name, description, introduction, address, phone, email,
         profile_image_url, main_image_url, categories, treatment_tags,
         business_hours, subway_names, latitude, longitude,
         rating, review_count,
         active_phone_consultation, active_chat_consultation, active_general_consultation,
         created_at, updated_at)
        VALUES ('" . $esc($name) . "','" . $esc($desc) . "','Test intro',
                '" . $esc($addr) . "','" . $esc($ph) . "','" . $esc($em) . "',
                '/img/default.jpg','/img/default.jpg',
                '" . $esc($cats) . "','[]',
                '" . $esc($bh) . "','" . $esc($sub) . "',
                37.5012,127.0396,0.0,0,1,1,1,
                '{$now}','{$now}')";
    if (mysqli_query($connect, $sql)) {
        $hid = mysqli_insert_id($connect);
        $tests[] = ['test'=>'Hospital INSERT','status'=>'PASS','id'=>$hid];
    } else {
        $tests[] = ['test'=>'Hospital INSERT','status'=>'FAIL','err'=>mysqli_error($connect)];
        $result = ['success'=>false,'tests'=>$tests,'db'=>$target_db]; break;
    }

    // Doctor
    $dn = isset($_POST['doctor_name']) ? $_POST['doctor_name'] : 'Dr.Test';
    $ds = isset($_POST['doctor_specialty']) ? $_POST['doctor_specialty'] : 'Surgery';
    $mx = @mysqli_fetch_assoc(mysqli_query($connect,"SELECT COALESCE(MAX(hms_doctor_id),0)+1 as n FROM gangnamunni_doctors"));
    $nid = $mx ? (int)$mx['n'] : 1;
    if (mysqli_query($connect, "INSERT INTO gangnamunni_doctors (hms_doctor_id,hospital_id,name,specialty,bio,photo_url,created_at) VALUES ({$nid},{$hid},'" . $esc($dn) . "','" . $esc($ds) . "','Auto','/img/doc.jpg','{$now}')")) {
        $tests[] = ['test'=>'Doctor INSERT','status'=>'PASS'];
    } else {
        $tests[] = ['test'=>'Doctor INSERT','status'=>'FAIL','err'=>mysqli_error($connect)];
    }

    // Image (image_type enum: main,interior,exterior,facility,etc)
    if (mysqli_query($connect, "INSERT INTO hospital_images (hospital_id,image_url,image_type,sort_order,created_at) VALUES ({$hid},'/img/gal.jpg','interior',1,'{$now}')")) {
        $tests[] = ['test'=>'Image INSERT','status'=>'PASS'];
    } else {
        $tests[] = ['test'=>'Image INSERT','status'=>'FAIL','err'=>mysqli_error($connect)];
    }

    // Verify
    $v = mysqli_fetch_assoc(mysqli_query($connect,"SELECT id,name FROM gangnamunni_hospitals WHERE id={$hid}"));
    $tests[] = ['test'=>'Verify Hospital','status'=>($v && $v['name']===$name)?'PASS':'FAIL'];
    $d = mysqli_fetch_assoc(mysqli_query($connect,"SELECT id FROM gangnamunni_doctors WHERE hospital_id={$hid}"));
    $tests[] = ['test'=>'Verify Doctor','status'=>$d?'PASS':'FAIL'];
    $ic = mysqli_fetch_assoc(mysqli_query($connect,"SELECT COUNT(*) as c FROM hospital_images WHERE hospital_id={$hid}"));
    $tests[] = ['test'=>'Verify Image','status'=>((int)$ic['c']>0)?'PASS':'FAIL'];

    $p=0; foreach($tests as $t) if($t['status']==='PASS') $p++;
    $result = ['success'=>true,'tests'=>$tests,'summary'=>"{$p}/".count($tests).' PASS','hospital_id'=>$hid,'db'=>$target_db];
    break;

case 'run_ad_test':
    $tests = [];
    $now = date('Y-m-d H:i:s');
    $an = isset($_POST['advertiser_name']) ? $_POST['advertiser_name'] : 'TC_Ad_'.date('YmdHis');
    // gangnamunni_advertisers: hospital_id 컬럼 없음
    $cn = isset($_POST['contact_name']) ? $_POST['contact_name'] : 'TestContact';
    $ce = isset($_POST['contact_email']) ? $_POST['contact_email'] : 'ad@test.com';
    $cp = isset($_POST['contact_phone']) ? $_POST['contact_phone'] : '010-0000-0000';

    if (mysqli_query($connect, "INSERT INTO gangnamunni_advertisers (hospital_name,contact_name,contact_email,contact_phone,status,total_spent,created_at,updated_at) VALUES ('".$esc($an)."','".$esc($cn)."','".$esc($ce)."','".$esc($cp)."','active',0,'{$now}','{$now}')")) {
        $aid = mysqli_insert_id($connect);
        $tests[] = ['test'=>'Advertiser INSERT','status'=>'PASS','id'=>$aid];
    } else {
        $tests[] = ['test'=>'Advertiser INSERT','status'=>'FAIL','err'=>mysqli_error($connect)];
        $result = ['success'=>false,'tests'=>$tests,'db'=>$target_db]; break;
    }

    $pr = @mysqli_fetch_assoc(mysqli_query($connect,"SELECT id FROM gangnamunni_ad_pricing LIMIT 1"));
    $pid = $pr ? (int)$pr['id'] : 0;
    $at = isset($_POST['ad_title']) ? $_POST['ad_title'] : 'TC_Ad_'.date('YmdHis');
    // gangnamunni_ads: position 컬럼 없음
    $adid = 0;
    if (mysqli_query($connect, "INSERT INTO gangnamunni_ads (advertiser_id,pricing_id,title,ad_type,image_url,link_url,start_date,end_date,is_active,impression_count,click_count,created_at,updated_at) VALUES ({$aid},{$pid},'".$esc($at)."','banner','/img/ad.jpg','/hairwang/app/','{$now}',DATE_ADD('{$now}',INTERVAL 30 DAY),1,0,0,'{$now}','{$now}')")) {
        $adid = mysqli_insert_id($connect);
        $tests[] = ['test'=>'Ad INSERT','status'=>'PASS','id'=>$adid];
    } else {
        $tests[] = ['test'=>'Ad INSERT','status'=>'FAIL','err'=>mysqli_error($connect)];
    }

    // Financial states are never fabricated by this CRUD test runner.
    $tests[] = ['test'=>'Payment INSERT','status'=>'SKIP','reason'=>'Provider verification required'];

    // Verify
    $av = mysqli_fetch_assoc(mysqli_query($connect,"SELECT id,status FROM gangnamunni_advertisers WHERE id={$aid}"));
    $tests[] = ['test'=>'Verify Advertiser','status'=>$av?'PASS':'FAIL'];
    if ($adid) { $adv=mysqli_fetch_assoc(mysqli_query($connect,"SELECT is_active FROM gangnamunni_ads WHERE id={$adid}")); $tests[]=['test'=>'Verify Ad Active','status'=>($adv&&$adv['is_active']==1)?'PASS':'FAIL']; }

    $p=0; foreach($tests as $t) if($t['status']==='PASS') $p++;
    $result = ['success'=>true,'tests'=>$tests,'summary'=>"{$p}/".count($tests).' PASS','advertiser_id'=>$aid,'ad_id'=>$adid,'db'=>$target_db];
    break;

case 'verify_frontend':
    $tests = [];
    $hs=[]; $r=mysqli_query($connect,'SELECT id,name FROM gangnamunni_hospitals ORDER BY id DESC LIMIT 5');
    while($row=mysqli_fetch_assoc($r)) $hs[]=$row;
    $tests[]=['test'=>'Hospital List','status'=>count($hs)>0?'PASS':'FAIL','count'=>count($hs),'recent'=>$hs];

    $ads=[]; $r=mysqli_query($connect,'SELECT id,title FROM gangnamunni_ads WHERE is_active=1 ORDER BY id DESC LIMIT 5');
    while($row=mysqli_fetch_assoc($r)) $ads[]=$row;
    $tests[]=['test'=>'Active Ads','status'=>count($ads)>0?'PASS':'FAIL','count'=>count($ads)];

    // gangnamunni_categories: is_active, display_order (not active, order)
    $cs=[]; $r=mysqli_query($connect,'SELECT id,name FROM gangnamunni_categories WHERE is_active=1 ORDER BY display_order');
    while($row=mysqli_fetch_assoc($r)) $cs[]=$row;
    $tests[]=['test'=>'Categories','status'=>count($cs)>0?'PASS':'FAIL','count'=>count($cs)];

    $hid=isset($_POST['hospital_id'])?(int)$_POST['hospital_id']:0;
    if($hid){
        $d=mysqli_fetch_assoc(mysqli_query($connect,"SELECT h.name,(SELECT COUNT(*) FROM gangnamunni_doctors WHERE hospital_id=h.id) as docs,(SELECT COUNT(*) FROM hospital_images WHERE hospital_id=h.id) as imgs FROM gangnamunni_hospitals h WHERE h.id={$hid}"));
        $tests[]=['test'=>'Hospital Detail','status'=>$d?'PASS':'FAIL','name'=>$d?$d['name']:'','docs'=>$d?(int)$d['docs']:0,'imgs'=>$d?(int)$d['imgs']:0];
    }

    $p=0; foreach($tests as $t) if($t['status']==='PASS') $p++;
    $result=['success'=>true,'tests'=>$tests,'summary'=>"{$p}/".count($tests).' PASS','db'=>$target_db];
    break;

case 'verify_dashboard':
    $tests = [];
    $s = [];
    $s['hospitals']=(int)mysqli_fetch_assoc(mysqli_query($connect,'SELECT COUNT(*) as c FROM gangnamunni_hospitals'))['c'];
    $s['doctors']=(int)mysqli_fetch_assoc(mysqli_query($connect,'SELECT COUNT(*) as c FROM gangnamunni_doctors'))['c'];
    $s['active_ads']=(int)mysqli_fetch_assoc(mysqli_query($connect,'SELECT COUNT(*) as c FROM gangnamunni_ads WHERE is_active=1'))['c'];
    $s['advertisers']=(int)mysqli_fetch_assoc(mysqli_query($connect,"SELECT COUNT(*) as c FROM gangnamunni_advertisers WHERE status='active'"))['c'];
    $s['reviews']=(int)mysqli_fetch_assoc(mysqli_query($connect,'SELECT COUNT(*) as c FROM hospital_reviews'))['c'];
    $s['categories']=(int)mysqli_fetch_assoc(mysqli_query($connect,'SELECT COUNT(*) as c FROM gangnamunni_categories WHERE is_active=1'))['c'];
    $tests[]=['test'=>'Dashboard Stats','status'=>'PASS','stats'=>$s];

    $rec=[]; $r=mysqli_query($connect,'SELECT id,name,created_at FROM gangnamunni_hospitals ORDER BY created_at DESC LIMIT 3');
    while($row=mysqli_fetch_assoc($r)) $rec[]=$row;
    $tests[]=['test'=>'Recent Hospitals','status'=>count($rec)>0?'PASS':'FAIL','data'=>$rec];

    $p=0; foreach($tests as $t) if($t['status']==='PASS') $p++;
    $result=['success'=>true,'tests'=>$tests,'summary'=>"{$p}/".count($tests).' PASS','db'=>$target_db];
    break;

case 'cleanup_test':
    $del = [];
    // gangnamunni_ad_payments: ad_id 기반으로 삭제 (advertiser_id 컬럼 없음)
    $r=mysqli_query($connect,"SELECT id FROM gangnamunni_ads WHERE advertiser_id IN (SELECT id FROM gangnamunni_advertisers WHERE hospital_name LIKE 'TC_%')");
    $adids=[];
    if ($r) { while($row=mysqli_fetch_assoc($r)) $adids[]=$row['id']; }
    if ($adids) {
        $idstr=implode(',',$adids);
        @mysqli_query($connect,"DELETE FROM gangnamunni_ad_payments WHERE ad_id IN ({$idstr})");
        $del['ad_payments']=mysqli_affected_rows($connect);
    }
    @mysqli_query($connect,"DELETE FROM gangnamunni_ads WHERE advertiser_id IN (SELECT id FROM gangnamunni_advertisers WHERE hospital_name LIKE 'TC_%')");
    $del['ads']=mysqli_affected_rows($connect);
    @mysqli_query($connect,"DELETE FROM gangnamunni_advertisers WHERE hospital_name LIKE 'TC_%'");
    $del['advertisers']=mysqli_affected_rows($connect);
    $r=mysqli_query($connect,"SELECT id FROM gangnamunni_hospitals WHERE name LIKE 'TC_%'");
    $ids=[];while($row=mysqli_fetch_assoc($r))$ids[]=$row['id'];
    if($ids){
        $is=implode(',',$ids);
        @mysqli_query($connect,"DELETE FROM gangnamunni_doctors WHERE hospital_id IN ({$is})");$del['doctors']=mysqli_affected_rows($connect);
        @mysqli_query($connect,"DELETE FROM hospital_images WHERE hospital_id IN ({$is})");$del['images']=mysqli_affected_rows($connect);
        @mysqli_query($connect,"DELETE FROM gangnamunni_hospitals WHERE id IN ({$is})");$del['hospitals']=mysqli_affected_rows($connect);
    }
    $result=['success'=>true,'deleted'=>$del,'db'=>$target_db];
    break;

default:
    $result=['success'=>false,'error'=>'Need action'];
}
} catch (Exception $e) {
    $result = ['success'=>false, 'error'=>$e->getMessage()];
}

mysqli_close($connect);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
