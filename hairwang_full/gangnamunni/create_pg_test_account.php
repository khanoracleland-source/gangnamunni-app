<?php
// CLI 전용 스크립트 — 웹 요청은 404
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
/**
 * ============================================================
 * PG사 심사용 테스트 회원 생성/갱신 (멱등)
 * ------------------------------------------------------------
 * - 일반회원(g5_member, 레벨 2) 1개를 만든다. 로그인은 이메일 + 비밀번호.
 * - 비밀번호는 코드/깃에 절대 넣지 않는다. 실행 시 직접 입력한다.
 *     방법 1) 환경변수:  PG_TEST_PASSWORD='...' php create_pg_test_account.php
 *     방법 2) 인자 없이 실행하면 프롬프트에서 숨김 입력
 * - 이미 같은 이메일/아이디가 있으면 비밀번호만 갱신하고 계정 상태를 정상화한다.
 * - 해시는 사이트 설정과 같은 PBKDF2(create_hash). bcrypt 로 넣으면 로그인 불가.
 *
 * 선택 환경변수: PG_TEST_EMAIL, PG_TEST_ID, PG_TEST_NAME, PG_TEST_PHONE
 * 이 계정은 운영 데이터와 섞이지 않도록 mb_1='pg_test' 로 표시한다.
 *
 * @file gangnamunni/create_pg_test_account.php
 * ============================================================
 */
// 서버의 실제 DB 설정(data/dbconfig.php)을 크론 스크립트와 동일한 방식으로 읽는다.
require_once __DIR__ . '/includes/booking_payment_db_config.php';
if (!defined('_GNUBOARD_')) define('_GNUBOARD_', true);
require_once __DIR__ . '/../lib/pbkdf2.compat.php';

$mb_id    = getenv('PG_TEST_ID')    ?: 'oraclepgtest';
$email    = strtolower(trim(getenv('PG_TEST_EMAIL') ?: 'oraclepgtest@seoulbeauty.app'));
$name     = getenv('PG_TEST_NAME')  ?: '오라클PG테스트';
$phone    = getenv('PG_TEST_PHONE') ?: '010-0000-0000';

if (!preg_match('/^[a-z0-9_]{3,20}$/', $mb_id)) { fwrite(STDERR, "잘못된 아이디 형식\n"); exit(1); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { fwrite(STDERR, "잘못된 이메일 형식\n"); exit(1); }

$password = getenv('PG_TEST_PASSWORD');
if ($password === false || $password === '') {
    fwrite(STDOUT, "비밀번호 입력(8자 이상, 화면에 표시되지 않음): ");
    if (function_exists('shell_exec') && DIRECTORY_SEPARATOR === '/') @shell_exec('stty -echo');
    $password = rtrim((string)fgets(STDIN), "\r\n");
    if (function_exists('shell_exec') && DIRECTORY_SEPARATOR === '/') @shell_exec('stty echo');
    fwrite(STDOUT, "\n");
}
if (strlen($password) < 8) { fwrite(STDERR, "비밀번호는 8자 이상이어야 합니다.\n"); exit(1); }

mysqli_report(MYSQLI_REPORT_OFF);
$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, (int)(defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306));
if (!$connect) { fwrite(STDERR, 'DB 연결 실패: ' . mysqli_connect_error() . "\n"); exit(1); }
mysqli_set_charset($connect, 'utf8mb4');

$hash  = create_hash($password);
$now   = date('Y-m-d H:i:s');
$today = date('Y-m-d');

// 이메일 또는 아이디로 기존 계정 조회
$st = mysqli_prepare($connect, "SELECT mb_no, mb_id, mb_1 FROM g5_member WHERE LOWER(mb_email) = ? OR mb_id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'ss', $email, $mb_id);
mysqli_stmt_execute($st);
$existing = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if ($existing) {
    // 실수로 실제 회원 비밀번호를 덮어쓰지 않도록, 이 스크립트가 만든 계정만 갱신한다.
    if (($existing['mb_1'] ?? '') !== 'pg_test') {
        fwrite(STDERR, "같은 이메일/아이디의 일반 회원이 이미 있습니다(mb_id={$existing['mb_id']}). 다른 PG_TEST_EMAIL/PG_TEST_ID 를 사용하세요.\n");
        exit(1);
    }
    $up = mysqli_prepare($connect,
        "UPDATE g5_member SET mb_password = ?, mb_level = 2, mb_leave_date = '', mb_intercept_date = '',
                mb_lost_certify = '', mb_email_certify = ? WHERE mb_no = ?");
    $no = (int)$existing['mb_no'];
    mysqli_stmt_bind_param($up, 'ssi', $hash, $now, $no);
    $ok = mysqli_stmt_execute($up);
    mysqli_stmt_close($up);
    echo $ok ? "[갱신] 기존 테스트 계정 비밀번호를 변경했습니다.\n" : "[실패] " . mysqli_error($connect) . "\n";
} else {
    $ins = mysqli_prepare($connect,
        "INSERT INTO g5_member (mb_id, mb_password, mb_name, mb_nick, mb_nick_date, mb_email, mb_level, mb_datetime,
                mb_today_login, mb_ip, mb_login_ip, mb_email_certify, mb_open_date, mb_1, mb_mailling, mb_sms, mb_hp, mb_memo)
         VALUES (?, ?, ?, ?, ?, ?, 2, ?, ?, '', '', ?, ?, 'pg_test', '0', '0', ?, 'PG 심사용 테스트 계정')");
    $nick = $name;
    mysqli_stmt_bind_param($ins, 'sssssssssss', $mb_id, $hash, $name, $nick, $today, $email, $now, $now, $now, $today, $phone);
    $ok = mysqli_stmt_execute($ins);
    if (!$ok) { fwrite(STDERR, '[실패] ' . mysqli_stmt_error($ins) . "\n"); exit(1); }
    mysqli_stmt_close($ins);
    echo "[생성] 테스트 계정을 만들었습니다.\n";
}

echo "  로그인 이메일: {$email}\n  아이디(mb_id): {$mb_id}\n  이름: {$name}\n";
echo "  (비밀번호는 출력하지 않습니다)\n";
