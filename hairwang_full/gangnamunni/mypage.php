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

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB error');
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_once __DIR__ . '/includes/page_cache.php';

// Phase5: SSO 토큰 자동 로그인 (앱 WebView에서 세션 없을 때)
include_once __DIR__ . '/includes/sso_auto_login.php';

// 로그인 체크
$is_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];

if (!$is_logged_in) {
    // 웹 방문자는 로그인 후 마이페이지로 돌아온다.
    if (!$is_app_mode) {
        $login_return = rtrim(SITE_BASE_PATH, '/') . '/mypage.php';
        header('Location: ' . rtrim(SITE_BASE_PATH, '/') . '/auth/login.php?url=' . urlencode($login_return));
        exit;
    }
    // 7312fb73: 로그아웃 직후 WebView 재진입은 리다이렉트/JSON 오류 없이
    // 아래 로그인 안내 HTML을 렌더링한다. 로그인 링크는 app=1을 유지한다.
    $mb_id = ''; $mb_nick = '';
} else {
    $mb_id = $member['mb_id'];
    $mb_no = $member['mb_no'] ?? 0; // 숫자 ID (wishlist/points/coupon용)
    $mb_nick = $member['mb_nick'] ?? $mb_id;
    gangnamunni_page_cache(1800, 'private');
}

// 프로필 사진 확인
$profile_photo_url = null;
if ($is_logged_in) {
    $profile_photo_dir = __DIR__ . '/uploads/profile/';
    // 파일 경로에는 영숫자/밑줄/하이픈만 허용해 경로 이탈을 방지한다.
    $profile_file_id = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$mb_id);
    foreach ($profile_file_id !== '' ? ['jpg', 'jpeg', 'png', 'webp'] : [] as $_ext) {
        $_path = $profile_photo_dir . $profile_file_id . '.' . $_ext;
        if (file_exists($_path)) {
            $profile_photo_url = 'uploads/profile/' . rawurlencode($profile_file_id) . '.' . $_ext . '?v=' . filemtime($_path);
            break;
        }
    }
}

// 로그인 상태에서 실제 화면에 표시하는 포인트만 조회한다.
$booking_count = 0;
if ($is_logged_in):
// 포인트 잔액 — 원장(#포인트제도) 스냅샷 사용 (member_id는 INT = mb_no)
// 스키마 미설치 등으로 원장을 못 써도 마이페이지가 죽지 않도록 안전 조회 사용.
require_once __DIR__ . '/includes/points_ledger.php';
$point_balance = points_balance_safe($connect, $mb_no);

// 예약 건수 — 메뉴 행 배지에 표시한다.
// 조회 범위는 my_bookings.php 와 동일하게 맞춘다(숫자와 목록이 어긋나면 안 된다):
//   회원 mb_id 일치 + mb_id 가 비어있는 과거 예약 중 본인 전화번호 일치.
$_bk_phone = preg_replace('/[^0-9]/', '', (string)($member['mb_hp'] ?? ''));
if ($_bk_stmt = mysqli_prepare($connect,
    "SELECT COUNT(*) AS c FROM gangnamunni_bookings
     WHERE mb_id = ?
        OR ((mb_id IS NULL OR mb_id = '') AND ? <> ''
            AND REPLACE(REPLACE(phone, '-', ''), ' ', '') = ?)")) {
    mysqli_stmt_bind_param($_bk_stmt, 'sss', $mb_id, $_bk_phone, $_bk_phone);
    mysqli_stmt_execute($_bk_stmt);
    $_bk_row = mysqli_fetch_assoc(mysqli_stmt_get_result($_bk_stmt));
    $booking_count = (int)($_bk_row['c'] ?? 0);
    mysqli_stmt_close($_bk_stmt);
}

endif; // $is_logged_in

if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
// 글로벌 네비게이션은 넣지 않는다 — 이 페이지는 자체 "마이페이지" 헤더를 쓴다.
// (global_nav.php 가 정의하는 변수에 의존하는 코드는 없다: $is_logged_in 은 위에서 직접 판정)
$primary_color = getTheme('primary_color', '#AE1932');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $primary_color)) $primary_color = '#AE1932';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>마이페이지 — <?php echo htmlspecialchars(site_name()); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root { --pri: <?php echo $primary_color; ?>; --page-bg:#f3f3f3; --card-bg:#fff; }
* { margin:0; padding:0; box-sizing:border-box; }
html { background:#3f3f3f; }
body,
button,
input,
select,
textarea {
    font-family:'Noto Sans KR',sans-serif !important;
}
body {
    background:var(--page-bg); max-width:640px; margin:0 auto; min-height:100vh;
    padding-bottom:calc(82px + env(safe-area-inset-bottom, 0px)); color:#111;
}

/* 페이지 헤더 */
/* 글로벌 네비를 아예 렌더하지 않으므로 .global-nav-* 를 가리는 규칙은 필요 없다. */
.page-header {
    display:flex; align-items:center; justify-content:space-between;
    height:62px; padding:0 20px; background:var(--card-bg); border-bottom:0;
}
.page-header-title { font-size:calc(21px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:800; color:#000; letter-spacing:-.5px; }
.page-header .settings-btn {
    width:40px; height:40px; display:flex; align-items:center; justify-content:center;
    border-radius:50%; background:transparent; border:none; cursor:pointer; color:#aaa; font-size:calc(29px * var(--fs-scale,1) * var(--fs-page,1));
    text-decoration:none;
}
.page-header .settings-btn:hover { background:var(--bs-tertiary-bg, #f5f5f5); }
/* 프로필 섹션 */
.profile-section {
    display:flex; align-items:center; gap:14px;
    min-height:88px; margin:26px 20px 16px; padding:18px 22px; background:var(--card-bg); cursor:pointer;
    text-decoration:none; color:inherit;
}
.profile-section:hover { background:#fafafa; }
.profile-avatar {
    width:52px; height:52px; border-radius:50%; background:#D9D9D9;
    display:flex; align-items:center; justify-content:center;
    font-size:calc(22px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:700; color:#fff; flex-shrink:0;
}
.profile-info-wrap { flex:1; min-width:0; }
.profile-name { font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:750; color:#000; }
.profile-sub { font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color:#999; margin-top:2px; }
.profile-arrow { color:#000; font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1)); flex-shrink:0; }

/* 메뉴 섹션 */
.menu-section { background:var(--card-bg); border-radius:0; margin:0 20px 16px; padding:18px 22px 8px; }
.menu-section.compact-section { padding-top:5px; padding-bottom:5px; }
.menu-section-title {
    width:100%; padding:0 0 3px; font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:600; color:#999; letter-spacing:-.02em;
}
.menu-row {
    display:flex; align-items:center; gap:14px;
    min-height:68px; padding:15px 0; text-decoration:none; color:#000;
    transition: background 0.15s;
}
.menu-row:hover { background:#fafafa; }
.menu-row-icon {
    width:28px; height:32px; display:flex; align-items:center; justify-content:center;
    font-size:calc(25px * var(--fs-scale,1) * var(--fs-page,1)); color:#111; flex-shrink:0;
}
.menu-icon-point {
    width:27px; height:27px; border:1.6px solid #111; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    font-family:Arial,sans-serif; font-size:18px; font-weight:500; line-height:1;
}
.menu-icon-review {
    position:relative; width:25px; height:22px; border:1.6px solid #111; border-radius:4px;
}
.menu-icon-review::before {
    content:""; position:absolute; left:5px; top:6px; width:11px; height:1.5px;
    background:#111; box-shadow:0 5px 0 #111;
}
.menu-icon-review::after {
    content:""; position:absolute; left:5px; bottom:-5px; width:7px; height:7px;
    border-left:1.6px solid #111; transform:skewY(-38deg); background:#fff;
}
.menu-row-text { flex:1; font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1)); font-weight:650; letter-spacing:-.35px; }
.menu-row-arrow { color:#000; font-weight:900; font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1)); flex-shrink:0; }

/* 광고 배너 */
.advertisement-section {
    position: relative;
    margin: 0 20px 16px;
    display: block;
    background: #ead1cc;
    overflow: hidden;
}
.advertisement-section img {
    display: block;
    width: 100%;
    height: auto;
}
.advertisement-click-link {
    position: absolute;
    inset: 0;
    -webkit-tap-highlight-color: transparent;
}
.advertisement-click-link:focus-visible {
    outline: 2px solid #AE1932;
    outline-offset: 2px;
}

/* 메뉴 배지 */
/* 배지는 알약 배경 없이 텍스트만 — padding/border-radius 는 배경이 없으면 의미가 없어
   함께 제거하고, 화살표와의 간격만 margin 으로 유지한다. */
.menu-row-badge {
    font-size: calc(17px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; color: var(--pri, #AE1932);
    margin-right: 2px; white-space:nowrap;
}

@media (max-width:380px) {
    .profile-section, .menu-section, .advertisement-section { margin-left:14px; margin-right:14px; }
    .menu-section { padding-left:18px; padding-right:18px; }
    .menu-row { gap:11px; }
    .menu-row-text { font-size:calc(16px * var(--fs-scale,1) * var(--fs-page,1)); }
}
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>

<!-- 페이지 헤더: "마이페이지" + 설정 아이콘 -->
<div class="page-header">
    <span class="page-header-title">마이페이지</span>
    <a href="notification_settings.php" class="settings-btn" title="설정" aria-label="설정"><i class="bi bi-gear"></i></a>
</div>

<?php if ($is_logged_in): ?>
<!-- 프로필 섹션: 이름 + 화살표 (클릭 시 프로필 수정) -->
<a href="profile_edit.php" class="profile-section">
    <div class="profile-avatar" style="<?php echo $profile_photo_url ? 'background:#D9D9D9;overflow:hidden;padding:0;' : ''; ?>">
        <?php if ($profile_photo_url): ?>
            <img src="<?php echo htmlspecialchars($profile_photo_url); ?>" alt="프로필" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
        <?php else: ?>
            <?php echo htmlspecialchars(mb_substr($mb_nick, 0, 1), ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
    </div>
    <div class="profile-info-wrap">
        <div class="profile-name"><?php echo htmlspecialchars($mb_nick); ?></div>
    </div>
    <span class="profile-arrow"><i class="bi bi-chevron-right" style="color:#000;"></i> </span>
</a>

<!-- 섹션: 병원 이용 -->
<div class="menu-section">
    <div class="menu-section-title"><?php echo term('business', '병원'); ?> 이용</div>
    <a href="my_bookings.php" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-calendar-check"></i></span>
        <span class="menu-row-text">내 <?php echo term('booking', '예약'); ?> / 결제 내역</span>
        <?php if ($booking_count > 0): ?>
        <span class="menu-row-badge"><?php echo number_format($booking_count); ?>건</span>
        <?php endif; ?>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <?php // 포인트(#포인트제도) — 상단 포인트/쿠폰 박스를 걷어내고 예약과 같은 메뉴 행으로 노출한다. ?>
    <a href="my_points.php" class="menu-row">
        <span class="menu-row-icon"><span class="menu-icon-point" aria-hidden="true">P</span></span>
        <span class="menu-row-text">포인트</span>
        <span class="menu-row-badge"><?php echo number_format($point_balance); ?>P</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <a href="my_wishlist.php" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-star"></i></span>
        <span class="menu-row-text"><?php echo term('business', '병원'); ?> 찜</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <a href="my_reviews.php" class="menu-row">
        <span class="menu-row-icon"><span class="menu-icon-review" aria-hidden="true"></span></span>
        <span class="menu-row-text">내가 작성한 <?php echo term('review', '후기'); ?></span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
</div>

<div class="advertisement-section">
    <img src="assets/media/mypage-app-intro-banner.jpg?v=<?php echo (int)filemtime(__DIR__ . '/assets/media/mypage-app-intro-banner.jpg'); ?>" alt="미용시술, 시술별 한눈에 병원선택. 원하는 시술, 서울미녀에서 최저가로 찾아보세요.">
    <a class="advertisement-click-link" href="about_project.php" aria-label="서울미녀 앱 소개 페이지로 이동"></a>
</div>

<!-- 섹션: 커뮤니티 -->
<div class="menu-section">
    <div class="menu-section-title">커뮤니티</div>
    <a href="community.php?my=1" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-pencil-square"></i></span>
        <span class="menu-row-text">MY 커뮤니티 글</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <a href="my_wishlist.php?type=community" class="menu-row">
        <span class="menu-row-icon"><i class="bi bi-heart"></i></span>
        <span class="menu-row-text">MY 좋아요 목록</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
</div>

<!-- 섹션: 고객센터/공지 -->
<div class="menu-section compact-section">
    <a href="qna.php" class="menu-row">
        <span class="menu-row-text">고객센터</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
    <a href="faq.php" class="menu-row">
        <span class="menu-row-text">공지사항</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
</div>

<?php // 로그아웃 / 회원 탈퇴 는 알림·계정 설정(notification_settings.php) 의 "계정 관리" 로 이동했다. ?>

<?php else: ?>
<!-- 비로그인 상태: 로그인 안내 -->
<div style="padding:60px 20px;text-align:center;background:var(--bs-body-bg, #fff);border-bottom:8px solid #F8F8F8;">
    <div style="width:72px;height:72px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:32px;color:#ccc;">
        <i class="bi bi-person"></i>
    </div>
    <p style="font-size:16px;font-weight:600;color:var(--bs-body-color, #333);margin-bottom:8px;">로그인이 필요합니다</p>
    <p style="font-size:14px;color:#999;margin-bottom:24px;">로그인 후 마이페이지를 이용하실 수 있습니다.</p>
    <?php // 앱 WebView 에서는 로그인 후에도 app=1 로 돌아와야 앱 전용 레이아웃이 유지된다. (한글 주석)
    $login_return = rtrim(SITE_BASE_PATH, '/') . '/mypage.php' . ($is_app_mode ? '?app=1' : ''); ?>
    <a href="<?php echo rtrim(SITE_BASE_PATH, '/'); ?>/auth/login.php?url=<?php echo urlencode($login_return); ?>" style="display:inline-block;padding:14px 48px;background:#AE1932;color:#fff;border-radius:12px;font-size:15px;font-weight:600;text-decoration:none;">로그인</a>
    <div style="margin-top:12px;">
        <a href="<?php echo G5_BBS_URL; ?>/register.php" style="font-size:13px;color:#999;text-decoration:none;">아직 회원이 아니신가요? <span style="color:#AE1932;font-weight:600;">회원가입</span></a>
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
        <span class="menu-row-text">FAQ</span>
        <span class="menu-row-arrow"><i class="bi bi-chevron-right"></i></span>
    </a>
</div>
<?php endif; ?>

<?php
// 하단 네비게이션
if (!$is_app_mode) { include_once __DIR__ . '/includes/bottom_nav.php'; }
?>

</body>
</html>
