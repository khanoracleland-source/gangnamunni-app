<?php
include_once('./_common.php');

// 봇의 메일 링크 크롤링을 방지합니다.
if(function_exists('check_mail_bot')){ check_mail_bot($_SERVER['REMOTE_ADDR']); }

run_event('password_lost_certify_before');

// 오류시 공히 Error 라고 처리하는 것은 회원정보가 있는지? 비밀번호가 틀린지? 를 알아보려는 해킹에 대비한것

$mb_no = isset($_GET['mb_no']) ? preg_replace('#[^0-9]#', '', trim($_GET['mb_no'])) : 0;
$mb_nonce = isset($_GET['mb_nonce']) ? trim($_GET['mb_nonce']) : '';

// 회원아이디가 아닌 회원고유번호로 회원정보를 구한다.
$sql = " select mb_id, mb_lost_certify from {$g5['member_table']} where mb_no = '$mb_no' ";
$mb  = sql_fetch($sql);
$parts = explode(' ', trim($mb['mb_lost_certify'] ?? ''), 3);
if (count($parts) !== 3 || strlen($parts[0]) !== 32 || !ctype_digit($parts[1]) || strlen($parts[2]) < 33)
    die("Error");

[$stored_nonce, $expires_at, $new_password_hash] = $parts;
// nonce 및 만료시각을 검증한 후에만 한번 사용처리한다. 틀린 요청이 정상 토큰을 삭제하지 않는다.
if (hash_equals($stored_nonce, $mb_nonce) && time() <= (int)$expires_at) {
    global $connect;
    $consumed = false;
    if ($clear = mysqli_prepare($connect, "UPDATE {$g5['member_table']} SET mb_lost_certify = '' WHERE mb_no = ? AND mb_lost_certify = ?")) {
        mysqli_stmt_bind_param($clear, 'is', $mb_no, $mb['mb_lost_certify']);
        mysqli_stmt_execute($clear);
        $consumed = mysqli_stmt_affected_rows($clear) === 1;
        mysqli_stmt_close($clear);
    }
    if (!$consumed) die("Error");
    if ($pw = mysqli_prepare($connect, "UPDATE {$g5['member_table']} SET mb_password = ? WHERE mb_no = ?")) {
        mysqli_stmt_bind_param($pw, 'si', $new_password_hash, $mb_no);
        mysqli_stmt_execute($pw);
        mysqli_stmt_close($pw);
    } else die("Error");

    run_event('password_lost_certify_after', $mb, $mb_nonce);

    alert('비밀번호가 변경됐습니다.\\n\\n회원아이디와 변경된 비밀번호로 로그인 하시기 바랍니다.', G5_URL.'/gangnamunni/auth/login.php');
}
else {
    die("Error");
}
