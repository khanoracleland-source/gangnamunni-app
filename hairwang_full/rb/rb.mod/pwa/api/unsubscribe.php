<?php
require __DIR__.'/_bootstrap.php';
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$endpoint   = $data['endpoint'] ?? '';
$device_uid = $data['device_uid'] ?? '';
$reason     = $data['reason'] ?? 'uninstall';

$endpoint_q   = sql_real_escape_string($endpoint);
$device_uid   = trim($device_uid);
if ($device_uid !== '' && !preg_match('/^[a-z0-9\-\_\.]{6,100}$/i', $device_uid)) {
    $device_uid = substr(preg_replace('/[^a-z0-9\-\_\.]/i','',$device_uid), 0, 100);
}
$device_uid_q = sql_real_escape_string($device_uid);

$mb_id_q = sql_real_escape_string($member['mb_id'] ?? '');
$ip_q    = sql_real_escape_string($_SERVER['REMOTE_ADDR'] ?? '');
$ua_q    = sql_real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '');
$reason_q= sql_real_escape_string($reason);

// device_uid가 있으면 "없으면 생성, 있으면 갱신"으로 tombstone(installed=0) 기록
if ($device_uid_q !== '') {
    sql_query("INSERT INTO rb_pwa_subscriptions
      (device_uid, endpoint, mb_id, ip, ua, installed, created_at, updated_at)
      VALUES ('{$device_uid_q}','{$endpoint_q}','{$mb_id_q}','{$ip_q}','{$ua_q}',0,'".G5_TIME_YMDHIS."','".G5_TIME_YMDHIS."')
      ON DUPLICATE KEY UPDATE
        endpoint=VALUES(endpoint),
        mb_id=VALUES(mb_id),
        ip=VALUES(ip),
        ua=VALUES(ua),
        installed=0,
        updated_at='".G5_TIME_YMDHIS."'");
} else if ($endpoint_q !== '') {
    // 구버전/폴백: endpoint만으로 비활성화
    sql_query("UPDATE rb_pwa_subscriptions SET installed=0, updated_at='".G5_TIME_YMDHIS."' WHERE endpoint='{$endpoint_q}'");
} else {
    http_response_code(400);
    exit('BADREQ');
}

sql_query("INSERT INTO rb_pwa_install_log (mb_id, ip, ua, action, reg_dt)
VALUES ('{$mb_id_q}','{$ip_q}','{$ua_q}','{$reason_q}','".G5_TIME_YMDHIS."')");

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true]);
