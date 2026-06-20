<?php
/**
 * AI 리뷰 요약 API
 * GET ?hospital_id=8093 → 키워드 + 요약문
 * 캐시 사용 (24시간)
 */
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) jsonError('DB 연결 실패', 500);
mysqli_set_charset($connect, 'utf8mb4');

// 캐시 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_ai_review_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    keywords TEXT,
    summary TEXT,
    positive_count INT DEFAULT 0,
    negative_count INT DEFAULT 0,
    total_reviews INT DEFAULT 0,
    avg_rating DECIMAL(3,1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    UNIQUE KEY idx_hospital (hospital_id),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$hospital_id = intval($_GET['hospital_id'] ?? 0);
if ($hospital_id <= 0) jsonError('hospital_id 필수');

$action = $_GET['action'] ?? 'get';

if ($action === 'get') {
    // 캐시 확인
    $cache_stmt = mysqli_prepare($connect,
        "SELECT * FROM gangnamunni_ai_review_cache WHERE hospital_id = ? AND expires_at > NOW()");
    if ($cache_stmt) {
        mysqli_stmt_bind_param($cache_stmt, 'i', $hospital_id);
        mysqli_stmt_execute($cache_stmt);
        $cache = mysqli_fetch_assoc(mysqli_stmt_get_result($cache_stmt));
        mysqli_stmt_close($cache_stmt);

        if ($cache) {
            mysqli_close($connect);
            jsonSuccess([
                'hospital_id' => $hospital_id,
                'keywords' => json_decode($cache['keywords'], true) ?: [],
                'summary' => $cache['summary'],
                'positive_count' => (int)$cache['positive_count'],
                'negative_count' => (int)$cache['negative_count'],
                'total_reviews' => (int)$cache['total_reviews'],
                'avg_rating' => (float)$cache['avg_rating'],
                'cached' => true,
                'expires_at' => $cache['expires_at']
            ]);
        }
    }

    // 캐시 없음 → 후기 데이터 수집 후 키워드 추출
    $reviews = [];
    $total = 0;
    $sum_rating = 0;
    $positive = 0;
    $negative = 0;

    $rev_stmt = mysqli_prepare($connect,
        "SELECT content, rating FROM hospital_reviews WHERE hospital_id = ? AND review_status = 'published' AND is_active = 1 ORDER BY created_at DESC LIMIT 100");
    if ($rev_stmt) {
        mysqli_stmt_bind_param($rev_stmt, 'i', $hospital_id);
        mysqli_stmt_execute($rev_stmt);
        $rev_res = mysqli_stmt_get_result($rev_stmt);
        while ($row = mysqli_fetch_assoc($rev_res)) {
            $reviews[] = $row['content'];
            $total++;
            $sum_rating += (float)$row['rating'];
            if ((float)$row['rating'] >= 4) $positive++;
            elseif ((float)$row['rating'] <= 2) $negative++;
        }
        mysqli_stmt_close($rev_stmt);
    }

    $avg_rating = $total > 0 ? round($sum_rating / $total, 1) : 0;

    // 키워드 추출 (간단한 형태소 분석 — AI 없이도 동작)
    $keywords = extractKeywords($reviews);
    $summary = generateSummary($total, $avg_rating, $positive, $negative, $keywords);

    // AI 프록시 사용 가능한 경우 AI 요약 시도
    $ai_summary = null;
    $ai_proxy_path = __DIR__ . '/includes/ai_proxy.php';
    if (file_exists($ai_proxy_path) && $total >= 5) {
        require_once $ai_proxy_path;
        if (function_exists('callAI')) {
            $review_text = implode("\n---\n", array_slice($reviews, 0, 20));
            $prompt = "다음은 병원 후기입니다. 핵심 키워드 5~8개와 전체 요약을 한국어로 작성해주세요.\n\n형식:\n키워드: 단어1, 단어2, ...\n요약: 2~3문장\n\n후기:\n" . mb_substr($review_text, 0, 2000);

            $ai_result = callAI('review', $prompt, ['max_tokens' => 300, 'hospital_id' => $hospital_id]);
            if (!empty($ai_result['content'])) {
                $ai_summary = $ai_result['content'];
                // AI 결과에서 키워드 파싱
                if (preg_match('/키워드[:\s]*(.+)/u', $ai_summary, $m)) {
                    $ai_keywords = array_map('trim', explode(',', $m[1]));
                    if (count($ai_keywords) >= 3) {
                        $keywords = array_slice($ai_keywords, 0, 8);
                    }
                }
                if (preg_match('/요약[:\s]*(.+)/us', $ai_summary, $m2)) {
                    $summary = trim($m2[1]);
                }
            }
        }
    }

    // 캐시 저장 (24시간)
    $keywords_json = json_encode($keywords, JSON_UNESCAPED_UNICODE);
    $save_stmt = mysqli_prepare($connect,
        "INSERT INTO gangnamunni_ai_review_cache (hospital_id, keywords, summary, positive_count, negative_count, total_reviews, avg_rating, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
         ON DUPLICATE KEY UPDATE keywords=VALUES(keywords), summary=VALUES(summary), positive_count=VALUES(positive_count),
         negative_count=VALUES(negative_count), total_reviews=VALUES(total_reviews), avg_rating=VALUES(avg_rating),
         expires_at=VALUES(expires_at), created_at=NOW()");
    if ($save_stmt) {
        mysqli_stmt_bind_param($save_stmt, 'issiiid', $hospital_id, $keywords_json, $summary, $positive, $negative, $total, $avg_rating);
        mysqli_stmt_execute($save_stmt);
        mysqli_stmt_close($save_stmt);
    }

    mysqli_close($connect);
    jsonSuccess([
        'hospital_id' => $hospital_id,
        'keywords' => $keywords,
        'summary' => $summary,
        'positive_count' => $positive,
        'negative_count' => $negative,
        'total_reviews' => $total,
        'avg_rating' => $avg_rating,
        'cached' => false,
        'ai_used' => !empty($ai_summary)
    ]);
}

/**
 * 간단한 키워드 추출 (AI 없이 동작)
 */
function extractKeywords($reviews) {
    if (empty($reviews)) return [];

    $text = implode(' ', $reviews);
    // 불용어
    $stopwords = ['이', '가', '을', '를', '의', '에', '도', '는', '은', '와', '과', '로', '으로', '에서', '부터', '까지', '하고', '그리고', '그래서', '그런데', '너무', '정말', '아주', '매우', '것', '수', '때', '중', '후', '전', '좀', '잘', '더', '안', '못', '다', '좋', '있', '없', '하', '되', '보', '나', '우', '등', '및'];

    // 2-4글자 단어 추출
    preg_match_all('/[가-힣]{2,4}/u', $text, $matches);
    $words = $matches[0] ?? [];

    $freq = [];
    foreach ($words as $w) {
        if (in_array($w, $stopwords)) continue;
        if (mb_strlen($w) < 2) continue;
        $freq[$w] = ($freq[$w] ?? 0) + 1;
    }

    arsort($freq);
    return array_slice(array_keys($freq), 0, 8);
}

/**
 * 기본 요약문 생성
 */
function generateSummary($total, $avg, $positive, $negative, $keywords) {
    if ($total == 0) return '아직 등록된 후기가 없습니다.';

    $sentiment = '';
    if ($positive > $negative * 2) $sentiment = '대체로 만족도가 높은';
    elseif ($negative > $positive) $sentiment = '호불호가 갈리는';
    else $sentiment = '균형 잡힌 평가를 받는';

    $kw_text = !empty($keywords) ? '"' . implode('", "', array_slice($keywords, 0, 4)) . '"' : '';

    return "총 {$total}개의 후기에서 평균 {$avg}점으로, {$sentiment} 병원입니다."
        . ($kw_text ? " 주요 키워드는 {$kw_text} 등입니다." : '');
}
