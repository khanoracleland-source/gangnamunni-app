<?php
if (!defined('_GNUBOARD_')) exit;

/**
 * seoulbeauty-api keeps its own sign-in session next to the PHP one (cookie sb_sid, table sb_sessions; see
 * gangnamunni/sql/sb_sessions_20261112.sql and seoulbeauty-api NestSessionService). The Next.js pages trust that session
 * without asking PHP, so whenever PHP ends a sign-in or signs a (different) member in, the Nest session of this browser
 * has to go too. Otherwise Next would keep showing the member PHP has just signed out.
 *
 * Only a DELETE by the hash of the visitor's own cookie; no cookie = nothing happens. A missing table is ignored.
 */
add_event('member_logout', 'sb_nest_session_drop', 10, 0);
// a PHP login (auth/login.php, bbs/login_check.php, the session bridge): Nest opens its own session after the bridge, any other
// PHP login (partner / admin screens, "sign in as member") simply leaves the browser without one and Next asks PHP again
add_event('member_login_check', 'sb_nest_session_drop', 1, 0);

function sb_nest_session_drop()
{
    $token = isset($_COOKIE['sb_sid']) ? (string)$_COOKIE['sb_sid'] : '';
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return;
    try {
        global $g5;
        $link = isset($g5['connect_db']) ? $g5['connect_db'] : (isset($GLOBALS['connect']) ? $GLOBALS['connect'] : null);
        if ($link) {
            $stmt = @mysqli_prepare($link, 'DELETE FROM sb_sessions WHERE token_hash = ?');
            if ($stmt) {
                $hash = hash('sha256', $token);
                mysqli_stmt_bind_param($stmt, 's', $hash);
                @mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    } catch (Throwable $e) {
        error_log('[sb-nest-session] ' . $e->getMessage());
    }
    // remove the cookie in both forms it can have (host-only, or the shared session-cookie domain)
    if (!headers_sent()) {
        setcookie('sb_sid', '', array('expires' => time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'));
        $host = strtolower(preg_replace('/:\d+$/', '', (string)(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '')));
        $parts = preg_match('/^[a-z0-9.-]+$/', $host) && !preg_match('/^\d+(\.\d+){3}$/', $host) ? array_values(array_filter(explode('.', $host))) : array();
        if (count($parts) >= 2) {
            setcookie('sb_sid', '', array('expires' => time() - 3600, 'path' => '/', 'domain' => '.' . implode('.', array_slice($parts, -2)), 'secure' => true, 'httponly' => true, 'samesite' => 'Lax'));
        }
    }
    unset($_COOKIE['sb_sid']);
}
