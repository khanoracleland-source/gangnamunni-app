<?php
/**
 * 병원 이미지 갤러리 테이블 생성
 * - 여러 이미지를 저장하고 순서대로 표시
 */

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');

echo "=== 병원 이미지 갤러리 테이블 생성 ===\n\n";

// 1. hospital_images 테이블 생성
$table_sql = "CREATE TABLE IF NOT EXISTS hospital_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    image_type ENUM('main', 'interior', 'exterior', 'facility', 'etc') DEFAULT 'main',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_hospital (hospital_id),
    INDEX idx_active_order (hospital_id, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='병원 이미지 갤러리'";

if (mysqli_query($connect, $table_sql)) {
    echo "✅ hospital_images 테이블이 생성되었습니다.\n\n";
} else {
    echo "❌ 테이블 생성 실패: " . mysqli_error($connect) . "\n";
    exit;
}

// 2. 기존 gangnamunni_hospitals 테이블의 이미지를 hospital_images로 마이그레이션
echo "기존 이미지 데이터 마이그레이션 중...\n";

$hospitals_query = "SELECT id, main_image_url, profile_image_url FROM gangnamunni_hospitals WHERE main_image_url IS NOT NULL OR profile_image_url IS NOT NULL";
$hospitals_result = mysqli_query($connect, $hospitals_query);

$migrated = 0;
while ($hospital = mysqli_fetch_assoc($hospitals_result)) {
    $hospital_id = $hospital['id'];

    // main_image_url을 첫 번째 이미지로 추가
    if (!empty($hospital['main_image_url'])) {
        $image_url = mysqli_real_escape_string($connect, $hospital['main_image_url']);

        // 중복 체크
        $check_sql = "SELECT id FROM hospital_images WHERE hospital_id = {$hospital_id} AND image_url = '{$image_url}'";
        $check_result = mysqli_query($connect, $check_sql);

        if (mysqli_num_rows($check_result) == 0) {
            $insert_sql = "INSERT INTO hospital_images (hospital_id, image_url, image_type, sort_order)
                          VALUES ({$hospital_id}, '{$image_url}', 'main', 1)";

            if (mysqli_query($connect, $insert_sql)) {
                $migrated++;
            }
        }
    }

    // profile_image_url을 두 번째 이미지로 추가 (main_image_url과 다른 경우)
    if (!empty($hospital['profile_image_url']) && $hospital['profile_image_url'] !== $hospital['main_image_url']) {
        $image_url = mysqli_real_escape_string($connect, $hospital['profile_image_url']);

        // 중복 체크
        $check_sql = "SELECT id FROM hospital_images WHERE hospital_id = {$hospital_id} AND image_url = '{$image_url}'";
        $check_result = mysqli_query($connect, $check_sql);

        if (mysqli_num_rows($check_result) == 0) {
            $insert_sql = "INSERT INTO hospital_images (hospital_id, image_url, image_type, sort_order)
                          VALUES ({$hospital_id}, '{$image_url}', 'main', 2)";

            if (mysqli_query($connect, $insert_sql)) {
                $migrated++;
            }
        }
    }
}

echo "✅ {$migrated}개 이미지 마이그레이션 완료\n\n";

// 3. 샘플 이미지 추가 (테스트용)
echo "샘플 이미지 추가 중...\n";

// 첫 번째 병원에 여러 샘플 이미지 추가
$first_hospital_query = "SELECT id FROM gangnamunni_hospitals ORDER BY id LIMIT 1";
$first_hospital_result = mysqli_query($connect, $first_hospital_query);

if ($first_hospital = mysqli_fetch_assoc($first_hospital_result)) {
    $hospital_id = $first_hospital['id'];

    $sample_images = [
        ['url' => 'https://via.placeholder.com/800x400/FF6B6B/FFFFFF?text=병원+외관', 'type' => 'exterior', 'order' => 10],
        ['url' => 'https://via.placeholder.com/800x400/4ECDC4/FFFFFF?text=대기실', 'type' => 'interior', 'order' => 11],
        ['url' => 'https://via.placeholder.com/800x400/45B7D1/FFFFFF?text=진료실', 'type' => 'facility', 'order' => 12],
        ['url' => 'https://via.placeholder.com/800x400/96CEB4/FFFFFF?text=수술실', 'type' => 'facility', 'order' => 13],
        ['url' => 'https://via.placeholder.com/800x400/FFEAA7/000000?text=회복실', 'type' => 'facility', 'order' => 14],
        ['url' => 'https://via.placeholder.com/800x400/DFE6E9/000000?text=상담실', 'type' => 'interior', 'order' => 15],
    ];

    $added = 0;
    foreach ($sample_images as $img) {
        $url = mysqli_real_escape_string($connect, $img['url']);
        $type = $img['type'];
        $order = $img['order'];

        // 중복 체크
        $check_sql = "SELECT id FROM hospital_images WHERE hospital_id = {$hospital_id} AND image_url = '{$url}'";
        $check_result = mysqli_query($connect, $check_sql);

        if (mysqli_num_rows($check_result) == 0) {
            $insert_sql = "INSERT INTO hospital_images (hospital_id, image_url, image_type, sort_order)
                          VALUES ({$hospital_id}, '{$url}', '{$type}', {$order})";

            if (mysqli_query($connect, $insert_sql)) {
                $added++;
            }
        }
    }

    echo "✅ 병원 ID {$hospital_id}에 {$added}개 샘플 이미지 추가 완료\n";
}

mysqli_close($connect);

echo "\n✨ 병원 이미지 갤러리 테이블 설정 완료!\n";
echo "\n다음 단계: detail.php에서 이미지 갤러리 확인\n";
