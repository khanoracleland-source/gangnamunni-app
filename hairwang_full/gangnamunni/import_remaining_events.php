<?php
/**
 * 강남언니 나머지 이벤트 데이터 임포트
 */

set_time_limit(0);
ini_set('memory_limit', '2048M');

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');

$base_path = 'H:/carebaby_project/site_get/output';

echo "=== 강남언니 나머지 이벤트 임포트 ===\n\n";

// 사이트 ID 가져오기
$result = mysqli_query($connect, "SELECT id FROM crawled_sites WHERE site_name = 'www.gangnamunni.com'");
$site_id = mysqli_fetch_assoc($result)['id'];

$imported = 0;
$events_path = $base_path . '/www.gangnamunni.com/events';

if (is_dir($events_path)) {
    // HTML 파일 직접 가져오기
    $event_files = glob($events_path . '/*/*index.html');

    echo "찾은 파일: " . count($event_files) . "개\n\n";

    foreach ($event_files as $index_file) {
        $path_parts = explode('/', str_replace('\\', '/', $index_file));
        $event_id = $path_parts[count($path_parts) - 2]; // events/[ID]/index.html

        if (file_exists($index_file)) {
            $html = file_get_contents($index_file);
            $file_size = round(filesize($index_file) / 1024);

            $name = '';

            // 타이틀에서 이벤트명 추출
            if (preg_match('/<title>(.*?)\s*\|/s', $html, $matches)) {
                $name = trim(strip_tags($matches[1]));
            }

            // 타이틀이 없으면 h1 태그 시도
            if (empty($name) && preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $html, $matches)) {
                $name = trim(strip_tags($matches[1]));
            }

            if (!empty($name) && strlen($name) > 3) {
                $sql = "INSERT INTO crawled_events
                        (site_id, event_id, event_name, file_path, file_size_kb)
                        VALUES (
                            {$site_id},
                            '" . mysqli_real_escape_string($connect, $event_id) . "',
                            '" . mysqli_real_escape_string($connect, $name) . "',
                            '" . mysqli_real_escape_string($connect, $index_file) . "',
                            {$file_size}
                        )
                        ON DUPLICATE KEY UPDATE
                            event_name = VALUES(event_name)";

                if (mysqli_query($connect, $sql)) {
                    $imported++;
                    if ($imported % 100 == 0) {
                        echo "✓ {$imported}개 이벤트 처리 완료\n";
                    }
                }
            }
        }
    }
}

mysqli_close($connect);

echo "\n✅ 총 {$imported}개 이벤트 임포트 완료!\n";
