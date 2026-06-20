<?php
/**
 * 관리자 인라인 편집 버튼 시스템
 * 이윰빌더 스타일 — 관리자 로그인 시 프론트엔드 섹션별 편집 버튼 표시
 *
 * 사용법:
 *   <?php ae('ad_settings', '광고 설정', 'bi-megaphone'); ?>
 *   <?php ae('hospitals', '병원 관리'); ?>
 */

// 관리자 로그인 여부 확인 (Gnuboard 세션과 별도 세션 모두 체크)
$_is_admin_on_front = false;
if (isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true) {
    $_is_admin_on_front = true;
} elseif (!empty($_COOKIE['PHPSESSID'])) {
    // Gnuboard 세션과 별도로 기본 PHP 세션 확인
    $orig_id = session_id();
    $orig_name = session_name();
    if ($orig_name !== 'PHPSESSID') {
        session_write_close();
        session_name('PHPSESSID');
        session_id($_COOKIE['PHPSESSID']);
        session_start();
        if (isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true) {
            $_is_admin_on_front = true;
        }
        session_write_close();
        // 원래 세션 복원
        session_name($orig_name);
        if ($orig_id) { session_id($orig_id); }
        session_start();
    }
}

// 관리자 대시보드 URL 매핑
$_admin_edit_urls = [
    'ad_settings'      => ['url' => 'admin_ad_settings.php',        'label' => '광고 설정',       'icon' => 'bi-megaphone'],
    'ads'              => ['url' => 'admin_ads.php',                'label' => '광고 관리',       'icon' => 'bi-badge-ad'],
    'promos'           => ['url' => 'admin_promos.php',             'label' => '프로모션 관리',   'icon' => 'bi-gift'],
    'hospitals'        => ['url' => 'admin_hospitals.php',          'label' => '병원 관리',       'icon' => 'bi-building'],
    'treatments'       => ['url' => 'admin_treatments.php',         'label' => '시술 관리',       'icon' => 'bi-scissors'],
    'reviews'          => ['url' => 'admin_review_wizard.php',      'label' => '후기 관리',       'icon' => 'bi-chat-quote'],
    'events'           => ['url' => 'admin_events.php',             'label' => '이벤트 관리',     'icon' => 'bi-calendar-event'],
    'theme'            => ['url' => 'admin_theme.php',              'label' => '테마 설정',       'icon' => 'bi-palette'],
    'design_tokens'    => ['url' => 'admin_design_tokens.php',      'label' => '디자인 토큰',     'icon' => 'bi-brush'],
    'design_mode'      => ['url' => 'admin_design_mode.php',        'label' => '디자인 모드',     'icon' => 'bi-toggles'],
    'navigation'       => ['url' => 'admin_navigation.php',         'label' => '네비게이션',      'icon' => 'bi-list'],
    'categories'       => ['url' => 'admin_categories.php',         'label' => '카테고리',        'icon' => 'bi-grid-3x3-gap'],
    'rankings'         => ['url' => 'admin_rankings.php',           'label' => '랭킹 관리',      'icon' => 'bi-trophy'],
    'community'        => ['url' => 'admin_community.php',          'label' => '커뮤니티 관리',   'icon' => 'bi-people'],
    'bookings'         => ['url' => 'admin_bookings.php',           'label' => '예약 관리',       'icon' => 'bi-calendar-check'],
    'members'          => ['url' => 'admin_members.php',            'label' => '회원 관리',       'icon' => 'bi-person'],
    'notifications'    => ['url' => 'admin_send_notification.php',  'label' => '알림 관리',       'icon' => 'bi-bell'],
    'dashboard'        => ['url' => 'dashboard.php',                'label' => '관리자 대시보드',  'icon' => 'bi-speedometer2'],
    'settings'         => ['url' => 'admin_ad_settings.php',        'label' => '사이트 설정',     'icon' => 'bi-gear'],
    'header'           => ['url' => 'admin_theme.php',              'label' => '헤더 설정',       'icon' => 'bi-window-sidebar'],
    'search'           => ['url' => 'admin_ad_settings.php',        'label' => '검색바 설정',     'icon' => 'bi-search'],
    'bottom_nav'       => ['url' => 'admin_navigation.php',         'label' => '하단 탭 설정',    'icon' => 'bi-phone-landscape'],
];

/**
 * 관리자 편집 버튼 출력
 * @param string $key   $_admin_edit_urls 키
 * @param string $label 커스텀 라벨 (생략 시 기본값)
 * @param string $icon  커스텀 아이콘 (생략 시 기본값)
 */
function ae($key, $label = '', $icon = '') {
    global $_is_admin_on_front, $_admin_edit_urls;
    if (!$_is_admin_on_front) return;

    $info = $_admin_edit_urls[$key] ?? null;
    if (!$info) return;

    // 병원 관리자는 자기 병원 관련 메뉴만 표시
    $role = $_SESSION['ad_admin_role'] ?? '';
    if (in_array($role, ['hospital_admin', 'hospital_sub_admin'])) {
        $hospital_allowed = ['hospitals','treatments','reviews','events','bookings','notifications'];
        if (!in_array($key, $hospital_allowed)) return;
    }

    $url   = $info['url'];
    $label = $label ?: $info['label'];
    $icon  = $icon ?: $info['icon'];

    echo '<a href="' . htmlspecialchars($url) . '" target="_blank" class="ae-btn" title="' . htmlspecialchars($label) . '">';
    echo '<i class="bi ' . $icon . '"></i> ' . htmlspecialchars($label);
    echo '</a>';
}

// CSS 한번만 출력
if ($_is_admin_on_front && empty($GLOBALS['_ae_css_loaded'])):
    $GLOBALS['_ae_css_loaded'] = true;
?>
<style>
.ae-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    font-size: 11px;
    font-weight: 600;
    color: #fff;
    background: rgba(174,25,50,0.85);
    border-radius: 4px;
    text-decoration: none;
    white-space: nowrap;
    position: absolute;
    top: 4px;
    right: 4px;
    z-index: 50;
    opacity: 0;
    transition: opacity 0.2s;
    backdrop-filter: blur(4px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.ae-btn:hover { background: rgba(174,25,50,1); color: #fff; }
.ae-wrap { position: relative; }
.ae-wrap:hover .ae-btn { opacity: 1; }
/* 관리자 바 */
.ae-topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 9999;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: #fff;
    font-size: 12px;
    padding: 4px 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}
.ae-topbar a { color: #7ecfff; text-decoration: none; font-size: 11px; padding: 2px 8px; border-radius: 4px; }
.ae-topbar a:hover { background: rgba(255,255,255,0.1); color: #fff; }
.ae-topbar .ae-sep { color: #444; margin: 0 2px; }
body.ae-active { padding-top: 30px !important; }
</style>
<script>document.body.classList.add('ae-active');</script>
<div class="ae-topbar">
    <?php
    $ae_role = $_SESSION['ad_admin_role'] ?? '';
    $ae_is_hospital = in_array($ae_role, ['hospital_admin', 'hospital_sub_admin']);
    $ae_hospital_name = $_SESSION['ad_admin_hospital_name'] ?? '';
    ?>
    <strong><i class="bi bi-gear-fill"></i> <?php echo $ae_is_hospital ? '병원 Admin' : 'Admin'; ?></strong>
    <span class="ae-sep">|</span>
    <a href="dashboard.php" target="_blank"><i class="bi bi-speedometer2"></i> 대시보드</a>
    <?php if ($ae_is_hospital): ?>
        <a href="partner_treatments.php" target="_blank"><i class="bi bi-scissors"></i> 시술관리</a>
        <a href="partner_wallet.php" target="_blank"><i class="bi bi-wallet2"></i> 지출/월렛</a>
        <a href="partner_settings.php" target="_blank"><i class="bi bi-gear"></i> 설정</a>
        <a href="partner_ai_settings.php" target="_blank"><i class="bi bi-robot"></i> AI설정</a>
    <?php else: ?>
        <a href="admin_ad_settings.php" target="_blank"><i class="bi bi-megaphone"></i> 광고설정</a>
        <a href="admin_theme.php" target="_blank"><i class="bi bi-palette"></i> 테마</a>
        <a href="admin_design_tokens.php" target="_blank"><i class="bi bi-brush"></i> 디자인토큰</a>
        <a href="admin_hospitals.php" target="_blank"><i class="bi bi-building"></i> 병원</a>
        <a href="admin_treatments.php" target="_blank"><i class="bi bi-scissors"></i> 시술</a>
        <a href="admin_events.php" target="_blank"><i class="bi bi-calendar-event"></i> 이벤트</a>
        <span class="ae-sep">|</span>
        <a href="admin_design_mode.php" target="_blank"><i class="bi bi-toggles"></i> 디자인모드</a>
        <a href="admin_ad_settings.php#tab-design" target="_blank"><i class="bi bi-sliders"></i> 디스플레이</a>
    <?php endif; ?>
    <span style="margin-left:auto;color:#888;">
        <i class="bi bi-person-badge"></i> <?php echo htmlspecialchars($_SESSION['ad_admin_name'] ?? $_SESSION['ad_admin_user'] ?? 'admin'); ?>
        <?php if ($ae_is_hospital && $ae_hospital_name): ?>
        <span style="color:#7ecfff;margin-left:4px;">(<?php echo htmlspecialchars($ae_hospital_name); ?>)</span>
        <?php endif; ?>
    </span>
</div>
<?php endif; ?>
