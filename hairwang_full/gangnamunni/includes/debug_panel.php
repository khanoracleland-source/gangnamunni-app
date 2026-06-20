<?php
/**
 * 디버그 콘솔 패널 — 관리자 로그인 시 브라우저 콘솔에 디버그 정보 출력
 * includes/admin_footer.php + includes/bottom_navigation.php에서 include
 *
 * @created 2026-03-05
 */

// 조건: 관리자 로그인 시에만
if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) return;

// DB에서 debug 설정 체크 ($connect 변수가 있을 때만)
if (isset($connect) && $connect instanceof mysqli && @mysqli_ping($connect)) {
    $dbg_r = @mysqli_query($connect, "SELECT setting_value FROM gangnamunni_settings WHERE setting_key='debug_console_enabled' LIMIT 1");
    if ($dbg_r && $dbg_row = mysqli_fetch_assoc($dbg_r)) {
        if ($dbg_row['setting_value'] !== '1') return;
    }
}

// 정보 수집
$_debug_data = [
    'page'     => basename($_SERVER['SCRIPT_NAME'] ?? 'unknown'),
    'query'    => $_SERVER['QUERY_STRING'] ?? '',
    'load_ms'  => round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000),
    'mem_mb'   => round(memory_get_usage(true) / 1048576, 1),
    'mem_peak' => round(memory_get_peak_usage(true) / 1048576, 1),
    'php_ver'  => PHP_VERSION,
    'server_os'=> php_uname('s') . ' ' . php_uname('r'),
    'time'     => date('Y-m-d H:i:s'),
    'user_id'  => $_SESSION['ad_admin_id'] ?? 0,
    'user_name'=> $_SESSION['ad_admin_name'] ?? '',
    'user_role'=> $_SESSION['ad_admin_role'] ?? 'unknown',
    'request'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri'      => $_SERVER['REQUEST_URI'] ?? '',
];

// 세션 (민감 정보 마스킹)
$_debug_session = [];
foreach ($_SESSION as $k => $v) {
    if (stripos($k, 'password') !== false || stripos($k, 'token') !== false || stripos($k, 'secret') !== false) {
        $_debug_session[$k] = '***';
    } else {
        $_debug_session[$k] = is_scalar($v) ? $v : gettype($v) . '(' . (is_array($v) ? count($v) : '') . ')';
    }
}
$_debug_data['session'] = $_debug_session;

// GET 파라미터
$_debug_data['get_params'] = $_GET;

// POST 파라미터 (민감 정보 마스킹)
$_debug_post = [];
foreach ($_POST as $k => $v) {
    if (stripos($k, 'pass') !== false || stripos($k, 'token') !== false || stripos($k, 'secret') !== false) {
        $_debug_post[$k] = '***';
    } else {
        $_debug_post[$k] = is_scalar($v) ? (mb_strlen($v) > 100 ? mb_substr($v, 0, 100) . '...' : $v) : gettype($v);
    }
}
$_debug_data['post_params'] = $_debug_post;

// DB 연결 상태
if (isset($connect) && $connect instanceof mysqli && @mysqli_ping($connect)) {
    $_debug_data['db_status'] = mysqli_ping($connect) ? 'connected' : 'disconnected';
    $_debug_data['db_host'] = mysqli_get_host_info($connect);
} else {
    $_debug_data['db_status'] = 'no $connect';
}

$_debug_json = json_encode($_debug_data, JSON_UNESCAPED_UNICODE);
?>
<script>
(function(){
try{
var d=<?php echo $_debug_json; ?>;
var style='color:#ff6b35;font-weight:bold;font-size:13px';
console.group('%c\uD83D\uDD27 \uC11C\uC6B8\uBBF8\uB140 Debug \u2014 '+d.page, style);
console.log('\uD83D\uDCC4 Page:', d.page + (d.query ? '?' + d.query : ''));
console.log('\u23F1\uFE0F Load:', d.load_ms + 'ms');
console.log('\uD83D\uDCBE Memory:', d.mem_mb + 'MB (peak: ' + d.mem_peak + 'MB)');
console.log('\uD83D\uDC64 User:', d.user_name || 'N/A', '(' + d.user_role + ') ID:' + d.user_id);
console.log('\uD83C\uDF10 Server: PHP ' + d.php_ver + ' | ' + d.server_os);
console.log('\uD83D\uDDC4\uFE0F DB:', d.db_status, d.db_host || '');
console.log('\uD83D\uDD50 Time:', d.time);
console.log('\uD83D\uDD17 URI:', d.uri, '[' + d.request + ']');
if(d.get_params && Object.keys(d.get_params).length > 0){
    console.log('\uD83D\uDCCB GET:', d.get_params);
}
if(d.post_params && Object.keys(d.post_params).length > 0){
    console.log('\uD83D\uDCCB POST:', d.post_params);
}
if(d.session){
    console.groupCollapsed('\uD83D\uDD11 Session (' + Object.keys(d.session).length + ' keys)');
    console.table(d.session);
    console.groupEnd();
}
console.groupEnd();
}catch(e){console.warn('Debug panel error:', e);}
})();
</script>
