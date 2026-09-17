<?php
/**
 * 공통 헤더 레이아웃
 *
 * 사용법:
 *   $page_title = '페이지 제목';
 *   $show_search = true; // 검색바 표시 (기본: true)
 *   $page_css = '추가 CSS'; // 선택 (인라인)
 *   $page_css_file = 'assets/css/index-page.css'; // 선택 (파일)
 *   include __DIR__ . '/includes/header.php';
 *
 * 이 파일이 제공하는 것:
 *   - HTML head (Tailwind CDN, common.css, 테마 CSS)
 *   - 글로벌 네비게이션
 *   - 검색바 섹션 (선택)
 *   - <div class="main-content"> 시작
 */

// 기본값 설정
if (!isset($page_title)) $page_title = function_exists('site_name') ? site_name() : site_name();
if (!isset($page_title_only)) $page_title_only = false; // true면 "제목 | 사이트명" 대신 제목만 표시
if (!isset($show_search)) $show_search = true;
if (file_exists(__DIR__ . '/design_tokens.php')) { include_once __DIR__ . '/design_tokens.php'; }
if (!isset($page_css)) $page_css = '';
if (!isset($page_css_file)) $page_css_file = '';

// theme_helper가 로드되지 않았으면 로드
if (!function_exists('getTheme')) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../theme_helper.php';
}

// DB 연결이 있으면 테마 로드
if (isset($connect) && $connect && function_exists('loadThemeSettings')) {
    // 이미 로드되었으면 재로드하지 않음
    if (!defined('THEME_LOADED')) {
        loadThemeSettings($connect);
        define('THEME_LOADED', true);
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <base href="<?php echo htmlspecialchars(defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '/', ENT_QUOTES, 'UTF-8'); ?>">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo htmlspecialchars($page_title); ?><?php echo $page_title_only ? '' : ' | ' . htmlspecialchars(getTheme('site_name')); ?></title>
    <?php if (!empty($page_description)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($page_description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (!empty($page_robots)): ?>
    <meta name="robots" content="<?php echo htmlspecialchars($page_robots, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (empty($page_canonical)) $page_canonical = seoulbeautyCanonicalUrl($_SERVER['REQUEST_URI'] ?? ''); ?>
    <?php if (!empty($page_canonical)): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php if (!empty($page_naver_verification)): ?>
    <meta name="naver-site-verification" content="<?php echo htmlspecialchars($page_naver_verification, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <?php echo generateFaviconLinks(); ?>
    <?php echo generateGoogleFontsLink(); ?>
    <?php echo generateThemeCSS(); ?>

    <?php
    // Windows PHP dirname()이 '\'를 반환하면 //로 시작하는 잘못된 URL이 됨 → 상대경로 사용
    $base_dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    $css_base = ($base_dir === '' || $base_dir === '/') ? '' : $base_dir;
    ?>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<?php
// 관리자 인라인 편집 버튼 (이윰빌더 스타일)
// 페이지에서 $hide_admin_inline_edit = true 로 설정하면 해당 페이지에선 노출하지 않음
if (empty($hide_admin_inline_edit) && isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true) {
    if (file_exists(__DIR__ . '/admin_inline_edit.php')) {
        include_once __DIR__ . '/admin_inline_edit.php';
    }
}
?>

    <!-- Tailwind CSS (로컬 빌드) -->
    <link rel="stylesheet" href="<?php echo $css_base; ?>/assets/css/tailwind.css">

    <!-- 공통 CSS -->
    <link rel="stylesheet" href="<?php echo $css_base; ?>/assets/css/common.css?v=3">

    <?php if (!empty($page_css_file)): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($page_css_file); ?>">
    <?php endif; ?>

    <?php if (!empty($page_css)): ?>
    <style>
        <?php echo $page_css; ?>
    </style>
    <?php endif; ?>

    <script>
    (function () {
        'use strict';

        var tokenStorageKey = 'seoulbeauty_fcm_token';
        var registerUrl = <?php echo json_encode(SITE_BASE_PATH . 'push_fcm/get_android_regid.php', JSON_UNESCAPED_SLASHES); ?>;

        function registerFcmToken(token) {
            if (typeof token !== 'string' || token.length < 32) return;

            try {
                localStorage.setItem(tokenStorageKey, token);
            } catch (e) {
                // Token registration must still continue when storage is unavailable.
            }

            fetch(registerUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    token: token,
                    platform: 'android'
                })
            }).catch(function () {
                // A later page load retries the token saved in localStorage.
            });
        }

        // Callback name used by the native Android/iOS WebView bridge.
        window.onTokenReceived = registerFcmToken;
        window.receiveFCMToken = registerFcmToken;

        document.addEventListener('DOMContentLoaded', function () {
            try {
                var savedToken = localStorage.getItem(tokenStorageKey)
                    || localStorage.getItem('fcm_token');
                if (savedToken) registerFcmToken(savedToken);
            } catch (e) {
                // Ignore storage access failures.
            }

            if (window.Android && typeof window.Android.requestToken === 'function') {
                try {
                    window.Android.requestToken();
                } catch (e) {
                    // The published app may register directly through the legacy URL.
                }
            }

            if (window.FlutterBridge
                && typeof window.FlutterBridge.postMessage === 'function') {
                try {
                    window.FlutterBridge.postMessage(JSON.stringify({
                        action: 'requestFcmToken'
                    }));
                } catch (e) {
                    // A later page load retries the bridge request.
                }
            }
        });
    }());
    </script>
</head>
<body>
    <!-- 글로벌 네비게이션 (앱 모드 또는 $hide_global_nav 설정 시 숨김) -->
    <?php if (empty($is_app_mode) && empty($hide_global_nav)) { include __DIR__ . '/global_nav.php'; } ?>

<?php if ($show_search && empty($is_app_mode)):
    // 검색/지역필터 동작을 페이지별로 파라미터화 (기본: 병원 목록)
    //  - $search_target_page : 검색/지역 필터 적용 시 이동/유지할 페이지
    //  - $region_storage_key : 지역 선택 localStorage 키 (페이지 간 독립 유지)
    $search_target_page = $search_target_page ?? 'hospitals.php';
    $region_storage_key = $region_storage_key ?? 'sb_selected_regions';
?>
    <!-- 피그마 디자인 검색바 + 지역 필터 (640px 컨테이너 내) -->
    <div class="header-search-wrap">
        <input type="text" id="csb-keyword" class="hospital-search-input" value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES); ?>" placeholder="<?php echo function_exists('dt') ? htmlspecialchars(dt('fd_search_placeholder','시술, 주소를 검색해보세요')) : '시술, 주소를 검색해보세요'; ?>">
        <button id="regionToggleBtn" class="hospital-search-btn" onclick="toggleRegionPanel()">
            <i class="bi bi-geo-alt"></i> 지역 선택
        </button>
    </div>

    <!-- 선택된 지역 태그 바 (JS로 채워짐 — localStorage 기반) -->
    <div id="headerActiveBar" class="region-active-bar" style="display:none;max-width:640px;margin:0 auto;padding-left:16px;padding-right:16px;">

    <button class="btn-reset-bar" onclick="clearRegions()"><i class="bi bi-arrow-counterclockwise"></i> 초기화</button>
        <div id="headerRegionTags" class="region-active-tags"></div>
    </div>

    <!-- 오버레이 -->
    <div id="regionOverlay" onclick="closeRegionPanel()"></div>

    <!-- 지역 선택 패널 (fixed overlay) -->
    <div id="regionPanel">
        <?php
        require __DIR__ . '/site_regions.php';
        foreach ($region_groups as $gi => $group): ?>
        <?php if ($gi > 0): ?><hr class="region-divider"><?php endif; ?>
        <div class="region-grid">
            <?php foreach ($group as $r): ?>
            <label>
                <input type="checkbox" class="region-cb" value="<?php echo $r; ?>">
                <?php echo $r; ?>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <div class="region-btn-wrap">
            <button onclick="clearRegions()" class="btn-reset"><i class="bi bi-arrow-counterclockwise"></i> 초기화</button>
            <div id="selectedRegionTags"></div>
            <button onclick="applyRegionFilter()" class="btn-apply">필터 선택 완료</button>
        </div>
    </div>

    <script>
    function toggleRegionPanel() {
        var panel = document.getElementById('regionPanel');
        var overlay = document.getElementById('regionOverlay');
        var wrap = document.querySelector('.header-search-wrap');
        if (panel.style.display === 'block') {
            closeRegionPanel();
        } else {
            var rect = wrap.getBoundingClientRect();
            panel.style.top = rect.bottom + 'px';
            overlay.style.top = rect.bottom + 'px';
            panel.style.display = 'block';
            overlay.style.display = 'block';
        }
    }
    function closeRegionPanel() {
        document.getElementById('regionPanel').style.display = 'none';
        document.getElementById('regionOverlay').style.display = 'none';
    }

    // localStorage로 선택 저장/복원
    function saveRegionsToStorage() {
        var checked = [];
        document.querySelectorAll('.region-cb:checked').forEach(function(cb) { checked.push(cb.value); });
        localStorage.setItem('<?php echo $region_storage_key; ?>', JSON.stringify(checked));
    }
    function loadRegionsFromStorage() {
        try {
            var saved = JSON.parse(localStorage.getItem('<?php echo $region_storage_key; ?>') || '[]');
            if (saved.length > 0) {
                saved.forEach(function(val) {
                    var cb = document.querySelector('.region-cb[value="' + val + '"]');
                    if (cb) cb.checked = true;
                });
                updateRegionTags();
            }
        } catch(e) {}
        // URL의 region 파라미터도 반영
        var urlParams = new URLSearchParams(window.location.search);
        var regionParam = urlParams.get('region');
        if (regionParam) {
            regionParam.split(',').forEach(function(val) {
                var cb = document.querySelector('.region-cb[value="' + val.trim() + '"]');
                if (cb) cb.checked = true;
            });
            updateRegionTags();
        }
    }

    // 체크박스 변경 시 태그 업데이트 + 저장
    document.querySelectorAll('.region-cb').forEach(function(cb) {
        cb.addEventListener('change', function() { updateRegionTags(); saveRegionsToStorage(); });
    });
    function updateRegionTags() {
        var checked = document.querySelectorAll('.region-cb:checked');
        // 패널 내부 태그
        var container = document.getElementById('selectedRegionTags');
        container.innerHTML = '';
        checked.forEach(function(cb) {
            var tag = document.createElement('span');
            tag.className = 'region-tag';
            tag.innerHTML = cb.value + ' <span onclick="uncheckRegion(\'' + cb.value + '\')" style="cursor:pointer;">&times;</span>';
            container.appendChild(tag);
        });
        // 검색바 아래 active bar
        var bar = document.getElementById('headerActiveBar');
        var barTags = document.getElementById('headerRegionTags');
        barTags.innerHTML = '';
        if (checked.length > 0) {
            checked.forEach(function(cb) {
                var tag = document.createElement('span');
                tag.className = 'region-tag';
                tag.innerHTML = cb.value + ' <span onclick="uncheckRegion(\'' + cb.value + '\')" style="cursor:pointer;">&times;</span>';
                barTags.appendChild(tag);
            });
            bar.style.display = 'flex';
        } else {
            bar.style.display = 'none';
        }
        // 버튼 스타일
        var btn = document.getElementById('regionToggleBtn');
        if (checked.length > 0) {
            btn.innerHTML = '<i class="bi bi-geo-alt" style="color:#fff;margin-right:4px;"></i>지역 ' + checked.length + '개';
            btn.style.background = '#AE1932';
            btn.style.color = '#fff';
        } else {
            btn.innerHTML = '<i class="bi bi-geo-alt" style="color:#fff;margin-right:4px;"></i>지역 선택';
            btn.style.background = '#1A0000';
            btn.style.color = '#fff';
        }
    }
    function uncheckRegion(val) {
        var cb = document.querySelector('.region-cb[value="' + val + '"]');
        if (cb) { cb.checked = false; updateRegionTags(); saveRegionsToStorage(); }
    }
    function clearRegions() {
        document.querySelectorAll('.region-cb').forEach(function(cb) { cb.checked = false; });
        updateRegionTags();
        saveRegionsToStorage();
    }
    function applyRegionFilter() {
        var regions = [];
        document.querySelectorAll('.region-cb:checked').forEach(function(cb) { regions.push(cb.value); });
        saveRegionsToStorage();
        var keyword = document.getElementById('csb-keyword').value.trim();
        // 이미 대상 페이지면 기존 필터(category/treatment/sort/tab 등)를 유지, 아니면 새로 이동
        var target = '<?php echo $search_target_page; ?>';
        var onTarget = location.pathname.indexOf(target) !== -1;
        var url = onTarget ? new URL(location.href) : new URL(target, location.href);
        if (regions.length > 0) url.searchParams.set('region', regions.join(',')); else url.searchParams.delete('region');
        if (keyword) url.searchParams.set('q', keyword); else url.searchParams.delete('q');
        url.searchParams.delete('page'); // 검색/필터 변경 시 페이지네이션 초기화
        location.href = url.pathname + url.search;
    }
    // 키워드 엔터 검색
    document.getElementById('csb-keyword').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') { applyRegionFilter(); }
    });
    // 페이지 로드 시 저장된 지역 복원
    loadRegionsFromStorage();
    </script>
    <?php endif; ?>

    <!-- 메인 콘텐츠 영역 (640px 중앙 정렬) -->
    <div class="main-content">
