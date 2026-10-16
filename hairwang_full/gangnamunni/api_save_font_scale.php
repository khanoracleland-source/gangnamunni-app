<?php
/**
 * [폰트 스케일 전용 저장 API] 2026-06-16
 *   POST: key, value → gangnamunni_theme_settings 에 INSERT/UPDATE(업서트)
 *   getTheme() 가 읽는 테이블과 동일 → 저장 즉시 반영.
 *   기존 api_save_setting / updateSetting 은 전혀 건드리지 않음(기존기능 보존).
 *   화이트리스트: fs_ 폰트 스케일 키만 허용.
 */
header('Content-Type: application/json; charset=utf-8');

// 관리자 인증
session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
$is_admin = !empty($_SESSION['ad_admin_logged_in']) || !empty($_SESSION['admin_logged_in']);
if (!$is_admin) {
    // 관리자 세션 확인 보강: 기존 admin 체크 파일이 있으면 사용
    if (file_exists(__DIR__ . '/admin_auth.php')) {
        @require_once __DIR__ . '/admin_auth.php';
    }
    if (empty($_SESSION['ad_admin_logged_in']) && empty($_SESSION['admin_logged_in'])) {
        echo json_encode(['success' => false, 'error' => 'unauthorized']);
        exit;
    }
}

$key   = trim($_POST['key'] ?? '');
$value = trim($_POST['value'] ?? '');

// 화이트리스트 — 폰트 스케일 키만
$allowed = ['fs_mobile_min','fs_mobile_slope','fs_mobile_base','fs_os_scale','fs_page_scale'];
if (!in_array($key, $allowed, true)) {
    echo json_encode(['success' => false, 'error' => 'invalid key']);
    exit;
}

// 숫자 검증 (0 < v <= 2 범위)
if (!is_numeric($value)) { echo json_encode(['success'=>false,'error'=>'not numeric']); exit; }
$fv = floatval($value);
if ($fv <= 0 || $fv > 2) { echo json_encode(['success'=>false,'error'=>'out of range']); exit; }
$value = (string)$fv;

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success'=>false,'error'=>'db']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

$ek = mysqli_real_escape_string($connect, $key);
$ev = mysqli_real_escape_string($connect, $value);
// 업서트 — 행 없으면 INSERT, 있으면 UPDATE (updateSetting 의 UPDATE전용 문제 회피)
$sql = "INSERT INTO gangnamunni_theme_settings (setting_key, setting_value, setting_type)
        VALUES ('{$ek}', '{$ev}', 'number')
        ON DUPLICATE KEY UPDATE setting_value = '{$ev}'";
$ok = mysqli_query($connect, $sql);
$err = $ok ? '' : mysqli_error($connect);
mysqli_close($connect);

echo json_encode(['success' => (bool)$ok, 'key' => $key, 'value' => $value, 'error' => $err]);
