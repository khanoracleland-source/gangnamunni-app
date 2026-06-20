<?php
/**
 * 점검 모드 설정 및 체크
 *
 * 사용법:
 * 1. 모든 공개 페이지 상단에 추가:
 *    require_once __DIR__ . '/maintenance_config.php';
 *    checkMaintenance();
 *
 * 2. 점검 모드 활성화:
 *    enableMaintenance("시스템 점검 중입니다", "2026-02-09 02:00:00", "2026-02-09 04:00:00");
 *
 * 3. 점검 모드 비활성화:
 *    disableMaintenance();
 */

define('MAINTENANCE_FILE', __DIR__ . '/.maintenance');
define('MAINTENANCE_PAGE', '/hairwang/gangnamunni/maintenance.php');

/**
 * 점검 모드 활성화 여부 확인
 *
 * @return bool
 */
function isMaintenanceMode() {
    // 점검 파일이 없으면 점검 모드 아님
    if (!file_exists(MAINTENANCE_FILE)) {
        return false;
    }

    // 점검 설정 로드
    $config = json_decode(file_get_contents(MAINTENANCE_FILE), true);

    if (!$config || !isset($config['enabled']) || !$config['enabled']) {
        return false;
    }

    // 시간 범위 체크
    if (isset($config['start_time']) && isset($config['end_time'])) {
        $now = time();
        $start = strtotime($config['start_time']);
        $end = strtotime($config['end_time']);

        // 예약 시간 전이면 점검 모드 아님
        if ($start && $now < $start) {
            return false;
        }

        // 종료 시간이 지났으면 자동 종료
        if ($end && $now > $end && $config['auto_end']) {
            disableMaintenance();
            return false;
        }
    }

    // 관리자 우회 체크
    if (isset($config['admin_bypass']) && $config['admin_bypass']) {
        $client_ip = $_SERVER['REMOTE_ADDR'];
        $allowed_ips = $config['allowed_ips'] ?? [];

        if (in_array($client_ip, $allowed_ips)) {
            return false;  // 관리자 IP는 우회
        }

        // 관리자 세션 체크
        session_start();
        if (isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true) {
            return false;  // 로그인한 관리자는 우회
        }
    }

    return true;
}

/**
 * 점검 모드 체크 및 리다이렉트
 *
 * 점검 모드가 활성화되어 있고, 현재 페이지가 maintenance.php가 아니면
 * maintenance.php로 리다이렉트
 */
function checkMaintenance() {
    // maintenance.php 자체는 체크하지 않음 (무한 루프 방지)
    if (basename($_SERVER['SCRIPT_NAME']) === 'maintenance.php') {
        return;
    }

    // admin_maintenance.php도 체크하지 않음 (관리자가 점검 모드 해제할 수 있어야 함)
    if (basename($_SERVER['SCRIPT_NAME']) === 'admin_maintenance.php') {
        return;
    }

    if (isMaintenanceMode()) {
        header('Location: ' . MAINTENANCE_PAGE);
        exit;
    }
}

/**
 * 점검 모드 설정 로드
 *
 * @return array|null
 */
function getMaintenanceConfig() {
    if (!file_exists(MAINTENANCE_FILE)) {
        return null;
    }

    return json_decode(file_get_contents(MAINTENANCE_FILE), true);
}

/**
 * 점검 모드 활성화
 *
 * @param string $message 점검 메시지
 * @param string|null $startTime 시작 시간 (Y-m-d H:i:s)
 * @param string|null $endTime 종료 시간 (Y-m-d H:i:s)
 * @param array $options 추가 옵션
 * @return bool
 */
function enableMaintenance($message, $startTime = null, $endTime = null, $options = []) {
    $config = [
        'enabled' => true,
        'message' => $message,
        'start_time' => $startTime ?? date('Y-m-d H:i:s'),
        'end_time' => $endTime,
        'admin_bypass' => $options['admin_bypass'] ?? true,
        'auto_end' => $options['auto_end'] ?? true,
        'allowed_ips' => $options['allowed_ips'] ?? ['127.0.0.1', $_SERVER['SERVER_ADDR'] ?? ''],
        'contact_email' => $options['contact_email'] ?? 'support@seoulbeauty.app',
        'created_at' => date('Y-m-d H:i:s'),
        'created_by' => $_SESSION['ad_admin_user'] ?? 'system',
    ];

    return file_put_contents(MAINTENANCE_FILE, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

/**
 * 점검 모드 비활성화
 *
 * @return bool
 */
function disableMaintenance() {
    if (file_exists(MAINTENANCE_FILE)) {
        return unlink(MAINTENANCE_FILE);
    }
    return true;
}

/**
 * 점검 진행률 계산 (시간 기반)
 *
 * @return int 0-100
 */
function getMaintenanceProgress() {
    $config = getMaintenanceConfig();

    if (!$config || !isset($config['start_time']) || !isset($config['end_time'])) {
        return 0;
    }

    $start = strtotime($config['start_time']);
    $end = strtotime($config['end_time']);
    $now = time();

    if ($now < $start) {
        return 0;
    }

    if ($now > $end) {
        return 100;
    }

    $total = $end - $start;
    $elapsed = $now - $start;

    return (int) min(100, max(0, ($elapsed / $total) * 100));
}

/**
 * 예상 종료까지 남은 시간 (분)
 *
 * @return int
 */
function getRemainingMinutes() {
    $config = getMaintenanceConfig();

    if (!$config || !isset($config['end_time'])) {
        return 0;
    }

    $end = strtotime($config['end_time']);
    $now = time();

    if ($now > $end) {
        return 0;
    }

    return (int) ceil(($end - $now) / 60);
}

/**
 * 관리자 화면용 점검 배너 HTML
 *
 * @return string
 */
function renderMaintenanceBanner() {
    if (!isMaintenanceMode()) {
        return '';
    }

    $config = getMaintenanceConfig();

    // 관리자만 우회 접속 중이라면 배너 표시
    return <<<HTML
<div style="background: linear-gradient(135deg, #ff9800 0%, #ff5722 100%); color: white; padding: 12px 20px; text-align: center; font-weight: 600; position: sticky; top: 0; z-index: 10000; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
    🔧 점검 모드 활성화됨 - 일반 사용자는 차단되었습니다
    <a href="admin_maintenance.php" style="color: white; text-decoration: underline; margin-left: 20px;">점검 관리</a>
</div>
HTML;
}
?>
