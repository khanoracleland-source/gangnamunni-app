<?php
require __DIR__.'/_bootstrap.php';
use Minishlink\WebPush\VAPID;
if (!$is_admin || $is_admin!=='super'){ http_response_code(403); exit('FORBIDDEN'); }

$keys = VAPID::createVapidKeys();
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

sql_query("INSERT INTO rb_pwa_vapid (domain,pubkey,prikey,reg_dt)
VALUES ('".sql_real_escape_string($host)."','".sql_real_escape_string($keys['publicKey'])."','".sql_real_escape_string($keys['privateKey'])."','".G5_TIME_YMDHIS."')
ON DUPLICATE KEY UPDATE pubkey=VALUES(pubkey), prikey=VALUES(prikey), reg_dt=VALUES(reg_dt)");

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok'=>true,'publicKey'=>$keys['publicKey']]);
