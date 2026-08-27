<?php
/**
 * 상담 관리 (admin_consultations.php)
 * - 상담 신청 목록, 상태 관리(대기/승인/완료/거절), 검색
 */
session_start();

$admin_page_title = '상담 관리';
$admin_current = 'consultations';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

// DB 연결
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
require_admin_login();
require_permission('consultations');

// ===== Action: 상태 변경 (viewer 역할은 불가) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // viewer 역할은 상태 변경 불가
    $admin_role = $_SESSION['ad_admin_role'] ?? 'viewer';
    if ($admin_role === 'viewer') {
        $msg = '뷰어 역할은 상담 상태를 변경할 수 없습니다.';
        $msg_type = 'danger';
        goto skip_consult_action;
    }
    $id = intval($_POST['id'] ?? 0);
    $action = $_POST['action'];
    $status_map = ['approve' => 'approved', 'complete' => 'completed', 'reject' => 'rejected'];
    if ($id > 0 && isset($status_map[$action])) {
        $new_status = $status_map[$action];
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_consultations SET status = ? WHERE id = ?");
        $status_changed = false;
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $id);
            $status_changed = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
            mysqli_stmt_close($stmt);
        }
        $msg = '상담 상태가 변경되었습니다.';
        if ($new_status === 'approved' && $status_changed) {
            try {
                $consult_result = mysqli_query($connect, "SELECT * FROM gangnamunni_consultations WHERE id = {$id} LIMIT 1");
                $consultation_row = $consult_result ? mysqli_fetch_assoc($consult_result) : null;
                $patient_phone = preg_replace('/[^0-9]/', '', (string)($consultation_row['phone'] ?? $consultation_row['patient_phone'] ?? ''));
                $member_table = (defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_') . 'member';
                $patient_member_id = '';

                if ($patient_phone !== '' && preg_match('/^[A-Za-z0-9_]+$/', $member_table)) {
                    $member_stmt = mysqli_prepare($connect,
                        "SELECT mb_id FROM {$member_table} WHERE REPLACE(REPLACE(mb_hp, '-', ''), ' ', '') = ? LIMIT 1");
                    if ($member_stmt) {
                        mysqli_stmt_bind_param($member_stmt, 's', $patient_phone);
                        mysqli_stmt_execute($member_stmt);
                        $member_row = mysqli_fetch_assoc(mysqli_stmt_get_result($member_stmt));
                        mysqli_stmt_close($member_stmt);
                        $patient_member_id = trim((string)($member_row['mb_id'] ?? ''));
                    }
                }

                if ($patient_member_id !== '') {
                    require_once __DIR__ . '/includes/user_notifications.php';
                    require_once __DIR__ . '/includes/admin_fcm.php';
                    $notification_title = '상담신청이 확인되었습니다';
                    $notification_body = trim((string)($consultation_row['hospital_name'] ?? ''));
                    $notification_link = (defined('SITE_FULL_URL') ? SITE_FULL_URL : '') . 'my_appointments.php';
                    create_user_notification($connect, $patient_member_id, 'general', $notification_title, $notification_body, $notification_link);
                    send_push_to_members($connect, [$patient_member_id], $notification_title, $notification_body, $notification_link);
                }
            } catch (Throwable $e) {
                error_log('[admin_consultations] patient notification failed (consultation already approved): ' . $e->getMessage());
            }
        }
        $msg_type = 'success';
    }
}
skip_consult_action:

// ===== Filters =====
$filter_status = $_GET['status'] ?? '';
$search = $_GET['q'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 동적 WHERE 절 구성 (prepared statement 사용)
$where_clauses = ["1=1"];
$bind_types = '';
$bind_values = [];

if ($filter_status) {
    $where_clauses[] = "status = ?";
    $bind_types .= 's';
    $bind_values[] = $filter_status;
}
if ($search) {
    $where_clauses[] = "(name LIKE ? OR phone LIKE ? OR hospital_name LIKE ?)";
    $bind_types .= 'sss';
    $search_like = '%' . $search . '%';
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
}

$where = implode(' AND ', $where_clauses);

// 총 건수
$count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_consultations WHERE " . $where;
$stmt_c = mysqli_prepare($connect, $count_sql);
if ($bind_types && $stmt_c) {
    mysqli_stmt_bind_param($stmt_c, $bind_types, ...$bind_values);
}
mysqli_stmt_execute($stmt_c);
$count_r = mysqli_stmt_get_result($stmt_c);
$total = $count_r ? mysqli_fetch_assoc($count_r)['cnt'] : 0;
$total_pages = max(1, ceil($total / $per_page));
mysqli_stmt_close($stmt_c);

// 데이터 조회
$data_sql = "SELECT * FROM gangnamunni_consultations WHERE " . $where . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt_d = mysqli_prepare($connect, $data_sql);
$data_bind_types = $bind_types . 'ii';
$data_bind_values = array_merge($bind_values, [$per_page, $offset]);
mysqli_stmt_bind_param($stmt_d, $data_bind_types, ...$data_bind_values);
mysqli_stmt_execute($stmt_d);
$r = mysqli_stmt_get_result($stmt_d);
$rows = [];
if ($r) while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
mysqli_stmt_close($stmt_d);

// Stats
$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'completed' => 0, 'rejected' => 0];
$sr = mysqli_query($connect, "SELECT status, COUNT(*) as cnt FROM gangnamunni_consultations GROUP BY status");
if ($sr) while ($s = mysqli_fetch_assoc($sr)) { $stats[$s['status']] = $s['cnt']; $stats['total'] += $s['cnt']; }

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show">
        <?php echo $msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-chat-dots me-2"></i>상담 관리</h4>
        <span class="badge bg-primary"><?php echo number_format($stats['total']); ?>건</span>
    </div>

    <!-- 통계 -->
    <div class="row g-3 mb-4">
        <?php foreach ([['전체', $stats['total'], 'primary'], ['대기중', $stats['pending'], 'warning'], ['승인', $stats['approved'], 'success'], ['완료', $stats['completed'], 'info'], ['거절', $stats['rejected'], 'danger']] as $sc): ?>
        <div class="col-6 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0"><?php echo number_format($sc[1]); ?></h5>
                    <small class="text-<?php echo $sc[2]; ?>"><?php echo $sc[0]; ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 필터 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">전체 상태</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>대기중</option>
                        <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>승인</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>완료</option>
                        <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>거절</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="이름, 전화번호, 병원명 검색" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-search"></i> 검색</button></div>
            </form>
        </div>
    </div>

    <!-- 목록 -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>병원</th><th>신청자</th><th>전화번호</th><th>상담유형</th><th>메시지</th><th>첨부</th><th>상태</th><th>신청일</th><th>관리</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="10" class="text-center py-5 text-muted"><i class="bi bi-inbox" style="font-size:2rem;"></i><br>상담 신청이 없습니다.</td></tr>
                    <?php else: foreach ($rows as $c): ?>
                    <tr>
                        <td><?php echo $c['id']; ?></td>
                        <td><small><?php echo htmlspecialchars($c['hospital_name'] ?? ''); ?></small></td>
                        <td><strong><?php echo htmlspecialchars($c['name'] ?? ''); ?></strong></td>
                        <td><?php echo htmlspecialchars($c['phone'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($c['consultation_type'] ?? $c['treatment_type'] ?? ''); ?></td>
                        <td><span title="<?php echo htmlspecialchars($c['message'] ?? ''); ?>"><?php echo mb_substr($c['message'] ?? '', 0, 30) . (mb_strlen($c['message'] ?? '') > 30 ? '...' : ''); ?></span></td>
                        <td>
                            <?php
                            $photos = !empty($c['photo_urls']) ? json_decode($c['photo_urls'], true) : [];
                            if (!empty($photos)):
                            ?>
                            <a href="#" onclick="showPhotos(<?php echo htmlspecialchars(json_encode($photos)); ?>); return false;" class="text-primary" title="첨부 사진 보기">
                                <i class="bi bi-image"></i> <?php echo count($photos); ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $badge = ['pending'=>'warning','approved'=>'success','completed'=>'info','rejected'=>'danger'];
                            $lbl = ['pending'=>'대기중','approved'=>'승인','completed'=>'완료','rejected'=>'거절'];
                            $st = $c['status'] ?? 'pending';
                            ?>
                            <span class="badge bg-<?php echo $badge[$st] ?? 'secondary'; ?>"><?php echo $lbl[$st] ?? $st; ?></span>
                        </td>
                        <td><small><?php echo substr($c['created_at'] ?? '', 0, 16); ?></small></td>
                        <td>
                            <?php $is_viewer = (($_SESSION['ad_admin_role'] ?? '') === 'viewer'); ?>
                            <?php if ($is_viewer): ?>
                            <span class="text-muted small">보기 전용</span>
                            <?php elseif ($st === 'pending'): ?>
                            <form method="post" class="d-inline"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i></button></form>
                            <form method="post" class="d-inline"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-danger btn-sm"><i class="bi bi-x-lg"></i></button></form>
                            <?php elseif ($st === 'approved'): ?>
                            <form method="post" class="d-inline"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><input type="hidden" name="action" value="complete"><button class="btn btn-info btn-sm text-white"><i class="bi bi-check2-all"></i></button></form>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white">
            <nav><ul class="pagination pagination-sm justify-content-center mb-0">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&q=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 사진 라이트박스 모달 -->
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-images me-2"></i>첨부 사진</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center" id="photoModalBody" style="background:#f8f9fa;">
            </div>
        </div>
    </div>
</div>

<script>
function showPhotos(photos) {
    var body = document.getElementById('photoModalBody');
    var html = '<div class="d-flex flex-wrap gap-2 justify-content-center">';
    photos.forEach(function(url) {
        html += '<a href="' + url + '" target="_blank"><img src="' + url + '" style="max-width:280px;max-height:280px;border-radius:8px;object-fit:cover;cursor:zoom-in;" class="shadow-sm"></a>';
    });
    html += '</div>';
    body.innerHTML = html;
    new bootstrap.Modal(document.getElementById('photoModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
