<?php
/**
 * SNS 로그인 사용자 정보 API
 * 앱에서 SNS 로그인 콜백 후 "로그인한 본인"의 정보 조회
 *
 * 보안 (2026-10-13): 이전에는 로그인 없이 ?mb_id= 만으로 임의 회원의 이름·이메일·전화번호를 반환했고
 * Access-Control-Allow-Origin: * 로 어느 사이트에서나 읽을 수 있었다.
 * 이제 그누보드 로그인 세션의 본인 정보만 반환한다. mb_id 를 넘기면 본인과 같을 때만 허용.
 */
require_once __DIR__ . '/includes/init.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

function sns_user_info_fail($code, $error) {
    http_response_code($code);
    echo json_encode(['error' => $error]);
    exit;
}

if (!is_gangnamunni_logged_in()) {
    sns_user_info_fail(401, 'login required');
}

$me = get_gangnamunni_member();
$my_id = (string)($me['mb_id'] ?? '');
$requested = isset($_GET['mb_id']) ? trim((string)$_GET['mb_id']) : '';

// 다른 회원 조회는 존재 여부도 드러내지 않는다
if ($my_id === '' || ($requested !== '' && !hash_equals($my_id, $requested))) {
    sns_user_info_fail(403, 'forbidden');
}

echo json_encode([
    'mb_id' => $my_id,
    'name' => ($me['mb_name'] ?? '') ?: ($me['mb_nick'] ?? ''),
    'email' => $me['mb_email'] ?? '',
    'phone' => $me['mb_hp'] ?? '',
], JSON_UNESCAPED_UNICODE);
