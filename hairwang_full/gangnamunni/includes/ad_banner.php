<?php
/**
 * 공통 광고 배너 컴포넌트
 * 사용법: $ad_page_key = 'hospitals'; include __DIR__ . '/ad_banner.php';
 */
if (!isset($connect) || !isset($ad_page_key)) return;

require_once __DIR__ . '/settings_helper.php';

$_ad_flag = getSettingValue($connect, 'ad_show_' . $ad_page_key, '1');
if ($_ad_flag !== '1') return;

$_ad_query = "SELECT a.* FROM gangnamunni_ads a
    WHERE a.status = 'approved'
      AND (a.target_page = 'all' OR a.target_page = '" . mysqli_real_escape_string($connect, $ad_page_key) . "')
      AND EXISTS (
          SELECT 1 FROM gangnamunni_ad_slot_bookings b
          WHERE b.advertiser_id = a.advertiser_id
            AND b.status = 'active'
            AND b.start_date <= CURDATE()
            AND (b.end_date IS NULL OR b.end_date >= CURDATE())
      )
    ORDER BY a.display_order ASC LIMIT 10";
$_ad_result = mysqli_query($connect, $_ad_query);
$_ads = [];
if ($_ad_result) {
    while ($_row = mysqli_fetch_assoc($_ad_result)) {
        $_ads[] = $_row;
    }
}

if (empty($_ads)) return;

$_auto_scroll = getSettingValue($connect, 'ad_auto_scroll', '1');
$_scroll_speed = getSettingValue($connect, 'ad_auto_scroll_speed', '4000');
$_carousel_id = 'ad-carousel-' . $ad_page_key;

// 배너별 그라데이션 색상
$_gradients = [
    'linear-gradient(135deg, #E91E63 0%, #FF5722 100%)',
    'linear-gradient(135deg, #1565C0 0%, #42A5F5 100%)',
    'linear-gradient(135deg, #6A1B9A 0%, #AB47BC 100%)',
    'linear-gradient(135deg, #00695C 0%, #26A69A 100%)',
    'linear-gradient(135deg, #E65100 0%, #FF9800 100%)',
];
?>

<div class="ad-banner-section"  id="<?php echo $_carousel_id; ?>">
    <div class="ad-carousel-container">
        <?php if (count($_ads) > 1): ?>
        <button class="ad-carousel-arrow ad-prev" onclick="adCarouselMove('<?php echo $_carousel_id; ?>', -1)">&lsaquo;</button>
        <button class="ad-carousel-arrow ad-next" onclick="adCarouselMove('<?php echo $_carousel_id; ?>', 1)">&rsaquo;</button>
        <?php endif; ?>
        <div class="ad-carousel-track">
            <?php foreach ($_ads as $_ai => $_ad):
                $_bg = $_gradients[$_ai % count($_gradients)];
            ?>
            <a href="<?php echo htmlspecialchars($_ad['link_url'] ?? '#'); ?>" class="ad-carousel-slide" data-ad-id="<?php echo $_ad['id']; ?>">
                <?php if (!empty($_ad['image_url']) && strpos($_ad['image_url'], 'sample') === false): ?>
                <img src="<?php echo htmlspecialchars($_ad['image_url']); ?>" alt="<?php echo htmlspecialchars($_ad['title']); ?>" class="ad-banner-img">
                <?php else: ?>
                <div class="ad-banner-text" style="background: <?php echo $_bg; ?>;">
                    <div class="ad-banner-badge">AD</div>
                    <div class="ad-banner-title"><?php echo htmlspecialchars($_ad['title']); ?></div>
                    <?php if (!empty($_ad['subtitle'])): ?>
                    <div class="ad-banner-subtitle"><?php echo htmlspecialchars($_ad['subtitle']); ?></div>
                    <?php endif; ?>
                    <div class="ad-banner-cta">자세히 보기 &rarr;</div>
                </div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (count($_ads) > 1): ?>
        <div class="ad-carousel-indicator">
            <span class="ad-current">1</span> / <?php echo count($_ads); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* ========================================================================
   [관리자 #메인배너 - 2026-05-28] 스크롤 시 header에 잘려 보이는 현상 픽스
   원인 : sticky top:48px + z-index:50. global_nav(z-index:1000)에 가려져
          배너 상단이 잘려 보이는 현상 (모바일 환경).
   변경 : sticky top 값을 global_nav 높이만큼 정확히 조정 (top:56px → 안전)
          + z-index 100으로 상향 (다른 sticky 요소와 분리)
          + isolation:isolate 추가로 stacking context 명확화
   영향 범위 : ad_banner.php (광고 배너 컴포넌트만). 다른 sticky 요소 영향 X.
   ======================================================================== */
/* 기존 코드 (보존)
.ad-banner-section { position: sticky; top: 48px; z-index: 50; max-width: 640px; margin: 0 auto 16px; padding: 0 16px 12px; margin-bottom: 16px; padding-bottom: 12px; overflow: hidden; border-radius: 12px; box-sizing: border-box; background: inherit; scroll-margin-bottom: 20px; }
*/
.ad-banner-section { position: sticky; top: 56px; z-index: 100; isolation: isolate; max-width: 640px; margin: 0 auto 16px; padding: 0 16px 12px; margin-bottom: 16px; padding-bottom: 12px; overflow: hidden; border-radius: 12px; box-sizing: border-box; background: var(--bs-body-bg, #fff); scroll-margin-bottom: 20px; }
.ad-carousel-container { position: relative; overflow: hidden; border-radius: 12px; touch-action: pan-y; -webkit-user-select: none; user-select: none; }
.ad-carousel-track { display: flex; transition: transform 0.4s ease; }
.ad-carousel-slide { min-width: 100%; text-decoration: none; }
.ad-banner-img { width: 100%; height: 140px; object-fit: cover; border-radius: 12px; }
.ad-banner-text {
    width: 100%; height: 140px; border-radius: 12px;
    display: flex; flex-direction: column; justify-content: center;
    padding: 24px 28px; box-sizing: border-box; position: relative;
}
.ad-banner-badge {
    position: absolute; top: 10px; left: 12px;
    background: rgba(255,255,255,0.25); color: #fff;
    padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold;
}
.ad-banner-title { color: #fff; font-size: 20px; font-weight: bold; margin-bottom: 6px; text-shadow: 0 1px 3px rgba(0,0,0,0.2); }
.ad-banner-subtitle { color: rgba(255,255,255,0.9); font-size: 13px; margin-bottom: 8px; }
.ad-banner-cta {
    display: inline-block; background: rgba(255,255,255,0.25); color: #fff;
    padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
    width: fit-content;
}
.ad-carousel-arrow {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(0,0,0,0.3); color: #fff; border: none;
    width: 32px; height: 32px; border-radius: 50%;
    font-size: 20px; cursor: pointer; z-index: 5;
    display: flex; align-items: center; justify-content: center;
}
.ad-prev { left: 8px; }
.ad-next { right: 8px; }
.ad-carousel-indicator {
    position: absolute; bottom: 8px; right: 12px;
    background: rgba(0,0,0,0.5); color: #fff;
    padding: 2px 10px; border-radius: 10px; font-size: 11px;
}
</style>

<script>
(function(){
    var cid = '<?php echo $_carousel_id; ?>';
    var el = document.getElementById(cid);
    if (!el) return;
    var track = el.querySelector('.ad-carousel-track');
    var slides = track ? track.querySelectorAll('.ad-carousel-slide') : [];
    if (slides.length <= 1) return;
    window['adCarouselMove'] = window['adCarouselMove'] || function(id, dir) {
        var e = document.getElementById(id);
        if (!e) return;
        var t = e.querySelector('.ad-carousel-track');
        var s = t.querySelectorAll('.ad-carousel-slide');
        var ci = parseInt(e.dataset.idx || '0');
        ci = (ci + dir + s.length) % s.length;
        t.style.transform = 'translateX(-' + (ci * 100) + '%)';
        e.dataset.idx = ci;
        var ind = e.querySelector('.ad-current');
        if (ind) ind.textContent = ci + 1;
    };
    el.dataset.idx = '0';
    // 터치 스와이프 지원
    var startX = 0, startY = 0, swiped = false;
    el.addEventListener('touchstart', function(e) {
        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
        swiped = false;
    }, {passive: true});
    el.addEventListener('touchmove', function(e) {
        if (!swiped) {
            var dx = Math.abs(e.touches[0].clientX - startX);
            var dy = Math.abs(e.touches[0].clientY - startY);
            if (dx > dy && dx > 10) {
                swiped = true;
            }
        }
        if (swiped) { e.preventDefault(); }
    }, {passive: false});
    el.addEventListener('touchend', function(e) {
        if (!swiped) return;
        var diff = startX - e.changedTouches[0].clientX;
        if (Math.abs(diff) > 40) adCarouselMove(cid, diff > 0 ? 1 : -1);
    }, {passive: true});
    <?php if ($_auto_scroll === '1'): ?>
    var timer;
    function startAuto() {
        timer = setInterval(function(){ adCarouselMove(cid, 1); }, <?php echo (int)$_scroll_speed; ?>);
    }
    startAuto();
    el.addEventListener('mouseenter', function(){ clearInterval(timer); });
    el.addEventListener('mouseleave', startAuto);
    <?php endif; ?>
})();
</script>
<?php
unset($_ad_flag, $_ad_query, $_ad_result, $_ads, $_auto_scroll, $_scroll_speed, $_carousel_id, $_gradients);
?>
