<?php
require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');

echo "=== 데이터베이스 현황 ===\n\n";

$tables = [
    'crawled_sites' => '사이트',
    'crawled_hospitals' => '병원',
    'crawled_doctors' => '의사',
    'crawled_events' => '이벤트',
    'crawled_tips' => '의료팁'
];

foreach ($tables as $table => $label) {
    $result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM $table");
    $count = mysqli_fetch_assoc($result)['cnt'];
    echo "$label ($table): " . number_format($count) . "개\n";
}

echo "\n=== 사이트별 상세 현황 ===\n\n";
$result = mysqli_query($connect, "SELECT s.site_name,
    (SELECT COUNT(*) FROM crawled_hospitals WHERE site_id = s.id) as hospitals,
    (SELECT COUNT(*) FROM crawled_doctors WHERE site_id = s.id) as doctors,
    (SELECT COUNT(*) FROM crawled_events WHERE site_id = s.id) as events,
    (SELECT COUNT(*) FROM crawled_tips WHERE site_id = s.id) as tips
    FROM crawled_sites s");

while ($row = mysqli_fetch_assoc($result)) {
    echo "{$row['site_name']}:\n";
    echo "  - 병원: {$row['hospitals']}개\n";
    echo "  - 의사: {$row['doctors']}명\n";
    echo "  - 이벤트: {$row['events']}개\n";
    echo "  - 의료팁: {$row['tips']}개\n\n";
}

mysqli_close($connect);
