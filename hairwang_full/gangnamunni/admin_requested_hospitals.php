<?php
/**
 * 입점 신청 병원 심사 — admin_requested_hospitals.php
 *
 * hospital_apply_public.php 로 접수된 병원(gangnamunni_hospitals.status = 'pending')을
 * 서류와 함께 확인하고 승인·반려한다.
 * 승인된 병원만 admin_hospitals.php 목록에 나타난다.
 *
 * 승인·반려 처리 자체는 admin_hospitals.php 의 action=approve / action=reject 가 담당한다.
 * (계정 발급 provision_hospital_accounts, 반려 메일, 관리자 알림이 모두 거기에 있어 로직을 한 곳에 둔다.)
 * 이 페이지는 return_to 를 함께 보내 처리 후 다시 돌아온다.
 *
 * @created 2026-10-13
 */
session_start();

$admin_page_title = '입점 신청 병원';
$admin_current = 'requested_hospitals';

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
require_platform_admin_page(); // 병원 계정 차단 (전역 데이터)
// 입점 심사는 최고관리자만. 승인 시 병원 계정이 발급되고 플랫폼에 병원이 추가되므로
// 다른 플랫폼 관리자(admin/editor/viewer)에게도 열지 않는다.
// 'hospital_request_review' 는 어떤 역할 목록에도 없는 키 → check_permission 은 super_admin 에게만 true.
// (사이드바 메뉴도 이 키로 걸러져 최고관리자에게만 보인다)
require_permission('hospital_request_review');

if (!is_super_admin()) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="ko"><meta charset="UTF-8"><title>권한 없음</title>';
    echo '<p style="font-family:sans-serif;text-align:center;padding:80px 20px">';
    echo '입점 신청 심사는 최고관리자만 할 수 있습니다.<br><a href="dashboard.php">대시보드로 돌아가기</a></p></html>';
    exit;
}

// admin_hospitals.php 의 POST 핸들러(승인·반려)로 보내므로 같은 세션 토큰을 쓴다.
if (empty($_SESSION['admin_hospitals_csrf'])) {
    $_SESSION['admin_hospitals_csrf'] = bin2hex(random_bytes(32));
}
$admin_hospitals_csrf = $_SESSION['admin_hospitals_csrf'];

$msg = (string)($_GET['msg'] ?? '');
$err = (string)($_GET['err'] ?? '');

// pending(대기) / rejected(반려) 만 다룬다. 승인된 병원은 admin_hospitals.php.
$filter_status = ($_GET['status'] ?? 'pending') === 'rejected' ? 'rejected' : 'pending';
$q = trim((string)($_GET['q'] ?? ''));

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = 'status = ?';
$params = [$filter_status];
$types = 's';
if ($q !== '') {
    $where .= ' AND (name LIKE ? OR contact_name LIKE ? OR phone LIKE ? OR email LIKE ? OR address LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

$total = 0;
if ($stmt = mysqli_prepare($connect, "SELECT COUNT(*) FROM gangnamunni_hospitals WHERE $where")) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $total);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}
$total_pages = max(1, (int)ceil($total / $per_page));

$rows = [];
if ($stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_hospitals WHERE $where ORDER BY applied_at DESC, id DESC LIMIT ? OFFSET ?")) {
    $p = $params; $t = $types . 'ii';
    array_push($p, $per_page, $offset);
    mysqli_stmt_bind_param($stmt, $t, ...$p);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    mysqli_stmt_close($stmt);
}

$counts = ['pending' => 0, 'rejected' => 0, 'approved' => 0];
if ($res = mysqli_query($connect, "SELECT status, COUNT(*) AS c FROM gangnamunni_hospitals GROUP BY status")) {
    while ($r = mysqli_fetch_assoc($res)) {
        if (isset($counts[$r['status']])) $counts[$r['status']] = (int)$r['c'];
    }
}

function req_h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

require_once __DIR__ . '/includes/admin_header.php';
?>
<div class="container-fluid px-3 px-lg-4 py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h4 class="mb-1"><i class="bi bi-inbox me-1"></i>입점 신청 병원</h4>
            <p class="text-muted small mb-0">서류를 확인하고 승인하면 <strong>병원 관리</strong> 목록에 등록돼요. 승인 전에는 앱에도 노출되지 않아요.</p>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="admin_hospitals.php">
            승인된 병원 <span class="badge bg-success"><?= number_format($counts['approved']) ?></span>
        </a>
    </div>

    <?php if ($msg !== ''): ?><div class="alert alert-success py-2"><?= req_h($msg) ?></div><?php endif; ?>
    <?php if ($err !== ''): ?><div class="alert alert-danger py-2"><?= req_h($err) ?></div><?php endif; ?>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="btn-group btn-group-sm" role="group">
            <a class="btn btn-outline-primary<?= $filter_status === 'pending' ? ' active' : '' ?>"
               href="admin_requested_hospitals.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>">
                대기 <span class="badge bg-warning text-dark"><?= number_format($counts['pending']) ?></span>
            </a>
            <a class="btn btn-outline-primary<?= $filter_status === 'rejected' ? ' active' : '' ?>"
               href="admin_requested_hospitals.php?<?= http_build_query(array_filter(['status' => 'rejected', 'q' => $q])) ?>">
                반려 <span class="badge bg-danger"><?= number_format($counts['rejected']) ?></span>
            </a>
        </div>
        <form class="d-flex gap-2 ms-auto" method="get" action="admin_requested_hospitals.php">
            <?php if ($filter_status === 'rejected'): ?><input type="hidden" name="status" value="rejected"><?php endif; ?>
            <input class="form-control form-control-sm" type="search" name="q" value="<?= req_h($q) ?>" placeholder="병원명 · 담당자 · 연락처 · 이메일 · 주소" style="min-width:230px">
            <button class="btn btn-sm btn-primary" type="submit">검색</button>
        </form>
    </div>

    <?php if (!$rows): ?>
        <div class="card"><div class="card-body text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
            <?= $filter_status === 'pending' ? '심사 대기 중인 신청이 없습니다.' : '반려한 신청이 없습니다.' ?>
        </div></div>
    <?php else: ?>
        <?php foreach ($rows as $h):
            $hid = (int)$h['id'];
            $docs = [
                ['label' => '사업자등록증', 'doc' => 'business', 'path' => trim((string)($h['cert_business'] ?? ''))],
                ['label' => '의료기관 개설신고증명서', 'doc' => 'medical', 'path' => trim((string)($h['cert_medical'] ?? ''))],
            ];
        ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                    <div>
                        <h5 class="mb-1">
                            <?= req_h($h['name']) ?>
                            <span class="badge bg-<?= $filter_status === 'pending' ? 'warning text-dark' : 'danger' ?> align-middle">
                                <?= $filter_status === 'pending' ? '대기' : '반려' ?>
                            </span>
                        </h5>
                        <div class="text-muted small">
                            #<?= $hid ?> · 신청일 <?= req_h(!empty($h['applied_at']) ? date('Y-m-d H:i', strtotime($h['applied_at'])) : '-') ?>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#req-<?= $hid ?>">
                        상세 보기
                    </button>
                </div>

                <div class="row g-2 small">
                    <div class="col-md-4"><span class="text-muted">담당자</span> <?= req_h($h['contact_name'] ?: '-') ?></div>
                    <div class="col-md-4"><span class="text-muted">연락처</span>
                        <a href="tel:<?= req_h(preg_replace('/[^0-9]/', '', (string)$h['phone'])) ?>"><?= req_h($h['phone'] ?: '-') ?></a>
                    </div>
                    <div class="col-md-4"><span class="text-muted">이메일</span>
                        <?php if (!empty($h['email'])): ?><a href="mailto:<?= req_h($h['email']) ?>"><?= req_h($h['email']) ?></a><?php else: ?>-<?php endif; ?>
                    </div>
                    <div class="col-12"><span class="text-muted">주소</span> <?= req_h($h['address'] ?: '-') ?></div>
                </div>

                <div class="collapse mt-3" id="req-<?= $hid ?>">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="border rounded p-3 h-100">
                                <div class="fw-semibold mb-2"><i class="bi bi-file-earmark-check me-1"></i>제출 서류</div>
                                <?php foreach ($docs as $doc): ?>
                                    <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                                        <span class="small"><?= req_h($doc['label']) ?></span>
                                        <?php if ($doc['path'] !== ''): ?>
                                            <a class="btn btn-sm btn-outline-primary"
                                               href="<?= req_h('hospital_document.php?' . http_build_query(['hospital_id' => $hid, 'doc' => $doc['doc']])) ?>"
                                               target="_blank" rel="noopener">
                                                <i class="bi bi-box-arrow-up-right me-1"></i>열기
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">미제출</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <div class="small text-muted mt-2">
                                    사업자번호 <?= req_h($h['business_number'] ?: '-') ?> ·
                                    대표자 <?= req_h($h['owner_name'] ?: '-') ?>
                                </div>
                                <?php if (!empty($h['categories'])): ?>
                                    <div class="small mt-2"><span class="text-muted">진료과목</span> <?= req_h($h['categories']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($h['description'])): ?>
                                    <div class="small mt-2" style="white-space:pre-wrap"><span class="text-muted d-block">소개</span><?= req_h($h['description']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <?php if ($filter_status === 'pending'): ?>
                                <div class="border rounded p-3 h-100 d-flex flex-column gap-3">
                                    <div>
                                        <div class="fw-semibold mb-2 text-success"><i class="bi bi-check-circle me-1"></i>승인</div>
                                        <p class="small text-muted mb-2">승인하면 병원 계정이 발급되고 <strong>병원 관리</strong> 목록과 앱에 노출돼요.</p>
                                        <form method="post" action="admin_hospitals.php"
                                              onsubmit="return confirm('<?= req_h($h['name']) ?> 을(를) 승인할까요? 병원 계정이 발급됩니다.');">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="hospital_id" value="<?= $hid ?>">
                                            <input type="hidden" name="admin_csrf" value="<?= req_h($admin_hospitals_csrf) ?>">
                                            <input type="hidden" name="return_to" value="admin_requested_hospitals.php">
                                            <input type="hidden" name="return_status" value="<?= req_h($filter_status) ?>">
                                            <input type="hidden" name="return_q" value="<?= req_h($q) ?>">
                                            <button class="btn btn-success btn-sm" type="submit">승인하기</button>
                                        </form>
                                    </div>
                                    <hr class="my-0">
                                    <div>
                                        <div class="fw-semibold mb-2 text-danger"><i class="bi bi-x-circle me-1"></i>반려</div>
                                        <form method="post" action="admin_hospitals.php"
                                              onsubmit="return confirm('반려 사유를 신청자 이메일로 보냅니다. 계속할까요?');">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="hospital_id" value="<?= $hid ?>">
                                            <input type="hidden" name="admin_csrf" value="<?= req_h($admin_hospitals_csrf) ?>">
                                            <input type="hidden" name="return_to" value="admin_requested_hospitals.php">
                                            <input type="hidden" name="return_status" value="<?= req_h($filter_status) ?>">
                                            <input type="hidden" name="return_q" value="<?= req_h($q) ?>">
                                            <textarea class="form-control form-control-sm mb-2" name="reject_reason" rows="3" required
                                                      maxlength="1000" placeholder="반려 사유 (신청자에게 메일로 전달돼요)"></textarea>
                                            <button class="btn btn-outline-danger btn-sm" type="submit">반려하기</button>
                                        </form>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="border rounded p-3 h-100">
                                    <div class="fw-semibold mb-2 text-danger"><i class="bi bi-x-circle me-1"></i>반려 내역</div>
                                    <div class="small text-muted mb-2">
                                        처리자 <?= req_h($h['reviewed_by'] ?: '-') ?> ·
                                        <?= req_h(!empty($h['reviewed_at']) ? date('Y-m-d H:i', strtotime($h['reviewed_at'])) : '-') ?>
                                    </div>
                                    <div class="border rounded bg-light p-2 small" style="white-space:pre-wrap"><?= req_h($h['reject_reason'] ?: ($h['rejection_reason'] ?? '사유 없음')) ?></div>
                                    <form method="post" action="admin_hospitals.php" class="mt-3"
                                          onsubmit="return confirm('반려를 취소하고 승인할까요? 병원 계정이 발급됩니다.');">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="hospital_id" value="<?= $hid ?>">
                                        <input type="hidden" name="admin_csrf" value="<?= req_h($admin_hospitals_csrf) ?>">
                                            <input type="hidden" name="return_to" value="admin_requested_hospitals.php">
                                        <input type="hidden" name="return_status" value="rejected">
                                        <input type="hidden" name="return_q" value="<?= req_h($q) ?>">
                                        <button class="btn btn-success btn-sm" type="submit">다시 승인하기</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($total_pages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination pagination-sm justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++):
                        $qs = http_build_query(array_filter([
                            'status' => $filter_status === 'rejected' ? 'rejected' : '',
                            'q' => $q, 'page' => $i,
                        ])); ?>
                        <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                            <a class="page-link" href="admin_requested_hospitals.php<?= $qs ? '?' . $qs : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
