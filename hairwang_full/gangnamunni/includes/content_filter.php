<?php
/**
 * content_filter.php — UGC 콘텐츠 필터링 유틸리티
 *
 * Apple App Store Guideline 1.2 (Safety) 준수를 위한
 * 사용자 생성 콘텐츠(UGC) 필터링, 차단, 신고 처리 모듈.
 *
 * 독립 실행 파일 — 외부 include 없음, DB 연결은 호출자가 전달.
 * 모든 DB 쿼리는 prepared statement 사용.
 *
 * @since 2026-03-17
 */

// ============================================================
// 금칙어 사전 (카테고리별)
// ============================================================

/**
 * 금칙어 목록을 카테고리별로 반환
 *
 * @return array ['category_name' => ['word1', 'word2', ...]]
 */
function getBannedWords(): array
{
    return [
        // 한국어 비속어
        'korean_profanity' => [
            '씨발', '개새끼', '병신', '지랄', '미친년', '미친놈',
            '꺼져', '닥쳐', '개같은', '존나',
        ],

        // 스팸 키워드 — 광고성 게시물 차단
        'spam' => [
            '홍보', '광고', '바로가기', '카톡', '텔레그램',
            '무료상담', '클릭', '링크', 'http://', 'https://',
        ],

        // 영문 비속어
        'english_profanity' => [
            'fuck', 'shit', 'asshole', 'bitch', 'damn', 'bastard',
        ],

        // 의료법 위반 표현 — 의료광고법 준수
        'medical_law_violation' => [
            '100% 효과', '완치 보장', '부작용 없음', '최고의 의사',
        ],
    ];
}

// ============================================================
// filterContent — 텍스트 금칙어 검사
// ============================================================

/**
 * 텍스트에 금칙어가 포함되어 있는지 검사
 *
 * mb_stripos 를 사용하여 한국어·영문 대소문자 무시 매칭.
 * 하나라도 매칭되면 clean=false 로 반환한다.
 *
 * @param  string $text 검사 대상 텍스트
 * @return array  [
 *     'clean'         => bool,   // true: 통과, false: 금칙어 포함
 *     'blocked_words' => array,  // 매칭된 금칙어 목록
 *     'categories'    => array,  // 매칭된 카테고리 목록 (중복 제거)
 * ]
 */
function filterContent(string $text): array
{
    $result = [
        'clean'         => true,
        'blocked_words' => [],
        'categories'    => [],
    ];

    if ($text === '') {
        return $result;
    }

    $bannedWords = getBannedWords();
    $matchedCategories = [];

    foreach ($bannedWords as $category => $words) {
        foreach ($words as $word) {
            // mb_stripos: 멀티바이트(한국어) + 대소문자 무시
            if (mb_stripos($text, $word, 0, 'UTF-8') !== false) {
                $result['blocked_words'][] = $word;
                $matchedCategories[$category] = true;
            }
        }
    }

    if (!empty($result['blocked_words'])) {
        $result['clean'] = false;
        $result['categories'] = array_keys($matchedCategories);
    }

    return $result;
}

// ============================================================
// getBlockedUserIds — 차단한 사용자 목록 조회
// ============================================================

/**
 * 특정 사용자가 차단한 사용자 ID 목록을 반환
 *
 * gangnamunni_blocked_users 테이블 참조.
 *
 * @param  int    $user_id  차단을 설정한 사용자 ID
 * @param  mysqli $connect  DB 연결 객체
 * @return int[]  차단된 사용자 ID 배열 (없으면 빈 배열)
 */
function getBlockedUserIds(string $user_id, mysqli $connect): array
{
    $blockedIds = [];

    $stmt = $connect->prepare(
        "SELECT blocked_id
           FROM gangnamunni_blocked_users
          WHERE blocker_id = ?"
    );

    if (!$stmt) {
        return $blockedIds;
    }

    $stmt->bind_param('s', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $blockedIds[] = (int) $row['blocked_id'];
    }

    $stmt->close();

    return $blockedIds;
}

// ============================================================
// isUserBlocked — 차단 여부 확인
// ============================================================

/**
 * blocker_id 가 blocked_id 를 차단했는지 확인
 *
 * @param  int    $blocker_id 차단을 설정한 사용자 ID
 * @param  int    $blocked_id 차단 대상 사용자 ID
 * @param  mysqli $connect    DB 연결 객체
 * @return bool   차단 상태이면 true
 */
function isUserBlocked(string $blocker_id, string $blocked_id, mysqli $connect): bool
{
    $stmt = $connect->prepare(
        "SELECT 1
           FROM gangnamunni_blocked_users
          WHERE blocker_id = ?
            AND blocked_id = ?
          LIMIT 1"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $blocker_id, $blocked_id);
    $stmt->execute();
    $stmt->store_result();
    $found = $stmt->num_rows > 0;
    $stmt->close();

    return $found;
}

// ============================================================
// getReportCount — 콘텐츠 신고 건수 조회
// ============================================================

/**
 * 특정 콘텐츠에 대한 누적 신고 건수를 반환
 *
 * gangnamunni_content_reports 테이블 참조.
 * content_type 예시: 'review', 'comment', 'community', 'tip'
 *
 * @param  string $content_type 콘텐츠 유형 (영문 식별자)
 * @param  int    $content_id   콘텐츠 PK
 * @param  mysqli $connect      DB 연결 객체
 * @return int    신고 건수 (오류 시 0)
 */
function getReportCount(string $content_type, int $content_id, mysqli $connect): int
{
    $stmt = $connect->prepare(
        "SELECT COUNT(*) AS cnt
           FROM gangnamunni_content_reports
          WHERE content_type = ?
            AND content_id = ?"
    );

    if (!$stmt) {
        return 0;
    }

    $stmt->bind_param('si', $content_type, $content_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (int) ($row['cnt'] ?? 0);
}

// ============================================================
// autoHideIfReported — 신고 누적 시 자동 숨김 처리
// ============================================================

/**
 * 신고 건수가 threshold 이상이면 콘텐츠를 자동 숨김 처리
 *
 * gangnamunni_content_reports 의 집계 결과에 따라
 * 해당 콘텐츠의 is_hidden 플래그를 1 로 업데이트한다.
 *
 * 지원 content_type → 테이블 매핑:
 *   - review    → gangnamunni_reviews
 *   - comment   → gangnamunni_comments
 *   - community → gangnamunni_community
 *   - tip       → gangnamunni_tips
 *   - qna       → gangnamunni_qna
 *
 * @param  string $content_type 콘텐츠 유형
 * @param  int    $content_id   콘텐츠 PK
 * @param  mysqli $connect      DB 연결 객체
 * @param  int    $threshold    자동 숨김 기준 신고 건수 (기본 3)
 * @return bool   숨김 처리 실행 여부
 */
function autoHideIfReported(
    string $content_type,
    int    $content_id,
    mysqli $connect,
    int    $threshold = 3
): bool {
    // 신고 건수 조회
    $reportCount = getReportCount($content_type, $content_id, $connect);

    if ($reportCount < $threshold) {
        return false;
    }

    // content_type → 테이블+컬럼 매핑 (화이트리스트 — SQL 인젝션 방지)
    $tableMap = [
        'review'    => ['table' => 'hospital_reviews', 'col' => 'is_active', 'val' => '0'],
        'qna'       => ['table' => 'gangnamunni_qna', 'col' => 'is_public', 'val' => '0'],
        'community' => ['table' => 'gangnamunni_community_posts', 'col' => 'is_active', 'val' => '0'],
        'chat'      => ['table' => 'gangnamunni_chat_messages', 'col' => 'is_hidden', 'val' => '1'],
        'tip'       => ['table' => 'gangnamunni_tips', 'col' => 'is_active', 'val' => '0'],
    ];

    if (!isset($tableMap[$content_type])) {
        return false;
    }

    $map = $tableMap[$content_type];

    // 테이블명+컬럼명은 화이트리스트에서 가져오므로 직접 삽입 안전
    $sql = "UPDATE {$map['table']} SET {$map['col']} = {$map['val']} WHERE id = ?";
    $stmt = $connect->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $content_id);
    $success = $stmt->execute();
    $affected = $stmt->affected_rows > 0;
    $stmt->close();

    return $success && $affected;
}
