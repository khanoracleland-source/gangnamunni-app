<?php

if (!isset($_SERVER['QUERY_STRING'])) $_SERVER['QUERY_STRING'] = '';
if (!isset($_SERVER['REMOTE_ADDR'])) $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$g5_common_path = dirname(__DIR__, 2) . '/common.php';
if (file_exists($g5_common_path) && !defined('G5_PATH')) {
    @include_once $g5_common_path;
}


require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/theme_helper.php';
require_once dirname(__DIR__) . '/category_icons.php';
require_once __DIR__ . '/figma_block_helper.php';
if (!function_exists('term')) { require_once dirname(__DIR__) . '/includes/industry_terms.php'; }

if (!isset($connect) || !$connect) {
    if (defined('G5_MYSQL_HOST')) {
        $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
        if ($connect) {
            mysqli_set_charset($connect, 'utf8mb4');
        }
    }
}

if (isset($connect) && $connect && file_exists(__DIR__ . '/sso_auto_login.php')) {
    @include_once __DIR__ . '/sso_auto_login.php';
}

if (isset($connect) && $connect) {
    loadThemeSettings($connect);
}

/**
 * 이 요청의 응답이 JSON(또는 그 외 non-HTML)으로 이미 선언됐는지 확인한다.
 * gangnamunni 안의 여러 페이지가 부트스트랩 편의를 위해 이 init.php를
 * 그대로 재사용하면서 JSON을 응답으로 내보낸다 — 그런 응답 뒤에 <script>를
 * 이어붙이면 앱/페이지의 JSON.parse()가 깨진다. Content-Type 헤더가 아직
 * 없다면(가장 흔한 경우: 헤더를 명시적으로 설정하지 않은 일반 HTML 페이지)
 * 안전하다고 본다.
 */
function _app_native_auth_response_is_non_html() {
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') !== 0) continue;
        return stripos($header, 'text/html') === false;
    }
    return false;
}

if (!empty($_SESSION['app_exchange_window_until']) && isset($connect) && $connect) {
    register_shutdown_function(function () use ($connect) {
        $until = (int)($_SESSION['app_exchange_window_until'] ?? 0);
        if ($until <= 0) return;
        if (time() >= $until) {
            unset($_SESSION['app_exchange_window_until']);
            return;
        }
        if (_app_native_auth_response_is_non_html()) return; // JSON/API 응답을 훼손하지 않는다

        global $is_member, $member;
        if (empty($is_member) || empty($member['mb_id'])) return;
        if (!function_exists('app_issue_exchange_code')) {
            $helper = __DIR__ . '/app_native_auth.php';
            if (!file_exists($helper)) return;
            require_once $helper;
        }

        try {
            $code = app_issue_exchange_code($connect, $member['mb_id']);
        } catch (Throwable $e) {
            error_log('[app-native-auth] exchange code emission failed: ' . $e->getMessage());
            return;
        }
        if ($code === '') return;

        $escaped = htmlspecialchars($code, ENT_QUOTES);
        echo "<script>(function(){if(window.FlutterBridge){FlutterBridge.postMessage(JSON.stringify({action:'authExchange',code:'" . $escaped . "'}));}})();</script>";
    });
}

if (!empty($_GET['app_logout'])) {
    register_shutdown_function(function () {
        if (_app_native_auth_response_is_non_html()) return; // JSON/API 응답을 훼손하지 않는다
        echo "<script>(function(){if(window.FlutterBridge){FlutterBridge.postMessage(JSON.stringify({action:'logout'}));}})();</script>";
    });
}

function is_gangnamunni_logged_in() {
    global $is_member, $member;
    return isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];
}

function get_gangnamunni_member() {
    global $member;
    return $member ?? [];
}

function get_gangnamunni_member_id() {
    global $member;
    return (isset($member) && isset($member['mb_id'])) ? $member['mb_id'] : null;
}

function require_login($redirect_url = null) {
    if (!is_gangnamunni_logged_in()) {
        $login_url = 'auth/login.php';
        if ($redirect_url) {
            $login_url .= '?url=' . urlencode($redirect_url);
        }
        header('Location: ' . $login_url);
        exit;
    }
}

function is_platform_logged_in() { return is_gangnamunni_logged_in(); }
function get_platform_member() { return get_gangnamunni_member(); }
function get_platform_member_id() { return get_gangnamunni_member_id(); }
