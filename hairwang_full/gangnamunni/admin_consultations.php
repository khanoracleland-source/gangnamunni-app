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
require_once __DIR__ . '/includes/hospital_filter.php';

// DB 연결
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
require_admin_login();
require_permission('consultations');

// 병원 범위 (fail-closed): super_admin 만 전체, 그 외 관리자는 관리자 관리에서 지정된 소속 병원만.
// 미배정 병원 계정은 '0' → 어떤 상담도 조회/변경할 수 없다.
$consult_hospital_ids = get_strict_admin_hospital_ids($connect);
$consult_hosp_in = $consult_hospital_ids === null
    ? null
    : (empty($consult_hospital_ids) ? '0' : implode(',', array_map('intval', $consult_hospital_ids)));
$consult_scope = $consult_hosp_in !== null ? " AND hospital_id IN ({$consult_hosp_in})" : '';

if (empty($_SESSION['admin_consultations_csrf'])) {
    $_SESSION['admin_consultations_csrf'] = bin2hex(random_bytes(32));
}
$admin_consultations_csrf = $_SESSION['admin_consultations_csrf'];

// ===== Action: 상태 변경 (viewer 역할은 불가) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // viewer 역할은 상태 변경 불가
    $admin_role = $_SESSION['ad_admin_role'] ?? 'viewer';
    if ($admin_role === 'viewer') {
        $msg = '뷰어 역할은 상담 상태를 변경할 수 없습니다.';
        $msg_type = 'danger';
        goto skip_consult_action;
    }
    if (empty($_POST['admin_csrf']) || !hash_equals($admin_consultations_csrf, (string)$_POST['admin_csrf'])) {
        $msg = '요청이 만료되었습니다. 페이지를 새로고침한 뒤 다시 시도해주세요.';
        $msg_type = 'danger';
        goto skip_consult_action;
    }
    $id = intval($_POST['id'] ?? 0);
    $action = $_POST['action'];
    $status_map = ['approve' => 'approved', 'complete' => 'completed', 'reject' => 'rejected', 'contact_failed' => 'contact_failed'];
    // [07-41] 병원 메시지 저장 (고객 상담 상세에 노출)
    if ($id > 0 && $action === 'hospital_message') {
        $own_m = mysqli_query($connect, "SELECT 1 FROM gangnamunni_consultations WHERE id = {$id}{$consult_scope} LIMIT 1");
        $hm = trim((string)($_POST['hospital_message'] ?? ''));
        if (!$own_m || mysqli_num_rows($own_m) === 0) {
            $msg = '해당 상담에 대한 권한이 없습니다.'; $msg_type = 'danger';
        } elseif (mb_strlen($hm) > 1000) {
            $msg = '메시지는 1000자 이내로 입력해주세요.'; $msg_type = 'danger';
        } else {
            $hm_col = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_consultations LIKE 'hospital_message'");
            if (!$hm_col || mysqli_num_rows($hm_col) === 0) {
                $msg = '먼저 sql/consultation_history_20261023.sql 을 실행해주세요.'; $msg_type = 'danger';
            } else {
                $hm_stmt = mysqli_prepare($connect, "UPDATE gangnamunni_consultations SET hospital_message = ?, hospital_message_at = IF(? = '', NULL, NOW()) WHERE id = ?{$consult_scope}");
                if ($hm_stmt) {
                    mysqli_stmt_bind_param($hm_stmt, 'ssi', $hm, $hm, $id);
                    mysqli_stmt_execute($hm_stmt);
                    mysqli_stmt_close($hm_stmt);
                }
                $msg = '병원 메시지가 저장되었습니다.'; $msg_type = 'success';
            }
        }
    } elseif ($id > 0 && isset($status_map[$action])) {
        // 다른 병원의 상담은 존재 여부도 드러내지 않고 거부
        $own_r = mysqli_query($connect, "SELECT 1 FROM gangnamunni_consultations WHERE id = {$id}{$consult_scope} LIMIT 1");
        if (!$own_r || mysqli_num_rows($own_r) === 0) {
            $msg = '해당 상담에 대한 권한이 없습니다.';
            $msg_type = 'danger';
            goto skip_consult_action;
        }
        $new_status = $status_map[$action];
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_consultations SET status = ? WHERE id = ?{$consult_scope}");
        $status_changed = false;
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $new_status, $id);
            $status_changed = mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1;
            mysqli_stmt_close($stmt);
        }
        $msg = '상담 상태가 변경되었습니다.';
        // [07-41] 진행 기록(타임라인) + 연락 실패 알림
        if ($status_changed && in_array($new_status, ['contact_failed', 'completed'], true)) {
            $ev_tbl = @mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_consultation_events'");
            if ($ev_tbl && mysqli_num_rows($ev_tbl) > 0 && ($ev = mysqli_prepare($connect, "INSERT INTO gangnamunni_consultation_events (consultation_id, kind) VALUES (?, ?)"))) {
                $ev_kind = $new_status === 'completed' ? 'completed' : 'no_answer';
                mysqli_stmt_bind_param($ev, 'is', $id, $ev_kind);
                mysqli_stmt_execute($ev);
                mysqli_stmt_close($ev);
            }
            if ($new_status === 'contact_failed') {
                try {
                    $mc_col = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_consultations LIKE 'mb_id'");
                    if ($mc_col && mysqli_num_rows($mc_col) > 0) {
                        $nr = mysqli_query($connect, "SELECT mb_id, hospital_name FROM gangnamunni_consultations WHERE id = {$id}{$consult_scope} LIMIT 1");
                        $nrow = $nr ? mysqli_fetch_assoc($nr) : null;
                        if ($nrow && trim((string)$nrow['mb_id']) !== '') {
                            require_once __DIR__ . '/includes/user_notifications.php';
                            require_once __DIR__ . '/includes/admin_fcm.php';
                            $nt = '병원에서 연락드렸지만 받지 않으셨어요';
                            $nb = trim((string)$nrow['hospital_name']) . ' · 편한 시간을 골라 다시 요청해주세요.';
                            $nl = (defined('SITE_FULL_URL') ? SITE_FULL_URL : '') . 'my_consultations.php';
                            create_user_notification($connect, $nrow['mb_id'], 'general', $nt, $nb, $nl);
                            send_push_to_members($connect, [$nrow['mb_id']], $nt, $nb, $nl);
                        }
                    }
                } catch (Throwable $e) {
                    error_log('[admin_consultations] contact_failed notification failed: ' . $e->getMessage());
                }
            }
        }
        if ($new_status === 'approved' && $status_changed) {
            try {
                $consult_result = mysqli_query($connect, "SELECT * FROM gangnamunni_consultations WHERE id = {$id}{$consult_scope} LIMIT 1");
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
if ($consult_hosp_in !== null) {
    $where_clauses[] = "hospital_id IN ({$consult_hosp_in})";
}
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
$sr = mysqli_query($connect, "SELECT status, COUNT(*) as cnt FROM gangnamunni_consultations WHERE 1=1{$consult_scope} GROUP BY status");
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
                        <option value="contact_failed" <?php echo $filter_status === 'contact_failed' ? 'selected' : ''; ?>>연락 실패</option>
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
                        <td><span title="<?php echo htmlspecialchars($c['message'] ?? ''); ?>"><?php echo htmlspecialchars(mb_substr($c['message'] ?? '', 0, 30) . (mb_strlen($c['message'] ?? '') > 30 ? '...' : '')); ?></span></td>
                        <td>
                            <?php
                            $photos = !empty($c['photo_urls']) ? json_decode($c['photo_urls'], true) : [];
                            $photos = is_array($photos) ? array_values($photos) : [];
                            // 사진은 직접 URL 이 아니라 권한을 검사하는 consultation_photo.php 를 통해서만 연다.
                            $photo_links = [];
                            foreach ($photos as $pi => $_unused) {
                                $photo_links[] = 'consultation_photo.php?id=' . (int)$c['id'] . '&i=' . (int)$pi;
                            }
                            if (!empty($photo_links)):
                            ?>
                            <a href="#" onclick="showPhotos(<?php echo htmlspecialchars(json_encode($photo_links), ENT_QUOTES); ?>); return false;" class="text-primary" title="첨부 사진 보기">
                                <i class="bi bi-image"></i> <?php echo count($photos); ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $badge = ['pending'=>'warning','approved'=>'success','completed'=>'info','rejected'=>'danger','contact_failed'=>'dark'];
                            $lbl = ['pending'=>'대기중','approved'=>'승인','completed'=>'완료','rejected'=>'거절','contact_failed'=>'연락 실패'];
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
                            <form method="post" class="d-inline"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_consultations_csrf, ENT_QUOTES); ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i></button></form>
                            <form method="post" class="d-inline"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_consultations_csrf, ENT_QUOTES); ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-danger btn-sm"><i class="bi bi-x-lg"></i></button></form>
                            <button type="button" class="btn btn-outline-dark btn-sm" title="연락 실패 기록 (받지 않음)" onclick="if(confirm('연락이 닿지 않았음으로 기록하고 고객에게 알림을 보낼까요?')) document.getElementById('cf_<?php echo (int)$c['id']; ?>').submit();"><i class="bi bi-telephone-x"></i></button>
                            <form method="post" class="d-none" id="cf_<?php echo (int)$c['id']; ?>"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_consultations_csrf, ENT_QUOTES); ?>"><input type="hidden" name="action" value="contact_failed"></form>
                            <button type="button" class="btn btn-outline-secondary btn-sm" title="고객에게 보이는 병원 메시지" onclick="openHospitalMessage(<?php echo (int)$c['id']; ?>, this)" data-msg="<?php echo htmlspecialchars((string)($c['hospital_message'] ?? ''), ENT_QUOTES); ?>"><i class="bi bi-chat-left-text"></i></button>
                            <?php elseif ($st === 'approved'): ?>
                            <form method="post" class="d-inline"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_consultations_csrf, ENT_QUOTES); ?>"><input type="hidden" name="action" value="complete"><button class="btn btn-info btn-sm text-white"><i class="bi bi-check2-all"></i></button></form>
                            <?php elseif ($st === 'contact_failed'): ?>
                            <form method="post" class="d-inline"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_consultations_csrf, ENT_QUOTES); ?>"><input type="hidden" name="action" value="complete"><button class="btn btn-info btn-sm text-white" title="상담 완료"><i class="bi bi-check2-all"></i></button></form>
                            <button type="button" class="btn btn-outline-secondary btn-sm" title="고객에게 보이는 병원 메시지" onclick="openHospitalMessage(<?php echo (int)$c['id']; ?>, this)" data-msg="<?php echo htmlspecialchars((string)($c['hospital_message'] ?? ''), ENT_QUOTES); ?>"><i class="bi bi-chat-left-text"></i></button>
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

<!-- [07-41] 병원 메시지 모달 -->
<div class="modal fade" id="hmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" class="modal-content">
            <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_consultations_csrf, ENT_QUOTES); ?>">
            <input type="hidden" name="action" value="hospital_message">
            <input type="hidden" name="id" id="hmId" value="">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-chat-left-text me-2"></i>고객에게 보이는 병원 메시지</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <textarea name="hospital_message" id="hmText" class="form-control" rows="5" maxlength="1000" placeholder="예) 두 번 연락드렸지만 연결되지 않았어요. 편한 시간을 알려주시면 다시 연락드릴게요."></textarea>
                <div class="form-text">고객의 마이페이지 &gt; 상담 내역 상세에 표시됩니다. 비우고 저장하면 삭제됩니다.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="submit" class="btn btn-primary">저장</button>
            </div>
        </form>
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
function openHospitalMessage(id, btn) {
    document.getElementById('hmId').value = id;
    document.getElementById('hmText').value = btn.dataset.msg || '';
    new bootstrap.Modal(document.getElementById('hmModal')).show();
}
function showPhotos(photos) {
    var body = document.getElementById('photoModalBody');
    body.textContent = '';
    var wrap = document.createElement('div');
    wrap.className = 'd-flex flex-wrap gap-2 justify-content-center';
    photos.forEach(function(url) {
        if (typeof url !== 'string' || url.indexOf('consultation_photo.php?') !== 0) return;
        var a = document.createElement('a');
        a.href = url;
        a.target = '_blank';
        a.rel = 'noopener';
        var img = document.createElement('img');
        img.src = url;
        img.className = 'shadow-sm';
        img.style.cssText = 'max-width:280px;max-height:280px;border-radius:8px;object-fit:cover;cursor:zoom-in;';
        a.appendChild(img);
        wrap.appendChild(a);
    });
    body.appendChild(wrap);
    new bootstrap.Modal(document.getElementById('photoModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
