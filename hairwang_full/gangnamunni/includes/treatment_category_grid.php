<?php
/**
 * treatment_category_grid.php — 시술 카테고리 그리드 컴포넌트 (index.php 용)
 *
 * 사용 전 필수:
 *   $connect (mysqli) — DB 연결
 *
 * 출력: 6개 주요 카테고리 카드 그리드 (카테고리 아이콘 + 이름 + 시술 해시태그)
 * 데이터 없으면 아무것도 출력하지 않음
 *
 * @created 2026-03-25
 */
if (!isset($connect) || !($connect instanceof mysqli)) {
    return;
}

// 활성 카테고리 조회 (상위 6개)
$_tcg_cat_sql = "
    SELECT id, name, icon, sort_order
    FROM gangnamunni_treatment_categories
    WHERE is_active = 1
    ORDER BY sort_order ASC, id ASC
    LIMIT 6
";
$_tcg_cat_result = mysqli_query($connect, $_tcg_cat_sql);

$_tcg_categories = [];
if ($_tcg_cat_result) {
    while ($_tcg_c = mysqli_fetch_assoc($_tcg_cat_result)) {
        $_tcg_categories[intval($_tcg_c['id'])] = $_tcg_c;
    }
}

if (empty($_tcg_categories)) {
    return;
}

// 각 카테고리의 활성 시술 목록 조회 (한 번의 쿼리로)
$_tcg_cat_ids = array_keys($_tcg_categories);
$_tcg_placeholders = implode(',', array_fill(0, count($_tcg_cat_ids), '?'));
$_tcg_types = str_repeat('i', count($_tcg_cat_ids));

$_tcg_treat_sql = "
    SELECT id, category_id, name, hashtag, popularity_score
    FROM gangnamunni_treatment_master
    WHERE category_id IN ({$_tcg_placeholders})
      AND is_active = 1
    ORDER BY popularity_score DESC, name ASC
";

$_tcg_treat_stmt = mysqli_prepare($connect, $_tcg_treat_sql);
if (!$_tcg_treat_stmt) {
    return;
}

mysqli_stmt_bind_param($_tcg_treat_stmt, $_tcg_types, ...$_tcg_cat_ids);
mysqli_stmt_execute($_tcg_treat_stmt);
$_tcg_treat_result = mysqli_stmt_get_result($_tcg_treat_stmt);

$_tcg_treatments_by_cat = [];
if ($_tcg_treat_result) {
    while ($_tcg_t = mysqli_fetch_assoc($_tcg_treat_result)) {
        $cid = intval($_tcg_t['category_id']);
        if (!isset($_tcg_treatments_by_cat[$cid])) {
            $_tcg_treatments_by_cat[$cid] = [];
        }
        $_tcg_treatments_by_cat[$cid][] = $_tcg_t;
    }
}
mysqli_stmt_close($_tcg_treat_stmt);

// 시술이 있는 카테고리만 필터링
$_tcg_valid_categories = [];
foreach ($_tcg_categories as $_tcg_cid => $_tcg_cat) {
    if (!empty($_tcg_treatments_by_cat[$_tcg_cid])) {
        $_tcg_valid_categories[$_tcg_cid] = $_tcg_cat;
    }
}

if (empty($_tcg_valid_categories)) {
    return;
}

// 고유 ID
$_tcg_uid = 'tcg_' . mt_rand(1000, 9999);

// 카테고리 아이콘 기본값 매핑 (icon 필드가 비어있을 때 폴백)
$_tcg_default_icons = [
    'bi bi-droplet',       // 피부
    'bi bi-scissors',      // 성형
    'bi bi-heart-pulse',   // 체형
    'bi bi-emoji-smile',   // 안면
    'bi bi-stars',         // 레이저
    'bi bi-shield-check',  // 기타
];
?>
<style>
.tcg-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    max-width: 640px;
    margin: 0 auto;
    padding: 0;
}
.tcg-card {
    background: var(--tcg-card-bg, #fff);
    border: 1px solid var(--tcg-card-border, #eee);
    border-radius: 16px;
    padding: 20px 16px 16px;
    text-align: center;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.tcg-card:hover {
    border-color: var(--primary-color, #AE1932);
    box-shadow: 0 4px 16px rgba(211, 47, 47, 0.1);
    transform: translateY(-2px);
}
.tcg-card-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    text-decoration: none;
    color: inherit;
    width: 100%;
}
.tcg-card-header:hover {
    text-decoration: none;
    color: inherit;
}
.tcg-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--tcg-icon-bg, rgba(211, 47, 47, 0.08));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: var(--primary-color, #AE1932);
    flex-shrink: 0;
}
.tcg-cat-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--tcg-text-color, #222);
    line-height: 1.3;
}
.tcg-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: center;
    width: 100%;
}
.tcg-tag {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 14px;
    background: var(--tcg-tag-bg, #f8f8f8);
    color: var(--tcg-tag-color, #555);
    font-size: 12px;
    font-weight: 500;
    text-decoration: none;
    border: 1px solid var(--tcg-tag-border, #e8e8e8);
    transition: all 0.15s ease;
    white-space: nowrap;
    line-height: 1.4;
}
.tcg-tag:hover {
    background: var(--primary-color, #AE1932);
    color: #fff;
    border-color: var(--primary-color, #AE1932);
    text-decoration: none;
}
.tcg-tag-more {
    color: var(--primary-color, #AE1932);
    background: transparent;
    border-color: transparent;
    font-weight: 600;
}
.tcg-tag-more:hover {
    background: rgba(211, 47, 47, 0.06);
    color: var(--primary-color, #AE1932);
    border-color: transparent;
}

/* 다크모드 */
[data-bs-theme="dark"] .tcg-card {
    --tcg-card-bg: #1e1e1e;
    --tcg-card-border: #333;
}
[data-bs-theme="dark"] .tcg-card:hover {
    box-shadow: 0 4px 16px rgba(211, 47, 47, 0.15);
}
[data-bs-theme="dark"] .tcg-icon {
    --tcg-icon-bg: rgba(211, 47, 47, 0.15);
}
[data-bs-theme="dark"] .tcg-cat-name {
    --tcg-text-color: #e0e0e0;
}
[data-bs-theme="dark"] .tcg-tag {
    --tcg-tag-bg: #2a2a2a;
    --tcg-tag-color: #bbb;
    --tcg-tag-border: #3a3a3a;
}

/* 모바일: 2열 그리드 */
@media (max-width: 576px) {
    .tcg-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
        padding: 0 4px;
    }
    .tcg-card {
        padding: 16px 10px 12px;
        border-radius: 12px;
    }
    .tcg-icon {
        width: 40px;
        height: 40px;
        font-size: 18px;
    }
    .tcg-cat-name {
        font-size: 13px;
    }
    .tcg-tags {
        gap: 4px;
    }
    .tcg-tag {
        padding: 3px 8px;
        font-size: 11px;
    }
}

/* 태블릿: 2열에서 3열 전환 */
@media (min-width: 577px) and (max-width: 768px) {
    .tcg-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}
</style>

<div class="tcg-grid" id="<?php echo $_tcg_uid; ?>">
<?php
$_tcg_icon_idx = 0;
foreach ($_tcg_valid_categories as $_tcg_cid => $_tcg_cat):
    $_tcg_cat_name = htmlspecialchars($_tcg_cat['name']);
    $_tcg_cat_icon = !empty($_tcg_cat['icon']) ? htmlspecialchars($_tcg_cat['icon']) : ($_tcg_default_icons[$_tcg_icon_idx % count($_tcg_default_icons)] ?? 'bi bi-tag');
    $_tcg_cat_link_name = urlencode($_tcg_cat['name']);
    $_tcg_cat_treatments = $_tcg_treatments_by_cat[$_tcg_cid] ?? [];
    $_tcg_max_tags = 5; // 카테고리당 최대 표시 시술 수
    $_tcg_overflow = count($_tcg_cat_treatments) - $_tcg_max_tags;
?>
    <div class="tcg-card">
        <a href="hospitals.php?category=<?php echo $_tcg_cat_link_name; ?>" class="tcg-card-header">
            <div class="tcg-icon">
                <i class="<?php echo $_tcg_cat_icon; ?>"></i>
            </div>
            <div class="tcg-cat-name"><?php echo $_tcg_cat_name; ?></div>
        </a>
        <div class="tcg-tags">
<?php
    $_tcg_tag_count = 0;
    foreach ($_tcg_cat_treatments as $_tcg_t):
        if ($_tcg_tag_count >= $_tcg_max_tags) break;
        $_tcg_tag_label = $_tcg_t['hashtag'] ?: ('#' . str_replace(' ', '', $_tcg_t['name']));
        $_tcg_tag_link_name = urlencode($_tcg_t['name']);
?>
            <a href="hospitals.php?treatment=<?php echo $_tcg_tag_link_name; ?>"
               class="tcg-tag"
               title="<?php echo htmlspecialchars($_tcg_t['name']); ?>"><?php echo htmlspecialchars($_tcg_tag_label); ?></a>
<?php
        $_tcg_tag_count++;
    endforeach;

    if ($_tcg_overflow > 0):
?>
            <a href="hospitals.php?category=<?php echo $_tcg_cat_link_name; ?>"
               class="tcg-tag tcg-tag-more">+<?php echo $_tcg_overflow; ?></a>
<?php endif; ?>
        </div>
    </div>
<?php
    $_tcg_icon_idx++;
endforeach;
?>
</div>
