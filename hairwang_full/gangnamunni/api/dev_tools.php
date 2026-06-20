<?php
/**
 * Dev Tools API - admin only
 * Actions: db_stats, health_check, page_check, clear_cache, sample_create, sample_delete, change_log
 */
header('Content-Type: application/json; charset=utf-8');

// Auth
session_start();
$is_admin = !empty($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
if (!$is_admin) { http_response_code(403); echo json_encode(['error'=>'Unauthorized']); exit; }

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['error'=>'DB failed']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

case 'db_stats':
    $tables = ['gangnamunni_hospitals','gangnamunni_reviews','gangnamunni_tips','gangnamunni_events',
               'gangnamunni_ads','gangnamunni_bookings','gangnamunni_consultations','gangnamunni_promos',
               'gangnamunni_columns','gangnamunni_doctors','gangnamunni_members','gangnamunni_treatments',
               'gangnamunni_settings_log'];
    $stats = [];
    foreach ($tables as $t) {
        $r = @mysqli_query($connect, "SELECT COUNT(*) as c FROM $t");
        $stats[str_replace('gangnamunni_','',$t)] = $r ? (int)mysqli_fetch_assoc($r)['c'] : -1;
    }
    // Disk usage
    $r = @mysqli_query($connect, "SELECT ROUND(SUM(data_length + index_length) / 1048576, 1) as mb FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name LIKE 'gangnamunni_%'");
    $stats['_db_size_mb'] = $r ? (float)mysqli_fetch_assoc($r)['mb'] : 0;
    echo json_encode(['success'=>true, 'stats'=>$stats]);
    break;

case 'health_check':
    $checks = [];
    // DB
    $checks['db'] = $connect ? 'OK' : 'FAIL';
    // PHP
    $checks['php'] = PHP_VERSION;
    // Disk
    $df = @disk_free_space('/');
    $checks['disk_free_gb'] = $df ? round($df / 1073741824, 1) : -1;
    // Memory
    $checks['memory_mb'] = round(memory_get_usage(true) / 1048576, 1);
    // OPcache
    $checks['opcache'] = function_exists('opcache_get_status') ? (opcache_get_status(false)['opcache_enabled'] ?? false ? 'ON' : 'OFF') : 'N/A';
    // Session
    $checks['session_path'] = session_save_path();
    // Uptime
    $uptime = @file_get_contents('/proc/uptime');
    if ($uptime) {
        $secs = (int)explode(' ', $uptime)[0];
        $checks['uptime'] = intdiv($secs, 86400) . 'd ' . intdiv($secs % 86400, 3600) . 'h';
    }
    echo json_encode(['success'=>true, 'checks'=>$checks]);
    break;

case 'page_check':
    $pages = ['index.php','hospitals.php','events.php','reviews.php','tips.php','columns.php',
              'booking.php','dashboard.php','admin_hospitals.php','admin_events.php'];
    $results = [];
    $base_url = 'http://localhost/hairwang/gangnamunni/';
    foreach ($pages as $pg) {
        $ch = curl_init($base_url . $pg);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>5, CURLOPT_NOBODY=>true]);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $time = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000);
        curl_close($ch);
        $results[$pg] = ['status'=>$code, 'ms'=>$time];
    }
    echo json_encode(['success'=>true, 'pages'=>$results]);
    break;

case 'clear_cache':
    $cleared = [];
    if (function_exists('opcache_reset')) { opcache_reset(); $cleared[] = 'opcache'; }
    $cleared[] = 'done';
    echo json_encode(['success'=>true, 'cleared'=>$cleared]);
    break;

case 'sample_create':
    $type = $_POST['type'] ?? '';
    $count = 0;
    if ($type === 'hospitals') {
        for ($i = 1; $i <= 5; $i++) {
            $name = "Sample Hospital $i";
            $r = mysqli_query($connect, "INSERT INTO gangnamunni_hospitals (name, description, address, rating, review_count, is_active, status) VALUES ('$name', 'Test hospital for dev', 'Seoul Gangnam-gu Test $i', " . (3.5 + $i * 0.3) . ", $i, 1, 'approved')");
            if ($r) $count++;
        }
    } elseif ($type === 'reviews') {
        for ($i = 1; $i <= 3; $i++) {
            $r = mysqli_query($connect, "INSERT INTO gangnamunni_reviews (hospital_id, hospital_name, author_name, rating, title, content, status) VALUES (1, 'Sample Hospital', 'Tester$i', " . (3 + $i) . ", 'Sample Review $i', 'This is a test review content.', 'approved')");
            if ($r) $count++;
        }
    } elseif ($type === 'events') {
        for ($i = 1; $i <= 3; $i++) {
            $r = mysqli_query($connect, "INSERT INTO gangnamunni_events (title, description, category, original_price, discount_price, discount_rate, start_date, end_date, is_active) VALUES ('Sample Event $i', 'Test event for dev', 'test', 100000, 80000, 20, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1)");
            if ($r) $count++;
        }
    }
    echo json_encode(['success'=>true, 'type'=>$type, 'created'=>$count]);
    break;

case 'sample_delete':
    $deleted = [];
    $r = mysqli_query($connect, "DELETE FROM gangnamunni_hospitals WHERE name LIKE 'Sample Hospital%'");
    $deleted['hospitals'] = mysqli_affected_rows($connect);
    $r = mysqli_query($connect, "DELETE FROM gangnamunni_reviews WHERE author_name LIKE 'Tester%' AND title LIKE 'Sample%'");
    $deleted['reviews'] = mysqli_affected_rows($connect);
    $r = mysqli_query($connect, "DELETE FROM gangnamunni_events WHERE title LIKE 'Sample Event%'");
    $deleted['events'] = mysqli_affected_rows($connect);
    echo json_encode(['success'=>true, 'deleted'=>$deleted]);
    break;

case 'change_log':
    $limit = min((int)($_GET['limit'] ?? 10), 50);
    $stmt = $connect->prepare("SELECT id, admin_user, admin_name, setting_table, setting_key, old_value, new_value, source, created_at FROM gangnamunni_settings_log ORDER BY id DESC LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = [];
    while ($row = $result->fetch_assoc()) $logs[] = $row;
    echo json_encode(['success'=>true, 'logs'=>$logs, 'total'=>count($logs)]);
    break;

default:
    echo json_encode(['error'=>'Unknown action', 'available'=>['db_stats','health_check','page_check','clear_cache','sample_create','sample_delete','change_log']]);
}
