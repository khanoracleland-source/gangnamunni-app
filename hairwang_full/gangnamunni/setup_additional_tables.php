<?php
/**
 * 후기, 유튜브, 병원 특징 테이블 생성
 */

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');

echo "=== 추가 테이블 생성 ===\n\n";

// 1. 후기 테이블
$reviews_sql = "CREATE TABLE IF NOT EXISTS `hospital_reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hospital_id` INT NOT NULL,
    `doctor_id` INT,
    `event_id` INT,
    `user_name` VARCHAR(50) NOT NULL,
    `user_avatar` VARCHAR(500),
    `rating` TINYINT DEFAULT 5 COMMENT '별점 (1-5)',
    `content` TEXT COMMENT '후기 내용',
    `treatments` VARCHAR(255) COMMENT '시술 목록 (쉼표 구분)',
    `images` TEXT COMMENT '이미지 URL 배열 (JSON)',
    `like_count` INT DEFAULT 0,
    `comment_count` INT DEFAULT 0,
    `is_verified` TINYINT(1) DEFAULT 0 COMMENT '영수증 인증 여부',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_hospital` (`hospital_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='병원 후기'";

if (mysqli_query($connect, $reviews_sql)) {
    echo "✅ hospital_reviews 테이블 생성 완료\n";
} else {
    echo "❌ hospital_reviews 테이블 생성 실패: " . mysqli_error($connect) . "\n";
}

// 2. 유튜브 영상 테이블
$youtube_sql = "CREATE TABLE IF NOT EXISTS `hospital_youtube` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hospital_id` INT NOT NULL,
    `video_id` VARCHAR(20) COMMENT 'YouTube Video ID',
    `video_url` VARCHAR(500) NOT NULL,
    `thumbnail_url` VARCHAR(500),
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `duration` VARCHAR(10) COMMENT '재생시간 (mm:ss)',
    `view_count` INT DEFAULT 0,
    `click_count` INT DEFAULT 0 COMMENT '클릭 수',
    `published_at` DATETIME,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_hospital` (`hospital_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='병원 유튜브 영상'";

if (mysqli_query($connect, $youtube_sql)) {
    echo "✅ hospital_youtube 테이블 생성 완료\n";
} else {
    echo "❌ hospital_youtube 테이블 생성 실패: " . mysqli_error($connect) . "\n";
}

// 3. 병원 특징 테이블
$features_sql = "CREATE TABLE IF NOT EXISTS `hospital_features` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hospital_id` INT NOT NULL,
    `feature_type` VARCHAR(50) NOT NULL COMMENT '특징 유형 (doctor_count, parking, cctv 등)',
    `feature_label` VARCHAR(100) NOT NULL COMMENT '표시 라벨',
    `feature_value` VARCHAR(100) COMMENT '값',
    `feature_icon` VARCHAR(50) COMMENT '이모지 아이콘',
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_hospital` (`hospital_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='병원 특징'";

if (mysqli_query($connect, $features_sql)) {
    echo "✅ hospital_features 테이블 생성 완료\n";
} else {
    echo "❌ hospital_features 테이블 생성 실패: " . mysqli_error($connect) . "\n";
}

// 4. 상담 신청 테이블
$consultations_sql = "CREATE TABLE IF NOT EXISTS `hospital_consultations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `hospital_id` INT NOT NULL,
    `doctor_id` INT,
    `event_id` INT,
    `consultation_type` ENUM('hospital', 'doctor', 'kakao') DEFAULT 'hospital',
    `name` VARCHAR(50),
    `phone` VARCHAR(20),
    `message` TEXT,
    `status` ENUM('pending', 'contacted', 'completed', 'cancelled') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_hospital` (`hospital_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='상담 신청'";

if (mysqli_query($connect, $consultations_sql)) {
    echo "✅ hospital_consultations 테이블 생성 완료\n";
} else {
    echo "❌ hospital_consultations 테이블 생성 실패: " . mysqli_error($connect) . "\n";
}

echo "\n=== 샘플 데이터 추가 ===\n\n";

// 첫 번째 병원 ID 가져오기
$hospital_result = mysqli_query($connect, "SELECT id FROM gangnamunni_hospitals ORDER BY id LIMIT 1");
if ($hospital = mysqli_fetch_assoc($hospital_result)) {
    $hospital_id = $hospital['id'];

    // 샘플 후기 추가
    echo "샘플 후기 추가 중...\n";
    $sample_reviews = [
        ['name' => '김*영', 'rating' => 5, 'content' => '시술 결과에 매우 만족합니다. 상담부터 시술, 사후관리까지 모두 친절하고 전문적이었어요. 추천합니다!', 'treatments' => '눈성형,쌍꺼풀'],
        ['name' => '이*진', 'rating' => 5, 'content' => '원장님께서 꼼꼼하게 상담해주시고, 제 얼굴형에 맞는 디자인을 추천해주셨어요. 결과도 자연스럽고 만족스럽습니다.', 'treatments' => '코성형'],
        ['name' => '박*수', 'rating' => 4, 'content' => '회복 기간이 생각보다 짧았고, 붓기도 빨리 빠졌어요. 직원분들도 친절하시고 시설도 깨끗합니다.', 'treatments' => '안면윤곽'],
        ['name' => '정*희', 'rating' => 5, 'content' => '다른 병원도 여러 곳 상담받았는데, 이곳이 가장 신뢰가 갔어요. 과잉진료 없이 필요한 것만 추천해주셔서 좋았습니다.', 'treatments' => '리프팅'],
        ['name' => '최*민', 'rating' => 5, 'content' => '수술 전 걱정이 많았는데, 원장님과 상담하면서 안심이 되었어요. 결과도 기대 이상이고 매우 만족합니다!', 'treatments' => '지방흡입']
    ];

    foreach ($sample_reviews as $review) {
        $sql = "INSERT INTO hospital_reviews (hospital_id, user_name, rating, content, treatments, created_at)
                VALUES ({$hospital_id}, '" . mysqli_real_escape_string($connect, $review['name']) . "',
                        {$review['rating']}, '" . mysqli_real_escape_string($connect, $review['content']) . "',
                        '" . mysqli_real_escape_string($connect, $review['treatments']) . "',
                        DATE_SUB(NOW(), INTERVAL " . rand(1, 30) . " DAY))";
        mysqli_query($connect, $sql);
    }
    echo "✅ 샘플 후기 5개 추가 완료\n";

    // 샘플 유튜브 영상 추가
    echo "샘플 유튜브 영상 추가 중...\n";
    $sample_videos = [
        ['title' => '병원 시설 소개 영상', 'duration' => '5:24', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        ['title' => '눈성형 수술 과정 안내', 'duration' => '8:15', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        ['title' => '환자 후기 인터뷰', 'duration' => '3:42', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        ['title' => '코성형 Before & After', 'duration' => '6:30', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        ['title' => '원장님 시술 철학', 'duration' => '4:18', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
        ['title' => '안면윤곽 수술 상담', 'duration' => '7:55', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']
    ];

    foreach ($sample_videos as $video) {
        $sql = "INSERT INTO hospital_youtube (hospital_id, title, duration, video_url, thumbnail_url)
                VALUES ({$hospital_id}, '" . mysqli_real_escape_string($connect, $video['title']) . "',
                        '" . $video['duration'] . "', '" . $video['url'] . "',
                        'https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg')";
        mysqli_query($connect, $sql);
    }
    echo "✅ 샘플 유튜브 영상 6개 추가 완료\n";

    // 병원 특징 추가
    echo "병원 특징 추가 중...\n";
    $sample_features = [
        ['type' => 'doctor_count', 'label' => '전문의 34명', 'icon' => '👨‍⚕️', 'value' => '34'],
        ['type' => 'consultation_room', 'label' => '진료실부족', 'icon' => '🏥', 'value' => ''],
        ['type' => 'doctor_consult', 'label' => '의사상담', 'icon' => '👤', 'value' => ''],
        ['type' => 'parking', 'label' => '주차가능', 'icon' => '🅿️', 'value' => ''],
        ['type' => 'cctv', 'label' => 'CCTV실시', 'icon' => '📺', 'value' => '']
    ];

    foreach ($sample_features as $idx => $feature) {
        $sql = "INSERT INTO hospital_features (hospital_id, feature_type, feature_label, feature_icon, feature_value, sort_order)
                VALUES ({$hospital_id}, '" . $feature['type'] . "', '" . $feature['label'] . "',
                        '" . $feature['icon'] . "', '" . $feature['value'] . "', " . ($idx + 1) . ")";
        mysqli_query($connect, $sql);
    }
    echo "✅ 병원 특징 5개 추가 완료\n";
}

mysqli_close($connect);

echo "\n✨ 테이블 생성 및 샘플 데이터 추가 완료!\n";
