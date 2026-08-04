<?php
/**
 * treatment_chips.php — 병원 시술 해시태그 칩 컴포넌트
 *
 * 사용 전 필수:
 *   $hospital_id (int) — 병원 ID
 *   $connect (mysqli) — DB 연결
 *
 * 출력: 해시태그 칩 목록 (시술명 + 점수 뱃지)
 * 데이터 없으면 아무것도 출력하지 않음
 *
 * @created 2026-03-25
 */
if (!isset($hospital_id) || !isset($connect) || !($connect instanceof mysqli)) {
    return;
}

$_tc_hospital_id = intval($hospital_id);
if ($_tc_hospital_id <= 0) {
    return;
}

// 병원의 활성 시술 목록 조회 (마스터 + 카테고리 JOIN)
$_tc_sql = "
    SELECT
        ht.id AS assignment_id,
        ht.treatment_id,
        tm.name AS treatment_name,
        tm.hashtag,
        tc.name AS category_name,
        ht.is_promoted
    FROM gangnamunni_hospital_treatments ht
    INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
    LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
    WHERE ht.hospital_id = ?
      AND ht.status = 'active' AND ht.is_active = 1
      AND tm.is_active = 1
    ORDER BY ht.is_promoted DESC, tc.sort_order ASC, tm.popularity_score DESC, tm.name ASC
";

$_tc_stmt = mysqli_prepare($connect, $_tc_sql);
if (!$_tc_stmt) {
    return;
}

mysqli_stmt_bind_param($_tc_stmt, 'i', $_tc_hospital_id);
mysqli_stmt_execute($_tc_stmt);
$_tc_result = mysqli_stmt_get_result($_tc_stmt);

$_tc_treatments = [];
if ($_tc_result) {
    while ($_tc_row = mysqli_fetch_assoc($_tc_result)) {
        $_tc_treatments[] = $_tc_row;
    }
}
mysqli_stmt_close($_tc_stmt);

// 데이터 없으면 출력 없음
if (empty($_tc_treatments)) {
    return;
}

// 각 시술의 후기 기반 점수 조회 (gangnamunni_treatment_reviews에서 집계)
// treatment_reviews 테이블이 있으면 사용, 없으면 hospital_reviews에서 treatment 매칭
$_tc_scores = [];
$_tc_treatment_ids = array_column($_tc_treatments, 'treatment_id');

if (!empty($_tc_treatment_ids)) {
    // gangnamunni_treatment_reviews 테이블 존재 여부 확인
    $_tc_table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_treatment_reviews'");
    if ($_tc_table_check && mysqli_num_rows($_tc_table_check) > 0) {
        // 시술별 평균 점수 + 리뷰 수 조회
        $_tc_placeholders = implode(',', array_fill(0, count($_tc_treatment_ids), '?'));
        $_tc_types = str_repeat('i', count($_tc_treatment_ids));

        $_tc_score_sql = "
            SELECT treatment_id,
                   AVG(rating) AS avg_score,
                   COUNT(*) AS total_reviews
            FROM gangnamunni_treatment_reviews
            WHERE treatment_id IN ({$_tc_placeholders})
            GROUP BY treatment_id
        ";

        $_tc_score_stmt = mysqli_prepare($connect, $_tc_score_sql);
        if ($_tc_score_stmt) {
            mysqli_stmt_bind_param($_tc_score_stmt, $_tc_types, ...$_tc_treatment_ids);
            mysqli_stmt_execute($_tc_score_stmt);
            $_tc_score_result = mysqli_stmt_get_result($_tc_score_stmt);
            if ($_tc_score_result) {
                while ($_tc_sr = mysqli_fetch_assoc($_tc_score_result)) {
                    $_tc_scores[intval($_tc_sr['treatment_id'])] = [
                        'avg_score' => round(floatval($_tc_sr['avg_score']), 1),
                        'total_reviews' => intval($_tc_sr['total_reviews'])
                    ];
                }
            }
            mysqli_stmt_close($_tc_score_stmt);
        }
    } else {
        // gangnamunni_treatment_scores 테이블 폴백
        $_tc_table_check2 = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_treatment_scores'");
        if ($_tc_table_check2 && mysqli_num_rows($_tc_table_check2) > 0) {
            $_tc_placeholders = implode(',', array_fill(0, count($_tc_treatment_ids), '?'));
            $_tc_types = str_repeat('i', count($_tc_treatment_ids));

            $_tc_score_sql = "
                SELECT treatment_id, avg_score, total_reviews
                FROM gangnamunni_treatment_scores
                WHERE treatment_id IN ({$_tc_placeholders})
            ";

            $_tc_score_stmt = mysqli_prepare($connect, $_tc_score_sql);
            if ($_tc_score_stmt) {
                mysqli_stmt_bind_param($_tc_score_stmt, $_tc_types, ...$_tc_treatment_ids);
                mysqli_stmt_execute($_tc_score_stmt);
                $_tc_score_result = mysqli_stmt_get_result($_tc_score_stmt);
                if ($_tc_score_result) {
                    while ($_tc_sr = mysqli_fetch_assoc($_tc_score_result)) {
                        $_tc_scores[intval($_tc_sr['treatment_id'])] = [
                            'avg_score' => round(floatval($_tc_sr['avg_score']), 1),
                            'total_reviews' => intval($_tc_sr['total_reviews'])
                        ];
                    }
                }
                mysqli_stmt_close($_tc_score_stmt);
            }
        }
    }
}

// 더보기 토글 기준
$_tc_visible_limit = 6;
$_tc_total = count($_tc_treatments);
$_tc_has_more = $_tc_total > $_tc_visible_limit;
$_tc_more_count = $_tc_total - $_tc_visible_limit;

// 고유 ID (같은 페이지에 여러 번 include 가능)
$_tc_uid = 'tc_' . $_tc_hospital_id . '_' . mt_rand(1000, 9999);
?>
<style>
.treatment-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 0;
    margin: 0;
}
.treatment-chips .tc-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 20px;
    background: var(--tc-chip-bg, #f5f5f5);
    color: var(--tc-chip-color, #333);
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    border: 1px solid var(--tc-chip-border, #e0e0e0);
    transition: all 0.2s ease;
    white-space: nowrap;
    line-height: 1.4;
}
.treatment-chips .tc-chip:hover {
    background: var(--primary-color, #AE1932);
    color: #fff;
    border-color: var(--primary-color, #AE1932);
    text-decoration: none;
}
.treatment-chips .tc-chip:hover .tc-score-badge {
    background: rgba(255,255,255,0.25);
    color: #fff;
}
.treatment-chips .tc-chip.tc-promoted {
    border-color: var(--primary-color, #AE1932);
    background: rgba(211, 47, 47, 0.06);
}
.treatment-chips .tc-score-badge {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 1px 6px;
    border-radius: 10px;
    background: var(--primary-color, #AE1932);
    color: #fff;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.3;
}
.treatment-chips .tc-score-badge .tc-star {
    font-size: 10px;
}
.treatment-chips .tc-more-toggle {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    background: transparent;
    color: var(--primary-color, #AE1932);
    font-size: 13px;
    font-weight: 600;
    border: 1px dashed var(--primary-color, #AE1932);
    cursor: pointer;
    transition: all 0.2s ease;
}
.treatment-chips .tc-more-toggle:hover {
    background: rgba(211, 47, 47, 0.06);
}
.treatment-chips .tc-hidden {
    display: none;
}

/* 다크모드 대응 */
[data-bs-theme="dark"] .treatment-chips .tc-chip {
    --tc-chip-bg: #2a2a2a;
    --tc-chip-color: #e0e0e0;
    --tc-chip-border: #444;
}
[data-bs-theme="dark"] .treatment-chips .tc-chip.tc-promoted {
    background: rgba(211, 47, 47, 0.15);
}

/* 모바일 최적화 */
@media (max-width: 576px) {
    .treatment-chips {
        gap: 6px;
    }
    .treatment-chips .tc-chip {
        padding: 5px 10px;
        font-size: 12px;
    }
    .treatment-chips .tc-score-badge {
        font-size: 10px;
        padding: 1px 5px;
    }
}
</style>

<div class="treatment-chips" id="<?php echo $_tc_uid; ?>">
<?php
$_tc_index = 0;
foreach ($_tc_treatments as $_tc_t):
    $_tc_tid = intval($_tc_t['treatment_id']);
    $_tc_hashtag = $_tc_t['hashtag'] ?: ('#' . str_replace(' ', '', $_tc_t['treatment_name']));
    $_tc_link_name = urlencode($_tc_t['treatment_name']);
    $_tc_is_promoted = intval($_tc_t['is_promoted']) === 1;
    $_tc_hidden = ($_tc_has_more && $_tc_index >= $_tc_visible_limit) ? ' tc-hidden' : '';

    // 점수 정보
    $_tc_score_data = $_tc_scores[$_tc_tid] ?? null;
    $_tc_show_score = (
        $_tc_score_data
        && floatval($_tc_score_data['avg_score']) > 0
        && intval($_tc_score_data['total_reviews']) >= 3
    );
?>
    <a href="hospitals.php?treatment=<?php echo $_tc_link_name; ?>"
       class="tc-chip<?php echo $_tc_is_promoted ? ' tc-promoted' : ''; ?><?php echo $_tc_hidden; ?>"
       data-tc-index="<?php echo $_tc_index; ?>"
       title="<?php echo htmlspecialchars($_tc_t['treatment_name']); ?><?php echo $_tc_t['category_name'] ? ' (' . htmlspecialchars($_tc_t['category_name']) . ')' : ''; ?>">
        <span class="tc-label"><?php echo htmlspecialchars($_tc_hashtag); ?></span>
        <?php if ($_tc_show_score): ?>
        <span class="tc-score-badge">
            <span class="tc-star">&#9733;</span><?php echo $_tc_score_data['avg_score']; ?>
        </span>
        <?php endif; ?>
    </a>
<?php
    $_tc_index++;
endforeach;

if ($_tc_has_more):
?>
    <button type="button" class="tc-more-toggle" id="<?php echo $_tc_uid; ?>_toggle"
            onclick="tcToggleMore_<?php echo $_tc_uid; ?>(this)">
        +<?php echo $_tc_more_count; ?>개 더보기
    </button>
<?php endif; ?>
</div>

<?php if ($_tc_has_more): ?>
<script>
(function(){
    var expanded = false;
    window['tcToggleMore_<?php echo $_tc_uid; ?>'] = function(btn) {
        var container = document.getElementById('<?php echo $_tc_uid; ?>');
        var hidden = container.querySelectorAll('.tc-chip.tc-hidden');
        if (!expanded) {
            for (var i = 0; i < hidden.length; i++) {
                hidden[i].classList.remove('tc-hidden');
            }
            btn.textContent = '접기';
            expanded = true;
        } else {
            var chips = container.querySelectorAll('.tc-chip');
            for (var i = 0; i < chips.length; i++) {
                if (parseInt(chips[i].getAttribute('data-tc-index')) >= <?php echo $_tc_visible_limit; ?>) {
                    chips[i].classList.add('tc-hidden');
                }
            }
            btn.textContent = '+<?php echo $_tc_more_count; ?>개 더보기';
            expanded = false;
        }
    };
})();
</script>
<?php endif; ?>
