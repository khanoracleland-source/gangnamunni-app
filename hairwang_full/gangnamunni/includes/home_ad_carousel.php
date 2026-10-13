<?php
/**
 * 홈 광고 배너 캐러셀 — index.php 와 mypage.php 가 공유한다.
 * 관리자에서 광고를 승인/추가/숨김 처리하면 두 페이지에 동시에 반영된다.
 *
 *  - home_banner_ads($connect)      : 표시할 광고 목록 (index.php 와 동일한 쿼리)
 *  - home_ad_show_index($connect)   : 관리자 "메인 배너 표시" 설정 (ad_show_index)
 *  - home_ad_slides($ads)           : .carousel-track 안의 슬라이드 마크업
 *  - render_home_ad_carousel(...)   : 섹션 + CSS/JS 까지 한 번에 출력 (mypage.php 용)
 */

if (!function_exists('home_safe_link')) {
    function home_safe_link($url, $fallback = '#') {
        $url = trim((string)$url);
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) return $fallback;
        if ($url[0] === '#' || $url[0] === '/') return strncmp($url, '//', 2) === 0 ? $fallback : $url;
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if ($scheme === '') return preg_match('/^[a-z0-9._~!$&\'()*+,;=:@%\\/?#-]+$/i', $url) ? $url : $fallback;
        return in_array($scheme, ['http', 'https'], true) ? $url : $fallback;
    }
}

if (!function_exists('home_banner_ads')) {
    // Homepage banners use approval and independent visibility, not paid slot bookings.
    function home_banner_ads($connect) {
        $ads = [];
        $ads_query = "SELECT a.* FROM gangnamunni_ads a
            WHERE a.status = 'approved'
              AND NOT EXISTS (
                  SELECT 1 FROM gangnamunni_settings s
                  WHERE s.setting_key = CONCAT('home_banner_enabled_', a.id)
                    AND s.setting_value = '0'
              )
            ORDER BY a.display_order ASC LIMIT 10";
        $ads_result = mysqli_query($connect, $ads_query);
        if ($ads_result) {
            while ($row = mysqli_fetch_assoc($ads_result)) {
                $ads[] = $row;
            }
        }
        return $ads;
    }
}

if (!function_exists('home_ad_show_index')) {
    function home_ad_show_index($connect) {
        $r = mysqli_query($connect, "SELECT setting_value FROM gangnamunni_settings WHERE setting_key = 'ad_show_index' LIMIT 1");
        if ($r && ($row = mysqli_fetch_assoc($r))) return (string)$row['setting_value'];
        return '0';
    }
}

if (!function_exists('home_ad_slides')) {
    function home_ad_slides(array $ads) {
        $base = dirname(__DIR__); // gangnamunni/
        foreach ($ads as $ad): ?>
                    <a href="<?php echo htmlspecialchars(home_safe_link($ad['link_url']), ENT_QUOTES); ?>" class="carousel-slide ad-item" data-ad-id="<?php echo (int)$ad['id']; ?>" style="text-decoration:none;">
                        <?php if (!empty($ad['image_url']) && file_exists($base . '/' . $ad['image_url'])): ?>
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
<?php   endforeach;
    }
}

if (!function_exists('render_home_ad_carousel')) {
    /**
     * index.php 와 같은 광고/같은 표시 설정으로 캐러셀을 출력한다.
     * 광고가 없거나 관리자에서 메인 배너를 끄면 아무것도 출력하지 않는다.
     */
    function render_home_ad_carousel($connect, $carousel_id = 'mypage-ads-carousel', $section_style = '') {
        if (home_ad_show_index($connect) !== '1') return false;
        $ads = home_banner_ads($connect);
        if (empty($ads)) return false;

        $auto = '1';
        $r = mysqli_query($connect, "SELECT setting_value FROM gangnamunni_theme_settings WHERE setting_key = 'ad_auto_scroll_enabled' LIMIT 1");
        if ($r && ($row = mysqli_fetch_assoc($r))) $auto = (string)$row['setting_value'];

        static $assets_done = false;
        if (!$assets_done) {
            $assets_done = true; ?>
<style>
.carousel-section { position: relative; margin-bottom: 16px; }
.carousel-container { position: relative; overflow: hidden; border-radius: 0px; touch-action: pan-y; -webkit-user-select: none; user-select: none; }
.carousel-track { display: flex; transition: transform 0.4s ease; }
.carousel-slide { min-width: 100%; flex-shrink: 0; }
</style>
<script>
(function () {
    function move(el, dir) {
        var track = el.querySelector('.carousel-track');
        var total = track ? track.querySelectorAll('.carousel-slide').length : 0;
        if (!total) return;
        var idx = (parseInt(track.getAttribute('data-index') || '0', 10) + dir + total) % total;
        track.setAttribute('data-index', idx);
        track.style.transform = 'translateX(-' + (idx * 100) + '%)';
    }
    window.initHomeAdCarousel = function (id, autoOn, ms) {
        var el = document.getElementById(id);
        if (!el) return;
        var timer = null;
        function start() { if (autoOn === '1' && !timer) timer = setInterval(function () { move(el, 1); }, ms || 5000); }
        function stop() { clearInterval(timer); timer = null; }
        start();
        el.addEventListener('mouseenter', stop);
        el.addEventListener('mouseleave', start);
        var sx = 0, sy = 0, moved = false;
        el.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; sy = e.touches[0].clientY; moved = false; }, { passive: true });
        el.addEventListener('touchmove', function (e) {
            if (!moved) { var dx = Math.abs(e.touches[0].clientX - sx), dy = Math.abs(e.touches[0].clientY - sy); if (dx > dy && dx > 10) moved = true; }
            if (moved) e.preventDefault();
        }, { passive: false });
        el.addEventListener('touchend', function (e) {
            if (!moved) return;
            var diff = sx - e.changedTouches[0].clientX;
            if (Math.abs(diff) > 40) { stop(); move(el, diff > 0 ? 1 : -1); start(); }
        }, { passive: true });
    };
}());
</script>
<?php   } ?>
        <div class="carousel-section" id="<?php echo htmlspecialchars($carousel_id, ENT_QUOTES); ?>"<?php echo $section_style !== '' ? ' style="' . htmlspecialchars($section_style, ENT_QUOTES) . '"' : ''; ?>>
            <div class="carousel-container">
                <div class="carousel-track">
<?php home_ad_slides($ads); ?>
                </div>
            </div>
        </div>
        <script>initHomeAdCarousel(<?php echo json_encode($carousel_id); ?>, <?php echo json_encode($auto); ?>, 5000);</script>
<?php
        return true;
    }
}
