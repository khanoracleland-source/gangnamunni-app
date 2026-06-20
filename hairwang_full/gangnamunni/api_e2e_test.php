<?php
/**
 * E2E 테스트 API — 실행/결과 조회/중단
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false, 'error' => 'DB error']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'run') {
    $run_id = trim($_POST['run_id'] ?? 'run_' . time());
    $tests = $_POST['tests'] ?? '[]';

    // Node.js 러너 실행 (백그라운드)
    $e2e_dir = realpath(__DIR__ . '/auto_manual_engine/e2e');
    if (!$e2e_dir) {
        // 서버 절대 경로 폴백
        $e2e_dir = '/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/hairwang_full/gangnamunni/auto_manual_engine/e2e';
    }
    $log_file = "/tmp/e2e_{$run_id}.log";
    $cmd = "cd {$e2e_dir} && node e2e-runner.js --run-id " . escapeshellarg($run_id) . " --tests " . escapeshellarg($tests) . " > {$log_file} 2>&1 &";
    exec($cmd);

    // 로그 파일 확인 (0.5초 대기)
    usleep(500000);
    $started = file_exists($log_file);

    echo json_encode(['success' => true, 'run_id' => $run_id, 'started' => $started, 'cmd' => $cmd]);
    exit;
}

if ($action === 'results') {
    $run_id = mysqli_real_escape_string($connect, $_GET['run_id'] ?? '');
    $run = null;
    $rq = mysqli_query($connect, "SELECT * FROM gangnamunni_e2e_runs WHERE run_id='{$run_id}'");
    if ($rq) $run = mysqli_fetch_assoc($rq);

    $results = [];
    $rr = mysqli_query($connect, "SELECT * FROM gangnamunni_e2e_results WHERE run_id='{$run_id}' ORDER BY test_id");
    if ($rr) { while ($r = mysqli_fetch_assoc($rr)) $results[] = $r; }

    echo json_encode(['success' => true, 'run' => $run ?: [], 'results' => $results]);
    exit;
}

if ($action === 'abort') {
    $run_id = mysqli_real_escape_string($connect, $_POST['run_id'] ?? '');
    mysqli_query($connect, "UPDATE gangnamunni_e2e_runs SET status='aborted' WHERE run_id='{$run_id}'");
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'history') {
    $rows = [];
    $hq = mysqli_query($connect, "SELECT * FROM gangnamunni_e2e_runs ORDER BY started_at DESC LIMIT 20");
    if ($hq) { while ($r = mysqli_fetch_assoc($hq)) $rows[] = $r; }
    echo json_encode(['success' => true, 'runs' => $rows]);
    exit;
}

if ($action === 'delete') {
    $run_id = mysqli_real_escape_string($connect, $_POST['run_id'] ?? '');
    mysqli_query($connect, "DELETE FROM gangnamunni_e2e_results WHERE run_id='{$run_id}'");
    mysqli_query($connect, "DELETE FROM gangnamunni_e2e_runs WHERE run_id='{$run_id}'");
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Unknown action']);
