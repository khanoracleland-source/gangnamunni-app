<?php
/**
 * cron_treatment_scores.php
 *
 * 시술 점수 재집계 + treatment_master popularity 갱신 (CLI 전용)
 *
 * Run via cron every 30 min:
 *   php /path/to/cron_treatment_scores.php >> /var/log/treatment_scores.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo 'CLI only';
    exit(1);
}

// --- DB 접속 ---
require_once __DIR__ . '/dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo date('Y-m-d H:i:s') . " [ERROR] DB connection failed: " . mysqli_connect_error() . "\n";
    exit(1);
}
mysqli_set_charset($connect, 'utf8mb4');

$start_time = microtime(true);
echo date('Y-m-d H:i:s') . " [START] Treatment score recalculation\n";

// --- 1. 필수 테이블 존재 확인 ---
$tables_required = ['gangnamunni_treatment_reviews', 'gangnamunni_treatment_scores'];
foreach ($tables_required as $tbl) {
    $check = mysqli_query($connect, "SHOW TABLES LIKE '{$tbl}'");
    if (!$check || mysqli_num_rows($check) === 0) {
        echo date('Y-m-d H:i:s') . " [ERROR] Table {$tbl} does not exist\n";
        mysqli_close($connect);
        exit(1);
    }
}

// --- 2. 모든 (hospital_id, treatment_id) 쌍별 점수 재집계 ---
$agg_sql = "
    SELECT
        hospital_id,
        treatment_id,
        COUNT(*)          AS total_reviews,
        SUM(is_positive)  AS positive_count,
        AVG(sentiment_score) AS avg_sentiment
    FROM gangnamunni_treatment_reviews
    GROUP BY hospital_id, treatment_id
";
$agg_result = mysqli_query($connect, $agg_sql);
if (!$agg_result) {
    echo date('Y-m-d H:i:s') . " [ERROR] Aggregation query failed: " . mysqli_error($connect) . "\n";
    mysqli_close($connect);
    exit(1);
}

$upsert_sql = "
    INSERT INTO gangnamunni_treatment_scores
        (hospital_id, treatment_id, total_reviews, positive_count, avg_sentiment, score, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE
        total_reviews  = VALUES(total_reviews),
        positive_count = VALUES(positive_count),
        avg_sentiment  = VALUES(avg_sentiment),
        score          = VALUES(score),
        updated_at     = NOW()
";
$upsert_stmt = mysqli_prepare($connect, $upsert_sql);
if (!$upsert_stmt) {
    echo date('Y-m-d H:i:s') . " [ERROR] Prepare upsert failed: " . mysqli_error($connect) . "\n";
    mysqli_close($connect);
    exit(1);
}

$upserted = 0;
$skipped  = 0;

while ($row = mysqli_fetch_assoc($agg_result)) {
    $hospital_id    = (int)$row['hospital_id'];
    $treatment_id   = (int)$row['treatment_id'];
    $total_reviews  = (int)$row['total_reviews'];
    $positive_count = (int)$row['positive_count'];
    $avg_sentiment  = (float)$row['avg_sentiment'];

    if ($total_reviews <= 0) {
        $skipped++;
        continue;
    }

    // score = (positive_count/total_reviews * 80) + ((avg_sentiment + 1) / 2 * 20)
    $score = ($positive_count / $total_reviews * 80.0) + (($avg_sentiment + 1.0) / 2.0 * 20.0);
    $score = round(max(0.0, min(100.0, $score)), 2);

    mysqli_stmt_bind_param($upsert_stmt, 'iiiidd',
        $hospital_id, $treatment_id,
        $total_reviews, $positive_count,
        $avg_sentiment, $score
    );

    if (mysqli_stmt_execute($upsert_stmt)) {
        $upserted++;
    } else {
        echo date('Y-m-d H:i:s') . " [WARN] Upsert failed for hospital={$hospital_id}, treatment={$treatment_id}: " . mysqli_stmt_error($upsert_stmt) . "\n";
    }
}

mysqli_stmt_close($upsert_stmt);
mysqli_free_result($agg_result);

echo date('Y-m-d H:i:s') . " [INFO] Scores upserted: {$upserted}, skipped: {$skipped}\n";

// --- 3. treatment_master popularity 갱신 ---
$pop_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_treatment_master'");
$pop_updated = 0;

if ($pop_check && mysqli_num_rows($pop_check) > 0) {
    // gangnamunni_hospital_treatments 존재 확인
    $ht_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_hospital_treatments'");
    if ($ht_check && mysqli_num_rows($ht_check) > 0) {
        $pop_sql = "
            UPDATE gangnamunni_treatment_master tm
            SET popularity = (
                SELECT COUNT(*)
                FROM gangnamunni_hospital_treatments ht
                WHERE ht.treatment_id = tm.id
                  AND ht.status = 'active'
            )
        ";
        if (mysqli_query($connect, $pop_sql)) {
            $pop_updated = mysqli_affected_rows($connect);
            echo date('Y-m-d H:i:s') . " [INFO] Treatment popularity updated: {$pop_updated} rows\n";
        } else {
            echo date('Y-m-d H:i:s') . " [WARN] Popularity update failed: " . mysqli_error($connect) . "\n";
        }
    } else {
        echo date('Y-m-d H:i:s') . " [WARN] gangnamunni_hospital_treatments table not found, skipping popularity\n";
    }
} else {
    echo date('Y-m-d H:i:s') . " [WARN] gangnamunni_treatment_master table not found, skipping popularity\n";
}

// --- 완료 ---
mysqli_close($connect);

$elapsed = round(microtime(true) - $start_time, 3);
echo date('Y-m-d H:i:s') . " [DONE] Elapsed: {$elapsed}s | scores={$upserted}, popularity={$pop_updated}\n";

exit(0);
