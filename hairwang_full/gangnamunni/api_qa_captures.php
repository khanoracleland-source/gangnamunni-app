<?php
/**
 * QA 자동 캡처 & 피드백 API
 * DB: gangnamunni_qa_captures (자동 생성)
 *
 * GET  ?action=list                → 전체 캡처 목록
 * GET  ?action=get&page_key=index  → 단일 캡처
 * POST {action:"capture_all"}      → 전체 페이지 Puppeteer 캡처
 * POST {action:"capture_one", page_key:"index"} → 단일 캡처
 * POST {action:"update_status", page_key:"index", status:"fail", memo:"...", memo_author:"...", memo_role:"client"}
 * POST {action:"update_memo", page_key:"index", memo:"...", memo_author:"dev", memo_role:"dev"}
 * POST {action:"add_page", page_key:"new", page_name:"새 페이지", page_url:"https://..."}
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success' => false, 'message' => 'DB 연결 실패']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

$BASE_DIR = __DIR__;
$SCREENSHOT_DIR = $BASE_DIR . '/qa_screenshots';
$NODE_SCRIPT = $BASE_DIR . '/qa_auto_capture.js';

if (!is_dir($SCREENSHOT_DIR)) {
    mkdir($SCREENSHOT_DIR, 0755, true);
}

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_qa_captures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(50) NOT NULL COMMENT 'unique page identifier',
    page_name VARCHAR(100) NOT NULL COMMENT 'display name',
    page_url VARCHAR(500) NOT NULL,
    screenshot_pc VARCHAR(500) DEFAULT NULL,
    screenshot_mob VARCHAR(500) DEFAULT NULL,
    status ENUM('pass','fail','need_fix','checking') DEFAULT 'checking',
    memo TEXT DEFAULT NULL COMMENT 'feedback/fix request memo',
    memo_author VARCHAR(50) DEFAULT NULL,
    memo_role ENUM('dev','client','qa') DEFAULT 'dev',
    captured_at DATETIME DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_page_key (page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 기본 페이지 삽입
$defaultPages = [
    ['index', '홈페이지', 'https://hms.seoulbeauty.app/hairwang/app/index.php'],
    ['hospitals', '병원 목록', 'https://hms.seoulbeauty.app/hairwang/app/hospitals.php'],
    ['hospitals_sort', '병원 목록 (평점순)', 'https://hms.seoulbeauty.app/hairwang/app/hospitals.php?sort=rating'],
    ['detail_8093', '병원 상세 (8093)', 'https://hms.seoulbeauty.app/hairwang/app/detail.php?id=8093'],
    ['detail_8118', '병원 상세 (8118)', 'https://hms.seoulbeauty.app/hairwang/app/detail.php?id=8118'],
    ['booking_8118', '예약 페이지', 'https://hms.seoulbeauty.app/hairwang/app/booking.php?id=8118'],
    ['mypage', '마이페이지', 'https://hms.seoulbeauty.app/hairwang/app/mypage.php'],
    ['community', '커뮤니티', 'https://hms.seoulbeauty.app/hairwang/app/community.php'],
    ['community_detail', '커뮤니티 상세', 'https://hms.seoulbeauty.app/hairwang/app/detail_community.php?id=1'],
    ['map', '지도', 'https://hms.seoulbeauty.app/hairwang/app/map.php'],
    ['events', '이벤트', 'https://hms.seoulbeauty.app/hairwang/app/events.php'],
    ['event_detail', '이벤트 상세', 'https://hms.seoulbeauty.app/hairwang/app/event_detail.php?id=1'],
    ['reviews', '후기 목록', 'https://hms.seoulbeauty.app/hairwang/app/reviews.php'],
    ['review_write', '후기 작성', 'https://hms.seoulbeauty.app/hairwang/app/review_write.php?hospital_id=8118'],
    ['tips', '꿀팁', 'https://hms.seoulbeauty.app/hairwang/app/tips.php'],
    ['dashboard', '관리자 대시보드', 'https://hms.seoulbeauty.app/hairwang/app/dashboard.php'],
    ['admin_ads', '광고 관리', 'https://hms.seoulbeauty.app/hairwang/app/admin_ads.php'],
    ['admin_hospitals', '병원 관리', 'https://hms.seoulbeauty.app/hairwang/app/admin_hospitals.php'],
    ['admin_cpv_settings', 'CPV 설정', 'https://hms.seoulbeauty.app/hairwang/app/admin_cpv_settings.php'],
    ['admin_cpv_dashboard', 'CPV 대시보드', 'https://hms.seoulbeauty.app/hairwang/app/admin_cpv_dashboard.php'],
    ['admin_nav_settings', '탭 설정', 'https://hms.seoulbeauty.app/hairwang/app/admin_nav_settings.php'],
    ['partner_hospital', '파트너 병원 편집', 'https://hms.seoulbeauty.app/hairwang/app/partner_hospital.php'],
    ['partner_wallet', '파트너 지갑', 'https://hms.seoulbeauty.app/hairwang/app/partner_wallet.php'],
];

// 테이블이 비어 있으면 기본 데이터 삽입
$chk = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_qa_captures");
$row = mysqli_fetch_assoc($chk);
if ((int)$row['cnt'] === 0) {
    $ins = mysqli_prepare($connect, "INSERT IGNORE INTO gangnamunni_qa_captures (page_key, page_name, page_url) VALUES (?, ?, ?)");
    foreach ($defaultPages as $dp) {
        mysqli_stmt_bind_param($ins, 'sss', $dp[0], $dp[1], $dp[2]);
        mysqli_stmt_execute($ins);
    }
    mysqli_stmt_close($ins);
}

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

// ============ GET ============
if ($method === 'GET') {

    // 전체 목록
    if ($action === 'list') {
        $statusFilter = $_GET['status'] ?? '';
        $search = $_GET['search'] ?? '';

        $where = '1=1';
        $params = [];
        $types = '';

        if ($statusFilter && in_array($statusFilter, ['pass', 'fail', 'need_fix', 'checking'])) {
            $where .= ' AND status = ?';
            $params[] = $statusFilter;
            $types .= 's';
        }
        if ($search) {
            $where .= ' AND (page_key LIKE ? OR page_name LIKE ?)';
            $s = '%' . $search . '%';
            $params[] = $s;
            $params[] = $s;
            $types .= 'ss';
        }

        $sql = "SELECT * FROM gangnamunni_qa_captures WHERE $where ORDER BY id ASC";
        $stmt = mysqli_prepare($connect, $sql);
        if ($types) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $items = [];
        $summary = ['pass' => 0, 'fail' => 0, 'need_fix' => 0, 'checking' => 0];
        while ($r = mysqli_fetch_assoc($result)) {
            $items[] = $r;
            if (isset($summary[$r['status']])) {
                $summary[$r['status']]++;
            }
        }
        mysqli_stmt_close($stmt);

        // 전체 통계 (필터 무관)
        $totalSummary = ['pass' => 0, 'fail' => 0, 'need_fix' => 0, 'checking' => 0];
        $sr = mysqli_query($connect, "SELECT status, COUNT(*) as cnt FROM gangnamunni_qa_captures GROUP BY status");
        while ($r = mysqli_fetch_assoc($sr)) {
            if (isset($totalSummary[$r['status']])) {
                $totalSummary[$r['status']] = (int)$r['cnt'];
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $items,
            'count' => count($items),
            'summary' => $totalSummary
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 단일 조회
    if ($action === 'get') {
        $pageKey = $_GET['page_key'] ?? '';
        if (empty($pageKey)) {
            echo json_encode(['success' => false, 'message' => 'page_key 필수']);
            exit;
        }
        $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_qa_captures WHERE page_key = ?");
        mysqli_stmt_bind_param($stmt, 's', $pageKey);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($row) {
            echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => '페이지를 찾을 수 없습니다']);
        }
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'QA Captures API', 'actions' => ['list', 'get', 'capture_all', 'capture_one', 'update_status', 'update_memo', 'add_page']]);
    exit;
}

// ============ POST ============
if ($method === 'POST') {

    // 전체 캡처
    if ($action === 'capture_all') {
        // DB에서 모든 페이지 읽기
        $result = mysqli_query($connect, "SELECT page_key, page_name, page_url FROM gangnamunni_qa_captures ORDER BY id ASC");
        $pages = [];
        while ($r = mysqli_fetch_assoc($result)) {
            $pages[] = $r;
        }

        if (empty($pages)) {
            echo json_encode(['success' => false, 'message' => '캡처할 페이지가 없습니다']);
            exit;
        }

        // JSON 파일로 Puppeteer에 전달
        $tmpFile = '/tmp/qa_auto_capture_' . uniqid() . '.json';
        file_put_contents($tmpFile, json_encode($pages, JSON_UNESCAPED_UNICODE));

        $cmd = "export HOME=/tmp && cd " . escapeshellarg($BASE_DIR) . " && node qa_auto_capture.js --auto " . escapeshellarg($tmpFile) . " 2>&1";
        $output = shell_exec($cmd);
        @unlink($tmpFile);

        // 결과 파싱 — JSON 라인 찾기
        $captureResults = [];
        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '{"results"') === 0 || strpos($line, '{"success"') === 0) {
                $parsed = json_decode($line, true);
                if ($parsed && isset($parsed['results'])) {
                    $captureResults = $parsed['results'];
                }
            }
        }

        // DB 업데이트
        $t = time();
        $updateStmt = mysqli_prepare($connect,
            "UPDATE gangnamunni_qa_captures SET screenshot_pc = ?, screenshot_mob = ?, captured_at = NOW() WHERE page_key = ?"
        );

        $updated = 0;
        foreach ($pages as $page) {
            $pk = $page['page_key'];
            $pcFile = "qa_screenshots/qa_auto_{$pk}_pc.png";
            $mobFile = "qa_screenshots/qa_auto_{$pk}_mob.png";

            $pcExists = file_exists($SCREENSHOT_DIR . "/qa_auto_{$pk}_pc.png");
            $mobExists = file_exists($SCREENSHOT_DIR . "/qa_auto_{$pk}_mob.png");

            $pcVal = $pcExists ? $pcFile . "?t=$t" : null;
            $mobVal = $mobExists ? $mobFile . "?t=$t" : null;

            if ($pcExists || $mobExists) {
                mysqli_stmt_bind_param($updateStmt, 'sss', $pcVal, $mobVal, $pk);
                mysqli_stmt_execute($updateStmt);
                $updated++;
            }
        }
        mysqli_stmt_close($updateStmt);

        echo json_encode([
            'success' => true,
            'message' => "전체 캡처 완료",
            'total' => count($pages),
            'captured' => $updated,
            'log' => $output
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 단일 캡처
    if ($action === 'capture_one') {
        $pageKey = trim($input['page_key'] ?? '');
        if (empty($pageKey)) {
            echo json_encode(['success' => false, 'message' => 'page_key 필수']);
            exit;
        }

        // DB에서 URL 조회
        $stmt = mysqli_prepare($connect, "SELECT page_key, page_name, page_url FROM gangnamunni_qa_captures WHERE page_key = ?");
        mysqli_stmt_bind_param($stmt, 's', $pageKey);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $page = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$page) {
            echo json_encode(['success' => false, 'message' => '페이지를 찾을 수 없습니다']);
            exit;
        }

        // 단일 페이지 JSON
        $tmpFile = '/tmp/qa_auto_capture_one_' . uniqid() . '.json';
        file_put_contents($tmpFile, json_encode([$page], JSON_UNESCAPED_UNICODE));

        $cmd = "export HOME=/tmp && cd " . escapeshellarg($BASE_DIR) . " && node qa_auto_capture.js --auto " . escapeshellarg($tmpFile) . " 2>&1";
        $output = shell_exec($cmd);
        @unlink($tmpFile);

        $t = time();
        $pcFile = "qa_screenshots/qa_auto_{$pageKey}_pc.png";
        $mobFile = "qa_screenshots/qa_auto_{$pageKey}_mob.png";
        $pcExists = file_exists($SCREENSHOT_DIR . "/qa_auto_{$pageKey}_pc.png");
        $mobExists = file_exists($SCREENSHOT_DIR . "/qa_auto_{$pageKey}_mob.png");

        $pcVal = $pcExists ? $pcFile . "?t=$t" : null;
        $mobVal = $mobExists ? $mobFile . "?t=$t" : null;

        if ($pcExists || $mobExists) {
            $upd = mysqli_prepare($connect, "UPDATE gangnamunni_qa_captures SET screenshot_pc = ?, screenshot_mob = ?, captured_at = NOW() WHERE page_key = ?");
            mysqli_stmt_bind_param($upd, 'sss', $pcVal, $mobVal, $pageKey);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);
        }

        echo json_encode([
            'success' => $pcExists || $mobExists,
            'page_key' => $pageKey,
            'screenshot_pc' => $pcVal,
            'screenshot_mob' => $mobVal,
            'log' => $output
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 상태 + 메모 업데이트
    if ($action === 'update_status') {
        $pageKey = trim($input['page_key'] ?? '');
        if (empty($pageKey)) {
            echo json_encode(['success' => false, 'message' => 'page_key 필수']);
            exit;
        }

        $updates = [];
        $params = [];
        $types = '';

        if (isset($input['status']) && in_array($input['status'], ['pass', 'fail', 'need_fix', 'checking'])) {
            $updates[] = 'status = ?';
            $params[] = $input['status'];
            $types .= 's';
        }
        if (isset($input['memo'])) {
            $updates[] = 'memo = ?';
            $params[] = $input['memo'];
            $types .= 's';
        }
        if (isset($input['memo_author'])) {
            $updates[] = 'memo_author = ?';
            $params[] = $input['memo_author'];
            $types .= 's';
        }
        if (isset($input['memo_role']) && in_array($input['memo_role'], ['dev', 'client', 'qa'])) {
            $updates[] = 'memo_role = ?';
            $params[] = $input['memo_role'];
            $types .= 's';
        }

        if (empty($updates)) {
            echo json_encode(['success' => false, 'message' => '변경 사항 없음']);
            exit;
        }

        $sql = "UPDATE gangnamunni_qa_captures SET " . implode(', ', $updates) . " WHERE page_key = ?";
        $params[] = $pageKey;
        $types .= 's';

        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => '상태 업데이트 완료', 'affected' => mysqli_stmt_affected_rows($stmt)]);
        } else {
            echo json_encode(['success' => false, 'message' => '업데이트 실패: ' . mysqli_error($connect)]);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    // 메모만 업데이트
    if ($action === 'update_memo') {
        $pageKey = trim($input['page_key'] ?? '');
        $memo = $input['memo'] ?? '';
        $memoAuthor = $input['memo_author'] ?? '';
        $memoRole = $input['memo_role'] ?? 'dev';

        if (empty($pageKey)) {
            echo json_encode(['success' => false, 'message' => 'page_key 필수']);
            exit;
        }

        if (!in_array($memoRole, ['dev', 'client', 'qa'])) {
            $memoRole = 'dev';
        }

        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_qa_captures SET memo = ?, memo_author = ?, memo_role = ? WHERE page_key = ?");
        mysqli_stmt_bind_param($stmt, 'ssss', $memo, $memoAuthor, $memoRole, $pageKey);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => '메모 업데이트 완료']);
        } else {
            echo json_encode(['success' => false, 'message' => '업데이트 실패: ' . mysqli_error($connect)]);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    // 새 페이지 추가
    if ($action === 'add_page') {
        $pageKey = trim($input['page_key'] ?? '');
        $pageName = trim($input['page_name'] ?? '');
        $pageUrl = trim($input['page_url'] ?? '');

        if (empty($pageKey) || empty($pageName) || empty($pageUrl)) {
            echo json_encode(['success' => false, 'message' => 'page_key, page_name, page_url 필수']);
            exit;
        }

        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_qa_captures (page_key, page_name, page_url) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE page_name = VALUES(page_name), page_url = VALUES(page_url)");
        mysqli_stmt_bind_param($stmt, 'sss', $pageKey, $pageName, $pageUrl);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => '페이지 추가 완료', 'page_key' => $pageKey]);
        } else {
            echo json_encode(['success' => false, 'message' => '추가 실패: ' . mysqli_error($connect)]);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'action 필수']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid method']);
