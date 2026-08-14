<?php
/**
 * 후기 관리 마법사
 * AI 검수 큐 + 승인/거부 + 일괄처리 + 통계
 */
session_start();

// 후기 검수 기능은 admin_reviews.php로 통합되어 이 페이지는 임시 비활성화한다.
header('Location: admin_reviews.php');
exit;

$admin_page_title = '후기 검수';
$admin_current = 'review_wizard';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('reviews');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

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

// AJAX 처리
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['ajax_action'];

    try {
        if ($action === 'load_reviews') {
            $filter = $_POST['filter'] ?? 'pending';
            $page = max(1, intval($_POST['page'] ?? 1));
            $per_page = max(10, min(100, intval($_POST['per_page'] ?? 20)));
            $search = trim($_POST['search'] ?? '');
            $offset = ($page - 1) * $per_page;

            $where = "1=1";
            $params = [];

            // editor: 본인 병원 후기로 제한 (소속 없으면 아무것도 없음)
            if ($is_editor_scope) {
                if ($scope_hospital_id > 0) { $where .= " AND r.hospital_id = ?"; $params[] = $scope_hospital_id; }
                else { $where .= " AND 1=0"; }
            }

            if ($filter === 'pending') { $where .= " AND r.review_status = 'pending'"; }
            elseif ($filter === 'published') { $where .= " AND r.review_status = 'published' AND r.is_active = 1"; }
            elseif ($filter === 'rejected') { $where .= " AND r.review_status = 'rejected'"; }
            elseif ($filter === 'low_score') { $where .= " AND r.ai_score IS NOT NULL AND r.ai_score < 70"; }
            elseif ($filter === 'all') { /* 전체 */ }

            if (!empty($search)) {
                $where .= " AND (r.title LIKE ? OR r.content LIKE ? OR r.user_name LIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }

            $total = intval($db->fetchValue("SELECT COUNT(*) FROM hospital_reviews r WHERE {$where}", $params));

            $sql = "SELECT r.*, h.name as hospital_name,
                    (SELECT COUNT(*) FROM review_images WHERE review_id = r.id) as image_count,
                    (SELECT GROUP_CONCAT(image_url ORDER BY image_order LIMIT 3) FROM review_images WHERE review_id = r.id) as thumb_urls
                    FROM hospital_reviews r
                    LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id
                    WHERE {$where}
                    ORDER BY r.created_at DESC
                    LIMIT {$per_page} OFFSET {$offset}";
            $reviews = $db->fetchAll($sql, $params);

            // AI 로그 추가
            foreach ($reviews as &$rv) {
                $rv['thumb_list'] = $rv['thumb_urls'] ? explode(',', $rv['thumb_urls']) : [];
                try {
                    $log = $db->fetchOne("SELECT score, passed, messages FROM review_ai_logs WHERE review_id = ? ORDER BY checked_at DESC LIMIT 1", [$rv['id']]);
                    $rv['ai_log'] = $log ? ['score' => $log['score'], 'passed' => $log['passed'], 'messages' => json_decode($log['messages'], true)] : null;
                } catch (Exception $e) {
                    $rv['ai_log'] = null;
                }
            }

            echo json_encode(['success' => true, 'reviews' => $reviews, 'total' => $total, 'page' => $page, 'per_page' => $per_page, 'pages' => ceil($total / $per_page)], JSON_UNESCAPED_UNICODE);

        } elseif ($action === 'approve') {
            $id = intval($_POST['review_id'] ?? 0);
            if ($id <= 0) throw new Exception('review_id 필요');
            $review = $db->fetchOne("SELECT hospital_id FROM hospital_reviews WHERE id = ?", [$id]);
            if (!$review) throw new Exception('후기 없음');
            if ($is_editor_scope && (int)$review['hospital_id'] !== $scope_hospital_id) throw new Exception('접근 권한이 없는 후기입니다.');
            $db->execute("UPDATE hospital_reviews SET is_active = 1, review_status = 'published', updated_at = NOW() WHERE id = ?", [$id]);
            $hid = $review['hospital_id'];
            $db->execute("UPDATE gangnamunni_hospitals SET review_count = (SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1), rating = (SELECT COALESCE(AVG(rating),0) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1) WHERE id = ?", [$hid, $hid, $hid]);
            echo json_encode(['success' => true, 'message' => '승인 완료']);

        } elseif ($action === 'reject') {
            $id = intval($_POST['review_id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            if ($id <= 0) throw new Exception('review_id 필요');
            if (!review_in_scope($db, $id, $is_editor_scope, $scope_hospital_id)) throw new Exception('접근 권한이 없는 후기입니다.');
            $review = $db->fetchOne("SELECT hospital_id, is_active FROM hospital_reviews WHERE id = ?", [$id]);
            $db->execute("UPDATE hospital_reviews SET is_active = 0, review_status = 'published', updated_at = NOW() WHERE id = ?", [$id]);
            if (!empty($review['is_active'])) {
                $hid = (int)$review['hospital_id'];
                $db->execute("UPDATE gangnamunni_hospitals SET review_count=(SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id=? AND is_active=1), rating=(SELECT COALESCE(AVG(LEAST(5, GREATEST(1, rating))),0) FROM hospital_reviews WHERE hospital_id=? AND is_active=1) WHERE id=?", [$hid, $hid, $hid]);
            }
            echo json_encode(['success' => true, 'message' => '거부 완료']);

        } elseif ($action === 'recheck') {
            $id = intval($_POST['review_id'] ?? 0);
            if ($id <= 0) throw new Exception('review_id 필요');
            if (!review_in_scope($db, $id, $is_editor_scope, $scope_hospital_id)) throw new Exception('접근 권한이 없는 후기입니다.');
            $review = $db->fetchOne("SELECT content, title, rating FROM hospital_reviews WHERE id = ?", [$id]);
            if (!$review) throw new Exception('후기 없음');
            $ai = performAICheck($review['content'], $review['title'], $review['rating'], []);
            $db->execute("UPDATE hospital_reviews SET ai_score = ?, updated_at = NOW() WHERE id = ?", [$ai['score'], $id]);
            try {
                $db->execute("INSERT INTO review_ai_logs (review_id, score, passed, messages, checked_at) VALUES (?, ?, ?, ?, NOW())",
                    [$id, $ai['score'], $ai['passed'] ? 1 : 0, json_encode($ai['messages'], JSON_UNESCAPED_UNICODE)]);
            } catch (Exception $e) {}
            echo json_encode(['success' => true, 'ai_check' => $ai]);

        } elseif ($action === 'bulk_action') {
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            $bulk = $_POST['bulk_type'] ?? '';
            if (empty($ids) || !is_array($ids)) throw new Exception('선택된 후기 없음');
            $ids = array_values(array_map('intval', $ids));
            // editor: 본인 병원 후기만 대상으로 필터 (타 병원 id 는 제거)
            if ($is_editor_scope) {
                if ($scope_hospital_id <= 0) throw new Exception('접근 권한이 없습니다.');
                $ph_chk = implode(',', array_fill(0, count($ids), '?'));
                $owned = $db->fetchAll("SELECT id FROM hospital_reviews WHERE id IN ({$ph_chk}) AND hospital_id = ?", array_merge($ids, [$scope_hospital_id]));
                $ids = array_map(function ($r) { return (int)$r['id']; }, $owned);
                if (empty($ids)) throw new Exception('접근 권한이 있는 후기가 없습니다.');
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            if ($bulk === 'approve') {
                $db->execute("UPDATE hospital_reviews SET is_active = 1, review_status = 'published', updated_at = NOW() WHERE id IN ({$placeholders})", $ids);
                // 병원 통계 업데이트
                $hospitals = $db->fetchAll("SELECT DISTINCT hospital_id FROM hospital_reviews WHERE id IN ({$placeholders})", $ids);
                foreach ($hospitals as $h) {
                    $hid = $h['hospital_id'];
                    $db->execute("UPDATE gangnamunni_hospitals SET review_count = (SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1), rating = (SELECT COALESCE(AVG(rating),0) FROM hospital_reviews WHERE hospital_id = ? AND is_active = 1) WHERE id = ?", [$hid, $hid, $hid]);
                }
                echo json_encode(['success' => true, 'message' => count($ids) . '건 승인']);
            } elseif ($bulk === 'reject') {
                $db->execute("UPDATE hospital_reviews SET is_active = 0, review_status = 'published', updated_at = NOW() WHERE id IN ({$placeholders})", $ids);
                echo json_encode(['success' => true, 'message' => count($ids) . '건 거부']);
            } elseif ($bulk === 'delete') {
                $db->execute("DELETE FROM review_images WHERE review_id IN ({$placeholders})", $ids);
                $db->execute("DELETE FROM hospital_reviews WHERE id IN ({$placeholders})", $ids);
                echo json_encode(['success' => true, 'message' => count($ids) . '건 삭제']);
            } else {
                throw new Exception('알 수 없는 일괄 작업');
            }

        } elseif ($action === 'get_detail') {
            $id = intval($_POST['review_id'] ?? 0);
            $review = $db->fetchOne("SELECT r.*, h.name as hospital_name FROM hospital_reviews r LEFT JOIN gangnamunni_hospitals h ON r.hospital_id = h.id WHERE r.id = ?", [$id]);
            if (!$review) throw new Exception('후기 없음');
            if ($is_editor_scope && (int)$review['hospital_id'] !== $scope_hospital_id) throw new Exception('접근 권한이 없는 후기입니다.');
            $images = $db->fetchAll("SELECT * FROM review_images WHERE review_id = ? ORDER BY image_order", [$id]);
            $review['images'] = $images;
            try {
                $logs = $db->fetchAll("SELECT * FROM review_ai_logs WHERE review_id = ? ORDER BY checked_at DESC LIMIT 5", [$id]);
                foreach ($logs as &$l) { $l['messages'] = json_decode($l['messages'], true); }
                $review['ai_logs'] = $logs;
            } catch (Exception $e) { $review['ai_logs'] = []; }
            echo json_encode(['success' => true, 'review' => $review], JSON_UNESCAPED_UNICODE);

        } else {
            throw new Exception('알 수 없는 액션');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * AI 검수
 */
function performAICheck($content, $title, $rating, $images) {
    $score = 100; $messages = []; $passed = true;
    $len = mb_strlen(strip_tags($content));
    if ($len < 20) { $score -= 30; $messages[] = '내용 짧음'; }
    elseif ($len < 50) { $score -= 10; $messages[] = '내용 짧음 (권장 50자+)'; }
    $spam_kw = ['카톡','텔레그램','telegram','010-','광고','홍보','할인코드','무료체험','선착순','http://','bit.ly','클릭'];
    $spam = 0;
    foreach ($spam_kw as $kw) { if (mb_stripos($content.' '.$title, $kw) !== false) $spam++; }
    if ($spam >= 3) { $score -= 50; $messages[] = '스팸 감지'; $passed = false; }
    elseif ($spam >= 1) { $score -= 15; $messages[] = '광고성 표현'; }
    $bad = ['시발','씨발','ㅅㅂ','지랄','병신','ㅂㅅ','개새끼'];
    foreach ($bad as $bw) {
        if (mb_stripos($content.' '.$title, $bw) !== false) { $score -= 40; $messages[] = '비속어'; $passed = false; break; }
    }
    if (preg_match('/(.)\1{9,}/u', $content)) { $score -= 20; $messages[] = '반복 문자'; }
    $score = max(0, min(100, $score));
    if ($score < 70) $passed = false;
    if ($passed && empty($messages)) $messages[] = '통과';
    return ['score' => $score, 'passed' => $passed, 'messages' => $messages];
}

require_once __DIR__ . '/includes/admin_header.php';

?>
<!-- [추가 2026-06-19] 중복 안내 (인수인계) -->
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div class="small"><strong>중복 안내:</strong> 후기 승인/거절은 <strong>후기 관리(admin_reviews.php)</strong>에서도 가능합니다. 이 마법사는 review_status, 후기 관리는 is_active 컬럼을 쓰므로 <strong>한 곳에서 일관되게 처리</strong>하세요. (상태 이원화 주의)</div>
</div>
<?php
?>

<style>
.review-card { background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: 12px; padding: 16px; margin-bottom: 12px; transition: all 0.2s; }
.review-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.review-card.selected { border-color: #AE1932; background: #faf5ff; }
.review-meta { font-size: 12px; color: #999; display: flex; gap: 12px; flex-wrap: wrap; }
.review-content-preview { font-size: 14px; color: var(--bs-body-color); margin: 8px 0; max-height: 60px; overflow: hidden; }
.ai-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.ai-badge.pass { background: #f0fdf4; color: #166534; }
.ai-badge.warn { background: var(--bs-warning-bg-subtle, #fffbeb); color: var(--bs-warning-text-emphasis, #92400e); }
.ai-badge.fail { background: #fef2f2; color: #991b1b; }
.status-badge { padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.status-pending { background: #fef3c7; color: #92400e; }
.status-published { background: #d1fae5; color: #065f46; }
.status-rejected { background: #fee2e2; color: #991b1b; }
.review-thumbs { display: flex; gap: 4px; margin-top: 8px; }
.review-thumbs img { width: 48px; height: 48px; border-radius: 6px; object-fit: cover; }
.filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
.filter-tab { padding: 6px 16px; border: 1px solid var(--bs-border-color); border-radius: 20px; background: var(--bs-body-bg); cursor: pointer; font-size: 13px; transition: all 0.2s; }
.filter-tab.active { background: #AE1932; color: #fff; border-color: #AE1932; }
.filter-tab .count { background: rgba(0,0,0,0.1); padding: 1px 6px; border-radius: 10px; font-size: 11px; margin-left: 4px; }
.filter-tab.active .count { background: rgba(255,255,255,0.3); }
.bulk-bar { display: none; padding: 12px 16px; background: #AE1932; color: #fff; border-radius: 8px; margin-bottom: 12px; align-items: center; gap: 12px; }
.bulk-bar.show { display: flex; }
.bulk-bar button { background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: #fff; padding: 4px 14px; border-radius: 6px; cursor: pointer; font-size: 13px; }
.bulk-bar button:hover { background: rgba(255,255,255,0.3); }
.review-actions { display: flex; gap: 6px; }
.review-actions button { padding: 4px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; border: 1px solid; transition: all 0.2s; }
.btn-approve { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
.btn-approve:hover { background: #a7f3d0; }
.btn-reject { background: #fee2e2; color: #991b1b; border-color: #fecaca; }
.btn-reject:hover { background: #fecaca; }
.btn-recheck { background: #e0e7ff; color: #3730a3; border-color: #c7d2fe; }
.btn-recheck:hover { background: #c7d2fe; }
.btn-detail { background: #f3f4f6; color: #374151; border-color: #e5e7eb; }
.btn-detail:hover { background: #e5e7eb; }
.detail-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 1050; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
.detail-modal.show { display: flex; }
.detail-panel { background: var(--bs-body-bg); border-radius: 16px; max-width: 700px; width: 95%; max-height: 90vh; overflow-y: auto; padding: 24px; }
.rating-bars .bar-item { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; font-size: 13px; }
.rating-bars .bar-label { width: 80px; color: #666; }
.rating-bars .bar-track { flex: 1; height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden; }
.rating-bars .bar-fill { height: 100%; background: #fbbf24; border-radius: 4px; transition: width 0.3s; }
.rating-bars .bar-value { width: 24px; text-align: right; font-weight: 600; color: var(--bs-body-color); }
.pagination-bar { display: flex; justify-content: center; gap: 4px; margin-top: 16px; }
.pagination-bar button { width: 32px; height: 32px; border: 1px solid var(--bs-border-color); border-radius: 6px; background: var(--bs-body-bg); cursor: pointer; font-size: 13px; }
.pagination-bar button.active { background: #AE1932; color: #fff; border-color: #AE1932; }
</style>

<div class="container-fluid p-4">
    <!-- 검색 -->
    <div class="d-flex gap-3 mb-3 flex-wrap align-items-center">
        <input type="text" class="form-control" style="max-width: 300px;" id="searchInput" placeholder="검색 (제목, 내용, 작성자)">
        <button class="btn btn-outline-primary btn-sm" onclick="loadReviews(1)">검색</button>
        <div class="ms-auto">
            <select class="form-select form-select-sm" id="perPage" onchange="loadReviews(1)" style="width: auto;">
                <option value="10">10건</option>
                <option value="20" selected>20건</option>
                <option value="50">50건</option>
                <option value="100">100건</option>
            </select>
        </div>
    </div>

    <!-- 필터 탭 -->
    <div class="filter-tabs" id="filterTabs">
        <button class="filter-tab active" data-filter="pending" onclick="setFilter(this)">검수 대기 <span class="count" id="countPending">0</span></button>
        <button class="filter-tab" data-filter="published" onclick="setFilter(this)">게시됨 <span class="count" id="countPublished">0</span></button>
        <button class="filter-tab" data-filter="rejected" onclick="setFilter(this)">거부됨 <span class="count" id="countRejected">0</span></button>
        <button class="filter-tab" data-filter="low_score" onclick="setFilter(this)">AI 주의 <span class="count" id="countLowScore">0</span></button>
        <button class="filter-tab" data-filter="all" onclick="setFilter(this)">전체 <span class="count" id="countAll">0</span></button>
    </div>

    <!-- 일괄 처리 바 -->
    <div class="bulk-bar" id="bulkBar">
        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this.checked)">
        <span id="selectedCount">0</span>건 선택
        <button onclick="bulkAction('approve')">일괄 승인</button>
        <button onclick="bulkAction('reject')">일괄 거부</button>
        <button onclick="bulkAction('delete')" style="background: rgba(239,68,68,0.3);">일괄 삭제</button>
    </div>

    <!-- 리뷰 목록 -->
    <div id="reviewList"></div>
    <div class="pagination-bar" id="pagination"></div>
</div>

<!-- 상세 모달 -->
<div class="detail-modal" id="detailModal">
    <div class="detail-panel" id="detailPanel"></div>
</div>

<script>
var currentFilter = 'pending';
var currentPage = 1;
var selectedIds = [];

document.addEventListener('DOMContentLoaded', function() {
    loadCounts();
    loadReviews(1);
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') loadReviews(1);
    });
});

function loadCounts() {
    ['pending','published','rejected','low_score','all'].forEach(function(f) {
        var fd = new FormData();
        fd.append('ajax_action', 'load_reviews');
        fd.append('filter', f);
        fd.append('per_page', '1');
        fd.append('page', '1');
        fetch(window.location.href, { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var el = document.getElementById('count' + f.charAt(0).toUpperCase() + f.slice(1).replace(/_(\w)/g, function(m,c) { return c.toUpperCase(); }));
                    if (el) el.textContent = data.total;
                }
            });
    });
}

function setFilter(btn) {
    document.querySelectorAll('.filter-tab').forEach(function(t) { t.classList.remove('active'); });
    btn.classList.add('active');
    currentFilter = btn.dataset.filter;
    selectedIds = [];
    updateBulkBar();
    loadReviews(1);
}

function loadReviews(page) {
    currentPage = page;
    var fd = new FormData();
    fd.append('ajax_action', 'load_reviews');
    fd.append('filter', currentFilter);
    fd.append('page', page);
    fd.append('per_page', document.getElementById('perPage').value);
    fd.append('search', document.getElementById('searchInput').value);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { document.getElementById('reviewList').innerHTML = '<p class="text-muted text-center py-4">' + data.message + '</p>'; return; }
            renderReviews(data.reviews);
            renderPagination(data.page, data.pages, data.total);
        });
}

function renderReviews(reviews) {
    var container = document.getElementById('reviewList');
    if (!reviews.length) { container.innerHTML = '<p class="text-muted text-center py-4">후기가 없습니다.</p>'; return; }
    var html = '';
    reviews.forEach(function(r) {
        var aiClass = r.ai_score >= 70 ? 'pass' : (r.ai_score >= 40 ? 'warn' : 'fail');
        var statusClass = r.review_status === 'published' ? 'status-published' : (r.review_status === 'rejected' ? 'status-rejected' : 'status-pending');
        var statusText = r.review_status === 'published' ? '게시됨' : (r.review_status === 'rejected' ? '거부됨' : '대기중');
        var checked = selectedIds.indexOf(r.id) >= 0 ? 'checked' : '';

        html += '<div class="review-card" id="card_' + r.id + '">';
        html += '<div class="d-flex align-items-start gap-3">';
        html += '<input type="checkbox" class="form-check-input mt-1" value="' + r.id + '" ' + checked + ' onchange="toggleSelect(' + r.id + ', this.checked)">';
        html += '<div class="flex-grow-1 min-w-0">';
        html += '<div class="d-flex justify-content-between align-items-center mb-1">';
        html += '<strong class="text-truncate">' + (r.title || '(제목 없음)') + '</strong>';
        html += '<span class="status-badge ' + statusClass + '">' + statusText + '</span>';
        html += '</div>';
        html += '<div class="review-meta">';
        html += '<span>' + (r.user_name || '익명') + '</span>';
        html += '<span>' + (r.hospital_name || '') + '</span>';
        if (r.ai_score !== null) html += '<span class="ai-badge ' + aiClass + '">AI ' + r.ai_score + '점</span>';
        html += '<span>' + (r.rating ? r.rating + '점' : '') + '</span>';
        html += '<span>' + (r.created_at || '') + '</span>';
        html += '</div>';
        html += '<div class="review-content-preview">' + (r.content || '').substring(0, 150) + '</div>';
        if (r.thumb_list && r.thumb_list.length) {
            html += '<div class="review-thumbs">';
            r.thumb_list.forEach(function(url) { html += '<img src="' + url + '" alt="">'; });
            html += '</div>';
        }
        html += '<div class="review-actions mt-2">';
        if (r.review_status !== 'published') html += '<button class="btn-approve" onclick="approveReview(' + r.id + ')">승인</button>';
        if (r.review_status !== 'rejected') html += '<button class="btn-reject" onclick="rejectReview(' + r.id + ')">거부</button>';
        html += '<button class="btn-recheck" onclick="recheckReview(' + r.id + ')">AI 재검수</button>';
        html += '<button class="btn-detail" onclick="showDetail(' + r.id + ')">상세</button>';
        html += '</div>';
        html += '</div></div></div>';
    });
    container.innerHTML = html;
}

function renderPagination(page, pages, total) {
    var el = document.getElementById('pagination');
    if (pages <= 1) { el.innerHTML = ''; return; }
    var html = '';
    var start = Math.max(1, page - 3);
    var end = Math.min(pages, page + 3);
    if (page > 1) html += '<button onclick="loadReviews(' + (page-1) + ')">&lt;</button>';
    for (var i = start; i <= end; i++) {
        html += '<button class="' + (i===page?'active':'') + '" onclick="loadReviews(' + i + ')">' + i + '</button>';
    }
    if (page < pages) html += '<button onclick="loadReviews(' + (page+1) + ')">&gt;</button>';
    el.innerHTML = html + '<small class="ms-3 text-muted align-self-center">' + total + '건</small>';
}

function toggleSelect(id, checked) {
    if (checked) { if (selectedIds.indexOf(id) < 0) selectedIds.push(id); }
    else { selectedIds = selectedIds.filter(function(x) { return x !== id; }); }
    var card = document.getElementById('card_' + id);
    if (card) card.classList.toggle('selected', checked);
    updateBulkBar();
}

function toggleSelectAll(checked) {
    document.querySelectorAll('.review-card input[type="checkbox"]').forEach(function(cb) {
        cb.checked = checked;
        var id = parseInt(cb.value);
        toggleSelect(id, checked);
    });
}

function updateBulkBar() {
    var bar = document.getElementById('bulkBar');
    bar.classList.toggle('show', selectedIds.length > 0);
    document.getElementById('selectedCount').textContent = selectedIds.length;
}

function approveReview(id) {
    if (!confirm('이 후기를 승인하시겠습니까?')) return;
    var fd = new FormData();
    fd.append('ajax_action', 'approve');
    fd.append('review_id', id);
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { loadReviews(currentPage); loadCounts(); }
            else alert(data.message);
        });
}

function rejectReview(id) {
    var reason = prompt('거부 사유 (선택):');
    if (reason === null) return;
    var fd = new FormData();
    fd.append('ajax_action', 'reject');
    fd.append('review_id', id);
    fd.append('reason', reason);
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { loadReviews(currentPage); loadCounts(); }
            else alert(data.message);
        });
}

function recheckReview(id) {
    var fd = new FormData();
    fd.append('ajax_action', 'recheck');
    fd.append('review_id', id);
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var check = data.ai_check;
                alert('AI 점수: ' + check.score + '점 (' + (check.passed ? '통과' : '주의') + ')\n' + check.messages.join('\n'));
                loadReviews(currentPage);
            } else alert(data.message);
        });
}

function bulkAction(type) {
    if (!selectedIds.length) return;
    var label = type === 'approve' ? '승인' : (type === 'reject' ? '거부' : '삭제');
    if (!confirm(selectedIds.length + '건을 ' + label + '하시겠습니까?')) return;
    var fd = new FormData();
    fd.append('ajax_action', 'bulk_action');
    fd.append('bulk_type', type);
    fd.append('ids', JSON.stringify(selectedIds));
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { selectedIds = []; updateBulkBar(); loadReviews(currentPage); loadCounts(); }
            alert(data.message);
        });
}

function showDetail(id) {
    var fd = new FormData();
    fd.append('ajax_action', 'get_detail');
    fd.append('review_id', id);
    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { alert(data.message); return; }
            var r = data.review;
            var html = '<div class="d-flex justify-content-between align-items-center mb-3">';
            html += '<h4 class="mb-0">후기 상세 #' + r.id + '</h4>';
            html += '<button class="btn-close" onclick="closeDetail()"></button>';
            html += '</div>';
            html += '<div class="mb-3"><strong>' + (r.title || '(제목 없음)') + '</strong></div>';
            html += '<div class="review-meta mb-2"><span>작성자: ' + (r.user_name||'익명') + '</span><span>병원: ' + (r.hospital_name||'') + '</span><span>작성일: ' + (r.created_at||'') + '</span></div>';
            // 다축 별점
            var axes = [{k:'rating_result',l:'시술결과'},{k:'rating_consultation',l:'상담'},{k:'rating_facility',l:'시설'},{k:'rating_wait',l:'대기'},{k:'rating_cost',l:'가격'},{k:'rating_recovery',l:'회복'}];
            html += '<div class="rating-bars mb-3">';
            axes.forEach(function(ax) {
                var val = parseInt(r[ax.k]) || 0;
                html += '<div class="bar-item"><span class="bar-label">' + ax.l + '</span><div class="bar-track"><div class="bar-fill" style="width:' + (val*20) + '%"></div></div><span class="bar-value">' + val + '</span></div>';
            });
            html += '<div class="bar-item"><span class="bar-label fw-bold">종합</span><div class="bar-track"><div class="bar-fill" style="width:' + ((r.rating||0)*20) + '%;background:#AE1932"></div></div><span class="bar-value fw-bold">' + (r.rating||0) + '</span></div>';
            html += '</div>';
            // 시술 정보
            if (r.doctor_name || r.procedure_date || r.procedure_cost) {
                html += '<div class="mb-3 p-2 bg-light rounded">';
                if (r.doctor_name) html += '<span class="me-3">의사: ' + r.doctor_name + '</span>';
                if (r.procedure_date) html += '<span class="me-3">시술일: ' + r.procedure_date + '</span>';
                if (r.procedure_cost) html += '<span>비용: ' + parseInt(r.procedure_cost).toLocaleString() + '원</span>';
                html += '</div>';
            }
            html += '<div class="mb-3" style="font-size:14px;line-height:1.8;">' + (r.editor_content || r.content || '') + '</div>';
            // 이미지
            if (r.images && r.images.length) {
                html += '<div class="d-flex flex-wrap gap-2 mb-3">';
                r.images.forEach(function(img) {
                    var badge = img.photo_category === 'before' ? 'Before' : (img.photo_category === 'after' ? 'After' : '');
                    html += '<div class="position-relative"><img src="' + img.image_url + '" style="width:80px;height:80px;object-fit:cover;border-radius:8px;cursor:pointer;" onclick="window.open(this.src)">';
                    if (badge) html += '<span class="position-absolute bottom-0 start-0 px-1" style="font-size:9px;background:rgba(0,0,0,0.6);color:#fff;border-radius:0 0 0 8px;">' + badge + '</span>';
                    html += '</div>';
                });
                html += '</div>';
            }
            // AI 로그
            if (r.ai_logs && r.ai_logs.length) {
                html += '<h6>AI 검수 이력</h6><table class="table table-sm table-bordered"><thead><tr><th>점수</th><th>결과</th><th>메시지</th><th>일시</th></tr></thead><tbody>';
                r.ai_logs.forEach(function(log) {
                    var cls = log.passed == 1 ? 'text-success' : 'text-danger';
                    html += '<tr><td class="' + cls + ' fw-bold">' + log.score + '</td><td>' + (log.passed == 1 ? '통과' : '주의') + '</td><td>' + (log.messages||[]).join(', ') + '</td><td>' + (log.checked_at||'') + '</td></tr>';
                });
                html += '</tbody></table>';
            }
            // 액션
            html += '<div class="d-flex gap-2 mt-3">';
            if (r.review_status !== 'published') html += '<button class="btn btn-success btn-sm" onclick="approveReview(' + r.id + ');closeDetail();">승인</button>';
            if (r.review_status !== 'rejected') html += '<button class="btn btn-danger btn-sm" onclick="rejectReview(' + r.id + ');closeDetail();">거부</button>';
            html += '<button class="btn btn-outline-primary btn-sm" onclick="recheckReview(' + r.id + ')">AI 재검수</button>';
            html += '<button class="btn btn-outline-secondary btn-sm ms-auto" onclick="closeDetail()">닫기</button>';
            html += '</div>';
            document.getElementById('detailPanel').innerHTML = html;
            document.getElementById('detailModal').classList.add('show');
        });
}

function closeDetail() { document.getElementById('detailModal').classList.remove('show'); }
document.getElementById('detailModal').addEventListener('click', function(e) { if (e.target === this) closeDetail(); });
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
