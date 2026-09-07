<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * 환자앱 네이티브 인증 - 로그인 성공 훅
 *
 * login.php(이메일)와 bbs/login_check.php(카카오/애플/구글 소셜 로그인 포함,
 * $is_social_login 플래그로 구분)가 공통으로 발생시키는 member_login_check
 * 이벤트를 사용한다 — 로그인 방식과 무관하게 동일하게 동작하기 위함이다.
 *
 * 여기서는 세션에 "교환 코드를 발급해줄 시간 창"만 남긴다. 실제 발급과
 * 스크립트 출력은 includes/init.php의 shutdown 훅에서 처리한다 (로그인
 * 응답 자체는 대부분 header('Location') 리다이렉트라 이 시점에는 HTML을
 * 출력할 수 없다).
 *
 * 1회성 불리언 플래그 대신 만료 시각을 쓰는 이유: 리다이렉트 중 DB 오류나
 * 네트워크 유실로 앱이 코드를 받지 못해도, 창이 열려있는 동안 로드되는
 * 다음 페이지가 새 코드로 자동 재시도한다 — "이번 한 번" 성공에 기대지 않는다.
 *
 * @created 2026-09-07
 */
add_event('member_login_check', '_app_native_auth_on_login', 10, 3);

function _app_native_auth_on_login($mb, $link = '', $is_social_login = false) {
    $is_patient_app = isset($_COOKIE['sb_patient_app'])
        && hash_equals('1', (string)$_COOKIE['sb_patient_app']);
    if ($is_patient_app && !empty($mb['mb_id'])) {
        $_SESSION['app_exchange_window_until'] = time() + 60;
    }
}
