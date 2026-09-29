<?php

// Only explicitly mounted user content is cached, never a whole HTML document.
function user_content_cache_enabled() {
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
        && empty($_SESSION['ad_admin_logged_in'])
        && empty($GLOBALS['is_admin'])
        && empty($_GET['server_render']);
}

function user_content_cache_scope() {
    return hash('sha256', session_id() . ':' . ($_SESSION['ss_mb_id'] ?? 'guest'));
}

function user_content_cache_reply($render) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        echo json_encode(['error' => 'GET required']);
        exit;
    }
    $level = ob_get_level();
    ob_start();
    try {
        $render();
        $html = ob_get_clean();
        echo json_encode(['scope' => user_content_cache_scope(), 'html' => $html], JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        while (ob_get_level() > $level) ob_end_clean();
        http_response_code(503);
        error_log('[user-content-cache] ' . $error->getMessage());
        echo json_encode(['error' => 'Content unavailable']);
    }
    exit;
}

function user_content_cache_mount($name) {
    $scope = htmlspecialchars(user_content_cache_scope(), ENT_QUOTES, 'UTF-8');
    $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $fallback = htmlspecialchars('?' . http_build_query(array_merge($_GET, ['server_render' => 1])), ENT_QUOTES, 'UTF-8');
    echo '<section data-user-cache="' . $name . '" data-cache-scope="' . $scope . '">'
        . '<div data-cache-content></div>'
        . '<div data-cache-status role="status" aria-live="polite"></div>'
        . '<noscript><a href="' . $fallback . '">목록 보기</a></noscript></section>';
}
