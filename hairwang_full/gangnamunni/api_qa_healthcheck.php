<?php
/**
 * QA Health Check API — 서버 측 페이지 상태 확인
 * 각 페이지의 HTTP 상태코드 + 에러 원인 반환
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$base_url = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';

// 관리자 세션 쿠키 획득
function getAdminSession() {
    global $base_url;
    $cookie_file = '/tmp/qa_hc_cookies_' . getmypid() . '.txt';

    $ch = curl_init($base_url . 'dashboard.php');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['login' => 1, 'username' => 'admin', 'password' => 'admin123']),
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_COOKIEJAR => $cookie_file,
        CURLOPT_COOKIEFILE => $cookie_file,
    ]);
    curl_exec($ch);
    curl_close($ch);

    return $cookie_file;
}

// 단일 페이지 체크
function checkPage($url, $cookie_file) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIEFILE => $cookie_file,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $result = [
        'status' => $code,
        'ok' => ($code >= 200 && $code < 400),
    ];

    // 에러 원인 추출
    if ($code >= 400) {
        $cause = '';

        // PHP Fatal Error 패턴
        if (preg_match('/Fatal error:(.+?)(?:in\s+\/\S+|$)/si', $body, $m)) {
            $cause = 'Fatal: ' . trim(strip_tags($m[1]));
        }
        // PHP Warning/Notice
        elseif (preg_match('/(?:Warning|Notice):(.+?)(?:in\s+\/\S+|$)/si', $body, $m)) {
            $cause = 'Warning: ' . trim(strip_tags($m[1]));
        }
        // SQL Error
        elseif (preg_match('/(?:Unknown column|Table .+ doesn.t exist|You have an error in your SQL|Duplicate entry)/i', $body, $m)) {
            $cause = 'SQL: ' . trim($m[0]);
        }
        // JSON 에러 응답
        elseif ($json_body = @json_decode($body, true)) {
            if (isset($json_body['message'])) $cause = $json_body['message'];
            elseif (isset($json_body['error'])) $cause = $json_body['error'];
        }
        // 접근 오류 (병원 ID 필요)
        elseif (strpos($body, '병원 정보를 확인할 수 없습니다') !== false) {
            $cause = '접근 오류: hospital_id 파라미터 필요';
        }
        // 로그인 필요
        elseif (strpos($body, '로그인') !== false && $code == 401) {
            $cause = '인증 필요: 로그인 세션 없음';
        }
        // 리다이렉트 (로그인 페이지로)
        elseif (strpos($body, 'login.php') !== false || strpos($body, 'dashboard.php') !== false) {
            $cause = '리다이렉트: 로그인 페이지로 이동';
        }
        // curl 에러
        elseif ($error) {
            $cause = 'cURL: ' . $error;
        }
        // 빈 응답
        elseif (empty($body)) {
            $cause = '빈 응답 (서버 크래시 가능)';
        }
        // 일반 HTML 에러 페이지
        elseif (preg_match('/<title>(.+?)<\/title>/i', $body, $m)) {
            $cause = 'HTTP ' . $code . ': ' . trim(strip_tags($m[1]));
        }
        else {
            $cause = 'HTTP ' . $code;
        }

        $result['cause'] = mb_substr($cause, 0, 200);
        $result['body_size'] = strlen($body);
    }

    return $result;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'check_one') {
    // 단일 URL 체크
    $file = $_GET['file'] ?? $_POST['file'] ?? '';
    if (!$file) { echo json_encode(['success' => false, 'message' => 'file 파라미터 필요']); exit; }

    $cookie_file = getAdminSession();
    $url = $base_url . $file;

    // partner 파일은 hospital_id 추가
    if (strpos($file, 'partner_') === 0 && strpos($file, '?') === false) {
        $url .= '?hospital_id=8093';
    }

    $result = checkPage($url, $cookie_file);
    @unlink($cookie_file);

    echo json_encode(['success' => true, 'file' => $file, 'result' => $result]);
}
elseif ($action === 'check_batch') {
    // 배치 체크
    $input = json_decode(file_get_contents('php://input'), true);
    $files = $input['files'] ?? [];
    if (empty($files)) { echo json_encode(['success' => false, 'message' => 'files 배열 필요']); exit; }

    $cookie_file = getAdminSession();
    $results = [];
    $ok = 0;
    $errors = 0;

    foreach ($files as $file) {
        if (empty($file)) continue;

        $url = $base_url . $file;
        if (strpos($file, 'partner_') === 0 && strpos($file, '?') === false) {
            $url .= '?hospital_id=8093';
        }

        $r = checkPage($url, $cookie_file);
        $results[$file] = $r;
        if ($r['ok']) $ok++; else $errors++;
    }

    @unlink($cookie_file);

    echo json_encode([
        'success' => true,
        'total' => count($results),
        'ok' => $ok,
        'errors' => $errors,
        'results' => $results
    ]);
}
elseif ($action === 'check_all') {
    // 전체 PHP 파일 스캔
    $dir = __DIR__ . '/';
    $files = glob($dir . '*.php');
    $skip = ['dbconfig', 'admin_auth', 'theme_helper', 'admin_header', 'admin_footer',
        'global_nav', 'bottom_nav', 'social_share', 'ad_banner', 'cpv_engine', 'cpv_helper',
        'infyhms_api', 'ai_proxy', 'hospital_filter', 'cron_', 'api_qa_screenshot', 'api_qa_healthcheck'];

    $cookie_file = getAdminSession();
    $results = [];
    $ok = 0;
    $errors = 0;

    foreach ($files as $path) {
        $fname = basename($path);
        $skipThis = false;
        foreach ($skip as $p) { if (strpos($fname, $p) !== false) { $skipThis = true; break; } }
        if ($skipThis) continue;

        $url = $base_url . $fname;
        if (strpos($fname, 'partner_') === 0) $url .= '?hospital_id=8093';

        $r = checkPage($url, $cookie_file);
        if (!$r['ok']) {
            $results[$fname] = $r;
            $errors++;
        } else {
            $ok++;
        }
    }

    @unlink($cookie_file);

    echo json_encode([
        'success' => true,
        'total' => $ok + $errors,
        'ok' => $ok,
        'errors' => $errors,
        'error_pages' => $results
    ]);
}
else {
    echo json_encode([
        'success' => true,
        'message' => 'QA Health Check API',
        'actions' => ['check_one?file=xxx.php', 'check_batch (POST files[])', 'check_all']
    ]);
}
