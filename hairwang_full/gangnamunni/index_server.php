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

// DB에서 활성 카테고리 로드 (category_icons.php의 loadCategoriesFromDB)
$db_categories = loadCategoriesFromDB($connect);

// 서울미녀 병원 데이터 테이블 존재 확인
$table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_hospitals'");
$table_exists = mysqli_num_rows($table_check) > 0;

// 꿀팁 테이블 존재 확인
$tips_table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_tips'");
$tips_table_exists = mysqli_num_rows($tips_table_check) > 0;

// 병원 데이터 가져오기
$hospitals = [];
if ($table_exists) {
    $query = "SELECT * FROM gangnamunni_hospitals WHERE is_active = 1 ORDER BY is_featured DESC, rating DESC, review_count DESC LIMIT " . HOSPITALS_PER_PAGE;
    $result = mysqli_query($connect, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $hospitals[] = $row;
        }
    }
}

// 꿀팁 데이터 가져오기
$tips = [];
if ($tips_table_exists && !USE_DEMO_DATA) {
    $tips_query = "SELECT * FROM gangnamunni_tips ORDER BY created_at DESC LIMIT " . TIPS_PER_PAGE;
    $tips_result = mysqli_query($connect, $tips_query);

    if ($tips_result) {
        while ($row = mysqli_fetch_assoc($tips_result)) {
            $tips[] = $row;
        }
    }
}

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
$events_query = "SELECT e.*, h.name as hospital_name
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

// 응모 이벤트 (has_raffle = 1인 이벤트)
$raffles = [];
$raffles_query = "SELECT e.*, h.name as hospital_name,
    (SELECT COUNT(*) FROM gangnamunni_event_entries en WHERE en.event_id = e.id) as entry_count
    FROM gangnamunni_events e
    LEFT JOIN gangnamunni_hospitals h ON e.hospital_id = h.id
    WHERE e.is_active = 1 AND e.has_raffle = 1
    AND (e.raffle_end_date IS NULL OR e.raffle_end_date >= CURDATE())
    ORDER BY e.raffle_end_date ASC, e.created_at DESC LIMIT 6";
$raffles_result = mysqli_query($connect, $raffles_query);
if ($raffles_result) {
    while ($row = mysqli_fetch_assoc($raffles_result)) {
        $raffles[] = $row;
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

// 레이아웃 설정
$page_title = term('business') . ' 찾기';
$show_search = true;
$page_css_file = 'assets/css/index-page.css';

// 이벤트/응모 섹션 추가 CSS
$page_css = '
/* 이벤트 섹션 */
.events-section {
    margin-bottom: 24px;
}
.events-section .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.events-section .section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
}
.events-section .section-more {
    font-size: 13px;
    color: #AE1932;
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
    border-radius: 14px;
    overflow: hidden;
    background: var(--bs-body-bg, #fff);
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: transform 0.2s;
    text-decoration: none;
    color: inherit;
}
.event-card-mini:hover {
    transform: translateY(-3px);
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
    font-size: 11px;
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
    font-size: 11px;
    color: #AE1932;
    font-weight: 600;
    margin-bottom: 4px;
}
.event-card-mini .card-title {
    font-size: 14px;
    font-weight: 700;
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
    color: #888;
    margin-bottom: 6px;
}
.event-card-mini .card-price {
    display: flex;
    align-items: center;
    gap: 6px;
}
.event-card-mini .card-price .original {
    font-size: 12px;
    color: #bbb;
    text-decoration: line-through;
}
.event-card-mini .card-price .discount {
    font-size: 15px;
    font-weight: 700;
    color: #AE1932;
}
.event-card-mini .card-date {
    font-size: 11px;
    color: #999;
    margin-top: 4px;
}

/* 응모 섹션 */
.raffle-section {
    margin-bottom: 24px;
}
.raffle-section .section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.raffle-section .section-title {
    font-size: 18px;
    font-weight: 700;
    color: #1a1a1a;
}
.raffle-section .section-more {
    font-size: 13px;
    color: #AE1932;
    text-decoration: none;
    font-weight: 500;
}
.raffle-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.raffle-card {
    border-radius: 14px;
    overflow: hidden;
    background: var(--bs-body-bg, #fff);
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: transform 0.2s;
    text-decoration: none;
    color: inherit;
    position: relative;
}
.raffle-card:hover {
    transform: translateY(-3px);
}
.raffle-card .raffle-img {
    width: 100%;
    height: 120px;
    background: linear-gradient(135deg, #AE1932, #A78BFA);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    position: relative;
    overflow: hidden;
}
.raffle-card .raffle-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.raffle-card .raffle-type-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    color: white;
    background: #AE1932;
}
.raffle-card .raffle-dday {
    position: absolute;
    top: 8px;
    right: 8px;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    color: white;
    background: rgba(0,0,0,0.5);
}
.raffle-card .raffle-body {
    padding: 12px;
}
.raffle-card .raffle-title {
    font-size: 14px;
    font-weight: 700;
    color: #1a1a1a;
    margin-bottom: 4px;
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.raffle-card .raffle-prize {
    font-size: 12px;
    color: #AE1932;
    font-weight: 600;
    margin-bottom: 6px;
}
.raffle-card .raffle-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.raffle-card .raffle-entries {
    font-size: 11px;
    color: #888;
}
.raffle-card .raffle-btn {
    font-size: 12px;
    font-weight: 700;
    color: white;
    background: #AE1932;
    padding: 4px 12px;
    border-radius: 12px;
}

@media (max-width: 480px) {
    .event-card-mini {
        flex: 0 0 170px;
    }
    .event-card-mini .card-img {
        width: 170px;
        height: 120px;
    }
    .raffle-grid {
        grid-template-columns: 1fr;
    }
}

/* 캐러셀 (광고/프로모션 배너) */
.carousel-section { position: relative; margin-bottom: 16px; }
.carousel-container { position: relative; overflow: hidden; border-radius: 12px; }
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
    padding: 2px 10px; border-radius: 10px; font-size: 11px;
}
.promo-banner {
    display: flex; flex-direction: column; justify-content: center;
    padding: 24px 28px; border-radius: 12px; min-height: 120px;
}
.promo-title { font-size: 18px; font-weight: bold; margin-bottom: 6px; }
.promo-subtitle { font-size: 13px; opacity: 0.9; }
';

// 페이지 전용 JS
$page_js = '
// 뷰 전환 기능
function switchView(viewType) {
    var grid = document.getElementById("hospital-grid");
    var cardBtn = document.getElementById("view-card");
    var listBtn = document.getElementById("view-list");

    if (viewType === "list") {
        grid.classList.add("list-view");
        cardBtn.classList.remove("active");
        listBtn.classList.add("active");
    } else {
        grid.classList.remove("list-view");
        cardBtn.classList.add("active");
        listBtn.classList.remove("active");
    }

    localStorage.setItem("hospital_view", viewType);
}

// 페이지 로드 시 저장된 뷰 적용
document.addEventListener("DOMContentLoaded", function() {
    var savedView = localStorage.getItem("hospital_view");
    if (savedView === "list") {
        switchView("list");
    }
});

// 스크롤 컨테이너 좌우 이동
function scrollContainer(selector, amount) {
    var container = document.querySelector(selector);
    if (!container) return;

    var startPosition = container.scrollLeft;
    var targetPosition = startPosition + amount;
    var duration = 500;
    var startTime = null;

    function animation(currentTime) {
        if (startTime === null) startTime = currentTime;
        var timeElapsed = currentTime - startTime;
        var progress = Math.min(timeElapsed / duration, 1);

        var ease = progress < 0.5
            ? 2 * progress * progress
            : 1 - Math.pow(-2 * progress + 2, 2) / 2;

        container.scrollLeft = startPosition + ((targetPosition - startPosition) * ease);

        if (timeElapsed < duration) {
            requestAnimationFrame(animation);
        }
    }

    requestAnimationFrame(animation);
}

function showImportGuide() {
    alert("데이터베이스 Import 가이드:\n\n1. MySQL 접속:\n   mysql -u root -p -h localhost\n\n2. hairwang 데이터베이스 선택:\n   USE hairwang;\n\n3. SQL 파일 import:\n   SOURCE H:/carebaby_project/site_get/gangnamunni_hospitals_import.sql;\n\n4. 완료 후 이 페이지 새로고침 (F5)\n\n문제 발생 시: test_db_connection.php에서 DB 연결 확인");
}

// 시술 카테고리 필터
function filterByCategory(category) {
    location.href = "hospitals.php?category=" + encodeURIComponent(category);
}

// 지역 필터
function filterByRegion(region) {
    if (region === "") {
        location.href = "index.php";
    } else {
        location.href = "hospitals.php?q=" + encodeURIComponent(region);
    }
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

// 수평 자동 스크롤 기능
function initAutoScroll(containerSelector, enabled, interval) {
    if (!enabled || enabled === "0") return;

    var container = document.querySelector(containerSelector);
    if (!container) return;

    var scrollInterval = parseInt(interval) || 10000;
    var isScrolling = false;

    function smoothScrollTo(targetPosition) {
        if (isScrolling) return;
        isScrolling = true;

        var startPosition = container.scrollLeft;
        var distance = targetPosition - startPosition;
        var duration = 800;
        var startTime = null;

        function animation(currentTime) {
            if (startTime === null) startTime = currentTime;
            var timeElapsed = currentTime - startTime;
            var progress = Math.min(timeElapsed / duration, 1);

            var ease = progress < 0.5
                ? 2 * progress * progress
                : 1 - Math.pow(-2 * progress + 2, 2) / 2;

            container.scrollLeft = startPosition + (distance * ease);

            if (timeElapsed < duration) {
                requestAnimationFrame(animation);
            } else {
                isScrolling = false;
            }
        }

        requestAnimationFrame(animation);
    }

    function autoScroll() {
        var maxScroll = container.scrollWidth - container.clientWidth;

        if (container.scrollLeft >= maxScroll - 10) {
            smoothScrollTo(0);
        } else {
            var item = container.querySelector(".ad-item, .promo-banner");
            var itemWidth = item ? item.offsetWidth : 300;
            var gap = 12;
            var scrollAmount = itemWidth + gap;
            smoothScrollTo(container.scrollLeft + scrollAmount);
        }
    }

    var scrollTimer = setInterval(autoScroll, scrollInterval);

    container.addEventListener("scroll", function() {
        clearInterval(scrollTimer);
        scrollTimer = setInterval(autoScroll, scrollInterval);
    }, { passive: true });

    container.addEventListener("mouseenter", function() {
        clearInterval(scrollTimer);
    });

    container.addEventListener("mouseleave", function() {
        scrollTimer = setInterval(autoScroll, scrollInterval);
    });
}

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

// 터치 스와이프 지원 (#5)
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
    }, { passive: true });
    el.addEventListener("touchend", function(e) {
        if (!moved) return;
        var endX = e.changedTouches[0].clientX;
        var diff = startX - endX;
        if (Math.abs(diff) > 40) {
            carouselMove(carouselId, diff > 0 ? 1 : -1);
        }
    }, { passive: true });
}

// 페이지 로드 후 초기화
document.addEventListener("DOMContentLoaded", function() {
    initCarouselAuto("ads-carousel", "' . $ad_auto_scroll_enabled . '", "' . $ad_auto_scroll_interval . '");
    initCarouselAuto("promo-carousel", "' . $promo_auto_scroll_enabled . '", "' . $promo_auto_scroll_interval . '");
    addSwipeSupport("ads-carousel");
    addSwipeSupport("promo-carousel");
});
';

// 공통 헤더 include
include __DIR__ . '/includes/header.php';
?>
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
        <!-- 광고 배너 캐러셀 -->
        <?php if (!empty($ads)): ?>
        <div class="carousel-section" id="ads-carousel" style="position:sticky;top:48px;z-index:100;background:var(--bs-body-bg, #fff);">
            <h3 style="margin-bottom: 12px; font-size: 16px; font-weight: 600;">관심있는 시술을 선택해보세요</h3>
            <div class="carousel-container">
                <button class="carousel-arrow carousel-prev" onclick="carouselMove('ads-carousel', -1)">‹</button>
                <button class="carousel-arrow carousel-next" onclick="carouselMove('ads-carousel', 1)">›</button>
                <div class="carousel-track">
                    <?php foreach ($ads as $ad): ?>
                    <a href="<?php echo htmlspecialchars($ad['link_url']); ?>" class="carousel-slide ad-item" data-ad-id="<?php echo $ad['id']; ?>">
                        <?php
                        $_grd = ['linear-gradient(135deg,#E91E63,#FF5722)','linear-gradient(135deg,#1565C0,#42A5F5)','linear-gradient(135deg,#6A1B9A,#AB47BC)','linear-gradient(135deg,#00695C,#26A69A)','linear-gradient(135deg,#E65100,#FF9800)'];
                        $_bg = $_grd[$ad['id'] % count($_grd)];
                        ?>
                        <?php if (!empty($ad['image_url']) && file_exists(__DIR__ . '/' . $ad['image_url'])): ?>
                        <div class="ad-image">
                            <img src="<?php echo htmlspecialchars($ad['image_url']); ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>" style="width:100%;height:100%;object-fit:cover;">
                        </div>
                        <div class="ad-content">
                            <div class="ad-title"><?php echo htmlspecialchars($ad['title']); ?></div>
                            <?php if (!empty($ad['subtitle'])): ?><div class="ad-subtitle"><?php echo htmlspecialchars($ad['subtitle']); ?></div><?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div style="background:<?php echo $_bg; ?>;border-radius:12px;padding:20px;color:#fff;display:flex;flex-direction:column;justify-content:center;min-height:120px;position:relative;">
                            <span style="position:absolute;top:8px;right:10px;background:rgba(255,255,255,0.25);color:#fff;font-size:10px;padding:2px 6px;border-radius:4px;font-weight:600;">AD</span>
                            <div style="font-size:16px;font-weight:700;margin-bottom:4px;text-shadow:0 1px 3px rgba(0,0,0,0.2);"><?php echo htmlspecialchars($ad['title']); ?></div>
                            <?php if (!empty($ad['subtitle'])): ?><div style="font-size:12px;opacity:0.9;"><?php echo htmlspecialchars($ad['subtitle']); ?></div><?php endif; ?>
                            <div style="margin-top:8px;display:inline-block;background:rgba(255,255,255,0.25);padding:4px 12px;border-radius:15px;font-size:11px;font-weight:600;width:fit-content;">자세히 보기 &rarr;</div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-indicator"><span class="carousel-current">1</span> / <?php echo count($ads); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 프로모션 배너 캐러셀 -->
        <?php if (!empty($promos)): ?>
        <div class="carousel-section" id="promo-carousel">
            <div class="carousel-container">
                <button class="carousel-arrow carousel-prev" onclick="carouselMove('promo-carousel', -1)">‹</button>
                <button class="carousel-arrow carousel-next" onclick="carouselMove('promo-carousel', 1)">›</button>
                <div class="carousel-track">
                    <?php foreach ($promos as $promo): ?>
                    <a href="<?php echo htmlspecialchars($promo['link_url']); ?>"
                       class="carousel-slide promo-banner"
                       style="background: <?php echo htmlspecialchars($promo['background_gradient']); ?>; text-decoration: none; color: white;"
                       data-icon="<?php echo htmlspecialchars($promo['icon']); ?>">
                        <div class="promo-title"><?php echo $promo['title']; ?></div>
                        <div class="promo-subtitle"><?php echo htmlspecialchars($promo['subtitle']); ?></div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-indicator"><span class="carousel-current">1</span> / <?php echo count($promos); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 특별 이벤트 섹션 -->
        <?php if (!empty($events)): ?>
        <div class="events-section">
            <div class="section-header">
                <div class="section-title">🎁 특별 이벤트</div>
                <a href="events.php" class="section-more">전체보기 →</a>
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
                        <?php if (!empty($event['hospital_name'])): ?>
                        <div class="card-hospital"><?php echo htmlspecialchars($event['hospital_name']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($event['discount_price'])): ?>
                        <div class="card-price">
                            <?php if (!empty($event['original_price'])): ?>
                            <span class="original"><?php echo number_format($event['original_price']); ?>원</span>
                            <?php endif; ?>
                            <span class="discount"><?php echo number_format($event['discount_price']); ?>원</span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($event['end_date'])): ?>
                        <div class="card-date">~<?php echo date('m.d', strtotime($event['end_date'])); ?></div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 응모 이벤트 섹션 -->
        <?php if (!empty($raffles)): ?>
        <div class="raffle-section">
            <div class="section-header">
                <div class="section-title">🎰 응모 이벤트</div>
                <a href="events.php?filter=raffle" class="section-more">전체보기 →</a>
            </div>
            <div class="raffle-grid">
                <?php foreach ($raffles as $raffle):
                    // D-day 계산
                    $dday = '';
                    if (!empty($raffle['raffle_end_date'])) {
                        $diff = (strtotime($raffle['raffle_end_date']) - strtotime('today')) / 86400;
                        if ($diff <= 0) $dday = 'D-Day';
                        else $dday = 'D-' . intval($diff);
                    }
                    // 응모 타입 라벨
                    $type_labels = [
                        'lottery' => '추첨',
                        'first_come' => '선착순',
                        'quiz' => '퀴즈',
                        'share' => '공유'
                    ];
                    $type_label = $type_labels[$raffle['raffle_type'] ?? ''] ?? '추첨';
                    // 남은 자리
                    $spots_left = '';
                    if (!empty($raffle['max_entries']) && $raffle['max_entries'] > 0) {
                        $left = $raffle['max_entries'] - ($raffle['entry_count'] ?? 0);
                        $spots_left = max(0, $left) . '/' . $raffle['max_entries'];
                    }
                ?>
                <a href="event_detail.php?id=<?php echo $raffle['id']; ?>" class="raffle-card">
                    <div class="raffle-img">
                        <?php if (!empty($raffle['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($raffle['image_url']); ?>"
                             alt="<?php echo htmlspecialchars($raffle['title']); ?>"
                             onerror="this.style.display='none'; this.parentNode.textContent='🎰';">
                        <?php else: ?>
                        🎰
                        <?php endif; ?>
                        <div class="raffle-type-badge"><?php echo $type_label; ?></div>
                        <?php if ($dday): ?>
                        <div class="raffle-dday"><?php echo $dday; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="raffle-body">
                        <div class="raffle-title"><?php echo htmlspecialchars($raffle['title']); ?></div>
                        <?php if (!empty($raffle['raffle_prize'])): ?>
                        <div class="raffle-prize">🎁 <?php echo htmlspecialchars(mb_substr($raffle['raffle_prize'], 0, 30)); ?></div>
                        <?php endif; ?>
                        <div class="raffle-meta">
                            <span class="raffle-entries">
                                <?php if ($spots_left): ?>
                                <?php echo $spots_left; ?>명
                                <?php else: ?>
                                <?php echo number_format($raffle['entry_count'] ?? 0); ?>명 참여
                                <?php endif; ?>
                            </span>
                            <span class="raffle-btn">응모하기</span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 시술 카테고리 -->
        <div class="category-section">
            <h2 class="category-title"><?php echo getTheme('section_categories_title'); ?></h2>
            <div class="grid grid-cols-4 gap-4">
                <?php
                // DB 활성 카테고리 표시 (admin_categories.php에서 관리)
                if (!empty($db_categories)):
                    foreach ($db_categories as $cat):
                ?>
                <div class="flex flex-col items-center gap-2 cursor-pointer hover:scale-105 transition-transform" onclick="filterByCategory('<?php echo htmlspecialchars($cat['name']); ?>')">
                    <?php echo renderCategoryIcon($cat['name'], $category_icons); ?>
                    <div class="text-xs font-medium text-gray-700 text-center"><?php echo htmlspecialchars($cat['name']); ?></div>
                </div>
                <?php
                    endforeach;
                else:
                    // DB 카테고리 없을 때 폴백
                    echo '<div class="text-center text-gray-500 col-span-4">카테고리가 없습니다.</div>';
                endif;
                ?>
            </div>
        </div>

        <!-- 서울미녀 꿀팁 -->
        <div class="tips-section">
            <div class="tips-header">
                <h2 class="tips-title"><?php echo getTheme('section_tips_title'); ?></h2>
                <div class="tips-more" onclick="location.href='tips.php'"><?php echo getTheme('button_more'); ?> →</div>
            </div>
            <div class="tips-grid">
                <?php if (!empty($tips) && !USE_DEMO_DATA): ?>
                    <?php
                    $gradient_colors = [
                        ['#FFE0B2', '#FFCC80', '📝'],
                        ['#F8BBD0', '#F48FB1', '💄'],
                        ['#C5E1A5', '#AED581', '🌟'],
                        ['#B2EBF2', '#80DEEA', '💊']
                    ];
                    $color_index = 0;
                    foreach ($tips as $tip):
                        $colors = $gradient_colors[$color_index % 4];
                        $color_index++;
                        // placeholder URL인지 확인
                        $is_placeholder = empty($tip['thumbnail_url']) ||
                                         strpos($tip['thumbnail_url'], 'placeholder') !== false ||
                                         strpos($tip['thumbnail_url'], 'via.placeholder') !== false;
                    ?>
                    <div class="tip-card" onclick="location.href='detail_tip.php?id=<?php echo $tip['id']; ?>'">
                        <div class="tip-image" style="background: linear-gradient(135deg, <?php echo $colors[0]; ?>, <?php echo $colors[1]; ?>); display: flex; align-items: center; justify-content: center; font-size: 48px;">
                            <?php echo $colors[2]; ?>
                        </div>
                        <div class="tip-overlay"><?php echo htmlspecialchars($tip['title']); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- 데모 데이터 -->
                    <div class="tip-card" onclick="alert('실제 데이터가 로드되면 사용 가능합니다. config.php에서 USE_DEMO_DATA를 false로 설정하세요.')">
                        <div class="tip-image" style="background: linear-gradient(135deg, #FFE0B2, #FFCC80); display: flex; align-items: center; justify-content: center; font-size: 48px;">
                            📝
                        </div>
                        <div class="tip-overlay">연휴를 활용한 맞춤 관리 5가지</div>
                    </div>
                    <div class="tip-card" onclick="alert('실제 데이터가 로드되면 사용 가능합니다. config.php에서 USE_DEMO_DATA를 false로 설정하세요.')">
                        <div class="tip-image" style="background: linear-gradient(135deg, #F8BBD0, #F48FB1); display: flex; align-items: center; justify-content: center; font-size: 48px;">
                            💄
                        </div>
                        <div class="tip-overlay">볼살·턱살엔 인모드? 울쎄라?</div>
                    </div>
                    <div class="tip-card" onclick="alert('실제 데이터가 로드되면 사용 가능합니다. config.php에서 USE_DEMO_DATA를 false로 설정하세요.')">
                        <div class="tip-image" style="background: linear-gradient(135deg, #C5E1A5, #AED581); display: flex; align-items: center; justify-content: center; font-size: 48px;">
                            🌟
                        </div>
                        <div class="tip-overlay">콜써마지 문제 해결? 리주란부다 리투?</div>
                    </div>
                    <div class="tip-card" onclick="alert('실제 데이터가 로드되면 사용 가능합니다. config.php에서 USE_DEMO_DATA를 false로 설정하세요.')">
                        <div class="tip-image" style="background: linear-gradient(135deg, #B2EBF2, #80DEEA); display: flex; align-items: center; justify-content: center; font-size: 48px;">
                            💊
                        </div>
                        <div class="tip-overlay">스킨부스터 환절엽 리후란다 리투?</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 병원 리스트 섹션 -->
        <div class="hospital-section">
            <h2 class="hospital-section-title"><?php echo getTheme('section_hospitals_title'); ?></h2>

            <!-- 필터 섹션 -->
            <div class="filter-section">
                <button class="filter-btn active" onclick="filterByRegion('')">전체</button>
                <button class="filter-btn" onclick="filterByRegion('강남')">강남</button>
                <button class="filter-btn" onclick="filterByRegion('신촌')">신촌</button>
                <button class="filter-btn" onclick="filterByRegion('홍대')">홍대</button>
                <button class="filter-btn" onclick="filterByRegion('명동')">명동</button>
            </div>

            <!-- 뷰 전환 버튼 -->
            <div class="view-toggle">
                <button class="view-btn active" onclick="switchView('card')" id="view-card">
                    <span>🔲</span> 카드형
                </button>
                <button class="view-btn" onclick="switchView('list')" id="view-list">
                    <span>📋</span> 리스트형
                </button>
            </div>

        <!-- 병원 카드 그리드 -->
        <div class="hospital-grid" id="hospital-grid">
            <?php foreach ($hospitals as $hospital): ?>
            <div class="hospital-card" onclick="location.href='detail.php?id=<?php echo $hospital['id']; ?>'">
                <img
                    src="<?php echo htmlspecialchars($hospital['profile_image_url'] ?? ''); ?>"
                    alt="<?php echo htmlspecialchars($hospital['name']); ?>"
                    class="hospital-image"
                    onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'300\' height=\'200\'%3E%3Crect fill=\'%23f5f5f5\' width=\'300\' height=\'200\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' fill=\'%23999\' text-anchor=\'middle\' dominant-baseline=\'middle\' font-size=\'20\'%3E병원 이미지%3C/text%3E%3C/svg%3E'"
                >
                <div class="hospital-info">
                    <div class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></div>
                    <div class="hospital-address"><?php echo htmlspecialchars(mb_substr($hospital['address'] ?? '', 0, 30)); ?></div>
                    <div class="rating-section">
                        <span class="rating-score"><?php echo number_format($hospital['rating'] ?? 0, 1); ?></span>
                        <span class="review-count">(평가 <?php echo number_format($hospital['review_count'] ?? 0); ?>개)</span>
                    </div>
                    <?php if (!empty($hospital['treatment_tags'])): ?>
                    <div class="badge-group">
                        <?php
                        $tags = json_decode($hospital['treatment_tags'], true);
                        if (is_array($tags)) {
                            foreach (array_slice($tags, 0, 3) as $tag) {
                                // 태그가 배열이면 첫 번째 요소 사용, 아니면 그대로 사용
                                $tag_text = is_array($tag) ? (isset($tag[0]) ? $tag[0] : '') : $tag;
                                if (!empty($tag_text) && is_string($tag_text)) {
                                    echo '<span class="badge">' . htmlspecialchars($tag_text) . '</span>';
                                }
                            }
                        }
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($hospitals)): ?>
        <div style="text-align: center; padding: 60px 20px; color: #999;">
            <div style="font-size: 48px; margin-bottom: 20px;">🏥</div>
            <p>병원 데이터가 없습니다.</p>
            <p style="margin-top: 10px; font-size: 14px;">SQL 파일을 import 해주세요.</p>
        </div>
        <?php endif; ?>
        </div><!-- /.hospital-section -->
        <?php endif; ?>

<?php
// 공통 푸터 include
include __DIR__ . '/includes/footer.php';
?>
