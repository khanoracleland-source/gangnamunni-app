<?php
/**
 * 롤링배너 — 광고 등급 병원 자동 순환 노출
 * 사용: $rolling_page = 'index'; include __DIR__ . '/includes/rolling_banner.php';
 *
 * 동작: ad_grade가 none이 아닌 병원 또는 rolling_banner_enabled=1인 병원을
 *       랜덤 추출하여 자동 슬라이드로 표시. CPV 캠페인 연동 시 노출당 차감.
 */
if (!isset($connect)) return;

require_once __DIR__ . '/settings_helper.php';

// 롤링배너 ON/OFF
$_rb_enabled = getSettingValue($connect, 'rolling_banner_enabled', '1');
if ($_rb_enabled !== '1') return;

$_rb_count = (int)getSettingValue($connect, 'rolling_banner_count', '10');
$_rb_speed = (int)getSettingValue($connect, 'rolling_banner_speed', '5000');
if ($_rb_count < 1) $_rb_count = 10;
if ($_rb_count > 100) $_rb_count = 100;

// 광고 등급 병원 + 롤링 활성 병원 조회 (랜덤 순서)
$_rb_sql = "SELECT h.id, h.name, h.main_image_url, h.profile_image_url, h.ad_grade, h.address,
                   h.rating, h.review_count,
                   rc.id as campaign_id, rc.impression_cost
            FROM gangnamunni_hospitals h
            LEFT JOIN gangnamunni_rolling_campaigns rc ON rc.hospital_id = h.id AND rc.status = 'active'
            WHERE (h.ad_grade != 'none' OR h.rolling_banner_enabled = 1)
              AND h.is_active = 1
            ORDER BY FIELD(h.ad_grade,'S','A','B','C','D','E','none') ASC, RAND()
            LIMIT {$_rb_count}";
$_rb_result = mysqli_query($connect, $_rb_sql);
$_rb_hospitals = [];
if ($_rb_result) {
    while ($_r = mysqli_fetch_assoc($_rb_result)) {
        $_rb_hospitals[] = $_r;
    }
}

if (empty($_rb_hospitals)) return;

// CPV 노출 기록 (캠페인 연동)
foreach ($_rb_hospitals as $_rbh) {
    if (!empty($_rbh['campaign_id'])) {
        $cost = (int)($_rbh['impression_cost'] ?: 10);
        mysqli_query($connect, "UPDATE gangnamunni_rolling_campaigns
            SET impressions = impressions + 1, spent = spent + {$cost},
                status = CASE WHEN (spent + {$cost}) >= total_budget AND total_budget > 0 THEN 'exhausted' ELSE status END
            WHERE id = {$_rbh['campaign_id']} AND status = 'active'");
    }
}

$_rb_id = 'rolling-banner-' . ($rolling_page ?? 'default');
$_grade_colors = [
    'S' => '#e53e3e', 'A' => '#dd6b20', 'B' => '#d69e2e',
    'C' => '#38a169', 'D' => '#3182ce', 'E' => '#805ad5'
];
?>

<div class="rb-section" id="<?php echo $_rb_id; ?>">
    <div class="rb-header">
        <span class="rb-title">🏥 추천 병원</span>
        <span class="rb-indicator"><span class="rb-current">1</span> / <?php echo count($_rb_hospitals); ?></span>
    </div>
    <div class="rb-track-wrap">
        <div class="rb-track">
            <?php foreach ($_rb_hospitals as $_ri => $_rbh):
                $_img = !empty($_rbh['main_image_url']) ? $_rbh['main_image_url'] : 'assets/images/hospital_default.jpg';
                if (strpos($_img, 'http') !== 0) $_img = './' . $_img;
                $_grade = $_rbh['ad_grade'] ?? 'none';
                $_gc = $_grade_colors[$_grade] ?? '#999';
            ?>
            <a href="detail.php?id=<?php echo $_rbh['id']; ?>" class="rb-card" data-idx="<?php echo $_ri; ?>">
                <div class="rb-img-wrap">
                    <img src="<?php echo htmlspecialchars($_img); ?>" alt="<?php echo htmlspecialchars($_rbh['name']); ?>" loading="lazy">
                    <?php if ($_grade !== 'none'): ?>
                    <span class="rb-grade" style="background:<?php echo $_gc; ?>;"><?php echo $_grade; ?></span>
                    <?php endif; ?>
                </div>
                <div class="rb-info">
                    <div class="rb-name"><?php echo htmlspecialchars($_rbh['name']); ?></div>
                    <?php if ($_rbh['rating'] > 0): ?>
                    <div class="rb-rating">⭐ <?php echo number_format($_rbh['rating'], 1); ?> (<?php echo (int)$_rbh['review_count']; ?>)</div>
                    <?php endif; ?>
                    <?php if (!empty($_rbh['address'])): ?>
                    <div class="rb-addr"><?php echo htmlspecialchars(mb_substr($_rbh['address'], 0, 20)); ?></div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
.rb-section { max-width:640px; margin:0 auto 16px; padding:0 16px; }
.rb-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.rb-title { font-size:15px; font-weight:700; color:var(--color-text-primary,#333); }
.rb-indicator { font-size:12px; color:#999; }
.rb-track-wrap { overflow:hidden; position:relative; }
.rb-track { display:flex; gap:10px; overflow-x:auto; scroll-snap-type:x mandatory; -webkit-overflow-scrolling:touch; scrollbar-width:none; padding-bottom:4px; }
.rb-track::-webkit-scrollbar { display:none; }
.rb-card { flex:0 0 160px; scroll-snap-align:start; text-decoration:none; color:inherit; border-radius:12px; overflow:hidden; border:1px solid var(--color-border,#f0f0f0); background:var(--color-bg-white,#fff); }
.rb-img-wrap { position:relative; width:100%; height:100px; overflow:hidden; }
.rb-img-wrap img { width:100%; height:100%; object-fit:cover; }
.rb-grade { position:absolute; top:6px; left:6px; color:#fff; font-size:10px; font-weight:700; padding:2px 8px; border-radius:4px; }
.rb-info { padding:8px 10px; }
.rb-name { font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.rb-rating { font-size:11px; color:#f59e0b; margin-top:2px; }
.rb-addr { font-size:10px; color:#999; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
</style>

<script>
(function(){
    var wrap = document.querySelector('#<?php echo $_rb_id; ?> .rb-track');
    if (!wrap) return;
    var cards = wrap.querySelectorAll('.rb-card');
    var total = cards.length;
    if (total <= 1) return;
    var idx = 0;
    var indicator = document.querySelector('#<?php echo $_rb_id; ?> .rb-current');

    // [1차 검수 #4 - 2026-05-28] 자동 스크롤 — 페이지 세로 스크롤 영향 방지
    // 기존 scrollIntoView({block:'nearest', inline:'start'}) 는
    // 카드가 화면 밖에 있을 때 페이지 전체를 위/아래로 스크롤하는 부작용 발생.
    // 가로 컨테이너 scrollLeft 만 직접 조작하여 세로 스크롤 트리거 차단.
    setInterval(function(){
        idx = (idx + 1) % total;
        var card = cards[idx];
        var targetLeft = card.offsetLeft - wrap.offsetLeft;
        wrap.scrollTo({ left: targetLeft, behavior: 'smooth' });
        if (indicator) indicator.textContent = idx + 1;
    }, <?php echo $_rb_speed; ?>);

    // 스크롤 시 인디케이터 업데이트
    wrap.addEventListener('scroll', function(){
        var scrollLeft = wrap.scrollLeft;
        var cardWidth = cards[0].offsetWidth + 10;
        var newIdx = Math.round(scrollLeft / cardWidth);
        if (newIdx >= 0 && newIdx < total && indicator) {
            indicator.textContent = newIdx + 1;
        }
    });
})();
</script>
<?php
