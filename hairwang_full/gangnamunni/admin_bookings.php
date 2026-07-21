<?php
/**
 * 예약 관리 (admin_bookings.php)
 * - 예약 목록 조회, 상태 변경(승인/거절/취소), 검색, 필터
 */
session_start();

$admin_page_title = '예약 관리';
$admin_current = 'bookings';

// DB 연결
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_filter.php'; // 병원 격리(super_admin=전체, 그 외=소속 병원)
require_once __DIR__ . '/includes/photo_upload_helper.php';
require_admin_login();
require_permission('bookings');

// 담당의사 배정 컬럼 보장 (없으면 추가) — idempotent
//   운영 DB에 컬럼이 없어도 배정/조회가 'Unknown column' 으로 실패하지 않도록 한다.
$_ensure_booking_cols = [
    'doctor_id'   => "ADD COLUMN doctor_id INT NULL AFTER hms_appointment_id",
    'doctor_name' => "ADD COLUMN doctor_name VARCHAR(100) NULL AFTER doctor_id",
];
foreach ($_ensure_booking_cols as $_col => $_ddl) {
    $_chk = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_bookings LIKE '{$_col}'");
    if ($_chk && mysqli_num_rows($_chk) === 0) {
        @mysqli_query($connect, "ALTER TABLE gangnamunni_bookings {$_ddl}");
    }
}

// ===== Action: 상태 변경 (viewer 역할은 불가) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // viewer 역할은 상태 변경 불가
    $admin_role = $_SESSION['ad_admin_role'] ?? 'viewer';
    if ($admin_role === 'viewer') {
        $msg = '뷰어 역할은 예약 상태를 변경할 수 없습니다.';
        $msg_type = 'danger';
        goto skip_action;
    }
    $id = intval($_POST['id'] ?? 0);
    $action = $_POST['action'];
    $allowed = ['confirm', 'cancel', 'complete', 'noshow'];
    // 병원 격리: super_admin 이 아니면 소속 병원 예약만 상태 변경 가능
    $_act_hin = get_hospital_filter_in($connect);
    if ($id > 0 && $_act_hin !== null) {
        $_own_r = mysqli_query($connect, "SELECT 1 FROM gangnamunni_bookings WHERE id={$id} AND hospital_id IN (" . $_act_hin . ") LIMIT 1");
        if (!$_own_r || mysqli_num_rows($_own_r) === 0) {
            $msg = '해당 예약에 대한 권한이 없습니다.';
            $msg_type = 'danger';
            goto skip_action;
        }
    }
    if ($id > 0 && in_array($action, $allowed)) {
        $status_map = ['confirm' => 'confirmed', 'cancel' => 'cancelled', 'complete' => 'completed', 'noshow' => 'no_show'];
        $new_status = $status_map[$action];
        $sql = "UPDATE gangnamunni_bookings SET status='" . mysqli_real_escape_string($connect, $new_status) . "'";
        if ($action === 'cancel') {
            $reason = mysqli_real_escape_string($connect, $_POST['cancel_reason'] ?? '');
            $sql .= ", cancel_reason='" . $reason . "', cancelled_at=NOW()";
        }
        $sql .= " WHERE id=" . $id;
        mysqli_query($connect, $sql);

        // === HMS 동기화: 예약 상태 변경 시 HMS appointments도 업데이트 ===
        $booking_row = null;
        $bk_r = mysqli_query($connect, "SELECT b.*, h.hms_hospital_id FROM gangnamunni_bookings b LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id WHERE b.id = {$id}");
        if ($bk_r) $booking_row = mysqli_fetch_assoc($bk_r);

        if ($booking_row && !empty($booking_row['hms_appointment_id'])) {
            $hms_apt_id = intval($booking_row['hms_appointment_id']);
            // HMS appointments.is_completed: 0=pending, 1=completed, 2=cancelled
            $hms_status_map = ['confirmed' => 0, 'completed' => 1, 'cancelled' => 2, 'no_show' => 2];
            $hms_is_completed = $hms_status_map[$new_status] ?? 0;
            mysqli_query($connect, "UPDATE appointments SET is_completed = {$hms_is_completed}, updated_at = NOW() WHERE id = {$hms_apt_id}");
        }

        // HMS에 hms_appointment_id가 없는데 hms_hospital_id가 있으면 실시간 API로 생성 시도
        if ($booking_row && empty($booking_row['hms_appointment_id']) && !empty($booking_row['hms_hospital_id']) && $new_status === 'confirmed') {
            // HMS 독립 모드면 HMS 전송 스킵
            if (!function_exists("is_hms_standalone") || !is_hms_standalone()) { require_once __DIR__ . "/includes/infyhms_api.php";
            try {
                $hms_api = get_infyhms_api();
                $apt_data = [
                    'patient_name'  => $booking_row['name'] ?? '',
                    'patient_phone' => $booking_row['phone'] ?? '',
                    'appointment_datetime' => ($booking_row['booking_date'] ?? date('Y-m-d')) . ' ' . ($booking_row['booking_time'] ?? '10:00') . ':00',
                    'problem'       => '[서울미녀 예약] 시술: ' . ($booking_row['treatment'] ?? ''),
                    'tenant_id'     => $booking_row['hms_hospital_id'],
                ];
                $hms_result = $hms_api->createAppointment($apt_data);
                if (!empty($hms_result['success']) && !empty($hms_result['data']['id'])) {
                    $new_hms_id = intval($hms_result['data']['id']);
                    mysqli_query($connect, "UPDATE gangnamunni_bookings SET hms_appointment_id = {$new_hms_id} WHERE id = {$id}");
                }
            } catch (Exception $e) {
                error_log("[admin_bookings] HMS API 전송 실패: " . $e->getMessage());
            }
            } // end standalone check
        }

        // === FCM 알림: 예약 상태 변경 시 관리자 앱으로 푸시 ===
        if (file_exists(__DIR__ . '/includes/admin_fcm.php')) {
            require_once __DIR__ . '/includes/admin_fcm.php';
            if (is_fcm_enabled($connect) && $booking_row) {
                $fcm_title_map = [
                    'confirmed' => '예약 확정',
                    'cancelled' => '예약 취소',
                    'completed' => '예약 완료',
                    'no_show'   => '노쇼 처리'
                ];
                $fcm_title = ($fcm_title_map[$new_status] ?? '예약 상태 변경') . ' - ' . ($booking_row['name'] ?? '');
                $fcm_body = ($booking_row['hospital_name'] ?? '') . ' | ' . ($booking_row['booking_date'] ?? '') . ' ' . substr($booking_row['booking_time'] ?? '', 0, 5);

                // 관리자 토큰 가져오기 (병원 관련 관리자)
                $tokens_r = mysqli_query($connect, "SELECT token FROM gangnamunni_admin_fcm_tokens WHERE is_active = 1");
                if ($tokens_r) {
                    while ($tk = mysqli_fetch_assoc($tokens_r)) {
                        @send_fcm_notification($connect, $tk['token'], $fcm_title, $fcm_body, [
                            'type' => 'booking_status',
                            'booking_id' => (string)$id,
                            'status' => $new_status,
                            'click_action' => 'admin_bookings.php'
                        ]);
                    }
                }
            }
        }

        $msg = '예약 상태가 변경되었습니다.';
        $msg_type = 'success';
    }

    // ===== Action: 담당의사 배정 / 해제 =====
    if ($id > 0 && $action === 'assign_doctor') {
        $doctor_id = intval($_POST['doctor_id'] ?? 0);
        // 예약의 병원 확인 (배정 의사가 같은 병원 소속인지 검증용)
        $bk_h = mysqli_query($connect, "SELECT hospital_id FROM gangnamunni_bookings WHERE id={$id} LIMIT 1");
        $bk_hosp = $bk_h ? (int)(mysqli_fetch_assoc($bk_h)['hospital_id'] ?? 0) : 0;
        if ($doctor_id > 0) {
            $doc_r = mysqli_query($connect, "SELECT name, hospital_id FROM gangnamunni_doctors WHERE id={$doctor_id} LIMIT 1");
            $doc = $doc_r ? mysqli_fetch_assoc($doc_r) : null;
            if ($doc && (int)$doc['hospital_id'] === $bk_hosp) {
                $dn = mysqli_real_escape_string($connect, $doc['name']);
                mysqli_query($connect, "UPDATE gangnamunni_bookings SET doctor_id={$doctor_id}, doctor_name='{$dn}' WHERE id={$id}");
                $msg = '담당의사가 배정되었습니다.';
                $msg_type = 'success';
            } else {
                $msg = '선택한 의사가 해당 예약의 병원 소속이 아닙니다.';
                $msg_type = 'danger';
            }
        } else {
            // 미배정 선택 → 배정 해제
            mysqli_query($connect, "UPDATE gangnamunni_bookings SET doctor_id=NULL, doctor_name=NULL WHERE id={$id}");
            $msg = '담당의사 배정이 해제되었습니다.';
            $msg_type = 'success';
        }
    }
}
skip_action:

// ===== Filters =====
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';
$search = $_GET['q'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// ===== Query =====
$where = "1=1";
if ($filter_status) $where .= " AND b.status='" . mysqli_real_escape_string($connect, $filter_status) . "'";
if ($filter_date) $where .= " AND b.booking_date='" . mysqli_real_escape_string($connect, $filter_date) . "'";
if ($search) {
    $s = mysqli_real_escape_string($connect, $search);
    $where .= " AND (b.name LIKE '%" . $s . "%' OR b.phone LIKE '%" . $s . "%' OR b.hospital_name LIKE '%" . $s . "%')";
}

// ===== 병원 격리: super_admin 은 전체, 그 외 관리자는 소속 병원 예약만 =====
//   get_hospital_filter_in(): null=전체, "1,2,3"=해당 병원, '0'=소속 병원 없음(아무것도 안 보임)
$hosp_in = get_hospital_filter_in($connect);
$hosp_and = ($hosp_in !== null) ? " AND hospital_id IN (" . $hosp_in . ")" : "";
if ($hosp_and) $where .= $hosp_and;

$count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_bookings b WHERE " . $where;
$count_result = mysqli_query($connect, $count_sql);
$total = $count_result ? mysqli_fetch_assoc($count_result)['cnt'] : 0;
$total_pages = max(1, ceil($total / $per_page));

$sql = "SELECT b.* FROM gangnamunni_bookings b WHERE " . $where . " ORDER BY b.created_at DESC LIMIT " . $per_page . " OFFSET " . $offset;
$result = mysqli_query($connect, $sql);
$bookings = [];
if ($result) while ($row = mysqli_fetch_assoc($result)) $bookings[] = $row;

// ===== 담당의사 배정용: 현재 페이지 예약들의 병원 소속 활성 의사 목록 =====
$doctors_by_hospital = [];
$_doc_hids = [];
foreach ($bookings as $_b) { $_hid = (int)($_b['hospital_id'] ?? 0); if ($_hid > 0) $_doc_hids[$_hid] = true; }
if ($_doc_hids) {
    $_hid_list = implode(',', array_keys($_doc_hids));
    $doc_res = mysqli_query($connect, "SELECT id, name, hospital_id FROM gangnamunni_doctors WHERE hospital_id IN ({$_hid_list}) AND status='active' ORDER BY name ASC");
    if ($doc_res) while ($d = mysqli_fetch_assoc($doc_res)) {
        $doctors_by_hospital[(int)$d['hospital_id']][] = ['id' => (int)$d['id'], 'name' => $d['name']];
    }
}

// ===== Stats =====
$stats = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'completed' => 0, 'today' => 0];
$stats_where = ($hosp_in !== null) ? " WHERE hospital_id IN (" . $hosp_in . ")" : "";
$stats_r = mysqli_query($connect, "SELECT status, COUNT(*) as cnt FROM gangnamunni_bookings" . $stats_where . " GROUP BY status");
if ($stats_r) while ($r = mysqli_fetch_assoc($stats_r)) { $stats[$r['status']] = $r['cnt']; $stats['total'] += $r['cnt']; }
$today_r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_bookings WHERE booking_date=CURDATE()" . $hosp_and);
if ($today_r) $stats['today'] = mysqli_fetch_assoc($today_r)['cnt'];

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (isset($connect) && $connect) loadThemeSettings($connect);
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <!-- 알림 -->
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show">
        <?php echo $msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- 헤더 -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-calendar-check me-2"></i>예약 관리</h4>
        <a href="admin_appointment_wizard.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>새 예약 등록</a>
    </div>

    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <?php
        $stat_cards = [
            ['전체', $stats['total'], 'bi-calendar-range', 'primary'],
            ['대기중', $stats['pending'], 'bi-clock', 'warning'],
            ['확정', $stats['confirmed'], 'bi-check-circle', 'success'],
            ['완료', $stats['completed'], 'bi-check2-all', 'info'],
            ['취소', $stats['cancelled'], 'bi-x-circle', 'danger'],
            ['오늘 예약', $stats['today'], 'bi-calendar-day', 'dark'],
        ];
        foreach ($stat_cards as $sc):
        ?>
        <div class="col-6 col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <i class="bi <?php echo $sc[2]; ?> text-<?php echo $sc[3]; ?>" style="font-size:1.5rem;"></i>
                    <h5 class="mb-0 mt-1"><?php echo number_format($sc[1]); ?></h5>
                    <small class="text-muted"><?php echo $sc[0]; ?></small>
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
                    <label class="form-label mb-0 small">상태</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">전체</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>대기중</option>
                        <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>확정</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>완료</option>
                        <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>취소</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-0 small">예약일</label>
                    <input type="date" name="date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-0 small">검색</label>
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="이름, 전화번호, 병원명" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-search me-1"></i>검색</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 예약 목록 -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px">#</th>
                        <th>예약자</th>
                        <th>전화번호</th>
                        <th>병원</th>
                        <th>시술</th>
                        <th>예약일</th>
                        <th>예약시간</th>
                        <th>사진</th>
                        <th>상태</th>
                        <th>담당의사</th>
                        <th style="width:150px">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                    <tr><td colspan="11" class="text-center py-5 text-muted"><i class="bi bi-inbox" style="font-size:2rem;"></i><br>예약 내역이 없습니다.</td></tr>
                    <?php else: $is_viewer = (($_SESSION['ad_admin_role'] ?? '') === 'viewer'); foreach ($bookings as $b): ?>
                    <tr>
                        <td><?php echo $b['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($b['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($b['phone']); ?></td>
                        <td><?php echo htmlspecialchars($b['hospital_name']); ?></td>
                        <td><?php echo htmlspecialchars($b['treatment']); ?></td>
                        <td><?php echo $b['booking_date']; ?></td>
                        <td><?php echo substr($b['booking_time'], 0, 5); ?></td>
                        <td><?php echo render_photo_thumbnails($b['photo_urls'] ?? null, 36); ?></td>
                        <td>
                            <?php
                            $badge_map = ['pending' => 'warning', 'confirmed' => 'success', 'completed' => 'info', 'cancelled' => 'danger', 'no_show' => 'secondary'];
                            $label_map = ['pending' => '대기중', 'confirmed' => '확정', 'completed' => '완료', 'cancelled' => '취소', 'no_show' => '노쇼'];
                            $st = $b['status'] ?? 'pending';
                            ?>
                            <span class="badge bg-<?php echo $badge_map[$st] ?? 'secondary'; ?>"><?php echo $label_map[$st] ?? $st; ?></span>
                        </td>
                        <td>
                            <?php $dn = trim($b['doctor_name'] ?? ''); ?>
                            <?php if ($dn !== ''): ?>
                            <span class="badge bg-light text-dark border"><i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($dn); ?></span>
                            <?php else: ?>
                            <span class="text-muted small">미배정</span>
                            <?php endif; ?>
                            <?php if (!$is_viewer): ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm ms-1" title="담당의사 배정"
                                    onclick="assignDoctor(<?php echo $b['id']; ?>, <?php echo (int)($b['hospital_id'] ?? 0); ?>, <?php echo (int)($b['doctor_id'] ?? 0); ?>)">
                                <i class="bi bi-person-plus"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_viewer): ?>
                            <span class="text-muted small">보기 전용</span>
                            <?php elseif ($st === 'pending'): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                                <input type="hidden" name="action" value="confirm">
                                <button class="btn btn-success btn-sm" title="승인"><i class="bi bi-check-lg"></i></button>
                            </form>
                            <button class="btn btn-danger btn-sm" title="거절" onclick="cancelBooking(<?php echo $b['id']; ?>)"><i class="bi bi-x-lg"></i></button>
                            <?php elseif ($st === 'confirmed'): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                                <input type="hidden" name="action" value="complete">
                                <button class="btn btn-info btn-sm text-white" title="완료"><i class="bi bi-check2-all"></i></button>
                            </form>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?php echo $b['id']; ?>">
                                <input type="hidden" name="action" value="noshow">
                                <button class="btn btn-secondary btn-sm" title="노쇼"><i class="bi bi-person-x"></i></button>
                            </form>
                            <?php else: ?>
                            <span class="text-muted small">-</span>
                            <?php endif; ?>
                            <?php if ($b['memo']): ?>
                            <button class="btn btn-outline-secondary btn-sm" title="메모" data-bs-toggle="tooltip" data-bs-title="<?php echo htmlspecialchars($b['memo']); ?>"><i class="bi bi-chat-text"></i></button>
                            <?php endif; ?>
                            <?php // 취소 사유: 사용자/관리자가 남긴 취소 사유 보기 (병원 격리로 소속 병원 예약만 노출됨) ?>
                            <?php if (in_array($st, ['cancelled', 'no_show'], true) && trim($b['cancel_reason'] ?? '') !== ''): ?>
                            <button type="button" class="btn btn-outline-danger btn-sm" title="취소 사유 보기"
                                    onclick="showCancelReason(this)"
                                    data-reason="<?php echo htmlspecialchars($b['cancel_reason'], ENT_QUOTES); ?>">
                                <i class="bi bi-info-circle"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 페이지네이션 -->
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white border-top">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filter_status); ?>&date=<?php echo urlencode($filter_date); ?>&q=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 취소 모달 -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">예약 취소</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="cancel_id">
                    <input type="hidden" name="action" value="cancel">
                    <div class="mb-3">
                        <label class="form-label">취소 사유</label>
                        <textarea name="cancel_reason" class="form-control" rows="3" placeholder="취소 사유를 입력하세요"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                    <button type="submit" class="btn btn-danger">취소 확정</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 담당의사 배정 모달 -->
<div class="modal fade" id="assignDoctorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">담당의사 배정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="assign_booking_id">
                    <input type="hidden" name="action" value="assign_doctor">
                    <div class="mb-2">
                        <label class="form-label">담당의사</label>
                        <select name="doctor_id" id="assign_doctor_select" class="form-select">
                            <option value="0">— 미배정 —</option>
                        </select>
                        <small class="text-muted">해당 예약 병원 소속의 활성 의사만 표시됩니다.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                    <button type="submit" class="btn btn-primary">배정</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 취소 사유 보기 모달 -->
<div class="modal fade" id="cancelReasonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-x-circle text-danger me-2"></i>취소 사유</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="cancelReasonText" class="mb-0" style="white-space:pre-wrap;word-break:break-word;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<script>
const DOCTORS_BY_HOSPITAL = <?php echo json_encode($doctors_by_hospital, JSON_UNESCAPED_UNICODE); ?>;
function showCancelReason(btn) {
    // textContent 로 넣어 HTML 인젝션 방지
    document.getElementById('cancelReasonText').textContent = btn.dataset.reason || '(사유 없음)';
    new bootstrap.Modal(document.getElementById('cancelReasonModal')).show();
}
function cancelBooking(id) {
    document.getElementById('cancel_id').value = id;
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}
function assignDoctor(bookingId, hospitalId, currentDoctorId) {
    document.getElementById('assign_booking_id').value = bookingId;
    const sel = document.getElementById('assign_doctor_select');
    sel.innerHTML = '<option value="0">— 미배정 —</option>';
    const list = DOCTORS_BY_HOSPITAL[hospitalId] || [];
    list.forEach(d => {
        const opt = document.createElement('option');
        opt.value = d.id;
        opt.textContent = d.name;
        if (d.id === currentDoctorId) opt.selected = true;
        sel.appendChild(opt);
    });
    if (list.length === 0) {
        const opt = document.createElement('option');
        opt.textContent = '등록된 활성 의사가 없습니다';
        opt.disabled = true;
        sel.appendChild(opt);
    }
    new bootstrap.Modal(document.getElementById('assignDoctorModal')).show();
}
// Tooltip init
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
