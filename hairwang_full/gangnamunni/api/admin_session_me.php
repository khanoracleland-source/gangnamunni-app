<?php
/**
 * GET /api/admin_session_me.php — which ADMIN is signed in for THIS cookie session (JSON).
 *
 *   200 {"loggedIn":false,"admin":null}
 *   200 {"loggedIn":true,"admin":{"id":1,"username":"…","name":"…","role":"super_admin","hospitalId":null}}
 *   503 {"error":"session_unavailable"}   the account could not be checked against the database
 *
 * The admin login itself stays in the PHP admin pages (dashboard.php). The admin panel API calls this endpoint
 * server-side, forwarding the browser's Cookie header, instead of re-implementing the admin session.
 *
 * The session is opened exactly like dashboard.php does: a bare session_start() (default session name and cookie
 * parameters), NOT the Gnuboard member session of includes/init.php — the admin keys ($_SESSION['ad_admin_*']) live there.
 *
 * Read-only: no login, no SSO auto-login, no token is issued or rotated and no query other than the SELECT of
 * check_admin_login() runs. As on every admin page, check_admin_login() drops the admin keys from the session when the
 * account was deleted, deactivated, re-assigned or its password changed, and refreshes the cached display name.
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'HEAD') {
    http_response_code(405);
    header('Allow: GET, HEAD');
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$out = ['loggedIn' => false, 'admin' => null];

// No session cookie: nobody is signed in. Answer without starting (and thereby creating) a session.
if (empty($_COOKIE[session_name()]) || !is_string($_COOKIE[session_name()])) {
    echo json_encode($out);
    exit;
}

// Includes must not leak output (warnings, BOM) into the JSON body.
ob_start();
$connect = null;
try {
    session_start();

    require_once __DIR__ . '/../../dbconfig.php';
    require_once __DIR__ . '/../includes/admin_auth.php';

    if (isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true) {
        // check_admin_login() lets a session through when it cannot reach the database (so an outage does not sign every
        // admin out of the PHP pages). Here the answer grants access elsewhere, so no database means no answer.
        try {
            $connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
        } catch (Throwable $e) {
            $connect = false;
        }
        if (!$connect) {
            throw new RuntimeException('database unreachable');
        }
        mysqli_set_charset($connect, 'utf8mb4');
        if (!mysqli_query($connect, 'SELECT 1 FROM gangnamunni_admins LIMIT 1')) {
            throw new RuntimeException('admin table unreadable');
        }

        if (check_admin_login()) {
            $a = get_current_admin();
            $hospital_id = (int)($a['hospital_id'] ?? 0);
            $out = [
                'loggedIn' => true,
                'admin' => [
                    'id' => (int)$a['id'],
                    'username' => (string)$a['username'],
                    'name' => (string)$a['name'],
                    'role' => (string)$a['role'],
                    'hospitalId' => $hospital_id > 0 ? $hospital_id : null,
                ],
            ];
        }
    }
    // Release the session lock before answering.
    session_write_close();
} catch (Throwable $e) {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    ob_end_clean();
    http_response_code(503);
    error_log('[admin_session_me] ' . get_class($e) . ': ' . $e->getMessage());
    echo json_encode(['error' => 'session_unavailable']);
    exit;
}
ob_end_clean();
// Re-assert in case an include replaced them.
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: private, no-store');

echo json_encode($out, JSON_UNESCAPED_UNICODE);
