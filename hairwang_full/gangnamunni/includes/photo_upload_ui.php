<?php
/**
 * 사진 첨부 UI 공용 컴포넌트 (프론트엔드)
 * - CSS + HTML + JavaScript 통합 출력
 * - booking.php, consultation.php, review_write.php, detail.php에서 include
 *
 * 사용법:
 *   $photo_config = [
 *       'prefix'     => 'booking',     // HTML ID 접두사 (고유해야 함)
 *       'max_photos' => 3,             // 최대 사진 수 (기본 3)
 *       'label'      => '📷 사진 첨부 (선택, 최대 3장)',
 *       'consent_text' => '...',       // 동의 문구 (선택)
 *   ];
 *   include __DIR__ . '/includes/photo_upload_ui.php';
 */

// 기본값 설정
$prefix = $photo_config['prefix'] ?? 'photo';
$max_photos = intval($photo_config['max_photos'] ?? 3);
$label = $photo_config['label'] ?? "📷 사진 첨부 (선택, 최대 {$max_photos}장)";
$consent_text = $photo_config['consent_text'] ?? '상담 참고용 사진을 첨부합니다. 제공된 사진은 상담 목적으로만 사용되며, 상담 완료 후 30일 이내에 삭제됩니다.';
$show_medical_notice = $photo_config['show_medical_notice'] ?? false;
?>

<!-- 사진 첨부 CSS (중복 include 방지) -->
<?php if (empty($GLOBALS['_photo_upload_css_loaded'])): $GLOBALS['_photo_upload_css_loaded'] = true; ?>
<style>
.pu-section { margin-bottom: 14px; }
.pu-label { font-size: 13px; color: var(--color-text-secondary, #666); margin-bottom: 8px; display: block; font-weight: 600; }
.pu-grid { display: flex; gap: 8px; flex-wrap: wrap; }
.pu-slot {
    width: 72px; height: 72px;
    border: 2px dashed var(--color-border, #ddd);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; position: relative; overflow: hidden;
    background: var(--color-bg-light, #f8f9fa);
    flex-shrink: 0;
}
.pu-slot.filled { border-style: solid; border-color: var(--color-primary, #AE1932); }
.pu-slot img { width: 100%; height: 100%; object-fit: cover; }
.pu-slot .pu-add { font-size: 24px; color: var(--color-text-secondary, #999); }
.pu-slot .pu-remove {
    position: absolute; top: -2px; right: -2px;
    width: 20px; height: 20px; background: #dc3545; color: #fff;
    border: none; border-radius: 50%; font-size: 12px;
    line-height: 20px; text-align: center; cursor: pointer;
    padding: 0; z-index: 2;
}
.pu-consent {
    display: flex; align-items: flex-start; gap: 8px;
    margin-bottom: 14px; font-size: 12px;
    color: var(--color-text-secondary, #666); line-height: 1.4;
}
.pu-consent input[type="checkbox"] { margin-top: 2px; flex-shrink: 0; }
.pu-medical-notice {
    font-size: 11px; color: #dc3545; margin-top: 6px;
    padding: 6px 8px; background: #fff5f5; border-radius: 4px;
}
</style>
<?php endif; ?>

<!-- 사진 첨부 HTML -->
<div class="pu-section" id="<?php echo $prefix; ?>_section">
    <span class="pu-label"><?php echo htmlspecialchars($label); ?></span>
    <input type="file" id="<?php echo $prefix; ?>_input" accept="image/*" multiple style="display:none;"
           onchange="puHandleSelect('<?php echo $prefix; ?>', this)">
    <div class="pu-grid" id="<?php echo $prefix; ?>_grid">
        <div class="pu-slot" onclick="document.getElementById('<?php echo $prefix; ?>_input').click()">
            <span class="pu-add">+</span>
        </div>
    </div>
    <?php if ($show_medical_notice): ?>
    <div class="pu-medical-notice">⚠️ 의료법에 따라 사진은 상담 목적으로만 사용되며, 제3자에게 제공되지 않습니다.</div>
    <?php endif; ?>
</div>

<!-- 동의 체크박스 (사진 첨부 시 표시) -->
<div class="pu-consent" id="<?php echo $prefix; ?>_consent" style="display:none;">
    <input type="checkbox" name="photo_consent" id="<?php echo $prefix; ?>_consent_cb" value="1">
    <label for="<?php echo $prefix; ?>_consent_cb"><?php echo htmlspecialchars($consent_text); ?></label>
</div>

<!-- 사진 첨부 JS (중복 include 방지) -->
<?php if (empty($GLOBALS['_photo_upload_js_loaded'])): $GLOBALS['_photo_upload_js_loaded'] = true; ?>
<script>
// 사진 첨부 공용 모듈
(function() {
    window._puData = {}; // { prefix: [{blob, dataUrl, name}, ...] }
    var MAX_SIZE = 5 * 1024 * 1024; // 5MB
    var MAX_DIM = 1280;

    window.puGetConfig = function(prefix) {
        var section = document.getElementById(prefix + '_section');
        return {
            maxPhotos: parseInt(section?.dataset.maxPhotos || '3', 10)
        };
    };

    window.puHandleSelect = function(prefix, input) {
        if (!window._puData[prefix]) window._puData[prefix] = [];
        var data = window._puData[prefix];
        var maxPhotos = parseInt(document.getElementById(prefix + '_section')?.dataset.maxPhotos || '3', 10);
        var files = Array.from(input.files);

        for (var i = 0; i < files.length; i++) {
            if (data.length >= maxPhotos) {
                alert('최대 ' + maxPhotos + '장까지 첨부 가능합니다.');
                break;
            }
            if (!files[i].type.startsWith('image/')) continue;
            if (files[i].size > MAX_SIZE) {
                alert(files[i].name + ': 5MB 이하 파일만 가능합니다.');
                continue;
            }
            puResizeAndAdd(prefix, files[i]);
        }
        input.value = '';
    };

    window.puResizeAndAdd = function(prefix, file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = new Image();
            img.onload = function() {
                var canvas = document.createElement('canvas');
                var w = img.width, h = img.height;
                if (w > MAX_DIM || h > MAX_DIM) {
                    if (w > h) { h = Math.round(h * MAX_DIM / w); w = MAX_DIM; }
                    else { w = Math.round(w * MAX_DIM / h); h = MAX_DIM; }
                }
                canvas.width = w;
                canvas.height = h;
                canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                canvas.toBlob(function(blob) {
                    if (!window._puData[prefix]) window._puData[prefix] = [];
                    var dataUrl = URL.createObjectURL(blob);
                    window._puData[prefix].push({ blob: blob, dataUrl: dataUrl, name: file.name });
                    puRenderGrid(prefix);
                }, 'image/jpeg', 0.85);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    };

    window.puRemovePhoto = function(prefix, idx) {
        var data = window._puData[prefix] || [];
        if (data[idx]) URL.revokeObjectURL(data[idx].dataUrl);
        data.splice(idx, 1);
        puRenderGrid(prefix);
    };

    window.puRenderGrid = function(prefix) {
        var data = window._puData[prefix] || [];
        var maxPhotos = parseInt(document.getElementById(prefix + '_section')?.dataset.maxPhotos || '3', 10);
        var grid = document.getElementById(prefix + '_grid');
        if (!grid) return;

        grid.innerHTML = '';
        data.forEach(function(p, i) {
            var slot = document.createElement('div');
            slot.className = 'pu-slot filled';
            slot.innerHTML = '<img src="' + p.dataUrl + '" alt="photo">'
                + '<button type="button" class="pu-remove" onclick="puRemovePhoto(\'' + prefix + '\',' + i + ')">×</button>';
            grid.appendChild(slot);
        });

        if (data.length < maxPhotos) {
            var addSlot = document.createElement('div');
            addSlot.className = 'pu-slot';
            addSlot.innerHTML = '<span class="pu-add">+</span>';
            addSlot.onclick = function() { document.getElementById(prefix + '_input').click(); };
            grid.appendChild(addSlot);
        }

        // 동의 체크박스 표시/숨김
        var consent = document.getElementById(prefix + '_consent');
        if (consent) {
            consent.style.display = data.length > 0 ? 'flex' : 'none';
            if (data.length === 0) {
                var cb = document.getElementById(prefix + '_consent_cb');
                if (cb) cb.checked = false;
            }
        }
    };

    /**
     * FormData에 사진 blob 추가 (form submit 시 호출)
     * @param {string} prefix
     * @param {FormData} formData
     * @returns {boolean} 동의 체크 통과 여부
     */
    window.puAppendToFormData = function(prefix, formData) {
        var data = window._puData[prefix] || [];
        if (data.length > 0) {
            var cb = document.getElementById(prefix + '_consent_cb');
            if (cb && !cb.checked) {
                alert('사진 첨부 시 동의가 필요합니다.');
                return false;
            }
            data.forEach(function(p, i) {
                formData.append('photos[]', p.blob, 'photo_' + i + '.jpg');
            });
            formData.set('photo_consent', '1');
        }
        return true;
    };

    /**
     * 사진 초기화 (폼 리셋 시)
     */
    window.puReset = function(prefix) {
        var data = window._puData[prefix] || [];
        data.forEach(function(p) { URL.revokeObjectURL(p.dataUrl); });
        window._puData[prefix] = [];
        puRenderGrid(prefix);
    };
})();
</script>
<?php endif; ?>

<!-- maxPhotos 데이터 속성 설정 -->
<script>
document.getElementById('<?php echo $prefix; ?>_section').dataset.maxPhotos = '<?php echo $max_photos; ?>';
</script>
