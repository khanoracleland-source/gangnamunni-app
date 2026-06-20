<?php
/**
 * FCM 토큰 관리 API
 * Flutter 앱에서 FCM 토큰을 서버에 등록/해제하는 엔드포인트
 *
 * Endpoints:
 *   POST ?action=register   -- FCM 토큰 등록 (body: {token, platform, device_info})
 *   POST ?action=unregister -- FCM 토큰 해제 (body: {token})
 *   GET  ?action=test       -- 테스트 푸시 전송
 *   GET  ?action=tokens     -- 현재 관리자의 토큰 목록
 *   GET  ?action=status     -- FCM 설정 상태 확인
 *   GET  ?action=stats      -- FCM 전체 통계
 *
 * 인증 방식 (우선순위):
 *   1) 세션 기반 (웹 관리자)
 *   2) Authorization: Bearer <token> (앱 토큰)
 *   3) X-Admin-Token 헤더 (앱 토큰)
 *   4) POST body {admin_username/username, admin_password/password} (앱 초기 설정용)
 *
 * @created 2026-02-26
 * @updated 2026-02-26 platform/device_info 컬럼 대응 + stats 엔드포인트 + app_tokens 방어 처리
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Admin-Token');

// CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/admin_fcm.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB 연결 실패']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// --- 인증 처리 ---
$admin_id = null;

// php://input은 한 번만 읽을 수 있으므로 미리 캐시
$raw_input = file_get_contents('php://input');
$json_input = json_decode($raw_input, true) ?? [];

// 1) 세션 기반 인증 (웹 관리자)
if (!empty($_SESSION['ad_admin_logged_in']) && !empty($_SESSION['ad_admin_id'])) {
    $admin_id = intval($_SESSION['ad_admin_id']);
}

// 2) 토큰 기반 인증 (앱) -- Authorization: Bearer 또는 X-Admin-Token
if (!$admin_id) {
    $auth_token = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
        if (stripos($auth_header, 'Bearer ') === 0) {
            $auth_token = substr($auth_header, 7);
        }
    }
    if (!$auth_token && !empty($_SERVER['HTTP_X_ADMIN_TOKEN'])) {
        $auth_token = $_SERVER['HTTP_X_ADMIN_TOKEN'];
    }

    if ($auth_token) {
        // gangnamunni_admin_app_tokens 테이블이 존재하면 조회 시도
        $table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_admin_app_tokens'");
        if ($table_check && mysqli_num_rows($table_check) > 0) {
            $stmt = mysqli_prepare($connect, "SELECT admin_id FROM gangnamunni_admin_app_tokens WHERE token = ? AND expires_at > NOW()");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 's', $auth_token);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                if ($row) {
                    $admin_id = intval($row['admin_id']);
                }
            }
        }
    }
}

// 3) POST body 기본 인증 (username:password) -- 앱 초기 설정용
if (!$admin_id) {
    // json_input 또는 $_POST에서 인증 정보 추출
    $input_for_auth = !empty($json_input) ? $json_input : $_POST;
    $username = trim($input_for_auth['admin_username'] ?? $input_for_auth['username'] ?? '');
    $password = $input_for_auth['admin_password'] ?? $input_for_auth['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $stmt = mysqli_prepare($connect, "SELECT id, password FROM gangnamunni_admins WHERE username = ? AND is_active = 1");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $admin = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if ($admin && password_verify($password, $admin['password'])) {
                $admin_id = intval($admin['id']);
            }
        }
    }
}

// --- 액션 라우팅 ---
$action = $_GET['action'] ?? $_POST['action'] ?? $json_input['action'] ?? '';

// 인증이 필요한 액션 체크
$auth_required_actions = ['register', 'unregister', 'test', 'tokens', 'stats'];
if (in_array($action, $auth_required_actions) && !$admin_id) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => '인증 필요',
        'hint' => '세션, Authorization Bearer, X-Admin-Token, 또는 admin_username/admin_password 제공',
    ]);
    mysqli_close($connect);
    exit;
}

switch ($action) {

    case 'register':
        // POST: FCM 토큰 등록
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST만 허용']);
            break;
        }

        // json_input 또는 $_POST에서 파라미터 추출
        $input = !empty($json_input) ? $json_input : $_POST;

        // token / fcm_token 모두 지원
        $token = trim($input['token'] ?? $input['fcm_token'] ?? '');
        // platform / device_type 모두 지원
        $platform = trim($input['platform'] ?? $input['device_type'] ?? 'android');
        // device_info / device_name 모두 지원
        $device_info = trim($input['device_info'] ?? $input['device_name'] ?? '');

        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'token 필수']);
            break;
        }

        $result = register_fcm_token($connect, $admin_id, $token, $platform, $device_info);
        echo json_encode($result);
        break;

    case 'unregister':
        // POST: FCM 토큰 해제
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'POST만 허용']);
            break;
        }

        $input = !empty($json_input) ? $json_input : $_POST;
        $token = trim($input['token'] ?? $input['fcm_token'] ?? '');

        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'token 필수']);
            break;
        }

        $result = unregister_fcm_token($connect, $token);
        echo json_encode($result);
        break;

    case 'test':
        // GET: 테스트 푸시 알림 전송
        $result = send_push_notification(
            $connect,
            $admin_id,
            '서울미녀 테스트 알림',
            '푸시 알림이 정상적으로 작동합니다! (' . date('Y-m-d H:i:s') . ')',
            'https://hms.seoulbeauty.app/hairwang/gangnamunni/dashboard.php',
            'bi-bell-fill'
        );
        echo json_encode(['success' => ($result['sent'] ?? 0) > 0, 'result' => $result]);
        break;

    case 'tokens':
        // GET: 현재 관리자의 등록된 토큰 목록
        $tokens = get_admin_fcm_tokens($connect, $admin_id);
        echo json_encode(['success' => true, 'admin_id' => $admin_id, 'count' => count($tokens), 'tokens' => $tokens]);
        break;

    case 'stats':
        // GET: FCM 전체 통계 (관리자 전용)
        $stats = get_fcm_stats($connect);
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;

    case 'status':
        // GET: FCM 설정 상태 (인증 불필요)
        $has_service_account = file_exists(FCM_SERVICE_ACCOUNT_PATH);
        $server_key = get_fcm_server_key($connect);
        $project_id = get_fcm_project_id($connect);
        $enabled = is_fcm_enabled($connect);

        echo json_encode([
            'success' => true,
            'fcm_enabled' => $enabled,
            'fcm_configured' => $has_service_account || !empty($server_key),
            'api_mode' => $has_service_account ? 'v1 (서비스 계정)' : (!empty($server_key) ? 'legacy (서버키)' : '미설정'),
            'project_id' => $project_id ?: '미설정',
            'has_service_account' => $has_service_account,
            'has_server_key' => !empty($server_key),
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => '알 수 없는 action',
            'available_actions' => ['register', 'unregister', 'test', 'tokens', 'status', 'stats'],
        ]);
        break;
}

mysqli_close($connect);
