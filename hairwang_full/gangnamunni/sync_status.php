<?php
if (!defined('G5_MYSQL_PASSWORD')) { require_once __DIR__ . '/dbconfig.php'; }
/**
 * HMS 동기화 현황 API
 * GET /sync_status.php - JSON 반환
 * POST /sync_status.php?action=run_sync - 동기화 실행
 */
require_once __DIR__ . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? 'status';

// 관리자 세션 확인 (super_admin 전용)
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/includes/admin_auth.php';
if (!is_super_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
if ($action === 'run_sync' && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

try {
    $db = new mysqli(
        defined('GANGNAMUNNI_DB_HOST') ? GANGNAMUNNI_DB_HOST : 'localhost',
        defined('GANGNAMUNNI_DB_USER') ? GANGNAMUNNI_DB_USER : 'root',
        defined('GANGNAMUNNI_DB_PASS') ? GANGNAMUNNI_DB_PASS : (defined('G5_MYSQL_PASSWORD') ? G5_MYSQL_PASSWORD : ''),
        defined('GANGNAMUNNI_DB_NAME') ? GANGNAMUNNI_DB_NAME : 'hairwang'
    );
    $db->set_charset('utf8mb4');

    $hms_db = new mysqli(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, 'hms_saas');
    $hms_db->set_charset('utf8mb4');

    if ($action === 'status') {
        // 서울미녀 데이터
        $gn_hospitals = $db->query("SELECT COUNT(*) as cnt FROM gangnamunni_hospitals")->fetch_assoc()['cnt'];
        $gn_linked = $db->query("SELECT COUNT(*) as cnt FROM gangnamunni_hospitals WHERE hms_hospital_id IS NOT NULL AND hms_hospital_id != ''")->fetch_assoc()['cnt'];
        $gn_doctors = $db->query("SELECT COUNT(*) as cnt FROM gangnamunni_doctors")->fetch_assoc()['cnt'];
        $gn_depts = $db->query("SELECT COUNT(*) as cnt FROM gangnamunni_departments")->fetch_assoc()['cnt'];
        $gn_bookings = $db->query("SELECT COUNT(*) as cnt FROM gangnamunni_bookings")->fetch_assoc()['cnt'];
        $gn_consults = $db->query("SELECT COUNT(*) as cnt FROM gangnamunni_consultations")->fetch_assoc()['cnt'];

        // HMS 데이터
        $hms_tenants = $hms_db->query("SELECT COUNT(*) as cnt FROM tenants")->fetch_assoc()['cnt'];
        $hms_doctors = $hms_db->query("SELECT COUNT(*) as cnt FROM doctors")->fetch_assoc()['cnt'];
        $hms_depts = $hms_db->query("SELECT COUNT(*) as cnt FROM doctor_departments")->fetch_assoc()['cnt'];
        $hms_appts = $hms_db->query("SELECT COUNT(*) as cnt FROM appointments")->fetch_assoc()['cnt'];
        $hms_patients = $hms_db->query("SELECT COUNT(*) as cnt FROM patients")->fetch_assoc()['cnt'];
        $hms_users = $hms_db->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];

        // 마지막 동기화 시간
        $last_hosp = $db->query("SELECT MAX(synced_at) as t FROM gangnamunni_hospitals WHERE synced_at IS NOT NULL")->fetch_assoc()['t'] ?? 'N/A';
        $last_doc = $db->query("SELECT MAX(synced_at) as t FROM gangnamunni_doctors WHERE synced_at IS NOT NULL")->fetch_assoc()['t'] ?? 'N/A';
        $last_dept = $db->query("SELECT MAX(synced_at) as t FROM gangnamunni_departments WHERE synced_at IS NOT NULL")->fetch_assoc()['t'] ?? 'N/A';

        // 최근 동기화 로그
        $log_dir = __DIR__ . '/sync/logs/';
        $recent_logs = [];
        if (is_dir($log_dir)) {
            $files = glob($log_dir . '*.log');
            rsort($files);
            if (!empty($files)) {
                $lines = file($files[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recent_logs = array_slice($lines, -10);
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'gangnamunni' => [
                    'hospitals' => (int)$gn_hospitals,
                    'hms_linked' => (int)$gn_linked,
                    'doctors' => (int)$gn_doctors,
                    'departments' => (int)$gn_depts,
                    'bookings' => (int)$gn_bookings,
                    'consultations' => (int)$gn_consults,
                ],
                'hms' => [
                    'tenants' => (int)$hms_tenants,
                    'doctors' => (int)$hms_doctors,
                    'departments' => (int)$hms_depts,
                    'appointments' => (int)$hms_appts,
                    'patients' => (int)$hms_patients,
                    'users' => (int)$hms_users,
                ],
                'last_sync' => [
                    'hospitals' => $last_hosp,
                    'doctors' => $last_doc,
                    'departments' => $last_dept,
                ],
                'recent_logs' => $recent_logs,
            ],
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'run_sync') {
        $direction = $_POST['direction'] ?? 'both';
        $output = [];

        if ($direction === 'hms_to_gn' || $direction === 'both') {
            $out = shell_exec('cd ' . __DIR__ . '/sync && php sync_hms_to_gangnamunni.php 2>&1');
            $output['hms_to_gn'] = $out;
        }

        if ($direction === 'gn_to_hms' || $direction === 'both') {
            $out = shell_exec('cd ' . __DIR__ . '/sync && php sync_gangnamunni_to_hms.php 2>&1');
            $output['gn_to_hms'] = $out;
        }

        echo json_encode([
            'success' => true,
            'output' => $output,
        ], JSON_UNESCAPED_UNICODE);

    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }

    $db->close();
    $hms_db->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
