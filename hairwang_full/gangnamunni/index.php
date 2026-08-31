<?php
/**
 * ========================================
 * 서울미녀 스타일 병원 리스팅 페이지
 * ========================================
 *
 * @file    gangnamunni/index.php
 * @created 2026-01-29
 * @version 3.0.0 (이벤트/응모 섹션 추가)
 */

// 초기화 (그누보드 세션 + DB 연결)
require_once __DIR__ . '/includes/init.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }

function home_safe_link($url, $fallback = '#') {
    $url = trim((string)$url);
    if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) return $fallback;
    if ($url[0] === '#' || $url[0] === '/') return strncmp($url, '//', 2) === 0 ? $fallback : $url;
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if ($scheme === '') return preg_match('/^[a-z0-9._~!$&\'()*+,;=:@%\\/?#-]+$/i', $url) ? $url : $fallback;
    return in_array($scheme, ['http', 'https'], true) ? $url : $fallback;
}

function home_safe_background($value) {
    $value = trim((string)$value);
    if (preg_match('/^#[0-9a-f]{3,8}$/i', $value)) return $value;
    if (preg_match('/^(?:linear|radial)-gradient\([a-z0-9#%.,()\s-]+\)$/i', $value)) return $value;
    return 'linear-gradient(135deg, #AE1932, #D4455A)';
}

// 서울미녀 병원 데이터 테이블 존재 확인
$table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_hospitals'");
$table_exists = mysqli_num_rows($table_check) > 0;

// 광고 배너 데이터 가져오기
$ads = [];
$ads_query = "SELECT a.* FROM gangnamunni_ads a
    WHERE a.status = 'approved'
      AND EXISTS (
          SELECT 1 FROM gangnamunni_ad_slot_bookings b
          WHERE b.advertiser_id = a.advertiser_id
            AND b.status = 'active'
            AND b.start_date <= CURDATE()
            AND (b.end_date IS NULL OR b.end_date >= CURDATE())
      )
    ORDER BY a.display_order ASC LIMIT 10";
$ads_result = mysqli_query($connect, $ads_query);
if ($ads_result) {
    while ($row = mysqli_fetch_assoc($ads_result)) {
        $ads[] = $row;
    }
}

// 프로모션 배너 데이터 가져오기
$promos = [];
$promos_query = "SELECT * FROM gangnamunni_promos WHERE is_active = 1 ORDER BY display_order ASC LIMIT 10";
$promos_result = mysqli_query($connect, $promos_query);
if ($promos_result) {
    while ($row = mysqli_fetch_assoc($promos_result)) {
        $promos[] = $row;
    }
}

// 이벤트 데이터 가져오기 (진행중인 이벤트)
$events = [];
$events_query = "SELECT e.*, h.name as hospital_name, h.address as hospital_address
    FROM gangnamunni_events e
    LEFT JOIN gangnamunni_hospitals h ON e.hospital_id = h.id
    WHERE e.is_active = 1
    AND (e.end_date IS NULL OR e.end_date >= CURDATE())
    ORDER BY e.display_order ASC, e.created_at DESC LIMIT 10";
$events_result = mysqli_query($connect, $events_query);
if ($events_result) {
    while ($row = mysqli_fetch_assoc($events_result)) {
        $events[] = $row;
    }
}

// 자동 스크롤 설정 가져오기
function getScrollSetting($connect, $key, $default) {
    $key = mysqli_real_escape_string($connect, $key);
    $result = mysqli_query($connect, "SELECT setting_value FROM gangnamunni_theme_settings WHERE setting_key = '{$key}'");
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['setting_value'];
    }
    return $default;
}

$ad_auto_scroll_enabled = getScrollSetting($connect, 'ad_auto_scroll_enabled', '1');
$ad_auto_scroll_interval = getScrollSetting($connect, 'ad_auto_scroll_interval', '10000');
$promo_auto_scroll_enabled = getScrollSetting($connect, 'promo_auto_scroll_enabled', '1');
$promo_auto_scroll_interval = getScrollSetting($connect, 'promo_auto_scroll_interval', '10000');

// 홈 광고 위치 설정 (관리자 대시보드에서 토글) — gangnamunni_settings 테이블
$ad_home_after_search = '0';
$ad_home_after_events = '0';
$ad_show_index = '0';
$promo_show_index = '0';
$tc_table_mode = 'excel'; // 기본: 엑셀형
$_adpos_q = mysqli_query($connect, "SELECT setting_key, setting_value FROM gangnamunni_settings WHERE setting_key IN ('ad_home_after_search','ad_home_after_events','ad_show_index','promo_show_index','tc_table_mode')");
if ($_adpos_q) {
    while ($_adpos_r = mysqli_fetch_assoc($_adpos_q)) {
        if ($_adpos_r['setting_key'] === 'ad_home_after_search') $ad_home_after_search = $_adpos_r['setting_value'];
        if ($_adpos_r['setting_key'] === 'ad_home_after_events') $ad_home_after_events = $_adpos_r['setting_value'];
        if ($_adpos_r['setting_key'] === 'ad_show_index') $ad_show_index = $_adpos_r['setting_value'];
        if ($_adpos_r['setting_key'] === 'promo_show_index') $promo_show_index = $_adpos_r['setting_value'];
        if ($_adpos_r['setting_key'] === 'tc_table_mode') $tc_table_mode = $_adpos_r['setting_value'];
    }
}

// 레이아웃 설정
$page_title = term('business') . ' 찾기';
$show_search = true;
if (file_exists(__DIR__ . '/includes/design_tokens.php')) { include_once __DIR__ . '/includes/design_tokens.php'; }
$page_css_file = 'assets/css/index-page.css';

// 이벤트/응모 섹션 추가 CSS
$page_css = '
@import url("https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap");

/* 홈 화면의 모든 일반 텍스트에 Noto Sans KR 적용 (아이콘 폰트 제외) */
body,
button,
input,
select,
textarea {
    font-family: "Noto Sans KR", sans-serif !important;
}

/* 이벤트 섹션 */
.events-section {
    margin-bottom: 45px;
}
.events-section .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.events-section .section-title {
    font-size: 20px;
    font-weight: 700;
    color: #000000;
}
.events-section .section-more {
    font-size: 26px;
    color: #000;
    text-decoration: none;
    font-weight: 500;
}
.events-scroll {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 8px;
    scrollbar-width: none;
}
.events-scroll::-webkit-scrollbar { display: none; }
.event-card-mini {
    flex: 0 0 200px;
    scroll-snap-align: start;
    border-radius: 0px;
    overflow: hidden;
    background: var(--bs-body-bg, #fff);
    cursor: pointer;
    text-decoration: none;
    color: inherit;
}
.event-card-mini:hover {
}
.event-card-mini .card-img {
    width: 200px;
    height: 140px;
    background: linear-gradient(135deg, #AE1932, #D4455A);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 40px;
    position: relative;
    overflow: hidden;
}
.event-card-mini .card-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.event-card-mini .card-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    background: #AE1932;
    color: white;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 700;
}
.event-card-mini .raffle-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #AE1932;
    color: white;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
}
.event-card-mini .card-body {
    padding: 12px;
}
.event-card-mini .card-cat {
    font-size: 13px;
    color: #000000;
    font-weight: 700;
    margin-bottom: 4px;
}
.event-card-mini .card-title {
    font-size: 14px;
    font-weight: 400;
    color: #1a1a1a;
    margin-bottom: 4px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.event-card-mini .card-hospital {
    font-size: 12px;
    font-weight: 400;
    color: #888888;
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.event-card-mini .card-price {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.event-card-mini .card-price .original {
    font-size: 12px;
    font-weight: 400;
    color: #A9A9A9;
    text-decoration: line-through;
}
.event-card-mini .card-price .discount {
    font-size: 14px;
    font-weight: 700;
    color: #AE1932;
}
.event-card-mini .card-date {
    font-size: 11px;
    color: #999;
    margin-top: 4px;
}

@media (max-width: 480px) {
    .event-card-mini {
        flex: 0 0 170px;
    }
    .event-card-mini .card-img {
        width: 170px;
        height: 120px;
    }
}

/* 캐러셀 (광고/프로모션 배너) */
.carousel-section { position: relative; margin-bottom: 16px; }
.carousel-container { position: relative; overflow: hidden; border-radius: 0px; touch-action: pan-y; -webkit-user-select: none; user-select: none; }
.carousel-track { display: flex; transition: transform 0.4s ease; }
.carousel-slide { min-width: 100%; flex-shrink: 0; }
.carousel-arrow {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(0,0,0,0.3); color: #fff; border: none;
    width: 32px; height: 32px; border-radius: 50%;
    font-size: 20px; cursor: pointer; z-index: 5;
    display: flex; align-items: center; justify-content: center;
}
.carousel-prev { left: 8px; }
.carousel-next { right: 8px; }
.carousel-indicator {
    position: absolute; bottom: 8px; right: 12px;
    background: rgba(0,0,0,0.5); color: #fff;
    padding: 2px 10px; border-radius: 10px; font-size: 12px;
}
.promo-banner {
    display: flex; flex-direction: column; justify-content: center;
    padding: 24px 28px; border-radius: 0px; min-height: 120px;
}
.promo-title { font-size: 18px; font-weight: bold; margin-bottom: 6px; }
.promo-subtitle { font-size: 13px; opacity: 0.9; }
';

// 페이지 전용 JS
$page_js = '
function showImportGuide() {
    alert("데이터베이스 Import 가이드:\n\n1. MySQL 접속:\n   mysql -u root -p -h localhost\n\n2. hairwang 데이터베이스 선택:\n   USE hairwang;\n\n3. SQL 파일 import:\n   SOURCE H:/carebaby_project/site_get/gangnamunni_hospitals_import.sql;\n\n4. 완료 후 이 페이지 새로고침 (F5)\n\n문제 발생 시: test_db_connection.php에서 DB 연결 확인");
}

// 검색 기능
var searchInput = document.querySelector(".search-input");
if (searchInput) {
    searchInput.addEventListener("keyup", function(e) {
        if (e.key === "Enter") {
            var keyword = this.value.trim();
            if (keyword) {
                location.href = "hospitals.php?q=" + encodeURIComponent(keyword);
            }
        }
    });
}

// 네비게이션 아이템 클릭
document.querySelectorAll(".nav-item").forEach(function(item) {
    item.addEventListener("click", function() {
        var page = this.getAttribute("data-page");
        if (page && page !== "#") {
            location.href = page;
        }
    });
});

// 캐러셀 이동
function carouselMove(carouselId, dir) {
    var el = document.getElementById(carouselId);
    if (!el) return;
    var track = el.querySelector(".carousel-track");
    var slides = track.querySelectorAll(".carousel-slide");
    var total = slides.length;
    if (total === 0) return;

    var idx = parseInt(track.getAttribute("data-index") || "0");
    idx += dir;
    if (idx < 0) idx = total - 1;
    if (idx >= total) idx = 0;
    track.setAttribute("data-index", idx);

    track.style.transform = "translateX(-" + (idx * 100) + "%)";
    el.querySelector(".carousel-current").textContent = (idx + 1);
}

// 캐러셀 자동 스크롤
function initCarouselAuto(carouselId, enabled, interval) {
    if (!enabled || enabled === "0") return;
    var el = document.getElementById(carouselId);
    if (!el) return;
    var ms = parseInt(interval) || 10000;
    var timer = setInterval(function() { carouselMove(carouselId, 1); }, ms);
    el.addEventListener("mouseenter", function() { clearInterval(timer); });
    el.addEventListener("mouseleave", function() { timer = setInterval(function() { carouselMove(carouselId, 1); }, ms); });
}

// 터치 스와이프 지원 (#5) - WebView 대응 개선
function addSwipeSupport(carouselId) {
    var el = document.getElementById(carouselId);
    if (!el) return;
    var startX = 0, startY = 0, moved = false;
    el.addEventListener("touchstart", function(e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        moved = false;
    }, { passive: true });
    el.addEventListener("touchmove", function(e) {
        if (!moved) {
            var dx = Math.abs(e.touches[0].clientX - startX);
            var dy = Math.abs(e.touches[0].clientY - startY);
            if (dx > dy && dx > 10) {
                moved = true;
            }
        }
        if (moved) { e.preventDefault(); }
    }, { passive: false });
    el.addEventListener("touchend", function(e) {
        if (!moved) return;
        var endX = e.changedTouches[0].clientX;
        var diff = startX - endX;
        if (Math.abs(diff) > 40) {
            carouselMove(carouselId, diff > 0 ? 1 : -1);
        }
    }, { passive: true });
}

// 캐러셀 초기화 (첫 슬라이드 보장)
function initCarouselIndex(carouselId) {
    var el = document.getElementById(carouselId);
    if (!el) return;
    var track = el.querySelector(".carousel-track");
    if (track) {
        track.setAttribute("data-index", "0");
        track.style.transform = "translateX(0%)";
    }
    var ind = el.querySelector(".carousel-current");
    if (ind) ind.textContent = "1";
}

// 페이지 로드 후 초기화
document.addEventListener("DOMContentLoaded", function() {
    initCarouselIndex("ads-carousel");
    initCarouselIndex("promo-carousel");
    initCarouselAuto("ads-carousel", "' . $ad_auto_scroll_enabled . '", "' . $ad_auto_scroll_interval . '");
    initCarouselAuto("promo-carousel", "' . $promo_auto_scroll_enabled . '", "' . $promo_auto_scroll_interval . '");
    addSwipeSupport("ads-carousel");
    addSwipeSupport("promo-carousel");
});
';

// 공통 헤더 include

// [Figma Sync - 2026-06-06] index 검색바 Figma #01 매칭 — CSS 오버라이드 추가
// 원인 : 검색 input/지역선택 버튼이 Figma 디자인과 다름
// 변경 : $page_css에 figma_sync_index.css 내용 append (PHP/HTML 무변경)
// 원복 : 본 블록 3줄 삭제 또는 figma_sync_index.css 파일 삭제
$_fs_css_file = __DIR__ . '/assets/css/figma_sync_index.css';
if (file_exists($_fs_css_file)) { $page_css .= "
" . file_get_contents($_fs_css_file); }

$page_css .= '
/* 홈 회사 정보 푸터 */
.home-company-footer {
    margin: 40px -16px 0;
    padding: 30px 20px 34px;
    background: #f5f5f5;
    color: #8f8f8f;
    font-size: 13px;
    line-height: 1.75;
}
.home-company-footer__logo {
    margin-bottom: 14px;
    max-width: 90px;
}
.home-company-footer__logo img {
    display: block;
    width: 100%;
    max-width: 100%;
    height: auto;
}
.home-company-footer__company p,
.home-company-footer__notice p { margin: 0; }
.home-company-footer__links {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
    margin: 8px 0 18px;
}
.home-company-footer__links a {
    color: #666;
    text-decoration: none;
}
.home-company-footer__links a:hover,
.home-company-footer__links a:focus { text-decoration: underline; }
.home-company-footer__links a.home-company-footer__link-strong {
    color: #555;
    font-weight: 600;
    text-decoration: underline;
    text-underline-offset: 2px;
}
.home-company-footer__notice {
    margin-top: 4px;
    color: #adadad;
}
.home-company-footer__copyright {
    margin: 6px 0 0;
    color: #aaa;
    font-weight: 600;
}
@media (max-width: 640px) {
    .home-company-footer { margin-right: -12px; margin-left: -12px; }
}
';

include __DIR__ . '/includes/header.php';
?>
<?php if (function_exists('figma_review_mode') && figma_review_mode()): ?>
<style>
/* Figma 비교 iframe 모드: 비교에 방해되는 chrome 숨김 (?in_figma_review=1) */
.global-nav, [class^="global-nav"], header.global-nav, #globalNav { display:none !important; }
.bottom-nav { display:none !important; }
body { padding-top:0 !important; }
.ae-topbar { display:none !important; }
body.ae-active { padding-top:0 !important; }
</style>
<?php endif; ?>
        <?php if (!$table_exists): ?>
        <!-- 데이터 없음 상태 -->
        <div class="status-box">
            <h2>⚠️ 데이터베이스 설정 필요</h2>
            <p>서울미녀 병원 데이터를 불러오려면 먼저 SQL 파일을 import 해야 합니다.</p>
            <p style="margin-top: 10px;">
                <strong>파일 위치:</strong><br>
                H:\carebaby_project\site_get\gangnamunni_hospitals_import.sql
            </p>
            <button onclick="showImportGuide()">Import 가이드 보기</button>
        </div>

        <!-- 데모 데이터 -->
        <div class="demo-data">
            <h2>✨ 서울미녀 스타일 페이지</h2>
            <p>
                서울미녀 웹사이트의 디자인을 참고하여 만든 병원 리스팅 페이지입니다.<br>
                실제 크롤링된 데이터(99개 병원)를 표시하려면 SQL import가 필요합니다.
            </p>
        </div>

        <?php else: ?>

        <!-- 광고: 지역선택 아래 (ad_home_after_search 설정) — 피그마 스타일: 흰 카드 -->
        <?php if ($ad_home_after_search === '1' && !empty($ads)): ?>
        <div class="carousel-section ae-wrap" id="ads-carousel-top" style="margin-bottom:8px;">
            <?php if (function_exists('ae')) ae('ad_settings', '광고 설정'); ?>
            <div class="carousel-container">
                <button class="carousel-arrow carousel-prev" onclick="event.stopPropagation(); event.preventDefault(); carouselMove('ads-carousel-top', -1)">‹</button>
                <button class="carousel-arrow carousel-next" onclick="event.stopPropagation(); event.preventDefault(); carouselMove('ads-carousel-top', 1)">›</button>
                <div class="carousel-track">
                    <?php foreach ($ads as $ad): ?>
                    <a href="<?php echo htmlspecialchars(home_safe_link($ad['link_url']), ENT_QUOTES); ?>" class="carousel-slide ad-item" data-ad-id="<?php echo (int)$ad['id']; ?>" style="text-decoration:none;">
                        <?php if (!empty($ad['image_url']) && file_exists(__DIR__ . '/' . $ad['image_url'])): ?>
                        <div style="border-radius:0px;overflow:hidden;border:1px solid var(--bs-border-color, #eee);">
                            <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" style="width:100%;height:auto;object-fit:cover;">
                        </div>
                        <?php else: ?>
                        <div style="background:var(--bs-tertiary-bg, #f8f8f8);border-radius:0px;padding:20px 16px;color:var(--bs-body-color, #333);min-height:100px;position:relative;border:1px solid var(--bs-border-color, #eee);">
                            <span style="position:absolute;top:10px;right:12px;background:#e0e0e0;color:var(--bs-secondary-color, #666);font-size:12px;font-weight:400;color:#000000;padding:2px 6px;border-radius:4px;">AD</span>
                            <div style="font-size:15px;font-weight:700;margin-bottom:4px;color:#222;"><?php echo htmlspecialchars($ad['title']); ?></div>
                            <?php if (!empty($ad['subtitle'])): ?><div style="font-size:12px;color:var(--bs-secondary-color, #666);line-height:1.4;"><?php echo htmlspecialchars($ad['subtitle']); ?></div><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-indicator"><span class="carousel-current">1</span> / <?php echo count($ads); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 피그마 디자인: 시술 카테고리 테이블 (6대분류 x 중분류) -->
        <div class="ae-wrap" style="position:relative;">
            <?php if (function_exists('ae')) ae('treatments', '시술 관리'); ?>
        <?php
        // DB에서 대분류 + 중분류 동적 조회
        $treatment_categories = [];
        $tc_sql = "SELECT c.id, c.name AS cat_name, GROUP_CONCAT(m.name ORDER BY m.sort_order ASC, m.popularity_score DESC, m.id ASC SEPARATOR '||') AS treatments
            FROM gangnamunni_treatment_categories c
            LEFT JOIN gangnamunni_treatment_master m ON m.category_id = c.id AND m.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.sort_order
            ORDER BY c.sort_order ASC, c.id ASC";
        $tc_r = mysqli_query($connect, $tc_sql);
        if ($tc_r) {
            while ($tc = mysqli_fetch_assoc($tc_r)) {
                $subs = $tc['treatments'] ? explode('||', $tc['treatments']) : [];
                $treatment_categories[$tc['cat_name']] = $subs;
            }
        }
        ?>
        <div class="tc-table-wrap" style="overflow:hidden; width:100%; margin:0 auto; margin-bottom:15px;">
        <?php if ($tc_table_mode === 'row'): ?>
        <!-- 모드B: 행별 독립 스크롤 -->
        <div class="tc-table tc-mode-row" style="background:var(--bs-body-bg, #fff);border-radius:0;overflow:hidden;border:1px solid #D5D5D5;">
            <?php foreach ($treatment_categories as $major => $subs): ?>
            <div class="tc-row" style="display:flex;border-bottom:1px solid #D5D5D5;">
                <div class="tc-major"><?php echo htmlspecialchars($major); ?></div>
                <div class="tc-subs-wrap">
                    <button class="tc-arrow tc-arrow-l" onclick="tcRowScroll(this,-1)" aria-label="왼쪽">‹</button>
                    <div class="tc-subs">
                        <?php foreach ($subs as $sub): ?>
                        <a href="hospitals.php?treatment=<?php echo urlencode($sub); ?>" class="tc-chip"><?php echo htmlspecialchars($sub); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <button class="tc-arrow tc-arrow-r" onclick="tcRowScroll(this,1)" aria-label="오른쪽">›</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php elseif ($tc_table_mode === 'hybrid'): ?>
        <!-- 모드C: 하이브리드 — 대분류 고정 + 각 행 독립 스크롤 -->
        <div class="tc-table tc-mode-hybrid" style="background:var(--bs-body-bg, #fff);border-radius:0;overflow:hidden;border:1px solid #D5D5D5;">
            <?php foreach ($treatment_categories as $major => $subs): ?>
            <div class="tc-row" style="display:flex;border-bottom:1px solid #D5D5D5;">
                <div class="tc-major tc-major-sticky"><?php echo htmlspecialchars($major); ?></div>
                <div class="tc-subs-wrap">
                    <button class="tc-arrow tc-arrow-l" onclick="tcRowScroll(this,-1)" aria-label="왼쪽">‹</button>
                    <div class="tc-subs">
                        <?php foreach ($subs as $sub): ?>
                        <a href="hospitals.php?treatment=<?php echo urlencode($sub); ?>" class="tc-chip"><?php echo htmlspecialchars($sub); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <button class="tc-arrow tc-arrow-r" onclick="tcRowScroll(this,1)" aria-label="오른쪽">›</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- 패널형: 좌측 1/3 대분류 + 우측 2/3 시술 2열 -->
        <div class="tc-panel-wrap">
            <div class="tc-nav-only" id="tcNavOnly">
                <?php $pi = 0; foreach ($treatment_categories as $major => $subs): ?>
                <div class="tc-nav-item<?php echo $pi === 0 ? ' active' : ''; ?>"
                     onclick="tcNavSelect(this, <?php echo $pi; ?>)"
                ><?php echo htmlspecialchars($major); ?></div>
                <?php $pi++; endforeach; ?>
            </div>
            <div class="tc-panel-right" id="tcPanelRight">
                <?php $pi = 0; foreach ($treatment_categories as $major => $subs): ?>
                <div class="tc-panel-body<?php echo $pi === 0 ? ' tc-panel-body-active' : ''; ?>" id="tcBody<?php echo $pi; ?>">
                    <?php foreach ($subs as $sub): ?>
                    <a href="hospitals.php?treatment=<?php echo urlencode($sub); ?>" class="tc-chip"><?php echo htmlspecialchars($sub); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php $pi++; endforeach; ?>
                <div class="tc-below-divider" id="tcBelowDivider"></div>
                <div class="tc-below-wrap" id="tcBelowWrap">
                    <?php $pi = 0; foreach ($treatment_categories as $major => $subs): ?>
                    <div class="tc-below-group" id="tcBelow<?php echo $pi; ?>" style="display:none">
                        <?php foreach ($subs as $sub): ?>
                        <span class="tc-chip tc-chip-faded"><?php echo htmlspecialchars($sub); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php $pi++; endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        </div>

        <style>
        /* ===== 시술 카테고리 테이블 공통 ===== */
        /* Jadval balandligini kategoriyalar soni belgilaydi. */
        .tc-table-wrap { height: auto; }
        .tc-chip {
            display: inline-block; padding: calc(6px * var(--cat-scale,1) * var(--cat-pad,1)) calc(14px * var(--cat-scale,1) * var(--cat-chip,1)); background: var(--bs-body-bg, #fff);
            border-radius: 13px; color: #111; text-decoration: none;
            font-size: calc(15px * var(--cat-scale,1) * var(--cat-font,1)); font-weight: 600; white-space: nowrap; transition: all 0.2s;
            border: 1px solid transparent;
        }
        .tc-chip:hover { background: #FFE0E0; color: #AE1932; border-color: #AE1932; }

        .tc-table .tc-row:last-child { border-bottom: none; }

        /* 대분류 공통 */
        .tc-major {
            padding: 14px 14px; font-weight: 700; font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1));
            background: var(--bs-tertiary-bg, #F1F1F1); color: var(--bs-body-color, #000);
            white-space: nowrap; min-width: 110px; flex-shrink: 0;
            border-right: 1px solid #D5D5D5;
            display: flex; align-items: center;
        }

        /* 행별 스크롤 영역 공통 (모드B, C) */
        .tc-subs-wrap {
            flex: 1; position: relative; overflow: hidden;
            display: flex; align-items: center;
        }
        .tc-subs {
            display: flex; gap: 6px; padding: 10px 8px;
            overflow-x: auto; -webkit-overflow-scrolling: touch;
            scrollbar-width: none; flex: 1;
            mask-image: linear-gradient(to right, #000 85%, transparent 100%);
            -webkit-mask-image: linear-gradient(to right, #000 85%, transparent 100%);
        }
        .tc-subs::-webkit-scrollbar { display: none; }
        .tc-subs.tc-scrolled-end { mask-image: none; -webkit-mask-image: none; }

        .tc-arrow {
            display: none; position: absolute; top: 50%; transform: translateY(-50%);
            width: 28px; height: 28px; border-radius: 50%;
            background: rgba(0,0,0,0.5); color: #fff; border: none;
            font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1)); cursor: pointer; z-index: 3;
            align-items: center; justify-content: center; line-height: 1;
        }
        .tc-arrow-l { left: 2px; }
        .tc-arrow-r { right: 2px; }
        @media (min-width: 768px) {
            .tc-subs-wrap:hover .tc-arrow { display: flex; }
        }

        /* ===== 모드C: 하이브리드 — sticky 대분류 + 행별 스크롤 ===== */
        .tc-major-sticky {
            position: sticky; left: 0; z-index: 2;
        }
        .tc-major-sticky::after {
            content: ''; position: absolute; top: 0; right: -6px;
            width: 6px; height: 100%;
            background: linear-gradient(to right, rgba(0,0,0,0.06), transparent);
            pointer-events: none;
        }

        /* ===== panel wrap ===== */
        .tc-panel-wrap {
            position: relative;
            display: block;
            height: auto;
            min-height: 0;
            overflow: hidden;
        }
        .tc-panel-right {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 66.667%;
            height: auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding: 10px;
            border: none;
            scrollbar-width: none;
        }
        .tc-panel-right::-webkit-scrollbar { display: none; }
        .tc-panel-body {
            display: none;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            width: 100%;
        }
        .tc-panel-body.tc-panel-body-active {
            display: grid;
            margin-top: var(--tc-panel-offset, 0px);
        }
        .tc-panel-body .tc-chip {
            display: block;
            text-align: center;
            border-radius: 8px;
            padding: 10px 6px;
            font-size: calc(13px * var(--cat-scale,1) * var(--cat-font,1));
            white-space: normal;
            word-break: keep-all;
        }
        .tc-below-divider {
            width: 100%;
            border-top: 1px solid #E8E8E8;
            margin: 12px 0 10px;
            display: none;
        }
        .tc-below-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            width: 100%;
        }
        .tc-below-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            width: 100%;
        }
        .tc-chip-faded {
            display: block;
            text-align: center;
            border-radius: 8px;
            padding: 10px 6px;
            font-size: calc(13px * var(--cat-scale,1) * var(--cat-font,1));
            font-weight: 600;
            white-space: normal;
            word-break: keep-all;
            color: #ccc;
            background: var(--bs-body-bg, #fff);
            pointer-events: none;
            cursor: default;
            text-decoration: none;
        }
        /* ===== kulrang ustun (nav-only) ===== */
        .tc-nav-only {
            display: flex;
            flex-direction: column;
            width: 33.333%;
            height: auto;
            overflow: visible;
            -webkit-overflow-scrolling: auto;
            border: none;
            background: var(--bs-tertiary-bg, #F1F1F1);
        }
        .tc-nav-item {
            padding: 14px 8px;
            text-align: center;
            font-size: calc(13px * var(--cat-scale,1) * var(--cat-font,1));
            font-weight: 600;
            line-height: 1.3;
            cursor: pointer;
            border-bottom: none;
            color: #666;
            transition: background 0.15s, color 0.15s;
        }
        .tc-nav-item:last-child { border-bottom: none; }
        .tc-nav-item.active {
            background: var(--bs-body-bg, #fff);
            color: #AE1932;
            font-weight: 700;
            border-left: none;
        }
        </style>

        <script>
        (function(){
            'use strict';
            // ===== 모드B/C: 행별 스크롤 =====
            window.tcRowScroll = function(btn, dir) {
                var wrap = btn.parentElement;
                var subs = wrap.querySelector('.tc-subs');
                if (subs) subs.scrollBy({ left: dir * 150, behavior: 'smooth' });
            };
            document.querySelectorAll('.tc-subs').forEach(function(el) {
                el.addEventListener('scroll', function() {
                    if (el.scrollLeft + el.clientWidth >= el.scrollWidth - 5) {
                        el.classList.add('tc-scrolled-end');
                    } else {
                        el.classList.remove('tc-scrolled-end');
                    }
                });
            });

            window.tcNavSelect = function(el, idx) {
                document.querySelectorAll('.tc-nav-item').forEach(function(x) { x.classList.remove('active'); });
                el.classList.add('active');
                document.querySelectorAll('.tc-panel-body').forEach(function(x) {
                    x.style.display = 'none';
                    x.classList.remove('tc-panel-body-active');
                });
                var body = document.getElementById('tcBody' + idx);
                if (body) {
                    body.style.display = 'grid';
                    body.classList.add('tc-panel-body-active');
                }
                document.querySelectorAll('.tc-below-group').forEach(function(x) { x.style.display = 'none'; });
                var divider = document.getElementById('tcBelowDivider');
                if (divider) divider.style.display = 'none';
                var right = document.getElementById('tcPanelRight');
                if (right && body) {
                    // O'ng submenu tanlangan chap kategoriya bilan bir qatordan boshlanadi.
                    right.scrollTop = 0;
                    var rightTopPadding = parseFloat(window.getComputedStyle(right).paddingTop) || 0;
                    right.style.setProperty('--tc-panel-offset', Math.max(0, el.offsetTop - rightTopPadding) + 'px');
                }
            };
            var _initNav = document.querySelector('.tc-nav-item.active');
            if (_initNav) tcNavSelect(_initNav, 0);
        })();
        </script>
        </div><!-- /ae-wrap 시술 카테고리 -->

        <!-- 광고 배너 캐러셀 (메인 배너 ON + 검색아래 OFF일 때 여기 표시) -->
        <?php if (!empty($ads) && $ad_show_index === '1' && $ad_home_after_search !== '1'): ?>
        <div class="carousel-section ae-wrap" id="ads-carousel" style="background:var(--bs-body-bg, #fff);">
            <?php if (function_exists('ae')) ae('ad_settings', '광고 설정'); ?>
            <div class="carousel-container">
                <button class="carousel-arrow carousel-prev" onclick="event.stopPropagation(); event.preventDefault(); carouselMove('ads-carousel', -1)">‹</button>
                <button class="carousel-arrow carousel-next" onclick="event.stopPropagation(); event.preventDefault(); carouselMove('ads-carousel', 1)">›</button>
                <div class="carousel-track">
                    <?php foreach ($ads as $ad): ?>
                    <a href="<?php echo htmlspecialchars(home_safe_link($ad['link_url']), ENT_QUOTES); ?>" class="carousel-slide ad-item" data-ad-id="<?php echo (int)$ad['id']; ?>" style="text-decoration:none;">
                        <?php if (!empty($ad['image_url']) && file_exists(__DIR__ . '/' . $ad['image_url'])): ?>
                        <div style="border-radius:0px;overflow:hidden;border:1px solid var(--bs-border-color, #eee);">
                            <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" style="width:100%;height:auto;object-fit:cover;">
                        </div>
                        <?php else: ?>
                        <div style="background:var(--bs-tertiary-bg, #f8f8f8);border-radius:0px;padding:20px 16px;color:var(--bs-body-color, #333);min-height:100px;position:relative;border:1px solid var(--bs-border-color, #eee);">
                            <span style="position:absolute;top:10px;right:12px;background:#e0e0e0;color:var(--bs-secondary-color, #666);font-size:12px;font-weight:400;color:#000000;padding:2px 6px;border-radius:4px;">AD</span>
                            <div style="font-size:15px;font-weight:700;margin-bottom:4px;color:#222;"><?php echo htmlspecialchars($ad['title']); ?></div>
                            <?php if (!empty($ad['subtitle'])): ?><div style="font-size:12px;color:var(--bs-secondary-color, #666);line-height:1.4;"><?php echo htmlspecialchars($ad['subtitle']); ?></div><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-indicator"><span class="carousel-current">1</span> / <?php echo count($ads); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 프로모션 배너 캐러셀 (promo_show_index ON일 때만) -->
        <?php if (!empty($promos) && $promo_show_index === '1' && figma_block_visible('home_promos')): ?>
        <div class="carousel-section ae-wrap" id="promo-carousel">
            <?php if (function_exists('ae')) ae('promos', '프로모션 관리'); ?>
            <div class="carousel-container">
                <button class="carousel-arrow carousel-prev" onclick="event.stopPropagation(); event.preventDefault(); carouselMove('promo-carousel', -1)">‹</button>
                <button class="carousel-arrow carousel-next" onclick="event.stopPropagation(); event.preventDefault(); carouselMove('promo-carousel', 1)">›</button>
                <div class="carousel-track">
                    <?php foreach ($promos as $promo): ?>
                    <a href="<?php echo htmlspecialchars(home_safe_link($promo['link_url']), ENT_QUOTES); ?>"
                       class="carousel-slide promo-banner"
                       style="background: <?php echo htmlspecialchars(home_safe_background($promo['background_gradient']), ENT_QUOTES); ?>; text-decoration: none; color: white;"
                       data-icon="<?php echo htmlspecialchars($promo['icon']); ?>">
                        <div class="promo-title"><?php echo htmlspecialchars($promo['title'], ENT_QUOTES); ?></div>
                        <div class="promo-subtitle"><?php echo htmlspecialchars($promo['subtitle']); ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-indicator"><span class="carousel-current">1</span> / <?php echo count($promos); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 특별 이벤트 섹션 -->
        <?php if (!empty($events) && figma_block_visible('home_events')): ?>
        <div class="events-section ae-wrap">
            <?php if (function_exists('ae')) ae('events', '이벤트 관리'); ?>
            <div class="section-header">
                <div class="section-title">이벤트</div>
                <a href="events.php" class="section-more">›</a>
            </div>
            <div class="events-scroll">
                <?php foreach ($events as $event): ?>
                <a href="event_detail.php?id=<?php echo $event['id']; ?>" class="event-card-mini">
                    <div class="card-img">
                        <?php if (!empty($event['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($event['image_url']); ?>"
                             alt="<?php echo htmlspecialchars($event['title']); ?>"
                             onerror="this.style.display='none'; this.parentNode.textContent='🎁';">
                        <?php else: ?>
                        🎁
                        <?php endif; ?>
                        <?php if (!empty($event['discount_rate']) && $event['discount_rate'] > 0): ?>
                        <div class="card-badge"><?php echo intval($event['discount_rate']); ?>%</div>
                        <?php endif; ?>
                        <?php if (!empty($event['has_raffle']) && $event['has_raffle']): ?>
                        <div class="raffle-badge">응모</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($event['category'])): ?>
                        <div class="card-cat"><?php echo htmlspecialchars($event['category']); ?></div>
                        <?php endif; ?>
                        <div class="card-title"><?php echo htmlspecialchars($event['title']); ?></div>
                        <?php if (!empty($event['hospital_address'])): ?>
                        <div class="card-hospital"><?php echo htmlspecialchars($event['hospital_address']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($event['discount_price'])): ?>
                        <div class="card-price">
                            <?php if (!empty($event['original_price'])): ?>
                            <span class="original"><?php echo number_format($event['original_price']); ?>원</span>
                            <?php endif; ?>
                            <span class="discount"><?php echo number_format($event['discount_price']); ?>원</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 광고: 이벤트 아래 (ad_home_after_events 설정) -->
        <?php if ($ad_home_after_events === '1' && !empty($ads)): ?>
        <div class="carousel-section" id="ads-carousel-bottom" style="margin:16px 0;">
            <div class="carousel-container">
                <button class="carousel-arrow carousel-prev" onclick="event.stopPropagation(); event.preventDefault(); carouselMove('ads-carousel-bottom', -1)">‹</button>
                <button class="carousel-arrow carousel-next" onclick="event.stopPropagation(); event.preventDefault(); carouselMove('ads-carousel-bottom', 1)">›</button>
                <div class="carousel-track">
                    <?php foreach ($ads as $ad): ?>
                    <a href="<?php echo htmlspecialchars(home_safe_link($ad['link_url']), ENT_QUOTES); ?>" class="carousel-slide ad-item" data-ad-id="<?php echo (int)$ad['id']; ?>" style="text-decoration:none;">
                        <?php if (!empty($ad['image_url']) && file_exists(__DIR__ . '/' . $ad['image_url'])): ?>
                        <div style="border-radius:0px;overflow:hidden;border:1px solid var(--bs-border-color, #eee);">
                            <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" style="width:100%;height:auto;object-fit:cover;">
                        </div>
                        <?php else: ?>
                        <div style="background:var(--bs-tertiary-bg, #f8f8f8);border-radius:0px;padding:20px 16px;color:var(--bs-body-color, #333);min-height:100px;position:relative;border:1px solid var(--bs-border-color, #eee);">
                            <span style="position:absolute;top:10px;right:12px;background:#e0e0e0;color:var(--bs-secondary-color, #666);font-size:12px;font-weight:400;color:#000000;padding:2px 6px;border-radius:4px;">AD</span>
                            <div style="font-size:15px;font-weight:700;margin-bottom:4px;color:#222;"><?php echo htmlspecialchars($ad['title']); ?></div>
                            <?php if (!empty($ad['subtitle'])): ?><div style="font-size:12px;color:var(--bs-secondary-color, #666);line-height:1.4;"><?php echo htmlspecialchars($ad['subtitle']); ?></div><?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-indicator"><span class="carousel-current">1</span> / <?php echo count($ads); ?></div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>


<footer class="home-company-footer" aria-label="회사 정보">
    <div class="home-company-footer__logo">
        <img src="assets/images/logo_of_footer.png" alt="서울미녀">
    </div>
    <div class="home-company-footer__company">
        <p>셀럽몰 주식회사 | 대표자: 최서진</p>
        <p>서울특별시 강남구 선릉로 612, 4층(삼성동, 한일빌딩)</p>
        <p>사업자등록번호: 308-81-44202</p>
        <p>통신판매업신고: 2018-서울강남-01913호</p>
        <p>고객센터 E-mail: <a href="mailto:info@seoulbeauty.app" style="color:inherit;text-decoration:none;">info@seoulbeauty.app</a></p>
    </div>
    <nav class="home-company-footer__links" aria-label="회사 정책 및 고객센터">
        <a class="home-company-footer__link-strong" href="qna.php">고객센터 문의하기</a>
        <a href="terms_of_service.php">이용약관</a>
        <a href="terms_of_service.php">위치기반서비스 이용약관</a>
        <a class="home-company-footer__link-strong" href="privacy_policy.php">개인정보처리방침</a>
    </nav>
    <div class="home-company-footer__notice">
        <p>셀럽몰 주식회사는 통신판매의 당사자가 아니므로,</p>
        <p>의료기관이 등록한 시/수술 정보 및 거래 등에 대해 책임을 지지 않습니다.</p>
    </div>
    <p class="home-company-footer__copyright">©CELEBMALL Co., Ltd. All rights reserved.</p>
</footer>


<?php
// 회원가입 축하 포인트 안내(#포인트제도): 가입 직후 1회만 노출
if (!empty($_SESSION['reg_welcome_point'])):
    $__wp = (int)$_SESSION['reg_welcome_point'];
    unset($_SESSION['reg_welcome_point']);
?>
<div id="welcomePointToast" style="position:fixed;left:50%;bottom:90px;transform:translateX(-50%);z-index:9999;
     background:linear-gradient(135deg,#AE1932,#d4354a);color:#fff;padding:14px 22px;border-radius:14px;
     box-shadow:0 8px 24px rgba(0,0,0,.25);font-weight:700;font-size:15px;white-space:nowrap;
     display:flex;align-items:center;gap:8px;opacity:0;transition:opacity .3s;">
    <i class="bi bi-coin" style="font-size:20px;"></i>
    회원가입 축하 <?php echo number_format($__wp); ?>P가 적립되었어요!
</div>
<script>
(function(){
    var t=document.getElementById('welcomePointToast'); if(!t) return;
    requestAnimationFrame(function(){ t.style.opacity='1'; });
    setTimeout(function(){ t.style.opacity='0'; setTimeout(function(){ t.remove(); },300); }, 4000);
})();
</script>
<?php endif; ?>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
<?php
// 공통 푸터 include
if (file_exists(__DIR__ . '/includes/footer.php')) {
    include __DIR__ . '/includes/footer.php';
}
?>

<script>
// [1차 검수 #2 - 2026-05-28] 새로고침 시 최상단 튐 수정
// 브라우저 기본 스크롤 복원 동작 활성화 (새로고침 시 이전 위치 유지)
if ('scrollRestoration' in history) {
    history.scrollRestoration = 'auto';
}

// 이윰빌더 위젯의 scrollIntoView 강제 방지 — 자동 스크롤 버그 차단
(function(){
    var _origScroll = Element.prototype.scrollIntoView;
    Element.prototype.scrollIntoView = function(opts) {
        // 출석체크 위젯의 scrollIntoView만 차단
        if (this.classList && this.closest('.rb-attend')) {
            return; // 무시
        }
        return _origScroll.apply(this, arguments);
    };
    // [1차 검수 #2 픽스] 기존 setTimeout 3초 후 강제 scrollTo(0) 코드 제거
    // 이 코드가 사용자가 스크롤 중인 상태에서도 강제로 최상단으로 보내는 원인이었음
})();
</script>
