<?php
/**
 * 개발 피드백 관리 대시보드
 * 피드백 목록, 상태 변경, 필터, 프롬프트 생성
 * @created 2026-04-06
 */
session_start();
$admin_page_title = '개발 피드백';
$admin_current = 'dev_feedback';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('super_admin');

$message = '';

// POST: 상태 변경
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $dev_note = trim($_POST['dev_note'] ?? '');
        $resolved_by = $_SESSION['ad_admin_user'] ?? '';

        $valid = ['open','in_progress','done','rejected'];
        if ($id > 0 && in_array($status, $valid)) {
            $resolved_at = ($status === 'done' || $status === 'rejected') ? date('Y-m-d H:i:s') : null;
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_dev_feedback SET status=?, dev_note=?, resolved_by=?, resolved_at=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssssi', $status, $dev_note, $resolved_by, $resolved_at, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $message = "피드백 #{$id} 상태가 '{$status}'로 변경되었습니다.";
        }
    }
}

// 필터
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';

$where = '1=1';
$params = [];
$types = '';
if ($filter_status) { $where .= ' AND f.status=?'; $params[] = $filter_status; $types .= 's'; }
if ($filter_type) { $where .= ' AND f.feedback_type=?'; $params[] = $filter_type; $types .= 's'; }

$sql = "SELECT f.* FROM gangnamunni_dev_feedback f WHERE {$where} ORDER BY FIELD(f.priority,'urgent','high','medium','low'), f.created_at DESC LIMIT 200";
$stmt = mysqli_prepare($connect, $sql);
if (!empty($params)) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$feedbacks = [];
while ($row = mysqli_fetch_assoc($result)) $feedbacks[] = $row;
mysqli_stmt_close($stmt);

// 통계
$stats = ['open' => 0, 'in_progress' => 0, 'done' => 0, 'rejected' => 0];
$sq = mysqli_query($connect, "SELECT status, COUNT(*) as cnt FROM gangnamunni_dev_feedback GROUP BY status");
while ($sq && $sr = mysqli_fetch_assoc($sq)) { $stats[$sr['status']] = intval($sr['cnt']); }
$total = array_sum($stats);

require_once __DIR__ . '/includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show py-2"><i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- 헤더 + 통계 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-chat-dots me-2"></i>개발 피드백 관리</h5>
    <div class="d-flex gap-1 flex-wrap">
    <a href="api_dev_feedback.php?action=prompt" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-robot me-1"></i>Claude 프롬프트</a>
    <a href="api_feedback_export.php?format=csv" class="btn btn-outline-success btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>엑셀</a>
    <a href="api_feedback_export.php?format=html_pdf" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    <a href="api_feedback_export.php?format=html_word" class="btn btn-outline-info btn-sm"><i class="bi bi-file-earmark-word me-1"></i>Word</a>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col"><div class="card border-0 shadow-sm text-center py-2"><div class="fs-4 fw-bold"><?= $total ?></div><small class="text-muted">전체</small></div></div>
    <div class="col"><a href="?status=open" class="card border-0 shadow-sm text-center py-2 text-decoration-none"><div class="fs-4 fw-bold text-warning"><?= $stats['open'] ?></div><small class="text-muted">대기</small></a></div>
    <div class="col"><a href="?status=in_progress" class="card border-0 shadow-sm text-center py-2 text-decoration-none"><div class="fs-4 fw-bold text-info"><?= $stats['in_progress'] ?></div><small class="text-muted">진행중</small></a></div>
    <div class="col"><a href="?status=done" class="card border-0 shadow-sm text-center py-2 text-decoration-none"><div class="fs-4 fw-bold text-success"><?= $stats['done'] ?></div><small class="text-muted">완료</small></a></div>
    <div class="col"><a href="?status=rejected" class="card border-0 shadow-sm text-center py-2 text-decoration-none"><div class="fs-4 fw-bold text-secondary"><?= $stats['rejected'] ?></div><small class="text-muted">반려</small></a></div>
</div>

<!-- 목록 -->
<div class="card border-0 shadow-sm">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="fw-bold"><?= count($feedbacks) ?>건</span>
        <a href="?" class="btn btn-outline-secondary btn-sm">전체 보기</a>
    </div>
    <?php if (empty($feedbacks)): ?>
    <div class="card-body text-center text-muted py-5">
        <i class="bi bi-inbox" style="font-size:3rem;"></i>
        <p class="mt-2">피드백이 없습니다</p>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr><th>#</th><th>캡처</th><th>유형</th><th>우선순위</th><th>페이지</th><th>내용</th><th>상태</th><th>작성자</th><th>날짜</th><th>처리</th></tr>
            </thead>
            <tbody>
                <?php
                $type_labels = ['bug'=>'버그','improve'=>'개선','feature'=>'기능','question'=>'질문','other'=>'기타'];
                $type_colors = ['bug'=>'danger','improve'=>'primary','feature'=>'success','question'=>'info','other'=>'secondary'];
                $pri_labels = ['urgent'=>'긴급','high'=>'높음','medium'=>'보통','low'=>'낮음'];
                $pri_colors = ['urgent'=>'danger','high'=>'warning','medium'=>'secondary','low'=>'light text-dark'];
                $stat_colors = ['open'=>'warning','in_progress'=>'info','done'=>'success','rejected'=>'secondary'];
                foreach ($feedbacks as $fb):
                ?>
                <tr>
                    <td><?= $fb['id'] ?></td>
                    <td><?php if (!empty($fb['screenshot_url'])): ?><img src="<?= htmlspecialchars($fb['screenshot_url']) ?>" style="max-width:60px;max-height:40px;border-radius:4px;cursor:pointer;" onclick="showModal(this)" title="스크린샷 확대"><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                    <td><span class="badge bg-<?= $type_colors[$fb['feedback_type']] ?? 'secondary' ?>"><?= $type_labels[$fb['feedback_type']] ?? $fb['feedback_type'] ?></span></td>
                    <td><span class="badge bg-<?= $pri_colors[$fb['priority']] ?? 'secondary' ?>"><?= $pri_labels[$fb['priority']] ?? $fb['priority'] ?></span></td>
                    <td><small><?= htmlspecialchars($fb['page_id'] ?? '') ?></small></td>
                    <td style="max-width:300px;"><small><?= htmlspecialchars(mb_substr($fb['content'], 0, 80)) ?><?= mb_strlen($fb['content']) > 80 ? '...' : '' ?></small></td>
                    <td><span class="badge bg-<?= $stat_colors[$fb['status']] ?? 'secondary' ?>"><?= $fb['status'] ?></span></td>
                    <td><small><?= htmlspecialchars($fb['submitted_by'] ?? '') ?></small></td>
                    <td><small><?= substr($fb['created_at'] ?? '', 5, 11) ?></small></td>
                    <td>
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#fbModal<?= $fb['id'] ?>"><i class="bi bi-pencil"></i></button>
                    </td>
                </tr>
                <!-- 처리 모달 -->
                <div class="modal fade" id="fbModal<?= $fb['id'] ?>" tabindex="-1">
                    <div class="modal-dialog"><div class="modal-content">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="id" value="<?= $fb['id'] ?>">
                            <div class="modal-header py-2"><h6 class="modal-title">피드백 #<?= $fb['id'] ?> 처리</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                                <div class="mb-2"><strong>내용:</strong><br><?= nl2br(htmlspecialchars($fb['content'])) ?></div>
                                <div class="mb-2"><label class="form-label fw-bold">상태</label>
                                    <select name="status" class="form-select form-select-sm">
                                        <?php foreach (['open','in_progress','done','rejected'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $fb['status']===$s?'selected':'' ?>><?= $s ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div><label class="form-label fw-bold">개발자 메모</label>
                                    <textarea name="dev_note" class="form-control" rows="3"><?= htmlspecialchars($fb['dev_note'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="modal-footer py-1"><button type="submit" class="btn btn-primary btn-sm">저장</button></div>
                        </form>
                    </div></div>
                </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
?>
<!-- 이미지 확대 모달 -->
<div class="modal fade" id="imgModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content" style="background:transparent;border:none;"><div class="modal-body text-center p-0"><img id="modalImg" style="max-width:100%;max-height:85vh;border-radius:8px;box-shadow:0 8px 32px rgba(0,0,0,.3);"></div></div></div></div>
<script>function showModal(el){document.getElementById('modalImg').src=el.src;new bootstrap.Modal(document.getElementById('imgModal')).show();}</script>
<?php
require_once __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
