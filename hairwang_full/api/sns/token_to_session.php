<?php
/**
 * JWT 토큰을 웹 세션으로 변환 API
 *
 * 기능:
 * - 앱에서 SNS 로그인 완료 후 받은 JWT 토큰을 웹 세션으로 변환
 * - 웹뷰에서 자동 로그인 상태로 전환
 * - 앱 → 웹 간 인증 상태 동기화
 *
 * 흐름:
 * 1. 앱에서 SNS 로그인 완료 → JWT 토큰 획득
 * 2. 웹뷰 JavaScript에서 이 API 호출 (토큰 전달)
 * 3. 서버에서 JWT 토큰 검증
 * 4. 유효한 경우 그누보드 세션 생성 (자동 로그인)
 * 5. 성공 응답
 *
 * 요청:
 * - POST /api/sns/token_to_session.php
 * - Content-Type: application/json
 * - Body: {"token": "JWT_TOKEN_HERE"}
 *
 * 응답:
 * - success: true/false
 * - data: 회원 정보 (mb_id, mb_name, mb_nick 등)
 * - error: 에러 메시지
 *
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// ============================================================
// 1. 초기화 및 공통 파일 포함
// ============================================================

// 그누보드 공통 파일 포함 (세션 생성을 위해 필요)
include_once '../../common.php';

// ============================================================
// 2. CORS 헤더 설정 (앱 WebView에서 호출 시 필요)
// ============================================================

// 앱 도메인 또는 모든 도메인 허용 (보안 고려 필요)
header('Access-Control-Allow-Origin: *'); // 운영 환경에서는 특정 도메인으로 제한
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// OPTIONS 요청 처리 (Preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================
// 3. 요청 메서드 확인 (POST만 허용)
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Only POST method is allowed'
    ]);
    exit;
}

// ============================================================
// 4. 요청 본문 파싱 (JSON)
// ============================================================

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON format'
    ]);
    exit;
}

// ============================================================
// 5. 토큰 파라미터 확인
// ============================================================

$token = isset($data['token']) ? trim($data['token']) : '';

if (empty($token)) {
    echo json_encode([
        'success' => false,
        'error' => 'Token is required'
    ]);
    exit;
}

// ============================================================
// 6. JWT 토큰 검증 및 디코딩
// ============================================================

try {
    $decoded = verify_and_decode_jwt($token);

    // ============================================================
    // 7. 회원 ID 추출
    // ============================================================

    if (!isset($decoded->mb_id)) {
        throw new Exception('Invalid token: mb_id not found');
    }

    $mb_id = $decoded->mb_id;

    // ============================================================
    // 8. 회원 정보 조회
    // ============================================================

    $member = get_member($mb_id);

    if (!$member) {
        throw new Exception('Member not found: ' . $mb_id);
    }

    // ============================================================
    // 9. 그누보드 세션 생성 (자동 로그인)
    // ============================================================

    create_gnuboard_session($member);

    // ============================================================
    // 10. 성공 응답
    // ============================================================

    echo json_encode([
        'success' => true,
        'data' => [
            'mb_id' => $member['mb_id'],
            'mb_name' => $member['mb_name'],
            'mb_nick' => $member['mb_nick'],
            'mb_level' => $member['mb_level'],
            'mb_email' => $member['mb_email']
        ],
        'message' => 'Login successful'
    ]);

} catch (Exception $e) {
    // ============================================================
    // 11. 에러 응답
    // ============================================================

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// ============================================================
// 함수 정의
// ============================================================

/**
 * JWT 토큰 검증 및 디코딩
 *
 * @param string $token JWT 토큰
 * @return object 디코딩된 Payload 객체
 * @throws Exception 토큰 검증 실패 시
 */
function verify_and_decode_jwt($token) {
    // ============================================================
    // JWT 라이브러리 로드
    // ============================================================

    $jwt_lib_path = G5_LIB_PATH . '/jwt/JWT.php';

    if (file_exists($jwt_lib_path)) {
        require_once $jwt_lib_path;
        require_once G5_LIB_PATH . '/jwt/Key.php';

        // ============================================================
        // JWT Secret Key (app_callback.php와 동일해야 함)
        // ============================================================

        $secret = defined('G5_JWT_SECRET') ? G5_JWT_SECRET : 'hairwang_jwt_secret_key_change_this';

        // ============================================================
        // JWT 디코딩 및 검증
        // ============================================================

        try {
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));

            // ============================================================
            // 만료 시간 확인
            // ============================================================

            if (isset($decoded->exp) && $decoded->exp < time()) {
                throw new Exception('Token expired');
            }

            return $decoded;

        } catch (\Firebase\JWT\ExpiredException $e) {
            throw new Exception('Token expired');
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            throw new Exception('Invalid token signature');
        } catch (Exception $e) {
            throw new Exception('Token verification failed: ' . $e->getMessage());
        }

    } else {
        // ============================================================
        // JWT 라이브러리가 없는 경우: 간단한 디코딩 (비권장)
        // ============================================================

        // Base64 디코딩
        $payload_json = base64_decode($token);
        $payload = json_decode($payload_json);

        if (!$payload) {
            throw new Exception('Invalid token format');
        }

        // 간단한 만료 시간 확인
        if (isset($payload->timestamp) && (time() - $payload->timestamp) > (60 * 60 * 24 * 7)) {
            throw new Exception('Token expired');
        }

        return $payload;
    }
}

/**
 * 회원 정보 조회
 *
 * @param string $mb_id 회원 ID
 * @return array|false 회원 정보 배열 또는 false
 */
function get_member($mb_id) {
    global $g5;

    $sql = "SELECT * FROM {$g5['member_table']} WHERE mb_id = '" . sql_real_escape_string($mb_id) . "'";
    $member = sql_fetch($sql);

    return $member;
}

/**
 * 그누보드 세션 생성 (자동 로그인)
 *
 * 그누보드 표준 로그인 프로세스를 따름
 *
 * @param array $member 회원 정보
 * @return void
 */
function create_gnuboard_session($member) {
    global $g5;

    // ============================================================
    // 1. 세션 변수 설정
    // ============================================================

    // 회원 ID 저장
    set_session('ss_mb_id', $member['mb_id']);

    // 회원 고유 키 생성 (보안을 위해 회원 가입일 기반 해시 사용)
    $mb_key = md5($member['mb_datetime']);
    set_session('ss_mb_key', $mb_key);

    // ============================================================
    // 2. 최종 로그인 시간 업데이트
    // ============================================================

    $sql_update = "
        UPDATE {$g5['member_table']} SET
            mb_today_login = NOW(),
            mb_login_ip = '{$_SERVER['REMOTE_ADDR']}'
        WHERE mb_id = '{$member['mb_id']}'
    ";
    sql_query($sql_update);

    // ============================================================
    // 3. 로그인 포인트 지급 (1일 1회)
    // ============================================================

    // 오늘 이미 로그인 포인트를 받았는지 확인
    $today = date('Y-m-d');
    $sql_check = "
        SELECT COUNT(*) as cnt FROM {$g5['point_table']}
        WHERE mb_id = '{$member['mb_id']}'
        AND po_datetime LIKE '{$today}%'
        AND po_content LIKE '%로그인%'
    ";
    $row = sql_fetch($sql_check);

    if ($row['cnt'] == 0) {
        // 로그인 포인트 설정 확인
        $login_point = isset($g5['cf_login_point']) ? (int)$g5['cf_login_point'] : 0;

        if ($login_point > 0) {
            // 포인트 지급
            insert_point(
                $member['mb_id'],
                $login_point,
                '로그인 (' . date('Y-m-d') . ')',
                '@login',
                $member['mb_id'],
                date('Y-m-d')
            );
        }
    }

    // ============================================================
    // 4. 자동 로그인 쿠키 설정 (선택 사항)
    // ============================================================

    // 환자 앱(webview)에서 호출된 경우 120일, 그 외 일반 웹에서는 7일 유지
    // 보안: HTTPS 환경에서만 사용 권장
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $sns_remember_lifetime = (!empty($is_patient_app_request) && !empty($patient_app_lifetime))
            ? $patient_app_lifetime
            : 60 * 60 * 24 * 7;
        $remember_token = function_exists('create_remember_login_token')
            ? create_remember_login_token($member, $sns_remember_lifetime)
            : '';
        if ($remember_token !== '') {
            set_cookie('ck_mb_id', $member['mb_id'], $sns_remember_lifetime);
            set_cookie('ck_auto', $remember_token, $sns_remember_lifetime);
        }
    }

    // ============================================================
    // 5. 방문 기록 업데이트
    // ============================================================

    // 그누보드 visit 테이블 업데이트 (선택 사항)
    if (isset($g5['visit_table'])) {
        $vi_date = date('Y-m-d');
        $vi_time = date('H:i:s');
        $vi_referer = isset($_SERVER['HTTP_REFERER']) ? substr($_SERVER['HTTP_REFERER'], 0, 255) : '';
        $vi_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';

        $sql_visit = "
            INSERT INTO {$g5['visit_table']} SET
                vi_ip = '{$_SERVER['REMOTE_ADDR']}',
                vi_date = '{$vi_date}',
                vi_time = '{$vi_time}',
                vi_referer = '" . sql_real_escape_string($vi_referer) . "',
                vi_agent = '" . sql_real_escape_string($vi_agent) . "'
        ";
        sql_query($sql_visit, false); // 실패해도 무시
    }
}

/**
 * 세션 값 설정 헬퍼 함수
 *
 * @param string $key 세션 키
 * @param mixed $value 세션 값
 * @return void
 */
function set_session($key, $value) {
    if (!isset($_SESSION)) {
        @session_start();
    }
    $_SESSION[$key] = $value;
}
?>
