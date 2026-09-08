<?php
/**
 * 환자앱 네이티브 인증 (accessToken/refreshToken)
 *
 * 로그인 자체는 여전히 login.php(이메일/카카오/애플)가 담당한다. 이 파일은
 * "이미 인증된 웹 세션"을 앱의 FlutterSecureStorage에 넣을 네이티브 토큰으로
 * 교환하는 다리 역할만 한다 — 어떤 로그인 방식으로 세션이 생겼는지와 무관하게
 * 동일하게 동작한다.
 *
 * 토큰 종류 (모두 sso_tokens 테이블, user_type으로 구분되어 서로 침범하지 않음):
 *   - patient_app_exchange : 초단기(90초) 코드. WebView -> 앱 전달 전용. 최초로
 *     제출한 기기에 청구(claim)되고, 그 기기의 재제출은 저장된 payload를
 *     그대로 재응답한다(응답 유실 복구용) — 다른 기기의 제출이나 로그아웃 후
 *     재사용은 거부된다.
 *   - patient_app_access   : 단기(1시간) accessToken. 기기에 바인딩됨.
 *   - patient_app_remember : 기존 120일 refreshToken 메커니즘을 그대로 재사용.
 *
 * WebView recovery uses a device-bound patient_app_bridge token, consumed
 * once by sso_auto_login.php. It is separate from the generic SSO channel.
 *
 * @created 2026-09-07
 */

// common.lib.php는 _GNUBOARD_ 미정의 시 즉시 exit한다 (Gnuboard 부트스트랩 밖에서
// 로드될 때를 대비한 가드). API 엔드포인트처럼 전체 앱을 띄우지 않는 컨텍스트에서도
// create_remember_login_token()/verify_remember_login_token()을 쓸 수 있어야 한다.
if (!defined('_GNUBOARD_')) define('_GNUBOARD_', true);
require_once __DIR__ . '/../../lib/common.lib.php';

/**
 * common.lib.php의 is_use_email_certify()는 전역 $config에 의존하는데,
 * 전체 Gnuboard 부트스트랩(common.php)을 거치지 않는 이 API 컨텍스트에서는
 * $config가 절대 채워지지 않는다 — function_exists('is_use_email_certify')는
 * true를 반환하지만 실제로는 항상 "인증 불필요"로 조용히 오판했었다.
 * g5_config 한 행만 직접 읽어 채워서, 사이트가 이메일 인증을 요구할 때
 * native accessToken/refreshToken 경로도 일반 로그인과 동일하게 걸러지게 한다.
 */
function app_native_auth_ensure_config($connect) {
    global $config;
    static $loaded = false;
    if ($loaded || !$connect) return;
    $result = mysqli_query($connect, 'SELECT * FROM g5_config LIMIT 1');
    if (!$result) throw new RuntimeException('Auth configuration lookup failed');
    $row = mysqli_fetch_assoc($result);
    mysqli_free_result($result);
    if (!$row) throw new RuntimeException('Auth configuration is missing');
    $loaded = true;
    if (!is_array($config)) $config = array();
    if ($row) $config += $row; // 이미 설정된 키는 덮어쓰지 않는다
}

define('APP_ACCESS_TOKEN_LIFETIME', 3600);        // 1시간
define('APP_EXCHANGE_CODE_LIFETIME', 90);         // 90초, 1회용
define('APP_REFRESH_TOKEN_LIFETIME', 86400 * 120); // 120일 (기존 patient_app_lifetime과 동일)
define('APP_SESSION_BRIDGE_LIFETIME', 300);       // 5분, 기기 바인딩 + 1회용

/** Standalone APIs do not load config.php. Match the web bootstrap's clock
 * and SQL DATETIME timezone before issuing or comparing any credential.
 */
function app_native_auth_configure_connection($connect) {
    date_default_timezone_set('Asia/Seoul');
    if (!defined('G5_SERVER_TIME')) define('G5_SERVER_TIME', time());
    if (!mysqli_query($connect, "SET time_zone = '+09:00'")) {
        throw new RuntimeException('Auth timezone initialization failed');
    }
}

/**
 * 기기 식별자를 검증한다 (sb_app_device와 동일한 64자리 hex 규칙).
 */
function app_native_auth_is_valid_device_id($device_id) {
    return is_string($device_id) && preg_match('/^[a-f0-9]{64}$/i', $device_id) === 1;
}

/** Reuse one code across page navigations in the same login window.
 * Reusing the code makes lost-response retries idempotent and avoids
 * unnecessary token issuance. Call before session shutdown to persist it.
 */
function app_session_exchange_code($connect, $mb_id) {
    $until = (int)($_SESSION['app_exchange_window_until'] ?? 0);
    if ($until <= time() || $mb_id === '') return '';
    $cached = $_SESSION['app_native_exchange'] ?? null;
    if (is_array($cached) && ($cached['mb_id'] ?? '') === $mb_id
        && ($cached['window_until'] ?? 0) === $until) {
        return $cached['code'];
    }
    $code = app_issue_exchange_code($connect, $mb_id);
    if ($code !== '') {
        $_SESSION['app_native_exchange'] = array(
            'mb_id' => $mb_id, 'window_until' => $until, 'code' => $code);
    }
    return $code;
}

/**
 * 로그인 직후(웹 세션) 발급하는 1회용 교환 코드.
 * WebView의 JS 브릿지가 이 코드만 앱으로 전달하고, 실제 accessToken/refreshToken은
 * 앱이 이 코드를 들고 서버에 HTTPS로 직접 요청해 응답 바디로만 받는다.
 */
function app_issue_exchange_code($connect, $mb_id) {
    if (!$connect || $mb_id === '') return '';

    $raw_code = bin2hex(random_bytes(32));
    $code_hash = hash('sha256', $raw_code);
    $expires = date('Y-m-d H:i:s', G5_SERVER_TIME + APP_EXCHANGE_CODE_LIFETIME);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // 이전에 소비되지 않은 코드가 남아있어도 상관없다 — 각자 독립적으로 90초 후 만료된다.
    // 다만 무한히 쌓이지 않도록 이미 만료된 것만 정리한다.
    $cleanup = mysqli_prepare($connect,
        "DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = 'patient_app_exchange' AND expires_at <= NOW()");
    if ($cleanup) {
        mysqli_stmt_bind_param($cleanup, 's', $mb_id);
        mysqli_stmt_execute($cleanup);
        mysqli_stmt_close($cleanup);
    }

    $insert = mysqli_prepare($connect,
        "INSERT INTO sso_tokens (token, user_type, g5_mb_id, ip_address, expires_at, created_at)
         VALUES (?, 'patient_app_exchange', ?, ?, ?, NOW())");
    if (!$insert) {
        error_log('[app-native-auth] exchange code prepare failed: ' . mysqli_error($connect));
        return '';
    }
    mysqli_stmt_bind_param($insert, 'ssss', $code_hash, $mb_id, $ip, $expires);
    $ok = mysqli_stmt_execute($insert);
    mysqli_stmt_close($insert);

    return $ok ? $raw_code : '';
}

/**
 * 교환 코드를 검증하고, 어느 기기가 이 코드를 처음 제출했는지 원자적으로
 * "청구(claim)"한다.
 *
 * 예전에는 SELECT ... FOR UPDATE + 즉시 삭제로 "엄격한 1회용"을 구현했지만,
 * 그 설계는 서버가 코드를 이미 처리해 성공 응답을 만든 "직후"에 네트워크가
 * 끊기는 흔한 경우를 복구 불가능하게 만들었다. 그렇다고 기기 구분 없이
 * 재사용을 허용하면, 코드를 가로챈 *다른* 기기도 새 세션을 열 수 있고
 * 로그아웃해도 남은 유효시간 동안 그 코드로 다시 로그인할 수 있었다.
 *
 * 그래서 코드는 최초로 제출한 기기에 청구된다(UPDATE ... WHERE user_agent
 * IS NULL — 두 기기가 동시에 제출해도 이 UPDATE 자체가 원자적이라 한쪽만
 * 성공한다). 이후:
 *   - 같은 기기가 같은 코드를 다시 제출 -> 유효하며(만료 전), 청구된 기기와
 *     일치 -> 통과. app_auth_exchange.php는 이때 저장된 payload를 그대로
 *     재응답해 응답 유실을 안전하게 복구한다.
 *   - 다른 기기가 같은 코드를 제출 -> 청구된 기기와 불일치 -> 거부.
 *   - 로그아웃(app_revoke_native_auth_tokens)은 이 청구된 행 자체를
 *     지우므로, 로그아웃 후 같은 코드로는 (남은 유효시간이 있어도) 다시
 *     통과할 수 없다.
 *
 * 성공 시 ['mb_id' => ..., 'payload' => 이전에 저장된 JSON 문자열 또는 null]
 * 를 반환하고, 실패 시 ['mb_id' => '', 'payload' => null]을 반환한다.
 */
function app_consume_exchange_code($connect, $raw_code, $device_id) {
    $none = array('mb_id' => '', 'payload' => null);
    if (!$connect || !is_string($raw_code) || !preg_match('/^[a-f0-9]{64}$/i', $raw_code)) return $none;
    if (!app_native_auth_is_valid_device_id($device_id)) return $none;

    $code_hash = hash('sha256', $raw_code);
    $ua = 'patient-app-device:' . hash('sha256', strtolower($device_id));

    // 아직 아무도 청구하지 않았다면 이 기기가 청구한다. WHERE user_agent IS
    // NULL이 그 자체로 원자적 청구 연산이다 — 동시에 두 기기가 이 문을
    // 두드려도 UPDATE는 한쪽에만 적용된다.
    $claim = mysqli_prepare($connect,
        "UPDATE sso_tokens SET user_agent = ?
         WHERE token = ? AND user_type = 'patient_app_exchange' AND expires_at > NOW() AND user_agent IS NULL");
    if (!$claim) throw new RuntimeException('Exchange claim prepare failed');
    mysqli_stmt_bind_param($claim, 'ss', $ua, $code_hash);
    if (!mysqli_stmt_execute($claim)) throw new RuntimeException('Exchange claim failed');
    mysqli_stmt_close($claim);

    // 방금 청구했든, 이전에 이미 이 기기가 청구해뒀든 — 이제 이 기기의
    // user_agent로 일치해야만 통과한다.
    $stmt = mysqli_prepare($connect,
        "SELECT g5_mb_id, payload FROM sso_tokens
         WHERE token = ? AND user_type = 'patient_app_exchange' AND user_agent = ? AND expires_at > NOW() LIMIT 1 FOR UPDATE");
    if (!$stmt) {
        error_log('[app-native-auth] exchange code lookup prepare failed: ' . mysqli_error($connect));
        throw new RuntimeException('Exchange lookup failed');
    }
    mysqli_stmt_bind_param($stmt, 'ss', $code_hash, $ua);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row || empty($row['g5_mb_id'])) return $none;
    return array('mb_id' => $row['g5_mb_id'], 'payload' => $row['payload']);
}

/**
 * 최초 청구 시 발급한 토큰 묶음을 저장해, 같은 코드+기기의 재제출이 새로
 * 발급하지 않고 이 값을 그대로 재응답할 수 있게 한다(진짜 idempotent 재시도).
 */
// The key is provisioned outside the database and source tree. Never fall
// back to DB credentials, a public device id, or an unencrypted payload.
function app_exchange_payload_key() {
    $encoded = getenv('APP_NATIVE_AUTH_KEY');
    $key = is_string($encoded) ? base64_decode($encoded, true) : false;
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('APP_NATIVE_AUTH_KEY must be a base64-encoded 32-byte key');
    }
    return $key;
}

function app_encrypt_exchange_payload($raw_code, array $payload) {
    $nonce = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt(json_encode($payload, JSON_THROW_ON_ERROR),
        'aes-256-gcm', app_exchange_payload_key(), OPENSSL_RAW_DATA, $nonce,
        $tag, hash('sha256', $raw_code), 16);
    if ($ciphertext === false) throw new RuntimeException('Payload encryption failed');
    return 'v1:' . base64_encode($nonce . $tag . $ciphertext);
}

function app_decrypt_exchange_payload($raw_code, $stored) {
    if (!is_string($stored) || strpos($stored, 'v1:') !== 0) {
        throw new RuntimeException('Unsupported exchange payload');
    }
    $bytes = base64_decode(substr($stored, 3), true);
    if ($bytes === false || strlen($bytes) < 29) throw new RuntimeException('Invalid exchange payload');
    $plain = openssl_decrypt(substr($bytes, 28), 'aes-256-gcm',
        app_exchange_payload_key(), OPENSSL_RAW_DATA, substr($bytes, 0, 12),
        substr($bytes, 12, 16), hash('sha256', $raw_code));
    if ($plain === false) throw new RuntimeException('Exchange payload authentication failed');
    $payload = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload)) throw new RuntimeException('Invalid exchange payload data');
    return $payload;
}

function app_native_auth_transaction($connect, callable $operation) {
    if (!mysqli_begin_transaction($connect)) throw new RuntimeException('Transaction failed');
    try {
        $result = $operation();
        if (!mysqli_commit($connect)) throw new RuntimeException('Commit failed');
        return $result;
    } catch (Throwable $e) {
        mysqli_rollback($connect);
        throw $e;
    }
}

function app_store_exchange_payload($connect, $raw_code, array $payload) {
    $encrypted = app_encrypt_exchange_payload($raw_code, $payload);
    $code_hash = hash('sha256', $raw_code);
    $stmt = mysqli_prepare($connect,
        "UPDATE sso_tokens SET payload = ? WHERE token = ? AND user_type = 'patient_app_exchange' AND payload IS NULL AND expires_at > NOW()");
    if (!$stmt) throw new RuntimeException('Exchange payload prepare failed');
    mysqli_stmt_bind_param($stmt, 'ss', $encrypted, $code_hash);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok || $affected !== 1) throw new RuntimeException('Exchange payload was not persisted');
}

/**
 * accessToken 발급 (기기 바인딩, 짧은 수명). 매번 새로 발급해도 안전하다 —
 * 응답이 유실되면 다음 verify가 실패해 refresh 1회를 더 taxi할 뿐, 로그인은
 * refreshToken(patient_app_remember)이 그대로 지켜준다.
 */
function app_issue_access_token($connect, $mb_id, $device_id) {
    if (!$connect || $mb_id === '' || !app_native_auth_is_valid_device_id($device_id)) return array('token' => '', 'expires_at' => 0);

    $raw_token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $raw_token);
    $ua = 'patient-app-device:' . hash('sha256', strtolower($device_id));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $expires_ts = G5_SERVER_TIME + APP_ACCESS_TOKEN_LIFETIME;
    $expires = date('Y-m-d H:i:s', $expires_ts);

    // 같은 기기의 이전 accessToken은 정리한다 (patient_app_remember/exchange와는
    // user_type이 달라 서로 영향을 주지 않는다).
    $del = mysqli_prepare($connect,
        "DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = 'patient_app_access' AND user_agent = ?");
    if ($del) {
        mysqli_stmt_bind_param($del, 'ss', $mb_id, $ua);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
    }

    $insert = mysqli_prepare($connect,
        "INSERT INTO sso_tokens (token, user_type, g5_mb_id, ip_address, user_agent, expires_at, created_at)
         VALUES (?, 'patient_app_access', ?, ?, ?, ?, NOW())");
    if (!$insert) {
        error_log('[app-native-auth] access token prepare failed: ' . mysqli_error($connect));
        return array('token' => '', 'expires_at' => 0);
    }
    mysqli_stmt_bind_param($insert, 'sssss', $token_hash, $mb_id, $ip, $ua, $expires);
    $ok = mysqli_stmt_execute($insert);
    mysqli_stmt_close($insert);

    return $ok ? array('token' => $raw_token, 'expires_at' => $expires_ts) : array('token' => '', 'expires_at' => 0);
}

/**
 * accessToken 검증 — 서버가 최종 권한자다. 로컬(폰) 만료시각 확인은 힌트일
 * 뿐이며, 서버가 이미 폐기(로그아웃/탈퇴/차단)한 토큰은 여기서 걸러진다.
 * 성공 시 g5_mb_id를 반환하고, 실패 시 빈 문자열을 반환한다.
 */
// $for_update requires a transaction covering validation through bridge issuance.
function app_verify_access_token($connect, $raw_token, $device_id, $for_update = false) {
    if (!$connect || !is_string($raw_token) || !preg_match('/^[a-f0-9]{64}$/i', $raw_token)) return '';
    if (!app_native_auth_is_valid_device_id($device_id)) return '';

    $token_hash = hash('sha256', $raw_token);
    $ua = 'patient-app-device:' . hash('sha256', strtolower($device_id));
    $stmt = mysqli_prepare($connect,
        "SELECT g5_mb_id FROM sso_tokens
         WHERE token = ? AND user_type = 'patient_app_access' AND user_agent = ? AND expires_at > NOW() LIMIT 1"
        . ($for_update ? ' FOR UPDATE' : ''));
    if (!$stmt) throw new RuntimeException('Access lookup failed');
    mysqli_stmt_bind_param($stmt, 'ss', $token_hash, $ua);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Access lookup failed');
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return ($row && !empty($row['g5_mb_id'])) ? $row['g5_mb_id'] : '';
}

/**
 * 계정 차단/탈퇴/이메일 미인증 여부를 로그인 흐름과 동일한 기준으로 확인한다.
 */
function app_native_auth_member_is_blocked($member, $connect = null) {
    if (empty($member) || empty($member['mb_id'])) return true;
    if ($connect) app_native_auth_ensure_config($connect);
    if (!empty($member['mb_intercept_date']) && $member['mb_intercept_date'] <= date('Ymd')) return true;
    if (!empty($member['mb_leave_date']) && $member['mb_leave_date'] <= date('Ymd')) return true;
    if (function_exists('is_use_email_certify') && is_use_email_certify()
        && !preg_match('/[1-9]/', $member['mb_email_certify'] ?? '')) return true;
    return false;
}

/**
 * WebView 세션 복구용 브릿지 토큰. 전용 user_type(patient_app_bridge)을 써서
 * 기존 'gnuboard' SSO 채널(다른 시스템 간 공유)과 완전히 분리한다 — 'gnuboard'
 * 타입을 재사용했다면 sso_create_token()의 "같은 mb_id의 이전 토큰 삭제"가
 * 이 계정의 *다른 기기*가 방금 받은, 아직 쓰지 않은 브릿지 토큰까지 지워버릴
 * 수 있었다. 여기서는 같은 기기(user_agent)의 이전 토큰만 정리한다.
 *
 * 짧은 수명(5분)에 더해, sso_auto_login.php가 이 타입을 세션으로 승격시킨
 * 직후 즉시 삭제해 1회용으로 만든다.
 *
 * sso_auto_login.php의 기존 조회(WHERE token = ?, 해시 없음)와 호환되도록
 * 다른 네이티브 토큰과 달리 원문 그대로 저장한다 — patient_app_access/
 * patient_app_remember처럼 SHA-256으로 저장하려면 그 조회도 함께 바꿔야
 * 하므로, 대신 5분 수명 + 기기 바인딩 + 1회용 삭제로 노출 폭을 좁힌다.
 */
function app_issue_session_bridge_token($connect, $mb_id, $device_id) {
    if (!$connect || $mb_id === '' || !app_native_auth_is_valid_device_id($device_id)) return '';

    $raw_token = bin2hex(random_bytes(32));
    $ua = 'patient-app-device:' . hash('sha256', strtolower($device_id));
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $expires = date('Y-m-d H:i:s', G5_SERVER_TIME + APP_SESSION_BRIDGE_LIFETIME);

    $del = mysqli_prepare($connect,
        "DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = 'patient_app_bridge' AND user_agent = ?");
    if ($del) {
        mysqli_stmt_bind_param($del, 'ss', $mb_id, $ua);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
    }

    $insert = mysqli_prepare($connect,
        "INSERT INTO sso_tokens (token, user_type, g5_mb_id, ip_address, user_agent, expires_at, created_at)
         VALUES (?, 'patient_app_bridge', ?, ?, ?, ?, NOW())");
    if (!$insert) {
        error_log('[app-native-auth] bridge token prepare failed: ' . mysqli_error($connect));
        return '';
    }
    mysqli_stmt_bind_param($insert, 'sssss', $raw_token, $mb_id, $ip, $ua, $expires);
    $ok = mysqli_stmt_execute($insert);
    mysqli_stmt_close($insert);

    return $ok ? $raw_token : '';
}

// Must run inside app_native_auth_transaction. Unlike login issuance, a
// refresh may only UPDATE the presented credential; it must never INSERT a
// replacement when a concurrent logout has removed it.
function app_renew_native_refresh_token($connect, $mb_id, $raw_token, $device_id) {
    if (!is_string($raw_token) || !preg_match('/^[a-f0-9]{64}$/i', $raw_token)
        || !app_native_auth_is_valid_device_id($device_id)) {
        throw new DomainException('refresh_invalid', 401);
    }
    $hash = hash('sha256', $raw_token);
    $binding = 'patient-app-device:' . hash('sha256', strtolower($device_id));
    $stmt = mysqli_prepare($connect,
        "SELECT token FROM sso_tokens WHERE token = ? AND g5_mb_id = ?
         AND user_type = 'patient_app_remember' AND user_agent = ? AND expires_at > NOW() FOR UPDATE");
    if (!$stmt) throw new RuntimeException('Refresh lookup failed');
    mysqli_stmt_bind_param($stmt, 'sss', $hash, $mb_id, $binding);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Refresh lookup failed');
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) throw new DomainException('refresh_invalid', 401);

    $expires = date('Y-m-d H:i:s', G5_SERVER_TIME + APP_REFRESH_TOKEN_LIFETIME);
    $stmt = mysqli_prepare($connect,
        "UPDATE sso_tokens SET expires_at = GREATEST(expires_at, ?) WHERE token = ?");
    if (!$stmt) throw new RuntimeException('Refresh renewal failed');
    mysqli_stmt_bind_param($stmt, 'ss', $expires, $hash);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Refresh renewal failed');
    mysqli_stmt_close($stmt);
    return $raw_token;
}

function app_revoke_native_auth_tokens($connect, $mb_id, $device_id) {
    if (!$connect || $mb_id === '' || !app_native_auth_is_valid_device_id($device_id)) return;

    $ua = 'patient-app-device:' . hash('sha256', strtolower($device_id));
    app_native_auth_transaction($connect, function () use ($connect, $mb_id, $ua) {
        // 이 기기가 청구한 교환 코드(또는 아직 아무도 청구하지 않은, 최근 이
        // 계정으로 발급된 코드)도 함께 지운다 — 그렇지 않으면 로그아웃 직후에도
        // 남은 유효시간(최대 90초) 동안 같은 코드로 다시 로그인할 수 있다.
        $delExchange = mysqli_prepare($connect,
            "DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = 'patient_app_exchange' AND (user_agent = ? OR user_agent IS NULL)");
        if (!$delExchange) throw new RuntimeException('Exchange revocation failed');
        mysqli_stmt_bind_param($delExchange, 'ss', $mb_id, $ua);
        if (!mysqli_stmt_execute($delExchange)) throw new RuntimeException('Exchange revocation failed');
        mysqli_stmt_close($delExchange);

        $refresh = mysqli_prepare($connect,
            "DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type = 'patient_app_remember' AND user_agent = ?");
        if (!$refresh) throw new RuntimeException('Refresh revocation failed');
        mysqli_stmt_bind_param($refresh, 'ss', $mb_id, $ua);
        if (!mysqli_stmt_execute($refresh)) throw new RuntimeException('Refresh revocation failed');
        mysqli_stmt_close($refresh);

        $del = mysqli_prepare($connect,
            "DELETE FROM sso_tokens WHERE g5_mb_id = ? AND user_type IN ('patient_app_access', 'patient_app_bridge') AND user_agent = ?");
        if (!$del) throw new RuntimeException('Token revocation failed');
        mysqli_stmt_bind_param($del, 'ss', $mb_id, $ua);
        if (!mysqli_stmt_execute($del)) throw new RuntimeException('Token revocation failed');
        mysqli_stmt_close($del);

    });
}
