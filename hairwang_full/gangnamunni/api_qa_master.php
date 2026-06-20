<?php
/**
 * QA 마스터 대시보드 API
 * 전체 기능 테스트 결과 CRUD + 집계 + 실패 항목 조회 (Claude CLI 연동)
 * DB: gangnamunni_qa_master (자동 생성)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) { echo json_encode(['success'=>false,'message'=>'DB error']); exit; }
mysqli_set_charset($connect, 'utf8mb4');

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_qa_master (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(20) NOT NULL COMMENT '카테고리 (front/admin/partner/api/component/etc)',
    feature_id VARCHAR(20) NOT NULL COMMENT '기능 ID (A1, B2, API3 등)',
    feature_name VARCHAR(100) DEFAULT '' COMMENT '기능 이름',
    file_name VARCHAR(100) DEFAULT '' COMMENT '파일명',
    url VARCHAR(500) DEFAULT '' COMMENT '테스트 URL',
    check_name VARCHAR(100) NOT NULL COMMENT '체크 항목명',
    check_result ENUM('pass','fail','skip','fixed','retest') DEFAULT NULL COMMENT '결과',
    severity ENUM('critical','major','minor','cosmetic') DEFAULT 'minor' COMMENT '심각도',
    notes TEXT COMMENT '메모/상세 설명',
    screenshot_url VARCHAR(500) DEFAULT '' COMMENT '스크린샷 URL',
    tester VARCHAR(50) DEFAULT '' COMMENT '테스터 (client/dev/claude)',
    fix_commit VARCHAR(100) DEFAULT '' COMMENT '수정 커밋 해시',
    fix_note TEXT COMMENT '수정 내용 요약',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    tested_at DATETIME NULL COMMENT '테스트 일시',
    fixed_at DATETIME NULL COMMENT '수정 일시',
    UNIQUE KEY uk_feature_check (feature_id, check_name),
    INDEX idx_category (category),
    INDEX idx_result (check_result),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// gangnamunni_qa_features 테이블 자동 생성 (기능 목록 DB 관리)
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_qa_features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    feature_id VARCHAR(20) NOT NULL,
    category VARCHAR(20) NOT NULL COMMENT 'front/admin/partner/api/component/etc/app_patient/app_admin',
    name VARCHAR(100) NOT NULL,
    file_name VARCHAR(200) DEFAULT '',
    checks JSON NOT NULL COMMENT '체크항목 배열',
    platform VARCHAR(20) DEFAULT 'web' COMMENT 'web/ios/android/both',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_feature_id (feature_id),
    INDEX idx_category (category),
    INDEX idx_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $input['action'] ?? '';

// === GET ===
if ($method === 'GET') {

    // 기능 목록 조회 (카테고리별 그룹핑)
    if ($action === 'features') {
        $platform = $_GET['platform'] ?? '';
        $where = 'is_active = 1';
        $params = [];
        $types = '';
        if ($platform) {
            $where .= ' AND platform = ?';
            $params[] = $platform;
            $types .= 's';
        }

        $sql = "SELECT feature_id, category, name, file_name, checks, platform FROM gangnamunni_qa_features WHERE $where ORDER BY category, sort_order, feature_id";
        $stmt = mysqli_prepare($connect, $sql);
        if ($types) {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $grouped = [];
        $total = 0;
        while ($row = mysqli_fetch_assoc($result)) {
            $cat = $row['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $checksDecoded = json_decode($row['checks'], true);
            if (!is_array($checksDecoded)) {
                $checksDecoded = [];
            }
            $grouped[$cat][] = [
                'id'       => $row['feature_id'],
                'name'     => $row['name'],
                'file'     => $row['file_name'],
                'checks'   => $checksDecoded,
                'platform' => $row['platform']
            ];
            $total++;
        }
        mysqli_stmt_close($stmt);

        echo json_encode(['success' => true, 'data' => $grouped, 'total' => $total], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 집계 통계
    if ($action === 'summary') {
        $sql = "SELECT
            category,
            COUNT(*) as total,
            SUM(check_result='pass') as pass_count,
            SUM(check_result='fail') as fail_count,
            SUM(check_result='skip') as skip_count,
            SUM(check_result='fixed') as fixed_count,
            SUM(check_result='retest') as retest_count,
            SUM(check_result IS NULL) as untested_count,
            SUM(severity='critical' AND check_result='fail') as critical_fails
        FROM gangnamunni_qa_master
        GROUP BY category WITH ROLLUP";
        $result = mysqli_query($connect, $sql);
        $rows = [];
        while ($r = mysqli_fetch_assoc($result)) {
            $rows[] = $r;
        }
        echo json_encode(['success'=>true, 'data'=>$rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 실패 항목만 (Claude CLI용)
    if ($action === 'failures') {
        $sql = "SELECT * FROM gangnamunni_qa_master
            WHERE check_result IN ('fail','retest')
            ORDER BY FIELD(severity,'critical','major','minor','cosmetic'), feature_id, check_name";
        $result = mysqli_query($connect, $sql);
        $items = [];
        while ($r = mysqli_fetch_assoc($result)) {
            $items[] = $r;
        }
        echo json_encode(['success'=>true, 'data'=>$items, 'count'=>count($items)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // fixed 항목 (재테스트 대기)
    if ($action === 'fixed') {
        $sql = "SELECT * FROM gangnamunni_qa_master WHERE check_result = 'fixed' ORDER BY fixed_at DESC";
        $result = mysqli_query($connect, $sql);
        $items = [];
        while ($r = mysqli_fetch_assoc($result)) {
            $items[] = $r;
        }
        echo json_encode(['success'=>true, 'data'=>$items], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 전체 목록 (필터 지원)
    $where = '1=1';
    $params = [];
    $types = '';

    if (!empty($_GET['category'])) {
        $where .= ' AND category = ?';
        $params[] = $_GET['category'];
        $types .= 's';
    }
    if (!empty($_GET['status'])) {
        $where .= ' AND check_result = ?';
        $params[] = $_GET['status'];
        $types .= 's';
    }
    if (!empty($_GET['feature_id'])) {
        $where .= ' AND feature_id = ?';
        $params[] = $_GET['feature_id'];
        $types .= 's';
    }
    if (!empty($_GET['severity'])) {
        $where .= ' AND severity = ?';
        $params[] = $_GET['severity'];
        $types .= 's';
    }

    $sql = "SELECT * FROM gangnamunni_qa_master WHERE {$where} ORDER BY feature_id, check_name";
    $stmt = mysqli_prepare($connect, $sql);
    if ($types) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);

    echo json_encode(['success'=>true, 'data'=>$items, 'count'=>count($items)], JSON_UNESCAPED_UNICODE);
    exit;
}

// === POST: 개별/배치 저장 (UPSERT) ===
if ($method === 'POST') {

    // 기능 목록 일괄 import (JSON → DB)
    if ($action === 'import_features') {
        $features = $input['features'] ?? [];
        if (!is_array($features) || empty($features)) {
            echo json_encode(['success' => false, 'message' => 'features 데이터 필수 (카테고리별 배열)']);
            exit;
        }

        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_qa_features
                (feature_id, category, name, file_name, checks, platform, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                category = VALUES(category),
                name = VALUES(name),
                file_name = VALUES(file_name),
                checks = VALUES(checks),
                platform = VALUES(platform),
                sort_order = VALUES(sort_order),
                is_active = 1"
        );

        mysqli_begin_transaction($connect);
        $ok = 0;
        $fail = 0;

        foreach ($features as $category => $items) {
            if (!is_array($items)) continue;
            $sortOrder = 0;
            foreach ($items as $item) {
                $fid = $item['id'] ?? $item['feature_id'] ?? '';
                $name = $item['name'] ?? '';
                if (empty($fid) || empty($name)) { $fail++; continue; }

                $fileName = $item['file'] ?? $item['file_name'] ?? '';
                $checksArr = $item['checks'] ?? [];
                $checksJson = json_encode($checksArr, JSON_UNESCAPED_UNICODE);
                $platform = $item['platform'] ?? 'web';

                mysqli_stmt_bind_param($stmt, 'ssssssi', $fid, $category, $name, $fileName, $checksJson, $platform, $sortOrder);
                if (mysqli_stmt_execute($stmt)) { $ok++; } else { $fail++; }
                $sortOrder++;
            }
        }

        mysqli_stmt_close($stmt);
        mysqli_commit($connect);

        echo json_encode(['success' => true, 'message' => 'import 완료', 'saved' => $ok, 'failed' => $fail], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 개별 기능 저장/수정
    if ($action === 'save_feature') {
        $fid = trim($input['feature_id'] ?? '');
        $category = trim($input['category'] ?? '');
        $name = trim($input['name'] ?? '');

        if (empty($fid) || empty($category) || empty($name)) {
            echo json_encode(['success' => false, 'message' => 'feature_id, category, name 필수']);
            exit;
        }

        $fileName = $input['file_name'] ?? '';
        $checksArr = $input['checks'] ?? [];
        $checksJson = json_encode($checksArr, JSON_UNESCAPED_UNICODE);
        $platform = $input['platform'] ?? 'web';
        $sortOrder = intval($input['sort_order'] ?? 0);

        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_qa_features
                (feature_id, category, name, file_name, checks, platform, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                category = VALUES(category),
                name = VALUES(name),
                file_name = VALUES(file_name),
                checks = VALUES(checks),
                platform = VALUES(platform),
                sort_order = VALUES(sort_order)"
        );

        mysqli_stmt_bind_param($stmt, 'ssssssi', $fid, $category, $name, $fileName, $checksJson, $platform, $sortOrder);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => '기능 저장 완료', 'feature_id' => $fid]);
        } else {
            echo json_encode(['success' => false, 'message' => '저장 실패: ' . mysqli_error($connect)]);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    // 기능 삭제 (soft delete: is_active=0)
    if ($action === 'delete_feature') {
        $fid = trim($input['feature_id'] ?? '');
        if (empty($fid)) {
            echo json_encode(['success' => false, 'message' => 'feature_id 필수']);
            exit;
        }

        $hard = $input['hard'] ?? false;

        if ($hard) {
            // 물리 삭제
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_qa_features WHERE feature_id = ?");
        } else {
            // 논리 삭제 (is_active=0)
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_qa_features SET is_active = 0 WHERE feature_id = ?");
        }
        mysqli_stmt_bind_param($stmt, 's', $fid);

        if (mysqli_stmt_execute($stmt)) {
            $affected = mysqli_stmt_affected_rows($stmt);
            echo json_encode(['success' => true, 'message' => ($hard ? '물리 삭제' : '비활성화') . ' 완료', 'affected' => $affected]);
        } else {
            echo json_encode(['success' => false, 'message' => '삭제 실패: ' . mysqli_error($connect)]);
        }
        mysqli_stmt_close($stmt);
        exit;
    }

    // 배치 저장
    if (isset($input['batch']) && is_array($input['batch'])) {
        mysqli_begin_transaction($connect);
        $ok = 0;
        $fail = 0;
        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_qa_master
                (category, feature_id, feature_name, file_name, url, check_name, check_result, severity, notes, screenshot_url, tester, tested_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                check_result = VALUES(check_result),
                severity = VALUES(severity),
                notes = VALUES(notes),
                screenshot_url = VALUES(screenshot_url),
                tester = VALUES(tester),
                tested_at = NOW()"
        );

        foreach ($input['batch'] as $item) {
            $cat = $item['category'] ?? '';
            $fid = $item['feature_id'] ?? '';
            $fname = $item['feature_name'] ?? '';
            $file = $item['file_name'] ?? '';
            $url = $item['url'] ?? '';
            $chk = $item['check_name'] ?? '';
            $res = $item['check_result'] ?? null;
            $sev = $item['severity'] ?? 'minor';
            $notes = $item['notes'] ?? '';
            $ss = $item['screenshot_url'] ?? '';
            $tester = $item['tester'] ?? '';

            if (empty($fid) || empty($chk)) { $fail++; continue; }

            mysqli_stmt_bind_param($stmt, 'sssssssssss', $cat, $fid, $fname, $file, $url, $chk, $res, $sev, $notes, $ss, $tester);
            if (mysqli_stmt_execute($stmt)) { $ok++; } else { $fail++; }
        }
        mysqli_stmt_close($stmt);
        mysqli_commit($connect);

        echo json_encode(['success'=>true, 'saved'=>$ok, 'failed'=>$fail], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 개별 저장
    $fid = trim($input['feature_id'] ?? '');
    $chk = trim($input['check_name'] ?? '');

    if (empty($fid) || empty($chk)) {
        echo json_encode(['success'=>false, 'message'=>'feature_id, check_name 필수']);
        exit;
    }

    $stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_qa_master
            (category, feature_id, feature_name, file_name, url, check_name, check_result, severity, notes, screenshot_url, tester, tested_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
            check_result = VALUES(check_result),
            severity = VALUES(severity),
            notes = VALUES(notes),
            screenshot_url = VALUES(screenshot_url),
            tester = VALUES(tester),
            tested_at = NOW()"
    );

    $cat = $input['category'] ?? '';
    $fname = $input['feature_name'] ?? '';
    $file = $input['file_name'] ?? '';
    $url = $input['url'] ?? '';
    $res = $input['check_result'] ?? null;
    $sev = $input['severity'] ?? 'minor';
    $notes = $input['notes'] ?? '';
    $ss = $input['screenshot_url'] ?? '';
    $tester = $input['tester'] ?? '';

    mysqli_stmt_bind_param($stmt, 'sssssssssss', $cat, $fid, $fname, $file, $url, $chk, $res, $sev, $notes, $ss, $tester);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success'=>true, 'message'=>'저장 완료']);
    } else {
        echo json_encode(['success'=>false, 'message'=>'저장 실패: ' . mysqli_error($connect)]);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// === PUT: 수정 상태 업데이트 (Claude CLI 수정 완료 등) ===
if ($method === 'PUT') {
    $fid = trim($input['feature_id'] ?? '');
    $chk = trim($input['check_name'] ?? '');

    if (empty($fid) || empty($chk)) {
        echo json_encode(['success'=>false, 'message'=>'feature_id, check_name 필수']);
        exit;
    }

    $updates = [];
    $params = [];
    $types = '';

    if (isset($input['status'])) {
        $valid = ['pass','fail','skip','fixed','retest'];
        if (in_array($input['status'], $valid)) {
            $updates[] = 'check_result = ?';
            $params[] = $input['status'];
            $types .= 's';
            if ($input['status'] === 'fixed') {
                $updates[] = 'fixed_at = NOW()';
            }
        }
    }
    if (isset($input['fix_commit'])) {
        $updates[] = 'fix_commit = ?';
        $params[] = $input['fix_commit'];
        $types .= 's';
    }
    if (isset($input['fix_note'])) {
        $updates[] = 'fix_note = ?';
        $params[] = $input['fix_note'];
        $types .= 's';
    }
    if (isset($input['tester'])) {
        $updates[] = 'tester = ?';
        $params[] = $input['tester'];
        $types .= 's';
    }
    if (isset($input['notes'])) {
        $updates[] = 'notes = ?';
        $params[] = $input['notes'];
        $types .= 's';
    }
    if (isset($input['severity'])) {
        $updates[] = 'severity = ?';
        $params[] = $input['severity'];
        $types .= 's';
    }

    if (empty($updates)) {
        echo json_encode(['success'=>false, 'message'=>'변경 사항 없음']);
        exit;
    }

    $sql = "UPDATE gangnamunni_qa_master SET " . implode(', ', $updates) . " WHERE feature_id = ? AND check_name = ?";
    $params[] = $fid;
    $params[] = $chk;
    $types .= 'ss';

    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);

    if (mysqli_stmt_execute($stmt)) {
        $affected = mysqli_stmt_affected_rows($stmt);
        echo json_encode(['success'=>true, 'message'=>'수정 완료', 'affected'=>$affected]);
    } else {
        echo json_encode(['success'=>false, 'message'=>'수정 실패']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

// === DELETE ===
if ($method === 'DELETE') {
    $fid = trim($input['feature_id'] ?? $_GET['feature_id'] ?? '');

    if (empty($fid)) {
        echo json_encode(['success'=>false, 'message'=>'feature_id 필수']);
        exit;
    }

    $chk = trim($input['check_name'] ?? $_GET['check_name'] ?? '');

    if ($chk) {
        $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_qa_master WHERE feature_id = ? AND check_name = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $fid, $chk);
    } else {
        $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_qa_master WHERE feature_id = ?");
        mysqli_stmt_bind_param($stmt, 's', $fid);
    }

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['success'=>true, 'message'=>'삭제 완료', 'affected'=>mysqli_stmt_affected_rows($stmt)]);
    } else {
        echo json_encode(['success'=>false, 'message'=>'삭제 실패']);
    }
    mysqli_stmt_close($stmt);
    exit;
}

echo json_encode(['success'=>false, 'message'=>'Invalid method']);
