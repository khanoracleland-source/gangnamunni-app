<?php
/**
 * 서울미녀 (gangnamunni) - 리뷰어 통계 & 신뢰도 점수 관리
 *
 * - hospital_reviews 데이터 기반으로 리뷰어별 통계 자동 집계
 * - 신뢰도 점수 (0~100) 계산
 * - 배지 (newbie/active/expert/vip) 부여
 *
 * @requires reviewer_stats 테이블, hospital_reviews 테이블
 */

if (!isset($connect) || !$connect) {
    require_once __DIR__ . '/ai_proxy.php';
}

/**
 * 리뷰어 통계 업데이트 (1명)
 *
 * hospital_reviews에서 해당 사용자의 모든 후기를 집계하여
 * reviewer_stats 테이블에 UPSERT
 *
 * @param string $userName  사용자 이름 (hospital_reviews.user_name)
 * @param string|null $userId  사용자 ID (있으면)
 */
function updateReviewerStats(mysqli $conn, string $userName, ?string $userId = null): array
{
    $userNameEsc = mysqli_real_escape_string($conn, $userName);

    // 후기 통계 집계
    // images는 JSON/CSV 형식 → 사진 수 계산: 빈 값이면 0, 아니면 구분자 수 + 1
    $sql = "SELECT
                COUNT(*) as total_reviews,
                ROUND(AVG(rating), 2) as avg_rating,
                ROUND(AVG(CHAR_LENGTH(COALESCE(content, ''))), 0) as avg_content_length,
                ROUND(AVG(
                    CASE WHEN images IS NULL OR images = '' OR images = '[]' THEN 0
                    ELSE (LENGTH(images) - LENGTH(REPLACE(images, ',', '')) + 1)
                    END
                ), 1) as avg_photo_count,
                ROUND(AVG(COALESCE(ai_score, 0)), 2) as avg_ai_score,
                MAX(created_at) as last_review_at
            FROM hospital_reviews
            WHERE user_name = '{$userNameEsc}'
              AND COALESCE(review_status, 'published') != 'deleted'";
    $result = mysqli_query($conn, $sql);
    if (!$result) return ['error' => 'Query failed'];

    $stats = mysqli_fetch_assoc($result);
    $totalReviews = (int)($stats['total_reviews'] ?? 0);
    $avgRating = (float)($stats['avg_rating'] ?? 0);
    $avgContentLength = (int)($stats['avg_content_length'] ?? 0);
    $avgPhotoCount = (float)($stats['avg_photo_count'] ?? 0);
    $avgAiScore = (float)($stats['avg_ai_score'] ?? 0);
    $lastReviewAt = $stats['last_review_at'];

    // 신뢰도 점수 계산 (0~100)
    $trustScore = calculateTrustScore($totalReviews, $avgAiScore, $avgPhotoCount, $avgContentLength);

    // 배지 결정
    $badge = determineBadge($totalReviews, $trustScore, $avgAiScore);

    // UPSERT
    $userIdPart = $userId ? "'" . mysqli_real_escape_string($conn, $userId) . "'" : 'NULL';
    $lastReviewPart = $lastReviewAt ? "'" . mysqli_real_escape_string($conn, $lastReviewAt) . "'" : 'NULL';

    $sql = "INSERT INTO reviewer_stats
            (user_name, user_id, total_reviews, avg_rating, avg_content_length,
             avg_photo_count, avg_ai_score, trust_score, badge, last_review_at,
             created_at, updated_at)
            VALUES
            ('{$userNameEsc}', {$userIdPart}, {$totalReviews}, {$avgRating}, {$avgContentLength},
             {$avgPhotoCount}, {$avgAiScore}, {$trustScore}, '{$badge}', {$lastReviewPart},
             NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                user_id = COALESCE(VALUES(user_id), user_id),
                total_reviews = VALUES(total_reviews),
                avg_rating = VALUES(avg_rating),
                avg_content_length = VALUES(avg_content_length),
                avg_photo_count = VALUES(avg_photo_count),
                avg_ai_score = VALUES(avg_ai_score),
                trust_score = VALUES(trust_score),
                badge = VALUES(badge),
                last_review_at = VALUES(last_review_at),
                updated_at = NOW()";

    $success = mysqli_query($conn, $sql);

    return [
        'success'        => (bool)$success,
        'user_name'      => $userName,
        'total_reviews'  => $totalReviews,
        'avg_rating'     => $avgRating,
        'avg_ai_score'   => $avgAiScore,
        'trust_score'    => $trustScore,
        'badge'          => $badge,
    ];
}

/**
 * 전체 리뷰어 통계 일괄 업데이트
 */
function updateAllReviewerStats(mysqli $conn): array
{
    $sql = "SELECT DISTINCT user_name FROM hospital_reviews WHERE COALESCE(review_status, 'published') != 'deleted'";
    $result = mysqli_query($conn, $sql);
    if (!$result) return ['error' => 'Query failed', 'updated' => 0];

    $updated = 0;
    while ($row = mysqli_fetch_assoc($result)) {
        $r = updateReviewerStats($conn, $row['user_name']);
        if (!empty($r['success'])) $updated++;
    }

    return ['updated' => $updated];
}

/**
 * 리뷰어 통계 조회
 */
function getReviewerStats(mysqli $conn, string $userName): ?array
{
    $userNameEsc = mysqli_real_escape_string($conn, $userName);
    $sql = "SELECT * FROM reviewer_stats WHERE user_name = '{$userNameEsc}' LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row;
    }
    return null;
}

/**
 * 리뷰어 목록 (신뢰도 순)
 */
function getTopReviewers(mysqli $conn, int $limit = 20): array
{
    $sql = "SELECT * FROM reviewer_stats
            WHERE total_reviews > 0
            ORDER BY trust_score DESC, total_reviews DESC
            LIMIT {$limit}";
    $result = mysqli_query($conn, $sql);
    $reviewers = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $reviewers[] = $row;
        }
    }
    return $reviewers;
}

// ========================================================================
// 점수 계산 로직
// ========================================================================

/**
 * 신뢰도 점수 계산 (0~100)
 *
 * 구성 요소:
 * - 후기 수: 1건당 +5 (최대 30점)
 * - 평균 AI 점수: ai_score/100 * 30 (최대 30점)
 * - 평균 사진 수: 사진당 +3 (최대 15점)
 * - 평균 내용 길이: 100자+ → 10점, 200자+ → 15점, 500자+ → 25점
 */
function calculateTrustScore(int $totalReviews, float $avgAiScore, float $avgPhotoCount, int $avgContentLength): int
{
    // 1. 후기 수 점수 (최대 30)
    $reviewScore = min(30, $totalReviews * 5);

    // 2. AI 점수 (최대 30)
    $aiScore = min(30, ($avgAiScore / 100) * 30);

    // 3. 사진 점수 (최대 15)
    $photoScore = min(15, $avgPhotoCount * 3);

    // 4. 내용 길이 점수 (최대 25)
    $lengthScore = 0;
    if ($avgContentLength >= 500) {
        $lengthScore = 25;
    } elseif ($avgContentLength >= 200) {
        $lengthScore = 15;
    } elseif ($avgContentLength >= 100) {
        $lengthScore = 10;
    }

    $total = $reviewScore + $aiScore + $photoScore + $lengthScore;

    return min(100, max(0, (int)round($total)));
}

/**
 * 배지 결정
 *
 * - newbie: 후기 0건 또는 trust < 30
 * - active: 후기 3건+ AND trust >= 30
 * - expert: 후기 10건+ AND trust >= 60 AND avg_ai >= 80
 * - vip:    후기 20건+ AND trust >= 80
 */
function determineBadge(int $totalReviews, int $trustScore, float $avgAiScore): string
{
    if ($totalReviews >= 20 && $trustScore >= 80) {
        return 'vip';
    }
    if ($totalReviews >= 10 && $trustScore >= 60 && $avgAiScore >= 80) {
        return 'expert';
    }
    if ($totalReviews >= 3 && $trustScore >= 30) {
        return 'active';
    }
    return 'newbie';
}

/**
 * 배지 HTML 렌더링
 */
function renderBadge(string $badge): string
{
    $badges = [
        'newbie' => ['icon' => '🌱', 'label' => '새싹',   'class' => 'bg-secondary'],
        'active' => ['icon' => '⭐', 'label' => '활동',   'class' => 'bg-primary'],
        'expert' => ['icon' => '💎', 'label' => '전문가', 'class' => 'bg-success'],
        'vip'    => ['icon' => '👑', 'label' => 'VIP',    'class' => 'bg-warning text-dark'],
    ];
    $b = $badges[$badge] ?? $badges['newbie'];
    return '<span class="badge ' . $b['class'] . ' badge-reviewer" title="리뷰어 등급: ' . $b['label'] . '">'
         . $b['icon'] . ' ' . $b['label'] . '</span>';
}

/**
 * 리뷰어 프로필 카드 HTML
 */
function renderReviewerCard(array $stats): string
{
    $badge = renderBadge($stats['badge'] ?? 'newbie');
    $trustScore = (int)($stats['trust_score'] ?? 0);
    $totalReviews = (int)($stats['total_reviews'] ?? 0);
    $avgRating = number_format((float)($stats['avg_rating'] ?? 0), 1);
    $avgAiScore = number_format((float)($stats['avg_ai_score'] ?? 0), 0);

    // 신뢰도 바 색상
    if ($trustScore >= 80) $barClass = 'bg-success';
    elseif ($trustScore >= 50) $barClass = 'bg-primary';
    elseif ($trustScore >= 30) $barClass = 'bg-warning';
    else $barClass = 'bg-danger';

    $html = '<div class="reviewer-card p-2 border rounded mb-2">';
    $html .= '<div class="d-flex align-items-center gap-2 mb-1">';
    $html .= '<strong>' . htmlspecialchars($stats['user_name'] ?? '') . '</strong> ' . $badge;
    $html .= '</div>';
    $html .= '<div class="small text-muted">';
    $html .= "후기 {$totalReviews}건 · 평균 ⭐{$avgRating} · AI {$avgAiScore}점";
    $html .= '</div>';
    $html .= '<div class="progress mt-1" style="height:6px">';
    $html .= '<div class="progress-bar ' . $barClass . '" style="width:' . $trustScore . '%" title="신뢰도 ' . $trustScore . '"></div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}
