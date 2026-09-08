<?php
// Recover a missed exchange (upgrade, OAuth redirect or temporary outage)
// from an already authenticated WebView. No long-lived token enters JS.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || ($_SERVER['HTTP_X_NATIVE_AUTH'] ?? '') !== '1') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_request']);
    exit;
}
// The custom header requires a CORS preflight for cross-origin callers.
// This endpoint deliberately sends no Access-Control-Allow-Origin header.
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_native_auth.php';
if (empty($is_member) || empty($member['mb_id'])
    || ($_COOKIE['sb_patient_app'] ?? '') !== '1'
    || !app_native_auth_is_valid_device_id($_COOKIE['sb_app_device'] ?? '')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'login_required']);
    exit;
}
try {
    if ((int)($_SESSION['app_exchange_window_until'] ?? 0) <= time()) {
        unset($_SESSION['app_native_exchange']);
        $_SESSION['app_exchange_window_until'] = time() + 60;
    }
    $code = app_session_exchange_code($connect, $member['mb_id']);
    if ($code === '') throw new RuntimeException('Exchange unavailable');
    echo json_encode(['success' => true, 'code' => $code, 'device_id' => $_COOKIE['sb_app_device']]);
} catch (Throwable $e) {
    error_log('[app-native-auth] bootstrap failed: ' . get_class($e));
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'bootstrap_unavailable']);
}
