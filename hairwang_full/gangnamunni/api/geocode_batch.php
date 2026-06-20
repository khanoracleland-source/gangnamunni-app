<?php
/**
 * 일괄 좌표 변환 API (AJAX)
 * - 주소가 있지만 좌표가 없는 병원들의 좌표를 일괄 변환
 * - Nominatim (OpenStreetMap) geocoding 사용 (무료, API 키 불필요)
 * - 1초 간격 요청 (Nominatim 정책 준수)
 *
 * GET  ?action=count  → 미변환 건수
 * POST ?action=batch  → 1건 변환 (프론트에서 반복 호출)
 */

header('Content-Type: application/json');
session_start();

// DB 연결
require_once __DIR__ . '/../../dbconfig.php';
require_once __DIR__ . '/../includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    echo json_encode(['error' => 'DB 연결 실패']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

if (!isset($_SESSION['ad_admin_logged_in']) || !$_SESSION['ad_admin_logged_in']) {
    echo json_encode(['error' => '로그인 필요']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'count') {
    // 미변환 건수 조회
    $r = mysqli_query($connect,
        "SELECT COUNT(*) as cnt FROM gangnamunni_hospitals
         WHERE address IS NOT NULL AND address != ''
         AND (latitude IS NULL OR latitude = 0 OR longitude IS NULL OR longitude = 0)"
    );
    $cnt = $r ? mysqli_fetch_assoc($r)['cnt'] : 0;
    $total = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_hospitals");
    $t = $total ? mysqli_fetch_assoc($total)['cnt'] : 0;
    echo json_encode(['pending' => intval($cnt), 'total' => intval($t)]);
    exit;
}

if ($action === 'batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1건 변환
    $r = mysqli_query($connect,
        "SELECT id, name, address FROM gangnamunni_hospitals
         WHERE address IS NOT NULL AND address != ''
         AND (latitude IS NULL OR latitude = 0 OR longitude IS NULL OR longitude = 0)
         LIMIT 1"
    );
    if (!$r || mysqli_num_rows($r) === 0) {
        echo json_encode(['done' => true, 'message' => '변환할 병원이 없습니다']);
        exit;
    }
    $row = mysqli_fetch_assoc($r);

    // Nominatim geocoding
    $query = urlencode($row['address']);
    $url = "https://nominatim.openstreetmap.org/search?q={$query}&format=json&limit=1&countrycodes=kr";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'SeoulBeauty/1.0 (admin geocoding)',
        CURLOPT_HTTPHEADER => ['Accept-Language: ko'],
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = ['id' => $row['id'], 'name' => $row['name'], 'address' => $row['address']];

    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        if (!empty($data) && isset($data[0]['lat'])) {
            $lat = floatval($data[0]['lat']);
            $lng = floatval($data[0]['lon']);

            $stmt = mysqli_prepare($connect,
                "UPDATE gangnamunni_hospitals SET latitude = ?, longitude = ? WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'ddi', $lat, $lng, $row['id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $result['success'] = true;
            $result['lat'] = $lat;
            $result['lng'] = $lng;
        } else {
            // 장소명으로 재시도
            $query2 = urlencode($row['name']);
            $url2 = "https://nominatim.openstreetmap.org/search?q={$query2}&format=json&limit=1&countrycodes=kr";
            $ch2 = curl_init();
            curl_setopt_array($ch2, [
                CURLOPT_URL => $url2,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_USERAGENT => 'SeoulBeauty/1.0',
            ]);
            $resp2 = curl_exec($ch2);
            curl_close($ch2);
            $data2 = json_decode($resp2, true);

            if (!empty($data2) && isset($data2[0]['lat'])) {
                $lat = floatval($data2[0]['lat']);
                $lng = floatval($data2[0]['lon']);
                $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_hospitals SET latitude = ?, longitude = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'ddi', $lat, $lng, $row['id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $result['success'] = true;
                $result['lat'] = $lat;
                $result['lng'] = $lng;
                $result['method'] = 'name_search';
            } else {
                $result['success'] = false;
                $result['error'] = '좌표를 찾을 수 없습니다';
                // 0.0001로 마킹 (재시도 방지)
                mysqli_query($connect, "UPDATE gangnamunni_hospitals SET latitude = 0.0001 WHERE id = " . intval($row['id']));
            }
        }
    } else {
        $result['success'] = false;
        $result['error'] = 'Geocoding API 오류 (HTTP ' . $http_code . ')';
    }

    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
