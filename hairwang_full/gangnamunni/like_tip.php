<?php
/**
 * 꿀팁 좋아요 처리 API
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../dbconfig.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    echo json_encode(['success' => false, 'message' => 'DB 연결 실패']);
    exit;
}

mysqli_set_charset($connect, 'utf8mb4');

$tip_id = isset($_POST['tip_id']) ? intval($_POST['tip_id']) : 0;

if ($tip_id <= 0) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

// 좋아요 증가
$query = "UPDATE gangnamunni_tips SET likes = likes + 1 WHERE id = {$tip_id}";
$result = mysqli_query($connect, $query);

if ($result) {
    $count_query = "SELECT likes FROM gangnamunni_tips WHERE id = {$tip_id}";
    $count_result = mysqli_query($connect, $count_query);
    $row = mysqli_fetch_assoc($count_result);

    echo json_encode([
        'success' => true,
        'likes' => $row['likes']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.']);
}

mysqli_close($connect);
?>
