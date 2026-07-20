<?php
/**
 * 관리자 - 후기 관리 페이지
 * Bootstrap 5.3 + 주간/야간 모드
 */

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/db.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패: " . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

// 로그인 체크
$is_logged_in = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
if (!$is_logged_in) { header('Location: dashboard.php'); exit; }
require_permission('reviews');
if (isset($_GET['logout'])) { session_destroy(); header('Location: dashboard.php'); exit; }

$message = '';
$error = '';

$db = Database::getInstance();

// editor 는 본인 소속 병원 후기만 조회·처리 (읽기·쓰기 공통)
$is_editor_scope   = (($_SESSION['ad_admin_role'] ?? '') === 'editor');
$scope_hospital_id = $is_editor_scope ? (int)($_SESSION['ad_admin_hospital_id'] ?? 0) : 0;

// 후기가 현재 관리자 접근 범위에 속하는지 확인 (editor 아니면 항상 허용)
function review_in_scope($db, $review_id, $is_editor_scope, $scope_hospital_id) {
    if (!$is_editor_scope) return true;
    if ($scope_hospital_id <= 0 || $review_id <= 0) return false;
    $row = $db->fetchOne("SELECT hospital_id FROM hospital_reviews WHERE id = ?", [$review_id]);
    return $row && (int)$row['hospital_id'] === $scope_hospital_id;
}

// 일괄 대상 id 목록을 본인 병원 후기로만 필터 (editor 아니면 그대로)
function reviews_scope_filter_ids($db, $ids, $is_editor_scope, $scope_hospital_id) {
    if (!$is_editor_scope) return $ids;
    if ($scope_hospital_id <= 0 || empty($ids)) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $rows = $db->fetchAll("SELECT id FROM hospital_reviews WHERE id IN ({$ph}) AND hospital_id = ?", array_merge($ids, [$scope_hospital_id]));
    return array_map(function ($r) { return (int)$r['id']; }, $rows);
}

// AI 후기 검수 AJAX (#2)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ai_verify') {
    header('Content-Type: application/json; charset=utf-8');
    $review_id = intval($_POST['review_id'] ?? 0);
    $review = $db->fetchOne("SELECT * FROM hospital_reviews WHERE id = ?", [$review_id]);
    if (!$review) {
        echo json_encode(['error' => '후기를 찾을 수 없습니다']);
        exit;
    }
    if ($is_editor_scope && (int)$review['hospital_id'] !== $scope_hospital_id) {
        echo json_encode(['error' => '접근 권한이 없는 후기입니다']);
        exit;
    }
    $content = $review['content'];
    $rating = $review['rating'];
    $user_name = $review['user_name'];

    // AI API 호출 시도
    $ai_result = null;
    try {
        require_once __DIR__ . '/ai/openai_config.php';
        $messages = [
            ['role' => 'system', 'content' => '당신은 병원 후기 검수 전문가입니다. 후기를 분석하여 JSON 형식으로 결과를 반환하세요.
응답 형식: {"score": 0-100, "sentiment": "positive/neutral/negative", "issues": ["문제점1"], "recommendation": "approve/reject/review", "reason": "판단 근거"}
검수 기준: 스팸/광고 여부, 욕설/비방 여부, 실제 시술 경험 여부, 개인정보 노출 여부, 의료법 위반 표현 여부'],
            ['role' => 'user', 'content' => "후기 검수 요청:\n작성자: {$user_name}\n별점: {$rating}/5\n내용: {$content}"]
        ];
        $response = callTextAI($messages, ['temperature' => 0.3, 'max_tokens' => 500]);
        if ($response && isset($response['content'])) {
            $json_str = $response['content'];
            // JSON 부분만 추출
            if (preg_match('/\{[\s\S]*\}/', $json_str, $matches)) {
                $ai_result = json_decode($matches[0], true);
            }
        }
    } catch (Exception $e) {
        // AI 실패시 룰 기반 fallback
    }

    // AI 실패시 룰 기반 검수
    if (!$ai_result) {
        $score = 70;
        $issues = [];
        $sentiment = 'neutral';

        if (mb_strlen($content) < 10) { $score -= 30; $issues[] = '내용이 너무 짧습니다'; }
        if (mb_strlen($content) > 2000) { $score -= 10; $issues[] = '내용이 매우 깁니다'; }
        if ($rating >= 4) $sentiment = 'positive';
        elseif ($rating <= 2) $sentiment = 'negative';

        $spam_words = ['홍보', '광고', '바로가기', 'http://', 'https://', '카톡', '텔레그램', '무료상담'];
        foreach ($spam_words as $w) {
            if (mb_strpos($content, $w) !== false) { $score -= 15; $issues[] = "스팸 의심 키워드: {$w}"; }
        }
        $bad_words = ['씨발', '개새끼', '병신', '지랄'];
        foreach ($bad_words as $w) {
            if (mb_strpos($content, $w) !== false) { $score -= 25; $issues[] = '비속어 포함'; break; }
        }
        if (preg_match('/\d{3}-\d{4}-\d{4}/', $content)) { $score -= 20; $issues[] = '전화번호 노출'; }

        $score = max(0, min(100, $score));
        $recommendation = $score >= 60 ? 'approve' : ($score >= 40 ? 'review' : 'reject');
        $ai_result = [
            'score' => $score,
            'sentiment' => $sentiment,
            'issues' => $issues,
            'recommendation' => $recommendation,
            'reason' => empty($issues) ? '특이사항 없음 (룰 기반 검수)' : implode(', ', $issues) . ' (룰 기반 검수)',
            'method' => 'rule_based'
        ];
    } else {
        $ai_result['method'] = 'ai';
    }

    echo json_encode($ai_result, JSON_UNESCAPED_UNICODE);
    exit;
}

// 일괄 승인
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_approve') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    $ids = reviews_scope_filter_ids($db, $ids, $is_editor_scope, $scope_hospital_id);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->execute("UPDATE hospital_reviews SET review_status = 'published', is_active = 1 WHERE id IN ({$placeholders})", $ids);
        $message = count($ids) . "개 후기가 승인되었습니다.";
    }
}

// 일괄 거부
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_reject') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    $ids = reviews_scope_filter_ids($db, $ids, $is_editor_scope, $scope_hospital_id);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->execute("UPDATE hospital_reviews SET review_status = 'rejected', is_active = 0 WHERE id IN ({$placeholders})", $ids);
        $message = count($ids) . "개 후기가 거부되었습니다.";
    }
}

// 일괄 삭제
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_delete') {
    $ids = array_map('intval', $_POST['ids'] ?? []);
    $ids = reviews_scope_filter_ids($db, $ids, $is_editor_scope, $scope_hospital_id);
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->execute("DELETE FROM hospital_reviews WHERE id IN ({$placeholders})", $ids);
        $message = count($ids) . "개 후기가 삭제되었습니다.";
    }
}

// 개별 승인/거부 (adminAction JS) — 페이지 세션 기반 관리자 인증(require_permission) 사용.
// api/admin/approve_review.php 는 그누보드 회원세션(ss_mb_id/레벨10) 기준이라 강남언니 관리자 세션과 맞지 않아 여기서 처리.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['approve_one', 'reject_one'], true)) {
    $rid = intval($_POST['review_id'] ?? 0);
    $review = $rid > 0 ? $db->fetchOne("SELECT id, hospital_id FROM hospital_reviews WHERE id = ?", [$rid]) : null;
    if ($review && $is_editor_scope && (int)$review['hospital_id'] !== $scope_hospital_id) {
        $error = '접근 권한이 없는 후기입니다.';
        $review = null;
    }
    if ($review) {
        if ($_POST['action'] === 'approve_one') {
            $db->execute("UPDATE hospital_reviews SET review_status = 'published', is_active = 1, updated_at = NOW() WHERE id = ?", [$rid]);
            $message = "후기 #{$rid}이(가) 승인되었습니다.";
        } else {
            $db->execute("UPDATE hospital_reviews SET review_status = 'rejected', is_active = 0, updated_at = NOW() WHERE id = ?", [$rid]);
            $message = "후기 #{$rid}이(가) 거부되었습니다.";
        }
        $hid = (int)$review['hospital_id'];
        if ($hid > 0) {
            $db->execute(
                "UPDATE gangnamunni_hospitals SET
                    review_count = (SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id = ? AND review_status = 'published'),
                    rating = (SELECT COALESCE(AVG(rating), 0) FROM hospital_reviews WHERE hospital_id = ? AND review_status = 'published')
                 WHERE id = ?",
                [$hid, $hid, $hid]
            );
        }
        $db->execute("UPDATE review_reports SET status = 'reviewed', processed_at = NOW() WHERE review_id = ? AND status = 'pending'", [$rid]);
    } elseif (empty($error)) {
        $error = "후기를 찾을 수 없습니다.";
    }
}

// 개별 편집
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_review') {
    $edit_id = intval($_POST['edit_id'] ?? 0);
    $edit_title = trim($_POST['edit_title'] ?? '');
    $edit_content = trim($_POST['edit_content'] ?? '');
    $edit_rating = max(1, min(5, intval($_POST['edit_rating'] ?? 5)));
    $edit_user_name = trim($_POST['edit_user_name'] ?? '');
    $edit_is_active = intval($_POST['edit_is_active'] ?? 1);
    $edit_hospital_id = intval($_POST['edit_hospital_id'] ?? 0);

    // editor: 대상 후기 소유권 확인 + hospital_id 를 본인 병원으로 강제(타 병원 이동 차단)
    if ($is_editor_scope) {
        if (!review_in_scope($db, $edit_id, $is_editor_scope, $scope_hospital_id)) {
            $error = '접근 권한이 없는 후기입니다.';
            $edit_id = 0;
        } else {
            $edit_hospital_id = $scope_hospital_id;
        }
    }

    if ($edit_id > 0 && !empty($edit_content)) {
        $db->execute(
            "UPDATE hospital_reviews SET title = ?, content = ?, rating = ?, user_name = ?, is_active = ?, hospital_id = ?, updated_at = NOW() WHERE id = ?",
            [$edit_title, $edit_content, $edit_rating, $edit_user_name, $edit_is_active, $edit_hospital_id, $edit_id]
        );
        // 병원 통계 업데이트
        if ($edit_hospital_id > 0) {
            $db->execute(
                "UPDATE gangnamunni_hospitals SET
                    review_count = (SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id = ? AND review_status = 'published'),
                    rating = (SELECT COALESCE(AVG(rating), 0) FROM hospital_reviews WHERE hospital_id = ? AND review_status = 'published')
                 WHERE id = ?",
                [$edit_hospital_id, $edit_hospital_id, $edit_hospital_id]
            );
        }
        $message = "후기 #{$edit_id}이(가) 수정되었습니다.";
    } elseif (empty($error)) {
        $error = "후기 내용을 입력해주세요.";
    }
}


// 필터
$filter_status = $_GET['status'] ?? 'all';
$filter_hospital = intval($_GET['hospital_id'] ?? 0);
$filter_reported = isset($_GET['reported']) ? 1 : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 페이지당 행수
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if (!in_array($per_page, [10, 20, 50, 100])) $per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// WHERE 조건
$where = '1=1';
$params = [];

// editor: 본인 병원 후기로 강제 제한 (소속 없으면 아무것도 없음)
if ($is_editor_scope) {
    if ($scope_hospital_id > 0) { $where .= ' AND r.hospital_id = ?'; $params[] = $scope_hospital_id; }
    else { $where .= ' AND 1=0'; }
}

if ($filter_status === 'active') {
    $where .= ' AND r.is_active = 1';
} elseif ($filter_status === 'inactive') {
    $where .= ' AND r.is_active = 0';
} elseif ($filter_status === 'pending') {
    $where .= " AND r.review_status = 'pending'";
}

if ($filter_hospital > 0) {
    $where .= ' AND r.hospital_id = ?';
    $params[] = $filter_hospital;
}

if ($filter_reported) {
    $where .= ' AND r.id IN (SELECT DISTINCT review_id FROM review_reports WHERE status = "pending")';
}

if (!empty($search)) {
    $where .= ' AND (r.content LIKE ? OR r.user_name LIKE ? OR h.name LIKE ?)';
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// 통계 (editor 는 본인 병원 후기만 집계; 정수 기반 조건이라 인젝션 안전)
$stat_scope = $is_editor_scope ? ($scope_hospital_id > 0 ? 'hospital_id = ' . (int)$scope_hospital_id : '1=0') : '1=1';
$stats = [
    'total' => (int)$db->fetchValue("SELECT COUNT(*) FROM hospital_reviews WHERE {$stat_scope}"),
    'pending' => (int)$db->fetchValue("SELECT COUNT(*) FROM hospital_reviews WHERE review_status = 'pending' AND {$stat_scope}"),
    'active' => (int)$db->fetchValue("SELECT COUNT(*) FROM hospital_reviews WHERE is_active = 1 AND {$stat_scope}"),
    'inactive' => (int)$db->fetchValue("SELECT COUNT(*) FROM hospital_reviews WHERE is_active = 0 AND {$stat_scope}"),
    'reported' => (int)$db->fetchValue("SELECT COUNT(DISTINCT review_id) FROM review_reports WHERE status = 'pending' AND review_id IN (SELECT id FROM hospital_reviews WHERE {$stat_scope})"),
];

// 목록
$total = (int)$db->fetchValue("SELECT COUNT(*) FROM hospital_reviews r LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id WHERE {$where}", $params);
$reviews = $db->fetchAll(
    "SELECT r.*, h.name as hospital_name,
        (SELECT COUNT(*) FROM review_reports rp WHERE rp.review_id = r.id AND rp.status = 'pending') as report_count
     FROM hospital_reviews r
     LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
     WHERE {$where}
     ORDER BY r.created_at DESC
     LIMIT {$offset}, {$per_page}",
    $params
);

$total_pages = $total > 0 ? ceil($total / $per_page) : 0;

// 병원 목록 (필터용) — editor 는 본인 병원만
if ($is_editor_scope) {
    $hospitals = $scope_hospital_id > 0
        ? $db->fetchAll("SELECT id, name FROM gangnamunni_hospitals WHERE id = ?", [$scope_hospital_id])
        : [];
} else {
    $hospitals = $db->fetchAll("SELECT id, name FROM gangnamunni_hospitals ORDER BY name");
}

// URL 빌더 헬퍼 (현재 필터 유지)
function buildReviewUrl($overrides = []) {
    global $filter_status, $filter_hospital, $filter_reported, $per_page, $search;
    $params = [
        'status' => $overrides['status'] ?? $filter_status,
        'per_page' => $overrides['per_page'] ?? $per_page,
        'page' => $overrides['page'] ?? 1,
    ];
    if (!empty($overrides['hospital_id'] ?? $filter_hospital)) {
        $params['hospital_id'] = $overrides['hospital_id'] ?? $filter_hospital;
    }
    if ($overrides['reported'] ?? $filter_reported) {
        $params['reported'] = 1;
    }
    if (!empty($overrides['search'] ?? $search)) {
        $params['search'] = $overrides['search'] ?? $search;
    }
    return '?' . http_build_query($params);
}

// 공통 헤더 include
$admin_page_title = '후기 관리';
$admin_current = 'reviews';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-chat-dots"></i> 후기 관리</h5>

    <a href="admin_review_wizard.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-magic"></i> 마법사로 작성</a>
</div>

<!-- 알림 메시지 -->
<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <?php echo htmlspecialchars($message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <?php echo htmlspecialchars($error); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- 통계 카드 -->
<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-primary"><?php echo number_format($stats['total']); ?></div>
                <div class="small text-body-secondary">전체</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-success"><?php echo number_format($stats['active']); ?></div>
                <div class="small text-body-secondary">활성</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-secondary"><?php echo number_format($stats['inactive']); ?></div>
                <div class="small text-body-secondary">비활성</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card text-center">
            <div class="card-body py-3">
                <div class="fs-4 fw-bold text-danger"><?php echo number_format($stats['reported']); ?></div>
                <div class="small text-body-secondary">신고</div>
            </div>
        </div>
    </div>
</div>

<!-- 필터 -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="fw-semibold small text-body-secondary me-1">상태</span>
            <a href="<?php echo buildReviewUrl(['status' => 'all', 'reported' => 0]); ?>"
               class="btn btn-sm <?php echo ($filter_status === 'all' && !$filter_reported) ? 'btn-primary' : 'btn-outline-secondary'; ?>">전체</a>
            <a href="<?php echo buildReviewUrl(['status' => 'pending', 'reported' => 0]); ?>"
               class="btn btn-sm <?php echo $filter_status === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                승인대기
                <?php if ($stats['pending'] > 0): ?>
                <span class="badge bg-warning text-dark ms-1"><?php echo $stats['pending']; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo buildReviewUrl(['status' => 'active', 'reported' => 0]); ?>"
               class="btn btn-sm <?php echo $filter_status === 'active' ? 'btn-success' : 'btn-outline-secondary'; ?>">활성</a>
            <a href="<?php echo buildReviewUrl(['status' => 'inactive', 'reported' => 0]); ?>"
               class="btn btn-sm <?php echo $filter_status === 'inactive' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">비활성</a>
            <a href="<?php echo buildReviewUrl(['status' => 'all', 'reported' => 1]); ?>"
               class="btn btn-sm <?php echo $filter_reported ? 'btn-danger' : 'btn-outline-secondary'; ?>">
                신고있음
                <?php if ($stats['reported'] > 0): ?>
                <span class="badge bg-white text-danger ms-1"><?php echo $stats['reported']; ?></span>
                <?php endif; ?>
            </a>
            <div class="vr mx-1"></div>
            <select class="form-select form-select-sm" style="width:200px;"
                    onchange="if(this.value!=='') location.href='<?php echo buildReviewUrl(['hospital_id' => '']); ?>&hospital_id='+this.value; else location.href='<?php echo buildReviewUrl(['hospital_id' => 0]); ?>';">
                <option value="">병원 전체</option>
                <?php foreach ($hospitals as $h): ?>
                <option value="<?php echo $h['id']; ?>" <?php echo $filter_hospital == $h['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($h['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>

<!-- 툴바: 검색 + 행수 -->
<div class="admin-toolbar">
    <form method="GET" class="d-flex gap-2 search-box flex-grow-1" style="max-width:400px;">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
        <?php if ($filter_hospital > 0): ?><input type="hidden" name="hospital_id" value="<?php echo $filter_hospital; ?>"><?php endif; ?>
        <?php if ($filter_reported): ?><input type="hidden" name="reported" value="1"><?php endif; ?>
        <input type="hidden" name="per_page" value="<?php echo $per_page; ?>">
        <input type="search" name="search" class="form-control form-control-sm" placeholder="작성자, 내용, 병원명으로 검색..."
               value="<?php echo htmlspecialchars($search); ?>">
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
    <div class="d-flex align-items-center gap-2">
        <label class="text-nowrap small text-body-secondary">행수</label>
        <select class="form-select form-select-sm" style="width:80px;" onchange="changePerPage(this)">
            <?php foreach ([10, 20, 50, 100] as $n): ?>
            <option value="<?php echo $n; ?>" <?php echo $per_page == $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
            <?php endforeach; ?>
        </select>
        <span class="text-body-secondary small">총 <?php echo number_format($total); ?>건</span>
    </div>
</div>

<!-- 일괄 작업 바 -->
<div id="bulkActions" class="alert alert-info py-2 align-items-center gap-2 mb-2" style="display:none;">
    <span><strong id="selectedCount">0</strong>개 선택</span>
    <div class="d-inline-flex gap-1 ms-2">
        <form method="POST" class="d-inline" onsubmit="return bulkSubmit(this, '선택한 후기를 승인하시겠습니까?')">
            <input type="hidden" name="action" value="bulk_approve">
            <span class="bulk-ids-container"></span>
            <button class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> 승인</button>
        </form>
        <form method="POST" class="d-inline" onsubmit="return bulkSubmit(this, '선택한 후기를 거부하시겠습니까?')">
            <input type="hidden" name="action" value="bulk_reject">
            <span class="bulk-ids-container"></span>
            <button class="btn btn-warning btn-sm"><i class="bi bi-x-lg"></i> 거부</button>
        </form>
        <form method="POST" class="d-inline" onsubmit="return bulkSubmit(this, '선택한 후기를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')">
            <input type="hidden" name="action" value="bulk_delete">
            <span class="bulk-ids-container"></span>
            <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i> 삭제</button>
        </form>
    </div>
</div>

<!-- 후기 테이블 -->
<div class="table-responsive">
    <table class="table table-hover table-bordered admin-table mb-0" id="reviewTable">
        <thead class="table-light">
            <tr>
                <th style="width:40px;"><input type="checkbox" class="form-check-input" onclick="toggleSelectAll(this)"></th>
                <th style="width:60px;">ID</th>
                <th>병원</th>
                <th style="width:100px;">작성자</th>
                <th style="width:100px;">별점</th>
                <th>내용</th>
                <th style="width:70px;">상태</th>
                <th style="width:70px;">신고</th>
                <th style="width:90px;">작성일</th>
                <th style="width:130px;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reviews)): ?>
            <tr><td colspan="10" class="empty-state"><i class="bi bi-chat-dots"></i>후기가 없습니다.</td></tr>
            <?php else: ?>
            <?php foreach ($reviews as $r): ?>
            <tr>
                <td><input type="checkbox" class="form-check-input row-check" value="<?php echo $r['id']; ?>" onchange="updateBulkActions()"></td>
                <td class="text-center"><?php echo $r['id']; ?></td>
                <td><?php echo htmlspecialchars($r['hospital_name'] ?? '-'); ?></td>
                <td><?php echo htmlspecialchars($r['user_name']); ?></td>
                <td>
                    <span class="text-warning"><?php echo str_repeat('&#9733;', $r['rating']); ?></span><span class="text-body-tertiary"><?php echo str_repeat('&#9733;', 5 - $r['rating']); ?></span>
                </td>
                <td>
                    <span class="text-truncate d-inline-block" style="max-width:250px; cursor:pointer;" title="클릭하여 전체 내용 보기" onclick="viewReview(<?php echo $r['id']; ?>)">
                        <?php echo htmlspecialchars(mb_substr($r['content'], 0, 50)); ?>
                    </span>
                    <?php if (mb_strlen($r['content']) > 50): ?>
                    <a href="#" class="small text-decoration-none" onclick="viewReview(<?php echo $r['id']; ?>); return false;">more</a>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['is_active']): ?>
                    <span class="badge bg-success">활성</span>
                    <?php else: ?>
                    <span class="badge bg-danger">비활성</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($r['report_count'] > 0): ?>
                    <span class="badge bg-warning text-dark"><?php echo $r['report_count']; ?>건</span>
                    <?php else: ?>
                    <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?php echo date('Y.m.d', strtotime($r['created_at'])); ?></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-info" onclick='viewReview(<?php echo $r["id"]; ?>)' title="상세보기">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-primary" onclick='editReview(<?php echo $r["id"]; ?>)' title="편집">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if (!$r['is_active']): ?>
                        <button class="btn btn-outline-success" onclick="adminAction(<?php echo $r['id']; ?>, 'approve')" title="승인">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <?php else: ?>
                        <button class="btn btn-outline-warning" onclick="adminAction(<?php echo $r['id']; ?>, 'reject')" title="거부">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 페이지네이션 -->
<?php if ($total_pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php if ($page > 1): ?>
        <li class="page-item">
            <a class="page-link" href="<?php echo buildReviewUrl(['page' => $page - 1]); ?>">&laquo;</a>
        </li>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 4);
        $end = min($total_pages, $page + 4);
        if ($start > 1): ?>
        <li class="page-item"><a class="page-link" href="<?php echo buildReviewUrl(['page' => 1]); ?>">1</a></li>
        <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="<?php echo buildReviewUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($end < $total_pages): ?>
        <?php if ($end < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?php echo buildReviewUrl(['page' => $total_pages]); ?>"><?php echo $total_pages; ?></a></li>
        <?php endif; ?>
        <?php if ($page < $total_pages): ?>
        <li class="page-item">
            <a class="page-link" href="<?php echo buildReviewUrl(['page' => $page + 1]); ?>">&raquo;</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- 상세보기 모달 -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-chat-dots"></i> 후기 상세</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <span class="fw-semibold small text-body-secondary">병원</span>
                    <div id="view_hospital"></div>
                </div>
                <div class="mb-2">
                    <span class="fw-semibold small text-body-secondary">작성자</span>
                    <div id="view_user"></div>
                </div>
                <div class="mb-2">
                    <span class="fw-semibold small text-body-secondary">별점</span>
                    <div id="view_rating" class="text-warning"></div>
                </div>
                <div class="mb-2">
                    <span class="fw-semibold small text-body-secondary">내용</span>
                    <div id="view_content" class="border rounded p-2 bg-body-tertiary" style="white-space:pre-wrap;"></div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <span class="fw-semibold small text-body-secondary">상태</span>
                        <div id="view_status"></div>
                    </div>
                    <div class="col-6">
                        <span class="fw-semibold small text-body-secondary">작성일</span>
                        <div id="view_date"></div>
                    </div>
                </div>
            </div>
            <!-- AI 검수 결과 영역 (#2) -->
            <div id="aiVerifyResult" class="mx-3 mb-2" style="display:none;">
                <div class="card border-info">
                    <div class="card-header py-1 bg-info bg-opacity-10 small fw-bold"><i class="bi bi-robot me-1"></i>AI 검수 결과</div>
                    <div class="card-body py-2 small">
                        <div class="row g-2">
                            <div class="col-4"><span class="text-muted">점수:</span> <strong id="ai_score">-</strong>/100</div>
                            <div class="col-4"><span class="text-muted">감성:</span> <span id="ai_sentiment">-</span></div>
                            <div class="col-4"><span class="text-muted">권장:</span> <span id="ai_recommendation" class="badge">-</span></div>
                        </div>
                        <div class="mt-1"><span class="text-muted">사유:</span> <span id="ai_reason">-</span></div>
                        <div id="ai_issues" class="mt-1" style="display:none;"><span class="text-muted">문제점:</span> <span id="ai_issues_text">-</span></div>
                        <div class="mt-1"><small class="text-muted">검수 방법: <span id="ai_method">-</span></small></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-info" id="aiVerifyBtn" onclick="aiVerifyReview()"><i class="bi bi-robot me-1"></i>AI 검수</button>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-sm btn-success" id="view_approve_btn" onclick="modalAction('approve')">승인</button>
                <button type="button" class="btn btn-sm btn-warning" id="view_reject_btn" onclick="modalAction('reject')">거부</button>
            </div>
        </div>
    </div>
</div>

<!-- 편집 모달 -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit_review">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="modal-header py-2">
                    <h6 class="modal-title"><i class="bi bi-pencil"></i> 후기 편집</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">병원</label>
                            <select class="form-select form-select-sm" name="edit_hospital_id" id="edit_hospital_id">
                                <option value="0">- 선택 -</option>
                                <?php foreach ($hospitals as $h): ?>
                                <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">작성자</label>
                            <input type="text" class="form-control form-control-sm" name="edit_user_name" id="edit_user_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">별점</label>
                            <div id="edit_stars" class="d-flex gap-1">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning edit-star-btn" data-rating="<?php echo $s; ?>" onclick="setEditRating(<?php echo $s; ?>)">&#9733;</button>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="edit_rating" id="edit_rating" value="5">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">상태</label>
                            <select class="form-select form-select-sm" name="edit_is_active" id="edit_is_active">
                                <option value="1">활성</option>
                                <option value="0">비활성</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">제목</label>
                            <input type="text" class="form-control form-control-sm" name="edit_title" id="edit_title" maxlength="200" placeholder="제목 (선택)">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">내용</label>
                            <textarea class="form-control" name="edit_content" id="edit_content" rows="6" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-check-lg"></i> 저장</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// 후기 데이터를 JS에서 사용할 수 있도록 JSON으로 전달
$reviewsJson = json_encode($reviews, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$admin_extra_js = <<<JSBLOCK
// 후기 데이터 캐시
var reviewData = {$reviewsJson};

// 후기 상세보기
function viewReview(reviewId) {
    var review = reviewData.find(function(r) { return r.id == reviewId; });
    if (!review) return;

    document.getElementById('view_hospital').textContent = review.hospital_name || '-';
    document.getElementById('view_user').textContent = review.user_name || '-';

    var stars = '';
    for (var i = 0; i < 5; i++) {
        stars += i < review.rating ? '\u2605' : '\u2606';
    }
    document.getElementById('view_rating').textContent = stars;
    document.getElementById('view_content').textContent = review.content || '';

    if (review.is_active == 1) {
        document.getElementById('view_status').innerHTML = '<span class="badge bg-success">활성</span>';
        document.getElementById('view_approve_btn').style.display = 'none';
        document.getElementById('view_reject_btn').style.display = '';
    } else {
        document.getElementById('view_status').innerHTML = '<span class="badge bg-danger">비활성</span>';
        document.getElementById('view_approve_btn').style.display = '';
        document.getElementById('view_reject_btn').style.display = 'none';
    }

    document.getElementById('view_date').textContent = review.created_at ? review.created_at.substring(0, 10) : '-';

    // 모달에 review ID 저장
    document.getElementById('viewModal').dataset.reviewId = reviewId;

    // AI 검수 결과 초기화
    document.getElementById('aiVerifyResult').style.display = 'none';

    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

// AI 후기 검수 (#2)
function aiVerifyReview() {
    var reviewId = document.getElementById('viewModal').dataset.reviewId;
    if (!reviewId) return;

    var btn = document.getElementById('aiVerifyBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>검수 중...';

    var fd = new FormData();
    fd.append('action', 'ai_verify');
    fd.append('review_id', reviewId);

    fetch('', {method: 'POST', body: fd})
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                alert('검수 실패: ' + data.error);
                return;
            }
            document.getElementById('ai_score').textContent = data.score;
            var sentimentMap = {positive: '긍정적', neutral: '중립', negative: '부정적'};
            document.getElementById('ai_sentiment').textContent = sentimentMap[data.sentiment] || data.sentiment;

            var recEl = document.getElementById('ai_recommendation');
            var recMap = {approve: ['승인 권장', 'bg-success'], reject: ['거부 권장', 'bg-danger'], review: ['재검토', 'bg-warning text-dark']};
            var rec = recMap[data.recommendation] || ['알 수 없음', 'bg-secondary'];
            recEl.textContent = rec[0];
            recEl.className = 'badge ' + rec[1];

            document.getElementById('ai_reason').textContent = data.reason || '-';

            if (data.issues && data.issues.length > 0) {
                document.getElementById('ai_issues').style.display = '';
                document.getElementById('ai_issues_text').textContent = data.issues.join(', ');
            } else {
                document.getElementById('ai_issues').style.display = 'none';
            }
            document.getElementById('ai_method').textContent = data.method === 'ai' ? 'AI API' : '룰 기반 (AI 미연동시)';
            document.getElementById('aiVerifyResult').style.display = '';
        })
        .catch(function(e) { alert('검수 오류: ' + e.message); })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-robot me-1"></i>AI 검수';
        });
}

// 편집 모달 열기
function editReview(reviewId) {
    var review = reviewData.find(function(r) { return r.id == reviewId; });
    if (!review) return;
    
    document.getElementById('edit_id').value = review.id;
    document.getElementById('edit_hospital_id').value = review.hospital_id || 0;
    document.getElementById('edit_user_name').value = review.user_name || '';
    document.getElementById('edit_title').value = review.title || '';
    document.getElementById('edit_content').value = review.content || '';
    document.getElementById('edit_is_active').value = review.is_active ? '1' : '0';
    setEditRating(review.rating || 5);
    
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// 별점 설정
function setEditRating(rating) {
    document.getElementById('edit_rating').value = rating;
    document.querySelectorAll('.edit-star-btn').forEach(function(btn) {
        var r = parseInt(btn.dataset.rating);
        btn.classList.toggle('btn-warning', r <= rating);
        btn.classList.toggle('btn-outline-warning', r > rating);
    });
}

// 모달에서 승인/거부
function modalAction(action) {
    var reviewId = document.getElementById('viewModal').dataset.reviewId;
    if (reviewId) adminAction(parseInt(reviewId), action);
}

// 후기 승인/거부 API 호출
// 개별 승인/거부 — 페이지로 POST 제출(강남언니 관리자 세션 인증 사용).
// 기존 api/admin/approve_review.php 는 그누보드 회원세션 기준이라 관리자 세션과 불일치했음.
function adminAction(reviewId, action) {
    var msg = action === 'approve' ? '이 후기를 승인하시겠습니까?' : '이 후기를 거부하시겠습니까?';
    if (!confirm(msg)) return;

    var f = document.createElement('form');
    f.method = 'POST';
    f.action = '';   // 현재 URL(필터 쿼리 유지)로 제출
    f.innerHTML =
        '<input type="hidden" name="action" value="' + (action === 'approve' ? 'approve_one' : 'reject_one') + '">' +
        '<input type="hidden" name="review_id" value="' + parseInt(reviewId, 10) + '">';
    document.body.appendChild(f);
    f.submit();
}

// 일괄 작업 폼 제출 시 선택된 ID 추가
function bulkSubmit(form, confirmMsg) {
    if (!confirm(confirmMsg)) return false;
    var container = form.querySelector('.bulk-ids-container');
    container.innerHTML = '';
    document.querySelectorAll('.row-check:checked').forEach(function(cb) {
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'ids[]';
        inp.value = cb.value;
        container.appendChild(inp);
    });
    return true;
}
JSBLOCK;

include __DIR__ . '/includes/admin_footer.php';
?>
