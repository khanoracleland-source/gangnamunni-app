<?php
/**
 * 내 신청 현황 — 상세 조회 + 수정 기능
 * 타임라인 UI + 수정 이력 추적
 * @created 2026-04-03
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$admin_page_title = '내 신청 현황';
$admin_current = 'hospital_apply_status';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();

$current_admin = get_current_admin();
$applied_by = $current_admin['username'] ?? ($current_admin['name'] ?? '');
$message = '';
$error = '';

// ===== POST: 수정 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_application') {
    $hospital_id = intval($_POST['hospital_id'] ?? 0);
    if ($hospital_id > 0) {
        // 기존 데이터 조회
        $stmt_old = mysqli_prepare($connect, "SELECT * FROM gangnamunni_hospitals WHERE id=? AND applied_by=?");
        mysqli_stmt_bind_param($stmt_old, 'is', $hospital_id, $applied_by);
        mysqli_stmt_execute($stmt_old);
        $old = mysqli_stmt_get_result($stmt_old)->fetch_assoc();
        mysqli_stmt_close($stmt_old);

        if ($old) {
            $editable_fields = [
                'name' => '병원명', 'owner_name' => '대표자명', 'business_number' => '사업자등록번호',
                'phone' => '전화번호', 'email' => '이메일', 'contact_name' => '담당자명',
                'contact_phone' => '담당자 전화', 'address' => '주소', 'introduction' => '병원 소개',
                'website' => '홈페이지', 'sns_url' => 'SNS', 'business_hours' => '영업시간',
                'treatment_tags' => '주요 시술',
            ];

            $changes = [];
            $update_parts = [];
            $update_vals = [];
            $update_types = '';

            foreach ($editable_fields as $field => $label) {
                $new_val = trim($_POST[$field] ?? '');
                $old_val = $old[$field] ?? '';
                if ($new_val !== $old_val) {
                    $changes[] = ['field' => $field, 'label' => $label, 'old' => $old_val, 'new' => $new_val];
                    $update_parts[] = "$field=?";
                    $update_vals[] = $new_val;
                    $update_types .= 's';
                }
            }

            if (!empty($changes)) {
                // 수정 이력 저장
                $stmt_rev = mysqli_prepare($connect, "INSERT INTO gangnamunni_hospital_revisions (hospital_id, field_name, old_value, new_value, changed_by) VALUES (?, ?, ?, ?, ?)");
                foreach ($changes as $c) {
                    mysqli_stmt_bind_param($stmt_rev, 'issss', $hospital_id, $c['field'], $c['old'], $c['new'], $applied_by);
                    mysqli_stmt_execute($stmt_rev);
                }
                mysqli_stmt_close($stmt_rev);

                // 병원 데이터 업데이트
                $update_parts[] = "modified_at=NOW()";
                $update_parts[] = "has_unreviewed_changes=1";
                $sql = "UPDATE gangnamunni_hospitals SET " . implode(', ', $update_parts) . " WHERE id=? AND applied_by=?";
                $stmt_upd = mysqli_prepare($connect, $sql);
                $update_vals[] = $hospital_id;
                $update_vals[] = $applied_by;
                $update_types .= 'is';
                mysqli_stmt_bind_param($stmt_upd, $update_types, ...$update_vals);
                if (mysqli_stmt_execute($stmt_upd)) {
                    $message = count($changes) . '개 항목이 수정되었습니다.';
                } else {
                    $error = '수정 실패: ' . mysqli_error($connect);
                }
                mysqli_stmt_close($stmt_upd);
            } else {
                $message = '변경된 내용이 없습니다.';
            }
        }
    }
}

// 내 신청 목록
$applications = [];
if (!empty($applied_by)) {
    $stmt = mysqli_prepare($connect, "SELECT h.*, (SELECT COUNT(*) FROM gangnamunni_hospital_revisions r WHERE r.hospital_id=h.id) as revision_count FROM gangnamunni_hospitals h WHERE h.applied_by=? ORDER BY h.applied_at DESC");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $applied_by);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) $applications[] = $row;
        mysqli_stmt_close($stmt);
    }
}

// 상세 보기 모드
$view_id = intval($_GET['view'] ?? 0);
$view_app = null;
$view_revisions = [];
if ($view_id > 0) {
    foreach ($applications as $a) {
        if ($a['id'] == $view_id) { $view_app = $a; break; }
    }
    if ($view_app) {
        $stmt_r = mysqli_prepare($connect, "SELECT * FROM gangnamunni_hospital_revisions WHERE hospital_id=? ORDER BY changed_at DESC");
        mysqli_stmt_bind_param($stmt_r, 'i', $view_id);
        mysqli_stmt_execute($stmt_r);
        $res_r = mysqli_stmt_get_result($stmt_r);
        while ($r = mysqli_fetch_assoc($res_r)) $view_revisions[] = $r;
        mysqli_stmt_close($stmt_r);
    }
}

$field_labels = [
    'name' => '병원명', 'owner_name' => '대표자명', 'business_number' => '사업자등록번호',
    'phone' => '전화번호', 'email' => '이메일', 'contact_name' => '담당자명',
    'contact_phone' => '담당자 전화', 'address' => '주소', 'introduction' => '병원 소개',
    'website' => '홈페이지', 'sns_url' => 'SNS', 'business_hours' => '영업시간',
    'treatment_tags' => '주요 시술', 'categories' => '진료과목',
];

require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
    .status-timeline { position: relative; padding-left: 30px; }
    .status-timeline::before { content: ''; position: absolute; left: 10px; top: 0; bottom: 0; width: 2px; background: #dee2e6; }
    .timeline-step { position: relative; margin-bottom: 20px; }
    .timeline-step .step-dot { position: absolute; left: -26px; width: 14px; height: 14px; border-radius: 50%; border: 2px solid #fff; top: 4px; }
    .timeline-step.done .step-dot { background: #198754; }
    .timeline-step.current .step-dot { background: #ffc107; box-shadow: 0 0 0 4px rgba(255,193,7,.3); }
    .timeline-step.future .step-dot { background: #dee2e6; }
    .timeline-step.rejected .step-dot { background: #dc3545; }
    .app-card { border-radius: 12px; border: none; box-shadow: 0 2px 12px rgba(0,0,0,.06); transition: transform 0.2s; }
    .app-card:hover { transform: translateY(-2px); box-shadow: 0 4px 20px rgba(0,0,0,.1); }
    .status-badge { font-size: 0.8rem; padding: 6px 14px; border-radius: 20px; }
    .detail-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { width: 120px; font-weight: 600; color: #666; flex-shrink: 0; }
    .detail-value { flex: 1; }
    .revision-diff { font-size: 0.85rem; }
    .revision-diff .old { background: #ffeef0; text-decoration: line-through; padding: 2px 6px; border-radius: 4px; }
    .revision-diff .new { background: #e6ffed; padding: 2px 6px; border-radius: 4px; }
</style>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if ($view_app): ?>
<!-- ==================== 상세/수정 모드 ==================== -->
<?php
$st = $view_app['status'];
$is_editable = in_array($st, ['pending', 'rejected']);
$edit_mode = isset($_GET['edit']) && $is_editable;
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <a href="hospital_apply_status.php" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>목록으로</a>
        <h4 class="fw-bold mt-1 mb-0"><?= htmlspecialchars($view_app['name']) ?></h4>
    </div>
    <div class="d-flex gap-2">
        <?php if ($is_editable && !$edit_mode): ?>
        <a href="?view=<?= $view_id ?>&edit=1" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>수정</a>
        <?php endif; ?>
        <?php if ($edit_mode): ?>
        <a href="?view=<?= $view_id ?>" class="btn btn-outline-secondary btn-sm">취소</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- 좌측: 진행 상태 -->
    <div class="col-lg-4">
        <div class="card app-card">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-1"></i>진행 상태</h6>
                <div class="status-timeline">
                    <div class="timeline-step done">
                        <div class="step-dot"></div>
                        <div class="fw-semibold small">신청 접수</div>
                        <div class="text-muted" style="font-size:0.75rem;"><?= $view_app['applied_at'] ? date('Y.m.d H:i', strtotime($view_app['applied_at'])) : '' ?></div>
                    </div>
                    <?php if ($view_app['modified_at']): ?>
                    <div class="timeline-step done">
                        <div class="step-dot" style="background:#17a2b8;"></div>
                        <div class="fw-semibold small">수정 (<?= $view_app['revision_count'] ?>회)</div>
                        <div class="text-muted" style="font-size:0.75rem;"><?= date('Y.m.d H:i', strtotime($view_app['modified_at'])) ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="timeline-step <?= $st === 'pending' ? 'current' : ($st === 'approved' ? 'done' : 'rejected') ?>">
                        <div class="step-dot"></div>
                        <div class="fw-semibold small"><?= $st === 'pending' ? '심사 중' : ($st === 'approved' ? '심사 완료' : '반려') ?></div>
                        <?php if ($st !== 'pending' && $view_app['reviewed_at']): ?>
                        <div class="text-muted" style="font-size:0.75rem;"><?= date('Y.m.d H:i', strtotime($view_app['reviewed_at'])) ?></div>
                        <?php elseif ($st === 'pending'): ?>
                        <div class="text-muted" style="font-size:0.75rem;">1~3 영업일 예상</div>
                        <?php endif; ?>
                    </div>
                    <?php if ($st === 'approved'): ?>
                    <div class="timeline-step done">
                        <div class="step-dot"></div>
                        <div class="fw-semibold small">승인 완료</div>
                        <div class="text-muted" style="font-size:0.75rem;">등급: <?= $view_app['ad_grade'] === 'none' ? 'Free' : strtoupper($view_app['ad_grade']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($st === 'rejected' && !empty($view_app['rejection_reason'])): ?>
                <div class="alert alert-danger mt-3 py-2 small">
                    <strong>반려 사유:</strong><br><?= nl2br(htmlspecialchars($view_app['rejection_reason'])) ?>
                </div>
                <?php endif; ?>

                <?php if ($st === 'approved'): ?>
                <a href="dashboard.php" class="btn btn-success btn-sm w-100 mt-3"><i class="bi bi-speedometer2 me-1"></i>관리자 대시보드</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 수정 이력 -->
        <?php if (!empty($view_revisions)): ?>
        <div class="card app-card mt-3">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-journal-text me-1"></i>수정 이력 (<?= count($view_revisions) ?>건)</h6>
                <?php
                $grouped = [];
                foreach ($view_revisions as $rv) {
                    $date_key = date('Y.m.d H:i', strtotime($rv['changed_at']));
                    $grouped[$date_key][] = $rv;
                }
                foreach ($grouped as $date => $items): ?>
                <div class="mb-3">
                    <div class="text-muted small fw-semibold"><?= $date ?></div>
                    <?php foreach ($items as $rv): ?>
                    <div class="revision-diff mt-1">
                        <span class="text-muted"><?= $field_labels[$rv['field_name']] ?? $rv['field_name'] ?>:</span>
                        <span class="old"><?= htmlspecialchars(mb_substr($rv['old_value'] ?? '', 0, 30)) ?: '(없음)' ?></span>
                        <i class="bi bi-arrow-right mx-1"></i>
                        <span class="new"><?= htmlspecialchars(mb_substr($rv['new_value'] ?? '', 0, 30)) ?: '(없음)' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 우측: 상세 정보 / 수정 폼 -->
    <div class="col-lg-8">
        <div class="card app-card">
            <div class="card-body">
                <?php if ($edit_mode): ?>
                <!-- 수정 폼 -->
                <h6 class="fw-bold mb-3"><i class="bi bi-pencil-square me-1"></i>신청 정보 수정</h6>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_application">
                    <input type="hidden" name="hospital_id" value="<?= $view_id ?>">

                    <?php
                    $edit_fields = [
                        ['name', '병원명', 'text', true],
                        ['owner_name', '대표자명', 'text', false],
                        ['business_number', '사업자등록번호', 'text', false],
                        ['phone', '전화번호', 'tel', false],
                        ['email', '이메일', 'email', false],
                        ['contact_name', '담당자명', 'text', false],
                        ['contact_phone', '담당자 전화', 'tel', false],
                        ['address', '주소', 'text', false],
                        ['business_hours', '영업시간', 'text', false],
                        ['treatment_tags', '주요 시술', 'text', false],
                        ['website', '홈페이지', 'url', false],
                        ['sns_url', 'SNS', 'text', false],
                        ['introduction', '병원 소개', 'textarea', false],
                    ];
                    foreach ($edit_fields as $ef):
                        $fname = $ef[0]; $flabel = $ef[1]; $ftype = $ef[2]; $freq = $ef[3];
                        $fval = $view_app[$fname] ?? '';
                    ?>
                    <div class="row mb-3">
                        <label class="col-sm-3 col-form-label fw-semibold small"><?= $flabel ?> <?= $freq ? '<span class="text-danger">*</span>' : '' ?></label>
                        <div class="col-sm-9">
                            <?php if ($ftype === 'textarea'): ?>
                            <textarea name="<?= $fname ?>" class="form-control form-control-sm" rows="3"><?= htmlspecialchars($fval) ?></textarea>
                            <?php else: ?>
                            <input type="<?= $ftype ?>" name="<?= $fname ?>" class="form-control form-control-sm" value="<?= htmlspecialchars($fval) ?>" <?= $freq ? 'required' : '' ?>>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <div class="text-end mt-3">
                        <a href="?view=<?= $view_id ?>" class="btn btn-outline-secondary btn-sm me-2">취소</a>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>변경사항 저장</button>
                    </div>
                </form>

                <?php else: ?>
                <!-- 상세 보기 -->
                <h6 class="fw-bold mb-3"><i class="bi bi-building me-1"></i>신청 정보</h6>
                <?php
                $detail_fields = [
                    ['name', '병원명'], ['owner_name', '대표자명'], ['business_number', '사업자등록번호'],
                    ['phone', '전화번호'], ['email', '이메일'], ['contact_name', '담당자명'],
                    ['contact_phone', '담당자 전화'], ['address', '주소'], ['business_hours', '영업시간'],
                    ['categories', '진료과목'], ['treatment_tags', '주요 시술'],
                    ['website', '홈페이지'], ['sns_url', 'SNS'], ['introduction', '병원 소개'],
                ];
                foreach ($detail_fields as $df):
                    $val = $view_app[$df[0]] ?? '';
                    if (empty($val)) continue;
                ?>
                <div class="detail-row">
                    <div class="detail-label"><?= $df[1] ?></div>
                    <div class="detail-value">
                        <?php if ($df[0] === 'website' || $df[0] === 'sns_url'): ?>
                        <a href="<?= htmlspecialchars($val) ?>" target="_blank" class="text-decoration-none"><?= htmlspecialchars($val) ?> <i class="bi bi-box-arrow-up-right" style="font-size:0.7rem;"></i></a>
                        <?php elseif ($df[0] === 'email'): ?>
                        <a href="mailto:<?= htmlspecialchars($val) ?>" class="text-decoration-none"><?= htmlspecialchars($val) ?></a>
                        <?php elseif ($df[0] === 'introduction'): ?>
                        <div class="text-muted small"><?= nl2br(htmlspecialchars($val)) ?></div>
                        <?php else: ?>
                        <?= htmlspecialchars($val) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- 서류 -->
                <?php if (!empty($view_app['cert_business']) || !empty($view_app['cert_medical'])): ?>
                <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-file-earmark me-1"></i>첨부 서류</h6>
                <?php if (!empty($view_app['cert_business'])): ?>
                <div class="detail-row">
                    <div class="detail-label">사업자등록증</div>
                    <div class="detail-value"><i class="bi bi-file-check text-success me-1"></i>제출 완료</div>
                </div>
                <?php endif; ?>
                <?php if (!empty($view_app['cert_medical'])): ?>
                <div class="detail-row">
                    <div class="detail-label">의료기관허가증</div>
                    <div class="detail-value"><i class="bi bi-file-check text-success me-1"></i>제출 완료</div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ==================== 목록 모드 ==================== -->

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>내 신청 현황</h4>
    <a href="hospital_apply.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>새 신청</a>
</div>

<?php if (empty($applications)): ?>
<div class="card app-card">
    <div class="card-body text-center py-5">
        <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
        <h5 class="mt-3 text-muted">아직 신청 내역이 없습니다</h5>
        <p class="text-muted">병원 등록을 신청하시면 여기에서 진행 상황을 확인하고 수정할 수 있습니다.</p>
        <a href="hospital_apply.php" class="btn btn-primary mt-2"><i class="bi bi-pencil-square me-1"></i>병원 등록 신청하기</a>
    </div>
</div>
<?php else: ?>

<div class="row g-3">
    <?php foreach ($applications as $app):
        $st = $app['status'];
        $badge_class = match($st) {
            'pending' => 'bg-warning text-dark',
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            default => 'bg-secondary',
        };
        $status_text = match($st) {
            'pending' => '심사 중',
            'approved' => '승인',
            'rejected' => '반려',
            default => $st,
        };
        $status_icon = match($st) {
            'pending' => 'bi-hourglass-split',
            'approved' => 'bi-check-circle',
            'rejected' => 'bi-x-circle',
            default => 'bi-question-circle',
        };
        $days_ago = floor((time() - strtotime($app['applied_at'])) / 86400);
    ?>
    <div class="col-lg-6">
        <div class="card app-card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($app['name']) ?></h5>
                        <span class="text-muted small">#<?= $app['id'] ?> · <?= date('Y.m.d', strtotime($app['applied_at'])) ?> (<?= $days_ago ?>일 전)</span>
                    </div>
                    <span class="status-badge <?= $badge_class ?>"><i class="bi <?= $status_icon ?> me-1"></i><?= $status_text ?></span>
                </div>

                <div class="row small text-muted mb-3">
                    <div class="col-6"><i class="bi bi-person me-1"></i><?= htmlspecialchars($app['owner_name'] ?? '-') ?></div>
                    <div class="col-6"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($app['phone'] ?? '-') ?></div>
                    <div class="col-6 mt-1"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($app['email'] ?? '-') ?></div>
                    <div class="col-6 mt-1"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars(mb_substr($app['address'] ?? '-', 0, 20)) ?></div>
                </div>

                <?php if ($app['revision_count'] > 0): ?>
                <div class="small text-info mb-2"><i class="bi bi-journal-text me-1"></i>수정 <?= $app['revision_count'] ?>회</div>
                <?php endif; ?>

                <?php if ($st === 'rejected' && !empty($app['rejection_reason'])): ?>
                <div class="alert alert-danger py-1 px-2 small mb-2">
                    <strong>반려:</strong> <?= htmlspecialchars(mb_substr($app['rejection_reason'], 0, 50)) ?>
                </div>
                <?php endif; ?>

                <div class="d-flex gap-2 mt-auto">
                    <a href="?view=<?= $app['id'] ?>" class="btn btn-outline-primary btn-sm flex-grow-1"><i class="bi bi-eye me-1"></i>상세</a>
                    <?php if (in_array($st, ['pending', 'rejected'])): ?>
                    <a href="?view=<?= $app['id'] ?>&edit=1" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
