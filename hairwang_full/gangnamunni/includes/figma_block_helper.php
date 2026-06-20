<?php
// ============================================================================
// [Figma Live Compare - 2026-06-15] 프론트 부가요소 표시 헬퍼
// 원인 : Figma 원본에 없는 부가요소(카테고리/프로모션/이벤트/광고)를
//        삭제하지 않고 '표시/숨김'으로 제어 — 기존 기능 보존
// 사용 : if (figma_block_visible('home_categories')): ...  endif;  (PHP 블록 안에서)
// 기본값: 설정 없으면 '표시(true)' — 즉 아무것도 안 바꾸면 기존과 100% 동일
// ============================================================================

if (!function_exists('figma_block_visible')) {

    /**
     * 부가요소 블록 표시 여부.
     * @param string $key       블록 키 (home_categories 등)
     * @param mysqli|null $conn 기존 DB 연결 (없으면 내부에서 1회 조회)
     * @return bool  표시=true / 숨김=false  (설정 없으면 true)
     */
    function figma_block_visible($key, $conn = null) {
        static $cache = null;

        if ($cache === null) {
            $cache = [];
            $local = false;
            $c = $conn;
            if (!$c) {
                if (defined('G5_MYSQL_HOST')) {
                    $c = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
                    $local = true;
                }
            }
            if ($c) {
                // 테이블 없을 수 있으니 에러 무시
                $r = @mysqli_query($c, "SELECT block_key, is_visible FROM figma_block_toggles");
                if ($r) {
                    while ($row = mysqli_fetch_assoc($r)) {
                        $cache[$row['block_key']] = (int)$row['is_visible'];
                    }
                }
                if ($local) mysqli_close($c);
            }
        }

        // 설정이 없으면 기본 표시(true) — 기존 동작 유지
        if (!array_key_exists($key, $cache)) return true;
        return $cache[$key] == 1;
    }
}

if (!function_exists('figma_review_mode')) {
    /**
     * figma_compare iframe 안에서 로드됐는지 (?in_figma_review=1).
     * true면 상/하단 네비 등 비교에 방해되는 chrome을 숨길 수 있다.
     */
    function figma_review_mode() {
        return isset($_GET['in_figma_review']) && $_GET['in_figma_review'] === '1';
    }
}
