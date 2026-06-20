<?php
/**
 * treatment_select_ui.php -- 후기 작성 폼용 시술 선택 컴포넌트
 *
 * 필수 변수:
 *   $connect     (mysqli) -- DB 연결
 *   $hospital_id (int)    -- 선택된 병원 ID (0이면 미선택 메시지)
 *
 * 출력: 카테고리별 체크박스 그룹 + hidden treatmentsText 필드
 * AJAX: 병원 드롭다운 변경 시 동적 리로드
 *
 * @created 2026-03-25
 */

if (!isset($connect) || !($connect instanceof mysqli)) {
    return;
}

$_tsu_hospital_id = intval($hospital_id ?? 0);
$_tsu_selected_ids = [];
if (isset($_POST['treatment_ids']) && is_array($_POST['treatment_ids'])) {
    $_tsu_selected_ids = array_map('intval', $_POST['treatment_ids']);
}

// 병원이 선택된 경우 시술 목록 조회
$_tsu_treatments = [];
if ($_tsu_hospital_id > 0) {
    $_tsu_sql = "
        SELECT
            tm.id AS treatment_id,
            tm.name AS treatment_name,
            tm.hashtag,
            tc.id AS category_id,
            tc.name AS category_name,
            tc.sort_order AS cat_sort
        FROM gangnamunni_hospital_treatments ht
        INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE ht.hospital_id = ?
          AND ht.status = 'active'
          AND tm.is_active = 1
        ORDER BY tc.sort_order ASC, tm.name ASC
    ";
    $_tsu_stmt = mysqli_prepare($connect, $_tsu_sql);
    if ($_tsu_stmt) {
        mysqli_stmt_bind_param($_tsu_stmt, 'i', $_tsu_hospital_id);
        mysqli_stmt_execute($_tsu_stmt);
        $_tsu_result = mysqli_stmt_get_result($_tsu_stmt);
        if ($_tsu_result) {
            while ($_tsu_row = mysqli_fetch_assoc($_tsu_result)) {
                $_tsu_treatments[] = $_tsu_row;
            }
        }
        mysqli_stmt_close($_tsu_stmt);
    }
}

// 카테고리별 그룹핑
$_tsu_grouped = [];
foreach ($_tsu_treatments as $_tsu_t) {
    $cat_name = $_tsu_t['category_name'] ?: '기타';
    if (!isset($_tsu_grouped[$cat_name])) {
        $_tsu_grouped[$cat_name] = [];
    }
    $_tsu_grouped[$cat_name][] = $_tsu_t;
}

// 고유 ID (같은 페이지 다중 include 대응)
$_tsu_uid = 'tsu_' . mt_rand(10000, 99999);
?>

<style>
.tsu-wrap { margin: 0; }
.tsu-placeholder {
    padding: 14px 16px;
    background: #f8f9fa;
    border: 1px dashed #ccc;
    border-radius: 8px;
    color: #888;
    font-size: 13px;
    text-align: center;
}
.tsu-empty {
    padding: 14px 16px;
    background: #fff8f0;
    border: 1px dashed #f0ad4e;
    border-radius: 8px;
    color: #856404;
    font-size: 13px;
    text-align: center;
}
.tsu-cat-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin: 10px 0 6px;
    padding-left: 2px;
}
.tsu-cat-label:first-child { margin-top: 0; }
.tsu-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0;
    padding: 0;
}
.tsu-chip-label {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
}
.tsu-chip-label input[type="checkbox"] {
    display: none;
}
.tsu-chip-text {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    background: var(--bs-tertiary-bg, #f5f5f5);
    color: #444;
    font-size: 13px;
    font-weight: 500;
    border: 1.5px solid var(--bs-border-color, #e0e0e0);
    transition: all 0.15s ease;
    white-space: nowrap;
    line-height: 1.4;
}
.tsu-chip-label input[type="checkbox"]:checked + .tsu-chip-text {
    background: var(--primary-color, #AE1932);
    color: #fff;
    border-color: var(--primary-color, #AE1932);
}
.tsu-chip-label:hover .tsu-chip-text {
    border-color: var(--primary-color, #AE1932);
}
.tsu-loading {
    text-align: center;
    padding: 20px;
    color: #999;
    font-size: 13px;
}
.tsu-loading .tsu-spinner {
    display: inline-block;
    width: 18px;
    height: 18px;
    border: 2px solid #ddd;
    border-top-color: var(--primary-color, #AE1932);
    border-radius: 50%;
    animation: tsu-spin 0.6s linear infinite;
    vertical-align: middle;
    margin-right: 6px;
}
@keyframes tsu-spin { to { transform: rotate(360deg); } }

/* 다크모드 */
[data-bs-theme="dark"] .tsu-placeholder { background: #1e1e1e; border-color: #444; color: #999; }
[data-bs-theme="dark"] .tsu-empty { background: #2a2210; border-color: #665520; color: #d4a017; }
[data-bs-theme="dark"] .tsu-cat-label { color: #999; }
[data-bs-theme="dark"] .tsu-chip-text { background: #2a2a2a; color: #ccc; border-color: #444; }
[data-bs-theme="dark"] .tsu-chip-label input[type="checkbox"]:checked + .tsu-chip-text {
    background: var(--primary-color, #AE1932); color: #fff;
}

/* 모바일 */
@media (max-width: 576px) {
    .tsu-chips { gap: 6px; }
    .tsu-chip-text { padding: 5px 11px; font-size: 12px; }
    .tsu-cat-label { font-size: 11px; }
}
</style>

<div class="tsu-wrap" id="<?php echo $_tsu_uid; ?>_wrap">
    <input type="hidden" name="treatments" id="<?php echo $_tsu_uid; ?>_treatmentsText" value="<?php echo htmlspecialchars($_POST['treatments'] ?? ''); ?>">

    <div id="<?php echo $_tsu_uid; ?>_content">
    <?php if ($_tsu_hospital_id <= 0): ?>
        <div class="tsu-placeholder">
            병원을 먼저 선택하세요
        </div>
    <?php elseif (empty($_tsu_treatments)): ?>
        <div class="tsu-empty">
            이 병원에 등록된 시술이 없습니다
        </div>
    <?php else: ?>
        <?php foreach ($_tsu_grouped as $cat_name => $items): ?>
        <div class="tsu-cat-label"><?php echo htmlspecialchars($cat_name); ?></div>
        <div class="tsu-chips">
            <?php foreach ($items as $t):
                $tid = intval($t['treatment_id']);
                $hashtag = $t['hashtag'] ?: ('#' . str_replace(' ', '', $t['treatment_name']));
                $checked = in_array($tid, $_tsu_selected_ids) ? ' checked' : '';
            ?>
            <label class="tsu-chip-label">
                <input type="checkbox" name="treatment_ids[]" value="<?php echo $tid; ?>"
                       data-hashtag="<?php echo htmlspecialchars($hashtag); ?>"
                       onchange="tsuSync_<?php echo $_tsu_uid; ?>()"<?php echo $checked; ?>>
                <span class="tsu-chip-text"><?php echo htmlspecialchars($hashtag); ?></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<script>
(function(){
    var uid = '<?php echo $_tsu_uid; ?>';
    var contentEl = document.getElementById(uid + '_content');
    var hiddenEl = document.getElementById(uid + '_treatmentsText');

    // 체크박스 변경 시 hidden 필드 동기화
    window['tsuSync_' + uid] = function() {
        var boxes = contentEl.querySelectorAll('input[type="checkbox"][name="treatment_ids[]"]:checked');
        var tags = [];
        for (var i = 0; i < boxes.length; i++) {
            tags.push(boxes[i].getAttribute('data-hashtag'));
        }
        hiddenEl.value = tags.join(',');
    };

    // 초기 동기화 (pre-check된 항목이 있을 수 있음)
    window['tsuSync_' + uid]();

    // 병원 드롭다운 변경 감지
    var hospitalSelect = document.querySelector('select[name="hospital_id"]');
    if (hospitalSelect) {
        hospitalSelect.addEventListener('change', function() {
            var hid = parseInt(this.value) || 0;
            loadTreatments(hid);
        });
    }

    function loadTreatments(hospitalId) {
        if (hospitalId <= 0) {
            contentEl.innerHTML = '<div class="tsu-placeholder">병원을 먼저 선택하세요</div>';
            hiddenEl.value = '';
            return;
        }

        contentEl.innerHTML = '<div class="tsu-loading"><span class="tsu-spinner"></span> 시술 목록 로딩중...</div>';

        // AJAX: 인라인 PHP 엔드포인트 사용
        var url = 'api_treatments.php?action=hospital&id=' + hospitalId;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    renderTreatments(data);
                } catch(e) {
                    contentEl.innerHTML = '<div class="tsu-empty">시술 목록을 불러올 수 없습니다</div>';
                }
            } else {
                contentEl.innerHTML = '<div class="tsu-empty">시술 목록을 불러올 수 없습니다</div>';
            }
        };
        xhr.send();
    }

    function renderTreatments(data) {
        if (!data || !data.treatments || data.treatments.length === 0) {
            contentEl.innerHTML = '<div class="tsu-empty">이 병원에 등록된 시술이 없습니다</div>';
            hiddenEl.value = '';
            return;
        }

        // 카테고리별 그룹핑
        var grouped = {};
        var catOrder = [];
        for (var i = 0; i < data.treatments.length; i++) {
            var t = data.treatments[i];
            var cat = t.category_name || '기타';
            if (!grouped[cat]) {
                grouped[cat] = [];
                catOrder.push(cat);
            }
            grouped[cat].push(t);
        }

        var html = '';
        for (var c = 0; c < catOrder.length; c++) {
            var catName = catOrder[c];
            var items = grouped[catName];
            html += '<div class="tsu-cat-label">' + escHtml(catName) + '</div>';
            html += '<div class="tsu-chips">';
            for (var j = 0; j < items.length; j++) {
                var item = items[j];
                var hashtag = item.hashtag || ('#' + item.treatment_name.replace(/\s/g, ''));
                html += '<label class="tsu-chip-label">';
                html += '<input type="checkbox" name="treatment_ids[]" value="' + item.treatment_id + '"';
                html += ' data-hashtag="' + escHtml(hashtag) + '"';
                html += ' onchange="tsuSync_' + uid + '()">';
                html += '<span class="tsu-chip-text">' + escHtml(hashtag) + '</span>';
                html += '</label>';
            }
            html += '</div>';
        }

        contentEl.innerHTML = html;
        hiddenEl.value = '';
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
})();
</script>
