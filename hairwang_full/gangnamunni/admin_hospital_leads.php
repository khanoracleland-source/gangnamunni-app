<?php
/**
 * 입점 신청(상담 요청) 관리 — admin_hospital_leads.php
 * about-apply.php 에서 접수된 gangnamunni_hospital_leads 목록/상태/메모 관리.
 *
 * 신청자 연락처가 담긴 플랫폼 전역 데이터이므로 병원 계정은 접근할 수 없다.
 * (정식 입점 신청 = hospital_apply_public.php → gangnamunni_hospitals → admin_hospitals.php)
 */
session_start();

$admin_page_title = '입점 신청 관리';
$admin_current = 'hospital_leads';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
require_admin_login();
require_platform_admin_page(); // 신청자 연락처는 전역 데이터 — 병원 계정 차단
require_permission('hospital_apply');

if (empty($_SESSION['lead_admin_csrf'])) $_SESSION['lead_admin_csrf'] = bin2hex(random_bytes(32));
$lead_csrf = $_SESSION['lead_admin_csrf'];

$STATUS_LABELS = ['new' => '신규', 'contacting' => '상담중', 'done' => '완료', 'hold' => '보류'];
$STATUS_BADGE  = ['new' => 'danger', 'contacting' => 'warning text-dark', 'done' => 'success', 'hold' => 'secondary'];

$msg = '';
$msg_type = 'success';

// ===== POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($lead_csrf, (string)$_POST['csrf_token'])) {
        http_response_code(400);
        exit('잘못된 요청입니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.');
    }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'update' && $id > 0) {
        $status = $_POST['status'] ?? 'new';
        if (!isset($STATUS_LABELS[$status])) $status = 'new';
        $admin_memo = mb_substr(trim((string)($_POST['admin_memo'] ?? '')), 0, 2000);

        if ($stmt = mysqli_prepare($connect, "UPDATE gangnamunni_hospital_leads SET status = ?, admin_memo = ? WHERE id = ?")) {
            mysqli_stmt_bind_param($stmt, 'ssi', $status, $admin_memo, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header('Location: admin_hospital_leads.php?' . http_build_query(array_filter([
                'status' => $_POST['back_status'] ?? '',
                'q'      => $_POST['back_q'] ?? '',
                'msg'    => '저장되었습니다.',
            ])));
            exit;
        }
        $msg = '저장에 실패했습니다.';
        $msg_type = 'danger';
    }

    // 삭제는 최고관리자만
    if ($action === 'delete' && $id > 0) {
        if (!is_super_admin()) {
            http_response_code(403);
            exit('삭제는 최고관리자만 할 수 있습니다.');
        }
        if ($stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_hospital_leads WHERE id = ?")) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            header('Location: admin_hospital_leads.php?msg=' . urlencode('삭제되었습니다.'));
            exit;
        }
    }
}

if (isset($_GET['msg'])) $msg = (string)$_GET['msg'];

// ===== 목록 =====
$filter_status = (string)($_GET['status'] ?? '');
if (!isset($STATUS_LABELS[$filter_status])) $filter_status = '';
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
$types = '';
if ($filter_status !== '') { $where[] = 'status = ?'; $params[] = $filter_status; $types .= 's'; }
if ($q !== '') {
    $where[] = '(hospital_name LIKE ? OR manager LIKE ? OR phone LIKE ? OR email LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}
$where_sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$total = 0;
if ($stmt = mysqli_prepare($connect, "SELECT COUNT(*) FROM gangnamunni_hospital_leads" . $where_sql)) {
    if ($types !== '') mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}
$total_pages = max(1, (int)ceil($total / $per_page));

$leads = [];
$sql = "SELECT * FROM gangnamunni_hospital_leads" . $where_sql . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
if ($stmt = mysqli_prepare($connect, $sql)) {
    $p = $params; $t = $types . 'ii';
    array_push($p, $per_page, $offset);
    mysqli_stmt_bind_param($stmt, $t, ...$p);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $leads[] = $row;
    mysqli_stmt_close($stmt);
}

// 상태별 건수 (탭 배지 — 검색/필터와 무관한 전체 기준)
$counts = ['' => 0];
if ($res = mysqli_query($connect, "SELECT status, COUNT(*) AS c FROM gangnamunni_hospital_leads GROUP BY status")) {
    while ($r = mysqli_fetch_assoc($res)) {
        $counts[$r['status']] = (int)$r['c'];
        $counts[''] += (int)$r['c'];
    }
}

function lead_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

require_once __DIR__ . '/includes/admin_header.php';
?>
<div class="container-fluid px-3 px-lg-4 py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1">입점 신청 관리</h4>
            <p class="text-muted small mb-0">about-apply.php 입점 신청서로 접수된 상담 요청이에요.</p>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="about-apply.php" target="_blank" rel="noopener">신청 페이지 보기</a>
    </div>

    <?php if ($msg !== ''): ?>
        <div class="alert alert-<?= lead_h($msg_type) ?> py-2"><?= lead_h($msg) ?></div>
    <?php endif; ?>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="btn-group btn-group-sm" role="group">
            <?php
            $tabs = ['' => '전체'] + $STATUS_LABELS;
            foreach ($tabs as $key => $label):
                $qs = http_build_query(array_filter(['status' => $key, 'q' => $q]));
                $active = ($filter_status === $key) ? ' active' : '';
            ?>
                <a class="btn btn-outline-primary<?= $active ?>" href="admin_hospital_leads.php<?= $qs ? '?' . $qs : '' ?>">
                    <?= lead_h($label) ?> <span class="badge bg-light text-dark"><?= (int)($counts[$key] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <form class="d-flex gap-2 ms-auto" method="get" action="admin_hospital_leads.php">
            <?php if ($filter_status !== ''): ?><input type="hidden" name="status" value="<?= lead_h($filter_status) ?>"><?php endif; ?>
            <input class="form-control form-control-sm" type="search" name="q" value="<?= lead_h($q) ?>" placeholder="병원명 · 담당자 · 연락처 · 이메일" style="min-width:220px">
            <button class="btn btn-sm btn-primary" type="submit">검색</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">번호</th>
                    <th>병원</th>
                    <th style="width:120px">진료과목</th>
                    <th>담당자 · 연락처</th>
                    <th style="width:100px">상태</th>
                    <th style="width:150px">접수일</th>
                    <th style="width:90px"></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$leads): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">접수된 신청이 없습니다.</td></tr>
            <?php else: foreach ($leads as $lead): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$lead['id'] ?></td>
                    <td>
                        <div class="fw-semibold"><?= lead_h($lead['hospital_name']) ?></div>
                        <div class="text-muted small"><?= lead_h($lead['sido']) ?> <?= lead_h($lead['address_detail']) ?></div>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= lead_h($lead['department']) ?></span></td>
                    <td>
                        <div><?= lead_h($lead['manager']) ?></div>
                        <div class="small">
                            <a href="tel:<?= lead_h(preg_replace('/[^0-9]/', '', $lead['phone'])) ?>"><?= lead_h($lead['phone']) ?></a>
                            <?php if (!empty($lead['email'])): ?>
                                · <a href="mailto:<?= lead_h($lead['email']) ?>"><?= lead_h($lead['email']) ?></a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><span class="badge bg-<?= lead_h($STATUS_BADGE[$lead['status']] ?? 'secondary') ?>"><?= lead_h($STATUS_LABELS[$lead['status']] ?? $lead['status']) ?></span></td>
                    <td class="small text-muted"><?= lead_h(date('Y-m-d H:i', strtotime($lead['created_at']))) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#lead-<?= (int)$lead['id'] ?>">상세</button>
                    </td>
                </tr>
                <tr class="collapse" id="lead-<?= (int)$lead['id'] ?>">
                    <td colspan="7" class="bg-light">
                        <div class="row g-3 py-2">
                            <div class="col-lg-6">
                                <div class="small text-muted mb-1">문의 내용</div>
                                <div class="border rounded bg-white p-2" style="white-space:pre-wrap; min-height:80px"><?= lead_h($lead['memo'] !== '' ? $lead['memo'] : '—') ?></div>
                                <div class="small text-muted mt-2">
                                    동의 시각: <?= lead_h($lead['privacy_agreed_at']) ?> · IP: <?= lead_h($lead['client_ip']) ?>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <form method="post" action="admin_hospital_leads.php" class="d-flex flex-column gap-2">
                                    <input type="hidden" name="csrf_token" value="<?= lead_h($lead_csrf) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= (int)$lead['id'] ?>">
                                    <input type="hidden" name="back_status" value="<?= lead_h($filter_status) ?>">
                                    <input type="hidden" name="back_q" value="<?= lead_h($q) ?>">
                                    <div>
                                        <label class="small text-muted mb-1" for="st-<?= (int)$lead['id'] ?>">처리 상태</label>
                                        <select class="form-select form-select-sm" id="st-<?= (int)$lead['id'] ?>" name="status">
                                            <?php foreach ($STATUS_LABELS as $key => $label): ?>
                                                <option value="<?= lead_h($key) ?>"<?= $lead['status'] === $key ? ' selected' : '' ?>><?= lead_h($label) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="small text-muted mb-1" for="am-<?= (int)$lead['id'] ?>">관리자 메모</label>
                                        <textarea class="form-control form-control-sm" id="am-<?= (int)$lead['id'] ?>" name="admin_memo" rows="3" maxlength="2000" placeholder="상담 내용, 다음 할 일 등"><?= lead_h($lead['admin_memo']) ?></textarea>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-primary" type="submit">저장</button>
                                        <?php if (is_super_admin()): ?>
                                            <button class="btn btn-sm btn-outline-danger ms-auto" type="submit" name="action" value="delete"
                                                    onclick="return confirm('이 신청을 삭제할까요? 되돌릴 수 없습니다.');">삭제</button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center">
                <?php for ($i = 1; $i <= $total_pages; $i++):
                    $qs = http_build_query(array_filter(['status' => $filter_status, 'q' => $q, 'page' => $i])); ?>
                    <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                        <a class="page-link" href="admin_hospital_leads.php?<?= $qs ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
