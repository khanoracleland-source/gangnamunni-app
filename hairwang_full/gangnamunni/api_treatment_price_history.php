<?php
/**
 * 시술 가격 이력 API
 * - GET ?hospital_id=X&treatment_id=Y → 가격 이력 (최신순, 최대 50건)
 * - GET ?hospital_id=X&treatment_id=Y&format=chart → Chart.js용 포맷
 * - GET ?treatment_id=Y&compare=1 → 병원간 가격 비교
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// DB 연결
require_once __DIR__ . '/../dbconfig.php';
$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
if (!$connect) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB 연결 실패'], JSON_UNESCAPED_UNICODE);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

// 파라미터
$hospital_id  = isset($_GET['hospital_id']) ? intval($_GET['hospital_id']) : 0;
$treatment_id = isset($_GET['treatment_id']) ? intval($_GET['treatment_id']) : 0;
$format       = isset($_GET['format']) ? trim($_GET['format']) : '';
$compare      = isset($_GET['compare']) ? intval($_GET['compare']) : 0;

// treatment_id 필수
if ($treatment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'treatment_id 필수'], JSON_UNESCAPED_UNICODE);
    mysqli_close($connect);
    exit;
}

/**
 * 시술 마스터 정보 조회
 */
function get_treatment_info($connect, $treatment_id) {
    $stmt = mysqli_prepare($connect, "SELECT name, hashtag, avg_price_min, avg_price_max FROM gangnamunni_treatment_master WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $treatment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row;
}

/**
 * 병원명 조회
 */
function get_hospital_name($connect, $hospital_id) {
    $stmt = mysqli_prepare($connect, "SELECT name FROM gangnamunni_hospitals WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ? $row['name'] : '';
}

/**
 * 현재 가격 조회 (hospital_treatments)
 */
function get_current_price($connect, $hospital_id, $treatment_id) {
    $stmt = mysqli_prepare($connect, "SELECT price_min, price_max FROM gangnamunni_hospital_treatments WHERE hospital_id = ? AND treatment_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $hospital_id, $treatment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ? ['min' => intval($row['price_min']), 'max' => intval($row['price_max'])] : null;
}

// ── 병원간 비교 모드 ──
if ($compare === 1) {
    $treatment_info = get_treatment_info($connect, $treatment_id);
    if (!$treatment_info) {
        echo json_encode(['success' => false, 'error' => '시술 정보 없음'], JSON_UNESCAPED_UNICODE);
        mysqli_close($connect);
        exit;
    }

    // 해당 시술을 제공하는 모든 병원의 현재 가격
    $stmt = mysqli_prepare($connect,
        "SELECT ht.hospital_id, h.name AS hospital_name, ht.price_min, ht.price_max
         FROM gangnamunni_hospital_treatments ht
         JOIN gangnamunni_hospitals h ON h.id = ht.hospital_id
         WHERE ht.treatment_id = ? AND ht.price_min > 0
         ORDER BY ht.price_min ASC
         LIMIT 30"
    );
    mysqli_stmt_bind_param($stmt, 'i', $treatment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $hospitals = [];
    $labels = [];
    $min_data = [];
    $max_data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $hospitals[] = [
            'hospital_id'   => intval($row['hospital_id']),
            'hospital_name' => $row['hospital_name'],
            'price_min'     => intval($row['price_min']),
            'price_max'     => intval($row['price_max'])
        ];
        $labels[]   = $row['hospital_name'];
        $min_data[] = intval($row['price_min']);
        $max_data[] = intval($row['price_max']);
    }
    mysqli_stmt_close($stmt);

    echo json_encode([
        'success'        => true,
        'treatment_name' => $treatment_info['name'],
        'treatment_id'   => $treatment_id,
        'avg_market_price' => [
            'min' => intval($treatment_info['avg_price_min']),
            'max' => intval($treatment_info['avg_price_max'])
        ],
        'hospitals' => $hospitals,
        'chart_data' => [
            'labels'   => $labels,
            'datasets' => [
                ['label' => '최소가', 'data' => $min_data],
                ['label' => '최대가', 'data' => $max_data]
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);

    mysqli_close($connect);
    exit;
}

// ── hospital_id 필수 (비교 모드 외) ──
if ($hospital_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'hospital_id 필수'], JSON_UNESCAPED_UNICODE);
    mysqli_close($connect);
    exit;
}

// 가격 이력 조회 (최신순 50건)
$stmt = mysqli_prepare($connect,
    "SELECT id, price_min, price_max, changed_by, DATE_FORMAT(recorded_at, '%Y-%m-%d') as recorded_date, recorded_at
     FROM gangnamunni_treatment_price_history
     WHERE hospital_id = ? AND treatment_id = ?
     ORDER BY recorded_at DESC
     LIMIT 50"
);
mysqli_stmt_bind_param($stmt, 'ii', $hospital_id, $treatment_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$history = [];
while ($row = mysqli_fetch_assoc($result)) {
    $history[] = [
        'id'            => intval($row['id']),
        'price_min'     => intval($row['price_min']),
        'price_max'     => intval($row['price_max']),
        'changed_by'    => $row['changed_by'],
        'recorded_date' => $row['recorded_date'],
        'recorded_at'   => $row['recorded_at']
    ];
}
mysqli_stmt_close($stmt);

// ── 일반 모드: 원본 데이터 반환 ──
if ($format !== 'chart') {
    echo json_encode([
        'success'      => true,
        'hospital_id'  => $hospital_id,
        'treatment_id' => $treatment_id,
        'count'        => count($history),
        'data'         => $history
    ], JSON_UNESCAPED_UNICODE);

    mysqli_close($connect);
    exit;
}

// ── Chart.js 포맷 모드 ──
$treatment_info = get_treatment_info($connect, $treatment_id);
$hospital_name  = get_hospital_name($connect, $hospital_id);
$current_price  = get_current_price($connect, $hospital_id, $treatment_id);

// 시간순 정렬 (차트용)
$sorted = array_reverse($history);

$labels   = [];
$min_data = [];
$max_data = [];
foreach ($sorted as $item) {
    $labels[]   = $item['recorded_date'];
    $min_data[] = $item['price_min'];
    $max_data[] = $item['price_max'];
}

$response = [
    'success'        => true,
    'treatment_name' => $treatment_info ? $treatment_info['name'] : '',
    'hospital_name'  => $hospital_name,
    'chart_data'     => [
        'labels'   => $labels,
        'datasets' => [
            ['label' => '최소가', 'data' => $min_data],
            ['label' => '최대가', 'data' => $max_data]
        ]
    ],
    'current_price'    => $current_price,
    'avg_market_price' => $treatment_info ? [
        'min' => intval($treatment_info['avg_price_min']),
        'max' => intval($treatment_info['avg_price_max'])
    ] : null
];

echo json_encode($response, JSON_UNESCAPED_UNICODE);
mysqli_close($connect);
