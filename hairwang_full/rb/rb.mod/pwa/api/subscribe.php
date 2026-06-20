<?php
require __DIR__.'/_bootstrap.php';
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || empty($data['endpoint'])) { http_response_code(400); exit('BADREQ'); }

$endpoint   = $data['endpoint'];
$keys       = $data['keys'] ?? [];
$p256dh     = $keys['p256dh'] ?? '';
$auth       = $keys['auth'] ?? '';
$ip         = $_SERVER['REMOTE_ADDR'] ?? '';
$ua         = $_SERVER['HTTP_USER_AGENT'] ?? '';
$mb_id      = $is_member ? $member['mb_id'] : '';
$device_uid = isset($data['device_uid']) ? trim($data['device_uid']) : '';

if ($device_uid !== '' && !preg_match('/^[a-z0-9\-\_\.]{6,100}$/i', $device_uid)) {
    // 허용문자만
    $device_uid = substr(preg_replace('/[^a-z0-9\-\_\.]/i','',$device_uid), 0, 100);
}

$endpoint_q   = sql_real_escape_string($endpoint);
$p256dh_q     = sql_real_escape_string($p256dh);
$auth_q       = sql_real_escape_string($auth);
$mb_id_q      = sql_real_escape_string($mb_id);
$ip_q         = sql_real_escape_string($ip);
$ua_q         = sql_real_escape_string($ua);
$device_uid_q = sql_real_escape_string($device_uid);

// device_uid가 있으면 그걸로 1행만 유지 (업서트)
// 없을 때는 endpoint 기준(기존 동작 그대로)
if ($device_uid_q !== '') {
    sql_query("INSERT INTO rb_pwa_subscriptions
      (device_uid, endpoint, p256dh, auth, mb_id, ip, ua, installed, created_at, updated_at)
      VALUES ('{$device_uid_q}','{$endpoint_q}','{$p256dh_q}','{$auth_q}','{$mb_id_q}','{$ip_q}','{$ua_q}',1,'".G5_TIME_YMDHIS."','".G5_TIME_YMDHIS."')
      ON DUPLICATE KEY UPDATE
        endpoint=VALUES(endpoint),
        p256dh=VALUES(p256dh),
        auth=VALUES(auth),
        mb_id=VALUES(mb_id),
        ip=VALUES(ip),
        ua=VALUES(ua),
        installed=1,
        updated_at='".G5_TIME_YMDHIS."'");
} else {
    sql_query("INSERT INTO rb_pwa_subscriptions
      (endpoint,p256dh,auth,mb_id,ip,ua,installed,created_at,updated_at)
      VALUES ('{$endpoint_q}','{$p256dh_q}','{$auth_q}','{$mb_id_q}','{$ip_q}','{$ua_q}',1,'".G5_TIME_YMDHIS."','".G5_TIME_YMDHIS."')
      ON DUPLICATE KEY UPDATE
        p256dh=VALUES(p256dh),
        auth=VALUES(auth),
        mb_id=VALUES(mb_id),
        ip=VALUES(ip),
        ua=VALUES(ua),
        installed=1,
        updated_at=NOW()");
}

sql_query("INSERT INTO rb_pwa_install_log (mb_id, ip, ua, action, reg_dt)
VALUES ('{$mb_id_q}','{$ip_q}','{$ua_q}','install',NOW())");

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true]);