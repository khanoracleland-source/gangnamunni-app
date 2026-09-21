<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/admin_menu_catalog.php';

$connect = @mysqli_connect(
    G5_MYSQL_HOST,
    G5_MYSQL_USER,
    G5_MYSQL_PASSWORD,
    G5_MYSQL_DB,
    defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306
);

if (!$connect) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

mysqli_set_charset($connect, 'utf8mb4');

if (!check_admin_login()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$menu_key = trim((string)($input['menu_key'] ?? ''));

if ($menu_key === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $menu_key)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid menu key'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!check_permission($menu_key)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Permission denied'], JSON_UNESCAPED_UNICODE);
    exit;
}

admin_menu_favorites_ensure_table($connect);

$admin_id = (int)($_SESSION['ad_admin_id'] ?? 0);
if ($admin_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid admin session'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_admin_menu_favorite_state (admin_id, initialized) VALUES (?, 1) ON DUPLICATE KEY UPDATE initialized = 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $admin_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$is_favorite = false;
$stmt = mysqli_prepare($connect, "SELECT id FROM gangnamunni_admin_menu_favorites WHERE admin_id = ? AND menu_key = ? LIMIT 1");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'is', $admin_id, $menu_key);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $is_favorite = !empty($row['id']);
}

if ($is_favorite) {
    $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_admin_menu_favorites WHERE admin_id = ? AND menu_key = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    mysqli_stmt_bind_param($stmt, 'is', $admin_id, $menu_key);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => $ok, 'favorite' => false, 'menu_key' => $menu_key], JSON_UNESCAPED_UNICODE);
    exit;
}

$sort_order = 10;
$stmt = mysqli_prepare($connect, "SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_sort FROM gangnamunni_admin_menu_favorites WHERE admin_id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $admin_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $sort_order = (int)($row['next_sort'] ?? 10);
}

$stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_admin_menu_favorites (admin_id, menu_key, sort_order) VALUES (?, ?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

mysqli_stmt_bind_param($stmt, 'isi', $admin_id, $menu_key, $sort_order);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo json_encode(['success' => $ok, 'favorite' => true, 'menu_key' => $menu_key], JSON_UNESCAPED_UNICODE);
