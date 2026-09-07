<?php
// Run: php -n tests/regression/mypage_login_redirect.php
// Exercise the production login gate without a database or real sessions.
namespace MyPageLoginRegression;
const G5_URL = 'https://example.test/hairwang';
class Redirect extends \RuntimeException {}
function header($value) { throw new Redirect($value); }
function check($condition, $message) {
    if (!$condition) throw new \RuntimeException($message);
}
$source = file_get_contents(__DIR__ . '/../../hairwang_full/gangnamunni/mypage.php');
$start = strpos($source, '// 로그인 체크');
$end = strpos($source, '// 프로필 사진 확인');
check($start !== false && $end > $start, 'Login gate must exist');
$gate = substr($source, $start, $end - $start);
check(strpos($source, "/includes/sso_auto_login.php") < $start, 'SSO restoration precedes login gate');
foreach ([false, true] as $app) {
    foreach ([false, true] as $loggedIn) {
        $is_app_mode = $app;
        $is_member = $loggedIn;
        $member = $loggedIn ? ['mb_id' => 'test_patient', 'mb_no' => 7] : [];
        $redirect = null;
        $mb_id = null;
        ob_start();
        try {
            eval('namespace ' . __NAMESPACE__ . ';' . $gate);
        } catch (Redirect $e) {
            $redirect = $e->getMessage();
        }
        $output = ob_get_clean();
        check($output === '', 'Gate must not emit JSON or error output');
        if (!$loggedIn && !$app) {
            check($redirect === 'Location: ' . G5_URL . '/gangnamunni/auth/login.php?url=' . urlencode(G5_URL . '/gangnamunni/mypage.php'), 'Web guest goes to login with a return URL');
        } else {
            check($redirect === null, 'App guests and logged-in members must not redirect');
            check($mb_id === ($loggedIn ? 'test_patient' : ''), 'Correct member or guest render context');
            check((bool)$is_logged_in === $loggedIn, 'App guest must render the login prompt, not member content');
        }
    }
}
check(strpos($source, "$" . "login_return = G5_URL . '/gangnamunni/mypage.php' . ($" . "is_app_mode ? '?app=1' : '');") !== false, 'Guest login link preserves app=1');
echo "PASS: web guest redirect, WebView guest HTML path, member access, SSO order, app return URL.\n";
