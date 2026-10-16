<?php
// CPV 기능 확인서 - 저장/조회/삭제 API + 추가 개발사항
// 플랫폼 관리자 전용 — 예전에는 인증 없이 + CORS * 로 누구나 조회/수정/삭제할 수 있었다.
// 같은 출처의 cpv_feature_confirm.html 에서만 호출하므로 CORS 헤더는 두지 않는다.
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../dbconfig.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB,
    defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
if (!$connect) { echo json_encode(['success'=>false,'message'=>'DB 연결 실패']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

require_once __DIR__ . '/includes/admin_auth.php';
if (!check_admin_login() || is_hospital_role()) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'관리자 로그인이 필요합니다.']);
    exit;
}

// CSRF: 상태 변경 요청은 JSON 본문만 허용 (교차 출처 form/text-plain 요청은 preflight 없이 이 헤더를 보낼 수 없다)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'DELETE'], true)) {
    $ctype = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $origin_ok = $origin === '' || parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '') === $host;
    if ($ctype !== 'application/json' || !$origin_ok) {
        http_response_code(403);
        echo json_encode(['success'=>false,'message'=>'허용되지 않은 요청입니다.']);
        exit;
    }
}

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_cpv_confirmations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feature_id VARCHAR(10) NOT NULL,
    status ENUM('confirmed','modified','rejected') NOT NULL,
    comment TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_feature (feature_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 추가 개발사항 테이블
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_cpv_custom_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    priority ENUM('high','medium','low') DEFAULT 'medium',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_GET['action'] ?? '';

// GET: 전체 조회
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'custom') {
        // 추가 개발사항 조회
        $rows = [];
        $r = mysqli_query($connect, "SELECT * FROM gangnamunni_cpv_custom_requests ORDER BY created_at DESC");
        if ($r) while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
        echo json_encode(['success'=>true, 'data'=>$rows]);
    } else {
        // 기능 확인 조회
        $rows = [];
        $r = mysqli_query($connect, "SELECT feature_id, status, comment, DATE_FORMAT(updated_at,'%Y-%m-%d') as date, updated_at FROM gangnamunni_cpv_confirmations ORDER BY updated_at DESC");
        if ($r) while ($row = mysqli_fetch_assoc($r)) $rows[$row['feature_id']] = $row;
        echo json_encode(['success'=>true, 'data'=>$rows]);
    }
    exit;
}

// POST: 저장/수정
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'custom') {
        // 추가 개발사항 등록
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $priority = in_array($input['priority'] ?? '', ['high','medium','low']) ? $input['priority'] : 'medium';

        if (empty($title)) {
            echo json_encode(['success'=>false,'message'=>'제목 필수']);
            exit;
        }

        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_cpv_custom_requests (title, description, priority) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sss', $title, $description, $priority);
        if (mysqli_stmt_execute($stmt)) {
            $newId = mysqli_insert_id($connect);
            echo json_encode(['success'=>true,'message'=>'등록 완료','id'=>$newId]);
        } else {
            echo json_encode(['success'=>false,'message'=>'등록 실패']);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    // 기능 확인 저장
    $feature_id = trim($input['feature_id'] ?? '');
    $status = trim($input['status'] ?? '');
    $comment = trim($input['comment'] ?? '');

    if (empty($feature_id) || !in_array($status, ['confirmed','modified','rejected'])) {
        echo json_encode(['success'=>false,'message'=>'feature_id, status 필수']);
        exit;
    }

    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_cpv_confirmations (feature_id, status, comment)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE status=VALUES(status), comment=VALUES(comment), updated_at=NOW()");
    mysqli_stmt_bind_param($stmt, 'sss', $feature_id, $status, $comment);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success'=>true,'message'=>'저장 완료']);
    } else {
        echo json_encode(['success'=>false,'message'=>'저장 실패']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// DELETE: 삭제
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($action === 'custom') {
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'id 필수']); exit; }
        $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_cpv_custom_requests WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success'=>true,'message'=>'삭제 완료']);
        } else {
            echo json_encode(['success'=>false,'message'=>'삭제 실패']);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    $feature_id = trim($input['feature_id'] ?? '');
    if (empty($feature_id)) {
        echo json_encode(['success'=>false,'message'=>'feature_id 필수']);
        exit;
    }
    $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_cpv_confirmations WHERE feature_id=?");
    mysqli_stmt_bind_param($stmt, 's', $feature_id);
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success'=>true,'message'=>'삭제 완료']);
    } else {
        echo json_encode(['success'=>false,'message'=>'삭제 실패']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

echo json_encode(['success'=>false,'message'=>'지원하지 않는 메서드']);
