<?php
/**
 * 모든 크롤링 데이터 임포트 (3개 사이트 통합)
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

echo "=== 크롤링 데이터 통합 임포트 시작 ===\n\n";

// 사이트 ID 가져오기
$site_ids = [];
$result = mysqli_query($connect, "SELECT id, site_name FROM crawled_sites");
while ($row = mysqli_fetch_assoc($result)) {
    $site_ids[$row['site_name']] = $row['id'];
}

$total_imported = [
    'hospitals' => 0,
    'doctors' => 0,
    'events' => 0,
    'tips' => 0,
    'images' => 0
];

// ============================================
// 1. www.gangnamunni.com 데이터 임포트
// ============================================
echo "📌 1. www.gangnamunni.com 데이터 처리 중...\n";
$site_id = $site_ids['www.gangnamunni.com'] ?? null;

if ($site_id) {
    // 1-1. 병원 데이터 (142개)
    echo "  병원 데이터 처리 중...\n";
    $hospitals_path = $base_path . '/www.gangnamunni.com/hospitals';
    if (is_dir($hospitals_path)) {
        $hospital_dirs = glob($hospitals_path . '/*', GLOB_ONLYDIR);
        foreach ($hospital_dirs as $dir) {
            $hospital_id = basename($dir);
            $index_file = $dir . '/index.html';

            if (file_exists($index_file)) {
                $html = file_get_contents($index_file);
                $file_size = round(filesize($index_file) / 1024);

                // HTML 파싱
                $name = '';
                $address = '';
                $review_count = 0;

                // 타이틀에서 병원명 추출
                if (preg_match('/<title>(.*?)\s*\|/s', $html, $matches)) {
                    $name = trim(strip_tags($matches[1]));
                }

                // 주소 추출
                if (preg_match('/"address":\s*"(.*?)"/s', $html, $matches)) {
                    $address = trim($matches[1]);
                }

                // 리뷰 수 추출
                if (preg_match('/(\d+)개의\s*리뷰/s', $html, $matches)) {
                    $review_count = intval($matches[1]);
                }

                if (!empty($name)) {
                    $sql = "INSERT INTO crawled_hospitals
                            (site_id, hospital_id, hospital_name, address, review_count, file_path, file_size_kb)
                            VALUES (
                                {$site_id},
                                '" . mysqli_real_escape_string($connect, $hospital_id) . "',
                                '" . mysqli_real_escape_string($connect, $name) . "',
                                '" . mysqli_real_escape_string($connect, $address) . "',
                                {$review_count},
                                '" . mysqli_real_escape_string($connect, $index_file) . "',
                                {$file_size}
                            )
                            ON DUPLICATE KEY UPDATE
                                hospital_name = VALUES(hospital_name),
                                address = VALUES(address),
                                review_count = VALUES(review_count)";

                    if (mysqli_query($connect, $sql)) {
                        $total_imported['hospitals']++;
                        if ($total_imported['hospitals'] % 20 == 0) {
                            echo "    ✓ {$total_imported['hospitals']}개 병원 처리 완료\n";
                        }
                    }
                }
            }
        }
    }
    echo "  ✅ 병원 {$total_imported['hospitals']}개 임포트 완료\n\n";

    // 1-2. 의사 데이터 (129개)
    echo "  의사 데이터 처리 중...\n";
    $doctors_path = $base_path . '/www.gangnamunni.com/doctors';
    if (is_dir($doctors_path)) {
        $doctor_dirs = glob($doctors_path . '/*', GLOB_ONLYDIR);
        foreach ($doctor_dirs as $dir) {
            $doctor_id = basename($dir);
            $index_file = $dir . '/index.html';

            if (file_exists($index_file)) {
                $html = file_get_contents($index_file);
                $file_size = round(filesize($index_file) / 1024);

                $name = '';
                $hospital_name = '';

                // 의사명과 병원명 추출
                if (preg_match('/"name":\s*"(.*?)"/s', $html, $matches)) {
                    $name = trim($matches[1]);
                }

                if (preg_match('/"hospitalName":\s*"(.*?)"/s', $html, $matches)) {
                    $hospital_name = trim($matches[1]);
                }

                if (!empty($name)) {
                    $sql = "INSERT INTO crawled_doctors
                            (site_id, doctor_id, doctor_name, hospital_name, file_path, file_size_kb)
                            VALUES (
                                {$site_id},
                                '" . mysqli_real_escape_string($connect, $doctor_id) . "',
                                '" . mysqli_real_escape_string($connect, $name) . "',
                                '" . mysqli_real_escape_string($connect, $hospital_name) . "',
                                '" . mysqli_real_escape_string($connect, $index_file) . "',
                                {$file_size}
                            )
                            ON DUPLICATE KEY UPDATE
                                doctor_name = VALUES(doctor_name),
                                hospital_name = VALUES(hospital_name)";

                    if (mysqli_query($connect, $sql)) {
                        $total_imported['doctors']++;
                        if ($total_imported['doctors'] % 20 == 0) {
                            echo "    ✓ {$total_imported['doctors']}명 의사 처리 완료\n";
                        }
                    }
                }
            }
        }
    }
    echo "  ✅ 의사 {$total_imported['doctors']}명 임포트 완료\n\n";

    // 1-3. 이벤트 데이터 (1,346개) - 전체
    echo "  이벤트 데이터 처리 중 (1,346개)...\n";
    $events_path = $base_path . '/www.gangnamunni.com/events';
    if (is_dir($events_path)) {
        $event_dirs = glob($events_path . '/*', GLOB_ONLYDIR);
        foreach ($event_dirs as $dir) {
            $event_id = basename($dir);
            $index_file = $dir . '/index.html';

            if (file_exists($index_file)) {
                $html = file_get_contents($index_file);
                $file_size = round(filesize($index_file) / 1024);

                $name = '';

                // 타이틀에서 이벤트명 추출
                if (preg_match('/<title>(.*?)\s*\|/s', $html, $matches)) {
                    $name = trim(strip_tags($matches[1]));
                }

                if (!empty($name)) {
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
                        $total_imported['events']++;
                        if ($total_imported['events'] % 100 == 0) {
                            echo "    ✓ {$total_imported['events']}개 이벤트 처리 완료\n";
                        }
                    }
                }
            }
        }
    }
    echo "  ✅ 이벤트 {$total_imported['events']}개 임포트 완료\n\n";

    // 1-4. 의료 팁 데이터 (41개)
    echo "  의료 팁 데이터 처리 중...\n";
    $tips_path = $base_path . '/www.gangnamunni.com/community/medical-tips';
    if (is_dir($tips_path)) {
        $tip_dirs = glob($tips_path . '/*', GLOB_ONLYDIR);
        foreach ($tip_dirs as $dir) {
            $tip_id = basename($dir);
            $index_file = $dir . '/index.html';

            if (file_exists($index_file)) {
                $html = file_get_contents($index_file);
                $file_size = round(filesize($index_file) / 1024);

                $title = '';

                // 타이틀 추출
                if (preg_match('/<title>(.*?)\s*\|/s', $html, $matches)) {
                    $title = trim(strip_tags($matches[1]));
                }

                if (!empty($title)) {
                    $sql = "INSERT INTO crawled_tips
                            (site_id, tip_id, title, file_path, file_size_kb)
                            VALUES (
                                {$site_id},
                                '" . mysqli_real_escape_string($connect, $tip_id) . "',
                                '" . mysqli_real_escape_string($connect, $title) . "',
                                '" . mysqli_real_escape_string($connect, $index_file) . "',
                                {$file_size}
                            )
                            ON DUPLICATE KEY UPDATE
                                title = VALUES(title)";

                    if (mysqli_query($connect, $sql)) {
                        $total_imported['tips']++;
                    }
                }
            }
        }
    }
    echo "  ✅ 의료 팁 {$total_imported['tips']}개 임포트 완료\n\n";
}

// ============================================
// 2. www.yeoshin.co.kr 데이터 임포트
// ============================================
echo "📌 2. www.yeoshin.co.kr 데이터 처리 중...\n";
$site_id = $site_ids['www.yeoshin.co.kr'] ?? null;

if ($site_id) {
    // 2-1. 병원 데이터 (6개)
    echo "  병원 데이터 처리 중...\n";
    $hospitals_path = $base_path . '/www.yeoshin.co.kr/hospitals';
    if (is_dir($hospitals_path)) {
        $hospital_dirs = glob($hospitals_path . '/*', GLOB_ONLYDIR);
        foreach ($hospital_dirs as $dir) {
            $hospital_id = basename($dir);
            $index_file = $dir . '/index.html';

            if (file_exists($index_file)) {
                $html = file_get_contents($index_file);
                $file_size = round(filesize($index_file) / 1024);

                $name = '';
                $location = '';

                // 타이틀 추출
                if (preg_match('/<title>(.*?)\s*[\|\-]/s', $html, $matches)) {
                    $name = trim(strip_tags($matches[1]));
                }

                // 위치 추출
                if (preg_match('/"address":\s*"(.*?)"/s', $html, $matches)) {
                    $location = trim($matches[1]);
                }

                if (!empty($name)) {
                    $sql = "INSERT INTO crawled_hospitals
                            (site_id, hospital_id, hospital_name, location, file_path, file_size_kb)
                            VALUES (
                                {$site_id},
                                '" . mysqli_real_escape_string($connect, $hospital_id) . "',
                                '" . mysqli_real_escape_string($connect, $name) . "',
                                '" . mysqli_real_escape_string($connect, $location) . "',
                                '" . mysqli_real_escape_string($connect, $index_file) . "',
                                {$file_size}
                            )
                            ON DUPLICATE KEY UPDATE
                                hospital_name = VALUES(hospital_name),
                                location = VALUES(location)";

                    if (mysqli_query($connect, $sql)) {
                        $total_imported['hospitals']++;
                    }
                }
            }
        }
    }
    echo "  ✅ 병원 6개 임포트 완료\n\n";

    // 2-2. 이벤트/시술 데이터 (923개) - 전체
    echo "  이벤트/시술 데이터 처리 중 (923개)...\n";
    $events_path = $base_path . '/www.yeoshin.co.kr/event/mobile';
    if (is_dir($events_path)) {
        $event_dirs = glob($events_path . '/*', GLOB_ONLYDIR);
        foreach ($event_dirs as $dir) {
            $event_id = basename($dir);
            $index_file = $dir . '/index.html';

            if (file_exists($index_file)) {
                $html = file_get_contents($index_file);
                $file_size = round(filesize($index_file) / 1024);

                $name = '';
                $brand = '';
                $price = 0;
                $rating = 0;
                $review_count = 0;

                // Schema.org 데이터 추출
                if (preg_match('/"name":\s*"(.*?)"/s', $html, $matches)) {
                    $name = trim($matches[1]);
                }

                if (preg_match('/"brand".*?"name":\s*"(.*?)"/s', $html, $matches)) {
                    $brand = trim($matches[1]);
                }

                if (preg_match('/"price":\s*"(\d+)"/s', $html, $matches)) {
                    $price = intval($matches[1]);
                }

                if (preg_match('/"ratingValue":\s*(\d+\.?\d*)/s', $html, $matches)) {
                    $rating = floatval($matches[1]);
                }

                if (preg_match('/"reviewCount":\s*(\d+)/s', $html, $matches)) {
                    $review_count = intval($matches[1]);
                }

                if (!empty($name)) {
                    $sql = "INSERT INTO crawled_events
                            (site_id, event_id, event_name, brand_name, discount_price, rating, review_count, file_path, file_size_kb)
                            VALUES (
                                {$site_id},
                                '" . mysqli_real_escape_string($connect, $event_id) . "',
                                '" . mysqli_real_escape_string($connect, $name) . "',
                                '" . mysqli_real_escape_string($connect, $brand) . "',
                                {$price},
                                {$rating},
                                {$review_count},
                                '" . mysqli_real_escape_string($connect, $index_file) . "',
                                {$file_size}
                            )
                            ON DUPLICATE KEY UPDATE
                                event_name = VALUES(event_name),
                                brand_name = VALUES(brand_name)";

                    if (mysqli_query($connect, $sql)) {
                        $total_imported['events']++;
                        if ($total_imported['events'] % 100 == 0) {
                            echo "    ✓ {$total_imported['events']}개 이벤트 처리 완료\n";
                        }
                    }
                }
            }
        }
    }
    echo "  ✅ 이벤트/시술 임포트 완료\n\n";
}

mysqli_close($connect);

echo "=== 데이터 임포트 완료 ===\n\n";
echo "📊 임포트 결과:\n";
echo "  - 병원: {$total_imported['hospitals']}개\n";
echo "  - 의사: {$total_imported['doctors']}명\n";
echo "  - 이벤트: {$total_imported['events']}개\n";
echo "  - 의료팁: {$total_imported['tips']}개\n";
echo "\n✨ 모든 작업이 완료되었습니다!\n";
echo "\n다음 단계: crawled_data_viewer.php 페이지에서 확인\n";
