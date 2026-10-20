<?php /** @var mysqli $connect */ /** @var string $hospital_return_url */ /** @var int $effective_treatment_id */ /** @var string $keyword */ /** @var string $effective_treatment */ require_once __DIR__ . '/ad_slot_performance.php'; /* adSlotIssueToken() */ ?>
    <?php if (!empty($hospitals)): ?>
<?php
$_infeed_enabled = getSettingValue($connect, 'ad_show_hospitals_infeed', '1');
$_infeed_interval = (int)getSettingValue($connect, 'ad_infeed_interval', '4');
$_infeed_ads = [];
if ($_infeed_enabled === '1') {
    $_iq = mysqli_query($connect, "SELECT a.*, h.id as hospital_id
        FROM gangnamunni_ads a
        LEFT JOIN gangnamunni_advertisers adv ON a.advertiser_id = adv.id
        LEFT JOIN gangnamunni_hospitals h ON h.name = adv.hospital_name
        WHERE a.status = 'approved' AND a.ad_type = 'infeed'
          AND EXISTS (
              SELECT 1 FROM gangnamunni_ad_slot_bookings b
              WHERE b.advertiser_id = a.advertiser_id
                AND b.status = 'active'
                AND b.start_date <= CURDATE()
                AND (b.end_date IS NULL OR b.end_date >= CURDATE())
          )
        ORDER BY a.display_order ASC LIMIT 10");
    if ($_iq) { while ($_ir = mysqli_fetch_assoc($_iq)) $_infeed_ads[] = $_ir; }
}
$_ad_idx = 0;
?>
    <div class="hospital-grid list-view<?php echo ($keyword !== '' || !empty($effective_treatment)) ? ' search-mode' : ''; ?>" id="hospital-grid">
<?php
$__hi = 0;
foreach ($hospitals as $hospital):
    if ($__hi > 0 && $_infeed_enabled === '1' && $_infeed_interval > 0 && $__hi % $_infeed_interval === 0 && isset($_infeed_ads[$_ad_idx])):
        $_iad = $_infeed_ads[$_ad_idx];
?>
        <a class="hospital-card ad-card" href="<?php echo !empty($_iad['hospital_id']) ? 'detail.php?id=' . (int)$_iad['hospital_id'] . '&amp;return_url=' . rawurlencode($hospital_return_url) : htmlspecialchars($_iad['link_url'] ?? '#'); ?>" data-ad-id="<?php echo $_iad['id']; ?>">
                <?php if (!empty($_iad['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($_iad['image_url']); ?>" class="hospital-image" alt="광고" loading="lazy" decoding="async">
                <?php else: ?>
                <div class="hospital-image" style="background:#D9D9D9;"></div>
                <?php endif; ?>
                <span class="grade-badge grade-badge-sa">AD</span>
                <div class="hospital-info">
                    <div class="hospital-name"><?php echo htmlspecialchars($_iad['title']); ?></div>
                    <?php $_iad_addr = $_iad['subtitle'] ?? ''; $_iad_dist = extract_addr_district($_iad_addr); ?>
                    <div class="hospital-address">
                        <?php if ($_iad_dist !== ''): ?>
                        <span class="addr-district"><?php echo htmlspecialchars($_iad_dist); ?></span>
                        <span class="addr-sep"> | </span>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars(mb_substr($_iad_addr, 0, 15, 'UTF-8')); ?></span>
                    </div>
                </div>
        </a>
<?php
        $_ad_idx++;
    endif;
    $__hi++;

    $_detail_url = 'detail.php?id=' . (int)$hospital['id']
        . '&return_url=' . rawurlencode($hospital_return_url);
    if (!empty($effective_treatment) && $effective_treatment_id > 0) {
        $_detail_url .= '&treatment_id=' . $effective_treatment_id . '&treatment=' . urlencode($effective_treatment);
    }
    $_slot_context = $slot_hospital_context[(int)$hospital['id']] ?? null;
?>
        <div class="hospital-card"
             onclick="location.href='<?php echo htmlspecialchars($_detail_url); ?>'"
             style="cursor:pointer;"
             <?php if ($_slot_context): ?>
             data-ad-slot-booking="<?php echo (int) $_slot_context['booking_id']; ?>"
             data-ad-slot-token="<?php echo htmlspecialchars(adSlotIssueToken((int) $_slot_context['booking_id']), ENT_QUOTES, 'UTF-8'); ?>"
             data-ad-slot-keyword="<?php echo htmlspecialchars($keyword !== '' ? $keyword : $effective_treatment, ENT_QUOTES, 'UTF-8'); ?>"
             data-ad-slot-rank="<?php echo (int) $_slot_context['rank_position']; ?>"
             data-target-url="<?php echo htmlspecialchars($_detail_url, ENT_QUOTES, 'UTF-8'); ?>"
             <?php endif; ?>>
            <div class="card-wishlist">
                <?php renderWishlistButton('hospital', (int)$hospital['id'], 'sm', 'star'); ?>
            </div>
            <div class="card-link">
                <?php
                $h_img = $hospital_main_images[(int)$hospital['id']]
                         ?? $hospital['main_image_url']
                         ?? $hospital_logos[(int)$hospital['id']]
                         ?? $hospital['profile_image_url'] ?? '';
                $placeholder = 'assets/images/seoul_beauty_hospital_placeholder.jpeg';
                $img_src = !empty($h_img) ? htmlspecialchars($h_img) : $placeholder;
                ?>
                <img src="<?php echo $img_src; ?>"
                     alt="<?php echo htmlspecialchars($hospital['name']); ?>"
                     class="hospital-image"
                     loading="<?php echo $__hi <= 4 ? 'eager' : 'lazy'; ?>"
                     decoding="async"
                     <?php echo $__hi <= 2 ? 'fetchpriority="high"' : ''; ?>
                     onerror="this.onerror=null;this.src='<?php echo $placeholder; ?>'">
                <div class="hospital-info">
                    <div class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></div>
                    <?php $_a=($hospital['address']??''); $_dist = extract_addr_district($_a); ?>
                    <div class="hospital-address">
                        <?php if ($_dist !== ''): ?>
                        <span class="addr-district"><?php echo htmlspecialchars($_dist); ?></span>
                        <span class="addr-sep"> | </span>
                        <?php endif; ?>
                        <span class="addr-full"><?php echo htmlspecialchars($_a); ?></span>
                        <span class="addr-short"><?php $_a=($hospital['address']??''); echo htmlspecialchars($_a); ?></span>
                    </div>
                    <?php
                    $_td = $hospital_treatment_descs[(int)$hospital['id']] ?? null;
                    $_tag_name = $_td['name'] ?? null;
                    $_price = !empty($_td['price_min']) && (int)$_td['price_min'] > 0 ? (int)$_td['price_min'] : 0;
                    $_price_max = !empty($_td['price_max']) && (int)$_td['price_max'] > 0 ? (int)$_td['price_max'] : 0;
                    $_price_won = $_price;
                    $_price_max_won = $_price_max;
                    $_dosage_label = '';
                    if ($_td && (float)($_td['dosage_value'] ?? 0) > 0 && !empty($_td['dosage_unit'])) {
                        $_dosage_label = ' · ' . rtrim(rtrim(number_format((float)$_td['dosage_value'], 2), '0'), '.') . $_td['dosage_unit'];
                    }
                    $_is_search_row = ($keyword !== '' || !empty($effective_treatment));
                    $_per_unit = 0; $_per_unit_label = '';
                    if ($_td && $_price > 0 && (float)($_td['dosage_value'] ?? 0) > 0 && !empty($_td['dosage_unit']) && trim($_td['dosage_unit']) !== '회') {
                        $_per_unit = (int)round($_price / (float)$_td['dosage_value']);
                        $_per_unit_label = '1' . $_td['dosage_unit'] . '당 ' . number_format($_per_unit) . '원';
                    }
                    $_treat_line = trim(($_tag_name ?? '') . ($_dosage_label !== '' ? ' ' . trim(str_replace('·', '', $_dosage_label)) : ''));
                    if ($_tag_name || $_price > 0):
                    ?>
                    <div class="card-tag-price">
                        <?php if ($_treat_line !== ''): ?>
                        <div class="card-tag-item"><?php echo htmlspecialchars($_treat_line); ?></div>
                        <?php endif; ?>
                        <?php if ($_price > 0): ?>
                        <?php if (!$_is_search_row && $_price_max > 0): ?>
                        <div class="card-vat">VAT 포함&nbsp;&nbsp;<?php echo number_format($_price_max_won); ?>원</div>
                        <?php endif; ?>
                        <div class="card-price">
                            <span class="card-price-sale"><span class="card-price-num"><?php echo number_format($_price_won); ?></span><span class="card-price-unit">원</span></span>
                            <?php if ($_is_search_row && $_per_unit_label !== ''): ?>
                            <span class="card-price-per"><?php echo htmlspecialchars($_per_unit_label); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php endforeach; ?>
    </div>
    <?php else: ?>
    <?php
    if (!function_exists('sr_josa_i_ga')) {
        function sr_josa_i_ga($w) {
            $w = trim((string)$w); if ($w === '') return '';
            $ch = mb_substr($w, -1, 1, 'UTF-8'); $cp = mb_ord($ch, 'UTF-8');
            if ($cp >= 0xAC00 && $cp <= 0xD7A3) return (($cp - 0xAC00) % 28 !== 0) ? '이' : '가';
            return '이(가)';
        }
    }
    $_sel_regions = array_filter(array_map('trim', explode(',', (string)($_GET['region'] ?? ''))));
    $_sel_dosage = trim((string)($_GET['dosage'] ?? ''));
    $_cond = [];
    if ($_sel_regions) $_cond[] = implode(', ', $_sel_regions);
    if ($_sel_dosage !== '') $_cond[] = $_sel_dosage . ($_sel_dosage === '4cc' ? ' 이상' : '');
    $_has_cond = !empty($_cond) && !empty($effective_treatment_id); /* 03-11: 시술은 있는데 조건(지역·용량)만 안 맞음. 시술 자체가 없으면 03-14 */
    $_sugg = ['텐써마','울쎄라','슈링크','인모드'];
    ?>
    <div class="sr-empty<?php echo ($_has_cond && $keyword !== '') ? '' : ' sr-none-flag'; ?>">
        <div class="sr-empty-ico"><svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#AC1830" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg></div>
        <?php if ($_has_cond && $keyword !== ''): ?>
        <div class="sr-empty-title">조건에 맞는 병원이 없어요</div>
        <div class="sr-empty-sub">'<?php echo htmlspecialchars(implode(' · ', $_cond)); ?>' 조건의 <?php echo htmlspecialchars($keyword) . sr_josa_i_ga($keyword); ?> 없어요<br>지역을 넓히거나 용량을 바꿔보세요</div>
        <button type="button" class="sr-empty-btn" onclick="srResetFilters()">필터 초기화</button>
        <?php else: ?>
        <div class="sr-empty-title"><?php echo $keyword !== '' ? "'" . htmlspecialchars($keyword) . "' 검색 결과가 없어요" : '검색 결과가 없어요'; ?></div>
        <div class="sr-empty-sub">철자를 확인하거나 다른 단어로 검색해보세요</div>
        <?php endif; ?>
    </div>
    <?php if (!($_has_cond && $keyword !== '')): ?>
    <div class="sr-sugg">
        <div class="sr-sugg-t">이런 시술은 어떠세요?</div>
        <div class="sr-sugg-chips">
            <?php foreach ($_sugg as $_sg): ?>
            <a class="sr-sugg-chip" href="hospitals.php?q=<?php echo urlencode($_sg); ?>"><?php echo htmlspecialchars($_sg); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
