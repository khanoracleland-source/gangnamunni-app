<?php
if (defined('_BOTTOM_NAV_LOADED')) return;
define('_BOTTOM_NAV_LOADED', true);
/**
 * 하단 고정 네비게이션 바 (4탭) — 피그마 디자인 반영
 * 홈 / 병원 / 커뮤니티 / 마이페이지
 *
 * - 탭 클릭 시 즉시 active 전환 → 페이지 이동 (깜빡임 방지)
 * - 콘텐츠 영역만 페이드인 애니메이션
 * - 관리자 설정으로 각 탭 ON/OFF 가능
 */
if (!function_exists('term')) { require_once __DIR__ . '/industry_terms.php'; }
$current_page = basename($_SERVER['PHP_SELF']);
$is_home = ($current_page === 'index.php');
$is_hospital = in_array($current_page, ['hospitals.php', 'detail.php', 'doctor_detail.php', 'booking.php']);
$is_community = in_array($current_page, ['community.php', 'community_write.php', 'detail_community.php', 'reviews.php', 'review_write.php']);
$is_my = in_array($current_page, ['mypage.php', 'my_bookings.php', 'my_notifications.php', 'my_events.php', 'my_wishlist.php', 'my_reviews.php', 'my_points.php', 'my_coupons.php', 'profile_edit.php', 'notification_settings.php', 'qna.php', 'faq.php', 'chat.php']);

// 관리자 설정에서 탭 ON/OFF 로드
$bottom_nav_settings = [];
if (isset($connect) && $connect) {
    $nav_q = mysqli_query($connect, "SELECT setting_key, setting_value FROM gangnamunni_settings WHERE setting_group = 'bottom_nav'");
    if ($nav_q) {
        while ($row = mysqli_fetch_assoc($nav_q)) {
            $bottom_nav_settings[$row['setting_key']] = $row['setting_value'];
        }
    }
}

// 각 탭 활성화 여부 (기본값: 활성)
$tab_home_on = ($bottom_nav_settings['bottom_nav_home_enabled'] ?? '1') === '1';
$tab_hospital_on = ($bottom_nav_settings['bottom_nav_hospital_enabled'] ?? '1') === '1';
$tab_community_on = ($bottom_nav_settings['bottom_nav_community_enabled'] ?? '1') === '1';
$tab_my_on = ($bottom_nav_settings['bottom_nav_my_enabled'] ?? '1') === '1';

// 피그마 아이콘: 커스텀 이미지 사용 (assets/icons/)
$icon_base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$all_nav_items = [
    ['url' => 'index.php', 'icon_img' => $icon_base . '/assets/icons/nav_home.png', 'label' => '홈', 'active' => $is_home, 'enabled' => $tab_home_on],
    ['url' => 'hospitals.php', 'icon_img' => $icon_base . '/assets/icons/nav_hospital.png', 'label' => term('business', '병원'), 'active' => $is_hospital, 'enabled' => $tab_hospital_on],
    ['url' => 'community.php', 'icon_img' => $icon_base . '/assets/icons/nav_community.png', 'label' => '커뮤니티', 'active' => $is_community, 'enabled' => $tab_community_on],
    ['url' => 'mypage.php', 'icon_img' => $icon_base . '/assets/icons/nav_mypage.png', 'label' => '마이페이지', 'active' => $is_my, 'enabled' => $tab_my_on],
];

// 활성화된 탭만 필터링
$nav_items = array_filter($all_nav_items, function($item) { return $item['enabled']; });
$tab_count = count($nav_items);

// 탭이 0개이면 네비 숨김
if ($tab_count === 0) return;
?>
<style>
/* 하단 탭 — 피그마 디자인 (77px, 4탭, 커스텀 아이콘) */
.bottom-nav {
    position: fixed;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100%;
    max-width: 640px;
    height: 77px;
    background: var(--bs-body-bg, #fff);
    border-top: 1px solid var(--bs-border-color, #f0f0f0);
    display: flex;
    align-items: stretch;
    z-index: 9999;
    will-change: transform;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}
.bottom-nav-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: var(--color-inactive, #aaaaaa);
    font-size: 12px;
    font-weight: 500;
    gap: 4px;
    transition: color 0.15s;
    position: relative;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
}
.bottom-nav-item .nav-icon {
    width: 25px;
    height: 25px;
    /* 기본: 회색 (inactive) — 원본 이미지 색상을 회색으로 통일 */
    filter: grayscale(100%) brightness(0.7);
}
.bottom-nav-item.active {
    color: #ae1932;
    font-weight: 700;
}
.bottom-nav-item.active .nav-icon {
    /* Active: 피그마 빨간색 (#ae1932) 색조 적용 */
    filter: brightness(0) saturate(100%) invert(15%) sepia(80%) saturate(3500%) hue-rotate(340deg) brightness(80%) contrast(100%);
}
.bottom-nav-item:hover {
    color: #ae1932;
}
.bottom-nav-item:hover .nav-icon {
    filter: brightness(0) saturate(100%) invert(15%) sepia(80%) saturate(3500%) hue-rotate(340deg) brightness(80%) contrast(100%);
}
.bottom-nav-item .nav-badge {
    position: absolute;
    top: 8px;
    right: calc(50% - 16px);
    background: var(--color-primary, #AE1932);
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    padding: 0 4px;
    border-radius: 6px;
    min-width: 14px;
    text-align: center;
    line-height: 14px;
}

/* 콘텐츠 페이드인 애니메이션 */
@keyframes bnFadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.main-content, .container, .page-content, body > .container-fluid {
    animation: bnFadeIn 0.2s ease-out;
}
</style>

<nav class="bottom-nav" id="bottomNav">
<?php foreach ($nav_items as $item): ?>
    <a href="<?php echo $item['url']; ?>" class="bottom-nav-item<?php echo $item['active'] ? ' active' : ''; ?>">
        <img src="<?php echo htmlspecialchars($item['icon_img']); ?>" alt="<?php echo htmlspecialchars($item['label']); ?>" class="nav-icon">
        <span><?php echo $item['label']; ?></span>
    </a>
<?php endforeach; ?>
</nav>

<script>
(function() {
    var nav = document.getElementById('bottomNav');
    if (!nav) return;

    // 탭 클릭: 즉시 active 전환 → 페이지 로드 시작
    nav.addEventListener('click', function(e) {
        var item = e.target.closest('.bottom-nav-item');
        if (!item) return;

        if (item.classList.contains('active')) {
            e.preventDefault();
            return;
        }

        // 즉시 시각적 전환 (CSS filter가 active 클래스로 처리)
        nav.querySelectorAll('.bottom-nav-item').forEach(function(el) {
            el.classList.remove('active');
        });
        item.classList.add('active');

        var content = document.querySelector('.main-content') || document.querySelector('.container');
        if (content) {
            content.style.transition = 'opacity 0.12s ease-out';
            content.style.opacity = '0.3';
        }
    });

    // BFCache 복원 시 active 상태 보정
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            var page = location.pathname.split('/').pop() || 'index.php';
            nav.querySelectorAll('.bottom-nav-item').forEach(function(el) {
                var href = el.getAttribute('href');
                el.classList.toggle('active', href === page);
            });
            var content = document.querySelector('.main-content') || document.querySelector('.container');
            if (content) { content.style.opacity = '1'; }
        }
    });
})();
</script>
