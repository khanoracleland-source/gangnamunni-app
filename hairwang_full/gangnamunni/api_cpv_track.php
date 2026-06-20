<?php
/**
 * CPV 추적 API 엔드포인트
 *
 * 프론트엔드 JavaScript에서 페이지 로드 시 호출합니다.
 * POST JSON: {page_type, target_id, page_url, keyword, procedure_type}
 *
 * 퍼블릭 엔드포인트 — 세션 불필요, 방문자 브라우저에서 직접 호출
 */

// CORS 헤더
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// POST만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// DB 연결
require_once __DIR__ . '/../dbconfig.php';

$connect = @mysqli_connect(
    G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB,
    defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306
);
if (!$connect) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// CPV 엔진 로드
require_once __DIR__ . '/includes/cpv_engine.php';

// --- 요청 파싱 ---
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input || empty($input['page_type']) || empty($input['target_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '필수 파라미터 누락 (page_type, target_id)']);
    exit;
}

$page_type      = trim($input['page_type']);
$target_id      = trim($input['target_id']);
$page_url       = isset($input['page_url']) ? trim($input['page_url']) : '';
$keyword        = isset($input['keyword']) ? trim($input['keyword']) : null;
$procedure_type = isset($input['procedure_type']) ? trim($input['procedure_type']) : null;

// --- page_type 유효성 검사 ---
$allowed_types = ['hospital', 'doctor', 'review', 'event', 'treatment', 'column'];
if (!in_array($page_type, $allowed_types, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '유효하지 않은 page_type']);
    exit;
}

// --- 요청 지연 검사 (Rate Limiting) ---
// 동일 IP에서 너무 빠른 연속 요청 방지
$charge_delay = (int) getCpvSetting('cpv_charge_delay_sec', 2);
if ($charge_delay > 0) {
    $visitor_ip = getCpvVisitorIp();
    $delay_stmt = mysqli_prepare($connect,
        "SELECT created_at FROM gangnamunni_cpv_logs
         WHERE visitor_ip = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
         ORDER BY created_at DESC LIMIT 1"
    );
    if ($delay_stmt) {
        mysqli_stmt_bind_param($delay_stmt, 'si', $visitor_ip, $charge_delay);
        mysqli_stmt_execute($delay_stmt);
        $delay_result = mysqli_stmt_get_result($delay_stmt);
        $delay_row = mysqli_fetch_assoc($delay_result);
        mysqli_stmt_close($delay_stmt);

        if ($delay_row) {
            // 지연 시간 내 요청 → 무시 (200 반환하되 과금 안 함)
            echo json_encode([
                'success' => true,
                'charged' => false,
                'rate'    => 0,
                'reason'  => 'rate_limited'
            ]);
            exit;
        }
    }
}

// --- target_id에서 hospital_id 결정 ---
$hospital_id = resolveHospitalId($connect, $page_type, $target_id);
if (!$hospital_id) {
    echo json_encode([
        'success' => true,
        'charged' => false,
        'rate'    => 0,
        'reason'  => 'no_hospital'
    ]);
    exit;
}

// --- CPV 과금 처리 ---
$result = processCpvView($hospital_id, $page_type, $target_id, $page_url, $keyword, $procedure_type);

// 응답 (내부 정보 최소 노출)
echo json_encode([
    'success' => $result['success'],
    'charged' => $result['charged'],
    'rate'    => $result['rate'],
    'reason'  => $result['reason']
]);
exit;


/**
 * page_type + target_id 조합으로 hospital_id 결정
 *
 * @param mysqli $connect
 * @param string $page_type
 * @param string $target_id
 * @return int|null
 */
function resolveHospitalId($connect, $page_type, $target_id) {
    $target_id = (int) $target_id;
    if ($target_id <= 0) return null;

    switch ($page_type) {
        case 'hospital':
            // target_id가 곧 hospital_id
            return $target_id;

        case 'doctor':
            // 의사 → 병원 매핑
            $stmt = mysqli_prepare($connect,
                "SELECT hospital_id FROM gangnamunni_doctors WHERE id = ? LIMIT 1"
            );
            if (!$stmt) return null;
            mysqli_stmt_bind_param($stmt, 'i', $target_id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($r);
            mysqli_stmt_close($stmt);
            return $row ? (int) $row['hospital_id'] : null;

        case 'review':
            // 후기 → 병원 매핑
            $stmt = mysqli_prepare($connect,
                "SELECT hospital_id FROM hospital_reviews WHERE id = ? LIMIT 1"
            );
            if (!$stmt) return null;
            mysqli_stmt_bind_param($stmt, 'i', $target_id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($r);
            mysqli_stmt_close($stmt);
            return $row ? (int) $row['hospital_id'] : null;

        case 'event':
            // 이벤트 → 병원 매핑
            $stmt = mysqli_prepare($connect,
                "SELECT hospital_id FROM gangnamunni_events WHERE id = ? LIMIT 1"
            );
            if (!$stmt) return null;
            mysqli_stmt_bind_param($stmt, 'i', $target_id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($r);
            mysqli_stmt_close($stmt);
            return $row ? (int) $row['hospital_id'] : null;

        case 'treatment':
            // 시술 → 병원 매핑
            $stmt = mysqli_prepare($connect,
                "SELECT hospital_id FROM gangnamunni_treatments WHERE id = ? LIMIT 1"
            );
            if (!$stmt) return null;
            mysqli_stmt_bind_param($stmt, 'i', $target_id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($r);
            mysqli_stmt_close($stmt);
            return $row ? (int) $row['hospital_id'] : null;

        case 'column':
            // 칼럼 → 병원 매핑
            $stmt = mysqli_prepare($connect,
                "SELECT hospital_id FROM gangnamunni_columns WHERE id = ? LIMIT 1"
            );
            if (!$stmt) return null;
            mysqli_stmt_bind_param($stmt, 'i', $target_id);
            mysqli_stmt_execute($stmt);
            $r = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($r);
            mysqli_stmt_close($stmt);
            return $row ? (int) $row['hospital_id'] : null;

        default:
            return null;
    }
}
