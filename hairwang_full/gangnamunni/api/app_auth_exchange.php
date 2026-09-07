<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

require_once __DIR__ . '/../../dbconfig.php';
require_once __DIR__ . '/../includes/app_native_auth.php';

if (!defined('G5_SERVER_TIME')) define('G5_SERVER_TIME', time());

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { http_response_code(500); echo json_encode(['success' => false, 'error' => 'DB fail']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// app_native_auth_transaction()'s rollback safety net depends on mysqli
// throwing on error (PHP 8.1+'s default) rather than silently returning
// false (pre-8.1, and how Gnuboard's own sql_connect() sets it via
// MYSQLI_REPORT_OFF for the shared $connect elsewhere). This endpoint owns
// its whole request, so forcing it here is safe and makes the guarantee
// hold regardless of PHP version.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

$code = trim((string)($input['code'] ?? ''));
$device_id = trim((string)($input['device_id'] ?? ''));

if ($code === '' || !app_native_auth_is_valid_device_id($device_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'code and device_id are required']);
    exit;
}

try {
    // Fail before issuing credentials if the server key is not provisioned.
    app_exchange_payload_key();
    // Expired retry data is no longer useful. This contains only ciphertext.
    mysqli_query($connect, "UPDATE sso_tokens SET payload = NULL WHERE user_type = 'patient_app_exchange' AND expires_at <= NOW() AND payload IS NOT NULL");
    $response = app_native_auth_transaction($connect, function () use ($connect, $code, $device_id) {
        $claim = app_consume_exchange_code($connect, $code, $device_id);
        $mb_id = $claim['mb_id'];
        if ($mb_id === '') throw new DomainException('exchange_invalid', 401);

        $stmt = mysqli_prepare($connect, "SELECT * FROM g5_member WHERE mb_id = ? LIMIT 1");
        if (!$stmt) throw new RuntimeException('Member lookup failed');
        mysqli_stmt_bind_param($stmt, 's', $mb_id);
        mysqli_stmt_execute($stmt);
        $member = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (app_native_auth_member_is_blocked($member, $connect)) {
            throw new DomainException('account_unavailable', 401);
        }
        $user = ['mb_id' => $mb_id, 'name' => $member['mb_name'],
            'nickname' => $member['mb_nick'], 'email' => $member['mb_email'],
            'phone' => $member['mb_hp'], 'level' => (int)$member['mb_level']];
        if ($claim['payload'] !== null) {
            $payload = app_decrypt_exchange_payload($code, $claim['payload']);
        } else {
            // This is a fresh login (not a refresh renewal) — any refreshToken
            // still on file for this exact device is stale (e.g. the app was
            // reinstalled without an explicit logout). Clear it first so this
            // device never ends up with two simultaneously-valid ones.
            app_discard_stale_refresh_token($connect, $mb_id, $device_id);
            $refresh = create_remember_login_token(['mb_id' => $mb_id], APP_REFRESH_TOKEN_LIFETIME,
                ['is_patient_app' => true, 'device_id' => $device_id]);
            $access = app_issue_access_token($connect, $mb_id, $device_id);
            $bridge = app_issue_session_bridge_token($connect, $mb_id, $device_id);
            if ($access['token'] === '' || $refresh === '' || $bridge === '') {
                throw new RuntimeException('Token issuance failed');
            }
            $payload = ['access_token' => $access['token'], 'access_expires_at' => $access['expires_at'],
                'refresh_token' => $refresh, 'refresh_expires_at' => G5_SERVER_TIME + APP_REFRESH_TOKEN_LIFETIME,
                'session_bridge_token' => $bridge];
            app_store_exchange_payload($connect, $code, $payload);
        }
        return array_merge(['success' => true], $payload, ['user' => $user]);
    });
    header('Cache-Control: no-store');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (DomainException $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[app-native-auth] exchange failed: ' . get_class($e));
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'exchange_unavailable']);
}
mysqli_close($connect);
