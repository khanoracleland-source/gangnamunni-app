<?php
/**
 * 관리자 — 경과 후기 검수 (#포인트제도 v2)
 * 작성 완료(submitted) → 승인(적립: 시술/수술, 월 3회 합산 한도) / 반려(사유, 작성일로부터 30일 내 수정 가능)
 */
session_start();
$admin_page_title = '경과 후기 검수';
$admin_current = 'reviews';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) { mysqli_set_charset($connect, 'utf8mb4'); mysqli_query($connect, "SET time_zone = '+09:00'"); }
}
if (!$connect) die('DB 연결 실패');
loadThemeSettings($connect);
require_admin_login();
require_permission('reviews');
require_once __DIR__ . '/includes/points_policy_engine.php';
require_once __DIR__ . '/includes/photo_upload_helper.php';

if (empty($_SESSION['admin_progress_csrf'])) $_SESSION['admin_progress_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['admin_progress_csrf'];
function apr_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$schema_ok = points_v2_schema_ready($connect);
$message = ''; $error = '';

if ($schema_ok && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['admin_csrf'] ?? ''))) { http_response_code(403); exit('요청이 만료되었습니다.'); }
    $pid = (int)($_POST['progress_id'] ?? 0);
    $act = (string)($_POST['action'] ?? '');
    $st = mysqli_prepare($connect, "SELECT * FROM gangnamunni_progress_reviews WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $pid);
    mysqli_stmt_execute($st);
    $pr = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    $admin_name = (string)($_SESSION['ad_admin_username'] ?? 'admin');
    if (!$pr) $error = '경과 후기를 찾을 수 없습니다.';
    elseif ($act === 'approve') {
        if ($pr['status'] !== 'submitted') $error = '검수 대기 상태가 아닙니다.';
        else {
            $award = points_v2_progress_award($connect, $pr);
            $u = mysqli_prepare($connect, "UPDATE gangnamunni_progress_reviews SET status='approved', reviewed_at=NOW(), reviewed_by=?, awarded_point=? WHERE id=? AND status='submitted'");
            mysqli_stmt_bind_param($u, 'sii', $admin_name, $award['amount'], $pid);
            mysqli_stmt_execute($u);
            mysqli_stmt_close($u);
            $message = "경과 후기 #{$pid} 승인" . ($award['amount'] > 0 ? ' (' . number_format($award['amount']) . 'P 적립)' : ($award['message'] ? ' — 적립 제외: ' . $award['message'] : ''));
        }
    } elseif ($act === 'reject') {
        $reason = trim(mb_substr((string)($_POST['reject_reason'] ?? ''), 0, 500));
        if ($reason === '') $error = '반려 사유를 입력해주세요.';
        elseif (!in_array($pr['status'], ['submitted', 'approved'], true)) $error = '반려할 수 없는 상태입니다.';
        else {
            if ($pr['status'] === 'approved') points_revoke_earn($connect, 'progress', $pid, '경과 후기 반려 회수');
            $u = mysqli_prepare($connect, "UPDATE gangnamunni_progress_reviews SET status='rejected', reviewed_at=NOW(), reviewed_by=?, reject_reason=? WHERE id=?");
            mysqli_stmt_bind_param($u, 'ssi', $admin_name, $reason, $pid);
            mysqli_stmt_execute($u);
            mysqli_stmt_close($u);
            $message = "경과 후기 #{$pid} 반려";
        }
    }
}

$filter = in_array($_GET['status'] ?? '', ['writing','submitted','approved','rejected','deleted'], true) ? $_GET['status'] : 'submitted';
$rows = []; $counts = [];
if ($schema_ok) {
    $r = mysqli_query($connect, "SELECT status, COUNT(*) c FROM gangnamunni_progress_reviews GROUP BY status");
    while ($r && ($x = mysqli_fetch_assoc($r))) $counts[$x['status']] = (int)$x['c'];
    $st = mysqli_prepare($connect, "SELECT p.*, h.name AS hospital_name, m.mb_nick,
            (SELECT COUNT(*) FROM gangnamunni_progress_entries e WHERE e.progress_id = p.id) AS entry_count
        FROM gangnamunni_progress_reviews p
        LEFT JOIN gangnamunni_hospitals h ON h.id = p.hospital_id
        LEFT JOIN g5_member m ON m.mb_no = p.member_id
        WHERE p.status = ? ORDER BY COALESCE(p.submitted_at, p.created_at) ASC LIMIT 200");
    mysqli_stmt_bind_param($st, 's', $filter);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    while ($x = mysqli_fetch_assoc($res)) $rows[] = $x;
    mysqli_stmt_close($st);
}
$entries = [];
if ($rows) {
    $ids = implode(',', array_map(fn($x) => (int)$x['id'], $rows));
    $r = mysqli_query($connect, "SELECT * FROM gangnamunni_progress_entries WHERE progress_id IN ($ids) ORDER BY entry_date, id");
    while ($r && ($x = mysqli_fetch_assoc($r))) $entries[(int)$x['progress_id']][] = $x;
}
$labels = ['submitted' => '검수 대기', 'approved' => '승인', 'rejected' => '반려', 'writing' => '작성 중', 'deleted' => '삭제'];

require_once __DIR__ . '/includes/admin_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">경과 후기 검수</h4>
    <a class="btn btn-outline-secondary btn-sm" href="admin_reviews.php"><i class="bi bi-arrow-left"></i> 후기 관리</a>
</div>
<?php if (!$schema_ok): ?>
<div class="alert alert-warning">포인트 제도 v2 스키마가 설치되지 않았습니다. <code>sql/points_v2.sql</code> 실행 후 이용할 수 있습니다.</div>
<?php else: ?>
<?php if (!points_v2_on($connect, 'v2_progress_enabled')): ?><div class="alert alert-secondary py-2 small">경과 후기 기능이 꺼져 있어 사용자 화면에는 노출되지 않습니다(관리자 &gt; 포인트 관리 &gt; 제도 v2).</div><?php endif; ?>
<?php if (!points_v2_on($connect, 'v2_policy_enabled')): ?><div class="alert alert-secondary py-2 small">v2 적립 규칙이 꺼져 있어 승인해도 포인트가 적립되지 않습니다.</div><?php endif; ?>
<?php if ($message): ?><div class="alert alert-success"><?php echo apr_h($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo apr_h($error); ?></div><?php endif; ?>

<ul class="nav nav-tabs mb-3">
<?php foreach ($labels as $k => $lb): ?>
    <li class="nav-item"><a class="nav-link<?php echo $k === $filter ? ' active' : ''; ?>" href="?status=<?php echo $k; ?>"><?php echo $lb; ?> <span class="badge bg-secondary"><?php echo (int)($counts[$k] ?? 0); ?></span></a></li>
<?php endforeach; ?>
</ul>

<?php if (empty($rows)): ?><div class="text-body-secondary py-5 text-center">해당 상태의 경과 후기가 없습니다.</div><?php endif; ?>
<?php foreach ($rows as $p):
    $need = points_v2_progress_required_entries($connect, $p['kind']); ?>
<div class="card mb-3"><div class="card-body">
    <div class="d-flex justify-content-between flex-wrap gap-2">
        <div>
            <span class="badge <?php echo $p['kind'] === 'surgery' ? 'bg-danger' : 'bg-primary'; ?>"><?php echo $p['kind'] === 'surgery' ? '수술' : '시술'; ?></span>
            <b class="ms-1">#<?php echo (int)$p['id']; ?> <?php echo apr_h($p['title']); ?></b>
            <div class="small text-body-secondary mt-1">
                <?php echo apr_h(($p['mb_nick'] ?: $p['mb_id']) . ' (mb_no ' . $p['member_id'] . ')'); ?> ·
                <?php echo apr_h(($p['hospital_name'] ?: '병원 미지정') . ' · ' . $p['treatment_name']); ?> ·
                시술일 <?php echo apr_h($p['procedure_date'] ?: '-'); ?> ·
                경과 <b class="<?php echo (int)$p['entry_count'] >= $need ? 'text-success' : 'text-danger'; ?>"><?php echo (int)$p['entry_count']; ?>/<?php echo $need; ?></b>회 ·
                작성 완료 <?php echo apr_h($p['submitted_at'] ?: '-'); ?>
                <?php if ($p['status'] === 'approved'): ?> · 적립 <?php echo number_format((int)$p['awarded_point']); ?>P<?php endif; ?>
                <?php if ($p['status'] === 'rejected'): ?> · 반려: <?php echo apr_h($p['reject_reason']); ?><?php endif; ?>
            </div>
        </div>
        <?php if (in_array($p['status'], ['submitted', 'approved'], true)): ?>
        <div class="d-flex gap-1 align-items-start">
            <?php if ($p['status'] === 'submitted'): ?>
            <form method="post" onsubmit="return confirm('승인하고 포인트를 적립할까요?')">
                <input type="hidden" name="admin_csrf" value="<?php echo apr_h($csrf); ?>"><input type="hidden" name="progress_id" value="<?php echo (int)$p['id']; ?>">
                <button class="btn btn-success btn-sm" name="action" value="approve"><i class="bi bi-check-lg"></i> 승인</button>
            </form>
            <?php endif; ?>
            <form method="post" class="d-flex gap-1" onsubmit="return this.reject_reason.value.trim()!=='' && confirm('반려할까요?<?php echo $p['status'] === 'approved' ? ' 적립된 포인트는 회수됩니다.' : ''; ?>')">
                <input type="hidden" name="admin_csrf" value="<?php echo apr_h($csrf); ?>"><input type="hidden" name="progress_id" value="<?php echo (int)$p['id']; ?>">
                <select name="reject_reason" class="form-select form-select-sm" style="width:auto">
                    <option value="">반려 사유 선택</option>
                    <option>경과별 변화 확인이 어렵거나 무의미한 내용</option>
                    <option>후기 내용의 상당 부분이 유사하거나 동일</option>
                    <option>시술·수술과 무관한 이미지 또는 이미지 없음</option>
                    <option>선정성 등 운영 정책 위반</option>
                    <option>한국 국적·한국어 작성 기준 미충족</option>
                </select>
                <button class="btn btn-outline-warning btn-sm" name="action" value="reject"><i class="bi bi-x-lg"></i> 반려</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <details class="mt-2"><summary class="small">경과 기록 보기 (<?php echo count($entries[(int)$p['id']] ?? []); ?>)</summary>
    <?php foreach (($entries[(int)$p['id']] ?? []) as $i => $e): ?>
        <div class="border-top pt-2 mt-2 small">
            <b><?php echo ($i + 1); ?>회차</b> · <?php echo apr_h($e['entry_date']); ?>
            <div class="mt-1" style="white-space:pre-wrap"><?php echo apr_h($e['content']); ?></div>
            <?php foreach (get_photo_urls($e['photo_urls'] ?? '') as $u): ?><a href="<?php echo apr_h($u); ?>" target="_blank" rel="noopener"><img src="<?php echo apr_h($u); ?>" style="width:72px;height:72px;object-fit:cover" class="me-1 mt-1 border"></a><?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    </details>
</div></div>
<?php endforeach; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
