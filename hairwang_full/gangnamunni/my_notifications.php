<?php
/**
 * 내 알림 (My Notifications) - 사용자 알림 목록 페이지
 * 로그인 필수, 필터/읽음처리/더보기 AJAX
 * @created 2026-02-27
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/user_notifications.php';
require_login();
$me = get_gangnamunni_member();
$member_id = $me['mb_id'];

function notification_filter_types($filter) {
    $groups = [
        'all' => [],
        'booking' => ['booking', 'payment'],
        'review' => ['review'],
        'benefit' => ['event', 'raffle', 'system', 'promotion', 'general', 'point', 'coupon', 'benefit'],
    ];

    return $groups[$filter] ?? [];
}

function notification_filter_where($filter, &$params, &$types) {
    $filter_types = notification_filter_types($filter);
    if ($filter === 'all' || empty($filter_types)) {
        return '';
    }

    $placeholders = implode(',', array_fill(0, count($filter_types), '?'));
    foreach ($filter_types as $type) {
        $params[] = $type;
        $types .= 's';
    }

    return " AND type IN ({$placeholders})";
}

function notification_bind_params($stmt, $types, array $params) {
    $refs = [$types];
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    return call_user_func_array('mysqli_stmt_bind_param', array_merge([$stmt], $refs));
}

function notification_page_items($connect, $member_id, $page = 1, $limit = 20, $filter = 'all') {
    $offset = ($page - 1) * $limit;
    $params = [$member_id];
    $types = 's';
    $where = notification_filter_where($filter, $params, $types);
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';

    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_user_notifications WHERE member_id = ?{$where} ORDER BY created_at DESC LIMIT ? OFFSET ?");
    if (!$stmt) return [];
    notification_bind_params($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $notifications = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $notifications;
}

function notification_page_count($connect, $member_id, $filter = 'all') {
    $params = [$member_id];
    $types = 's';
    $where = notification_filter_where($filter, $params, $types);
    $stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_user_notifications WHERE member_id = ?{$where}");
    if (!$stmt) return 0;
    notification_bind_params($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    return intval($result['cnt'] ?? 0);
}

function notification_display_icon($n, $fallback_icon) {
    $type = $n['type'] ?? '';
    $title = (string)($n['title'] ?? '');
    $message = (string)($n['message'] ?? '');
    $text = $title . ' ' . $message;

    if ($type === 'booking' && preg_match('/결제|입금|환불/u', $text)) return 'payment';
    if ($type === 'booking' && preg_match('/내일|오늘|리마인드|Reminder/i', $text)) return 'clock';
    if ($type === 'booking') return 'calendar';
    if ($type === 'review') return 'review';
    if (preg_match('/포인트|이벤트|혜택|쿠폰|소멸|발급/u', $text)) return 'gift';
    if (in_array($type, ['raffle', 'event', 'promotion', 'point', 'coupon', 'benefit'], true)) return 'gift';
    if ($type === 'system' || $type === 'general') return 'bell';

    $fallback_map = [
        'bi-credit-card' => 'payment',
        'bi-clock' => 'clock',
        'bi-pencil-square' => 'review',
        'bi-gift' => 'gift',
        'bi-bell' => 'bell',
        'bi-calendar-check' => 'calendar',
    ];
    return $fallback_map[$fallback_icon] ?? 'bell';
}

function notification_icon_src($icon) {
    $icons = [
        'calendar' => 'assets/icons/notifications/booking.svg',
        'payment' => 'assets/icons/notifications/payment.svg',
        'clock' => 'assets/icons/notifications/clock.svg',
        'gift' => 'assets/icons/notifications/gift.svg',
        'review' => 'assets/icons/notifications/review.svg',
        'bell' => 'assets/icons/notifications/bell.svg',
    ];

    return $icons[$icon] ?? $icons['bell'];
}

function notification_time_label($datetime) {
    $label = user_time_ago($datetime);
    if ($label === '방금') return '지금';
    return str_replace(['분전', '시간전', '일전'], ['분 전', '시간 전', '일 전'], $label);
}

// --- AJAX 엔드포인트: 알림 읽음 처리 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['ajax'] ?? '';

    if ($action === 'read') {
        $nid = intval($_POST['id'] ?? 0);
        if ($nid > 0) {
            mark_user_notification_read($connect, $nid, $member_id);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => '잘못된 요청']);
        }
        exit;
    }

    if ($action === 'read_all') {
        mark_user_all_read($connect, $member_id);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $nid = intval($_POST['id'] ?? 0);
        if ($nid > 0) {
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_user_notifications WHERE id = ? AND member_id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'is', $nid, $member_id);
                mysqli_stmt_execute($stmt);
                $deleted = mysqli_stmt_affected_rows($stmt) > 0;
                mysqli_stmt_close($stmt);
                echo json_encode(['success' => $deleted]);
                exit;
            }
        }
        echo json_encode(['success' => false, 'error' => '삭제할 수 없습니다']);
        exit;
    }

    if ($action === 'load') {
        $page = max(1, intval($_POST['page'] ?? 1));
        $type = $_POST['type'] ?? 'all';
        $allowed_types = ['all', 'booking', 'review', 'benefit'];
        if (!in_array($type, $allowed_types)) $type = 'all';

        $limit = 20;
        $notifications = [];
        $total = 0;

        // 테이블 존재 확인
        $table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_user_notifications'");
        if ($table_check && mysqli_num_rows($table_check) > 0) {
            $notifications = notification_page_items($connect, $member_id, $page, $limit, $type);
            $total = notification_page_count($connect, $member_id, $type);
        }

        $has_more = ($page * $limit) < $total;
        $html = '';

        foreach ($notifications as $n) {
            $html .= render_notification_item($n);
        }

        echo json_encode([
            'success' => true,
            'html' => $html,
            'has_more' => $has_more,
            'total' => $total,
            'page' => $page
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => '알 수 없는 요청']);
    exit;
}

// --- 초기 데이터 로딩 ---
$current_type = $_GET['type'] ?? 'all';
$allowed_types = ['all', 'booking', 'review', 'benefit'];
if (!in_array($current_type, $allowed_types)) $current_type = 'all';

$notifications = [];
$total_count = 0;
$unread_count = 0;
$table_exists = false;

// 테이블 존재 확인
$table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_user_notifications'");
if ($table_check && mysqli_num_rows($table_check) > 0) {
    $table_exists = true;
    $notifications = notification_page_items($connect, $member_id, 1, 20, $current_type);
    $total_count = notification_page_count($connect, $member_id, $current_type);
    $unread_count = get_user_unread_count($connect, $member_id);
}

$has_more = (1 * 20) < $total_count;

/**
 * 예약 알림에서 예약 ID 추출
 */
function notification_extract_booking_id($link) {
    if (empty($link)) return 0;

    $parts = parse_url($link);
    if (empty($parts['query'])) return 0;

    parse_str($parts['query'], $query);
    foreach (['booking_id', 'source_booking_id'] as $key) {
        if (!empty($query[$key])) return max(0, intval($query[$key]));
    }

    return 0;
}

/**
 * 내 예약 화면의 탭 분류와 같은 기준으로 예약 탭 결정
 */
function notification_booking_tab_from_booking(array $booking) {
    $status = $booking['status'] ?? '';
    if (in_array($status, ['cancelled', 'no_show'], true)) {
        return 'cancelled';
    }

    $value = trim((string)($booking['booking_date'] ?? '') . ' ' . (string)($booking['booking_time'] ?? ''));
    $timestamp = strtotime($value);
    $is_time_passed = $timestamp !== false && $timestamp <= time();

    if (in_array($status, ['pending', 'hospital_checking', 'confirmed'], true) && !$is_time_passed) {
        return 'active';
    }

    return 'completed';
}

/**
 * 예약 알림 클릭 시 내 예약의 알맞은 탭으로 이동
 */
function notification_booking_target_url($n) {
    global $connect;

    if (($n['type'] ?? '') !== 'booking') return '';

    $link = (string)($n['link'] ?? '');
    $booking_id = notification_extract_booking_id($link);
    if ($booking_id > 0 && isset($connect) && $connect) {
        $stmt = mysqli_prepare($connect, "SELECT id, status, booking_date, booking_time FROM gangnamunni_bookings WHERE id = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $booking_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $booking = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);

            if ($booking) {
                return 'my_bookings.php?tab=' . notification_booking_tab_from_booking($booking);
            }
        }
    }

    if (strpos($link, 'booking_cancel_detail.php') !== false) {
        return 'my_bookings.php?tab=cancelled';
    }

    return 'my_bookings.php?tab=active';
}

/**
 * 알림 항목 HTML 렌더링
 */
function render_notification_item($n) {
    global $USER_NOTIFICATION_TYPES;
    $type_info = $USER_NOTIFICATION_TYPES[$n['type']] ?? $USER_NOTIFICATION_TYPES['general'];
    $is_unread = intval($n['is_read'] ?? 0) === 0;
    $bg_class = $is_unread ? 'noti-unread' : 'noti-read';
    $icon_src = htmlspecialchars(notification_icon_src(notification_display_icon($n, $n['icon'] ?? $type_info['icon'])), ENT_QUOTES);
    $title = htmlspecialchars($n['title'] ?? '');
    $message = htmlspecialchars($n['message'] ?? '');
    $time = notification_time_label($n['created_at']);
    $link = htmlspecialchars($n['link'] ?? '');
    $open_url = notification_booking_target_url($n);
    $open_url_attr = htmlspecialchars($open_url, ENT_QUOTES);
    $open_area_class = 'noti-open-area' . ($open_url !== '' ? ' is-clickable' : '');
    $open_area_attrs = $open_url !== '' ? ' role="button" tabindex="0" aria-label="알림 보기"' : '';
    $id = intval($n['id']);

    return <<<HTML
<article class="noti-item {$bg_class}" data-id="{$id}" data-link="{$link}" data-open-url="{$open_url_attr}">
    <div class="{$open_area_class}"{$open_area_attrs}>
        <div class="noti-icon-wrap" aria-hidden="true">
            <img class="noti-svg-icon" src="{$icon_src}" alt="" loading="lazy" decoding="async">
        </div>
        <div class="noti-content">
            <div class="noti-title-row">
                <span class="noti-title">{$title}</span>
            </div>
            <p class="noti-message">{$message}</p>
        </div>
        <div class="noti-side">
            <span class="noti-time">{$time}</span>
            <span class="noti-dot"></span>
        </div>
    </div>
</article>
HTML;
}

// --- 페이지 레이아웃 변수 ---
$page_title = '알림';
$show_search = false;
$hide_global_nav = true;
$bottom_nav_active = 'home';

$page_css = <<<'CSS'
*{box-sizing:border-box}
html{background:#3f3f3f;-webkit-tap-highlight-color:transparent}
body{
    min-height:100vh;
    background:#eeeeee;
    color:#111;
    font-family:"Noto Sans KR",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    -webkit-font-smoothing:antialiased;
}
button{font:inherit}
button,a,[role="button"]{touch-action:manipulation;-webkit-tap-highlight-color:transparent}

.main-content{
    padding:0!important;
    background:#eeeeee!important;
}

.noti-wrapper{
    --noti-primary:#AC1830;
    --noti-bg:#eeeeee;
    --noti-border:#eeeeee;
    width:100%;
    max-width:640px;
    min-height:calc(100vh - 77px);
    margin:0 auto;
    padding-bottom:calc(96px + env(safe-area-inset-bottom, 0px));
    background:var(--noti-bg);
}

.noti-header{
    position:sticky;
    top:0;
    z-index:100;
    width:100%;
    background:#fff;
}
.noti-header-row{
    position:relative;
    min-height:72px;
    display:grid;
    grid-template-columns:48px 1fr 48px;
    align-items:center;
    padding:8px 20px 4px;
}
.noti-back{
    width:44px;
    height:44px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:0;
    border-radius:50%;
    background:transparent;
    color:#111;
    font-size:31px;
    cursor:pointer;
}
.noti-back:active{transform:scale(.98)}
.noti-page-title{
    min-width:0;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    color:#111;
    font-size:21px;
    font-weight:700;
    letter-spacing:0;
}
.noti-badge{
    display:none;
    min-width:22px;
    height:22px;
    align-items:center;
    justify-content:center;
    padding:0 7px;
    border-radius:999px;
    background:var(--noti-primary);
    color:#fff;
    font-size:11px;
    font-weight:800;
    line-height:1;
}
.noti-read-all-btn{display:none}

.noti-tabs{
    position:sticky;
    top:72px;
    z-index:90;
    width:100%;
    display:flex;
    overflow:hidden;
    border-bottom:1px solid #cfcfcf;
    background:#fff;
    scrollbar-width:none;
}
.noti-tabs::-webkit-scrollbar{display:none}
.noti-tab{
    position:relative;
    z-index:1;
    flex:1 1 0;
    min-width:0;
    min-height:60px;
    padding:0 4px;
    border:0;
    border-radius:0;
    background:#fff;
    color:#111;
    font-size:18px;
    font-weight:700;
    cursor:pointer;
    white-space:nowrap;
}
.noti-tab.active{
    border-color:transparent;
    background:#fff;
    color:var(--noti-primary);
    box-shadow:none;
}
.noti-tab-indicator{
    position:absolute;
    bottom:0;
    left:0;
    z-index:2;
    height:2px;
    width:0;
    background:var(--noti-primary);
    transform:translateX(0);
    transition:transform .28s cubic-bezier(.22, .61, .36, 1), width .28s cubic-bezier(.22, .61, .36, 1);
    pointer-events:none;
}
.noti-tab:active{transform:scale(.98)}
.noti-tab:focus-visible,
.noti-back:focus-visible,
.noti-read-all-btn:focus-visible,
.noti-open-area.is-clickable:focus-visible,
.noti-delete-btn:focus-visible,
.noti-load-more:focus-visible{
    outline:2px solid rgba(172, 24, 48,.35);
    outline-offset:2px;
}

.noti-list{
    display:grid;
    gap:14px;
    padding:22px 14px 54px;
    background:var(--noti-bg);
}
.noti-item{
    position:relative;
    display:flex;
    align-items:stretch;
    min-height:76px;
    overflow:visible;
    border:0;
    border-radius:0;
    background:#fff;
    box-shadow:none;
    transition:background .16s ease, border-color .16s ease, transform .16s ease;
}
.noti-item:active{transform:scale(.995)}
.noti-unread{
    border-color:transparent;
    background:#fff;
}
.noti-open-area{
    width:100%;
    min-width:0;
    display:grid;
    grid-template-columns:34px minmax(0, 1fr) 58px;
    align-items:center;
    column-gap:14px;
    padding:17px 16px 17px 22px;
}
.noti-open-area.is-clickable{cursor:pointer}
.noti-open-area.is-clickable:active{background:#fafafa}
.noti-icon-wrap{
    width:34px;
    height:42px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:0;
    color:var(--noti-primary);
    font-size:26px;
}
.noti-svg-icon{
    display:block;
    width:32px;
    height:32px;
    object-fit:contain;
}
.noti-content{flex:1;min-width:0}
.noti-title-row{
    display:flex;
    align-items:center;
    gap:7px;
    min-width:0;
    margin:0 0 4px;
}
.noti-title{
    min-width:0;
    flex:1;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    color:#111;
    font-size:15px;
    font-weight:800;
}
.noti-dot{
    flex:0 0 8px;
    width:8px;
    height:8px;
    border-radius:50%;
    background:var(--noti-primary);
}
.noti-read .noti-dot{
    background:#b5b5b5;
}
.noti-unread .noti-dot{
    background:var(--noti-primary);
}
.noti-message{
    display:block;
    margin:4px 0 0;
    color:#111;
    font-size:11px;
    line-height:1.45;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}
.noti-side{
    display:flex;
    flex-direction:column;
    align-items:flex-end;
    justify-content:flex-start;
    align-self:stretch;
    padding-top:0;
}
.noti-time{
    max-width:58px;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    color:#aaa;
    font-size:11px;
    line-height:1;
}
.noti-side .noti-dot{margin-top:14px}
.noti-delete-btn{
    position:absolute;
    right:6px;
    bottom:6px;
    width:34px;
    height:34px;
    min-height:0;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0;
    border:0;
    border-radius:50%;
    background:transparent;
    color:#cfcfcf;
    font-size:14px;
    cursor:pointer;
    opacity:0;
    transition:opacity .14s ease, background .14s ease, color .14s ease;
}
.noti-item:hover .noti-delete-btn,
.noti-delete-btn:focus-visible{
    opacity:1;
}
.noti-delete-btn:active{
    background:#fff1f3;
    color:var(--noti-primary);
}
.noti-delete-btn:disabled{opacity:.45;cursor:default}

.noti-load-more{
    display:block;
    width:calc(100% - 36px);
    max-width:604px;
    min-height:48px;
    margin:12px auto 24px;
    border:1px solid #e8e8e8;
    border-radius:0;
    background:#fff;
    color:#333;
    font-size:14px;
    font-weight:800;
    cursor:pointer;
}
.noti-load-more:active{background:#f6f6f6;transform:scale(.995)}
.noti-load-more:disabled{color:#bbb;cursor:default}

.noti-empty{
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    min-height:320px;
    padding:48px 18px;
    border:1px solid #ededed;
    border-radius:0;
    background:#fff;
    text-align:center;
}
.noti-empty-icon{
    width:72px;
    height:72px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-bottom:18px;
    border-radius:50%;
    background:#f7f7f7;
    color:#cfcfcf;
    font-size:34px;
}
.noti-empty-title{
    margin-bottom:6px;
    color:#555;
    font-size:16px;
    font-weight:850;
}
.noti-empty-desc{
    color:#aaa;
    font-size:13px;
}

.noti-spinner{
    display:flex;
    align-items:center;
    justify-content:center;
    min-height:220px;
}
.noti-spinner::after{
    content:"";
    width:30px;
    height:30px;
    border:3px solid #ececec;
    border-top-color:var(--noti-primary);
    border-radius:50%;
    animation:noti-spin .7s linear infinite;
}
@keyframes noti-spin{to{transform:rotate(360deg)}}

.noti-toast{
    position:fixed;
    left:50%;
    bottom:calc(92px + env(safe-area-inset-bottom, 0px));
    z-index:9999;
    max-width:calc(100% - 40px);
    transform:translateX(-50%) translateY(72px);
    padding:11px 18px;
    border-radius:999px;
    background:rgba(25,25,25,.92);
    color:#fff;
    font-size:13px;
    font-weight:700;
    opacity:0;
    pointer-events:none;
    transition:opacity .22s ease, transform .22s ease;
}
.noti-toast.show{
    opacity:1;
    transform:translateX(-50%) translateY(0);
}

@media(max-width:380px){
    .noti-tab{font-size:16px}
    .noti-list{padding-left:12px;padding-right:12px}
    .noti-open-area{grid-template-columns:32px minmax(0, 1fr) 52px;padding-left:18px;padding-right:14px;column-gap:12px}
    .noti-time{max-width:52px}
}
@media(prefers-reduced-motion:reduce){
    *,*::before,*::after{animation:none!important;transition:none!important}
}
CSS;

$page_js = '';

include __DIR__ . '/includes/header.php';
?>

<div class="noti-wrapper">
    <!-- 페이지 헤더 -->
    <div class="noti-header">
        <div class="noti-header-row">
            <button type="button" class="noti-back" onclick="history.back()" aria-label="뒤로가기">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </button>
            <span class="noti-page-title">
                알림
                <?php if ($unread_count > 0): ?>
                    <span class="noti-badge" id="unreadBadge"><?= $unread_count > 99 ? '99+' : $unread_count ?></span>
                <?php endif; ?>
            </span>
            <button class="noti-read-all-btn" id="readAllBtn"
                <?= $unread_count === 0 ? 'disabled' : '' ?>
                onclick="markAllRead()">
                모두 읽음
            </button>
        </div>
    </div>

    <!-- 필터 탭 -->
    <div class="noti-tabs" id="notiTabs" role="tablist" aria-label="알림 필터">
        <button class="noti-tab <?= $current_type === 'all' ? 'active' : '' ?>" data-type="all">전체</button>
        <button class="noti-tab <?= $current_type === 'booking' ? 'active' : '' ?>" data-type="booking">예약 · 결제</button>
        <button class="noti-tab <?= $current_type === 'review' ? 'active' : '' ?>" data-type="review">후기</button>
        <button class="noti-tab <?= $current_type === 'benefit' ? 'active' : '' ?>" data-type="benefit">혜택 · 기타</button>
        <span class="noti-tab-indicator" id="notiTabIndicator" aria-hidden="true"></span>
    </div>

    <!-- 알림 리스트 -->
    <div class="noti-list" id="notiList">
        <?php if (empty($notifications)): ?>
            <div class="noti-empty" id="emptyState">
                <div class="noti-empty-icon"><i class="bi bi-bell-slash"></i></div>
                <div class="noti-empty-title">알림이 없습니다</div>
                <div class="noti-empty-desc">새로운 알림이 오면 여기에 표시됩니다.</div>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <?= render_notification_item($n) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 더 보기 버튼 -->
    <?php if ($has_more): ?>
        <button class="noti-load-more" id="loadMoreBtn" onclick="loadMore()">
            더 보기
        </button>
    <?php endif; ?>
</div>

<!-- 토스트 -->
<div class="noti-toast" id="notiToast"></div>

<script>
(function() {
    'use strict';

    // 상태 변수
    var currentPage = 1;
    var currentType = '<?= $current_type ?>';
    var isLoading = false;
    var totalCount = <?= $total_count ?>;
    var unreadCount = <?= $unread_count ?>;
    var LIMIT = 20;
    var BASE_URL = location.pathname;

    // 요소 참조
    var notiList = document.getElementById('notiList');
    var loadMoreBtn = document.getElementById('loadMoreBtn');
    var readAllBtn = document.getElementById('readAllBtn');
    var unreadBadge = document.getElementById('unreadBadge');
    var unreadSummaryNumber = document.getElementById('unreadSummaryNumber');
    var notiToast = document.getElementById('notiToast');
    var tabs = document.querySelectorAll('.noti-tab');
    var tabIndicator = document.getElementById('notiTabIndicator');

    tabs.forEach(function(tab) {
        tab.setAttribute('role', 'tab');
        tab.setAttribute('aria-selected', tab.classList.contains('active') ? 'true' : 'false');
    });

    function moveTabIndicator(animate) {
        if (!tabIndicator) return;
        var activeTab = document.querySelector('.noti-tab.active');
        if (!activeTab) return;

        if (!animate) {
            tabIndicator.style.transition = 'none';
        }

        tabIndicator.style.width = activeTab.offsetWidth + 'px';
        tabIndicator.style.transform = 'translateX(' + activeTab.offsetLeft + 'px)';

        if (!animate) {
            requestAnimationFrame(function() {
                tabIndicator.style.transition = '';
            });
        }
    }

    moveTabIndicator(false);
    window.addEventListener('resize', function() {
        moveTabIndicator(false);
    });

    // --- 탭 전환 ---
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var type = this.getAttribute('data-type');
            if (type === currentType && !isLoading) return;
            switchTab(type);
        });
    });

    function switchTab(type) {
        currentType = type;
        currentPage = 1;

        // 탭 활성 상태 변경
        tabs.forEach(function(t) {
            var active = t.getAttribute('data-type') === type;
            t.classList.toggle('active', active);
            t.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        moveTabIndicator(true);

        // 리스트 로딩 표시
        notiList.innerHTML = '<div class="noti-spinner"></div>';
        removeLoadMoreBtn();

        // URL 히스토리 변경 (새로고침 없이)
        var url = BASE_URL + (type !== 'all' ? '?type=' + type : '');
        history.replaceState(null, '', url);

        // AJAX로 데이터 로드
        loadNotifications(1, type, false);
    }

    // --- 알림 목록 로드 ---
    function loadNotifications(page, type, append) {
        if (isLoading) return;
        isLoading = true;

        var formData = new FormData();
        formData.append('page', page);
        formData.append('type', type);

        fetch(BASE_URL + '?ajax=load', {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                showToast('데이터를 불러올 수 없습니다.');
                return;
            }

            totalCount = data.total;
            currentPage = data.page;

            if (append) {
                // 기존 리스트에 추가
                var temp = document.createElement('div');
                temp.innerHTML = data.html;
                while (temp.firstChild) {
                    notiList.appendChild(temp.firstChild);
                }
            } else {
                // 리스트 교체
                if (data.html) {
                    notiList.innerHTML = data.html;
                } else {
                    notiList.innerHTML =
                        '<div class="noti-empty" id="emptyState">' +
                        '<div class="noti-empty-icon"><i class="bi bi-bell-slash"></i></div>' +
                        '<div class="noti-empty-title">' + getEmptyTitle(type) + '</div>' +
                        '<div class="noti-empty-desc">새로운 알림이 오면 여기에 표시됩니다.</div>' +
                        '</div>';
                }
            }

            // 더 보기 버튼 표시/제거
            if (data.has_more) {
                showLoadMoreBtn();
            } else {
                removeLoadMoreBtn();
            }

            // 클릭 이벤트 다시 바인딩
            bindItemEvents();
        })
        .catch(function() {
            showToast('네트워크 오류가 발생했습니다.');
        })
        .finally(function() {
            isLoading = false;
        });
    }

    function getEmptyTitle(type) {
        var map = {
            'all': '알림이 없습니다',
            'booking': '예약 · 결제 알림이 없습니다',
            'review': '후기 알림이 없습니다',
            'benefit': '혜택 · 기타 알림이 없습니다'
        };
        return map[type] || '알림이 없습니다';
    }

    // --- 더 보기 ---
    window.loadMore = function() {
        if (isLoading) return;
        var nextPage = currentPage + 1;
        if (loadMoreBtn) {
            loadMoreBtn.textContent = '불러오는 중...';
            loadMoreBtn.disabled = true;
        }
        loadNotifications(nextPage, currentType, true);
    };

    function showLoadMoreBtn() {
        removeLoadMoreBtn();
        var btn = document.createElement('button');
        btn.className = 'noti-load-more';
        btn.id = 'loadMoreBtn';
        btn.textContent = '더 보기';
        btn.onclick = window.loadMore;
        notiList.parentNode.insertBefore(btn, notiList.nextSibling);
        loadMoreBtn = btn;
    }

    function removeLoadMoreBtn() {
        var existing = document.getElementById('loadMoreBtn');
        if (existing) existing.remove();
        loadMoreBtn = null;
    }

    // --- 알림 클릭 (읽음 처리 + 이동) ---
    function bindItemEvents() {
        var items = notiList.querySelectorAll('.noti-item');
        items.forEach(function(item) {
            // 이전 이벤트 리스너 방지
            if (item.dataset.bound) return;
            item.dataset.bound = '1';

            var deleteBtn = item.querySelector('.noti-delete-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('pointerdown', function(e) {
                    e.stopPropagation();
                });
                deleteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    deleteNotification(item, deleteBtn);
                });
            }

            var openArea = item.querySelector('.noti-open-area.is-clickable');
            if (openArea) {
                openArea.addEventListener('click', function() {
                    handleItemClick(item);
                });
                openArea.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        handleItemClick(item);
                    }
                });
            }
        });
    }

    function deleteNotification(item, button) {
        if (!item || !button || button.disabled) return;
        var id = item.getAttribute('data-id');
        var wasUnread = item.classList.contains('noti-unread');

        button.disabled = true;
        var formData = new FormData();
        formData.append('id', id);

        fetch(BASE_URL + '?ajax=delete', {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success) {
                button.disabled = false;
                showToast(data.error || '삭제할 수 없습니다.');
                return;
            }

            item.remove();
            totalCount = Math.max(0, totalCount - 1);
            if (wasUnread) {
                unreadCount = Math.max(0, unreadCount - 1);
                updateUnreadBadge();
            }
            if (!notiList.querySelector('.noti-item')) {
                notiList.innerHTML =
                    '<div class="noti-empty" id="emptyState">' +
                    '<div class="noti-empty-icon"><i class="bi bi-bell-slash"></i></div>' +
                    '<div class="noti-empty-title">' + getEmptyTitle(currentType) + '</div>' +
                    '<div class="noti-empty-desc">새로운 알림이 오면 여기에 표시됩니다.</div>' +
                    '</div>';
                removeLoadMoreBtn();
            }
            showToast('알림을 삭제했습니다.');
        })
        .catch(function() {
            button.disabled = false;
            showToast('네트워크 오류가 발생했습니다.');
        });
    }

    function handleItemClick(el) {
        var id = el.getAttribute('data-id');
        var link = el.getAttribute('data-open-url') || el.getAttribute('data-link');
        var isUnread = el.classList.contains('noti-unread');

        if (isUnread) {
            // 즉시 시각적으로 읽음 처리
            el.classList.remove('noti-unread');
            el.classList.add('noti-read');

            // 카운트 갱신
            unreadCount = Math.max(0, unreadCount - 1);
            updateUnreadBadge();

            // 서버에 읽음 처리 POST
            var formData = new FormData();
            formData.append('id', id);
            fetch(BASE_URL + '?ajax=read', {
                method: 'POST',
                body: formData
            }).catch(function() {});
        }

        // 링크가 있으면 이동
        if (link) {
            link = addNotificationReturnParams(link);
            setTimeout(function() {
                location.href = link;
            }, 100);
        }
    }

    function addNotificationReturnParams(link) {
        try {
            var url = new URL(link, location.href);
            if (url.origin !== location.origin) return link;

            url.searchParams.set('from', 'notifications');
            url.searchParams.set('return_to', 'my_notifications.php' + location.search);

            return url.pathname.split('/').pop() + url.search + url.hash;
        } catch (e) {
            return link;
        }
    }

    // --- 모두 읽음 ---
    window.markAllRead = function() {
        if (isLoading || unreadCount === 0) return;
        readAllBtn.disabled = true;
        readAllBtn.textContent = '처리 중...';

        fetch(BASE_URL + '?ajax=read_all', {
            method: 'POST'
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                // 시각적으로 전부 읽음 처리
                var unreadItems = notiList.querySelectorAll('.noti-unread');
                unreadItems.forEach(function(item) {
                    item.classList.remove('noti-unread');
                    item.classList.add('noti-read');
                });

                unreadCount = 0;
                updateUnreadBadge();
                showToast('모든 알림을 읽음 처리했습니다.');
            } else {
                showToast('처리 중 오류가 발생했습니다.');
            }
        })
        .catch(function() {
            showToast('네트워크 오류가 발생했습니다.');
        })
        .finally(function() {
            readAllBtn.textContent = '모두 읽음';
            readAllBtn.disabled = (unreadCount === 0);
        });
    };

    // --- 배지 업데이트 ---
    function updateUnreadBadge() {
        if (unreadCount > 0) {
            if (!unreadBadge) {
                var badge = document.createElement('span');
                badge.className = 'noti-badge';
                badge.id = 'unreadBadge';
                document.querySelector('.noti-page-title').appendChild(badge);
                unreadBadge = badge;
            }
            unreadBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            unreadBadge.style.display = '';
        } else {
            if (unreadBadge) unreadBadge.style.display = 'none';
            if (readAllBtn) readAllBtn.disabled = true;
        }
        if (unreadSummaryNumber) {
            unreadSummaryNumber.textContent = String(unreadCount);
            unreadSummaryNumber.parentElement?.setAttribute('aria-label', '읽지 않은 알림 ' + unreadCount + '개');
        }
    }

    // --- 토스트 ---
    var toastTimer = null;
    function showToast(msg) {
        notiToast.textContent = msg;
        notiToast.classList.add('show');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function() {
            notiToast.classList.remove('show');
        }, 2500);
    }

    // 초기 바인딩
    bindItemEvents();
})();
</script>

<?php
include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>
