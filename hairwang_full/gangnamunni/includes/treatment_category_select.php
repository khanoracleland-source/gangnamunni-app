<?php
/**
 * 시술 카테고리 선택 공통 컴포넌트
 *
 * 사용법:
 *   $cat_select_target = 'price_compare.php'; // 카테고리 클릭 시 이동할 페이지
 *   $cat_select_param = 'category';           // URL 파라미터 이름 (기본: category)
 *   $cat_select_show_popular = true;           // 인기 시술 TOP5 표시 (기본: true)
 *   $cat_select_title = '시술 카테고리';       // 섹션 제목 (기본: 시술 카테고리)
 *   include __DIR__ . '/treatment_category_select.php';
 *
 * 필요: $connect (mysqli)
 */

if (!function_exists('term')) { require_once __DIR__ . '/industry_terms.php'; }
if (!isset($cat_select_target)) $cat_select_target = basename($_SERVER['PHP_SELF']);
if (!isset($cat_select_param)) $cat_select_param = 'category';
if (!isset($cat_select_show_popular)) $cat_select_show_popular = true;
if (!isset($cat_select_title)) $cat_select_title = term('service', '시술') . ' 카테고리';

// 카테고리 목록
$_tcs_categories = [];
$_tcs_q = @mysqli_query($connect, "SELECT DISTINCT category, COUNT(*) as cnt FROM gangnamunni_treatments WHERE is_active=1 AND category != '' GROUP BY category ORDER BY cnt DESC");
if ($_tcs_q) {
    while ($r = mysqli_fetch_assoc($_tcs_q)) {
        $_tcs_categories[] = $r;
    }
}

// 카테고리 아이콘 매핑
$_tcs_icons = [
    '눈성형' => 'bi-eye', '코성형' => 'bi-droplet', '안면윤곽/양악' => 'bi-pentagon',
    '가슴성형' => 'bi-heart', '지방성형' => 'bi-lightning', '피부' => 'bi-stars',
    '리프팅' => 'bi-arrow-up-circle', '필러' => 'bi-droplet-half', '보톡스' => 'bi-syringe',
    '제모' => 'bi-scissors', '치아' => 'bi-emoji-smile', '탈모/두피' => 'bi-flower1',
    '레이저' => 'bi-sun', '성형' => 'bi-gem', '체형' => 'bi-person-arms-up',
];

if (!empty($_tcs_categories)):
?>
<div style="padding:20px 16px;">
    <h2 style="font-size:18px;font-weight:700;margin-bottom:4px;"><?php echo htmlspecialchars($cat_select_title); ?></h2>
    <p style="font-size:13px;color:var(--muted, #888);margin-bottom:16px;">비교할 <?php echo term('service', '시술'); ?> 카테고리를 선택하세요</p>

    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;">
        <?php foreach ($_tcs_categories as $cat):
            $icon = $_tcs_icons[$cat['category']] ?? 'bi-tag';
        ?>
        <a href="<?php echo htmlspecialchars($cat_select_target); ?>?<?php echo $cat_select_param; ?>=<?php echo urlencode($cat['category']); ?>"
           style="display:flex;align-items:center;gap:12px;padding:16px;background:var(--card, #fff);border:1px solid var(--border, #eee);border-radius:12px;text-decoration:none;color:var(--text, #1a1a1a);transition:all 0.2s;">
            <div style="width:44px;height:44px;border-radius:12px;background:linear-gradient(135deg,#AC183018,#AC183008);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--primary, #AC1830);flex-shrink:0;">
                <i class="bi <?php echo $icon; ?>"></i>
            </div>
            <div>
                <div style="font-size:15px;font-weight:600;"><?php echo htmlspecialchars($cat['category']); ?></div>
                <div style="font-size:12px;color:var(--muted, #888);"><?php echo $cat['cnt']; ?>개 <?php echo term('service', '시술'); ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($cat_select_show_popular):
        $_tcs_popular = [];
        $_tcs_pop_q = @mysqli_query($connect, "SELECT t.name, t.category, COUNT(*) as cnt, MIN(COALESCE(NULLIF(t.discount_price,0), t.price)) as min_price
            FROM gangnamunni_treatments t WHERE t.is_active=1 AND t.price > 0
            GROUP BY t.name ORDER BY cnt DESC LIMIT 5");
        if ($_tcs_pop_q) { while ($r = mysqli_fetch_assoc($_tcs_pop_q)) $_tcs_popular[] = $r; }
    ?>
    <?php if ($_tcs_popular): ?>
    <h3 style="font-size:16px;font-weight:700;margin:24px 0 12px;">인기 <?php echo term('service', '시술'); ?> TOP 5</h3>
    <?php foreach ($_tcs_popular as $idx => $pop): ?>
    <a href="<?php echo htmlspecialchars($cat_select_target); ?>?treatment=<?php echo urlencode($pop['name']); ?>&<?php echo $cat_select_param; ?>=<?php echo urlencode($pop['category']); ?>"
       style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border, #eee);text-decoration:none;color:var(--text, #1a1a1a);">
        <div style="width:28px;height:28px;border-radius:50%;background:var(--primary, #AC1830);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0;"><?php echo $idx + 1; ?></div>
        <div style="flex:1;">
            <span style="font-size:14px;font-weight:500;"><?php echo htmlspecialchars($pop['name']); ?></span>
            <span style="font-size:12px;color:var(--muted, #888);margin-left:6px;"><?php echo htmlspecialchars($pop['category']); ?></span>
        </div>
        <div style="font-size:14px;font-weight:700;color:var(--primary, #AC1830);"><?php echo number_format($pop['min_price']); ?>원~</div>
    </a>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="empty-state" style="text-align:center;padding:60px 20px;color:var(--muted, #888);">
    <i class="bi bi-tags" style="font-size:48px;display:block;margin-bottom:12px;"></i>
    <p>등록된 <?php echo term('service', '시술'); ?> 카테고리가 없습니다</p>
</div>
<?php endif; ?>
