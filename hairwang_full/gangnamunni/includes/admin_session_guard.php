<?php
/**
 * 관리자 세션 실시간 검증 (2026-10-16)
 *
 * 관리자 세션($_SESSION['ad_admin_*'])은 로그인 시점의 스냅샷이다. 계정이 삭제·비활성화되거나
 * 역할/소속 병원이 바뀌거나 비밀번호가 바뀌어도 세션은 그대로 남아 있었다.
 * 이 파일은 현재 세션을 DB(gangnamunni_admins)의 현재 행과 대조하고, 어긋나면 관리자 키를 지운다.
 *
 * 사용: session_start() 직후
 *   require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard();
 * check_admin_login()(admin_auth.php)도 내부적으로 sb_admin_session_guard() 를 쓴다.
 *
 * 의존성 없음(admin_auth.php 를 로드하지 않음) — 함수명 충돌을 피하려고 sb_ 접두사 사용.
 */

/** 요청당 1회 연결. 연결 불가면 null. */
function sb_admin_db() {
    static $db = null;
    if ($db !== null) return $db ?: null;
    if (!empty($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) {
        return $GLOBALS['connect'];
    }
    if (!defined('G5_MYSQL_HOST')) {
        $cfg = dirname(__DIR__, 2) . '/dbconfig.php';
        if (is_file($cfg)) {
            require_once $cfg;
            // 함수 스코프에서 로드됐어도 dbconfig 의 전역 변수를 쓰는 페이지가 깨지지 않도록 전역으로 내보낸다.
            foreach (array('_DB_HOST', '_DB_PORT', '_DB_USER', '_DB_PASSWORD', '_DB_NAME', '_override_file') as $v) {
                if (isset($$v) && !isset($GLOBALS[$v])) $GLOBALS[$v] = $$v;
            }
        }
    }
    if (!defined('G5_MYSQL_HOST')) { $db = false; return null; }
    try {
        $db = @mysqli_connect(
            G5_MYSQL_HOST,
            defined('G5_MYSQL_USER') ? G5_MYSQL_USER : '',
            defined('G5_MYSQL_PASSWORD') ? G5_MYSQL_PASSWORD : '',
            defined('G5_MYSQL_DB') ? G5_MYSQL_DB : '',
            defined('G5_MYSQL_PORT') ? (int) G5_MYSQL_PORT : 3306
        ) ?: false;
        if ($db) mysqli_set_charset($db, 'utf8mb4');
    } catch (Throwable $e) {
        $db = false;
    }
    return $db ?: null;
}

/**
 * @return array|false|null  array = 현재 행, false = 계정 없음(삭제됨), null = 확인 불가
 */
function sb_admin_live_row($admin_id, $connect = null) {
    static $cache = array();
    $admin_id = (int) $admin_id;
    if ($admin_id <= 0) return false;
    if (array_key_exists($admin_id, $cache)) return $cache[$admin_id];

    $db = ($connect instanceof mysqli) ? $connect : sb_admin_db();
    if (!$db) return null;

    $row = null;
    try {
        // SELECT * — cred_epoch 컬럼 미적용 환경에서도 동작
        $stmt = mysqli_prepare($db, "SELECT * FROM gangnamunni_admins WHERE id = ? LIMIT 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $admin_id);
            if (mysqli_stmt_execute($stmt)) {
                $r = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                $row = $r ? $r : false;
            }
            mysqli_stmt_close($stmt);
        }
    } catch (Throwable $e) {
        return null;
    }
    if ($row === null) return null;
    $cache[$admin_id] = $row;
    return $row;
}

/** 관리자 키만 제거(같은 세션의 그누보드 회원 로그인은 유지). */
function sb_admin_session_clear($reason = '') {
    $id = (int) ($_SESSION['ad_admin_id'] ?? 0);
    foreach (array_keys($_SESSION) as $k) {
        if (strpos((string) $k, 'ad_admin_') === 0) unset($_SESSION[$k]);
    }
    unset($_SESSION['ad_cred_epoch'], $_SESSION['partner_hospital_id']);
    if ($reason !== '') {
        error_log('[admin-session-revoked] admin_id=' . $id . '; reason=' . preg_replace('/[^a-z_]/', '', $reason));
    }
}

/**
 * 현재 세션의 관리자 로그인이 아직 유효한지 DB 와 대조한다.
 * 무효면 관리자 키를 지우고 false. 관리자 세션이 아니면 false. 유효(또는 DB 확인 불가)면 true.
 */
function sb_admin_session_guard($connect = null) {
    // session_write_close() 이후에도 $_SESSION 이 남아 있으면 검사한다(결과만 반환, 저장은 안 됨).
    if (!isset($_SESSION) || !is_array($_SESSION)) return false;
    if (!isset($_SESSION['ad_admin_logged_in']) || $_SESSION['ad_admin_logged_in'] !== true) return false;

    // 같은 요청에서 같은 세션은 한 번만 검사
    static $checked = array();
    $sid = session_name() . ':' . session_id();
    if (isset($checked[$sid])) return $checked[$sid];

    if (empty($_SESSION['ad_admin_id'])) {
        sb_admin_session_clear('no_admin_id');
        return $checked[$sid] = false;
    }

    $row = sb_admin_live_row($_SESSION['ad_admin_id'], $connect);
    if ($row === null) {
        // DB 확인 불가 — 일시적 장애로 전원 로그아웃되지 않도록 통과(캐시하지 않음)
        return true;
    }

    $reason = '';
    if ($row === false) {
        $reason = 'deleted';
    } elseif (array_key_exists('is_active', $row) && (int) $row['is_active'] !== 1) {
        $reason = 'inactive';
    } elseif ((string) ($row['role'] ?? '') !== (string) ($_SESSION['ad_admin_role'] ?? '')) {
        $reason = 'role_changed';
    } elseif ((int) ($row['hospital_id'] ?? 0) !== (int) ($_SESSION['ad_admin_hospital_id'] ?? 0)) {
        $reason = 'hospital_changed';
    } elseif (array_key_exists('cred_epoch', $row) && (int) ($_SESSION['ad_cred_epoch'] ?? 0) !== (int) $row['cred_epoch']) {
        $reason = 'cred_epoch'; // 비밀번호 변경(임시 비밀번호 발급 포함)
    }

    if ($reason !== '') {
        sb_admin_session_clear($reason);
        return $checked[$sid] = false;
    }

    // 권한에 영향 없는 표시용 필드는 최신값으로 동기화
    if (array_key_exists('name', $row)) $_SESSION['ad_admin_name'] = $row['name'];
    if (array_key_exists('hospital_name', $row)) $_SESSION['ad_admin_hospital_name'] = (string) $row['hospital_name'];
    if (array_key_exists('permissions', $row)) $_SESSION['ad_admin_permissions'] = json_decode($row['permissions'] ?? '{}', true) ?: [];

    return $checked[$sid] = true;
}
