<?php
/**
 * SNS 심사 관리 API
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['ad_admin_logged_in'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false, 'error' => 'DB fail']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

$action = $_REQUEST['action'] ?? 'list';

switch ($action) {
    case 'list':
        $where = "1=1";
        if (!empty($_GET['platform'])) {
            $p = mysqli_real_escape_string($connect, $_GET['platform']);
            $where .= " AND platform = '$p'";
        }
        if (!empty($_GET['status'])) {
            $s = mysqli_real_escape_string($connect, $_GET['status']);
            $where .= " AND status = '$s'";
        }
        $r = mysqli_query($connect, "SELECT * FROM gangnamunni_sns_reviews WHERE $where ORDER BY updated_at DESC");
        $items = [];
        while ($row = mysqli_fetch_assoc($r)) { $items[] = $row; }
        $stats = [];
        $r2 = mysqli_query($connect, "SELECT status, COUNT(*) as cnt FROM gangnamunni_sns_reviews GROUP BY status");
        while ($row = mysqli_fetch_assoc($r2)) { $stats[$row['status']] = (int)$row['cnt']; }
        echo json_encode(['success' => true, 'items' => $items, 'stats' => $stats], JSON_UNESCAPED_UNICODE);
        break;

    case 'create':
        $title = trim($_POST['title'] ?? '');
        if (empty($title)) { echo json_encode(['success' => false, 'error' => '제목 필수']); exit; }
        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_sns_reviews (platform,app_type,title,version,status,submit_date,notes,store_url) VALUES (?,?,?,?,?,?,?,?)");
        $platform = $_POST['platform'] ?? 'google_play';
        $app_type = $_POST['app_type'] ?? 'patient';
        $version = $_POST['version'] ?? '';
        $status = $_POST['status'] ?? 'draft';
        $submit_date = !empty($_POST['submit_date']) ? $_POST['submit_date'] : null;
        $notes = $_POST['notes'] ?? '';
        $store_url = $_POST['store_url'] ?? '';
        mysqli_stmt_bind_param($stmt, 'ssssssss', $platform, $app_type, $title, $version, $status, $submit_date, $notes, $store_url);
        $ok = mysqli_stmt_execute($stmt);
        echo json_encode(['success' => $ok, 'id' => mysqli_insert_id($connect)], JSON_UNESCAPED_UNICODE);
        mysqli_stmt_close($stmt);
        break;

    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success' => false, 'error' => 'Invalid ID']); exit; }
        $fields = []; $params = []; $types = '';
        foreach (['platform','app_type','title','version','status','submit_date','review_date','reject_reason','notes','store_url'] as $f) {
            if (isset($_POST[$f])) {
                $fields[] = "$f = ?";
                $params[] = $_POST[$f] === '' ? null : $_POST[$f];
                $types .= 's';
            }
        }
        if (empty($fields)) { echo json_encode(['success' => false, 'error' => 'No fields']); exit; }
        if (isset($_POST['status']) && in_array($_POST['status'], ['approved','live']) && !isset($_POST['review_date'])) {
            $fields[] = "review_date = CURDATE()";
        }
        $params[] = $id; $types .= 'i';
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_sns_reviews SET " . implode(', ', $fields) . " WHERE id = ?");
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        echo json_encode(['success' => mysqli_stmt_execute($stmt)]);
        mysqli_stmt_close($stmt);
        break;

    case 'update_status':
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($id <= 0 || empty($status)) { echo json_encode(['success' => false]); exit; }
        $extra = '';
        if ($status === 'submitted') $extra = ", submit_date = CURDATE()";
        if (in_array($status, ['approved','live'])) $extra = ", review_date = CURDATE()";
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_sns_reviews SET status = ?" . $extra . " WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $status, $id);
        echo json_encode(['success' => mysqli_stmt_execute($stmt)]);
        mysqli_stmt_close($stmt);
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success' => false]); exit; }
        echo json_encode(['success' => (bool)mysqli_query($connect, "DELETE FROM gangnamunni_sns_reviews WHERE id = $id")]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
mysqli_close($connect);
?>