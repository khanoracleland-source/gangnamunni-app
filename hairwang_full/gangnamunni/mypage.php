<?php
/**
 * 서울미녀 통합 마이페이지
 * Figma 디자인 기반 메뉴 구조 (2026-03-25)
 */
// 앱 모드 감지 (?app=1 파라미터)
$is_app_mode = isset($_GET['app']) && $_GET['app'] == '1';

// Gnuboard common.php를 먼저 로드 (세션 + 로그인 정보)
// session_start()를 직접 호출하면 Gnuboard 세션과 충돌하므로 common.php에 위임
$g5_common = dirname(__DIR__) . '/common.php';
if (file_exists($g5_common) && !defined('G5_PATH')) {
    include_once $g5_common;
}

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB error');
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

// Phase5: SSO 토큰 자동 로그인 (앱 WebView에서 세션 없을 때)
include_once __DIR__ . '/includes/sso_auto_login.php';

// 로그인 체크
$is_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];

if (!$is_logged_in) {
    // 앱 모드면 JSON 에러
    if ($is_app_mode) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'login_required', 'message' => '로그인이 필요합니다.']);
        exit;
    }
    // 비로그인: 리다이렉트 대신 로그인 안내 UI 표시 (리다이렉트 루프 방지)
    $mb_id = ''; $mb_nick = ''; $mb_email = ''; $mb_hp = ''; $mb_datetime = ''; $mb_level = 0;
} else {
    $mb_id = $member['mb_id'];
    $mb_no = $member['mb_no'] ?? 0; // 숫자 ID (wishlist/points/coupon용)
    $mb_nick = $member['mb_nick'] ?? $mb_id;
    $mb_email = $member['mb_email'] ?? '';
    $mb_hp = $member['mb_hp'] ?? '';
    $mb_datetime = $member['mb_datetime'] ?? '';
    $mb_level = $member['mb_level'] ?? 1;
}

// 로그인 상태에서만 DB 조회
if ($is_logged_in):

// 내 예약 건수 (mb_id 또는 전화번호)
$booking_count = 0;
$phone_clean = preg_replace('/[^0-9]/', '', $mb_hp);
$bq = mysqli_prepare($connect,
    "SELECT COUNT(*) as cnt FROM gangnamunni_bookings WHERE mb_id = ?" . (!empty($phone_clean) ? " OR REPLACE(REPLACE(phone,'-',''),' ','') = ?" : "")
);
if ($bq) {
    if (!empty($phone_clean)) {
        mysqli_stmt_bind_param($bq, 'ss', $mb_id, $phone_clean);
    } else {
        mysqli_stmt_bind_param($bq, 's', $mb_id);
    }
    mysqli_stmt_execute($bq);
    $r = mysqli_stmt_get_result($bq);
    $booking_count = (int)($r ? mysqli_fetch_assoc($r)['cnt'] : 0);
    mysqli_stmt_close($bq);
}

// 내 후기 건수 (user_id 컬럼)
$review_count = 0;
$rq = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM hospital_reviews WHERE user_id = ?");
if ($rq) {
    mysqli_stmt_bind_param($rq, 's', $mb_id);
    mysqli_stmt_execute($rq);
    $r = mysqli_stmt_get_result($rq);
    $review_count = (int)($r ? mysqli_fetch_assoc($r)['cnt'] : 0);
    mysqli_stmt_close($rq);
}

// 내 알림 (미읽음 건수)
$unread_count = 0;
$nq = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_user_notifications WHERE member_id = ? AND is_read = 0");
if ($nq) {
    mysqli_stmt_bind_param($nq, 's', $mb_id);
    mysqli_stmt_execute($nq);
    $r = mysqli_stmt_get_result($nq);
    $unread_count = (int)($r ? mysqli_fetch_assoc($r)['cnt'] : 0);
    mysqli_stmt_close($nq);
}

// 내 이벤트 응모 건수
$event_count = 0;
$eq = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_event_entries WHERE member_id = ?");
if ($eq) {
    mysqli_stmt_bind_param($eq, 's', $mb_id);
    mysqli_stmt_execute($eq);
    $r = mysqli_stmt_get_result($eq);
    $event_count = (int)($r ? mysqli_fetch_assoc($r)['cnt'] : 0);
    mysqli_stmt_close($eq);
}

// 찜 목록 건수 (member_id는 INT = mb_no)
$wish_count = 0;
$wq = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_wishlist WHERE member_id = ?");
if ($wq) {
    mysqli_stmt_bind_param($wq, 'i', $mb_no);
    mysqli_stmt_execute($wq);
    $r = mysqli_stmt_get_result($wq);
    $wish_count = (int)($r ? mysqli_fetch_assoc($r)['cnt'] : 0);
    mysqli_stmt_close($wq);
}

// 커뮤니티 글 건수
$community_count = 0;
$cq = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_community_posts WHERE mb_id = ? AND status = 'active'");
if ($cq) {
    mysqli_stmt_bind_param($cq, 's', $mb_id);
    mysqli_stmt_execute($cq);
    $r = mysqli_stmt_get_result($cq);
    $community_count = (int)($r ? mysqli_fetch_assoc($r)['cnt'] : 0);
    mysqli_stmt_close($cq);
}

// 포인트 잔액 (member_id는 INT = mb_no)
$point_balance = 0;
$pq = mysqli_prepare($connect, "SELECT COALESCE(SUM(CASE WHEN type='earn' OR type='admin' THEN amount WHEN type='use' THEN -amount ELSE 0 END), 0) as bal FROM gangnamunni_points WHERE member_id = ?");
if ($pq) {
    mysqli_stmt_bind_param($pq, 'i', $mb_no);
    mysqli_stmt_execute($pq);
    $r = mysqli_stmt_get_result($pq);
    $point_balance = (int)($r ? mysqli_fetch_assoc($r)['bal'] : 0);
    mysqli_stmt_close($pq);
}

// 쿠폰 건수 (member_id는 INT = mb_no)
$coupon_count = 0;
$cpq = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_coupon_claims WHERE member_id = ? AND status = 'available'");
if ($cpq) {
    mysqli_stmt_bind_param($cpq, 'i', $mb_no);
    mysqli_stmt_execute($cpq);
    $r = mysqli_stmt_get_result($cpq);
    $coupon_count = (int)($r ? mysqli_fetch_assoc($r)['cnt'] : 0);
    mysqli_stmt_close($cpq);
}

endif; // $is_logged_in

if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
if (!$is_app_mode) { require_once __DIR__ . '/includes/global_nav.php'; }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>마이페이지 — <?php echo htmlspecialchars(site_name()); ?></title>
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<script>
console.log('%c[MYPAGE DEBUG v2]%c Page loaded: ' + new Date().toISOString(), 'background:#AE1932;color:#fff;padding:2px 6px;border-radius:3px', '');
console.log('[DEBUG] URL:', window.location.href);
console.log('[DEBUG] Login: <?php echo $is_logged_in ? "YES" : "NO"; ?>');
console.log('[DEBUG] Referrer:', document.referrer || 'none');

// Intercept ALL network requests via PerformanceObserver
if (window.PerformanceObserver) {
    var po = new PerformanceObserver(function(list) {
        list.getEntries().forEach(function(entry) {
            if (entry.name.indexOf('gangnamunni') !== -1 && entry.name.indexOf('hairwang') === -1) {
                console.error('[DEBUG] BAD REQUEST:', entry.name, 'type:', entry.initiatorType);
            }
        });
    });
    po.observe({entryTypes: ['resource']});
}

// Override fetch to catch bad URLs
var origFetch = window.fetch;
window.fetch = function() {
    console.log('[DEBUG] fetch called:', arguments[0]);
    return origFetch.apply(this, arguments);
};

// Override XMLHttpRequest
var origOpen = XMLHttpRequest.prototype.open;
XMLHttpRequest.prototype.open = function(method, url) {
    console.log('[DEBUG] XHR:', method, url);
    return origOpen.apply(this, arguments);
};

// Click interceptor
document.addEventListener('click', function(e) {
    var link = e.target.closest('a');
    if (link) {
        console.log('[DEBUG] CLICK -> href attr:', link.getAttribute('href'), '| resolved:', link.href);
        if (link.href.indexOf('/gangnamunni') !== -1 && link.href.indexOf('/hairwang/') === -1) {
            console.error('[DEBUG] BAD LINK CLICKED! Blocking navigation. href:', link.href);
            e.preventDefault();
            alert('DEBUG: Bad link detected!\n' + link.href + '\nattr: ' + link.getAttribute('href'));
        }
    }
}, true);

// DOMContentLoaded - list all links
document.addEventListener('DOMContentLoaded', function() {
    console.log('[DEBUG] DOM ready. Links:');
    document.querySelectorAll('a[href]').forEach(function(a, i) {
        var raw = a.getAttribute('href');
        var full = a.href;
        var isBad = (full.indexOf('/gangnamunni') !== -1 && full.indexOf('/hairwang/') === -1) ||
                    (full.indexOf('/bbs/') !== -1 && full.indexOf('/hairwang/bbs/') === -1);
        if (isBad) {
            console.error('[DEBUG] BAD LINK #' + i + ': raw="' + raw + '" full="' + full + '" text="' + a.textContent.trim().substring(0,30) + '"');
        }
    });

    // Check all loaded resources
    if (window.performance) {
        var resources = performance.getEntriesByType('resource');
        resources.forEach(function(r) {
            if (r.name.indexOf('gangnamunni') !== -1 && r.name.indexOf('hairwang') === -1) {
                console.error('[DEBUG] BAD RESOURCE loaded:', r.name, 'type:', r.initiatorType);
            }
        });
    }
    console.log('[DEBUG] Analysis complete.');
});
</script>
<style>
:root { --pri: <?php echo getTheme('primary_color', '#AE1932'); ?>; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Pretendard','Apple SD Gothic Neo',sans-serif; background:#F8F8F8; max-width:640px; margin:0 auto; min-height:100vh; padding-bottom:80px; }

/* 페이지 헤더 */
.page-header {
    display:flex; align-items:center; justify-content:space-between;
    padding:14px 20px; background:var(--bs-body-bg, #fff); border-bottom:1px solid var(--bs-border-color, #f0f0f0);
}
.page-header-title { font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:700; color:#000; }
.page-header .settings-btn {
    width:36px; height:36px; display:flex; align-items:center; justify-content:center;
    border-radius:50%; background:transparent; border:none; cursor:pointer; color:var(--bs-secondary-color, #666); font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));
    text-decoration:none;
}
.page-header .settings-btn:hover { background:var(--bs-tertiary-bg, #f5f5f5); }

/* 프로필 섹션 */
.profile-section {
    display:flex; align-items:center; gap:14px;
    padding:20px; background:var(--bs-body-bg, #fff); border-bottom:8px solid #F8F8F8; cursor:pointer;
    text-decoration:none; color:inherit;
}
.profile-section:hover { background:#fafafa; }
.profile-avatar {
    width:50px; height:50px; border-radius:50%; background:#D9D9D9;
    display:flex; align-items:center; justify-content:center;
    font-size:calc(22px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:700; color:#fff; flex-shrink:0;
}
.profile-info-wrap { flex:1; min-width:0; }
.profile-name { font-size:calc(16px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:700; color:#000; }
.profile-sub { font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color:#999; margin-top:2px; }
.profile-arrow { color:#000; font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); flex-shrink:0; }

/* 메뉴 섹션 */
.menu-section { background:var(--bs-body-bg, #fff); border-radius:0; margin-bottom:20px; padding:20px; }
.menu-section:last-of-type { margin-bottom:0; }
.menu-section-title {
    padding:0 0 8px; font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:500; color:var(--color-inactive, #979797); letter-spacing:0.02em;
}
.menu-row {
    display:flex; align-items:center; gap:14px;
    padding:20px 0; text-decoration:none; color:#000;
    transition: background 0.15s;
}
.menu-row:hover { background:#fafafa; }
.menu-row-icon {
    width:30px; height:30px; display:flex; align-items:center; justify-content:center;
    font-size:calc(24px * var(--fs-scale,1) * var(--fs-page,1)); color:#555; flex-shrink:0;
}
.menu-row-text { flex:1; font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:500; }
.menu-row-arrow { color:#ccc; font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); flex-shrink:0; }

/* 로그아웃 */
.logout-section { background:var(--bs-body-bg, #fff); margin-top:20px; }
.logout-btn {
    display:flex; align-items:center; justify-content:center;
    padding:16px 20px; text-decoration:none;
    font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1)); color:#999; font-weight:500;
}
.logout-btn:hover { background:var(--bs-tertiary-bg, #fafafa); color:var(--color-primary, #AE1932); }

/* 활동 요약 4칸 그리드 */
.activity-summary {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 0;
    border: 1px solid var(--bs-border-color, #eee); border-radius: 10px; overflow: hidden;
}
.activity-item {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 16px 8px; text-decoration: none; color: inherit; transition: background 0.15s;
    border-right: 1px solid var(--bs-border-color, #eee);
}
.activity-item:last-child { border-right: none; }
.activity-item:hover { background: var(--bs-tertiary-bg, #fafafa); }
.activity-num { font-size: calc(22px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; color: var(--pri, #AE1932); line-height: 1.2; }
.activity-label { font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: #888; margin-top: 4px; }

/* 포인트/쿠폰 박스 */
.point-coupon-box {
    flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 18px 8px; text-decoration: none; color: inherit; transition: background 0.15s;
}
.point-coupon-box:hover { background: var(--bs-tertiary-bg, #fafafa); }
.pc-label { font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color: #888; margin-bottom: 4px; }
.pc-label i { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); }
.pc-value { font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; color: var(--bs-body-color, #000); }

/* 메뉴 배지 */
.menu-row-badge {
    font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; color: var(--pri, #AE1932); background: #fce4e8;
    padding: 2px 8px; border-radius: 10px; margin-right: 4px;
}
.menu-row-badge.badge-red {
    color: #fff; background: var(--pri, #AE1932);
}

/* 하단 네비 여백 */
.bottom-spacer { height: 70px; }
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>

<!-- 페이지 헤더: "마이페이지" + 설정 아이콘 -->
<div class="page-header">
    <span class="page-header-title">마이페이지</span>
    <a href="notification_settings.php" class="settings-btn" title="설정"><i class="bi bi-gear"></i></a>
</div>

<?php if ($is_logged_in): ?>
<!-- 프로필 섹션: 이름 + 화살표 (클릭 시 프로필 수정) -->
<a href="profile_edit.php" class="profile-section">
    <div class="profile-avatar"><?php echo mb_substr($mb_nick, 0, 1); ?></div>
    <div class="profile-info-wrap">
        <div class="profile-name"><?php echo htmlspecialchars($mb_nick); ?></div>
        <div class="profile-sub">프로필 보기</div>
    </div>
    <span class="profile-arrow"><i class="bi bi-chevron-right"></i></span>
</a>

<!-- 섹션: 내 활동 요약 -->
<div class="menu-section" style="border-bottom:8px solid #F8F8F8;">
    <div class="menu-section-title">내 활동</div>
    <div class="activity-summary">
        <a href="my_bookings.php" class="activity-item">
            <span class="activity-num"><?php echo $booking_count; ?></span>
            <span class="activity-label"><?php echo term('booking', '예약'); ?></span>
        </a>
        <a href="my_reviews.php" class="activity-item">
            <span class="activity-num"><?php echo $review_count; ?></span>
            <span class="activity-label"><?php echo term('review', '후기'); ?></span>
        </a>
        <a href="my_wishlist.php" class="activity-item">
            <span class="activity-num"><?php echo $wish_count; ?></span>
            <span class="activity-label">찜</span>
        </a>
        <a href="my_notifications.php" class="activity-item">
            <span class="activity-num"><?php echo $unread_count; ?></span>
            <span class="activity-label">알림</span>
        </a>
    </div>
</div>

<!-- 섹션: 포인트 / 쿠폰 -->
<div class="menu-section" style="border-bottom:8px solid #F8F8F8;">
    <div style="display:flex;gap:0;">
        <a href="my_points.php" class="point-coupon-box" style="border-right:1px solid var(--bs-border-color, #eee);">
            <span class="pc-label"><i class="bi bi-coin"></i> 포인트</span>
            <span class="pc-value"><?php echo number_format($point_balance); ?>P</span>
        </a>
        <a href="my_coupons.php" class="point-coupon-box">
            <span class="pc-label"><i class="bi bi-ticket-perforated"></i> 쿠폰</span>
            <span class="pc-value"><?php echo $coupon_count; ?>장</span>
        </a>
    </div>
</div>

<!-- 섹션: 병원 이용 -->
<div class="menu-section" style="border-bottom:8px solid #F8F8F8;">
    <div class="menu-section-title"><?php echo term('business', '병원'); ?> 이용</div>
    <a href="my_bookings.php" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-calendar-check"></i></span>
        <span class="menu-row-text">내 <?php echo term('booking', '예약'); ?> / 결제 내역</span>
        <?php if ($booking_count > 0): ?><span class="menu-row-badge"><?php echo $booking_count; ?></span><?php endif; ?>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <a href="my_wishlist.php" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-heart"></i></span>
        <span class="menu-row-text"><?php echo term('business', '병원'); ?>/<?php echo term('service', '시술'); ?> 찜 목록</span>
        <?php if ($wish_count > 0): ?><span class="menu-row-badge"><?php echo $wish_count; ?></span><?php endif; ?>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <a href="my_reviews.php" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-chat-quote"></i></span>
        <span class="menu-row-text">내가 작성한 <?php echo term('review', '후기'); ?></span>
        <?php if ($review_count > 0): ?><span class="menu-row-badge"><?php echo $review_count; ?></span><?php endif; ?>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <a href="my_notifications.php" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-bell"></i></span>
        <span class="menu-row-text">알림</span>
        <?php if ($unread_count > 0): ?><span class="menu-row-badge badge-red"><?php echo $unread_count; ?></span><?php endif; ?>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <a href="my_events.php" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-gift"></i></span>
        <span class="menu-row-text">이벤트 응모 내역</span>
        <?php if ($event_count > 0): ?><span class="menu-row-badge"><?php echo $event_count; ?></span><?php endif; ?>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
</div>

<!-- 섹션: 커뮤니티 -->
<div class="menu-section" style="border-bottom:8px solid #F8F8F8;">
    <div class="menu-section-title">커뮤니티</div>
    <a href="community.php?my=1" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-pencil-square"></i></span>
        <span class="menu-row-text">MY 커뮤니티 글</span>
        <?php if ($community_count > 0): ?><span class="menu-row-badge"><?php echo $community_count; ?></span><?php endif; ?>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <a href="my_wishlist.php?type=community" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-heart"></i></span>
        <span class="menu-row-text">MY 좋아요 목록</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
</div>

<!-- 섹션: 고객센터/공지 -->
<div class="menu-section">
    <a href="notification_settings.php" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-gear"></i></span>
        <span class="menu-row-text">알림 설정</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <a href="qna.php" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-headset"></i></span>
        <span class="menu-row-text">고객센터</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <a href="faq.php" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-megaphone"></i></span>
        <span class="menu-row-text">공지사항</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
</div>

<!-- 로그아웃 -->
<div class="logout-section">
    <a href="/hairwang/bbs/logout.php?url=<?php echo urlencode('/hairwang/gangnamunni/mypage.php'); ?>" class="logout-btn">로그아웃</a>
</div>

<?php else: ?>
<!-- 비로그인 상태: 로그인 안내 -->
<div style="padding:60px 20px;text-align:center;background:var(--bs-body-bg, #fff);border-bottom:8px solid #F8F8F8;">
    <div style="width:72px;height:72px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:32px;color:#ccc;">
        <i class="bi bi-person"></i>
    </div>
    <p style="font-size:16px;font-weight:600;color:var(--bs-body-color, #333);margin-bottom:8px;">로그인이 필요합니다</p>
    <p style="font-size:14px;color:#999;margin-bottom:24px;">로그인 후 마이페이지를 이용하실 수 있습니다.</p>
    <a href="/hairwang/bbs/login.php?url=<?php echo urlencode('/hairwang/gangnamunni/mypage.php'); ?>" style="display:inline-block;padding:14px 48px;background:#AE1932;color:#fff;border-radius:12px;font-size:15px;font-weight:600;text-decoration:none;">로그인</a>
    <div style="margin-top:12px;">
        <a href="/hairwang/bbs/register.php" style="font-size:13px;color:#999;text-decoration:none;">아직 회원이 아니신가요? <span style="color:#AE1932;font-weight:600;">회원가입</span></a>
    </div>
</div>

<!-- 비로그인에서도 접근 가능한 메뉴 -->
<div class="menu-section">
    <a href="qna.php" class="menu-row" style="margin-top:8px;">
        <span class="menu-row-icon"><i class="bi bi-headset"></i></span>
        <span class="menu-row-text">고객센터</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <a href="faq.php" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-megaphone"></i></span>
        <span class="menu-row-text">공지사항</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
</div>
<?php endif; ?>

<div class="bottom-spacer"></div>

<?php
// 하단 네비게이션
if (!$is_app_mode) { include_once __DIR__ . '/includes/bottom_nav.php'; }
?>

</body>
</html>
