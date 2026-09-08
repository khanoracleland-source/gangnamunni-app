<?php
// Shared transport for standalone native APIs. The WebView bootstrap uses
// its authenticated PHP session and deliberately does not enable CORS.
require_once __DIR__ . '/app_native_auth.php';

function app_auth_http_input() {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method === 'OPTIONS') { http_response_code(200); exit; }
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'POST only']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : $_POST;
}

function app_auth_input_string(array $input, $key) {
    return isset($input[$key]) && is_string($input[$key]) ? trim($input[$key]) : '';
}

// Call inside the endpoint's try block so connection/charset failures also
// produce a retryable JSON 503 rather than an uncaught PHP error.
function app_auth_open_connection() {
    require_once __DIR__ . '/../../dbconfig.php';
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD,
        G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    mysqli_set_charset($connection, 'utf8mb4');
    app_native_auth_configure_connection($connection);
    return $connection;
}

function app_auth_member($connect, $mb_id) {
    $stmt = mysqli_prepare($connect,
        'SELECT mb_id, mb_name, mb_nick, mb_email, mb_hp, mb_level,
                mb_intercept_date, mb_leave_date, mb_email_certify
         FROM g5_member WHERE mb_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $mb_id);
    mysqli_stmt_execute($stmt);
    $member = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (app_native_auth_member_is_blocked($member, $connect)) {
        throw new DomainException('account_unavailable', 401);
    }
    return ['mb_id' => $member['mb_id'], 'name' => $member['mb_name'],
        'nickname' => $member['mb_nick'], 'email' => $member['mb_email'],
        'phone' => $member['mb_hp'], 'level' => (int)$member['mb_level']];
}
