<?php
/**
 * 클라이언트 기획서 관리 페이지
 * Google Sheets 기획서 데이터를 DB에서 조회하고, 개발 메모/상태를 관리
 *
 * @table gangnamunni_client_spec_sheets, gangnamunni_client_spec_notes
 * @created 2026-03-27
 */

session_start();

$admin_page_title = '클라이언트 기획서';
$admin_current = 'client_spec';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

// DB 연결
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
require_admin_login();
require_permission('client_spec');

// 설정 헬퍼
require_once __DIR__ . '/includes/settings_helper.php';

// CSRF 토큰
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ===== 테이블 자동 생성 =====
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_client_spec_sheets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gid VARCHAR(20) NOT NULL DEFAULT '',
    tab_name VARCHAR(200) NOT NULL DEFAULT '',
    tab_order INT DEFAULT 0,
    tab_type ENUM('category','pricing','planning','ui','analysis','estimate','matrix') DEFAULT 'planning',
    csv_data LONGTEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_gid (gid),
    INDEX idx_type (tab_type),
    INDEX idx_order (tab_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_client_spec_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sheet_gid VARCHAR(20) NOT NULL DEFAULT '',
    row_index INT NOT NULL DEFAULT 0,
    col_index INT NOT NULL DEFAULT -1,
    cell_key VARCHAR(100) DEFAULT '',
    note_text TEXT,
    dev_status ENUM('pending','in_progress','done','skipped') DEFAULT 'pending',
    created_by VARCHAR(100) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cell (sheet_gid, row_index, col_index),
    INDEX idx_gid (sheet_gid),
    INDEX idx_status (dev_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// dev_mode 설정 키가 없으면 생성
$dm_check = mysqli_query($connect, "SELECT id FROM gangnamunni_settings WHERE setting_key = 'client_spec_dev_mode' LIMIT 1");
if ($dm_check && mysqli_num_rows($dm_check) === 0) {
    mysqli_query($connect, "INSERT INTO gangnamunni_settings (setting_key, setting_value, setting_group, description) VALUES ('client_spec_dev_mode', '0', 'client_spec', '클라이언트 기획서 개발 모드')");
}

// Google Sheets URL 설정 키
$gs_check = mysqli_query($connect, "SELECT id FROM gangnamunni_settings WHERE setting_key = 'client_spec_google_sheets_url' LIMIT 1");
if ($gs_check && mysqli_num_rows($gs_check) === 0) {
    mysqli_query($connect, "INSERT INTO gangnamunni_settings (setting_key, setting_value, setting_group, description) VALUES ('client_spec_google_sheets_url', '', 'client_spec', 'Google Sheets URL')");
}

// ===== AJAX API 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $ajax_action = $_POST['ajax_action'];
    $post_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, $post_csrf)) {
        echo json_encode(['success' => false, 'error' => 'CSRF 토큰 오류. 페이지를 새로고침해주세요.']);
        exit;
    }

    // --- 메모 저장 ---
    if ($ajax_action === 'save_note') {
        $sheet_gid = trim($_POST['sheet_gid'] ?? '');
        $row_index = intval($_POST['row_index'] ?? 0);
        $col_index = intval($_POST['col_index'] ?? -1);
        $note_text = trim($_POST['note_text'] ?? '');
        $dev_status = $_POST['dev_status'] ?? 'pending';
        $valid_statuses = ['pending', 'in_progress', 'done', 'skipped'];
        if (!in_array($dev_status, $valid_statuses)) $dev_status = 'pending';

        $created_by = $_SESSION['ad_admin_name'] ?? $_SESSION['ad_admin_user'] ?? 'unknown';

        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_client_spec_notes (sheet_gid, row_index, col_index, note_text, dev_status, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE note_text = VALUES(note_text), dev_status = VALUES(dev_status), updated_at = NOW()");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'siisss', $sheet_gid, $row_index, $col_index, $note_text, $dev_status, $created_by);
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => '메모가 저장되었습니다.']);
            } else {
                echo json_encode(['success' => false, 'error' => mysqli_stmt_error($stmt)]);
            }
            mysqli_stmt_close($stmt);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($connect)]);
        }
        exit;
    }

    // --- 개발 모드 토글 ---
    if ($ajax_action === 'toggle_dev_mode') {
        $new_val = intval($_POST['dev_mode'] ?? 0) ? '1' : '0';
        $stmt = mysqli_prepare($connect,
            "UPDATE gangnamunni_settings SET setting_value = ? WHERE setting_key = 'client_spec_dev_mode'");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $new_val);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        echo json_encode(['success' => true, 'dev_mode' => $new_val]);
        exit;
    }

    // --- Google Sheets URL 저장 ---
    if ($ajax_action === 'save_sheets_url') {
        $url = trim($_POST['sheets_url'] ?? '');
        $stmt = mysqli_prepare($connect,
            "UPDATE gangnamunni_settings SET setting_value = ? WHERE setting_key = 'client_spec_google_sheets_url'");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $url);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // --- Google Sheets에서 CSV 새로고침 ---
    if ($ajax_action === 'refresh_sheet') {
        $gid = trim($_POST['gid'] ?? '');
        $sheets_url = getSettingValue($connect, 'client_spec_google_sheets_url', '');

        if (empty($sheets_url)) {
            echo json_encode(['success' => false, 'error' => 'Google Sheets URL이 설정되지 않았습니다.']);
            exit;
        }
        if (empty($gid)) {
            echo json_encode(['success' => false, 'error' => 'Sheet GID가 필요합니다.']);
            exit;
        }

        // Google Sheets를 CSV로 export
        // URL 형식: https://docs.google.com/spreadsheets/d/SPREADSHEET_ID/export?format=csv&gid=GID
        if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $sheets_url, $m)) {
            $spreadsheet_id = $m[1];
        } else {
            echo json_encode(['success' => false, 'error' => 'Google Sheets URL 형식이 올바르지 않습니다.']);
            exit;
        }

        $csv_url = "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}/export?format=csv&gid={$gid}";

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (SeoulBeauty Admin)',
                'follow_location' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $csv_content = @file_get_contents($csv_url, false, $ctx);
        if ($csv_content === false) {
            echo json_encode(['success' => false, 'error' => 'Google Sheets에서 데이터를 가져올 수 없습니다. 공유 설정을 확인해주세요.']);
            exit;
        }

        // UTF-8 BOM 제거
        $csv_content = preg_replace('/^\xEF\xBB\xBF/', '', $csv_content);

        $stmt = mysqli_prepare($connect,
            "UPDATE gangnamunni_client_spec_sheets SET csv_data = ?, updated_at = NOW() WHERE gid = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $csv_content, $gid);
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => '시트 데이터가 갱신되었습니다.', 'size' => strlen($csv_content)]);
            } else {
                echo json_encode(['success' => false, 'error' => mysqli_stmt_error($stmt)]);
            }
            mysqli_stmt_close($stmt);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($connect)]);
        }
        exit;
    }

    // --- 전체 시트 새로고침 ---
    if ($ajax_action === 'refresh_all') {
        $sheets_url = getSettingValue($connect, 'client_spec_google_sheets_url', '');
        if (empty($sheets_url)) {
            echo json_encode(['success' => false, 'error' => 'Google Sheets URL이 설정되지 않았습니다.']);
            exit;
        }
        if (!preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $sheets_url, $m)) {
            echo json_encode(['success' => false, 'error' => 'Google Sheets URL 형식이 올바르지 않습니다.']);
            exit;
        }
        $spreadsheet_id = $m[1];

        $sheets_r = mysqli_query($connect, "SELECT id, gid, tab_name FROM gangnamunni_client_spec_sheets WHERE is_active = 1 ORDER BY tab_order ASC");
        $results = [];
        $success_count = 0;
        $fail_count = 0;

        while ($sheet = mysqli_fetch_assoc($sheets_r)) {
            $csv_url = "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}/export?format=csv&gid={$sheet['gid']}";
            $ctx = stream_context_create([
                'http' => ['timeout' => 30, 'user_agent' => 'Mozilla/5.0 (SeoulBeauty Admin)', 'follow_location' => true],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
            ]);
            $csv_content = @file_get_contents($csv_url, false, $ctx);
            if ($csv_content !== false) {
                $csv_content = preg_replace('/^\xEF\xBB\xBF/', '', $csv_content);
                $upd = mysqli_prepare($connect, "UPDATE gangnamunni_client_spec_sheets SET csv_data = ?, updated_at = NOW() WHERE id = ?");
                if ($upd) {
                    mysqli_stmt_bind_param($upd, 'si', $csv_content, $sheet['id']);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                }
                $success_count++;
                $results[] = ['tab' => $sheet['tab_name'], 'ok' => true];
            } else {
                $fail_count++;
                $results[] = ['tab' => $sheet['tab_name'], 'ok' => false];
            }
            usleep(500000); // 0.5초 딜레이 (rate limit 방지)
        }
        echo json_encode(['success' => true, 'results' => $results, 'success_count' => $success_count, 'fail_count' => $fail_count]);
        exit;
    }

    // --- 메모 삭제 ---
    if ($ajax_action === 'delete_note') {
        $note_id = intval($_POST['note_id'] ?? 0);
        if ($note_id > 0) {
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_client_spec_notes WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $note_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => '알 수 없는 액션']);
    exit;
}

// ===== 데이터 로드 =====

// 개발 모드 상태
$dev_mode = getSettingValue($connect, 'client_spec_dev_mode', '0') === '1';

// Google Sheets URL
$google_sheets_url = getSettingValue($connect, 'client_spec_google_sheets_url', '');

// 시트 목록
$sheets = [];
$sheets_r = mysqli_query($connect, "SELECT * FROM gangnamunni_client_spec_sheets WHERE is_active = 1 ORDER BY tab_order ASC, id ASC");
if ($sheets_r) {
    while ($s = mysqli_fetch_assoc($sheets_r)) {
        $sheets[] = $s;
    }
}

// 개발 메모 전체 (gid별 인덱싱)
$all_notes = [];
$notes_r = mysqli_query($connect, "SELECT * FROM gangnamunni_client_spec_notes ORDER BY sheet_gid, row_index, col_index");
if ($notes_r) {
    while ($n = mysqli_fetch_assoc($notes_r)) {
        $key = $n['sheet_gid'] . '_' . $n['row_index'];
        $all_notes[$key] = $n;
    }
}

// 상태별 카운트
$status_counts = ['pending' => 0, 'in_progress' => 0, 'done' => 0, 'skipped' => 0];
$cnt_r = mysqli_query($connect, "SELECT dev_status, COUNT(*) AS cnt FROM gangnamunni_client_spec_notes GROUP BY dev_status");
if ($cnt_r) {
    while ($c = mysqli_fetch_assoc($cnt_r)) {
        if (isset($status_counts[$c['dev_status']])) {
            $status_counts[$c['dev_status']] = intval($c['cnt']);
        }
    }
}
$total_notes = array_sum($status_counts);
$done_notes = $status_counts['done'] + $status_counts['skipped'];
$completion_pct = $total_notes > 0 ? round(($done_notes / $total_notes) * 100, 1) : 0;

// 탭 타입 그룹 정의
$type_groups = [
    '기획' => ['category', 'ui', 'matrix', 'planning'],
    '가격' => ['pricing'],
    '분석' => ['analysis', 'estimate'],
];

// 그룹별 시트 분류
$grouped_sheets = [];
foreach ($type_groups as $group_name => $types) {
    $grouped_sheets[$group_name] = [];
    foreach ($sheets as $s) {
        if (in_array($s['tab_type'], $types)) {
            $grouped_sheets[$group_name][] = $s;
        }
    }
}

// 현재 선택된 탭
$current_gid = $_GET['gid'] ?? '';
$current_sheet = null;
if (!empty($current_gid)) {
    foreach ($sheets as $s) {
        if ($s['gid'] === $current_gid) {
            $current_sheet = $s;
            break;
        }
    }
}
// 기본값: 첫 번째 시트
if (!$current_sheet && !empty($sheets)) {
    $current_sheet = $sheets[0];
    $current_gid = $current_sheet['gid'];
}

// CSV 파싱 함수
function parseCSV($csv_data) {
    if (empty($csv_data)) return [];
    $rows = [];
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $csv_data);
    rewind($stream);
    while (($row = fgetcsv($stream)) !== false) {
        $rows[] = $row;
    }
    fclose($stream);
    return $rows;
}

// 빈 행/열 정리 함수
function trimCSVData($rows) {
    if (empty($rows)) return $rows;

    // 1) 사용된 열 인덱스 찾기 (데이터가 있는 열만)
    $used_cols = [];
    foreach ($rows as $row) {
        foreach ($row as $ci => $cell) {
            if (trim($cell) !== '') $used_cols[$ci] = true;
        }
    }
    if (empty($used_cols)) return [];
    ksort($used_cols);
    $used_col_indices = array_keys($used_cols);

    // 2) 빈 행 제거 + 사용된 열만 추출
    $cleaned = [];
    foreach ($rows as $row) {
        $new_row = [];
        $has_data = false;
        foreach ($used_col_indices as $ci) {
            $cell = $row[$ci] ?? '';
            $new_row[] = $cell;
            if (trim($cell) !== '') $has_data = true;
        }
        if ($has_data) {
            $cleaned[] = $new_row;
        }
    }
    return $cleaned;
}

// 현재 시트의 CSV 데이터 파싱
$csv_rows = [];
$csv_header = [];
if ($current_sheet && !empty($current_sheet['csv_data'])) {
    $raw_rows = parseCSV($current_sheet['csv_data']);
    $csv_rows = trimCSVData($raw_rows);
    if (!empty($csv_rows)) {
        $csv_header = $csv_rows[0];
    }
}

// 탭 타입별 색상 매핑
$type_colors = [
    'category' => 'primary',
    'ui'       => 'primary',
    'matrix'   => 'primary',
    'planning' => 'primary',
    'pricing'  => 'success',
    'analysis' => 'warning',
    'estimate' => 'warning',
];

// 탭 타입 한글 라벨
$type_labels = [
    'category' => '카테고리',
    'pricing'  => '가격',
    'planning' => '기획',
    'ui'       => 'UI',
    'analysis' => '분석',
    'estimate' => '견적',
    'matrix'   => '매트릭스',
];

// 상태 라벨/색상
$status_labels = [
    'pending'     => ['label' => '대기', 'color' => 'secondary'],
    'in_progress' => ['label' => '진행중', 'color' => 'primary'],
    'done'        => ['label' => '완료', 'color' => 'success'],
    'skipped'     => ['label' => '건너뜀', 'color' => 'warning'],
];

// ===== HTML 렌더링 =====
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* 기획서 전용 스타일 */
.spec-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1rem;
}
[data-bs-theme="light"] .spec-toolbar { background: var(--bs-tertiary-bg); border: 1px solid #dee2e6; }
[data-bs-theme="dark"] .spec-toolbar { background: #1a1d21; border: 1px solid #343a40; }

.spec-progress-bar { height: 8px; border-radius: 4px; overflow: hidden; }
[data-bs-theme="light"] .spec-progress-bar { background: #e9ecef; }
[data-bs-theme="dark"] .spec-progress-bar { background: #343a40; }
.spec-progress-fill { height: 100%; border-radius: 4px; transition: width 0.4s ease; background: linear-gradient(90deg, #198754, #20c997); }

.spec-tab-group { margin-bottom: 0.25rem; }
.spec-tab-group-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.15rem 0.5rem;
    border-radius: 0.25rem 0.25rem 0 0;
    display: inline-block;
}

.spec-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: 0.5rem;
    border: 1px solid var(--bs-border-color);
}
.spec-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.spec-table th, .spec-table td {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--bs-border-color);
    vertical-align: top;
    max-width: 500px;
    word-wrap: break-word;
    white-space: pre-line;
}
/* 빈 셀 최소화 (레이아웃 유지) */

/* 가격 탭 전용 */
.spec-type-pricing td { white-space: nowrap; font-size: 0.82rem; }
.spec-type-pricing .spec-price-cell { color: #dc2626; font-weight: 700; }
[data-bs-theme="dark"] .spec-type-pricing .spec-price-cell { color: #f87171; }

/* 기획 탭: 세부내용 줄바꿈 */
.spec-type-planning td { white-space: pre-line; }

/* 1위 행 하이라이트 */
.spec-rank-1 { font-weight: 700; }
[data-bs-theme="light"] .spec-rank-1 { background: #fef3c7; }
[data-bs-theme="dark"] .spec-rank-1 { background: rgba(234,179,8,0.12); }
.spec-table th {
    font-weight: 600;
    position: sticky;
    top: 0;
    z-index: 2;
}
/* 기획 (blue) */
.spec-type-planning th, .spec-type-category th, .spec-type-ui th, .spec-type-matrix th {
    background: var(--spec-header-plan);
    color: #fff;
}
/* 가격 (green) */
.spec-type-pricing th {
    background: var(--spec-header-price);
    color: #fff;
}
/* 분석 (orange) */
.spec-type-analysis th, .spec-type-estimate th {
    background: var(--spec-header-analysis);
    color: #fff;
}

[data-bs-theme="light"] {
    --spec-header-plan: #2563eb;
    --spec-header-price: #059669;
    --spec-header-analysis: #d97706;
    --spec-row-hover: rgba(0,0,0,0.03);
    --spec-merged-bg: #f0f4ff;
    --spec-price-bg: #f0fdf4;
}
[data-bs-theme="dark"] {
    --spec-header-plan: #1d4ed8;
    --spec-header-price: #047857;
    --spec-header-analysis: #b45309;
    --spec-row-hover: rgba(255,255,255,0.03);
    --spec-merged-bg: rgba(37,99,235,0.1);
    --spec-price-bg: rgba(5,150,105,0.1);
}

.spec-table tbody tr:hover { background: var(--spec-row-hover); }

/* 가격 셀 */
.spec-price-cell {
    text-align: right;
    font-variant-numeric: tabular-nums;
    font-weight: 500;
}

/* 병원명 */
.spec-hospital-name { font-weight: 700; }

/* 카테고리 머지 셀 */
.spec-merged-cell {
    background: var(--spec-merged-bg);
    font-weight: 600;
    vertical-align: middle;
}

/* 개발 메모 행 */
.spec-note-row td {
    padding: 0.5rem 0.6rem;
    border-top: none;
}
[data-bs-theme="light"] .spec-note-row td { background: var(--bs-body-bg)beb; }
[data-bs-theme="dark"] .spec-note-row td { background: rgba(217,119,6,0.08); }

.spec-note-row .note-area {
    display: flex;
    gap: 0.5rem;
    align-items: flex-start;
    flex-wrap: wrap;
}
.spec-note-row textarea {
    flex: 1;
    min-width: 200px;
    font-size: 0.8rem;
    resize: vertical;
}
.spec-note-row select { width: auto; font-size: 0.8rem; }

/* 빈 셀 최소화 */
.spec-table td:empty { padding: 0.2rem 0.3rem; opacity: 0.3; }

/* 행번호 */
.spec-row-num {
    text-align: center;
    font-size: 0.75rem;
    color: var(--bs-secondary);
    width: 36px;
    min-width: 36px;
}

/* 토글 expand 버튼 */
.spec-expand-btn {
    cursor: pointer;
    font-size: 0.75rem;
    width: 24px;
    height: 24px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    border: 1px solid var(--bs-border-color);
    background: transparent;
    transition: transform 0.2s;
}
.spec-expand-btn.open { transform: rotate(90deg); }

/* 상태 뱃지 (행 왼쪽) */
.spec-row-status {
    width: 4px;
    min-width: 4px;
    padding: 0;
    border: none;
}
.spec-row-status.status-pending { background: var(--bs-secondary); }
.spec-row-status.status-in_progress { background: var(--bs-primary); }
.spec-row-status.status-done { background: var(--bs-success); }
.spec-row-status.status-skipped { background: var(--bs-warning); }

/* 시트 없음 */
.spec-empty-state {
    text-align: center;
    padding: 4rem 2rem;
}
.spec-empty-state i { font-size: 3rem; opacity: 0.3; }

/* Google Sheets URL 입력 모달 */
#sheetsUrlModal .modal-body input { font-size: 0.85rem; }
</style>

<div class="container-fluid py-3">

    <!-- 상단 도구 모음 -->
    <div class="spec-toolbar">
        <h5 class="mb-0 me-auto">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>
            클라이언트 기획서
        </h5>

        <!-- 개발 모드 토글 -->
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="devModeToggle" <?= $dev_mode ? 'checked' : '' ?>>
            <label class="form-check-label small fw-semibold" for="devModeToggle">개발 모드</label>
        </div>

        <!-- Google Sheets 링크 -->
        <?php if (!empty($google_sheets_url)): ?>
        <a href="<?= htmlspecialchars($google_sheets_url) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Google Sheets 열기">
            <i class="bi bi-box-arrow-up-right me-1"></i>Sheets
        </a>
        <?php endif; ?>

        <!-- Sheets URL 설정 -->
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#sheetsUrlModal" title="Google Sheets URL 설정">
            <i class="bi bi-gear"></i>
        </button>

        <!-- 전체 새로고침 -->
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnRefreshAll" title="전체 시트 새로고침">
            <i class="bi bi-arrow-clockwise me-1"></i>새로고침
        </button>
    </div>

    <!-- 진행 상황 요약 -->
    <?php if ($total_notes > 0): ?>
    <div class="row g-2 mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body py-2 px-3">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span class="small fw-semibold">진행 현황</span>
                        <div class="spec-progress-bar flex-grow-1" style="min-width:120px;">
                            <div class="spec-progress-fill" style="width:<?= $completion_pct ?>%"></div>
                        </div>
                        <span class="small fw-bold"><?= $completion_pct ?>%</span>
                        <span class="badge bg-secondary"><?= $status_counts['pending'] ?> 대기</span>
                        <span class="badge bg-primary"><?= $status_counts['in_progress'] ?> 진행중</span>
                        <span class="badge bg-success"><?= $status_counts['done'] ?> 완료</span>
                        <span class="badge bg-warning text-dark"><?= $status_counts['skipped'] ?> 건너뜀</span>
                        <span class="small text-muted">총 <?= $total_notes ?>건</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 탭 그룹 네비게이션 -->
    <?php if (!empty($sheets)): ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 px-3">
            <?php foreach ($type_groups as $group_name => $types):
                $group_sheets = $grouped_sheets[$group_name];
                if (empty($group_sheets)) continue;
                $group_color = ($group_name === '기획') ? 'primary' : (($group_name === '가격') ? 'success' : 'warning');
            ?>
            <div class="spec-tab-group d-inline-block me-3 mb-1">
                <span class="spec-tab-group-label bg-<?= $group_color ?> text-white"><?= $group_name ?></span>
                <div class="btn-group btn-group-sm" role="group">
                    <?php foreach ($group_sheets as $gs): ?>
                    <a href="?gid=<?= urlencode($gs['gid']) ?>"
                       class="btn btn-<?= ($current_gid === $gs['gid']) ? '' : 'outline-' ?><?= $group_color ?> btn-sm"
                       style="font-size:0.78rem;">
                        <?= htmlspecialchars($gs['tab_name']) ?>
                        <span class="badge bg-<?= $group_color === 'warning' ? 'dark' : 'light' ?> text-<?= $group_color === 'warning' ? 'white' : 'dark' ?> ms-1" style="font-size:0.65rem;">
                            <?= htmlspecialchars($type_labels[$gs['tab_type']] ?? $gs['tab_type']) ?>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 시트 컨텐츠 -->
    <?php if ($current_sheet): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex align-items-center flex-wrap gap-2 py-2">
            <h6 class="mb-0">
                <span class="badge bg-<?= $type_colors[$current_sheet['tab_type']] ?? 'secondary' ?> me-1">
                    <?= htmlspecialchars($type_labels[$current_sheet['tab_type']] ?? $current_sheet['tab_type']) ?>
                </span>
                <?= htmlspecialchars($current_sheet['tab_name']) ?>
            </h6>
            <span class="text-muted small ms-auto">
                GID: <?= htmlspecialchars($current_sheet['gid']) ?>
                | 갱신: <?= htmlspecialchars($current_sheet['updated_at'] ?? '-') ?>
            </span>
            <?php
            // 탭별 관련 관리 페이지 바로가기 버튼
            $quick_links = [
                '222025344' => ['admin_treatments.php?tab=categories', '대분류 관리', 'bi-folder', 'success'],
            ];
            if (isset($quick_links[$current_gid])):
                $ql = $quick_links[$current_gid];
            ?>
            <a href="<?= $ql[0] ?>" class="btn btn-sm btn-<?= $ql[3] ?>" target="_blank">
                <i class="<?= $ql[2] ?> me-1"></i><?= $ql[1] ?>
            </a>
            <a href="admin_treatments.php" class="btn btn-sm btn-outline-info" target="_blank">
                <i class="bi bi-list-ul me-1"></i>중분류 관리
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-primary btn-refresh-sheet" data-gid="<?= htmlspecialchars($current_sheet['gid']) ?>">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="card-body p-0">
            <?php if (empty($csv_rows)): ?>
            <div class="spec-empty-state">
                <i class="bi bi-table d-block mb-3"></i>
                <p class="text-muted">시트 데이터가 없습니다.</p>
                <p class="small text-muted">Google Sheets URL을 설정하고 새로고침 버튼을 눌러주세요.</p>
            </div>
            <?php else: ?>
            <div class="spec-table-wrap" style="max-height:75vh; overflow-y:auto;">
                <table class="spec-table spec-type-<?= htmlspecialchars($current_sheet['tab_type']) ?>" id="specTable">
                    <thead>
                        <tr>
                            <?php if ($dev_mode): ?>
                            <th class="spec-row-num">#</th>
                            <th style="width:28px;"></th>
                            <?php endif; ?>
                            <?php
                            $col_count = count($csv_header);
                            foreach ($csv_header as $ci => $col):
                            ?>
                            <th><?= htmlspecialchars($col) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $is_pricing = in_array($current_sheet['tab_type'], ['pricing', 'estimate']);
                        $is_category = in_array($current_sheet['tab_type'], ['category', 'matrix']);
                        $prev_first_col = '';

                        for ($ri = 1; $ri < count($csv_rows); $ri++):
                            $row = $csv_rows[$ri];
                            // 행 전체가 비어있으면 빈 행으로 표시하되 축소
                            $is_empty_row = true;
                            foreach ($row as $cell) {
                                if (trim($cell) !== '') { $is_empty_row = false; break; }
                            }

                            // 노트 키
                            $note_key = $current_gid . '_' . $ri;
                            $note = $all_notes[$note_key] ?? null;
                            $row_status = $note ? $note['dev_status'] : '';
                        ?>
                        <tr class="spec-data-row<?= $is_empty_row ? ' table-light' : '' ?>" data-row="<?= $ri ?>">
                            <?php if ($dev_mode): ?>
                            <td class="spec-row-num"><?= $ri ?></td>
                            <td style="padding:0.2rem;">
                                <?php if ($row_status): ?>
                                <div class="spec-row-status status-<?= $row_status ?>" style="width:4px;height:100%;min-height:20px;border-radius:2px;display:inline-block;"></div>
                                <?php endif; ?>
                                <button type="button" class="spec-expand-btn<?= $note ? ' open' : '' ?>" data-row="<?= $ri ?>" title="메모 열기/닫기">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                            </td>
                            <?php endif; ?>
                            <?php
                            for ($ci = 0; $ci < $col_count; $ci++):
                                $cell = $row[$ci] ?? '';
                                $trimmed = trim($cell);
                                $extra_class = '';
                                $cell_html = htmlspecialchars($trimmed);

                                // 카테고리 탭: 첫 번째 열 머지 효과
                                if ($is_category && $ci === 0 && $trimmed !== '') {
                                    if ($trimmed !== $prev_first_col) {
                                        $extra_class = 'spec-merged-cell';
                                        $prev_first_col = $trimmed;
                                    }
                                }

                                // 가격 탭: 숫자 셀 포맷
                                if ($is_pricing && $ci > 0 && preg_match('/^[\d,]+$/', str_replace([' ', '원', '₩'], '', $trimmed))) {
                                    $num = intval(str_replace([',', ' ', '원', '₩'], '', $trimmed));
                                    if ($num > 0) {
                                        $extra_class = 'spec-price-cell';
                                        $cell_html = '&#8361;' . number_format($num);
                                    }
                                }

                                // 가격 탭: 첫 번째 열 = 병원명
                                if ($is_pricing && $ci === 0 && $trimmed !== '') {
                                    $extra_class .= ' spec-hospital-name';
                                }
                            ?>
                            <td class="<?= $extra_class ?>"><?= $cell_html ?></td>
                            <?php endfor; ?>
                        </tr>
                        <?php if ($dev_mode): ?>
                        <tr class="spec-note-row" data-note-row="<?= $ri ?>" style="display:<?= $note ? 'table-row' : 'none' ?>;">
                            <td colspan="<?= $col_count + 2 ?>">
                                <div class="note-area">
                                    <textarea class="form-control form-control-sm note-text" rows="2"
                                              placeholder="개발 메모 입력..."
                                              data-gid="<?= htmlspecialchars($current_gid) ?>"
                                              data-row="<?= $ri ?>"><?= htmlspecialchars($note['note_text'] ?? '') ?></textarea>
                                    <select class="form-select form-select-sm note-status"
                                            data-gid="<?= htmlspecialchars($current_gid) ?>"
                                            data-row="<?= $ri ?>">
                                        <?php foreach ($status_labels as $sk => $sl): ?>
                                        <option value="<?= $sk ?>" <?= ($note && $note['dev_status'] === $sk) ? 'selected' : '' ?>>
                                            <?= $sl['label'] ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-sm btn-primary btn-save-note"
                                            data-gid="<?= htmlspecialchars($current_gid) ?>"
                                            data-row="<?= $ri ?>">
                                        <i class="bi bi-check-lg"></i> 저장
                                    </button>
                                    <?php if ($note): ?>
                                    <small class="text-muted align-self-center">
                                        <?= htmlspecialchars($note['created_by'] ?? '') ?>
                                        <?= $note['updated_at'] ? date('m/d H:i', strtotime($note['updated_at'])) : '' ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted py-2">
                총 <?= count($csv_rows) - 1 ?>행 x <?= $col_count ?>열
                <?php if ($dev_mode): ?>
                | 이 시트 메모: <?php
                    $sheet_note_count = 0;
                    foreach ($all_notes as $nk => $nv) {
                        if (strpos($nk, $current_gid . '_') === 0) $sheet_note_count++;
                    }
                    echo $sheet_note_count;
                ?>건
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif (empty($sheets)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body spec-empty-state">
            <i class="bi bi-file-earmark-spreadsheet d-block mb-3"></i>
            <h5>등록된 시트가 없습니다</h5>
            <p class="text-muted mb-3">Google Sheets URL을 설정하고 데이터를 가져와주세요.</p>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sheetsUrlModal">
                <i class="bi bi-gear me-1"></i>Google Sheets URL 설정
            </button>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Google Sheets URL 설정 모달 -->
<div class="modal fade" id="sheetsUrlModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-gear me-1"></i>Google Sheets 설정</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small fw-semibold">Google Sheets URL</label>
                <input type="url" class="form-control" id="inputSheetsUrl"
                       value="<?= htmlspecialchars($google_sheets_url) ?>"
                       placeholder="https://docs.google.com/spreadsheets/d/...">
                <div class="form-text">공유 설정에서 "링크가 있는 모든 사용자" 보기 권한을 부여해주세요.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSaveSheetsUrl">저장</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const CSRF = '<?= $csrf_token ?>';
    const BASE_URL = window.location.pathname;

    // 유틸: AJAX POST
    function ajaxPost(data, callback) {
        data.csrf_token = CSRF;
        const fd = new FormData();
        for (const k in data) fd.append(k, data[k]);

        fetch(BASE_URL, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(json => callback(null, json))
            .catch(err => callback(err, null));
    }

    // 유틸: Toast 알림
    function showToast(msg, type) {
        type = type || 'success';
        let container = document.getElementById('specToastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'specToastContainer';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '9999';
            document.body.appendChild(container);
        }
        const id = 'toast_' + Date.now();
        const colors = { success: 'bg-success', danger: 'bg-danger', warning: 'bg-warning text-dark', info: 'bg-info' };
        container.insertAdjacentHTML('beforeend',
            '<div id="' + id + '" class="toast align-items-center text-white ' + (colors[type] || 'bg-secondary') + ' border-0" role="alert">' +
            '<div class="d-flex"><div class="toast-body">' + msg + '</div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>');
        const toastEl = document.getElementById(id);
        const bsToast = new bootstrap.Toast(toastEl, { delay: 3000 });
        bsToast.show();
        toastEl.addEventListener('hidden.bs.toast', function() { toastEl.remove(); });
    }

    // 개발 모드 토글
    const devToggle = document.getElementById('devModeToggle');
    if (devToggle) {
        devToggle.addEventListener('change', function() {
            ajaxPost({ ajax_action: 'toggle_dev_mode', dev_mode: this.checked ? 1 : 0 }, function(err, json) {
                if (err || !json || !json.success) {
                    showToast('개발 모드 변경 실패', 'danger');
                    return;
                }
                // 페이지 리로드하여 메모 영역 표시/숨김
                window.location.reload();
            });
        });
    }

    // 메모 확장/축소 버튼
    document.querySelectorAll('.spec-expand-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const row = this.getAttribute('data-row');
            const noteRow = document.querySelector('tr[data-note-row="' + row + '"]');
            if (!noteRow) return;
            const isHidden = noteRow.style.display === 'none';
            noteRow.style.display = isHidden ? 'table-row' : 'none';
            this.classList.toggle('open', isHidden);
        });
    });

    // 메모 저장
    document.querySelectorAll('.btn-save-note').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const gid = this.getAttribute('data-gid');
            const row = this.getAttribute('data-row');
            const noteRow = document.querySelector('tr[data-note-row="' + row + '"]');
            if (!noteRow) return;
            const text = noteRow.querySelector('.note-text').value;
            const status = noteRow.querySelector('.note-status').value;

            this.disabled = true;
            const self = this;

            ajaxPost({
                ajax_action: 'save_note',
                sheet_gid: gid,
                row_index: row,
                col_index: -1,
                note_text: text,
                dev_status: status
            }, function(err, json) {
                self.disabled = false;
                if (err || !json || !json.success) {
                    showToast('저장 실패: ' + ((json && json.error) || '오류'), 'danger');
                    return;
                }
                showToast('메모가 저장되었습니다.', 'success');

                // 상태 표시 업데이트
                const dataRow = document.querySelector('tr.spec-data-row[data-row="' + row + '"]');
                if (dataRow) {
                    const statusDiv = dataRow.querySelector('.spec-row-status');
                    if (statusDiv) {
                        statusDiv.className = 'spec-row-status status-' + status;
                    }
                }
            });
        });
    });

    // 개별 시트 새로고침
    document.querySelectorAll('.btn-refresh-sheet').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const gid = this.getAttribute('data-gid');
            const icon = this.querySelector('i');
            this.disabled = true;
            if (icon) icon.classList.add('spin');

            ajaxPost({ ajax_action: 'refresh_sheet', gid: gid }, function(err, json) {
                btn.disabled = false;
                if (icon) icon.classList.remove('spin');
                if (err || !json || !json.success) {
                    showToast('새로고침 실패: ' + ((json && json.error) || '오류'), 'danger');
                    return;
                }
                showToast('시트 데이터 갱신 완료 (' + (json.size || 0) + ' bytes)', 'success');
                setTimeout(function() { window.location.reload(); }, 800);
            });
        });
    });

    // 전체 새로고침
    const btnRefreshAll = document.getElementById('btnRefreshAll');
    if (btnRefreshAll) {
        btnRefreshAll.addEventListener('click', function() {
            if (!confirm('전체 시트를 Google Sheets에서 다시 가져옵니다. 진행하시겠습니까?')) return;
            this.disabled = true;
            const icon = this.querySelector('i');
            if (icon) icon.classList.add('spin');

            ajaxPost({ ajax_action: 'refresh_all' }, function(err, json) {
                btnRefreshAll.disabled = false;
                if (icon) icon.classList.remove('spin');
                if (err || !json || !json.success) {
                    showToast('전체 새로고침 실패: ' + ((json && json.error) || '오류'), 'danger');
                    return;
                }
                showToast('전체 새로고침 완료: 성공 ' + json.success_count + '건, 실패 ' + json.fail_count + '건', 'success');
                setTimeout(function() { window.location.reload(); }, 1000);
            });
        });
    }

    // Google Sheets URL 저장
    const btnSaveUrl = document.getElementById('btnSaveSheetsUrl');
    if (btnSaveUrl) {
        btnSaveUrl.addEventListener('click', function() {
            const url = document.getElementById('inputSheetsUrl').value.trim();
            ajaxPost({ ajax_action: 'save_sheets_url', sheets_url: url }, function(err, json) {
                if (err || !json || !json.success) {
                    showToast('URL 저장 실패', 'danger');
                    return;
                }
                showToast('Google Sheets URL이 저장되었습니다.', 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('sheetsUrlModal'));
                if (modal) modal.hide();
                setTimeout(function() { window.location.reload(); }, 500);
            });
        });
    }
});

/* 스핀 애니메이션 */
var styleSheet = document.createElement('style');
styleSheet.textContent = '@keyframes spin-anim { from { transform: rotate(0deg); } to { transform: rotate(360deg); } } .spin { animation: spin-anim 1s linear infinite; }';
document.head.appendChild(styleSheet);
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
