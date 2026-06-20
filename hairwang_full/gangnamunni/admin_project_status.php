<?php
/**
 * admin_project_status.php - Project Status Report (관리자 내장)
 * 프로젝트 현황, QA 검수, 클라이언트 요구사항, 개발 타임라인
 */
session_start();
$admin_page_title = 'Project Status';
$admin_current = 'project_status';
// date 파라미터 있으면 해당 타임라인 키로 변경 (사이드바 active 매칭)
if (isset($_GET['date'])) {
    $admin_current = 'timeline_' . str_replace('-', '', $_GET['date']);
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();

// 날짜별 작업 이력 조회
$selected_date = isset($_GET['date']) ? $_GET['date'] : null;
$timeline_entries = [];
if ($selected_date) {
    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_dev_timeline WHERE event_date = ? ORDER BY id DESC");
    mysqli_stmt_bind_param($stmt, 's', $selected_date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $timeline_entries[] = $row;
    }

    // Also get fix_log entries for that date
    $stmt2 = mysqli_prepare($connect, "SELECT * FROM gangnamunni_fix_log WHERE DATE(created_at) = ? ORDER BY created_at DESC");
    mysqli_stmt_bind_param($stmt2, 's', $selected_date);
    mysqli_stmt_execute($stmt2);
    $result2 = mysqli_stmt_get_result($stmt2);
    $fix_entries = [];
    while ($row = mysqli_fetch_assoc($result2)) {
        $fix_entries[] = $row;
    }
}

// Create table if not exists
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_client_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    file_name VARCHAR(500),
    url VARCHAR(500),
    progress INT DEFAULT 0,
    status ENUM('pending','in_progress','completed') DEFAULT 'pending',
    dev_note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create timeline table
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_dev_timeline (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_date DATE NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category ENUM('feature','bugfix','deploy','meeting','milestone') DEFAULT 'feature',
    status ENUM('planned','in_progress','completed') DEFAULT 'completed',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// AJAX handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['ajax_action'];

    switch ($action) {
        // === Client Requests ===
        case 'req_list':
            $status_filter = $_POST['status'] ?? '';
            $sql = "SELECT * FROM gangnamunni_client_requests";
            if ($status_filter && in_array($status_filter, ['pending','in_progress','completed'])) {
                $sql .= " WHERE status='" . mysqli_real_escape_string($connect, $status_filter) . "'";
            }
            $sql .= " ORDER BY created_at DESC";
            $r = mysqli_query($connect, $sql);
            $items = [];
            while ($row = mysqli_fetch_assoc($r)) $items[] = $row;
            echo json_encode(['success' => true, 'data' => $items]);
            break;

        case 'req_create':
            $title = trim($_POST['title'] ?? '');
            if (!$title) { echo json_encode(['success' => false, 'message' => '제목 필수']); break; }
            $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_client_requests (title, content, file_name, url) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssss', $title, $_POST['content'], $_POST['file_name'], $_POST['url']);
            mysqli_stmt_execute($stmt);
            echo json_encode(['success' => true, 'id' => mysqli_insert_id($connect)]);
            break;

        case 'req_update':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'message' => 'ID 필수']); break; }
            $fields = [];
            $params = [];
            $types = '';
            if (isset($_POST['progress'])) { $fields[] = "progress=?"; $params[] = intval($_POST['progress']); $types .= 'i'; }
            if (isset($_POST['status'])) {
                $fields[] = "status=?"; $params[] = $_POST['status']; $types .= 's';
                if ($_POST['status'] === 'completed') { $fields[] = "completed_at=NOW()"; }
            }
            if (isset($_POST['dev_note'])) { $fields[] = "dev_note=?"; $params[] = $_POST['dev_note']; $types .= 's'; }
            if (empty($fields)) { echo json_encode(['success' => false, 'message' => '변경 사항 없음']); break; }
            $sql = "UPDATE gangnamunni_client_requests SET " . implode(', ', $fields) . " WHERE id=?";
            $types .= 'i'; $params[] = $id;
            $stmt = mysqli_prepare($connect, $sql);
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            echo json_encode(['success' => true]);
            break;

        case 'req_delete':
            $id = intval($_POST['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false]); break; }
            mysqli_query($connect, "DELETE FROM gangnamunni_client_requests WHERE id=" . $id);
            echo json_encode(['success' => true]);
            break;

        // === Timeline ===
        case 'timeline_list':
            $r = mysqli_query($connect, "SELECT * FROM gangnamunni_dev_timeline ORDER BY event_date DESC, id DESC");
            $items = [];
            while ($row = mysqli_fetch_assoc($r)) $items[] = $row;
            echo json_encode(['success' => true, 'data' => $items]);
            break;

        case 'timeline_create':
            $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_dev_timeline (event_date, title, description, category, status) VALUES (?, ?, ?, ?, ?)");
            $date = $_POST['event_date'] ?? date('Y-m-d');
            $title = $_POST['title'] ?? '';
            $desc = $_POST['description'] ?? '';
            $cat = $_POST['category'] ?? 'feature';
            $st = $_POST['status'] ?? 'completed';
            mysqli_stmt_bind_param($stmt, 'sssss', $date, $title, $desc, $cat, $st);
            mysqli_stmt_execute($stmt);
            echo json_encode(['success' => true, 'id' => mysqli_insert_id($connect)]);
            break;

        case 'timeline_delete':
            $id = intval($_POST['id'] ?? 0);
            mysqli_query($connect, "DELETE FROM gangnamunni_dev_timeline WHERE id=" . $id);
            echo json_encode(['success' => true]);
            break;

        // === QA Stats ===
        case 'qa_stats':
            // Count client requests by status
            $r = mysqli_query($connect, "SELECT status, COUNT(*) as cnt FROM gangnamunni_client_requests GROUP BY status");
            $stats = ['pending' => 0, 'in_progress' => 0, 'completed' => 0];
            while ($row = mysqli_fetch_assoc($r)) $stats[$row['status']] = intval($row['cnt']);
            $stats['total'] = array_sum($stats);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'unknown action']);
    }
    exit;
}

// Seed timeline if empty
$r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_dev_timeline");
$cnt = mysqli_fetch_assoc($r)['cnt'];
if ($cnt == 0) {
    $seeds = [
        ['2026-02-09', '관리자 레이아웃 + Apache CSP', '관리자 대시보드, 사이드바, 다크모드 구축', 'feature', 'completed'],
        ['2026-02-11', 'DB 통합 + SSO + 메일 + RBAC', 'HMS DB 통합, 크론 동기화, SSO 로그인, 메일 시스템', 'feature', 'completed'],
        ['2026-02-23', '등록 마법사 7종 + 엑셀', 'E2E 78/78 PASS', 'feature', 'completed'],
        ['2026-02-24', '후기 에디터 v2 + 승인 워크플로우', '', 'feature', 'completed'],
        ['2026-02-25', 'AI 자동화 5단계 + 매뉴얼 15종', '멀티프로바이더, 이벤트/응모, 이미지에디터', 'feature', 'completed'],
        ['2026-02-26', '로그인/상단바 + 주간/야간 토글', 'anti-flash, 신청 워크플로우 점검', 'feature', 'completed'],
        ['2026-02-27', '모바일 앱 + FCM 알림', 'Flutter 빌드, FCM 푸시 알림', 'deploy', 'completed'],
        ['2026-03-04', '프론트엔드 전면 개편', '3뷰 모드, 광고 시스템, 네비게이션', 'feature', 'completed'],
        ['2026-03-05', '라이브 세팅 패널 v2 + 예약 연동', '57개 설정, HMS API 연동', 'feature', 'completed'],
        ['2026-03-06', 'QA 19개 항목 수정 + 서버 배포', '', 'bugfix', 'completed'],
        ['2026-03-07', '사이드바 메뉴 통합 + 백업 관리 강화', '앱 빌드 탭, 서버 구조 탭', 'feature', 'completed'],
        ['2026-03-07', '클라이언트 QA 검수 피드백 접수', '20건 접수, 긴급 5건 파악', 'milestone', 'in_progress'],
        ['2026-03-15', '최종 납품 마감일', '앱 스토어 배포 + 문서 납품', 'milestone', 'planned'],
    ];
    foreach ($seeds as $s) {
        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_dev_timeline (event_date, title, description, category, status) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssss', $s[0], $s[1], $s[2], $s[3], $s[4]);
        mysqli_stmt_execute($stmt);
    }
}

$active_tab = $_GET['tab'] ?? ($selected_date ? 'timeline' : 'overview');

include __DIR__ . '/includes/admin_header.php';
?>

<!-- 탭 네비게이션 -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link <?= $active_tab === 'overview' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-overview">
            <i class="bi bi-graph-up"></i> 프로젝트 현황
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?= $active_tab === 'qa' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-qa">
            <i class="bi bi-list-check"></i> QA 검수
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?= $active_tab === 'requests' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-requests">
            <i class="bi bi-chat-left-text"></i> 클라이언트 요구사항
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?= $active_tab === 'timeline' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-timeline">
            <i class="bi bi-clock-history"></i> 개발 타임라인
        </button>
    </li>
    <li class="nav-item ms-auto">
        <a class="nav-link text-muted" href="project_status_report.html" target="_blank">
            <i class="bi bi-box-arrow-up-right"></i> 전체 리포트(HTML)
        </a>
    </li>
</ul>

<div class="tab-content">

<!-- ===== 탭 1: 프로젝트 현황 ===== -->
<div class="tab-pane fade <?= $active_tab === 'overview' ? 'show active' : '' ?>" id="tab-overview">

    <!-- 상단 요약 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-primary" id="statTotal">-</div>
                    <small class="text-muted">전체 요구사항</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-warning" id="statPending">-</div>
                    <small class="text-muted">대기</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-info" id="statProgress">-</div>
                    <small class="text-muted">진행중</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center py-3">
                    <div class="fs-2 fw-bold text-success" id="statDone">-</div>
                    <small class="text-muted">완료</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 진행률 바 -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-1">
                <strong>전체 진행률</strong>
                <span id="progressPct">0%</span>
            </div>
            <div class="progress" style="height: 24px;">
                <div class="progress-bar bg-success" id="progressBar" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <!-- D-Day -->
    <div class="card mb-4">
        <div class="card-body d-flex align-items-center gap-3">
            <i class="bi bi-calendar-event text-danger" style="font-size:32px;"></i>
            <div>
                <div class="fw-bold">납품 마감일: 2026-03-15</div>
                <div class="text-muted" id="dday">-</div>
            </div>
            <a href="project_status_report.html" target="_blank" class="btn btn-outline-primary ms-auto">
                <i class="bi bi-file-earmark-richtext me-1"></i>전체 리포트 보기
            </a>
        </div>
    </div>

    <!-- 최근 타임라인 미리보기 -->
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <strong><i class="bi bi-clock-history me-1"></i>최근 개발 이력</strong>
        </div>
        <div class="card-body p-0" id="recentTimeline">
            <div class="text-center text-muted py-3">로딩 중...</div>
        </div>
    </div>
</div>

<!-- ===== 탭 2: QA 검수 ===== -->
<div class="tab-pane fade <?= $active_tab === 'qa' ? 'show active' : '' ?>" id="tab-qa">
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <strong><i class="bi bi-list-check me-1"></i>QA 검수 항목 (구글 시트 연동)</strong>
            <a href="https://docs.google.com/spreadsheets/d/1zIuvYNBr7zaTYpUdC_IH5lj57rnDgSLCpNJAmXqQVig/" target="_blank" class="btn btn-sm btn-outline-success ms-auto">
                <i class="bi bi-table me-1"></i>구글 시트 열기
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle" id="qaTable">
                    <thead class="table-light">
                        <tr>
                            <th width="40">NO</th>
                            <th>해당 페이지</th>
                            <th>오류 내용</th>
                            <th width="80">상태</th>
                            <th width="120">처리</th>
                        </tr>
                    </thead>
                    <tbody id="qaBody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ===== 탭 3: 클라이언트 요구사항 ===== -->
<div class="tab-pane fade <?= $active_tab === 'requests' ? 'show active' : '' ?>" id="tab-requests">

    <!-- 등록 폼 -->
    <div class="card mb-3">
        <div class="card-header" role="button" data-bs-toggle="collapse" data-bs-target="#reqForm">
            <i class="bi bi-plus-circle me-1"></i><strong>새 요구사항 등록</strong>
        </div>
        <div class="collapse" id="reqForm">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">제목 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="reqTitle" placeholder="요구사항 제목">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">URL (선택)</label>
                        <input type="text" class="form-control" id="reqUrl" placeholder="https://...">
                    </div>
                    <div class="col-12">
                        <label class="form-label">내용</label>
                        <textarea class="form-control" id="reqContent" rows="3" placeholder="상세 내용"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">첨부 파일명 (선택)</label>
                        <input type="text" class="form-control" id="reqFile" placeholder="파일명 또는 경로">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="createRequest()">
                            <i class="bi bi-plus me-1"></i>등록하기
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 서브탭: 진행중 / 완료 -->
    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <button class="nav-link active" onclick="loadRequests('active')" id="btnReqActive">진행중 <span class="badge bg-warning" id="reqActiveCount">0</span></button>
        </li>
        <li class="nav-item">
            <button class="nav-link" onclick="loadRequests('completed')" id="btnReqDone">완료 <span class="badge bg-success" id="reqDoneCount">0</span></button>
        </li>
    </ul>

    <div id="requestList">
        <div class="text-center text-muted py-3">로딩 중...</div>
    </div>
</div>

<!-- ===== 탭 4: 개발 타임라인 ===== -->
<div class="tab-pane fade <?= $active_tab === 'timeline' ? 'show active' : '' ?>" id="tab-timeline">

    <!-- 추가 폼 -->
    <?php if (check_permission('project_status', true)): ?>
    <div class="card mb-3">
        <div class="card-header" role="button" data-bs-toggle="collapse" data-bs-target="#tlForm">
            <i class="bi bi-plus-circle me-1"></i><strong>타임라인 추가</strong>
        </div>
        <div class="collapse" id="tlForm">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-2">
                        <label class="form-label">날짜</label>
                        <input type="date" class="form-control" id="tlDate" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">제목</label>
                        <input type="text" class="form-control" id="tlTitle">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">카테고리</label>
                        <select class="form-select" id="tlCat">
                            <option value="feature">기능 개발</option>
                            <option value="bugfix">버그 수정</option>
                            <option value="deploy">배포</option>
                            <option value="meeting">미팅</option>
                            <option value="milestone">마일스톤</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="createTimeline()">추가</button>
                    </div>
                    <div class="col-12">
                        <input type="text" class="form-control" id="tlDesc" placeholder="설명 (선택)">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div id="timelineList">
        <div class="text-center text-muted py-3">로딩 중...</div>
    </div>
</div>

</div><!-- end tab-content -->

<?php
// selectedDate를 Nowdoc 밖에서 정의
$_selected_date_js = htmlspecialchars($selected_date ?? '', ENT_QUOTES);
?>
<script>var selectedDate = '<?php echo $_selected_date_js; ?>';</script>
<?php
$admin_extra_js = <<<'JSEOF'

var qaItems = [
    {no: 1, page: '후기 관리', error: '후기 내용 확인 필요 / 5개 후기 미표시', status: 'progress', red: '관리자 페이지에서 확인 가능한 5개의 후기가 보이지 않는 현상'},
    {no: 2, page: '후기 관리', error: '후기 마법사 작성 불가', status: 'error', red: '후기 마법사로 작성 클릭 시 오류 화면 발생(File not found.)'},
    {no: 3, page: '병원 이미지', error: '이미지 추가 불가능', status: 'progress', red: '기존 이미지 수정/제거 가능하지만 추가 방법 문의'},
    {no: 4, page: '광고 관리', error: '광고 유형 매뉴얼', status: 'done', red: ''},
    {no: '4-1', page: '광고 관리', error: '광고 승인/거절 오류', status: 'error', red: '승인/거절 시 오류 발생중'},
    {no: 5, page: '광고 관리', error: '이미지 URL 등록 방법 매뉴얼', status: 'progress', red: ''},
    {no: 6, page: '광고 관리', error: '랜딩 URL 등록 + 게시판 관리', status: 'pending', red: '현재 완성된 URL 정보도 확인 어려움'},
    {no: 7, page: '병원 등록', error: '병원 목록 확인 불가', status: 'done', red: ''},
    {no: 8, page: '병원 등록', error: '승인 절차 확인', status: 'error', red: '승인 클릭 시 오류 발생중(HTTP ERROR 500)'},
    {no: 9, page: '병원 등록', error: '등록 팝업 문구 오류', status: 'done', red: ''},
    {no: 10, page: '등록 관리', error: '등록 목록 확인/수정', status: 'done', red: '리스트만 보이고 수정/관리 불가능'},
    {no: 11, page: '회원 관리', error: '비밀번호 수정/찾기', status: 'error', red: '발급된 비밀번호로 서울미녀 앱 로그인 불가능 현상'},
    {no: 12, page: '회원 관리', error: '회원 등급 매뉴얼', status: 'done', red: ''},
    {no: 13, page: '회원 관리', error: '회원/환자 차이점', status: 'pending', red: '환자 관리 메뉴명/위치 확인'},
    {no: '13+', page: '관리자 전반', error: '저장/승인/거절 오류 다수', status: 'error', red: '데이터 수정 및 저장 관련 오류 다수 발생'},
    {no: 14, page: '프론트 메인', error: '이벤트 관리 + 응모 기능', status: 'progress', red: '이벤트 등록 팝업 스크롤 미작동 오류'},
    {no: 15, page: '프론트 메인', error: '꿀팁 관리 기능 확인', status: 'progress', red: ''},
    {no: 16, page: '프론트 메인', error: '추천 병원 관리자 지정', status: 'pending', red: '추천 병원 리스트 조절 매뉴얼 요청'},
    {no: 17, page: '프론트', error: '상담 신청 기능', status: 'progress', red: '예약/상담 메뉴 관리자 탭에 추가 요청'},
    {no: '', page: '프론트 메인', error: '배너 스크롤 이미지 잘림', status: 'pending', red: 'header에 걸려 잘려 보이는 현상'},
    {no: 18, page: '프론트 카테고리', error: '커뮤니티/칼럼 관리', status: 'progress', red: '커뮤니티, 칼럼 게시글 관리 불가능 현상'},
    {no: 19, page: '프론트 로그인', error: '로그인 정보 찾기', status: 'progress', red: '이메일 미수신, 카카오 로그인 준비 중 표시'}
];

function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

// D-Day
var deadline = new Date('2026-03-15');
var today = new Date();
var diff = Math.ceil((deadline - today) / (1000*60*60*24));
document.getElementById('dday').textContent = diff > 0 ? 'D-' + diff : (diff === 0 ? 'D-Day!' : 'D+' + Math.abs(diff));

// Load stats
function loadStats() {
    fetch('admin_project_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax_action=qa_stats'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) return;
        var s = data.data;
        document.getElementById('statTotal').textContent = s.total;
        document.getElementById('statPending').textContent = s.pending;
        document.getElementById('statProgress').textContent = s.in_progress;
        document.getElementById('statDone').textContent = s.completed;
        var pct = s.total > 0 ? Math.round(s.completed / s.total * 100) : 0;
        document.getElementById('progressPct').textContent = pct + '%';
        document.getElementById('progressBar').style.width = pct + '%';
    });
}

// QA Table
function renderQA() {
    var html = '';
    qaItems.forEach(function(q) {
        var statusBadge = '';
        if (q.status === 'done') statusBadge = '<span class="badge bg-success">완료</span>';
        else if (q.status === 'error') statusBadge = '<span class="badge bg-danger">오류</span>';
        else if (q.status === 'progress') statusBadge = '<span class="badge bg-info">진행중</span>';
        else statusBadge = '<span class="badge bg-secondary">대기</span>';

        var redHtml = q.red ? '<div class="text-danger small mt-1"><i class="bi bi-exclamation-triangle me-1"></i>' + esc(q.red) + '</div>' : '';

        html += '<tr' + (q.status === 'error' ? ' class="table-danger"' : '') + '>';
        html += '<td class="text-center fw-bold">' + (q.no || '#') + '</td>';
        html += '<td><small>' + esc(q.page) + '</small></td>';
        html += '<td>' + esc(q.error) + redHtml + '</td>';
        html += '<td class="text-center">' + statusBadge + '</td>';
        html += '<td>';
        if (q.status !== 'done') {
            html += '<button class="btn btn-sm btn-outline-success" onclick="markQaDone(' + (typeof q.no === 'number' ? q.no : "'" + q.no + "'") + ')"><i class="bi bi-check"></i></button>';
        }
        html += '</td>';
        html += '</tr>';
    });
    document.getElementById('qaBody').innerHTML = html;
}

function markQaDone(no) {
    qaItems.forEach(function(q) { if (q.no == no) q.status = 'done'; });
    renderQA();
}

// Client Requests
var currentReqFilter = 'active';

function loadRequests(filter) {
    currentReqFilter = filter || 'active';
    document.getElementById('btnReqActive').classList.toggle('active', filter === 'active');
    document.getElementById('btnReqDone').classList.toggle('active', filter === 'completed');

    fetch('admin_project_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax_action=req_list'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) return;
        var active = data.data.filter(function(r) { return r.status !== 'completed'; });
        var done = data.data.filter(function(r) { return r.status === 'completed'; });

        document.getElementById('reqActiveCount').textContent = active.length;
        document.getElementById('reqDoneCount').textContent = done.length;

        var items = filter === 'completed' ? done : active;
        if (items.length === 0) {
            document.getElementById('requestList').innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-inbox" style="font-size:32px;"></i><br>항목 없음</div>';
            return;
        }

        var html = '';
        items.forEach(function(r) {
            var statusCls = r.status === 'completed' ? 'success' : (r.status === 'in_progress' ? 'info' : 'warning');
            var statusTxt = r.status === 'completed' ? '완료' : (r.status === 'in_progress' ? '진행중' : '대기');

            html += '<div class="card mb-2">';
            html += '<div class="card-body py-2">';
            html += '<div class="d-flex align-items-start gap-2">';
            html += '<span class="badge bg-' + statusCls + ' mt-1">' + statusTxt + '</span>';
            html += '<div style="flex:1;min-width:0;">';
            html += '<div class="fw-bold">#' + r.id + ' ' + esc(r.title) + '</div>';
            if (r.content) html += '<div class="text-muted small">' + esc(r.content).substring(0, 200) + '</div>';
            if (r.url) html += '<a href="' + esc(r.url) + '" target="_blank" class="small"><i class="bi bi-link-45deg"></i>' + esc(r.url).substring(0, 60) + '</a>';
            html += '<div class="small text-muted mt-1">' + r.created_at + '</div>';
            html += '</div>';

            // Progress slider
            html += '<div style="width:120px;" class="text-center">';
            html += '<input type="range" class="form-range" min="0" max="100" value="' + r.progress + '" onchange="updateReqProgress(' + r.id + ', this.value)" style="width:100px;">';
            html += '<small>' + r.progress + '%</small>';
            html += '</div>';

            // Actions
            html += '<div class="d-flex gap-1">';
            if (r.status !== 'completed') {
                html += '<button class="btn btn-sm btn-outline-success" onclick="updateReqStatus(' + r.id + ', \'completed\')" title="완료"><i class="bi bi-check-lg"></i></button>';
                html += '<button class="btn btn-sm btn-outline-info" onclick="updateReqStatus(' + r.id + ', \'in_progress\')" title="진행중"><i class="bi bi-play"></i></button>';
            }
            html += '<button class="btn btn-sm btn-outline-danger" onclick="deleteRequest(' + r.id + ')" title="삭제"><i class="bi bi-trash"></i></button>';
            html += '</div>';

            html += '</div></div></div>';
        });
        document.getElementById('requestList').innerHTML = html;
    });
}

function createRequest() {
    var title = document.getElementById('reqTitle').value.trim();
    if (!title) { alert('제목을 입력하세요'); return; }
    fetch('admin_project_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax_action=req_create&title=' + encodeURIComponent(title) +
              '&content=' + encodeURIComponent(document.getElementById('reqContent').value) +
              '&url=' + encodeURIComponent(document.getElementById('reqUrl').value) +
              '&file_name=' + encodeURIComponent(document.getElementById('reqFile').value)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('reqTitle').value = '';
            document.getElementById('reqContent').value = '';
            document.getElementById('reqUrl').value = '';
            document.getElementById('reqFile').value = '';
            loadRequests(currentReqFilter);
            loadStats();
        } else { alert(data.message || '등록 실패'); }
    });
}

function updateReqProgress(id, val) {
    fetch('admin_project_status.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'ajax_action=req_update&id='+id+'&progress='+val});
}

function updateReqStatus(id, status) {
    fetch('admin_project_status.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'ajax_action=req_update&id='+id+'&status='+status})
    .then(function() { loadRequests(currentReqFilter); loadStats(); });
}

function deleteRequest(id) {
    if (!confirm('삭제하시겠습니까?')) return;
    fetch('admin_project_status.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'ajax_action=req_delete&id='+id})
    .then(function() { loadRequests(currentReqFilter); loadStats(); });
}

// Timeline
function loadTimeline(limit) {
    fetch('admin_project_status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'ajax_action=timeline_list'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) return;
        var items = data.data;
        renderTimeline(items, 'timelineList', false);
        renderTimeline(items.slice(0, limit || 5), 'recentTimeline', true);
    });
}

function renderTimeline(items, targetId, compact) {
    if (items.length === 0) {
        document.getElementById(targetId).innerHTML = '<div class="text-center text-muted py-3">타임라인 없음</div>';
        return;
    }

    var catIcons = {feature: 'bi-star-fill text-primary', bugfix: 'bi-bug-fill text-danger', deploy: 'bi-rocket-takeoff-fill text-success', meeting: 'bi-people-fill text-info', milestone: 'bi-flag-fill text-warning'};
    var catLabels = {feature: '기능', bugfix: '버그', deploy: '배포', meeting: '미팅', milestone: '마일스톤'};

    var html = '';
    var lastDate = '';

    items.forEach(function(t) {
        var dateStr = t.event_date;
        if (dateStr !== lastDate) {
            lastDate = dateStr;
            html += '<div class="px-3 py-1 bg-body-secondary border-bottom"><small class="fw-bold text-muted">' + dateStr + '</small></div>';
        }

        var icon = catIcons[t.category] || 'bi-circle text-secondary';
        var label = catLabels[t.category] || t.category;
        var statusBadge = t.status === 'completed' ? '<span class="badge bg-success">완료</span>' :
                          (t.status === 'in_progress' ? '<span class="badge bg-info">진행</span>' :
                          '<span class="badge bg-secondary">예정</span>');

        html += '<div class="d-flex align-items-start gap-2 px-3 py-2 border-bottom" data-date="' + dateStr + '">';
        html += '<i class="bi ' + icon + '" style="font-size:16px;margin-top:2px;"></i>';
        html += '<div style="flex:1;min-width:0;">';
        html += '<div class="fw-semibold">' + esc(t.title) + ' ' + statusBadge + '</div>';
        if (t.description && !compact) html += '<small class="text-muted">' + esc(t.description) + '</small>';
        html += '</div>';
        if (!compact) {
            html += '<button class="btn btn-sm btn-outline-danger" onclick="deleteTimeline(' + t.id + ')"><i class="bi bi-x"></i></button>';
        }
        html += '</div>';
    });
    document.getElementById(targetId).innerHTML = html;
}

function createTimeline() {
    var date = document.getElementById('tlDate').value;
    var title = document.getElementById('tlTitle').value.trim();
    if (!title) { alert('제목 입력'); return; }
    fetch('admin_project_status.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'ajax_action=timeline_create&event_date='+encodeURIComponent(date)+'&title='+encodeURIComponent(title)+
             '&description='+encodeURIComponent(document.getElementById('tlDesc').value)+
             '&category='+document.getElementById('tlCat').value})
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('tlTitle').value = '';
            document.getElementById('tlDesc').value = '';
            loadTimeline();
        }
    });
}

function deleteTimeline(id) {
    if (!confirm('삭제?')) return;
    fetch('admin_project_status.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'ajax_action=timeline_delete&id='+id})
    .then(function() { loadTimeline(); });
}

// Init
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
    renderQA();
    loadRequests('active');
    loadTimeline(selectedDate ? 999 : 5);
    // 선택된 날짜 하이라이트
    if (selectedDate) {
        setTimeout(function() {
            var items = document.querySelectorAll('[data-date]');
            items.forEach(function(el) {
                if (el.dataset.date === selectedDate) {
                    el.style.background = 'rgba(174,25,50,0.1)';
                    el.style.borderLeft = '4px solid #AE1932';
                    el.scrollIntoView({behavior:'smooth', block:'center'});
                }
            });
        }, 500);
    }
});

JSEOF;

include __DIR__ . '/includes/admin_footer.php';
