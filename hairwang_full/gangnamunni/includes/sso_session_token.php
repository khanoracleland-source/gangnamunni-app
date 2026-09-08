<?php

/** Claim a web-session credential; return its member id or an empty string.
 * Both Gnuboard's early extend hook and the page bootstrap use this path.
 * Native access/refresh/exchange rows contain hashes, never SSO bearers.
 */
function sso_claim_session_token($connect, $token, $device_id = '') {
    if (!$connect || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/i', $token)) return '';
    if (!mysqli_begin_transaction($connect)) return '';
    try {
        $stmt = mysqli_prepare($connect,
            "SELECT g5_mb_id, user_type, user_agent FROM sso_tokens
             WHERE token = ? AND user_type IN ('gnuboard', 'hms', 'admin', 'patient_app_bridge')
             AND expires_at > NOW() LIMIT 1 FOR UPDATE");
        if (!$stmt) throw new RuntimeException('SSO lookup prepare failed');
        mysqli_stmt_bind_param($stmt, 's', $token);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('SSO lookup failed');
        $sso = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$sso || empty($sso['g5_mb_id'])) {
            mysqli_rollback($connect);
            return '';
        }
        if ($sso['user_type'] === 'patient_app_bridge') {
            $binding = is_string($device_id) && preg_match('/^[a-f0-9]{64}$/i', $device_id)
                ? 'patient-app-device:' . hash('sha256', strtolower($device_id)) : '';
            if ($binding === '' || !hash_equals((string)$sso['user_agent'], $binding)) {
                mysqli_rollback($connect);
                return '';
            }
            $stmt = mysqli_prepare($connect,
                "DELETE FROM sso_tokens WHERE token = ? AND user_type = 'patient_app_bridge'");
            if (!$stmt) throw new RuntimeException('Bridge consumption prepare failed');
            mysqli_stmt_bind_param($stmt, 's', $token);
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Bridge consumption failed');
            $consumed = mysqli_stmt_affected_rows($stmt) === 1;
            mysqli_stmt_close($stmt);
            if (!$consumed) throw new RuntimeException('Bridge already consumed');
        }
        if (!mysqli_commit($connect)) throw new RuntimeException('SSO commit failed');
        return $sso['g5_mb_id'];
    } catch (Throwable $e) {
        mysqli_rollback($connect);
        error_log('[sso-session] claim failed: ' . get_class($e));
        return '';
    }
}
