<?php

// SeoulBeautyApp UA 감지 (향후 앱스토어 리다이렉트용)
$is_seoul_beauty_app = isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'SeoulBeautyApp') !== false;

// 앱/브라우저 동일 표시 모드 (현재 false -> 향후 앱스토어 리다이렉트 시 true로 전환)
$in_app = false;
if (!function_exists('term')) { require_once __DIR__ . '/industry_terms.php'; }


/**
 * 글로벌 상단 네비게이션 헤더
 * 모든 페이지 최상단에 표시되는 메인 메뉴
 * 모바일/PC 동일한 메뉴 표시 (햄버거 메뉴 없음)
 * 로그인 버튼 포함 (비로그인 시에만 표시)
 */

// 그누보드 common.php 로드하여 로그인 상태 확인
$g5_common_path = dirname(__DIR__, 2) . '/common.php';
if (file_exists($g5_common_path) && !defined('G5_PATH')) {
    include_once $g5_common_path;
}

// [버그수정 2026-06-15] SSO 자동로그인 — 로그인했는데 상단바에 로그인버튼 뜨는 문제 해결
if (file_exists(dirname(__DIR__).'/gangnamunni/includes/sso_auto_login.php') || file_exists(__DIR__.'/sso_auto_login.php')) {
    @include_once __DIR__ . '/sso_auto_login.php';
}

// 로그인 상태 확인 (그누보드 변수 사용)
$is_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];

// BBS URL 직접 지정 (gangnamunni는 /gangnamunni/ 에서 실행, bbs는 /bbs/ 에 있음)
$bbs_base_url = (defined('SITE_BASE_PATH') && SITE_BASE_PATH === '/gangnamunni/') ? '/bbs/' : '/hairwang/bbs/';
$login_url = $bbs_base_url . 'login.php';
$register_url = $bbs_base_url . 'register.php';
$mypage_url = $bbs_base_url . 'member_confirm.php';
$logout_url = $bbs_base_url . 'logout.php';
$member_nick = $is_logged_in ? (isset($member['mb_nick']) ? $member['mb_nick'] : $member['mb_id']) : '';

// 테마 설정 로드 (아직 로드되지 않았다면)
if (!isset($THEME_SETTINGS) || empty($THEME_SETTINGS)) {
    if (isset($connect)) {
        loadThemeSettings($connect);
    }
}

$site_name = getTheme('site_name', site_name());
$site_logo_path = getTheme('site_logo_path', 'assets/images/logo_128.png');
$login_btn_bg = getTheme('login_button_bg', '#AE1932');
$login_btn_text = getTheme('login_button_text', '#FFFFFF');

// 현재 페이지 파악 (활성 메뉴 표시용)
// 네비게이션 표시 설정 로드
require_once __DIR__ . '/settings_helper.php';
$nav_top_mobile = isset($connect) ? getSettingValue($connect, 'nav_top_show_mobile', '0') : '0';
$nav_top_desktop = isset($connect) ? getSettingValue($connect, 'nav_top_show_desktop', '1') : '1';

$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
/* 글로벌 네비게이션 */
.global-nav {
    position: fixed;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100%;
    max-width: 640px;
    height: 48px;
    background: var(--bs-body-bg, #fff);
    border-bottom: 1px solid var(--bs-border-color, #f0f0f0);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 8px;
    z-index: 1000;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
}

.global-nav-logo {
    display: flex;
    align-items: center;
    gap: 4px;
    text-decoration: none;
    color: var(--primary-color, #AE1932);
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}

.global-nav-logo img {
    height: 24px;
    width: auto;
}

.global-nav-center {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
    justify-content: center;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.global-nav-center::-webkit-scrollbar {
    display: none;
}

.global-nav-item {
    text-decoration: none;
    color: var(--bs-secondary-color, #666666);
    font-size: 12px;
    font-weight: 500;
    padding: 4px 0;
    transition: all 0.2s;
    white-space: nowrap;
}

.global-nav-item:hover {
    color: var(--primary-color, #AE1932);
}

.global-nav-item.active {
    color: var(--primary-color, #AE1932);
    font-weight: 700;
    border-bottom: 2px solid var(--primary-color, #AE1932);
}

/* 로그인 버튼 */
.global-nav-login {
    flex-shrink: 0;
}

.login-btn {
    display: inline-block;
    background: <?php echo htmlspecialchars($login_btn_bg); ?>;
    color: <?php echo htmlspecialchars($login_btn_text); ?>;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: all 0.2s;
}

.login-btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

/* 회원가입 버튼 */
.register-btn {
    display: inline-block;
    background: transparent;
    color: var(--bs-secondary-color, #666);
    padding: 6px 8px;
    font-size: 11px;
    font-weight: 500;
    text-decoration: none;
    white-space: nowrap;
}

.register-btn:hover {
    color: var(--primary-color, #AE1932);
}

/* 로그인된 사용자 영역 */
.user-menu {
    display: flex;
    align-items: center;
    gap: 8px;
}

.user-nick {
    font-size: 11px;
    font-weight: 600;
    color: var(--bs-body-color, #333);
    white-space: nowrap;
    max-width: 60px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: inline-block;
}

.user-menu-link {
    font-size: 11px;
    color: var(--bs-secondary-color, #666);
    text-decoration: none;
}

.user-menu-link:hover {
    color: var(--primary-color, #AE1932);
}

.user-menu-divider {
    color: #ddd;
    font-size: 10px;
}

/* 모바일 작은 화면 */
/* 네비게이션 반응형 표시 설정 */
.global-nav--hide-mobile-menu .global-nav-center { display: none; }

@media (min-width: 768px) {
    .global-nav--hide-mobile-menu .global-nav-center { display: flex; }
    .global-nav--hide-desktop-menu .global-nav-center { display: none; }
}

/* #7 모바일에서 로그인 시 중앙 메뉴 숨김 (레이아웃 깨짐 방지) */
@media (max-width: 768px) {
    .global-nav-center {
        display: none;
    }
}
/* [프론트 #4 - 2026-05-28] 로그인 시 좁은 데스크탑(<=1024px)에서도 중앙 메뉴 숨김 — 레이아웃 깨짐 방지 */
@media (max-width: 1024px) {
    .global-nav.is-logged-in .global-nav-center {
        display: none !important;
    }
}

/* 모바일 작은 화면 */
@media (max-width: 400px) {
    .global-nav-center {
        display: none;
    }

    .login-btn {
        padding: 5px 8px;
        font-size: 10px;
    }

    .user-menu {
        gap: 4px;
    }

    .user-nick {
        max-width: 40px;
        font-size: 10px;
    }

    .user-menu-link {
        font-size: 10px;
    }

    .user-menu-divider {
        display: none;
    }
}

/* PC (768px 이상) */
@media (min-width: 768px) {
    .global-nav {
        height: 56px;
        padding: 0 16px;
    }

    .global-nav-logo {
        font-size: 18px;
        gap: 8px;
    }

    .global-nav-logo img {
        height: 32px;
    }

    .global-nav-center {
        gap: 16px;
    }

    .global-nav-item {
        font-size: 14px;
    }

    .login-btn {
        padding: 8px 16px;
        font-size: 13px;
        border-radius: 8px;
    }
}

/* 글로벌 네비게이션 여백 */
.global-nav-spacer {
    height: 48px;
}

@media (min-width: 768px) {
    .global-nav-spacer {
        height: 56px;
    }
}

</style>

<!-- 글로벌 네비게이션 바 -->
<?php if (!$in_app): ?>
<div class="global-nav <?php
    if ($nav_top_mobile !== '1') echo 'global-nav--hide-mobile-menu ';
    if ($nav_top_desktop !== '1') echo 'global-nav--hide-desktop-menu ';
    if ($is_logged_in) echo 'is-logged-in '; // [프론트 #4 - 2026-05-28] 로그인 상태 클래스
?>">
    <a href="index.php" class="global-nav-logo">
        <?php if (file_exists($site_logo_path)): ?>
            <img src="<?php echo htmlspecialchars($site_logo_path); ?>" alt="<?php echo htmlspecialchars($site_name); ?>">
        <?php else: ?>
            <span><?php echo htmlspecialchars($site_name); ?></span>
        <?php endif; ?>
    </a>

    <nav class="global-nav-center">
        <a href="index.php" class="global-nav-item <?php echo ($current_page === 'index.php') ? 'active' : ''; ?>">홈</a>
        <a href="reviews.php" class="global-nav-item <?php echo ($current_page === 'reviews.php') ? 'active' : ''; ?>"><?= term('review') ?></a>
        <a href="hospitals.php" class="global-nav-item <?php echo ($current_page === 'hospitals.php' || $current_page === 'detail.php') ? 'active' : ''; ?>"><?= term('business') ?></a>
        <a href="events.php" class="global-nav-item <?php echo ($current_page === 'events.php') ? 'active' : ''; ?>"><?= term('event') ?></a>
        <a href="tips.php?type=community" class="global-nav-item <?php echo (isset($_GET['type']) && $_GET['type'] === 'community') ? 'active' : ''; ?>">커뮤니티</a>
        <a href="columns.php" class="global-nav-item <?php echo ($current_page === 'columns.php') ? 'active' : ''; ?>">칼럼</a>
        <a href="price_compare.php" class="global-nav-item <?php echo ($current_page === 'price_compare.php') ? 'active' : ''; ?>">가격비교</a>
        <a href="treatments.php" class="global-nav-item <?php echo ($current_page === 'treatments.php') ? 'active' : ''; ?>"><?= term('service', '시술') ?></a>
        <a href="hospital_ranking.php" class="global-nav-item <?php echo ($current_page === 'hospital_ranking.php') ? 'active' : ''; ?>">랭킹</a>
        <a href="my_bookings.php" class="global-nav-item <?php echo ($current_page === 'my_bookings.php') ? 'active' : ''; ?>">내 예약</a>
    </nav>

    <div class="global-nav-login">
        <?php if ($is_logged_in): ?>
        <div class="user-menu">
            <span class="user-nick"><?php echo htmlspecialchars($member_nick); ?>님</span>
            <span class="user-menu-divider">|</span>
            <a href="mypage.php" class="user-menu-link">마이</a>
            <span class="user-menu-divider">|</span>
            <a href="<?php echo htmlspecialchars($logout_url); ?>" class="user-menu-link">로그아웃</a>
        </div>
        <?php else: ?>
        <a href="<?php echo htmlspecialchars($login_url); ?>" class="login-btn">로그인 / <?= term('member') ?>가입</a>
        <?php endif; ?>
    </div>
</div>

<div class="global-nav-spacer"></div>
<?php endif; ?>
