<?php
/**
 * 내 예약 조회 페이지
 * - 전화번호 기반 예약 조회
 * - 로그인 시 자동 입력
 * - 진행중/지난 예약 2탭
 * - 예약 취소/날짜변경 기능
 */
require_once __DIR__ . '/includes/init.php';
// [2026-06-15] mypage.php 와 동일한 회원 로그인 체계 — SSO/회원세션 인식 (init.php에도 있지만 명시적 보강)
if (isset($connect) && $connect && file_exists(__DIR__ . '/includes/sso_auto_login.php')) {
    @include_once __DIR__ . '/includes/sso_auto_login.php';
}
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

// [2026-06-15] mypage.php 와 100% 동일한 로그인 판정 — 그누보드 전역변수 직접 확인
//   함수(is_gangnamunni_logged_in) 대신 $is_member/$member 를 직접 봐서 mypage 와 동일하게 동작
global $is_member, $member;
$is_user_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
$logged_member = $is_user_logged_in ? $member : [];

// [2026-06-15] 예약조회 로그인 필수 (booking_require_login 설정, 기본 ON / booking_phone_auth=1이면 전화번호조회 허용)
if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) { require_once __DIR__ . '/includes/settings_helper.php'; }
if (function_exists('getSettingValue')) {
    $_require_login = getSettingValue($connect, 'booking_require_login', '1') === '1';
    $_phone_auth    = getSettingValue($connect, 'booking_phone_auth', '0') === '1';
    if ($_require_login && !$_phone_auth && !$is_user_logged_in) {
        header('Location: /hairwang/bbs/login.php?url=' . urlencode('/hairwang/gangnamunni/my_bookings.php'));
        exit;
    }
}
$logged_mb_id = $logged_member['mb_id'] ?? '';

$auto_phone = '';
if (!empty($logged_member['mb_hp'])) {
    $auto_phone = $logged_member['mb_hp'];
} elseif (!empty($_SESSION['user_phone'])) {
    $auto_phone = $_SESSION['user_phone'];
}

// 예약 조회 (전화번호 또는 mb_id)
$search_phone = trim($_GET['phone'] ?? $auto_phone);
$bookings = [];
$active_bookings = [];
$past_bookings = [];
// [Figma Sync - 2026-06-06] 3탭 분리 (시술예정/시술완료/예약취소)
$upcoming_bookings = [];
$completed_bookings = [];
$cancelled_bookings = []; 

if (!empty($search_phone)) {
    // 전화번호 기반 조회
    $clean_phone = preg_replace('/[^0-9]/', '', $search_phone);
    $stmt = $connect->prepare("SELECT b.*, h.profile_image_url as hospital_image
        FROM gangnamunni_bookings b
        LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
        WHERE REPLACE(REPLACE(b.phone, '-', ''), ' ', '') = ?
        ORDER BY b.created_at DESC
        LIMIT 50");
    $stmt->bind_param('s', $clean_phone);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
        if (in_array($row['status'], ['pending', 'confirmed'])) {
            $active_bookings[] = $row;
            $upcoming_bookings[] = $row;
        } elseif (in_array($row['status'], ['cancelled', 'no_show'])) {
            $past_bookings[] = $row;
            $cancelled_bookings[] = $row;
        } else {
            $past_bookings[] = $row;
            $completed_bookings[] = $row;
        }
    }
    $stmt->close();
} elseif ($is_user_logged_in && !empty($logged_mb_id)) {
    // 전화번호 없지만 로그인됨 → mb_id로 조회
    $stmt = $connect->prepare("SELECT b.*, h.profile_image_url as hospital_image
        FROM gangnamunni_bookings b
        LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
        WHERE b.mb_id = ?
        ORDER BY b.created_at DESC
        LIMIT 50");
    $stmt->bind_param('s', $logged_mb_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
        if (in_array($row['status'], ['pending', 'confirmed'])) {
            $active_bookings[] = $row;
            $upcoming_bookings[] = $row;
        } elseif (in_array($row['status'], ['cancelled', 'no_show'])) {
            $past_bookings[] = $row;
            $cancelled_bookings[] = $row;
        } else {
            $past_bookings[] = $row;
            $completed_bookings[] = $row;
        }
    }
    $stmt->close();
}

if (!function_exists('getSettingValue')) {
    require_once __DIR__ . '/includes/settings_helper.php';
}

// 시술완료 예약별 후기 상태 (후기 작성/승인대기/공개 버튼 분기용)
require_once __DIR__ . '/includes/review_booking.php';
$completed_ids = [];
foreach ($past_bookings as $_pb) { if (($_pb['status'] ?? '') === 'completed') $completed_ids[] = (int)$_pb['id']; }
$review_map = getBookingReviewsMap($connect, $completed_ids);

$page_title = '내 예약';
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --color-primary: #AE1932;
            --color-bg: #ffffff;
            --color-bg-light: #f8f9fa;
            --color-text: #222;
            --color-text-secondary: #666;
            --color-border: #eee;
        }
        [data-bs-theme="dark"] {
            --color-bg: #1a1a2e;
            --color-bg-light: #16213e;
            --color-text: #e0e0e0;
            --color-text-secondary: #aaa;
            --color-border: #333;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--color-bg); color: var(--color-text); padding-bottom: 80px; }
        .container { max-width: 640px; margin: 0 auto; }

        .header{position:relative;padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
        .header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
        .header h1{position:absolute;left:50%;transform:translateX(-50%);font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;}


        /* 검색 */

        /* 탭 */
        .tabs { display: flex; background: var(--color-bg); border-bottom: 1px solid var(--color-border); }
        .tab-btn { flex: 1; padding: 12px; text-align: center; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; border: none; background: none; cursor: pointer; color: var(--color-text-secondary); border-bottom: 2px solid transparent; }
        .tab-btn.active { color: var(--color-primary); border-bottom-color: var(--color-primary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* 예약 카드 */
        .booking-card { background: var(--color-bg); margin: 8px 12px; padding: 16px; }
        .booking-header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .booking-hospital-img { width: 40px; height: 40px; border-radius: 8px; background: var(--color-bg-light); display: flex; align-items: center; justify-content: center; font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); overflow: hidden; flex-shrink: 0; }
        .booking-hospital-img img { width: 100%; height: 100%; object-fit: cover; }
        .booking-hospital-name, .booking-date { font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; }
        .booking-status { display: inline-block; font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; margin-top: 10px; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { color: #991b1b;}
        .status-no_show { background: #f3f4f6; color: #6b7280; }

        .booking-detail-row { display: flex; align-items: center; gap: 8px; }
        .booking-detail { display: flex; align-items: center; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color: #fff; line-height: 1.8; background: #AE1932; width: 60%; min-width: 0; }
        /* 시술완료/예약취소: detail-1(위)·detail-2(아래) 세로 배치 */
        .booking-detail.bd-stacked { flex-direction: column; align-items: flex-start; gap: 2px; padding: 6px 12px; }
        /* 시술완료: 회색 배경 + 어두운 글자 */
        .booking-detail.bd-done { background: #f2f2f2; }
        .booking-detail.bd-done .detail-1, .booking-detail.bd-done .detail-2 { color: #333; }
        .bd-left { padding-left: 12px; flex: 1; min-width: 0; }
        .bd-right { padding-right: 12px; font-size: 18px; font-weight: 800; white-space: nowrap; flex-shrink: 0; }
        .booking-detail i { width: 18px; display: inline-block; }
        .detail-1 { font-size: 15px !important; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .detail-2 { font-size: 13px !important; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .detail-btns { display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .detail-btn { padding: 8px 4px; border-radius: 6px; font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; cursor: pointer; text-align: center; border: none; background: var(--color-bg); color: var(--color-text); flex: 1; text-decoration: none; display: block; }
        @media (max-width: 399px) { .detail-btns { flex-direction: column; } }
        @media (min-width: 400px) { .detail-btns { flex-direction: row; } }

        .booking-actions { display: flex; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--color-border); }
        .action-btn { flex: 1; padding: 8px; border-radius: 6px; font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; cursor: pointer; text-align: center; border: 1px solid var(--color-border); background: var(--color-bg-light); color: var(--color-text); }
        .action-btn.cancel { color: var(--color-primary, #AE1932); border-color: #fecaca; }
        .action-btn.edit { color: var(--color-primary); border-color: #fed7aa; }

        .cancel-reason { font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--color-primary, #AE1932); margin-top: 8px; padding: 6px 10px; background: #fef2f2; border-radius: 6px; }

        /* 빈 상태 */
        .empty-state { text-align: center; padding: 60px 20px; color: var(--color-text-secondary); }
        .empty-icon { font-size: calc(48px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom: 12px; }

        /* 모달 */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: var(--color-bg); border-radius: 16px; width: 90%; max-width: 400px; padding: 24px; }
        .modal-title { font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; margin-bottom: 16px; }
        .modal-input { width: 100%; padding: 10px 12px; border: 1px solid var(--color-border); border-radius: 8px; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); margin-bottom: 10px; background: var(--color-bg-light); color: inherit; }
        .modal-btns { display: flex; gap: 8px; margin-top: 16px; }
        .modal-btn { flex: 1; padding: 10px; font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; cursor: pointer; border: none; }
        .modal-btn-cancel { background: var(--color-bg-light); color: var(--color-text); border: 1px solid var(--color-border); }
        .modal-btn-confirm { background: var(--color-primary); color: white; }
        .modal-btn-danger { background: var(--color-primary, #AE1932); color: white; }
        .action-btn.hospital { color: #2563eb; border-color: #bfdbfe; text-decoration: none; }
    </style>
    <script>
        (function(){
            var t = localStorage.getItem('theme');
            if (t === 'dark') document.documentElement.setAttribute('data-bs-theme', 'dark');
        })();
    </script>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="header"><button class="back" onclick="location.href='mypage.php'"><i class="bi bi-chevron-left"></i></button><h1>내 예약/결제 내역</h1></div>

<script>
console.group('[my_bookings] Debug Info');
console.log('logged_in:', <?php echo json_encode($is_user_logged_in); ?>, '<?php echo $is_user_logged_in ? "로그인" : "비로그인"; ?>');
console.log('mb_id:', <?php echo json_encode($logged_mb_id); ?>);
console.log('search_phone:', <?php echo json_encode($search_phone); ?>);
console.log('auto_phone:', <?php echo json_encode($auto_phone); ?>);
console.log('total_bookings:', <?php echo count($bookings); ?>);
console.log('active:', <?php echo count($active_bookings); ?>, 'past:', <?php echo count($past_bookings); ?>);
<?php foreach ($bookings as $i => $b): ?>
console.log('booking[<?php echo $i; ?>]:', {id:<?php echo $b['id']; ?>, hospital_id:<?php echo (int)$b['hospital_id']; ?>, status:'<?php echo $b['status']; ?>', date:'<?php echo $b['booking_date']; ?>', hospital:'<?php echo addslashes($b['hospital_name']); ?>'});
<?php endforeach; ?>
console.groupEnd();
</script>

<div class="container">
    <!-- 검색 (비로그인 시 또는 다른 번호 조회 시) -->
    <?php if (!$is_user_logged_in || isset($_GET['phone'])): ?>
   
    <?php endif; ?>

    <?php if (!empty($bookings)): ?>
    <!-- 탭 -->
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('active')">시술예정</button>
        <button class="tab-btn" onclick="switchTab('completed')">시술완료</button>
        <button class="tab-btn" onclick="switchTab('cancelled')">예약취소</button>
    </div>

    <!-- 진행중 -->
    <div class="tab-content active" id="tab-active">
        <?php if (empty($active_bookings)): ?>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <div>진행중인 예약이 없습니다.</div>
        </div>
        <?php else: ?>
        <?php foreach ($active_bookings as $bk): ?>
        <div class="booking-card" id="card-<?php echo $bk['id']; ?>" data-status="<?php echo $bk['status']; ?>">
            <div class="booking-header">
                <div>
                    <div class="booking-date"><?php
                        $_days_ko = ['일','월','화','수','목','금','토'];
                        $_ts = strtotime($bk['booking_date'] . ' ' . $bk['booking_time']);
                        $_dow = $_days_ko[(int)date('w', $_ts)];
                        $_h = (int)date('G', $_ts);
                        $_ampm = $_h < 12 ? '오전' : '오후';
                        $_h12 = $_h % 12; if ($_h12 === 0) $_h12 = 12;
                        echo date('Y.n.j', $_ts) . '(' . $_dow . ') ' . $_ampm . ' ' . $_h12 . ':' . date('i', $_ts);
                    ?></div>
                </div>
            </div>
            <div class="booking-detail-row">
                <div class="booking-detail">
                    <div class="bd-left">
                        <?php if (!empty($bk['treatment'])): ?>
                        <div class="detail-1"><?php echo htmlspecialchars($bk['treatment']); ?></div>
                        <?php endif; ?>
                        <div class="detail-2"><?php echo htmlspecialchars($bk['hospital_name']); ?></div>
                    </div>
                    <div class="bd-right"><?php
                        $_dd = (int)(new DateTime('today'))->diff(new DateTime($bk['booking_date']))->format('%r%a');
                        echo $_dd > 0 ? 'D-' . $_dd : ($_dd === 0 ? 'D-DAY' : 'D+' . abs($_dd));
                    ?></div>
                </div>
                <div class="detail-btns">
                    <a href="booking_detail.php?booking_id=<?php echo $bk['id']; ?>" class="detail-btn">예약변경</a>
                    <button type="button" class="detail-btn" onclick="openCancelModal(<?php echo $bk['id']; ?>)">예약취소</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 지난 예약 (시술완료/예약취소) - JS로 필터링 -->
    <div class="tab-content" id="tab-past">
        <div class="empty-state empty-state-dynamic" style="display:none;">
            <div class="empty-icon">📦</div>
            <div>해당 항목이 없습니다.</div>
        </div>
        <?php foreach ($past_bookings as $bk):
            $status_class = 'status-' . $bk['status'];
            $status_labels = ['completed'=>'완료','cancelled'=>'취소완료','no_show'=>'노쇼'];
            $status_label = $status_labels[$bk['status']] ?? $bk['status'];
        ?>
        <div class="booking-card" data-status="<?php echo $bk['status']; ?>">
            <div class="booking-header">
                <div>
                    <div class="booking-date"><?php
                        $_days_ko = ['일','월','화','수','목','금','토'];
                        $_is_cancelled = in_array($bk['status'], ['cancelled', 'no_show']);
                        $_dt_source = $_is_cancelled ? $bk['created_at'] : ($bk['booking_date'] . ' ' . $bk['booking_time']);
                        $_ts = strtotime($_dt_source);
                        $_dow = $_days_ko[(int)date('w', $_ts)];
                        $_h = (int)date('G', $_ts);
                        $_ampm = $_h < 12 ? '오전' : '오후';
                        $_h12 = $_h % 12; if ($_h12 === 0) $_h12 = 12;
                        echo date('Y.n.j', $_ts) . '(' . $_dow . ') ' . $_ampm . ' ' . $_h12 . ':' . date('i', $_ts);
                    ?></div>
                    <?php if ($_is_cancelled): // 시술완료(completed)엔 상태뱃지 숨김, 예약취소/노쇼만 표시 ?>
                    <span class="booking-status <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="booking-detail-row">
                <div class="booking-detail bd-stacked<?php echo $bk['status'] === 'completed' ? ' bd-done' : ''; ?>">
                    <?php if (!empty($bk['treatment'])): ?>
                    <div class="detail-1"><?php echo htmlspecialchars($bk['treatment']); ?></div>
                    <?php endif; ?>
                    <div class="detail-2"><?php echo htmlspecialchars($bk['hospital_name']); ?></div>
                </div>
                <div class="detail-btns">
                    <?php if ($bk['status'] === 'completed'): ?>
                    <?php
                        $_rv = $review_map[(int)$bk['id']] ?? null;
                        $_rs = bookingReviewState($_rv);
                    ?>
                    <?php if ($_rs['state'] === 'none'): ?>
                    <a href="review_write.php?booking_id=<?php echo (int)$bk['id']; ?>" class="detail-btn" style="background:var(--color-primary,#AE1932);color:#fff;">★ <?php echo term('review', '후기'); ?> 작성</a>
                    <?php elseif ($_rs['state'] === 'published'): ?>
                    <a href="review_detail.php?id=<?php echo (int)$_rv['id']; ?>" class="detail-btn"><?php echo htmlspecialchars($_rs['label']); ?></a>
                    <?php elseif ($_rs['state'] === 'rejected'): ?>
                    <a href="review_write.php?id=<?php echo (int)$_rv['id']; ?>" class="detail-btn" style="background:var(--color-primary,#AE1932);color:#fff;">★ <?php echo term('review', '후기'); ?> 다시 작성</a>
                    <?php else: ?>
                    <span class="detail-btn" style="opacity:.6;">⏳ <?php echo htmlspecialchars($_rs['label']); ?></span>
                    <?php endif; ?>
                    <a href="booking.php?id=<?php echo $bk['hospital_id']; ?>" class="detail-btn">다시예약</a>
                    <?php else: ?>
                    <a href="again_booking.php?booking_id=<?php echo $bk['id']; ?>" class="detail-btn">상세보기</a>
                    <a href="booking.php?id=<?php echo $bk['hospital_id']; ?>" class="detail-btn">다시예약</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php elseif (!empty($search_phone)): ?>
    <div class="empty-state">
        <div class="empty-icon">🔍</div>
        <div>해당 번호로 등록된 예약이 없습니다.</div>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="empty-icon">📱</div>
        <?php if ($is_user_logged_in): ?>
        <div>예약 내역이 없습니다.<br><a href="hospitals.php" style="color:var(--color-primary);font-weight:600;"><?php echo term('business', '병원'); ?> 찾아보기</a></div>
        <?php else: ?>
        <div>로그인하면 예약 내역을<br>자동으로 조회합니다.<br><br><a href="/bbs/login.php" style="color:var(--color-primary);font-weight:600;">로그인하기</a></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 취소 확인 모달 -->
<div class="modal-overlay" id="cancelModal">
    <div class="modal-box">
        <div class="modal-title">예약 취소</div>
        <p style="font-size:14px;color:var(--color-text);line-height:1.6;margin-bottom:18px;">예약 취소를 요청하시겠습니까?</p>
        <input type="hidden" id="cancelBookingId">
        <!-- 취소 사유(선택) → cancel_reason 으로 전송되어 관리자에게 표시됨 -->
        <textarea class="modal-input" id="cancelReason" rows="3" placeholder="취소 사유 (선택 · 관리자 전달용)"></textarea>
        <div class="modal-btns">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal('cancelModal')">아니오</button>
            <button class="modal-btn modal-btn-danger" onclick="submitCancel()">네</button>
        </div>
    </div>
</div>

<!-- 날짜변경 모달 -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-title">예약 날짜 변경</div>
        <input type="hidden" id="editBookingId">
        <label style="font-size:13px;font-weight:600;">새 날짜</label>
        <input type="date" class="modal-input" id="editDate" min="<?php echo date('Y-m-d'); ?>">
        <label style="font-size:13px;font-weight:600;">새 시간</label>
        <input type="time" class="modal-input" id="editTime">
        <label style="font-size:13px;font-weight:600;">변경 사유</label>
        <input type="text" class="modal-input" id="editReason" placeholder="변경 사유 (선택)">
        <div class="modal-btns">
            <button class="modal-btn modal-btn-cancel" onclick="closeModal('editModal')">닫기</button>
            <button class="modal-btn modal-btn-confirm" onclick="submitEdit()">변경하기</button>
        </div>
    </div>
</div>

<script>
var searchPhone = '<?php echo addslashes($search_phone); ?>';

function switchTab(tab) {
    // [Figma Sync 2026-06-06] 3탭: active(시술예정) / completed(시술완료) / cancelled(예약취소)
    var tabOrder = ['active','completed','cancelled'];
    var activeIdx = tabOrder.indexOf(tab);
    document.querySelectorAll('.tab-btn').forEach(function(b,i) { b.classList.toggle('active', i===activeIdx); });
    document.getElementById('tab-active').classList.toggle('active', tab==='active');
    var pastEl = document.getElementById('tab-past');
    if (pastEl) {
        pastEl.classList.toggle('active', tab==='completed' || tab==='cancelled');
        // 카드별 status로 필터링
        pastEl.querySelectorAll('.booking-card').forEach(function(card){
            var status = card.getAttribute('data-status') || '';
            var isCancelled = (status === 'cancelled' || status === 'no_show');
            var show = (tab==='completed' && !isCancelled) || (tab==='cancelled' && isCancelled);
            card.style.display = show ? '' : 'none';
        });
        // 빈 메시지 처리
        var visible = pastEl.querySelectorAll('.booking-card:not([style*="display: none"])').length;
        var emptyEl = pastEl.querySelector('.empty-state-dynamic');
        if (emptyEl) emptyEl.style.display = visible === 0 ? 'block' : 'none';
    }
}

function openCancelModal(id) {
    document.getElementById('cancelBookingId').value = id;
    // cancelReason 은 확인 팝업(네/아니오)으로 바뀌며 주석 처리됨 → 있을 때만 초기화
    var rEl = document.getElementById('cancelReason');
    if (rEl) rEl.value = '';
    document.getElementById('cancelModal').classList.add('active');
}

function openEditModal(id, date, time) {
    document.getElementById('editBookingId').value = id;
    document.getElementById('editDate').value = date;
    document.getElementById('editTime').value = time;
    document.getElementById('editReason').value = '';
    document.getElementById('editModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function submitCancel() {
    var id = document.getElementById('cancelBookingId').value;
    var reasonEl = document.getElementById('cancelReason');
    var reason = reasonEl ? reasonEl.value : '';
    var fd = new FormData();
    fd.append('booking_id', id);
    fd.append('phone', searchPhone);
    fd.append('cancel_reason', reason);

    fetch('api/cancel_booking.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(function() { alert('네트워크 오류가 발생했습니다.'); });

    closeModal('cancelModal');
}

function submitEdit() {
    var id = document.getElementById('editBookingId').value;
    var newDate = document.getElementById('editDate').value;
    var newTime = document.getElementById('editTime').value;
    var reason = document.getElementById('editReason').value;

    if (!newDate || !newTime) { alert('날짜와 시간을 선택해주세요.'); return; }

    var fd = new FormData();
    fd.append('booking_id', id);
    fd.append('phone', searchPhone);
    fd.append('new_date', newDate);
    fd.append('new_time', newTime);
    fd.append('edit_reason', reason);

    fetch('api/edit_booking.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert(data.message);
        }
    })
    .catch(function() { alert('네트워크 오류가 발생했습니다.'); });

    closeModal('editModal');
}
</script>

<?php include __DIR__ . '/includes/bottom_nav.php'; ?>
</body>
</html>
