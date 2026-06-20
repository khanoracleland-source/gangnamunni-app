<?php
/**
 * 강남언니 전체 이벤트 임포트 (1,346개)
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

echo "=== 강남언니 전체 이벤트 임포트 ===\n\n";

// 사이트 ID 가져오기
$result = mysqli_query($connect, "SELECT id FROM crawled_sites WHERE site_name = 'www.gangnamunni.com'");
$site_id = mysqli_fetch_assoc($result)['id'];

$imported = 0;
$skipped = 0;
$events_path = $base_path . '/www.gangnamunni.com/events';

if (is_dir($events_path)) {
    // events 디렉토리 바로 밑의 HTML 파일들 가져오기
    $event_files = glob($events_path . '/*.html');

    echo "찾은 파일: " . count($event_files) . "개\n\n";

    foreach ($event_files as $html_file) {
        $filename = basename($html_file);

        // 파일명에서 event_id와 option_id 추출
        // 예: 100431_optionId_05d7758e-c8ae-481d-a699-840a6dfc6000.html
        if (preg_match('/^(\d+)_optionId_(.+)\.html$/', $filename, $matches)) {
            $event_id = $matches[1] . '_' . $matches[2];

            $html = file_get_contents($html_file);
            $file_size = round(filesize($html_file) / 1024);

            $name = '';

            // 타이틀에서 이벤트명 추출
            if (preg_match('/<title>(.*?)\s*[\|\-]/s', $html, $title_matches)) {
                $name = trim(strip_tags($title_matches[1]));
            }

            // 타이틀이 없으면 다른 방법 시도
            if (empty($name)) {
                if (preg_match('/<h1[^>]*>(.*?)<\/h1>/s', $html, $h1_matches)) {
                    $name = trim(strip_tags($h1_matches[1]));
                }
            }

            if (!empty($name) && strlen($name) > 2) {
                $sql = "INSERT INTO crawled_events
                        (site_id, event_id, event_name, file_path, file_size_kb)
                        VALUES (
                            {$site_id},
                            '" . mysqli_real_escape_string($connect, $event_id) . "',
                            '" . mysqli_real_escape_string($connect, $name) . "',
                            '" . mysqli_real_escape_string($connect, $html_file) . "',
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
            } else {
                $skipped++;
            }
        }
    }
}

mysqli_close($connect);

echo "\n✅ 총 {$imported}개 이벤트 임포트 완료!\n";
echo "건너뜀: {$skipped}개\n";
