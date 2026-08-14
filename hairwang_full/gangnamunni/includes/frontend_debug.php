<?php
// Frontend console debug for admin users only
// Included from footer.php when admin session is detected
if (!isset($connect) || !$connect) return;

// Gather debug info
$_dbg = [];
$_dbg['page'] = basename($_SERVER['SCRIPT_NAME']);
$_dbg['uri'] = $_SERVER['REQUEST_URI'];
$_dbg['method'] = $_SERVER['REQUEST_METHOD'];
$_dbg['php_version'] = PHP_VERSION;
$_dbg['server_os'] = php_uname('s') . ' ' . php_uname('r');
$_dbg['memory'] = round(memory_get_usage(true) / 1048576, 1) . 'MB';
$_dbg['peak_memory'] = round(memory_get_peak_usage(true) / 1048576, 1) . 'MB';
$_dbg['time'] = date('Y-m-d H:i:s');
$_dbg['load_ms'] = round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000);

// DB info
$db_status = @mysqli_stat($connect);
$_dbg['db_status'] = $db_status ? 'connected' : 'disconnected';

// Theme settings count
$_dbg['theme_settings'] = 0;
if (function_exists('getTheme')) {
    global $THEME_SETTINGS;
    $_dbg['theme_settings'] = is_array($THEME_SETTINGS) ? count($THEME_SETTINGS) : 0;
}

// Table counts (quick check)
$_tables = [];
$tables_to_check = ['gangnamunni_hospitals', 'hospital_reviews', 'gangnamunni_tips', 'gangnamunni_events', 'gangnamunni_ads', 'gangnamunni_consultations', 'gangnamunni_bookings'];
foreach ($tables_to_check as $tbl) {
    $r = @mysqli_query($connect, "SELECT COUNT(*) as cnt FROM $tbl");
    if ($r) {
        $row = mysqli_fetch_assoc($r);
        $_tables[$tbl] = (int)$row['cnt'];
    }
}

// Nav settings
$_nav = [];
if (function_exists('getSettingValue')) {
    $_nav['top_mobile'] = getSettingValue($connect, 'nav_top_show_mobile', '0');
    $_nav['top_desktop'] = getSettingValue($connect, 'nav_top_show_desktop', '1');
    $_nav['bottom_mobile'] = getSettingValue($connect, 'nav_bottom_show_mobile', '1');
    $_nav['bottom_desktop'] = getSettingValue($connect, 'nav_bottom_show_desktop', '0');
}

// CSS variables in use
$_css_vars = [];
if (function_exists('getTheme')) {
    $css_keys = ['primary_color','secondary_color','background_color','text_color','border_color','link_color','border_radius','font_korean','font_english','font_size_base','site_name'];
    foreach ($css_keys as $ck) {
        $_css_vars[$ck] = getTheme($ck, '-');
    }
}

// Session info (sanitized)
$_sess = [];
$_sess['admin'] = !empty($_SESSION['ad_admin_logged_in']);
if ($_sess['admin']) {
    $_sess['role'] = $_SESSION['ad_admin_role'] ?? '';
    $_sess['user'] = $_SESSION['ad_admin_user'] ?? '';
}
$_sess['gnuboard'] = isset($is_member) && $is_member;
if (isset($member) && is_array($member)) {
    $_sess['mb_id'] = $member['mb_id'] ?? '';
}

// Output as console script
$_json_dbg = json_encode($_dbg, JSON_UNESCAPED_UNICODE);
$_json_tables = json_encode($_tables, JSON_UNESCAPED_UNICODE);
$_json_nav = json_encode($_nav, JSON_UNESCAPED_UNICODE);
$_json_css = json_encode($_css_vars, JSON_UNESCAPED_UNICODE);
$_json_sess = json_encode($_sess, JSON_UNESCAPED_UNICODE);
?>
<script>
(function(){
    var style = 'color:#ff6b35;font-weight:bold;font-size:13px';
    var page = <?= json_encode($_dbg['page']) ?>;

    console.group('%c\u{1f527} \uc11c\uc6b8\ubbf8\ub140 Debug \u2014 ' + page, style);

    // System info
    console.log('\u{1f4c4} Page:', page);
    console.log('\u23f1\ufe0f Load:', <?= $_dbg['load_ms'] ?> + 'ms');
    console.log('\u{1f4be} Memory:', <?= json_encode($_dbg['memory']) ?>, '(peak:', <?= json_encode($_dbg['peak_memory']) ?> + ')');
    console.log('\u{1f310} Server: PHP', <?= json_encode($_dbg['php_version']) ?>, '|', <?= json_encode($_dbg['server_os']) ?>);
    console.log('\u{1f5c4}\ufe0f DB:', <?= json_encode($_dbg['db_status']) ?>);
    console.log('\u{1f550} Time:', <?= json_encode($_dbg['time']) ?>);
    console.log('\u{1f517} URI:', <?= json_encode($_dbg['uri']) ?>, '[' + <?= json_encode($_dbg['method']) ?> + ']');

    // Session
    console.groupCollapsed('\u{1f511} Session');
    console.table(<?= $_json_sess ?>);
    console.groupEnd();

    // Theme settings
    console.groupCollapsed('\u{1f3a8} Theme/CSS Variables (' + Object.keys(<?= $_json_css ?>).length + ')');
    console.table(<?= $_json_css ?>);
    console.groupEnd();

    // Navigation settings
    console.groupCollapsed('\u{1f9ed} Navigation Settings');
    console.table(<?= $_json_nav ?>);
    console.groupEnd();

    // Table counts
    console.groupCollapsed('\u{1f4ca} DB Table Counts (' + Object.keys(<?= $_json_tables ?>).length + ')');
    console.table(<?= $_json_tables ?>);
    console.groupEnd();

    // Theme settings count
    console.log('\u2699\ufe0f Theme Settings:', <?= $_dbg['theme_settings'] ?>, 'keys loaded');

    // Performance
    if (window.performance && performance.getEntriesByType) {
        var nav = performance.getEntriesByType('navigation')[0];
        if (nav) {
            console.groupCollapsed('\u26a1 Performance');
            console.log('DNS:', Math.round(nav.domainLookupEnd - nav.domainLookupStart) + 'ms');
            console.log('TCP:', Math.round(nav.connectEnd - nav.connectStart) + 'ms');
            console.log('TTFB:', Math.round(nav.responseStart - nav.requestStart) + 'ms');
            console.log('DOM Ready:', Math.round(nav.domContentLoadedEventEnd - nav.startTime) + 'ms');
            console.log('Full Load:', Math.round(nav.loadEventEnd - nav.startTime) + 'ms');
            console.groupEnd();
        }
    }

    // Resource summary
    if (window.performance && performance.getEntriesByType) {
        var resources = performance.getEntriesByType('resource');
        var types = {};
        resources.forEach(function(r) {
            var ext = r.name.split('?')[0].split('.').pop().toLowerCase();
            if (!types[ext]) types[ext] = {count:0, size:0};
            types[ext].count++;
            types[ext].size += r.transferSize || 0;
        });
        console.groupCollapsed('\u{1f4e6} Resources (' + resources.length + ' total)');
        Object.keys(types).sort().forEach(function(ext) {
            console.log(ext + ':', types[ext].count + ' files,', Math.round(types[ext].size/1024) + 'KB');
        });
        console.groupEnd();
    }

    console.groupEnd();
})();
</script>
