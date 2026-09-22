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
    <div class="hospital-grid list-view" id="hospital-grid">
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
                <?php
                $ad_grade = $hospital['ad_grade'] ?? 'none';
                if ($_slot_context || in_array($ad_grade, ['S', 'A'])): ?>
                <span class="grade-badge grade-badge-sa">AD</span>
                <?php endif; ?>
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
                    if ($_tag_name || $_price > 0):
                    ?>
                    <div class="card-tag-price">
                        <?php if ($_tag_name): ?>
                        <span class="card-tag-item"><?php echo htmlspecialchars($_tag_name . $_dosage_label); ?></span>
                        <?php endif; ?>
                        <?php if ($_price > 0): ?>
                        <span class="card-price">
                            <?php if ($_price_max > 0): ?>
                            <span class="card-price-max"><?php echo number_format($_price_max_won); ?>원</span>
                            <?php endif; ?>
                            <span class="card-price-sale"><span class="card-price-num"><?php echo number_format($_price_won); ?></span><span class="card-price-unit">원</span></span>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="no-result">
        <div style="font-size:64px;margin-bottom:20px;">&#128269;</div>
        <div style="font-size:18px;margin-bottom:12px;">검색 결과가 없습니다</div>
        <div style="font-size:14px;">다른 검색어로 시도해보세요.</div>
    </div>
    <?php endif; ?>
