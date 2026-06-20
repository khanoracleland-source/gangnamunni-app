<?php
/**
 * 서버 상태 API — 매뉴얼 허브에서 AJAX 호출
 * 디스크, CPU, 메모리, DB, 서비스 상태 반환
 * @created 2026-04-06
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$status = [];

// 1. 디스크
$disk_total = disk_total_space('/');
$disk_free = disk_free_space('/');
$disk_used = $disk_total - $disk_free;
$disk_pct = round($disk_used / $disk_total * 100, 1);
$status['disk'] = [
    'total_gb' => round($disk_total / 1073741824, 1),
    'used_gb' => round($disk_used / 1073741824, 1),
    'free_gb' => round($disk_free / 1073741824, 1),
    'percent' => $disk_pct,
];

// 2. 메모리
$mem = [];
$meminfo = @file_get_contents('/proc/meminfo');
if ($meminfo) {
    preg_match('/MemTotal:\s+(\d+)/', $meminfo, $m);
    $mem_total = isset($m[1]) ? intval($m[1]) * 1024 : 0;
    preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $m);
    $mem_avail = isset($m[1]) ? intval($m[1]) * 1024 : 0;
    $mem_used = $mem_total - $mem_avail;
    $status['memory'] = [
        'total_gb' => round($mem_total / 1073741824, 1),
        'used_gb' => round($mem_used / 1073741824, 1),
        'free_gb' => round($mem_avail / 1073741824, 1),
        'percent' => $mem_total > 0 ? round($mem_used / $mem_total * 100, 1) : 0,
    ];
}

// 3. CPU 로드
$load = sys_getloadavg();
$cpu_cores = intval(trim(shell_exec('nproc 2>/dev/null') ?? '1'));
$status['cpu'] = [
    'load_1m' => $load[0] ?? 0,
    'load_5m' => $load[1] ?? 0,
    'load_15m' => $load[2] ?? 0,
    'cores' => $cpu_cores,
    'percent' => $cpu_cores > 0 ? round(($load[0] / $cpu_cores) * 100, 1) : 0,
];

// 4. 업타임
$uptime_str = trim(shell_exec('uptime -p 2>/dev/null') ?? '');
$status['uptime'] = $uptime_str;

// 5. DB 상태
require_once __DIR__ . '/dbconfig.php';
$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if ($connect) {
    mysqli_set_charset($connect, 'utf8mb4');
    $status['db'] = ['status' => 'online'];

    // 테이블 수
    $r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema='hms_saas'");
    $status['db']['tables'] = $r ? intval(mysqli_fetch_assoc($r)['cnt']) : 0;

    // 병원 수
    $r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_hospitals WHERE is_demo=0");
    $status['db']['hospitals'] = $r ? intval(mysqli_fetch_assoc($r)['cnt']) : 0;

    // 회원 수
    $r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM g5_member");
    $status['db']['members'] = $r ? intval(mysqli_fetch_assoc($r)['cnt']) : 0;

    // DB 크기
    $r = mysqli_query($connect, "SELECT ROUND(SUM(data_length+index_length)/1048576,1) as mb FROM information_schema.tables WHERE table_schema='hms_saas'");
    $status['db']['size_mb'] = $r ? floatval(mysqli_fetch_assoc($r)['mb']) : 0;

    mysqli_close($connect);
} else {
    $status['db'] = ['status' => 'offline'];
}

// 6. 웹서버
$status['web'] = [
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Apache',
    'php' => phpversion(),
    'os' => php_uname('s') . ' ' . php_uname('r'),
];

// 7. 서비스 상태
$services = [];
$svc_check = ['httpd' => 'Apache', 'mariadb' => 'MariaDB', 'auto-manual-engine' => '매뉴얼 엔진'];
foreach ($svc_check as $svc => $label) {
    $out = trim(shell_exec("systemctl is-active {$svc} 2>/dev/null") ?? 'unknown');
    $services[$label] = $out;
}
// Node 포트 체크
$node3100 = @fsockopen('127.0.0.1', 3100, $errno, $errstr, 1);
$services['매뉴얼 엔진 (3100)'] = $node3100 ? 'active' : 'inactive';
if ($node3100) fclose($node3100);
$status['services'] = $services;

$status['timestamp'] = date('Y-m-d H:i:s');

echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
