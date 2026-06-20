<?php
/**
 * 비포/애프터 갤러리 컴포넌트
 * detail.php에서 include 후 renderBeforeAfterGallery($hospital_id) 호출
 */

function renderBeforeAfterGallery($hospital_id, $connect, $treatment_id = null) {
    // DB에서 비포/애프터 조회
    $items = [];
    if ($treatment_id) {
        $stmt = mysqli_prepare($connect, "SELECT ba.*, tm.name AS master_name, tm.hashtag FROM gangnamunni_before_after ba LEFT JOIN gangnamunni_treatment_master tm ON ba.treatment_id = tm.id WHERE ba.hospital_id=? AND ba.treatment_id=? AND ba.is_active=1 ORDER BY ba.created_at DESC LIMIT 10");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $hospital_id, $treatment_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) $items[] = $row;
            mysqli_stmt_close($stmt);
        }
    } else {
        $stmt = mysqli_prepare($connect, "SELECT ba.*, tm.name AS master_name, tm.hashtag FROM gangnamunni_before_after ba LEFT JOIN gangnamunni_treatment_master tm ON ba.treatment_id = tm.id WHERE ba.hospital_id=? AND ba.is_active=1 ORDER BY ba.created_at DESC LIMIT 10");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) $items[] = $row;
            mysqli_stmt_close($stmt);
        }
    }

    // 데모 데이터 (DB에 없으면)
    if (empty($items)) {
        $items = [
            ['id' => 'demo1', 'treatment_name' => '눈성형', 'before_image_url' => '', 'after_image_url' => '', 'duration_text' => '3개월 후', 'description' => '자연스러운 쌍꺼풀 수술', 'is_verified' => 1],
            ['id' => 'demo2', 'treatment_name' => '코성형', 'before_image_url' => '', 'after_image_url' => '', 'duration_text' => '6개월 후', 'description' => '코끝 교정', 'is_verified' => 0],
            ['id' => 'demo3', 'treatment_name' => '피부', 'before_image_url' => '', 'after_image_url' => '', 'duration_text' => '1개월 후', 'description' => '레이저 토닝 5회', 'is_verified' => 1],
        ];
    }

    if (empty($items)) return;
    ?>
<div class="ba-section" style="padding:20px 16px; border-top:8px solid var(--bs-tertiary-bg,#f0f0f0);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="font-size:18px;font-weight:700;margin:0;">비포 / 애프터</h3>
        <span style="font-size:13px;color:var(--bs-secondary,#888);"><?php echo count($items); ?>건</span>
    </div>

    <div class="ba-scroll" style="display:flex;gap:12px;overflow-x:auto;-webkit-overflow-scrolling:touch;padding-bottom:8px;scroll-snap-type:x mandatory;">
    <?php foreach ($items as $item): ?>
        <div class="ba-card" style="flex-shrink:0;width:280px;border-radius:12px;overflow:hidden;border:1px solid var(--bs-border-color,#eee);scroll-snap-align:start;">
            <div style="display:flex;height:180px;">
                <div style="flex:1;position:relative;background:var(--bs-tertiary-bg,#f5f5f5);">
                    <?php if (!empty($item['before_image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($item['before_image_url']); ?>" alt="Before" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:14px;color:#999;">Before</div>
                    <?php endif; ?>
                    <span style="position:absolute;top:8px;left:8px;background:rgba(0,0,0,0.6);color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">BEFORE</span>
                </div>
                <div style="width:2px;background:var(--bs-primary,#AE1932);position:relative;">
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:24px;height:24px;background:var(--bs-primary,#AE1932);border-radius:50%;display:flex;align-items:center;justify-content:center;z-index:1;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#fff"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </div>
                </div>
                <div style="flex:1;position:relative;background:var(--bs-tertiary-bg,#f5f5f5);">
                    <?php if (!empty($item['after_image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($item['after_image_url']); ?>" alt="After" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:14px;color:#999;">After</div>
                    <?php endif; ?>
                    <span style="position:absolute;top:8px;right:8px;background:var(--bs-primary,#AE1932);color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;">AFTER</span>
                </div>
            </div>
            <div style="padding:10px 12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:13px;font-weight:600;"><?php echo htmlspecialchars($item['treatment_name'] ?? ''); ?></span>
                    <?php if (!empty($item['duration_text'])): ?>
                    <span style="font-size:12px;color:var(--bs-secondary,#888);"><?php echo htmlspecialchars($item['duration_text']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($item['description'])): ?>
                <p style="font-size:12px;color:var(--bs-secondary,#888);margin:4px 0 0;"><?php echo htmlspecialchars(mb_substr($item['description'], 0, 50)); ?></p>
                <?php endif; ?>
                <?php if (!empty($item['is_verified'])): ?>
                <span style="display:inline-block;margin-top:4px;padding:2px 6px;background:#dcfce7;color:#15803d;border-radius:4px;font-size:10px;font-weight:600;">인증됨</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<style>
.ba-scroll::-webkit-scrollbar { height: 4px; }
.ba-scroll::-webkit-scrollbar-thumb { background: var(--bs-secondary-bg, #ccc); border-radius: 2px; }
</style>
    <?php
}
