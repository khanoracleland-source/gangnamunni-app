<?php
/**
 * FAQ 카테고리 공통 정의
 * - 사용자 FAQ 화면(faq.php) 탭 순서와 관리자(admin_faq.php) 선택 목록이 같은 기준을 쓰도록 한 곳에서 관리한다.
 * - 디자인 기준 탭: 결제 / 계정 / 예약 / 일반 / 포인트 / 후기
 */
if (!defined('GANGNAMUNNI_FAQ_CATEGORIES')) {
    define('GANGNAMUNNI_FAQ_CATEGORIES', ['결제', '계정', '예약', '일반', '포인트', '후기']);
}

/**
 * 기본 카테고리 + DB 에 이미 존재하는 기타 카테고리(레거시 데이터)를 합쳐서 반환한다.
 * 레거시 카테고리를 버리지 않아야 관리자 수정 시 기존 값이 유지된다.
 */
function gangnamunni_faq_categories($connect, $only_active = false)
{
    $cats = GANGNAMUNNI_FAQ_CATEGORIES;
    $sql = "SELECT DISTINCT category FROM gangnamunni_faq" . ($only_active ? " WHERE is_active=1" : "");
    $res = @mysqli_query($connect, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $c = trim((string)$r['category']);
            if ($c !== '' && !in_array($c, $cats, true)) $cats[] = $c;
        }
    }
    return $cats;
}
