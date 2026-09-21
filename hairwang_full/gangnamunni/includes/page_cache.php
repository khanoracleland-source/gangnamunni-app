<?php

/**
 * Lightweight page cache headers for Gangnamunni mobile pages.
 *
 * This does not store HTML on disk. It lets browsers/WebViews reuse recent
 * pages and validates unchanged responses with ETag/304.
 */
function gangnamunni_page_cache($max_age, $scope = 'public') {
    if (headers_sent()) return;

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['GET', 'HEAD'], true)) {
        header('Cache-Control: no-store, max-age=0');
        return;
    }

    $max_age = max(0, (int)$max_age);
    $scope = $scope === 'private' ? 'private' : 'public';

    if ($max_age <= 0) {
        header('Cache-Control: no-store, max-age=0');
        return;
    }

    header('Cache-Control: ' . $scope . ', max-age=' . $max_age . ', stale-while-revalidate=60');
    header('Vary: Accept-Encoding, Cookie');

    if (!ob_get_level()) {
        ob_start();
    }

    register_shutdown_function(static function () {
        if (headers_sent()) return;
        if (function_exists('_app_native_auth_response_is_non_html') && _app_native_auth_response_is_non_html()) return;

        $status = http_response_code();
        if ($status >= 300 && $status !== 304) return;

        $body = ob_get_contents();
        if ($body === false) return;

        $etag = '"' . md5($body) . '"';
        header('ETag: ' . $etag);

        $client_etag = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($client_etag === $etag) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(304);
        }
    });
}
