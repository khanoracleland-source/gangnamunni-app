<?php
/**
 * 스토어 심사 정보 저장/조회 API
 * - POST: 스토어 심사 정보 DB 저장
 * - GET: 저장된 스토어 심사 정보 조회
 * DB: gangnamunni_store_submission (key-value)
 */
header('Content-Type: application/json; charset=utf-8');

// 이 테이블에는 스토어 심사용 데모 계정(p_ios_demo_username / p_ios_demo_password)과
// 개발자 연락처가 들어 있다. 이전에는 인증이 전혀 없었고 'Access-Control-Allow-Origin: *'
// 까지 붙어 있어, 누구나 GET 으로 전체를 읽고 POST 로 덮어쓸 수 있었다(아무 웹사이트의
// 스크립트에서도 가능). CORS 는 api/.htaccess 가 정한 대로 와일드카드를 쓰지 않는다 —
// 유일한 호출자(project_status_report.html)는 같은 오리진이므로 CORS 헤더 자체가 불필요하다.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../dbconfig.php';

// DB 연결 — 관리자 검사(check_admin_login)가 cred_epoch 를 조회하므로
// 반드시 연결을 먼저 만든 뒤에 인증을 확인한다.
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// 플랫폼 관리자 전용. 자격증명이 담긴 데이터이므로 editor/viewer 와 병원 역할은
// 제외한다(필요하면 아래 배열에 역할을 추가).
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../includes/admin_auth.php';
if (!check_admin_login() || !in_array($_SESSION['ad_admin_role'] ?? '', ['super_admin', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '관리자 로그인이 필요합니다']);
    exit;
}

// 테이블 자동 생성
$create_sql = "CREATE TABLE IF NOT EXISTS `gangnamunni_store_submission` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `field_key` VARCHAR(100) NOT NULL UNIQUE,
    `field_value` TEXT,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (`field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($connect, $create_sql);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 전체 조회
    $result = mysqli_query($connect, "SELECT field_key, field_value, updated_at FROM gangnamunni_store_submission ORDER BY id");
    $data = [];
    $last_updated = null;
    while ($row = mysqli_fetch_assoc($result)) {
        $data[$row['field_key']] = $row['field_value'];
        if (!$last_updated || $row['updated_at'] > $last_updated) {
            $last_updated = $row['updated_at'];
        }
    }
    echo json_encode([
        'success' => true,
        'data' => $data,
        'saved_at' => $last_updated,
        'count' => count($data)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['data']) || !is_array($input['data'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit;
    }

    $saved = 0;
    $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_store_submission (field_key, field_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = NOW()");

    foreach ($input['data'] as $key => $value) {
        // 키 이름 검증 (영문, 숫자, 언더스코어만)
        if (!preg_match('/^[a-z_]+$/', $key)) continue;
        $val = is_string($value) ? $value : '';
        mysqli_stmt_bind_param($stmt, 'ss', $key, $val);
        mysqli_stmt_execute($stmt);
        $saved++;
    }
    mysqli_stmt_close($stmt);

    // 체크리스트도 저장
    if (isset($input['checklist']) && is_array($input['checklist'])) {
        $stmt2 = mysqli_prepare($connect, "INSERT INTO gangnamunni_store_submission (field_key, field_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = NOW()");
        foreach ($input['checklist'] as $key => $value) {
            if (!preg_match('/^[a-z_]+$/', $key)) continue;
            $val = is_string($value) ? $value : ($value ? '1' : '0');
            mysqli_stmt_bind_param($stmt2, 'ss', $key, $val);
            mysqli_stmt_execute($stmt2);
            $saved++;
        }
        mysqli_stmt_close($stmt2);
    }

    echo json_encode([
        'success' => true,
        'saved' => $saved,
        'saved_at' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
