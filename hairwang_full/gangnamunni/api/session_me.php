<?php
/**
 * GET /api/session_me.php — who is logged in for THIS cookie session (JSON).
 *
 * Auth authority during the Next.js migration stays in PHP (Gnuboard session +
 * sso_auto_login remember token + intercept checks in includes/init.php). The
 * NestJS API and the Next.js server call this endpoint server-side, forwarding the
 * browser's Cookie header, instead of re-implementing session logic.
 *
 * Read-only: never issues or rotates tokens itself (init.php may refresh the
 * remember-login session exactly as a normal page view would).
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

// init.php / sso_auto_login.php may echo <script> bridge tags meant for HTML pages:
// capture and discard anything they print so the JSON body stays clean. The JSON
// Content-Type set above also makes init.php's shutdown hook skip its <script> echo.
ob_start();
try {
    require_once __DIR__ . '/../includes/init.php';
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(503);
    error_log('[session_me] init failed: ' . $e->getMessage());
    echo json_encode(['error' => 'session_unavailable']);
    exit;
}
ob_end_clean();
// Re-assert in case an include replaced it.
header('Content-Type: application/json; charset=UTF-8');

$out = ['loggedIn' => false, 'user' => null];
if (function_exists('is_gangnamunni_logged_in') && is_gangnamunni_logged_in()) {
    $m = get_gangnamunni_member();
    $out = [
        'loggedIn' => true,
        'user' => [
            'id' => (string)($m['mb_id'] ?? ''),
            // Numeric member no: gangnamunni_wishlist.member_id uses this, not mb_id.
            'no' => (int)($m['mb_no'] ?? 0),
            'name' => (string)($m['mb_name'] ?? ''),
            'nickname' => (string)($m['mb_nick'] ?? ''),
            'level' => (int)($m['mb_level'] ?? 0),
            // Prefill for forms the member fills in themselves (상담 신청); no email.
            'phone' => (string)($m['mb_hp'] ?? ''),
        ],
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
