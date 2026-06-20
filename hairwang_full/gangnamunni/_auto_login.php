<?php
session_start();
require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB error");
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/includes/admin_auth.php';
if (!check_admin_login()) {
    admin_login('admin', 'admin123', $connect);
}
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard.php';
if (!preg_match('/^[a-zA-Z0-9_]+\.php$/', $page)) { die('Invalid'); }
if (!file_exists(__DIR__ . '/' . $page)) { die('Not found: ' . $page); }
include __DIR__ . '/' . $page;
