<?php
/**
 * api_treatments.php -- 시술 관련 API 엔드포인트
 *
 * GET ?action=hospital&id={hospital_id}  → 병원의 활성 시술 목록 (JSON)
 * GET ?action=search&q={keyword}         → 시술 검색 (JSON)
 * GET ?action=categories                 → 카테고리 목록 (JSON)
 *
 * @created 2026-03-25
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS (같은 도메인이지만 안전하게)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $allowed = ['https://hms.seoulbeauty.app'];
    if (in_array($_SERVER['HTTP_ORIGIN'], $allowed)) {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    }
}

require_once __DIR__ . '/dbconfig.php';

// DB 연결 생성
$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
if (!$connect) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed'], JSON_UNESCAPED_UNICODE);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'hospital':
        getHospitalTreatments($connect);
        break;
    case 'search':
        searchTreatments($connect);
        break;
    case 'categories':
        getCategories($connect);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
}

mysqli_close($connect);
exit;

/**
 * 병원의 활성 시술 목록
 */
function getHospitalTreatments($connect) {
    $hospital_id = intval($_GET['id'] ?? 0);
    if ($hospital_id <= 0) {
        echo json_encode(['treatments' => []], JSON_UNESCAPED_UNICODE);
        return;
    }

    $sql = "
        SELECT
            tm.id AS treatment_id,
            tm.name AS treatment_name,
            tm.hashtag,
            tc.id AS category_id,
            tc.name AS category_name,
            tc.sort_order AS cat_sort,
            ht.price_min,
            ht.price_max
        FROM gangnamunni_hospital_treatments ht
        INNER JOIN gangnamunni_treatment_master tm ON ht.treatment_id = tm.id
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        WHERE ht.hospital_id = ?
          AND ht.status = 'active'
          AND tm.is_active = 1
        ORDER BY tc.sort_order ASC, tm.name ASC
    ";

    $stmt = mysqli_prepare($connect, $sql);
    if (!$stmt) {
        echo json_encode(['treatments' => []], JSON_UNESCAPED_UNICODE);
        return;
    }

    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $treatments = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $treatments[] = [
                'treatment_id' => intval($row['treatment_id']),
                'treatment_name' => $row['treatment_name'],
                'hashtag' => $row['hashtag'],
                'category_id' => intval($row['category_id']),
                'category_name' => $row['category_name'],
                'price_min' => intval($row['price_min']),
                'price_max' => intval($row['price_max'])
            ];
        }
    }
    mysqli_stmt_close($stmt);

    echo json_encode(['treatments' => $treatments], JSON_UNESCAPED_UNICODE);
}

/**
 * 시술 검색 (이름 + 별칭 + 해시태그)
 */
function searchTreatments($connect) {
    $q = trim($_GET['q'] ?? '');
    if (mb_strlen($q) < 1) {
        echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
        return;
    }

    $like = '%' . $q . '%';

    $sql = "
        SELECT DISTINCT
            tm.id AS treatment_id,
            tm.name AS treatment_name,
            tm.hashtag,
            tc.name AS category_name
        FROM gangnamunni_treatment_master tm
        LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
        LEFT JOIN gangnamunni_treatment_aliases ta ON tm.id = ta.treatment_id
        WHERE tm.is_active = 1
          AND (tm.name LIKE ? OR tm.hashtag LIKE ? OR ta.alias LIKE ?)
        ORDER BY tm.name ASC
        LIMIT 20
    ";

    $stmt = mysqli_prepare($connect, $sql);
    if (!$stmt) {
        echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
        return;
    }

    mysqli_stmt_bind_param($stmt, 'sss', $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $results = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $results[] = [
                'treatment_id' => intval($row['treatment_id']),
                'treatment_name' => $row['treatment_name'],
                'hashtag' => $row['hashtag'],
                'category_name' => $row['category_name']
            ];
        }
    }
    mysqli_stmt_close($stmt);

    echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
}

/**
 * 카테고리 목록
 */
function getCategories($connect) {
    $sql = "SELECT id, name, icon, sort_order FROM gangnamunni_treatment_categories WHERE is_active = 1 ORDER BY sort_order ASC";
    $result = mysqli_query($connect, $sql);

    $categories = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = [
                'id' => intval($row['id']),
                'name' => $row['name'],
                'icon' => $row['icon'],
                'sort_order' => intval($row['sort_order'])
            ];
        }
    }

    echo json_encode(['categories' => $categories], JSON_UNESCAPED_UNICODE);
}
