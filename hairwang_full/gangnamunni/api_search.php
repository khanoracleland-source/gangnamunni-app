<?php
/**
 * 검색 자동완성 API
 * GET ?q=검색어&limit=10
 * 병원명, 주소, 시술명, 의사명에서 검색
 */
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once dirname(__DIR__) . '/dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) jsonError('DB 연결 실패', 500);
mysqli_set_charset($connect, 'utf8mb4');

$q = trim($_GET['q'] ?? '');
$limit = max(1, min(intval($_GET['limit'] ?? 10), 20));
$type = $_GET['type'] ?? ''; // hospital, treatment, doctor, '' (all)

if (mb_strlen($q) < 1) {
    jsonSuccess(['results' => [], 'query' => $q]);
}

$like = '%' . $q . '%';
$results = [];

// 1. 병원 검색
if (!$type || $type === 'hospital') {
    $stmt = mysqli_prepare($connect,
        "SELECT id, name, address, rating, review_count, main_image_url
         FROM gangnamunni_hospitals h
         WHERE (h.name LIKE ? OR h.address LIKE ?)
           AND h.is_active = 1 AND COALESCE(h.is_demo, 0) = 0 AND (h.status = 'approved' OR h.status IS NULL)
         ORDER BY rating DESC
         LIMIT ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ssi', $like, $like, $limit);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            $results[] = [
                'type' => 'hospital',
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'sub' => $row['address'],
                'rating' => (float)$row['rating'],
                'reviews' => (int)$row['review_count'],
                'image' => $row['main_image_url'],
                'url' => 'detail.php?id=' . $row['id']
            ];
        }
        mysqli_stmt_close($stmt);
    }
}

// 2. 시술 검색
if (!$type || $type === 'treatment') {
    $stmt2 = mysqli_prepare($connect,
        "SELECT t.id, t.name, t.category, t.price, t.discount_price, h.name as hospital_name, h.id as hospital_id
         FROM gangnamunni_treatments t
         JOIN gangnamunni_hospitals h ON t.hospital_id = h.id
         WHERE (t.name LIKE ? OR t.category LIKE ?)
           AND t.is_active = 1
           AND h.is_active = 1 AND COALESCE(h.is_demo, 0) = 0 AND (h.status = 'approved' OR h.status IS NULL)
         ORDER BY t.is_popular DESC, t.name
         LIMIT ?");
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, 'ssi', $like, $like, $limit);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        while ($row = mysqli_fetch_assoc($res2)) {
            $price_text = '';
            if ($row['discount_price'] > 0) {
                $price_text = number_format($row['discount_price']) . '원';
            } elseif ($row['price'] > 0) {
                $price_text = number_format($row['price']) . '원';
            }
            $results[] = [
                'type' => 'treatment',
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'sub' => $row['hospital_name'] . ($price_text ? ' · ' . $price_text : ''),
                'category' => $row['category'],
                'url' => 'detail.php?id=' . $row['hospital_id']
            ];
        }
        mysqli_stmt_close($stmt2);
    }
}

// 3. 의사 검색
if (!$type || $type === 'doctor') {
    $stmt3 = mysqli_prepare($connect,
        "SELECT d.id, d.name, d.specialty, d.experience, d.photo_url, h.name as hospital_name
         FROM gangnamunni_doctors d
         LEFT JOIN gangnamunni_hospitals h ON d.hospital_id = h.id
         WHERE (d.name LIKE ? OR d.specialty LIKE ?)
           AND (d.hospital_id IS NULL OR h.id IS NULL OR (h.is_active = 1 AND COALESCE(h.is_demo, 0) = 0 AND (h.status = 'approved' OR h.status IS NULL)))
         ORDER BY d.experience DESC
         LIMIT ?");
    if ($stmt3) {
        mysqli_stmt_bind_param($stmt3, 'ssi', $like, $like, $limit);
        mysqli_stmt_execute($stmt3);
        $res3 = mysqli_stmt_get_result($stmt3);
        while ($row = mysqli_fetch_assoc($res3)) {
            $results[] = [
                'type' => 'doctor',
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'sub' => ($row['hospital_name'] ?? '') . ($row['specialty'] ? ' · ' . $row['specialty'] : ''),
                'image' => $row['photo_url'],
                'url' => 'doctor_detail.php?id=' . $row['id']
            ];
        }
        mysqli_stmt_close($stmt3);
    }
}

mysqli_close($connect);
jsonSuccess(['results' => $results, 'query' => $q, 'count' => count($results)]);
