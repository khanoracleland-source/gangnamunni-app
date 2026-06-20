<?php
/**
 * admin_fix_log.php - 수정 이력 관리 페이지
 *
 * 시스템 수정/변경 이력을 기록하고 관리하는 페이지
 * - 수정 전/후 스크린샷 자동 캡처 (Playwright 연동)
 * - Before/After 비교 슬라이더
 * - 카테고리별 필터, 날짜별 그룹 표시
 * - AJAX CRUD (페이지 새로고침 없음)
 *
 * @since 2026-03-04
 * @version 1.0.0
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/admin_auth.php';

require_admin_login();
require_permission('fix_log');

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/theme_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
loadThemeSettings($connect);


// 테이블 자동 생성 (최초 1회)
$create_table_sql = "CREATE TABLE IF NOT EXISTS `gangnamunni_fix_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fix_date` DATE NOT NULL,
    `issue_number` VARCHAR(20) DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `target_page` VARCHAR(100) DEFAULT NULL,
    `target_url` VARCHAR(500) DEFAULT NULL,
    `category` ENUM('bug_fix','feature','manual','improvement') NOT NULL DEFAULT 'bug_fix',
    `status` ENUM('completed','in_progress','planned') NOT NULL DEFAULT 'completed',
    `screenshot_before` VARCHAR(500) DEFAULT NULL,
    `screenshot_after` VARCHAR(500) DEFAULT NULL,
    `admin_id` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_fix_date` (`fix_date`),
    INDEX `idx_category` (`category`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
mysqli_query($connect, $create_table_sql);

// 기본 URL 설정
$base_url = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
$capture_api_url = 'http://localhost:3100/api/book/capture-single';

// 현재 관리자
$current_admin = get_current_admin();

// ========== AJAX 핸들러 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $action = $_POST['action'];

    switch ($action) {

        // ── 목록 조회 ──
        case 'list':
            $where = '1=1';
            $params = [];
            $types = '';

            // 날짜 필터
            if (!empty($_POST['date_from'])) {
                $where .= ' AND fix_date >= ?';
                $params[] = $_POST['date_from'];
                $types .= 's';
            }
            if (!empty($_POST['date_to'])) {
                $where .= ' AND fix_date <= ?';
                $params[] = $_POST['date_to'];
                $types .= 's';
            }

            // 카테고리 필터
            if (!empty($_POST['category']) && in_array($_POST['category'], ['bug_fix','feature','manual','improvement'])) {
                $where .= ' AND category = ?';
                $params[] = $_POST['category'];
                $types .= 's';
            }

            // 상태 필터
            if (!empty($_POST['status']) && in_array($_POST['status'], ['completed','in_progress','planned'])) {
                $where .= ' AND status = ?';
                $params[] = $_POST['status'];
                $types .= 's';
            }

            // 검색어
            if (!empty($_POST['search'])) {
                $where .= ' AND (title LIKE ? OR description LIKE ? OR target_page LIKE ? OR issue_number LIKE ?)';
                $search = '%' . $_POST['search'] . '%';
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $types .= 'ssss';
            }

            $sql = "SELECT * FROM gangnamunni_fix_log WHERE $where ORDER BY fix_date DESC, id DESC";
            $stmt = mysqli_prepare($connect, $sql);
            if ($types && count($params) > 0) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
            }
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            $rows = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
            mysqli_stmt_close($stmt);

            echo json_encode(['success' => true, 'data' => $rows]);
            break;

        // ── 통계 조회 ──
        case 'stats':
            $stats = [];
            $stats['total'] = (int)mysqli_fetch_assoc(mysqli_query($connect,
                "SELECT COUNT(*) as c FROM gangnamunni_fix_log"))['c'];
            $stats['bug_fix'] = (int)mysqli_fetch_assoc(mysqli_query($connect,
                "SELECT COUNT(*) as c FROM gangnamunni_fix_log WHERE category='bug_fix'"))['c'];
            $stats['feature'] = (int)mysqli_fetch_assoc(mysqli_query($connect,
                "SELECT COUNT(*) as c FROM gangnamunni_fix_log WHERE category='feature'"))['c'];
            $stats['this_month'] = (int)mysqli_fetch_assoc(mysqli_query($connect,
                "SELECT COUNT(*) as c FROM gangnamunni_fix_log WHERE fix_date >= DATE_FORMAT(NOW(),'%Y-%m-01')"))['c'];

            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        // ── 단건 조회 ──
        case 'get':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_fix_log WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($row) {
                echo json_encode(['success' => true, 'data' => $row]);
            } else {
                echo json_encode(['success' => false, 'message' => '데이터를 찾을 수 없습니다.']);
            }
            break;

        // ── 추가 ──
        case 'add':
            $fix_date = $_POST['fix_date'] ?? date('Y-m-d');
            $issue_number = trim($_POST['issue_number'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $target_page = trim($_POST['target_page'] ?? '');
            $target_url = trim($_POST['target_url'] ?? '');
            $category = $_POST['category'] ?? 'bug_fix';
            $status = $_POST['status'] ?? 'completed';
            $admin_id = $current_admin['id'];

            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => '제목을 입력해주세요.']);
                break;
            }

            $stmt = mysqli_prepare($connect,
                "INSERT INTO gangnamunni_fix_log (fix_date, issue_number, title, description, target_page, target_url, category, status, admin_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssssssssi',
                $fix_date, $issue_number, $title, $description, $target_page, $target_url, $category, $status, $admin_id);

            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($connect);
                mysqli_stmt_close($stmt);
                echo json_encode(['success' => true, 'message' => '수정 이력이 추가되었습니다.', 'id' => $new_id]);
            } else {
                mysqli_stmt_close($stmt);
                echo json_encode(['success' => false, 'message' => 'DB 오류: ' . mysqli_error($connect)]);
            }
            break;

        // ── 수정 ──
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $fix_date = $_POST['fix_date'] ?? date('Y-m-d');
            $issue_number = trim($_POST['issue_number'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $target_page = trim($_POST['target_page'] ?? '');
            $target_url = trim($_POST['target_url'] ?? '');
            $category = $_POST['category'] ?? 'bug_fix';
            $status = $_POST['status'] ?? 'completed';

            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => '제목을 입력해주세요.']);
                break;
            }

            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_fix_log SET fix_date=?, issue_number=?, title=?, description=?, target_page=?, target_url=?, category=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssssssssi',
                $fix_date, $issue_number, $title, $description, $target_page, $target_url, $category, $status, $id);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                echo json_encode(['success' => true, 'message' => '수정 이력이 업데이트되었습니다.']);
            } else {
                mysqli_stmt_close($stmt);
                echo json_encode(['success' => false, 'message' => 'DB 오류: ' . mysqli_error($connect)]);
            }
            break;

        // ── 삭제 ──
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);

            // 스크린샷 파일 경로 조회
            $stmt = mysqli_prepare($connect, "SELECT screenshot_before, screenshot_after FROM gangnamunni_fix_log WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            // DB 레코드 삭제
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_fix_log WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                // 스크린샷 파일 삭제 (서버에서)
                if ($row) {
                    foreach (['screenshot_before', 'screenshot_after'] as $field) {
                        if (!empty($row[$field]) && file_exists(__DIR__ . '/' . $row[$field])) {
                            @unlink(__DIR__ . '/' . $row[$field]);
                        }
                    }
                }
                echo json_encode(['success' => true, 'message' => '삭제되었습니다.']);
            } else {
                mysqli_stmt_close($stmt);
                echo json_encode(['success' => false, 'message' => 'DB 오류: ' . mysqli_error($connect)]);
            }
            break;

        // ── 스크린샷 캡처 (수정 전) ──
        case 'capture_before':
        // ── 스크린샷 캡처 (수정 후) ──
        case 'capture_after':
            $id = (int)($_POST['id'] ?? 0);
            $url = trim($_POST['capture_url'] ?? '');
            $type = ($action === 'capture_before') ? 'before' : 'after';

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => '수정 이력 ID가 필요합니다.']);
                break;
            }
            if (empty($url)) {
                echo json_encode(['success' => false, 'message' => '캡처할 URL을 입력해주세요.']);
                break;
            }

            // 캡처 API 호출 (server.js capture-single: {url, label} → 자체 저장 후 path 반환)
            $label = "fix_{$id}_{$type}_" . time();
            $post_data = json_encode([
                'url' => $url,
                'label' => $label
            ]);

            $ch = curl_init($capture_api_url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($http_code !== 200 || !$response) {
                echo json_encode([
                    'success' => false,
                    'message' => '캡처 API 호출 실패 (HTTP ' . $http_code . '): ' . ($curl_error ?: substr($response, 0, 200))
                ]);
                break;
            }

            $api_result = json_decode($response, true);
            if (!$api_result || !($api_result['success'] ?? false)) {
                echo json_encode([
                    'success' => false,
                    'message' => '캡처 실패: ' . ($api_result['error'] ?? $api_result['message'] ?? '알 수 없는 오류')
                ]);
                break;
            }

            // API가 반환한 경로를 DB에 저장
            $save_path = $api_result['path'];  // "uploads/fix_log/YYYYMMDD/label.jpg"
            $field = ($type === 'before') ? 'screenshot_before' : 'screenshot_after';
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_fix_log SET {$field} = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $save_path, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            echo json_encode([
                'success' => true,
                'message' => ($type === 'before' ? '수정 전' : '수정 후') . ' 스크린샷이 캡처되었습니다.',
                'path' => $save_path
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => '알 수 없는 액션: ' . $action]);
            break;
    }

    mysqli_close($connect);
    exit;
}

// ========== 페이지 렌더링 ==========

// 페이지 메타
$admin_page_title = '수정 이력';
$admin_current = 'fix_log';
$admin_page_description = '시스템 수정/변경 이력을 기록하고, 수정 전후 스크린샷을 비교합니다.';

// 페이지별 CSS
$admin_extra_css = '
    /* 통계 카드 */
    .stat-card { border: none; border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s; overflow: hidden; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
    .stat-border { width: 4px; min-height: 100%; }

    /* 날짜 그룹 헤더 */
    .date-group-header td {
        background: var(--bs-tertiary-bg) !important;
        font-weight: 700;
        font-size: 13px;
        padding: 8px 12px !important;
        border-bottom: 2px solid var(--bs-border-color) !important;
    }

    /* 카테고리 배지 */
    .badge-bug_fix { background: #dc3545; }
    .badge-feature { background: #0d6efd; }
    .badge-manual { background: #198754; }
    .badge-improvement { background: #ffc107; color: #000; }

    /* 상태 배지 */
    .badge-completed { background: #198754; }
    .badge-in_progress { background: #fd7e14; }
    .badge-planned { background: #6c757d; }

    /* 스크린샷 썸네일 */
    .screenshot-thumb {
        width: 60px;
        height: 40px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid var(--bs-border-color);
        cursor: pointer;
        transition: transform 0.2s;
    }
    .screenshot-thumb:hover { transform: scale(1.1); }

    /* 비교 슬라이더 */
    .compare-container {
        position: relative;
        overflow: hidden;
        border-radius: 8px;
        user-select: none;
        -webkit-user-select: none;
    }
    .compare-container img {
        display: block;
        width: 100%;
        height: auto;
    }
    .compare-after {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
    }
    .compare-slider {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--bs-primary);
        cursor: ew-resize;
        z-index: 10;
    }
    .compare-slider::before {
        content: "";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--bs-primary);
        border: 3px solid #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    .compare-slider::after {
        content: "\2194";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #fff;
        font-size: 14px;
        font-weight: bold;
        z-index: 1;
    }
    .compare-label {
        position: absolute;
        top: 8px;
        padding: 2px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        z-index: 5;
        pointer-events: none;
    }
    .compare-label-before { left: 8px; background: rgba(220,53,69,0.85); color: #fff; }
    .compare-label-after { right: 8px; background: rgba(25,135,84,0.85); color: #fff; }

    /* 모달 캡처 버튼 */
    .capture-btn { position: relative; }
    .capture-btn .spinner-border { display: none; }
    .capture-btn.loading .spinner-border { display: inline-block; }
    .capture-btn.loading .btn-text { display: none; }

    /* 캡처 미리보기 */
    .capture-preview {
        max-width: 100%;
        max-height: 120px;
        object-fit: contain;
        border-radius: 6px;
        border: 1px solid var(--bs-border-color);
        margin-top: 8px;
    }

    /* 토스트 */
    .toast-container { position: fixed; top: 60px; right: 16px; z-index: 9999; }

    /* 풀스크린 이미지 오버레이 */
    .fullscreen-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.9);
        z-index: 9998;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: zoom-out;
    }
    .fullscreen-overlay img {
        max-width: 95vw;
        max-height: 95vh;
        object-fit: contain;
    }

    /* 툴바 */
    .filter-toolbar { gap: 8px; }
    .filter-toolbar .form-control,
    .filter-toolbar .form-select {
        font-size: 13px;
    }

    /* 테이블 액션 버튼 */
    .action-btn { padding: 2px 6px; font-size: 12px; }
';

include __DIR__ . '/includes/admin_header.php';
?>

<!-- 토스트 컨테이너 -->
<div class="toast-container" id="toastContainer"></div>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-journal-text"></i> 수정 이력</h5>
    <button class="btn btn-primary btn-sm" onclick="openAddModal()">
        <i class="bi bi-plus-lg me-1"></i>새 수정 이력
    </button>
</div>

<!-- 통계 카드 -->
<div class="row g-3 mb-4" id="statsRow">
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="d-flex h-100">
                <div class="stat-border" style="background:#667eea;"></div>
                <div class="card-body py-3">
                    <div class="text-body-secondary small fw-semibold">전체 수정</div>
                    <div class="fs-2 fw-bold mt-1" id="statTotal">-</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="d-flex h-100">
                <div class="stat-border" style="background:#dc3545;"></div>
                <div class="card-body py-3">
                    <div class="text-body-secondary small fw-semibold">버그 수정</div>
                    <div class="fs-2 fw-bold mt-1" id="statBugFix">-</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="d-flex h-100">
                <div class="stat-border" style="background:#0d6efd;"></div>
                <div class="card-body py-3">
                    <div class="text-body-secondary small fw-semibold">기능 추가</div>
                    <div class="fs-2 fw-bold mt-1" id="statFeature">-</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card h-100">
            <div class="d-flex h-100">
                <div class="stat-border" style="background:#43e97b;"></div>
                <div class="card-body py-3">
                    <div class="text-body-secondary small fw-semibold">이번 달</div>
                    <div class="fs-2 fw-bold mt-1" id="statMonth">-</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 필터 툴바 -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center filter-toolbar">
            <div class="d-flex align-items-center gap-1">
                <label class="form-label mb-0 small text-nowrap">기간:</label>
                <input type="date" class="form-control form-control-sm" id="filterDateFrom" style="width:140px;">
                <span class="text-muted">~</span>
                <input type="date" class="form-control form-control-sm" id="filterDateTo" style="width:140px;">
            </div>
            <select class="form-select form-select-sm" id="filterCategory" style="width:130px;">
                <option value="">전체 카테고리</option>
                <option value="bug_fix">버그 수정</option>
                <option value="feature">기능 추가</option>
                <option value="manual">매뉴얼</option>
                <option value="improvement">개선</option>
            </select>
            <select class="form-select form-select-sm" id="filterStatus" style="width:120px;">
                <option value="">전체 상태</option>
                <option value="completed">완료</option>
                <option value="in_progress">진행 중</option>
                <option value="planned">예정</option>
            </select>
            <div class="input-group input-group-sm" style="width:220px;">
                <input type="text" class="form-control" id="filterSearch" placeholder="제목, 페이지, 이슈번호 검색...">
                <button class="btn btn-outline-secondary" onclick="loadList()"><i class="bi bi-search"></i></button>
            </div>
            <button class="btn btn-sm btn-outline-secondary" onclick="resetFilters()" title="필터 초기화">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
</div>

<!-- 수정 이력 테이블 -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="fixLogTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>제목</th>
                        <th style="width:130px;">대상 페이지</th>
                        <th style="width:90px;">카테고리</th>
                        <th style="width:80px;">상태</th>
                        <th style="width:120px;">스크린샷</th>
                        <th style="width:100px;">수정일</th>
                        <th style="width:80px;">액션</th>
                    </tr>
                </thead>
                <tbody id="fixLogBody">
                    <tr>
                        <td colspan="8" class="text-center py-5 text-body-secondary">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            데이터를 불러오는 중...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========== 추가/수정 모달 ========== -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalTitle"><i class="bi bi-plus-circle me-1"></i>새 수정 이력</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId" value="0">
                <div class="row g-3">
                    <!-- 수정일 -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">수정일 <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="editFixDate">
                    </div>
                    <!-- 이슈 번호 -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">이슈 번호</label>
                        <input type="text" class="form-control" id="editIssueNumber" placeholder="예: FIX-001">
                    </div>
                    <!-- 카테고리 -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">카테고리 <span class="text-danger">*</span></label>
                        <select class="form-select" id="editCategory">
                            <option value="bug_fix">버그 수정</option>
                            <option value="feature">기능 추가</option>
                            <option value="manual">매뉴얼</option>
                            <option value="improvement">개선</option>
                        </select>
                    </div>
                    <!-- 제목 -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">제목 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editTitle" placeholder="수정 내용을 간략히 입력">
                    </div>
                    <!-- 설명 -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">상세 설명</label>
                        <textarea class="form-control" id="editDescription" rows="3" placeholder="수정 내용의 상세 설명..."></textarea>
                    </div>
                    <!-- 대상 페이지 -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">대상 페이지</label>
                        <select class="form-select" id="editTargetPage" onchange="onTargetPageChange()">
                            <option value="">-- 선택 --</option>
                            <option value="dashboard.php">dashboard.php - 대시보드</option>
                            <option value="admin_reviews.php">admin_reviews.php - 후기 관리</option>
                            <option value="admin_review_wizard.php">admin_review_wizard.php - 후기 검수</option>
                            <option value="admin_hospital_images.php">admin_hospital_images.php - 병원 이미지</option>
                            <option value="admin_ads.php">admin_ads.php - 광고 관리</option>
                            <option value="admin_categories.php">admin_categories.php - 카테고리</option>
                            <option value="admin_hospital_wizard.php">admin_hospital_wizard.php - 병원 등록</option>
                            <option value="admin_ad_wizard.php">admin_ad_wizard.php - 광고 신청</option>
                            <option value="admin_doctor_wizard.php">admin_doctor_wizard.php - 의사 등록</option>
                            <option value="admin_members.php">admin_members.php - 회원 관리</option>
                            <option value="admin_navigation.php">admin_navigation.php - 네비게이션</option>
                            <option value="admin_theme.php">admin_theme.php - 테마 설정</option>
                            <option value="admin_ai_settings.php">admin_ai_settings.php - AI 설정</option>
                            <option value="admin_auto_test.php">admin_auto_test.php - 자동 테스트</option>
                            <option value="admin_screenshot_book.php">admin_screenshot_book.php - 스크린샷 매뉴얼</option>
                            <option value="includes/admin_header.php">includes/admin_header.php - 공통 헤더</option>
                            <option value="index.php">index.php - 프론트 메인</option>
                            <option value="events.php">events.php - 이벤트</option>
                            <option value="consultation.php">consultation.php - 상담</option>
                            <option value="tips.php">tips.php - 뷰티팁</option>
                        </select>
                    </div>
                    <!-- 대상 URL -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">대상 URL</label>
                        <input type="text" class="form-control" id="editTargetUrl" placeholder="https://hms.seoulbeauty.app/hairwang/gangnamunni/...">
                    </div>
                    <!-- 상태 -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">상태</label>
                        <select class="form-select" id="editStatus">
                            <option value="completed">완료</option>
                            <option value="in_progress">진행 중</option>
                            <option value="planned">예정</option>
                        </select>
                    </div>
                </div>

                <!-- 스크린샷 캡처 영역 (수정 모드에서만 표시) -->
                <div class="mt-4 d-none" id="captureSection">
                    <hr>
                    <h6 class="fw-bold mb-3"><i class="bi bi-camera me-1"></i> 스크린샷 캡처</h6>
                    <div class="row g-3">
                        <!-- 수정 전 캡처 -->
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-semibold text-danger"><i class="bi bi-camera me-1"></i>수정 전</span>
                                    <button type="button" class="btn btn-sm btn-outline-danger capture-btn" id="btnCaptureBefore" onclick="captureScreenshot('before')">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                        <span class="btn-text"><i class="bi bi-camera-fill me-1"></i>캡처</span>
                                    </button>
                                </div>
                                <div id="previewBefore" class="text-center">
                                    <span class="text-body-secondary small">캡처된 이미지 없음</span>
                                </div>
                            </div>
                        </div>
                        <!-- 수정 후 캡처 -->
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-semibold text-success"><i class="bi bi-camera me-1"></i>수정 후</span>
                                    <button type="button" class="btn btn-sm btn-outline-success capture-btn" id="btnCaptureAfter" onclick="captureScreenshot('after')">
                                        <span class="spinner-border spinner-border-sm" role="status"></span>
                                        <span class="btn-text"><i class="bi bi-camera-fill me-1"></i>캡처</span>
                                    </button>
                                </div>
                                <div id="previewAfter" class="text-center">
                                    <span class="text-body-secondary small">캡처된 이미지 없음</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" id="btnSave" onclick="saveFixLog()">
                    <i class="bi bi-check-lg me-1"></i>저장
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========== 스크린샷 비교 모달 ========== -->
<div class="modal fade" id="compareModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-layout-split me-1"></i>스크린샷 비교</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- 비교 모드 탭 -->
                <ul class="nav nav-tabs mb-3" id="compareTabs">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSideBySide">
                            <i class="bi bi-layout-split me-1"></i>나란히 보기
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSlider">
                            <i class="bi bi-sliders me-1"></i>슬라이더 비교
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <!-- 나란히 보기 -->
                    <div class="tab-pane fade show active" id="tabSideBySide">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-center mb-2">
                                    <span class="badge bg-danger px-3 py-1">수정 전 (Before)</span>
                                </div>
                                <div class="border rounded p-1 text-center" style="min-height:200px;">
                                    <img id="compareBefore" src="" alt="수정 전" class="img-fluid rounded" style="cursor:zoom-in;" onclick="openFullscreen(this.src)">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-center mb-2">
                                    <span class="badge bg-success px-3 py-1">수정 후 (After)</span>
                                </div>
                                <div class="border rounded p-1 text-center" style="min-height:200px;">
                                    <img id="compareAfter" src="" alt="수정 후" class="img-fluid rounded" style="cursor:zoom-in;" onclick="openFullscreen(this.src)">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 슬라이더 비교 -->
                    <div class="tab-pane fade" id="tabSlider">
                        <div class="compare-container" id="sliderContainer">
                            <img id="sliderBefore" src="" alt="수정 전" style="width:100%;">
                            <div class="compare-after" id="sliderAfterWrap">
                                <img id="sliderAfter" src="" alt="수정 후" style="width:100%;">
                            </div>
                            <div class="compare-slider" id="sliderHandle" style="left:50%;"></div>
                            <span class="compare-label compare-label-before">수정 전</span>
                            <span class="compare-label compare-label-after">수정 후</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// ========== JavaScript ==========
$admin_extra_js = <<<'JSEOF'

// ===== 상수/설정 =====
var BASE_URL = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
var CATEGORY_LABELS = { bug_fix: '버그 수정', feature: '기능 추가', manual: '매뉴얼', improvement: '개선' };
var STATUS_LABELS = { completed: '완료', in_progress: '진행 중', planned: '예정' };

// 페이지 → URL 매핑
var PAGE_URL_MAP = {
    'dashboard.php': BASE_URL + 'dashboard.php',
    'admin_reviews.php': BASE_URL + 'admin_reviews.php',
    'admin_review_wizard.php': BASE_URL + 'admin_review_wizard.php',
    'admin_hospital_images.php': BASE_URL + 'admin_hospital_images.php',
    'admin_ads.php': BASE_URL + 'admin_ads.php',
    'admin_categories.php': BASE_URL + 'admin_categories.php',
    'admin_hospital_wizard.php': BASE_URL + 'admin_hospital_wizard.php',
    'admin_ad_wizard.php': BASE_URL + 'admin_ad_wizard.php',
    'admin_doctor_wizard.php': BASE_URL + 'admin_doctor_wizard.php',
    'admin_members.php': BASE_URL + 'admin_members.php',
    'admin_navigation.php': BASE_URL + 'admin_navigation.php',
    'admin_theme.php': BASE_URL + 'admin_theme.php',
    'admin_ai_settings.php': BASE_URL + 'admin_ai_settings.php',
    'admin_auto_test.php': BASE_URL + 'admin_auto_test.php',
    'admin_screenshot_book.php': BASE_URL + 'admin_screenshot_book.php',
    'includes/admin_header.php': BASE_URL + 'dashboard.php',
    'index.php': BASE_URL + 'index.php',
    'events.php': BASE_URL + 'events.php',
    'consultation.php': BASE_URL + 'consultation.php',
    'tips.php': BASE_URL + 'tips.php'
};

// ===== 초기화 =====
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    loadList();

    // 엔터 키로 검색
    document.getElementById('filterSearch').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') loadList();
    });

    // 필터 변경 시 자동 검색
    ['filterDateFrom', 'filterDateTo', 'filterCategory', 'filterStatus'].forEach(function(id) {
        document.getElementById(id).addEventListener('change', function() { loadList(); });
    });

    // 비교 슬라이더 초기화
    initCompareSlider();
});

// ===== 토스트 알림 =====
function showToast(message, type) {
    type = type || 'success';
    var iconMap = { success: 'bi-check-circle-fill', danger: 'bi-exclamation-triangle-fill', warning: 'bi-exclamation-circle-fill', info: 'bi-info-circle-fill' };
    var container = document.getElementById('toastContainer');
    var id = 'toast_' + Date.now();
    var html = '<div id="' + id + '" class="toast align-items-center text-bg-' + type + ' border-0 show" role="alert">' +
        '<div class="d-flex">' +
        '<div class="toast-body"><i class="bi ' + (iconMap[type] || iconMap.info) + ' me-1"></i>' + message + '</div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
        '</div></div>';
    container.insertAdjacentHTML('beforeend', html);
    setTimeout(function() {
        var el = document.getElementById(id);
        if (el) el.remove();
    }, 4000);
}

// ===== 통계 로드 =====
function loadStats() {
    var fd = new FormData();
    fd.append('action', 'stats');
    fetch('admin_fix_log.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                document.getElementById('statTotal').textContent = d.stats.total;
                document.getElementById('statBugFix').textContent = d.stats.bug_fix;
                document.getElementById('statFeature').textContent = d.stats.feature;
                document.getElementById('statMonth').textContent = d.stats.this_month;
            }
        })
        .catch(function(e) { console.error('통계 로드 오류:', e); });
}

// ===== 목록 로드 =====
function loadList() {
    var fd = new FormData();
    fd.append('action', 'list');
    fd.append('date_from', document.getElementById('filterDateFrom').value);
    fd.append('date_to', document.getElementById('filterDateTo').value);
    fd.append('category', document.getElementById('filterCategory').value);
    fd.append('status', document.getElementById('filterStatus').value);
    fd.append('search', document.getElementById('filterSearch').value);

    var tbody = document.getElementById('fixLogBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm me-2"></div>불러오는 중...</td></tr>';

    fetch('admin_fix_log.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success || !d.data || d.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="empty-state"><i class="bi bi-journal-text"></i><div>등록된 수정 이력이 없습니다.</div></td></tr>';
                return;
            }
            renderTable(d.data);
        })
        .catch(function(e) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>데이터 로드 실패</td></tr>';
            console.error('목록 로드 오류:', e);
        });
}

// ===== 테이블 렌더링 (날짜 그룹) =====
function renderTable(data) {
    var tbody = document.getElementById('fixLogBody');
    var html = '';
    var lastDate = '';

    data.forEach(function(row) {
        // 날짜 그룹 헤더
        if (row.fix_date !== lastDate) {
            lastDate = row.fix_date;
            var dateObj = new Date(row.fix_date + 'T00:00:00');
            var dayNames = ['일','월','화','수','목','금','토'];
            var dateLabel = row.fix_date + ' (' + dayNames[dateObj.getDay()] + ')';
            html += '<tr class="date-group-header"><td colspan="8"><i class="bi bi-calendar3 me-1"></i>' + dateLabel + '</td></tr>';
        }

        // 카테고리 배지
        var catBadge = '<span class="badge badge-' + row.category + '">' + (CATEGORY_LABELS[row.category] || row.category) + '</span>';

        // 상태 배지
        var statusBadge = '<span class="badge badge-' + row.status + '">' + (STATUS_LABELS[row.status] || row.status) + '</span>';

        // 스크린샷 버튼
        var ssHtml = '';
        var hasBefore = !!row.screenshot_before;
        var hasAfter = !!row.screenshot_after;
        if (hasBefore) {
            ssHtml += '<button class="btn btn-outline-danger action-btn me-1" onclick="openSingleScreenshot(\'/auto-manual-api/' + escapeHtml(row.screenshot_before) + '\', \'수정 전\')" title="수정 전"><i class="bi bi-image"></i> 전</button>';
        }
        if (hasAfter) {
            ssHtml += '<button class="btn btn-outline-success action-btn me-1" onclick="openSingleScreenshot(\'/auto-manual-api/' + escapeHtml(row.screenshot_after) + '\', \'수정 후\')" title="수정 후"><i class="bi bi-image"></i> 후</button>';
        }
        if (hasBefore && hasAfter) {
            ssHtml += '<button class="btn btn-outline-primary action-btn" onclick="openCompare(\'/auto-manual-api/' + escapeHtml(row.screenshot_before) + '\',\'/auto-manual-api/' + escapeHtml(row.screenshot_after) + '\')" title="비교"><i class="bi bi-layout-split"></i></button>';
        }
        if (!hasBefore && !hasAfter) {
            ssHtml = '<span class="text-body-secondary small">-</span>';
        }

        // 대상 페이지 (생략 표시)
        var pageLabel = row.target_page ? '<span class="text-truncate d-inline-block" style="max-width:120px;" title="' + escapeHtml(row.target_page) + '">' + escapeHtml(row.target_page) + '</span>' : '<span class="text-body-secondary">-</span>';

        // 액션
        var actions = '<button class="btn btn-outline-secondary action-btn me-1" onclick="openEditModal(' + row.id + ')" title="수정"><i class="bi bi-pencil"></i></button>' +
            '<button class="btn btn-outline-danger action-btn" onclick="deleteFixLog(' + row.id + ', \'' + escapeHtml(row.title) + '\')" title="삭제"><i class="bi bi-trash"></i></button>';

        html += '<tr>' +
            '<td><span class="fw-semibold">' + escapeHtml(row.issue_number || '-') + '</span></td>' +
            '<td>' + escapeHtml(row.title) + '</td>' +
            '<td>' + pageLabel + '</td>' +
            '<td>' + catBadge + '</td>' +
            '<td>' + statusBadge + '</td>' +
            '<td class="text-nowrap">' + ssHtml + '</td>' +
            '<td class="text-body-secondary small">' + row.fix_date + '</td>' +
            '<td class="text-nowrap">' + actions + '</td>' +
            '</tr>';
    });

    tbody.innerHTML = html;
}

// ===== HTML 이스케이프 =====
function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

// ===== 필터 초기화 =====
function resetFilters() {
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    document.getElementById('filterCategory').value = '';
    document.getElementById('filterStatus').value = '';
    document.getElementById('filterSearch').value = '';
    loadList();
}

// ===== 추가 모달 열기 =====
function openAddModal() {
    document.getElementById('editId').value = '0';
    document.getElementById('editModalTitle').innerHTML = '<i class="bi bi-plus-circle me-1"></i>새 수정 이력';
    document.getElementById('editFixDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('editIssueNumber').value = '';
    document.getElementById('editTitle').value = '';
    document.getElementById('editDescription').value = '';
    document.getElementById('editTargetPage').value = '';
    document.getElementById('editTargetUrl').value = '';
    document.getElementById('editCategory').value = 'bug_fix';
    document.getElementById('editStatus').value = 'completed';
    document.getElementById('captureSection').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// ===== 수정 모달 열기 =====
function openEditModal(id) {
    var fd = new FormData();
    fd.append('action', 'get');
    fd.append('id', id);

    fetch('admin_fix_log.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.success) {
                showToast(d.message || '데이터를 불러올 수 없습니다.', 'danger');
                return;
            }
            var row = d.data;
            document.getElementById('editId').value = row.id;
            document.getElementById('editModalTitle').innerHTML = '<i class="bi bi-pencil me-1"></i>수정 이력 편집 #' + (row.issue_number || row.id);
            document.getElementById('editFixDate').value = row.fix_date;
            document.getElementById('editIssueNumber').value = row.issue_number || '';
            document.getElementById('editTitle').value = row.title;
            document.getElementById('editDescription').value = row.description || '';
            document.getElementById('editTargetPage').value = row.target_page || '';
            document.getElementById('editTargetUrl').value = row.target_url || '';
            document.getElementById('editCategory').value = row.category;
            document.getElementById('editStatus').value = row.status;

            // 캡처 섹션 표시
            document.getElementById('captureSection').classList.remove('d-none');

            // 기존 스크린샷 미리보기
            updateCapturePreview('before', row.screenshot_before);
            updateCapturePreview('after', row.screenshot_after);

            new bootstrap.Modal(document.getElementById('editModal')).show();
        })
        .catch(function(e) {
            showToast('데이터 로드 실패', 'danger');
            console.error(e);
        });
}

// ===== 캡처 미리보기 업데이트 =====
function updateCapturePreview(type, path) {
    var container = document.getElementById('preview' + (type === 'before' ? 'Before' : 'After'));
    if (path) {
        var url = '/auto-manual-api/' + path;
        container.innerHTML = '<img src="' + url + '" class="capture-preview" onclick="openFullscreen(\'' + url + '\')" alt="' + (type === 'before' ? '수정 전' : '수정 후') + '">';
    } else {
        container.innerHTML = '<span class="text-body-secondary small">캡처된 이미지 없음</span>';
    }
}

// ===== 대상 페이지 선택 시 URL 자동 입력 =====
function onTargetPageChange() {
    var page = document.getElementById('editTargetPage').value;
    if (page && PAGE_URL_MAP[page]) {
        document.getElementById('editTargetUrl').value = PAGE_URL_MAP[page];
    }
}

// ===== 저장 (추가/수정) =====
function saveFixLog() {
    var id = document.getElementById('editId').value;
    var title = document.getElementById('editTitle').value.trim();
    if (!title) {
        showToast('제목을 입력해주세요.', 'warning');
        document.getElementById('editTitle').focus();
        return;
    }

    var fd = new FormData();
    fd.append('action', id === '0' ? 'add' : 'edit');
    if (id !== '0') fd.append('id', id);
    fd.append('fix_date', document.getElementById('editFixDate').value);
    fd.append('issue_number', document.getElementById('editIssueNumber').value);
    fd.append('title', title);
    fd.append('description', document.getElementById('editDescription').value);
    fd.append('target_page', document.getElementById('editTargetPage').value);
    fd.append('target_url', document.getElementById('editTargetUrl').value);
    fd.append('category', document.getElementById('editCategory').value);
    fd.append('status', document.getElementById('editStatus').value);

    var btn = document.getElementById('btnSave');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>저장 중...';

    fetch('admin_fix_log.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>저장';
            if (d.success) {
                showToast(d.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
                loadStats();
                loadList();
            } else {
                showToast(d.message || '저장 실패', 'danger');
            }
        })
        .catch(function(e) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>저장';
            showToast('네트워크 오류', 'danger');
            console.error(e);
        });
}

// ===== 삭제 =====
function deleteFixLog(id, title) {
    if (!confirm('정말 삭제하시겠습니까?\n\n' + title)) return;

    var fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);

    fetch('admin_fix_log.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) {
                showToast(d.message, 'success');
                loadStats();
                loadList();
            } else {
                showToast(d.message || '삭제 실패', 'danger');
            }
        })
        .catch(function(e) {
            showToast('네트워크 오류', 'danger');
            console.error(e);
        });
}

// ===== 스크린샷 캡처 =====
function captureScreenshot(type) {
    var id = document.getElementById('editId').value;
    var url = document.getElementById('editTargetUrl').value.trim();

    if (!url) {
        showToast('대상 URL을 먼저 입력해주세요.', 'warning');
        document.getElementById('editTargetUrl').focus();
        return;
    }

    var btnId = (type === 'before') ? 'btnCaptureBefore' : 'btnCaptureAfter';
    var btn = document.getElementById(btnId);
    btn.classList.add('loading');
    btn.disabled = true;

    var fd = new FormData();
    fd.append('action', (type === 'before') ? 'capture_before' : 'capture_after');
    fd.append('id', id);
    fd.append('capture_url', url);

    fetch('admin_fix_log.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            btn.classList.remove('loading');
            btn.disabled = false;
            if (d.success) {
                showToast(d.message, 'success');
                updateCapturePreview(type, d.path);
            } else {
                showToast(d.message || '캡처 실패', 'danger');
            }
        })
        .catch(function(e) {
            btn.classList.remove('loading');
            btn.disabled = false;
            showToast('캡처 API 연결 실패', 'danger');
            console.error(e);
        });
}

// ===== 단일 스크린샷 보기 =====
function openSingleScreenshot(url, label) {
    openFullscreen(url);
}

// ===== 풀스크린 이미지 =====
function openFullscreen(src) {
    var overlay = document.createElement('div');
    overlay.className = 'fullscreen-overlay';
    overlay.innerHTML = '<img src="' + src + '" alt="Screenshot">';
    overlay.addEventListener('click', function() { overlay.remove(); });
    document.body.appendChild(overlay);

    // ESC 키 닫기
    var escHandler = function(e) {
        if (e.key === 'Escape') {
            overlay.remove();
            document.removeEventListener('keydown', escHandler);
        }
    };
    document.addEventListener('keydown', escHandler);
}

// ===== 비교 모달 =====
function openCompare(beforeUrl, afterUrl) {
    // 나란히 보기
    document.getElementById('compareBefore').src = beforeUrl;
    document.getElementById('compareAfter').src = afterUrl;

    // 슬라이더
    document.getElementById('sliderBefore').src = beforeUrl;
    document.getElementById('sliderAfter').src = afterUrl;

    // 슬라이더 위치 초기화
    document.getElementById('sliderHandle').style.left = '50%';
    document.getElementById('sliderAfterWrap').style.width = '50%';

    new bootstrap.Modal(document.getElementById('compareModal')).show();
}

// ===== 비교 슬라이더 초기화 =====
function initCompareSlider() {
    var container = document.getElementById('sliderContainer');
    var handle = document.getElementById('sliderHandle');
    var afterWrap = document.getElementById('sliderAfterWrap');
    if (!container || !handle) return;

    var isDragging = false;

    function updateSlider(x) {
        var rect = container.getBoundingClientRect();
        var pos = Math.max(0, Math.min(x - rect.left, rect.width));
        var pct = (pos / rect.width) * 100;
        handle.style.left = pct + '%';
        afterWrap.style.width = pct + '%';
    }

    handle.addEventListener('mousedown', function(e) {
        isDragging = true;
        e.preventDefault();
    });

    document.addEventListener('mousemove', function(e) {
        if (isDragging) updateSlider(e.clientX);
    });

    document.addEventListener('mouseup', function() {
        isDragging = false;
    });

    // 터치 지원
    handle.addEventListener('touchstart', function(e) {
        isDragging = true;
        e.preventDefault();
    });

    document.addEventListener('touchmove', function(e) {
        if (isDragging && e.touches.length === 1) {
            updateSlider(e.touches[0].clientX);
        }
    });

    document.addEventListener('touchend', function() {
        isDragging = false;
    });

    // 컨테이너 클릭으로도 이동
    container.addEventListener('click', function(e) {
        if (e.target === handle) return;
        updateSlider(e.clientX);
    });
}

JSEOF;

include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
