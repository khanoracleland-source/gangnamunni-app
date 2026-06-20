<?php
require __DIR__.'/_bootstrap.php';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$row  = sql_fetch("SELECT pubkey FROM rb_pwa_vapid WHERE domain='".sql_real_escape_string($host)."'");
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['publicKey'=>$row['pubkey'] ?? '']);
