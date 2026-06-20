<?php
/**
 * ============================================================================
 * [관리자 #13 - 2026-05-28] 회원/환자 분리 + 환자 관리 페이지
 * ============================================================================
 * 목적 : 회원(g5_member) vs 환자(HMS patients) 구분 명확화 + 환자 전용 관리 UI
 * 신규 : 본 파일 (admin_patients.php)
 *        - 기존 admin_members.php는 회원(사이트 가입자) 관리 전용으로 유지
 *        - 본 페이지는 HMS patients 테이블 (병원 예약/진료 환자) 전용
 * 영향 범위 : 신규 페이지만 추가. 기존 admin_members.php 영향 없음.
 * 차이점 안내 :
 *   - 회원(g5_member) : 서울미녀 사이트/앱 가입자 (로그인용)
 *   - 환자(patients)  : HMS 시스템 진료 환자 (실제 진료 기록 보유)
 *   - 회원은 예약 시 자동으로 환자로 등록되지만, 환자만 있고 회원 없을 수도 있음 (오프라인 등록)
 * ============================================================================
 */
session_start();
$admin_page_title = '환자 관리 (HMS)';
$admin_current = 'patients';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('members');

// 페이지네이션
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 30;
$offset = ($page - 1) * $per_page;
$search = trim($_GET['search'] ?? '');

$where = '1=1';
if (!empty($search)) {
    $search_esc = mysqli_real_escape_string($connect, $search);
    $where .= " AND (p.first_name LIKE '%{$search_esc}%' OR p.last_name LIKE '%{$search_esc}%' OR p.email LIKE '%{$search_esc}%' OR p.contact LIKE '%{$search_esc}%')";
}

// HMS patients 테이블 조회 (존재 확인)
$patients = [];
$total_count = 0;
$hms_check = mysqli_query($connect, "SHOW TABLES LIKE 'patients'");
if ($hms_check && mysqli_num_rows($hms_check) > 0) {
    $tc_q = mysqli_query($connect, "SELECT COUNT(*) c FROM patients p WHERE {$where}");
    if ($tc_q) $total_count = (int)mysqli_fetch_assoc($tc_q)['c'];
    $res = mysqli_query($connect, "SELECT p.* FROM patients p WHERE {$where} ORDER BY p.id DESC LIMIT {$offset}, {$per_page}");
    if ($res) while ($r = mysqli_fetch_assoc($res)) $patients[] = $r;
}

$total_pages = max(1, (int)ceil($total_count / $per_page));

include __DIR__ . '/includes/admin_header.php';

?>
<!-- [추가 2026-06-19] 조회 전용 안내 (인수인계) -->
<div class="alert alert-info d-flex align-items-start gap-2 mb-3">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div class="small"><strong>조회 전용 페이지:</strong> 이 화면은 HMS(병원 본 시스템) 데이터를 <u>조회만</u> 합니다. 추가·수정·삭제는 HMS 본체에서 처리되며, 강남언니 관리자에서는 변경할 수 없습니다. (설계상 의도된 동작)</div>
</div>
<?php
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-person-badge-fill text-info"></i> 환자 관리 (HMS)</h4>
        <a href="admin_members.php" class="btn btn-sm btn-outline-secondary">회원 관리로 이동</a>
    </div>

    <div class="alert alert-info py-2 small">
        <strong>💡 회원 vs 환자 차이점</strong><br>
        ▸ <strong>회원</strong> (g5_member) : 서울미녀 사이트/앱 가입자 (로그인 계정 보유) → <a href="admin_members.php">admin_members.php</a><br>
        ▸ <strong>환자</strong> (HMS patients) : 병원 진료 환자 (예약/진료 기록 보유, 로그인 계정 없을 수도 있음) → 본 페이지<br>
        ▸ <strong>연결</strong> : 회원이 예약 시 자동으로 환자로 등록됨. 환자만 있고 회원 없는 경우는 오프라인 등록 케이스.
    </div>

    <!-- 검색 -->
    <form method="GET" class="mb-3">
        <div class="input-group">
            <input type="text" name="search" class="form-control" placeholder="이름 / 이메일 / 연락처 검색" value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary">검색</button>
        </div>
    </form>

    <p class="small text-muted">총 <strong><?= $total_count ?></strong>명의 환자</p>

    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead class="table-light">
                <tr><th>ID</th><th>이름</th><th>이메일</th><th>연락처</th><th>생년월일</th><th>등록일</th></tr>
            </thead>
            <tbody>
                <?php foreach ($patients as $p): ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><?= htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')) ?></td>
                    <td class="small"><?= htmlspecialchars($p['email'] ?? '') ?></td>
                    <td class="small"><?= htmlspecialchars($p['contact'] ?? '') ?></td>
                    <td class="small"><?= htmlspecialchars($p['dob'] ?? '-') ?></td>
                    <td class="small"><?= $p['created_at'] ?? '-' ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($patients)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">검색 결과가 없습니다.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 페이지네이션 -->
    <?php if ($total_pages > 1): ?>
    <nav><ul class="pagination justify-content-center">
        <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul></nav>
    <?php endif; ?>
</div>
<?php
mysqli_close($connect);
include __DIR__ . '/includes/admin_footer.php';
?>
