<?php
/**
 * treatment_review_scorer.php -- 후기 시술 감성 분석 및 점수 저장
 *
 * 함수: scoreReviewTreatments($connect, $review_id, $hospital_id, $review_content, $treatment_ids)
 *
 * 동작:
 *   1. 각 treatment_id에 대해 이름 + 별칭 조회
 *   2. 후기 본문에서 긍정/부정 키워드 매칭 → 감성 점수 산출
 *   3. gangnamunni_treatment_reviews INSERT/UPDATE
 *   4. gangnamunni_treatment_scores 재집계 INSERT/UPDATE
 *
 * 필수 테이블:
 *   gangnamunni_treatment_master (id, name)
 *   gangnamunni_treatment_aliases (treatment_id, alias)
 *   gangnamunni_treatment_reviews (review_id, hospital_id, treatment_id, sentiment_score, is_positive, keyword_matched)
 *   gangnamunni_treatment_scores (hospital_id, treatment_id, total_reviews, positive_count, avg_sentiment, score)
 *
 * @created 2026-03-25
 */

/**
 * 후기에 연결된 시술의 감성 점수를 분석하고 DB에 저장한다.
 *
 * @param mysqli $connect       DB 연결
 * @param int    $review_id     후기 ID (hospital_reviews.id)
 * @param int    $hospital_id   병원 ID
 * @param string $review_content 후기 본문 텍스트
 * @param array  $treatment_ids  선택된 treatment_master ID 배열
 * @return void
 */
function scoreReviewTreatments($connect, $review_id, $hospital_id, $review_content, $treatment_ids)
{
    if (!($connect instanceof mysqli) || $review_id <= 0 || $hospital_id <= 0) {
        return;
    }
    if (empty($treatment_ids) || !is_array($treatment_ids)) {
        return;
    }

    // 감성 분석용 키워드
    $positive_keywords = ['좋', '만족', '추천', '효과', '자연스럽', '친절', '깨끗', '대만족', '재방문', '최고', '완전', '짱'];
    $negative_keywords = ['별로', '아프', '실망', '부작용', '불만', '비추', '후회', '안됨', '최악', '불친절'];

    // 본문 정리 (HTML 태그 제거)
    $clean_content = strip_tags($review_content);
    $clean_content = html_entity_decode($clean_content, ENT_QUOTES, 'UTF-8');

    // 테이블 존재 확인 (없으면 생성)
    _trs_ensureTables($connect);

    foreach ($treatment_ids as $tid) {
        $tid = intval($tid);
        if ($tid <= 0) {
            continue;
        }

        // 시술명 + 별칭 조회
        $names = _trs_getTreatmentNames($connect, $tid);
        if (empty($names)) {
            continue;
        }

        // 감성 분석
        $pos_count = 0;
        $neg_count = 0;
        $matched_keywords = [];

        foreach ($positive_keywords as $kw) {
            $cnt = mb_substr_count($clean_content, $kw);
            if ($cnt > 0) {
                $pos_count += $cnt;
                $matched_keywords[] = '+' . $kw;
            }
        }

        foreach ($negative_keywords as $kw) {
            $cnt = mb_substr_count($clean_content, $kw);
            if ($cnt > 0) {
                $neg_count += $cnt;
                $matched_keywords[] = '-' . $kw;
            }
        }

        // is_positive: 긍정 >= 부정
        $is_positive = ($pos_count >= $neg_count) ? 1 : 0;

        // sentiment_score: (pos - neg) / max(pos + neg, 1), -1.0 ~ 1.0
        $total_kw = max($pos_count + $neg_count, 1);
        $sentiment_score = ($pos_count - $neg_count) / $total_kw;
        $sentiment_score = max(-1.0, min(1.0, $sentiment_score));

        // 매칭된 키워드 문자열 (저장용, 최대 500자)
        $keyword_str = implode(',', $matched_keywords);
        if (mb_strlen($keyword_str) > 500) {
            $keyword_str = mb_substr($keyword_str, 0, 497) . '...';
        }

        // INSERT ... ON DUPLICATE KEY UPDATE
        $ins_sql = "
            INSERT INTO gangnamunni_treatment_reviews
                (review_id, hospital_id, treatment_id, sentiment_score, is_positive, keyword_matched, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                sentiment_score = VALUES(sentiment_score),
                is_positive = VALUES(is_positive),
                keyword_matched = VALUES(keyword_matched),
                updated_at = NOW()
        ";
        $ins_stmt = mysqli_prepare($connect, $ins_sql);
        if ($ins_stmt) {
            mysqli_stmt_bind_param($ins_stmt, 'iiidis',
                $review_id, $hospital_id, $tid,
                $sentiment_score, $is_positive, $keyword_str
            );
            mysqli_stmt_execute($ins_stmt);
            mysqli_stmt_close($ins_stmt);
        }
    }

    // 관련 hospital+treatment 조합의 점수 재집계
    $processed_pairs = [];
    foreach ($treatment_ids as $tid) {
        $tid = intval($tid);
        if ($tid <= 0) {
            continue;
        }
        $pair_key = $hospital_id . '_' . $tid;
        if (isset($processed_pairs[$pair_key])) {
            continue;
        }
        $processed_pairs[$pair_key] = true;

        _trs_recalculateScore($connect, $hospital_id, $tid);
    }
}

/**
 * 시술명 + 별칭 목록 조회 (내부 헬퍼)
 */
function _trs_getTreatmentNames($connect, $treatment_id)
{
    $names = [];

    // 마스터 이름
    $stmt = mysqli_prepare($connect, "SELECT name FROM gangnamunni_treatment_master WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $treatment_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $names[] = $row['name'];
        }
        mysqli_stmt_close($stmt);
    }

    if (empty($names)) {
        return $names;
    }

    // 별칭 테이블 조회 (테이블 없으면 무시)
    $table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_treatment_aliases'");
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        $alias_stmt = mysqli_prepare($connect, "SELECT alias FROM gangnamunni_treatment_aliases WHERE treatment_id = ?");
        if ($alias_stmt) {
            mysqli_stmt_bind_param($alias_stmt, 'i', $treatment_id);
            mysqli_stmt_execute($alias_stmt);
            $alias_result = mysqli_stmt_get_result($alias_stmt);
            if ($alias_result) {
                while ($alias_row = mysqli_fetch_assoc($alias_result)) {
                    $a = trim($alias_row['alias']);
                    if ($a !== '' && !in_array($a, $names)) {
                        $names[] = $a;
                    }
                }
            }
            mysqli_stmt_close($alias_stmt);
        }
    }

    return $names;
}

/**
 * 병원+시술 조합의 점수 재집계 (내부 헬퍼)
 *
 * score = (positive/total * 80) + ((avg_sentiment + 1) / 2 * 20)
 */
function _trs_recalculateScore($connect, $hospital_id, $treatment_id)
{
    $agg_sql = "
        SELECT
            COUNT(*) AS total,
            SUM(is_positive) AS positive,
            AVG(sentiment_score) AS avg_sent
        FROM gangnamunni_treatment_reviews
        WHERE hospital_id = ? AND treatment_id = ?
    ";
    $agg_stmt = mysqli_prepare($connect, $agg_sql);
    if (!$agg_stmt) {
        return;
    }

    mysqli_stmt_bind_param($agg_stmt, 'ii', $hospital_id, $treatment_id);
    mysqli_stmt_execute($agg_stmt);
    $agg_result = mysqli_stmt_get_result($agg_stmt);

    if (!$agg_result) {
        mysqli_stmt_close($agg_stmt);
        return;
    }

    $agg = mysqli_fetch_assoc($agg_result);
    mysqli_stmt_close($agg_stmt);

    $total = intval($agg['total'] ?? 0);
    $positive = intval($agg['positive'] ?? 0);
    $avg_sent = floatval($agg['avg_sent'] ?? 0);

    if ($total <= 0) {
        return;
    }

    // score = (positive/total * 80) + ((avg_sentiment + 1) / 2 * 20)
    $score = ($positive / $total * 80.0) + (($avg_sent + 1.0) / 2.0 * 20.0);
    $score = round(max(0.0, min(100.0, $score)), 2);

    $upsert_sql = "
        INSERT INTO gangnamunni_treatment_scores
            (hospital_id, treatment_id, total_reviews, positive_count, avg_sentiment, score, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            total_reviews = VALUES(total_reviews),
            positive_count = VALUES(positive_count),
            avg_sentiment = VALUES(avg_sentiment),
            score = VALUES(score),
            updated_at = NOW()
    ";
    $upsert_stmt = mysqli_prepare($connect, $upsert_sql);
    if ($upsert_stmt) {
        mysqli_stmt_bind_param($upsert_stmt, 'iiiidd',
            $hospital_id, $treatment_id,
            $total, $positive,
            $avg_sent, $score
        );
        mysqli_stmt_execute($upsert_stmt);
        mysqli_stmt_close($upsert_stmt);
    }
}

/**
 * 필수 테이블 자동 생성 (내부 헬퍼)
 * 테이블이 없을 때만 CREATE IF NOT EXISTS 실행
 */
function _trs_ensureTables($connect)
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    // gangnamunni_treatment_reviews
    mysqli_query($connect, "
        CREATE TABLE IF NOT EXISTS gangnamunni_treatment_reviews (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            review_id INT UNSIGNED NOT NULL,
            hospital_id INT UNSIGNED NOT NULL,
            treatment_id INT UNSIGNED NOT NULL,
            sentiment_score DECIMAL(5,4) NOT NULL DEFAULT 0.0000 COMMENT '-1.0 ~ 1.0',
            is_positive TINYINT(1) NOT NULL DEFAULT 1,
            keyword_matched VARCHAR(500) DEFAULT NULL COMMENT '매칭된 키워드 목록',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_review_treatment (review_id, treatment_id),
            KEY idx_hospital_treatment (hospital_id, treatment_id),
            KEY idx_treatment (treatment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // gangnamunni_treatment_scores
    mysqli_query($connect, "
        CREATE TABLE IF NOT EXISTS gangnamunni_treatment_scores (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            hospital_id INT UNSIGNED NOT NULL,
            treatment_id INT UNSIGNED NOT NULL,
            total_reviews INT UNSIGNED NOT NULL DEFAULT 0,
            positive_count INT UNSIGNED NOT NULL DEFAULT 0,
            avg_sentiment DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
            score DECIMAL(6,2) NOT NULL DEFAULT 0.00 COMMENT '0~100 점수',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_hospital_treatment (hospital_id, treatment_id),
            KEY idx_treatment_score (treatment_id, score DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
