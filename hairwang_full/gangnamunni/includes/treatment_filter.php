<?php
/**
 * treatment_filter.php -- 시술 카테고리 탭 + 해시태그 칩 필터 (hospitals.php 용)
 *
 * 사용 전 필수:
 *   $connect (mysqli) -- DB 연결
 *
 * GET 파라미터:
 *   treatment_cat  -- 선택된 카테고리 ID (숫자)
 *   treatment_id   -- 선택된 시술 ID (숫자)
 *
 * 출력: 가로 스크롤 카테고리 탭 + 시술 해시태그 칩 필터 UI
 * hospitals.php SQL WHERE 절에서 사용할 변수:
 *   $_tf_selected_treatment_id  -- 선택된 시술 ID (int, 0이면 미선택)
 *   $_tf_selected_cat_id        -- 선택된 카테고리 ID (int, 0이면 전체)
 *   $_tf_hospital_ids_clause    -- SQL IN 절 문자열 (예: "IN (8093,8094)") 또는 빈 문자열
 *
 * CSS 접두사: tf- (collision 방지)
 *
 * @created 2026-03-25
 */
if (!isset($connect) || !($connect instanceof mysqli)) {
    return;
}

// --- GET 파라미터 파싱 ---
$_tf_selected_cat_id = isset($_GET['treatment_cat']) ? intval($_GET['treatment_cat']) : 0;
$_tf_selected_treatment_id = isset($_GET['treatment_id']) ? intval($_GET['treatment_id']) : 0;

// --- 카테고리 목록 조회 ---
$_tf_categories = [];
$_tf_cat_result = mysqli_query($connect,
    "SELECT id, name, slug, icon, sort_order
     FROM gangnamunni_treatment_categories
     WHERE is_active = 1
     ORDER BY sort_order ASC, id ASC"
);
if ($_tf_cat_result) {
    while ($_tf_c = mysqli_fetch_assoc($_tf_cat_result)) {
        $_tf_categories[] = $_tf_c;
    }
}

if (empty($_tf_categories)) {
    // 카테고리 데이터 없으면 출력 없음
    $_tf_hospital_ids_clause = '';
    return;
}

// --- 시술이 선택된 경우 해당 카테고리 자동 결정 ---
if ($_tf_selected_treatment_id > 0 && $_tf_selected_cat_id <= 0) {
    $_tf_auto_cat_stmt = mysqli_prepare($connect,
        "SELECT category_id FROM gangnamunni_treatment_master WHERE id = ? AND is_active = 1 LIMIT 1"
    );
    if ($_tf_auto_cat_stmt) {
        mysqli_stmt_bind_param($_tf_auto_cat_stmt, 'i', $_tf_selected_treatment_id);
        mysqli_stmt_execute($_tf_auto_cat_stmt);
        $_tf_auto_result = mysqli_stmt_get_result($_tf_auto_cat_stmt);
        if ($_tf_auto_row = mysqli_fetch_assoc($_tf_auto_result)) {
            $_tf_selected_cat_id = intval($_tf_auto_row['category_id']);
        }
        mysqli_stmt_close($_tf_auto_cat_stmt);
    }
}

// --- 선택된 카테고리의 시술 목록 조회 ---
$_tf_treatments = [];
$_tf_active_cat_id = $_tf_selected_cat_id;

if ($_tf_active_cat_id > 0) {
    $_tf_treat_stmt = mysqli_prepare($connect,
        "SELECT id, name, hashtag, popularity_score
         FROM gangnamunni_treatment_master
         WHERE category_id = ? AND is_active = 1
         ORDER BY popularity_score DESC, name ASC"
    );
    if ($_tf_treat_stmt) {
        mysqli_stmt_bind_param($_tf_treat_stmt, 'i', $_tf_active_cat_id);
        mysqli_stmt_execute($_tf_treat_stmt);
        $_tf_treat_result = mysqli_stmt_get_result($_tf_treat_stmt);
        if ($_tf_treat_result) {
            while ($_tf_t = mysqli_fetch_assoc($_tf_treat_result)) {
                $_tf_treatments[] = $_tf_t;
            }
        }
        mysqli_stmt_close($_tf_treat_stmt);
    }
}

// --- 선택된 시술에 해당하는 병원 ID 목록 (SQL WHERE 절용) ---
$_tf_hospital_ids_clause = '';
if ($_tf_selected_treatment_id > 0) {
    $_tf_hid_stmt = mysqli_prepare($connect,
        "SELECT DISTINCT hospital_id
         FROM gangnamunni_hospital_treatments
         WHERE treatment_id = ? AND status = 'active' AND is_active = 1"
    );
    if ($_tf_hid_stmt) {
        mysqli_stmt_bind_param($_tf_hid_stmt, 'i', $_tf_selected_treatment_id);
        mysqli_stmt_execute($_tf_hid_stmt);
        $_tf_hid_result = mysqli_stmt_get_result($_tf_hid_stmt);
        $_tf_hids = [];
        if ($_tf_hid_result) {
            while ($_tf_h = mysqli_fetch_assoc($_tf_hid_result)) {
                $_tf_hids[] = intval($_tf_h['hospital_id']);
            }
        }
        mysqli_stmt_close($_tf_hid_stmt);

        if (!empty($_tf_hids)) {
            $_tf_hospital_ids_clause = " AND id IN (" . implode(',', $_tf_hids) . ")";
        } else {
            // 해당 시술을 제공하는 병원 없음 -- 결과 0건 강제
            $_tf_hospital_ids_clause = " AND id = 0";
        }
    }
} elseif ($_tf_selected_cat_id > 0) {
    // 카테고리만 선택 -- 해당 카테고리 시술을 하나라도 제공하는 병원
    $_tf_cat_hid_stmt = mysqli_prepare($connect,
        "SELECT DISTINCT ht.hospital_id
         FROM gangnamunni_hospital_treatments ht
         INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
         WHERE tm.category_id = ? AND ht.status = 'active' AND ht.is_active = 1 AND tm.is_active = 1"
    );
    if ($_tf_cat_hid_stmt) {
        mysqli_stmt_bind_param($_tf_cat_hid_stmt, 'i', $_tf_selected_cat_id);
        mysqli_stmt_execute($_tf_cat_hid_stmt);
        $_tf_cat_hid_result = mysqli_stmt_get_result($_tf_cat_hid_stmt);
        $_tf_cat_hids = [];
        if ($_tf_cat_hid_result) {
            while ($_tf_ch = mysqli_fetch_assoc($_tf_cat_hid_result)) {
                $_tf_cat_hids[] = intval($_tf_ch['hospital_id']);
            }
        }
        mysqli_stmt_close($_tf_cat_hid_stmt);

        if (!empty($_tf_cat_hids)) {
            $_tf_hospital_ids_clause = " AND id IN (" . implode(',', $_tf_cat_hids) . ")";
        } else {
            $_tf_hospital_ids_clause = " AND id = 0";
        }
    }
}

// --- 선택된 시술 이름 (결과 헤더 표시용) ---
$_tf_selected_treatment_name = '';
if ($_tf_selected_treatment_id > 0) {
    foreach ($_tf_treatments as $_tf_t) {
        if (intval($_tf_t['id']) === $_tf_selected_treatment_id) {
            $_tf_selected_treatment_name = $_tf_t['name'];
            break;
        }
    }
    // 다른 카테고리 시술일 수 있으므로 직접 조회
    if (empty($_tf_selected_treatment_name)) {
        $_tf_name_stmt = mysqli_prepare($connect,
            "SELECT name FROM gangnamunni_treatment_master WHERE id = ? LIMIT 1"
        );
        if ($_tf_name_stmt) {
            mysqli_stmt_bind_param($_tf_name_stmt, 'i', $_tf_selected_treatment_id);
            mysqli_stmt_execute($_tf_name_stmt);
            $_tf_name_result = mysqli_stmt_get_result($_tf_name_stmt);
            if ($_tf_nr = mysqli_fetch_assoc($_tf_name_result)) {
                $_tf_selected_treatment_name = $_tf_nr['name'];
            }
            mysqli_stmt_close($_tf_name_stmt);
        }
    }
}

// --- URL 빌더 헬퍼 (HTML 출력 전에 정의) ---
if (!function_exists('_tf_build_url')) {
    /**
     * URL 빌더 -- 기존 GET 파라미터를 유지하면서 treatment_cat, treatment_id 변경
     *
     * @param int $cat_id  카테고리 ID (0이면 삭제)
     * @param int $tid     시술 ID (0이면 삭제)
     * @return string      URL 문자열
     */
    function _tf_build_url(int $cat_id, int $tid): string
    {
        $params = $_GET;

        if ($cat_id > 0) {
            $params['treatment_cat'] = $cat_id;
        } else {
            unset($params['treatment_cat']);
        }

        if ($tid > 0) {
            $params['treatment_id'] = $tid;
        } else {
            unset($params['treatment_id']);
        }

        $qs = http_build_query($params);
        return 'hospitals.php' . ($qs ? '?' . $qs : '');
    }
}

// --- 고유 ID (같은 페이지에 여러 번 include 가능) ---
$_tf_uid = 'tf_' . mt_rand(1000, 9999);
?>
<style>
/* ===== Treatment Filter Component (tf- prefix) ===== */
.tf-wrap {
    margin-bottom: 4px;
}

/* 카테고리 탭 가로 스크롤 */
.tf-cat-row {
    display: flex;
    gap: 8px;
    padding: 10px 16px;
    overflow-x: auto;
    white-space: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.tf-cat-row::-webkit-scrollbar {
    display: none;
}
.tf-cat-tab {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 18px;
    border-radius: 24px;
    border: 1.5px solid var(--bs-border-color, #ddd);
    background: var(--bs-body-bg, #fff);
    color: var(--bs-secondary-color, #666);
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    text-decoration: none;
    flex-shrink: 0;
    line-height: 1.4;
}
.tf-cat-tab:hover {
    border-color: var(--primary-color, #AC1830);
    color: var(--primary-color, #AC1830);
    text-decoration: none;
}
.tf-cat-tab.tf-active {
    background: var(--primary-color, #AC1830);
    color: #fff;
    border-color: var(--primary-color, #AC1830);
    font-weight: 600;
}
.tf-cat-tab.tf-active:hover {
    color: #fff;
}
.tf-cat-icon {
    font-size: 15px;
    line-height: 1;
}

/* 시술 해시태그 칩 영역 */
.tf-chips-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 16px 12px;
    animation: tfSlideDown 0.2s ease;
}
@keyframes tfSlideDown {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.tf-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 14px;
    border-radius: 20px;
    border: 1px solid var(--bs-border-color, #e0e0e0);
    background: var(--bs-body-bg, #fff);
    color: var(--bs-body-color, #333);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
    text-decoration: none;
    line-height: 1.4;
}
.tf-chip:hover {
    border-color: var(--primary-color, #AC1830);
    color: var(--primary-color, #AC1830);
    text-decoration: none;
}
.tf-chip.tf-active {
    background: var(--primary-color, #AC1830);
    color: #fff;
    border-color: var(--primary-color, #AC1830);
    font-weight: 600;
}
.tf-chip.tf-active:hover {
    color: #fff;
}
.tf-chip-hash {
    opacity: 0.7;
    font-weight: 400;
}

/* 선택된 시술 라벨 */
.tf-selected-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin: 0 16px 8px;
    padding: 6px 14px;
    border-radius: 20px;
    background: rgba(211, 47, 47, 0.08);
    color: var(--primary-color, #AC1830);
    font-size: 13px;
    font-weight: 600;
}
.tf-selected-label .tf-clear {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--primary-color, #AC1830);
    color: #fff;
    font-size: 12px;
    line-height: 1;
    cursor: pointer;
    text-decoration: none;
    transition: opacity 0.15s;
}
.tf-selected-label .tf-clear:hover {
    opacity: 0.8;
    text-decoration: none;
}

/* 다크모드 대응 */
[data-bs-theme="dark"] .tf-cat-tab {
    border-color: #444;
    background: #1e1e1e;
    color: #bbb;
}
[data-bs-theme="dark"] .tf-cat-tab.tf-active {
    background: var(--primary-color, #AC1830);
    color: #fff;
    border-color: var(--primary-color, #AC1830);
}
[data-bs-theme="dark"] .tf-chip {
    border-color: #444;
    background: #1e1e1e;
    color: #ccc;
}
[data-bs-theme="dark"] .tf-chip.tf-active {
    background: var(--primary-color, #AC1830);
    color: #fff;
    border-color: var(--primary-color, #AC1830);
}
[data-bs-theme="dark"] .tf-selected-label {
    background: rgba(211, 47, 47, 0.15);
}

/* 모바일 최적화 */
@media (max-width: 576px) {
    .tf-cat-row {
        padding: 8px 12px;
        gap: 6px;
    }
    .tf-cat-tab {
        padding: 7px 14px;
        font-size: 13px;
    }
    .tf-cat-icon {
        font-size: 13px;
    }
    .tf-chips-row {
        padding: 6px 12px 10px;
        gap: 6px;
    }
    .tf-chip {
        padding: 5px 11px;
        font-size: 12px;
    }
    .tf-selected-label {
        margin: 0 12px 6px;
        font-size: 12px;
    }
}
</style>

<div class="tf-wrap" id="<?php echo $_tf_uid; ?>">
    <!-- 카테고리 탭 (가로 스크롤) -->
    <div class="tf-cat-row">
        <a href="<?php echo _tf_build_url(0, 0); ?>"
           class="tf-cat-tab<?php echo ($_tf_active_cat_id <= 0) ? ' tf-active' : ''; ?>">전체</a>
<?php foreach ($_tf_categories as $_tf_cat): ?>
        <a href="<?php echo _tf_build_url(intval($_tf_cat['id']), 0); ?>"
           class="tf-cat-tab<?php echo ($_tf_active_cat_id === intval($_tf_cat['id'])) ? ' tf-active' : ''; ?>"
           data-tf-cat="<?php echo intval($_tf_cat['id']); ?>">
<?php if (!empty($_tf_cat['icon'])): ?>
            <i class="<?php echo htmlspecialchars($_tf_cat['icon']); ?> tf-cat-icon"></i>
<?php endif; ?>
            <?php echo htmlspecialchars($_tf_cat['name']); ?>
        </a>
<?php endforeach; ?>
    </div>

<?php if (!empty($_tf_treatments) && $_tf_active_cat_id > 0): ?>
    <!-- 시술 해시태그 칩 -->
    <div class="tf-chips-row">
<?php foreach ($_tf_treatments as $_tf_t):
    $_tf_tid = intval($_tf_t['id']);
    $_tf_is_active = ($_tf_selected_treatment_id === $_tf_tid);
    $_tf_hashtag = !empty($_tf_t['hashtag']) ? $_tf_t['hashtag'] : ('#' . str_replace(' ', '', $_tf_t['name']));
?>
        <a href="<?php echo _tf_build_url($_tf_active_cat_id, $_tf_tid); ?>"
           class="tf-chip<?php echo $_tf_is_active ? ' tf-active' : ''; ?>"
           data-tf-tid="<?php echo $_tf_tid; ?>">
            <?php echo htmlspecialchars($_tf_hashtag); ?>
        </a>
<?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($_tf_selected_treatment_id > 0 && !empty($_tf_selected_treatment_name)): ?>
    <!-- 선택된 시술 라벨 -->
    <div class="tf-selected-label">
        <i class="bi bi-funnel-fill" style="font-size:12px;"></i>
        <?php echo htmlspecialchars($_tf_selected_treatment_name); ?> 시술 병원
        <a href="<?php echo _tf_build_url(0, 0); ?>" class="tf-clear" title="필터 해제">&times;</a>
    </div>
<?php endif; ?>
</div>

<script>
(function(){
    // 선택된 카테고리 탭을 자동 스크롤하여 화면에 보이게
    var wrap = document.getElementById('<?php echo $_tf_uid; ?>');
    if (!wrap) return;
    var activeTab = wrap.querySelector('.tf-cat-tab.tf-active');
    if (activeTab) {
        var row = wrap.querySelector('.tf-cat-row');
        if (row) {
            // 약간의 지연을 줘서 레이아웃 계산 완료 후 스크롤
            setTimeout(function() {
                var tabLeft = activeTab.offsetLeft;
                var tabWidth = activeTab.offsetWidth;
                var rowWidth = row.offsetWidth;
                // 활성 탭이 중앙에 오도록 스크롤
                row.scrollLeft = tabLeft - (rowWidth / 2) + (tabWidth / 2);
            }, 50);
        }
    }
})();
</script>

