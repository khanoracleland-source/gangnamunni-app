<?php
/**
 * admin_excel_import_wizard.php - 엑셀 대량 등록 마법사
 * 6단계 원형 스텝 프로세서로 엑셀 대량 Import 처리
 *
 * Step 1: 엔티티 선택 + 양식 다운로드
 * Step 2: 파일 업로드 + 미리보기 + 컬럼 매핑
 * Step 3: 충돌검사 프로그레스바 + 결과
 * Step 4: 사용자 확인 (옵션 A/B/C)
 * Step 5: 등록 프로그레스바
 * Step 6: 결과 요약 + 다운로드
 *
 * @since 2026-02-23
 */
session_start();
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_permission('excel_import');
require_once __DIR__ . '/includes/excel_engine.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
$esc = function($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };

// ── 업로드 디렉토리 ──
$uploadDir = __DIR__ . '/uploads/excel_imports/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

// ── AJAX 핸들러 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    // 양식 다운로드
    if ($action === 'download_template') {
        $entity = $_POST['entity'] ?? '';
        global $ENTITY_DEFS;
        if (!isset($ENTITY_DEFS[$entity])) {
            echo json_encode(['success' => false, 'error' => '알 수 없는 엔티티']);
            exit;
        }
        $filepath = generateTemplate($entity);
        if (!$filepath || !file_exists($filepath)) {
            echo json_encode(['success' => false, 'error' => '양식 생성 실패']);
            exit;
        }
        // 파일을 접근 가능한 위치로 복사
        $webDir = __DIR__ . '/uploads/excel_exports/';
        if (!is_dir($webDir)) mkdir($webDir, 0775, true);
        $webFile = basename($filepath);
        copy($filepath, $webDir . $webFile);
        echo json_encode(['success' => true, 'url' => 'uploads/excel_exports/' . $webFile, 'filename' => $webFile]);
        exit;
    }

    // 파일 업로드 + 파싱
    if ($action === 'upload_file') {
        $entity = $_POST['entity'] ?? '';
        global $ENTITY_DEFS;
        if (!isset($ENTITY_DEFS[$entity])) {
            echo json_encode(['success' => false, 'error' => '알 수 없는 엔티티']);
            exit;
        }

        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errMsg = '파일 업로드 실패';
            if ($file && $file['error'] === UPLOAD_ERR_INI_SIZE) $errMsg = '파일 크기 초과 (최대 ' . ini_get('upload_max_filesize') . ')';
            echo json_encode(['success' => false, 'error' => $errMsg]);
            exit;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
            echo json_encode(['success' => false, 'error' => '.xlsx, .xls, .csv 파일만 가능합니다.']);
            exit;
        }

        // 파일 크기 체크 (10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            echo json_encode(['success' => false, 'error' => '파일 크기가 10MB를 초과합니다.']);
            exit;
        }

        $fname = 'import_' . $entity . '_' . date('Ymd_His') . '_' . mt_rand(1000,9999) . '.' . $ext;
        $savePath = $uploadDir . $fname;
        if (!move_uploaded_file($file['tmp_name'], $savePath)) {
            echo json_encode(['success' => false, 'error' => '파일 저장 실패']);
            exit;
        }

        // 파싱
        $parsed = parseExcelFile($savePath);
        if (isset($parsed['error'])) {
            echo json_encode(['success' => false, 'error' => $parsed['error']]);
            exit;
        }

        // 헤더 매핑 분석
        $headerMapping = mapHeaders($parsed['headers'], $entity);

        // 미리보기 (10행)
        $previewRows = array_slice($parsed['rows'], 0, 10);

        // 세션에 파일 경로 저장
        $_SESSION['excel_import_file'] = $savePath;
        $_SESSION['excel_import_entity'] = $entity;
        $_SESSION['excel_import_total'] = $parsed['total'];

        echo json_encode([
            'success' => true,
            'total' => $parsed['total'],
            'headers' => $parsed['headers'],
            'headerMapping' => $headerMapping,
            'preview' => $previewRows,
            'filename' => $file['name'],
            'filesize' => round($file['size'] / 1024, 1) . ' KB',
        ]);
        exit;
    }

    // 충돌검사 (batch)
    if ($action === 'validate_batch') {
        $entity = $_POST['entity'] ?? ($_SESSION['excel_import_entity'] ?? '');
        $offset = intval($_POST['offset'] ?? 0);
        $batchSize = intval($_POST['batch_size'] ?? 50);

        // 파일에서 데이터 읽기
        $filePath = $_SESSION['excel_import_file'] ?? '';
        if (!$filePath || !file_exists($filePath)) {
            echo json_encode(['success' => false, 'error' => '파일을 찾을 수 없습니다. 다시 업로드해주세요.']);
            exit;
        }

        $parsed = parseExcelFile($filePath);
        if (isset($parsed['error'])) {
            echo json_encode(['success' => false, 'error' => $parsed['error']]);
            exit;
        }

        $headerMapping = mapHeaders($parsed['headers'], $entity);
        $mappedRows = applyMapping($parsed['rows'], $headerMapping);

        // batch 슬라이스
        $batch = array_slice($mappedRows, $offset, $batchSize);

        // 유효성 검사
        $validationErrors = validateRows($batch, $entity);

        // 충돌 검사
        $conflicts = checkConflicts($batch, $entity, $connect, $offset);

        // 유효성 에러를 충돌 결과에 병합
        foreach ($validationErrors as $bi => $errors) {
            $globalIdx = $offset + $bi;
            if (!empty($errors) && isset($conflicts[$globalIdx])) {
                if ($conflicts[$globalIdx]['status'] === 'ok') {
                    $conflicts[$globalIdx]['status'] = 'error';
                }
                foreach ($errors as $err) {
                    $conflicts[$globalIdx]['details'][] = ['type' => 'validation', 'field' => $err['field'], 'label' => $err['label'], 'msg' => $err['msg']];
                }
            }
        }

        echo json_encode([
            'success' => true,
            'results' => $conflicts,
            'offset' => $offset,
            'batchSize' => count($batch),
            'total' => count($mappedRows),
        ]);
        exit;
    }

    // 실제 등록 (batch)
    if ($action === 'import_batch') {
        $entity = $_POST['entity'] ?? ($_SESSION['excel_import_entity'] ?? '');
        $rowsJson = $_POST['rows'] ?? '[]';
        $decisionsJson = $_POST['decisions'] ?? '{}';

        $rows = json_decode($rowsJson, true);
        $decisions = json_decode($decisionsJson, true);

        if (!is_array($rows) || empty($rows)) {
            echo json_encode(['success' => false, 'error' => '등록할 데이터가 없습니다.']);
            exit;
        }

        $results = importRows($rows, $entity, $decisions, $connect);
        echo json_encode(['success' => true, 'results' => $results]);
        exit;
    }

    // 에러 엑셀 다운로드
    if ($action === 'download_errors') {
        $entity = $_POST['entity'] ?? '';
        $errorRowsJson = $_POST['error_rows'] ?? '[]';
        $errorRows = json_decode($errorRowsJson, true);

        if (empty($errorRows)) {
            echo json_encode(['success' => false, 'error' => '에러 데이터가 없습니다.']);
            exit;
        }

        $filepath = generateErrorExcel($errorRows, $entity);
        if (!$filepath || !file_exists($filepath)) {
            echo json_encode(['success' => false, 'error' => '에러 엑셀 생성 실패']);
            exit;
        }

        $webDir = __DIR__ . '/uploads/excel_exports/';
        if (!is_dir($webDir)) mkdir($webDir, 0775, true);
        $webFile = basename($filepath);
        copy($filepath, $webDir . $webFile);
        echo json_encode(['success' => true, 'url' => 'uploads/excel_exports/' . $webFile, 'filename' => $webFile]);
        exit;
    }

    // 결과 엑셀 다운로드
    if ($action === 'download_results') {
        $entity = $_POST['entity'] ?? '';
        $resultsJson = $_POST['results'] ?? '[]';
        $allResults = json_decode($resultsJson, true);

        if (empty($allResults)) {
            echo json_encode(['success' => false, 'error' => '결과 데이터가 없습니다.']);
            exit;
        }

        $filepath = generateResultsExcel($allResults, $entity);
        if (!$filepath || !file_exists($filepath)) {
            echo json_encode(['success' => false, 'error' => '결과 엑셀 생성 실패']);
            exit;
        }

        $webDir = __DIR__ . '/uploads/excel_exports/';
        if (!is_dir($webDir)) mkdir($webDir, 0775, true);
        $webFile = basename($filepath);
        copy($filepath, $webDir . $webFile);
        echo json_encode(['success' => true, 'url' => 'uploads/excel_exports/' . $webFile, 'filename' => $webFile]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// ── GET: 양식 파일 다운로드 ──
if (isset($_GET['download'])) {
    $file = __DIR__ . '/uploads/excel_exports/' . basename($_GET['download']);
    if (file_exists($file)) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = $ext === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($file);
        exit;
    }
    http_response_code(404);
    echo '파일을 찾을 수 없습니다.';
    exit;
}

$admin_page_title = '엑셀 대량 등록';
$admin_current = 'excel_import';
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
/* ── 원형 스텝 프로세서 (진녹색 테마: #198754) ── */
.wizard-header {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 20px 0 30px;
    gap: 0;
    overflow-x: auto;
}
.wizard-step-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 60px;
}
.wizard-step-row {
    display: flex;
    align-items: center;
}
.wizard-circle {
    width: 48px; height: 48px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 18px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 3px solid transparent;
    position: relative;
    flex-shrink: 0;
}
.wizard-circle.pending { background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); border-color: var(--bs-border-color); }
.wizard-circle.active { background: #198754; color: #fff; border-color: #198754; box-shadow: 0 0 0 5px rgba(25,135,84,0.25); transform: scale(1.1); }
.wizard-circle.completed { background: #198754; color: #fff; border-color: #198754; }
.wizard-circle.completed::after { content: '\2714'; position: absolute; font-size: 14px; }
.wizard-circle.completed span { visibility: hidden; }
.wizard-line {
    width: 40px; height: 3px;
    background: var(--bs-border-color);
    transition: background 0.3s;
    flex-shrink: 0;
}
.wizard-line.completed { background: #198754; }
.wizard-step-label {
    font-size: 11px;
    text-align: center;
    margin-top: 8px;
    color: var(--bs-secondary-color);
    white-space: nowrap;
    transition: all 0.3s;
}
.wizard-step-label.active { color: #198754; font-weight: 700; }
.wizard-step-label.completed { color: #198754; }

/* ── 스텝 콘텐츠 ── */
.wizard-content { display: none; animation: fadeIn 0.3s ease; }
.wizard-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* ── 엔티티 선택 카드 ── */
.entity-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
    margin: 20px 0;
}
.entity-card {
    border: 2px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 20px 12px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    background: var(--bs-body-bg);
}
.entity-card:hover { border-color: #198754; background: rgba(25,135,84,0.04); transform: translateY(-2px); }
.entity-card.selected { border-color: #198754; background: rgba(25,135,84,0.08); box-shadow: 0 0 0 3px rgba(25,135,84,0.2); }
.entity-card .entity-icon { font-size: 32px; margin-bottom: 8px; color: #198754; }
.entity-card .entity-label { font-weight: 600; font-size: 14px; }
.entity-card .entity-count { font-size: 11px; color: var(--bs-secondary-color); margin-top: 4px; }

/* ── 파일 업로드 드래그존 ── */
.upload-zone {
    border: 2px dashed var(--bs-border-color);
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 180px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    margin: 20px 0;
}
.upload-zone:hover { border-color: #198754; background: rgba(25,135,84,0.03); }
.upload-zone.dragover { border-color: #198754; background: rgba(25,135,84,0.08); }
.upload-zone .upload-icon { font-size: 48px; color: #198754; margin-bottom: 12px; }
.upload-zone .upload-text { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
.upload-zone .upload-hint { font-size: 13px; color: var(--bs-secondary-color); }

/* ── 미리보기 테이블 ── */
.preview-table-wrap {
    max-height: 400px;
    overflow: auto;
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    margin: 16px 0;
}
.preview-table { font-size: 12px; margin: 0; }
.preview-table thead th {
    position: sticky; top: 0;
    background: #198754; color: #fff;
    font-size: 11px; white-space: nowrap;
    z-index: 2;
}
.preview-table td { white-space: nowrap; max-width: 200px; overflow: hidden; text-overflow: ellipsis; }

/* ── 프로그레스 영역 ── */
.progress-area {
    margin: 30px 0;
    text-align: center;
}
.progress-area .progress {
    height: 28px;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 12px;
}
.progress-area .progress-bar {
    background: linear-gradient(90deg, #198754, #20c997);
    transition: width 0.3s;
    font-size: 13px;
    font-weight: 600;
    line-height: 28px;
}
.progress-stats {
    display: flex;
    justify-content: center;
    gap: 24px;
    font-size: 13px;
}
.progress-stats .stat-item { display: flex; align-items: center; gap: 6px; }
.progress-stats .stat-dot { width: 10px; height: 10px; border-radius: 50%; }

/* ── 충돌 결과 테이블 ── */
.conflict-table-wrap {
    max-height: 500px;
    overflow: auto;
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    margin: 16px 0;
}
.conflict-table { font-size: 12px; margin: 0; }
.conflict-table thead th { position: sticky; top: 0; z-index: 2; background: var(--bs-body-bg); }
.conflict-table .status-ok { color: #198754; }
.conflict-table .status-exact { color: #dc3545; }
.conflict-table .status-similar { color: #fd7e14; }
.conflict-table .status-fk { color: #6f42c1; }
.conflict-table .status-error { color: #dc3545; }

/* ── 옵션 카드 (Step 4) ── */
.option-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
    margin: 24px 0;
}
.option-card {
    border: 2px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 24px;
    cursor: pointer;
    transition: all 0.2s;
}
.option-card:hover { border-color: #198754; }
.option-card.selected { border-color: #198754; background: rgba(25,135,84,0.06); box-shadow: 0 0 0 3px rgba(25,135,84,0.2); }
.option-card .option-icon { font-size: 28px; margin-bottom: 10px; }
.option-card .option-title { font-weight: 700; font-size: 16px; margin-bottom: 6px; }
.option-card .option-desc { font-size: 13px; color: var(--bs-secondary-color); }
.option-card .badge { font-size: 10px; }

/* ── 충돌 건별 처리 (Option B) ── */
.conflict-resolve-list { margin: 20px 0; }
.conflict-resolve-item {
    border: 1px solid var(--bs-border-color);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 10px;
    background: var(--bs-body-bg);
}
.conflict-resolve-item .item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.conflict-resolve-item .item-data { font-size: 12px; color: var(--bs-secondary-color); }

/* ── 결과 요약 카드 ── */
.result-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin: 24px 0;
}
.result-card {
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.result-card .result-number { font-size: 32px; font-weight: 700; }
.result-card .result-label { font-size: 13px; margin-top: 4px; }
.result-card.success { background: #d1e7dd; color: #0f5132; }
.result-card.update { background: #cfe2ff; color: #084298; }
.result-card.skip { background: var(--bs-body-bg)3cd; color: #664d03; }
.result-card.fail { background: #f8d7da; color: #842029; }

/* ── 하단 버튼 ── */
.wizard-footer {
    display: flex;
    justify-content: space-between;
    padding: 20px 0;
    border-top: 1px solid var(--bs-border-color);
    margin-top: 24px;
}

/* ── 매핑 테이블 ── */
.mapping-table { font-size: 13px; }
.mapping-table .mapped { color: #198754; font-weight: 600; }
.mapping-table .unmapped { color: #dc3545; }
.mapping-table select { font-size: 12px; padding: 2px 6px; }
</style>

<div class="card shadow-sm">
    <div class="card-body">
        <!-- 타이틀 -->
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="card-title mb-0">
                <i class="bi bi-file-earmark-spreadsheet text-success me-2"></i>엑셀 대량 등록 마법사
            </h5>
            <span class="badge bg-success" id="entityBadge" style="display:none;"></span>
        </div>

        <!-- 스텝 헤더 -->
        <div class="wizard-header" id="wizardHeader"></div>

        <!-- Step 1: 엔티티 선택 -->
        <div class="wizard-content" id="step1">
            <h6 class="mb-3"><i class="bi bi-1-circle text-success me-2"></i>등록 대상 선택</h6>
            <p class="text-muted small">대량 등록할 데이터 유형을 선택한 후, 엑셀 양식을 다운로드하세요.</p>

            <div class="entity-grid" id="entityGrid"></div>

            <div id="templateDownloadArea" style="display:none;" class="text-center mt-3">
                <button class="btn btn-outline-success btn-sm" id="btnDownloadTemplate" onclick="downloadTemplate()">
                    <i class="bi bi-download me-1"></i>엑셀 양식 다운로드
                </button>
                <span class="text-muted small ms-2" id="templateHint">양식에 데이터를 입력 후 업로드하세요 (3시트: 데이터/가이드/코드표)</span>
            </div>
        </div>

        <!-- Step 2: 파일 업로드 -->
        <div class="wizard-content" id="step2">
            <h6 class="mb-3"><i class="bi bi-2-circle text-success me-2"></i>파일 업로드 및 미리보기</h6>

            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                <div class="upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                <div class="upload-text">파일을 드래그하거나 클릭하여 선택</div>
                <div class="upload-hint">.xlsx, .csv 파일 지원 (최대 10MB)</div>
            </div>
            <input type="file" id="fileInput" accept=".xlsx,.xls,.csv" style="display:none;" onchange="handleFileSelect(this)">

            <!-- 업로드 결과 영역 -->
            <div id="uploadResult" style="display:none;">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <i class="bi bi-file-earmark-check text-success me-1"></i>
                        <strong id="uploadFileName"></strong>
                        <span class="text-muted small ms-2" id="uploadFileInfo"></span>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm" onclick="resetUpload()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>다시 선택
                    </button>
                </div>

                <!-- 컬럼 매핑 -->
                <h6 class="mt-3 mb-2">컬럼 매핑 확인</h6>
                <div class="table-responsive">
                    <table class="table table-sm mapping-table" id="mappingTable">
                        <thead><tr><th>엑셀 컬럼</th><th>매핑 대상</th><th>상태</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>

                <!-- 미리보기 -->
                <h6 class="mt-3 mb-2">미리보기 (최대 10행)</h6>
                <div class="preview-table-wrap">
                    <table class="table table-sm table-bordered preview-table" id="previewTable">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Step 3: 충돌 검사 -->
        <div class="wizard-content" id="step3">
            <h6 class="mb-3"><i class="bi bi-3-circle text-success me-2"></i>충돌 및 유효성 검사</h6>

            <div class="progress-area" id="validateProgress">
                <div class="progress">
                    <div class="progress-bar" id="validateBar" role="progressbar" style="width:0%">0%</div>
                </div>
                <div class="progress-stats">
                    <div class="stat-item"><div class="stat-dot" style="background:#198754"></div><span>검사 중... <span id="validateCount">0</span> / <span id="validateTotal">0</span></span></div>
                </div>
            </div>

            <!-- 검사 결과 요약 -->
            <div id="validateSummary" style="display:none;">
                <div class="result-summary">
                    <div class="result-card success"><div class="result-number" id="countOk">0</div><div class="result-label">정상</div></div>
                    <div class="result-card" style="background:#ffe0e0;color:#842029;"><div class="result-number" id="countExact">0</div><div class="result-label">완전 중복</div></div>
                    <div class="result-card" style="background:var(--bs-body-bg)0d4;color:#664d03;"><div class="result-number" id="countSimilar">0</div><div class="result-label">유사 일치</div></div>
                    <div class="result-card" style="background:#e8daef;color:#4a235a;"><div class="result-number" id="countFk">0</div><div class="result-label">FK 불일치</div></div>
                    <div class="result-card fail"><div class="result-number" id="countError">0</div><div class="result-label">유효성 에러</div></div>
                </div>

                <!-- 상세 결과 테이블 -->
                <h6 class="mt-3 mb-2">상세 결과 <small class="text-muted">(문제 있는 행만 표시)</small></h6>
                <div class="conflict-table-wrap">
                    <table class="table table-sm table-hover conflict-table" id="conflictTable">
                        <thead><tr><th width="50">행</th><th width="80">상태</th><th>데이터 요약</th><th>상세 내용</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Step 4: 사용자 확인 -->
        <div class="wizard-content" id="step4">
            <h6 class="mb-3"><i class="bi bi-4-circle text-success me-2"></i>처리 방법 선택</h6>

            <div class="option-cards">
                <div class="option-card selected" data-option="A" onclick="selectOption('A')">
                    <div class="option-icon">&#9989;</div>
                    <div class="option-title">정상 건만 등록 <span class="badge bg-success">권장</span></div>
                    <div class="option-desc">정상 데이터만 등록하고, 충돌/에러 건은 에러 엑셀로 다운로드합니다.</div>
                    <div class="mt-2"><strong class="text-success" id="optACount">0건 등록 예정</strong></div>
                </div>
                <div class="option-card" data-option="B" onclick="selectOption('B')">
                    <div class="option-icon">&#9881;&#65039;</div>
                    <div class="option-title">충돌 건별 처리</div>
                    <div class="option-desc">충돌 데이터를 건별로 (건너뛰기/덮어쓰기/신규등록) 선택합니다.</div>
                    <div class="mt-2"><strong class="text-warning" id="optBCount">0건 충돌</strong></div>
                </div>
                <div class="option-card" data-option="C" onclick="selectOption('C')">
                    <div class="option-icon">&#10060;</div>
                    <div class="option-title">전체 취소</div>
                    <div class="option-desc">등록을 취소하고 처음으로 돌아갑니다.</div>
                </div>
            </div>

            <!-- Option B: 건별 처리 영역 -->
            <div id="conflictResolveArea" style="display:none;">
                <h6 class="mt-3 mb-2">충돌 건별 처리</h6>
                <div class="mb-2">
                    <button class="btn btn-sm btn-outline-secondary me-1" onclick="bulkResolve('skip')">전체 건너뛰기</button>
                    <button class="btn btn-sm btn-outline-warning me-1" onclick="bulkResolve('update')">전체 덮어쓰기</button>
                    <button class="btn btn-sm btn-outline-success" onclick="bulkResolve('insert')">전체 신규등록</button>
                </div>
                <div class="conflict-resolve-list" id="conflictResolveList"></div>
            </div>
        </div>

        <!-- Step 5: 등록 진행 -->
        <div class="wizard-content" id="step5">
            <h6 class="mb-3"><i class="bi bi-5-circle text-success me-2"></i>데이터 등록 중</h6>

            <div class="progress-area" id="importProgress">
                <div class="progress">
                    <div class="progress-bar" id="importBar" role="progressbar" style="width:0%">0%</div>
                </div>
                <div class="progress-stats">
                    <div class="stat-item"><div class="stat-dot" style="background:#198754"></div>성공 <strong id="importSuccess">0</strong></div>
                    <div class="stat-item"><div class="stat-dot" style="background:#0d6efd"></div>업데이트 <strong id="importUpdate">0</strong></div>
                    <div class="stat-item"><div class="stat-dot" style="background:#ffc107"></div>건너뜀 <strong id="importSkip">0</strong></div>
                    <div class="stat-item"><div class="stat-dot" style="background:#dc3545"></div>에러 <strong id="importError">0</strong></div>
                </div>
            </div>

            <div class="text-center text-muted mt-2" id="importStatusMsg">처리 중입니다. 페이지를 닫지 마세요...</div>
        </div>

        <!-- Step 6: 결과 -->
        <div class="wizard-content" id="step6">
            <h6 class="mb-3"><i class="bi bi-6-circle text-success me-2"></i>등록 완료</h6>

            <div class="text-center mb-4">
                <div style="font-size:64px;" id="finalIcon">&#9989;</div>
                <h4 id="finalTitle">등록이 완료되었습니다</h4>
                <p class="text-muted" id="finalMsg"></p>
            </div>

            <div class="result-summary" id="finalSummary"></div>

            <div class="text-center mt-4">
                <button class="btn btn-outline-danger btn-sm me-2" id="btnDownloadErrors" style="display:none;" onclick="downloadErrorExcel()">
                    <i class="bi bi-exclamation-triangle me-1"></i>에러 목록 다운로드
                </button>
                <button class="btn btn-outline-primary btn-sm me-2" onclick="downloadResultExcel()">
                    <i class="bi bi-download me-1"></i>전체 결과 다운로드
                </button>
                <button class="btn btn-success btn-sm" onclick="resetWizard()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>새로 시작
                </button>
            </div>
        </div>

        <!-- 하단 네비게이션 -->
        <div class="wizard-footer">
            <button class="btn btn-outline-secondary" id="btnPrev" onclick="prevStep()" style="display:none;">
                <i class="bi bi-chevron-left me-1"></i>이전
            </button>
            <div class="flex-grow-1"></div>
            <button class="btn btn-success" id="btnNext" onclick="nextStep()">
                다음 <i class="bi bi-chevron-right ms-1"></i>
            </button>
        </div>
    </div>
</div>

<script>
// ── 엔티티 정의 (클라이언트) ──
const ENTITIES = <?php
    $clientEntities = [];
    foreach ($ENTITY_DEFS as $key => $def) {
        $clientEntities[$key] = [
            'key' => $key,
            'label' => $def['label'],
            'icon' => $def['icon'],
            'columns' => array_map(function($c) { return ['key'=>$c['key'], 'label'=>$c['label'], 'required'=>$c['required']]; }, $def['columns']),
        ];
    }
    echo json_encode($clientEntities, JSON_UNESCAPED_UNICODE);
?>;

const STEPS = [
    {num: 1, label: '대상 선택'},
    {num: 2, label: '파일 업로드'},
    {num: 3, label: '충돌 검사'},
    {num: 4, label: '처리 확인'},
    {num: 5, label: '등록 진행'},
    {num: 6, label: '결과'}
];

let currentStep = 1;
let selectedEntity = null;
let parsedData = null;      // {headers, headerMapping, preview, total}
let allValidationResults = {};  // rowIndex -> {status, details, row}
let selectedOption = 'A';
let conflictDecisions = {};    // rowIndex -> 'skip'|'update'|'insert'
let importResults = {};        // rowIndex -> {status, msg, row}
let allMappedRows = [];        // 전체 매핑된 행 (validate 후 보관)

// ── 스텝 헤더 렌더링 ──
function renderStepHeader() {
    let html = '';
    STEPS.forEach((s, idx) => {
        const stateClass = s.num < currentStep ? 'completed' : (s.num === currentStep ? 'active' : 'pending');
        const labelClass = s.num < currentStep ? 'completed' : (s.num === currentStep ? 'active' : '');
        html += `<div class="wizard-step-item">
            <div class="wizard-step-row">
                ${idx > 0 ? `<div class="wizard-line ${s.num <= currentStep ? 'completed' : ''}"></div>` : ''}
                <div class="wizard-circle ${stateClass}" onclick="goToStep(${s.num})"><span>${s.num}</span></div>
            </div>
            <div class="wizard-step-label ${labelClass}">${s.label}</div>
        </div>`;
    });
    document.getElementById('wizardHeader').innerHTML = html;
}

function showStep(n) {
    currentStep = n;
    renderStepHeader();
    for (let i = 1; i <= 6; i++) {
        const el = document.getElementById('step' + i);
        if (el) el.classList.toggle('active', i === n);
    }
    // 버튼 상태
    document.getElementById('btnPrev').style.display = n > 1 && n < 6 ? '' : 'none';
    const btnNext = document.getElementById('btnNext');
    if (n === 1) {
        btnNext.style.display = selectedEntity ? '' : 'none';
        btnNext.innerHTML = '다음 <i class="bi bi-chevron-right ms-1"></i>';
    } else if (n === 2) {
        btnNext.style.display = parsedData ? '' : 'none';
        btnNext.innerHTML = '충돌 검사 시작 <i class="bi bi-shield-check ms-1"></i>';
    } else if (n === 3) {
        btnNext.style.display = 'none'; // 검사 완료 시 표시
        btnNext.innerHTML = '다음 <i class="bi bi-chevron-right ms-1"></i>';
    } else if (n === 4) {
        btnNext.style.display = '';
        if (selectedOption === 'C') {
            btnNext.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1"></i>처음으로';
        } else {
            btnNext.innerHTML = '등록 시작 <i class="bi bi-play-fill ms-1"></i>';
        }
    } else if (n >= 5) {
        btnNext.style.display = 'none';
    }
}

function goToStep(n) {
    if (n >= currentStep) return; // 앞으로만 가능 (클릭은 뒤로만)
    showStep(n);
}

function prevStep() {
    if (currentStep > 1) showStep(currentStep - 1);
}

function nextStep() {
    if (currentStep === 1) {
        if (!selectedEntity) { alert('등록 대상을 선택해주세요.'); return; }
        showStep(2);
    } else if (currentStep === 2) {
        if (!parsedData) { alert('파일을 업로드해주세요.'); return; }
        showStep(3);
        runValidation();
    } else if (currentStep === 3) {
        showStep(4);
        renderStep4();
    } else if (currentStep === 4) {
        if (selectedOption === 'C') {
            resetWizard();
            return;
        }
        showStep(5);
        runImport();
    }
}

// ── Step 1: 엔티티 선택 ──
function renderEntityGrid() {
    const grid = document.getElementById('entityGrid');
    let html = '';
    for (const [key, ent] of Object.entries(ENTITIES)) {
        html += `<div class="entity-card" data-entity="${key}" onclick="selectEntity('${key}')">
            <div class="entity-icon"><i class="bi ${ent.icon}"></i></div>
            <div class="entity-label">${ent.label}</div>
            <div class="entity-count">${ent.columns.length}개 필드</div>
        </div>`;
    }
    grid.innerHTML = html;
}

function selectEntity(key) {
    selectedEntity = key;
    document.querySelectorAll('.entity-card').forEach(c => c.classList.remove('selected'));
    document.querySelector(`.entity-card[data-entity="${key}"]`).classList.add('selected');
    document.getElementById('templateDownloadArea').style.display = '';
    document.getElementById('entityBadge').style.display = '';
    document.getElementById('entityBadge').textContent = ENTITIES[key].label;
    document.getElementById('btnNext').style.display = '';

    // 리셋
    parsedData = null;
    allValidationResults = {};
    importResults = {};
}

function downloadTemplate() {
    if (!selectedEntity) return;
    const btn = document.getElementById('btnDownloadTemplate');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>생성 중...';

    const fd = new FormData();
    fd.append('action', 'download_template');
    fd.append('entity', selectedEntity);

    fetch('', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                window.location.href = '?download=' + encodeURIComponent(d.filename);
            } else {
                alert(d.error || '양식 생성 실패');
            }
        })
        .catch(e => alert('오류: ' + e.message))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-download me-1"></i>엑셀 양식 다운로드';
        });
}

// ── Step 2: 파일 업로드 ──
const uploadZone = document.getElementById('uploadZone');
uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.classList.add('dragover'); });
uploadZone.addEventListener('dragleave', () => uploadZone.classList.remove('dragover'));
uploadZone.addEventListener('drop', e => {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
        document.getElementById('fileInput').files = e.dataTransfer.files;
        handleFileSelect(document.getElementById('fileInput'));
    }
});

function handleFileSelect(input) {
    if (!input.files.length) return;
    const file = input.files[0];

    uploadZone.innerHTML = '<span class="spinner-border text-success"></span><div class="mt-2">파싱 중...</div>';

    const fd = new FormData();
    fd.append('action', 'upload_file');
    fd.append('entity', selectedEntity);
    fd.append('file', file);

    fetch('', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                alert(d.error);
                resetUpload();
                return;
            }
            parsedData = d;
            uploadZone.style.display = 'none';
            document.getElementById('uploadResult').style.display = '';
            document.getElementById('uploadFileName').textContent = d.filename;
            document.getElementById('uploadFileInfo').textContent = `(${d.filesize}, ${d.total}행)`;

            renderMappingTable(d.headers, d.headerMapping);
            renderPreviewTable(d.headers, d.preview);

            document.getElementById('btnNext').style.display = '';
        })
        .catch(e => { alert('업로드 오류: ' + e.message); resetUpload(); });
}

function resetUpload() {
    parsedData = null;
    document.getElementById('uploadResult').style.display = 'none';
    const zone = document.getElementById('uploadZone');
    zone.style.display = '';
    zone.innerHTML = `<div class="upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
        <div class="upload-text">파일을 드래그하거나 클릭하여 선택</div>
        <div class="upload-hint">.xlsx, .csv 파일 지원 (최대 10MB)</div>`;
    document.getElementById('fileInput').value = '';
    document.getElementById('btnNext').style.display = 'none';
}

function renderMappingTable(headers, mapping) {
    const ent = ENTITIES[selectedEntity];
    const tbody = document.querySelector('#mappingTable tbody');
    let html = '';
    headers.forEach(h => {
        const mappedKey = mapping[h];
        const colDef = mappedKey ? ent.columns.find(c => c.key === mappedKey) : null;
        const statusClass = mappedKey ? 'mapped' : 'unmapped';
        const statusIcon = mappedKey ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>';

        // 드롭다운으로 수정 가능
        let selectHtml = `<select class="form-select form-select-sm" style="width:180px;display:inline-block;" onchange="updateMapping('${h}', this.value)">`;
        selectHtml += `<option value="">(매핑 안 함)</option>`;
        ent.columns.forEach(c => {
            const sel = mappedKey === c.key ? 'selected' : '';
            selectHtml += `<option value="${c.key}" ${sel}>${c.label}${c.required ? ' *' : ''}</option>`;
        });
        selectHtml += `</select>`;

        html += `<tr>
            <td><code>${h}</code></td>
            <td>${selectHtml}</td>
            <td class="${statusClass}">${statusIcon} ${mappedKey ? (colDef ? colDef.label : mappedKey) : '미매핑'}</td>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function updateMapping(header, key) {
    if (parsedData && parsedData.headerMapping) {
        parsedData.headerMapping[header] = key || null;
        renderMappingTable(parsedData.headers, parsedData.headerMapping);
    }
}

function renderPreviewTable(headers, preview) {
    const thead = document.querySelector('#previewTable thead');
    const tbody = document.querySelector('#previewTable tbody');

    let thHtml = '<tr><th>#</th>';
    headers.forEach(h => { thHtml += `<th>${h}</th>`; });
    thHtml += '</tr>';
    thead.innerHTML = thHtml;

    let trHtml = '';
    preview.forEach((row, i) => {
        trHtml += `<tr><td class="text-muted">${i + 1}</td>`;
        headers.forEach(h => { trHtml += `<td title="${row[h] || ''}">${row[h] || ''}</td>`; });
        trHtml += '</tr>';
    });
    tbody.innerHTML = trHtml;
}

// ── Step 3: 충돌 검사 ──
async function runValidation() {
    const total = parsedData.total;
    let offset = 0;
    const batchSize = 50;
    allValidationResults = {};
    allMappedRows = [];

    document.getElementById('validateProgress').style.display = '';
    document.getElementById('validateSummary').style.display = 'none';
    document.getElementById('validateTotal').textContent = total;

    while (offset < total) {
        const fd = new FormData();
        fd.append('action', 'validate_batch');
        fd.append('entity', selectedEntity);
        fd.append('offset', offset);
        fd.append('batch_size', batchSize);

        try {
            const r = await fetch('', {method: 'POST', body: fd});
            const d = await r.json();
            if (!d.success) {
                alert(d.error || '검사 오류');
                return;
            }
            // 결과 병합
            for (const [idx, result] of Object.entries(d.results)) {
                allValidationResults[idx] = result;
            }
        } catch(e) {
            alert('검사 오류: ' + e.message);
            return;
        }

        offset += batchSize;
        const progress = Math.min(Math.round((offset / total) * 100), 100);
        document.getElementById('validateBar').style.width = progress + '%';
        document.getElementById('validateBar').textContent = progress + '%';
        document.getElementById('validateCount').textContent = Math.min(offset, total);
    }

    renderValidationSummary();
}

function renderValidationSummary() {
    let counts = {ok: 0, conflict_exact: 0, conflict_similar: 0, fk_missing: 0, error: 0};
    for (const [idx, r] of Object.entries(allValidationResults)) {
        counts[r.status] = (counts[r.status] || 0) + 1;
    }

    document.getElementById('countOk').textContent = counts.ok;
    document.getElementById('countExact').textContent = counts.conflict_exact;
    document.getElementById('countSimilar').textContent = counts.conflict_similar;
    document.getElementById('countFk').textContent = counts.fk_missing;
    document.getElementById('countError').textContent = counts.error;

    // 상세 테이블 (문제 있는 행만)
    const tbody = document.querySelector('#conflictTable tbody');
    let html = '';
    const statusLabels = {
        'ok': ['정상', 'status-ok'],
        'conflict_exact': ['완전 중복', 'status-exact'],
        'conflict_similar': ['유사 일치', 'status-similar'],
        'fk_missing': ['FK 불일치', 'status-fk'],
        'error': ['에러', 'status-error']
    };

    for (const [idx, r] of Object.entries(allValidationResults)) {
        if (r.status === 'ok') continue;
        const [label, cls] = statusLabels[r.status] || ['?', ''];
        const rowNum = parseInt(idx) + 1;

        // 데이터 요약 (처음 3필드)
        const ent = ENTITIES[selectedEntity];
        let summary = '';
        if (r.row) {
            const keys = ent.columns.slice(0, 3).map(c => c.key);
            summary = keys.map(k => r.row[k] || '').filter(v => v).join(' / ');
        }

        // 상세 내용
        let detail = '';
        if (Array.isArray(r.details)) {
            detail = r.details.map(d => {
                if (d.type === 'exact') return `[중복] ${d.field}: "${d.value}" (기존 ID: ${d.existing_id})`;
                if (d.type === 'similar') return `[유사] ${d.msg}`;
                if (d.type === 'fk') return `[FK] ${d.label}: "${d.value}" 없음` + (d.suggestions?.length ? ` (추천: ${d.suggestions.join(', ')})` : '');
                if (d.type === 'required') return `[필수] ${d.msg}`;
                if (d.type === 'validation') return `[검증] ${d.label}: ${d.msg}`;
                return d.msg || JSON.stringify(d);
            }).join('<br>');
        }

        html += `<tr>
            <td>${rowNum}</td>
            <td><span class="${cls}" style="font-weight:600;">${label}</span></td>
            <td>${summary}</td>
            <td style="font-size:11px;">${detail}</td>
        </tr>`;
    }
    tbody.innerHTML = html || '<tr><td colspan="4" class="text-center text-success py-3">모든 데이터가 정상입니다!</td></tr>';

    document.getElementById('validateSummary').style.display = '';
    document.getElementById('validateProgress').style.display = 'none';

    // 다음 버튼 표시
    document.getElementById('btnNext').style.display = '';
    document.getElementById('btnNext').innerHTML = '다음 <i class="bi bi-chevron-right ms-1"></i>';
}

// ── Step 4: 옵션 선택 ──
function renderStep4() {
    let okCount = 0, conflictCount = 0, errorCount = 0;
    for (const [idx, r] of Object.entries(allValidationResults)) {
        if (r.status === 'ok') okCount++;
        else if (r.status === 'error') errorCount++;
        else conflictCount++;
    }

    document.getElementById('optACount').textContent = okCount + '건 등록 예정';
    document.getElementById('optBCount').textContent = conflictCount + '건 충돌';

    // 기본: Option A
    selectOption('A');

    // 충돌 건별 처리 리스트 준비
    conflictDecisions = {};
    const list = document.getElementById('conflictResolveList');
    let html = '';
    for (const [idx, r] of Object.entries(allValidationResults)) {
        if (r.status === 'ok' || r.status === 'error') continue;
        conflictDecisions[idx] = 'skip';

        const rowNum = parseInt(idx) + 1;
        const ent = ENTITIES[selectedEntity];
        const summary = ent.columns.slice(0, 3).map(c => r.row?.[c.key] || '').filter(v => v).join(' / ');
        const statusText = r.status === 'conflict_exact' ? '완전 중복' : (r.status === 'conflict_similar' ? '유사 일치' : 'FK 불일치');

        html += `<div class="conflict-resolve-item" data-idx="${idx}">
            <div class="item-header">
                <div><strong>행 ${rowNum}</strong> <span class="badge bg-warning text-dark">${statusText}</span></div>
                <select class="form-select form-select-sm" style="width:140px;" onchange="conflictDecisions[${idx}]=this.value">
                    <option value="skip" selected>건너뛰기</option>
                    <option value="update">덮어쓰기</option>
                    <option value="insert">신규등록</option>
                </select>
            </div>
            <div class="item-data">${summary}</div>
        </div>`;
    }
    list.innerHTML = html;
}

function selectOption(opt) {
    selectedOption = opt;
    document.querySelectorAll('.option-card').forEach(c => c.classList.remove('selected'));
    document.querySelector(`.option-card[data-option="${opt}"]`).classList.add('selected');

    document.getElementById('conflictResolveArea').style.display = opt === 'B' ? '' : 'none';

    const btnNext = document.getElementById('btnNext');
    if (opt === 'C') {
        btnNext.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1"></i>처음으로';
    } else {
        btnNext.innerHTML = '등록 시작 <i class="bi bi-play-fill ms-1"></i>';
    }
}

function bulkResolve(decision) {
    for (const idx of Object.keys(conflictDecisions)) {
        conflictDecisions[idx] = decision;
    }
    document.querySelectorAll('#conflictResolveList select').forEach(sel => sel.value = decision);
}

// ── Step 5: 실제 등록 ──
async function runImport() {
    // 등록 대상 결정
    const rowsToImport = [];
    const decisionsMap = {};

    for (const [idx, r] of Object.entries(allValidationResults)) {
        if (r.status === 'ok') {
            rowsToImport.push({idx: parseInt(idx), row: r.row, decision: 'insert'});
        } else if (r.status === 'error') {
            // 에러는 건너뛰기
            continue;
        } else {
            // 충돌
            if (selectedOption === 'A') continue; // Option A: 충돌 건너뛰기
            if (selectedOption === 'B') {
                const dec = conflictDecisions[idx] || 'skip';
                if (dec === 'skip') continue;
                rowsToImport.push({idx: parseInt(idx), row: r.row, decision: dec});
            }
        }
    }

    if (rowsToImport.length === 0) {
        showStep(6);
        renderFinalResults({});
        return;
    }

    const total = rowsToImport.length;
    let offset = 0;
    const batchSize = 20;
    importResults = {};
    let successCount = 0, updateCount = 0, skipCount = 0, errorCount = 0;

    document.getElementById('importBar').style.width = '0%';
    document.getElementById('importBar').textContent = '0%';

    while (offset < total) {
        const batch = rowsToImport.slice(offset, offset + batchSize);
        const batchRows = {};
        const batchDecisions = {};
        batch.forEach(item => {
            batchRows[item.idx] = item.row;
            batchDecisions[item.idx] = item.decision;
        });

        const fd = new FormData();
        fd.append('action', 'import_batch');
        fd.append('entity', selectedEntity);
        fd.append('rows', JSON.stringify(batchRows));
        fd.append('decisions', JSON.stringify(batchDecisions));

        try {
            const r = await fetch('', {method: 'POST', body: fd});
            const d = await r.json();
            if (d.success) {
                for (const [idx, result] of Object.entries(d.results)) {
                    importResults[idx] = result;
                    // 원본 행 데이터 보존
                    const origResult = allValidationResults[idx];
                    if (origResult) importResults[idx].row = origResult.row;

                    if (result.status === 'inserted') successCount++;
                    else if (result.status === 'updated') updateCount++;
                    else if (result.status === 'skipped') skipCount++;
                    else if (result.status === 'error') errorCount++;
                }
            }
        } catch(e) {
            console.error('Import error:', e);
        }

        offset += batchSize;
        const progress = Math.min(Math.round((offset / total) * 100), 100);
        document.getElementById('importBar').style.width = progress + '%';
        document.getElementById('importBar').textContent = progress + '%';
        document.getElementById('importSuccess').textContent = successCount;
        document.getElementById('importUpdate').textContent = updateCount;
        document.getElementById('importSkip').textContent = skipCount;
        document.getElementById('importError').textContent = errorCount;
    }

    document.getElementById('importStatusMsg').innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>처리 완료!</span>';

    // 잠시 후 결과 화면으로
    setTimeout(() => {
        showStep(6);
        renderFinalResults({success: successCount, update: updateCount, skip: skipCount, error: errorCount});
    }, 1000);
}

// ── Step 6: 최종 결과 ──
function renderFinalResults(counts) {
    const total = (counts.success || 0) + (counts.update || 0) + (counts.skip || 0) + (counts.error || 0);

    if (total === 0) {
        document.getElementById('finalIcon').textContent = '\u26A0\uFE0F';
        document.getElementById('finalTitle').textContent = '등록된 데이터가 없습니다';
        document.getElementById('finalMsg').textContent = '모든 데이터가 건너뛰기 처리되었거나 에러가 발생했습니다.';
    } else if (counts.error > 0) {
        document.getElementById('finalIcon').textContent = '\u26A0\uFE0F';
        document.getElementById('finalTitle').textContent = '등록 완료 (일부 에러)';
        document.getElementById('finalMsg').textContent = `${counts.success + counts.update}건 처리, ${counts.error}건 에러`;
    } else {
        document.getElementById('finalIcon').textContent = '\u2705';
        document.getElementById('finalTitle').textContent = '등록이 완료되었습니다!';
        document.getElementById('finalMsg').textContent = `총 ${counts.success + counts.update}건이 성공적으로 처리되었습니다.`;
    }

    let summaryHtml = '';
    if (counts.success > 0) summaryHtml += `<div class="result-card success"><div class="result-number">${counts.success}</div><div class="result-label">신규 등록</div></div>`;
    if (counts.update > 0) summaryHtml += `<div class="result-card update"><div class="result-number">${counts.update}</div><div class="result-label">업데이트</div></div>`;
    if (counts.skip > 0) summaryHtml += `<div class="result-card skip"><div class="result-number">${counts.skip}</div><div class="result-label">건너뜀</div></div>`;
    if (counts.error > 0) summaryHtml += `<div class="result-card fail"><div class="result-number">${counts.error}</div><div class="result-label">에러</div></div>`;
    document.getElementById('finalSummary').innerHTML = summaryHtml;

    // 에러 버튼 표시
    document.getElementById('btnDownloadErrors').style.display = counts.error > 0 ? '' : 'none';
}

// ── 다운로드 함수들 ──
function downloadErrorExcel() {
    const errorRows = [];
    for (const [idx, r] of Object.entries(allValidationResults)) {
        if (r.status !== 'ok') {
            errorRows.push({rowIndex: parseInt(idx), status: r.status, row: r.row, details: r.details});
        }
    }
    // import 단계의 에러도 추가
    for (const [idx, r] of Object.entries(importResults)) {
        if (r.status === 'error') {
            const existing = errorRows.find(e => e.rowIndex === parseInt(idx));
            if (!existing) {
                errorRows.push({rowIndex: parseInt(idx), status: 'error', row: r.row || {}, details: [{msg: r.msg}]});
            }
        }
    }

    const fd = new FormData();
    fd.append('action', 'download_errors');
    fd.append('entity', selectedEntity);
    fd.append('error_rows', JSON.stringify(errorRows));

    fetch('', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (d.success) window.location.href = '?download=' + encodeURIComponent(d.filename);
            else alert(d.error || '다운로드 실패');
        });
}

function downloadResultExcel() {
    const allResults = {};
    // validation + import 결과 병합
    for (const [idx, r] of Object.entries(allValidationResults)) {
        allResults[idx] = {
            status: importResults[idx]?.status || (r.status === 'ok' ? 'pending' : r.status),
            msg: importResults[idx]?.msg || (r.status === 'ok' ? '' : r.status),
            row: r.row || {}
        };
    }
    for (const [idx, r] of Object.entries(importResults)) {
        if (allResults[idx]) {
            allResults[idx].status = r.status;
            allResults[idx].msg = r.msg;
        }
    }

    const fd = new FormData();
    fd.append('action', 'download_results');
    fd.append('entity', selectedEntity);
    fd.append('results', JSON.stringify(allResults));

    fetch('', {method: 'POST', body: fd})
        .then(r => r.json())
        .then(d => {
            if (d.success) window.location.href = '?download=' + encodeURIComponent(d.filename);
            else alert(d.error || '다운로드 실패');
        });
}

// ── 리셋 ──
function resetWizard() {
    selectedEntity = null;
    parsedData = null;
    allValidationResults = {};
    importResults = {};
    conflictDecisions = {};
    selectedOption = 'A';
    allMappedRows = [];

    document.querySelectorAll('.entity-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('templateDownloadArea').style.display = 'none';
    document.getElementById('entityBadge').style.display = 'none';
    resetUpload();

    showStep(1);
}

// ── 초기화 ──
renderStepHeader();
renderEntityGrid();
showStep(1);
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
