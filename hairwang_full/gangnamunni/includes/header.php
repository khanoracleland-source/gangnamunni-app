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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo htmlspecialchars(defined('SITE_BASE_PATH') ? SITE_BASE_PATH : '/gangnamunni/', ENT_QUOTES, 'UTF-8'); ?>">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo htmlspecialchars($page_title); ?> | <?php echo getTheme('site_name'); ?></title>
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
if (isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true) {
    if (file_exists(__DIR__ . '/admin_inline_edit.php')) {
        include_once __DIR__ . '/admin_inline_edit.php';
    }
}
?>

    <!-- Tailwind CSS (로컬 빌드) -->
    <link rel="stylesheet" href="<?php echo $css_base; ?>/assets/css/tailwind.css">

    <!-- 공통 CSS -->
    <link rel="stylesheet" href="<?php echo $css_base; ?>/assets/css/common.css">

    <?php if (!empty($page_css_file)): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($page_css_file); ?>">
    <?php endif; ?>

    <?php if (!empty($page_css)): ?>
    <style>
        <?php echo $page_css; ?>
    </style>
    <?php endif; ?>
</head>
<body>
    <!-- 글로벌 네비게이션 (앱 모드에서는 숨김) -->
    <?php if (empty($is_app_mode)) { include __DIR__ . '/global_nav.php'; } ?>

<?php if ($show_search && empty($is_app_mode)): ?>
    <!-- 피그마 디자인 검색바 + 지역 필터 (640px 컨테이너 내) -->
    <div class="header-search-wrap" style="max-width:640px;margin:0 auto;position:relative;z-index:601;">
        <div class="search-section" style="padding:8px 16px;">
            <div class="search-container" style="display:flex;gap:8px;align-items:center;">
                <input type="text" id="csb-keyword" class="search-input" placeholder="<?php echo function_exists('dt') ? htmlspecialchars(dt('fd_search_placeholder','시술, 병원 이름을 검색해보세요')) : '시술, 병원 이름을 검색해보세요'; ?>" style="flex:1;padding:18px 29px;border:1px solid var(--color-border, #1A0000);border-radius:0;font-size:16px;font-weight:400;background:var(--bs-body-bg, #fff);outline:none;color:var(--bs-body-color, #000);">
                <button id="regionToggleBtn" onclick="toggleRegionPanel()" style="flex-shrink:0;padding:13px 50px 12px 16px;border:none;border-radius:30px;background:var(--primary-color, #AE1932);color:#fff;font-size:18px;font-weight:700;cursor:pointer;white-space:nowrap;">
                    <i class="bi bi-geo-alt" style="color:#fff;margin-right:4px;"></i>지역 선택
                </button>
            </div>
        </div>
    </div>

    <!-- 오버레이 -->
    <div id="regionOverlay" onclick="closeRegionPanel()" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:599;background:rgba(0,0,0,0.35);"></div>

    <!-- 지역 선택 패널 (fixed overlay) -->
    <div id="regionPanel" style="display:none;position:fixed;left:0;right:0;z-index:600;padding:12px 16px;background:var(--bs-body-bg, #fff);border-bottom:1px solid var(--bs-border-color, #eee);box-shadow:0 4px 12px rgba(0,0,0,0.12);">
        <div class="region-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px 8px;margin-bottom:10px;">
            <?php
            $regions = [
                '강남','신사','압구정','청담','교대','삼성',
                '송파','천호','목동','강북','노원',
                '인천','성남','동탄','일산','의정부',
                '부산','대구','대전','청주','광주','전주',
                '춘천','제주'
            ];
            foreach ($regions as $r):
                if ($r === '') continue;
            ?>
            <label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer;white-space:nowrap;">
                <input type="checkbox" class="region-cb" value="<?php echo $r; ?>" style="accent-color:#AE1932;width:16px;height:16px;">
                <?php echo $r; ?>
            </label>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <button onclick="clearRegions()" style="flex-shrink:0;font-size:15px;cursor:pointer;background:none;border:none;padding-left:0;"><i class="bi bi-arrow-counterclockwise"></i> 초기화</button>
            <div id="selectedRegionTags" style="display:flex;flex-wrap:wrap;gap:4px;flex:1;padding:0 8px;"></div>
            <button onclick="applyRegionFilter()" style="flex-shrink:0;padding:8px;border:none;background:#AE1932;color:#fff;font-size:15px;font-weight:500;cursor:pointer;height:40px;width:190px;">필터 선택 완료</button>
        </div>
    </div>
    <style>
    @media (min-width:480px) { .region-grid { grid-template-columns:repeat(6,1fr) !important; } }
    </style>

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
        localStorage.setItem('sb_selected_regions', JSON.stringify(checked));
    }
    function loadRegionsFromStorage() {
        try {
            var saved = JSON.parse(localStorage.getItem('sb_selected_regions') || '[]');
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
        var container = document.getElementById('selectedRegionTags');
        container.innerHTML = '';
        document.querySelectorAll('.region-cb:checked').forEach(function(cb) {
            var tag = document.createElement('span');
            tag.style.cssText = 'display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border:1px solid #AE1932;color:#000;border-radius:3px;font-size:12px;font-weight:500;cursor:pointer;';
            tag.innerHTML = cb.value + ' <span onclick="uncheckRegion(\'' + cb.value + '\')" style="cursor:pointer;font-size:14px;">&times;</span>';
            container.appendChild(tag);
        });
        var checked = document.querySelectorAll('.region-cb:checked');
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
        var params = [];
        if (regions.length > 0) params.push('region=' + encodeURIComponent(regions.join(',')));
        if (keyword) params.push('q=' + encodeURIComponent(keyword));
        location.href = 'hospitals.php' + (params.length > 0 ? '?' + params.join('&') : '');
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
