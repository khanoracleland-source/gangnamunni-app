<?php
/**
 * 이벤트 테이블 생성 스크립트
 */

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');

// 이벤트 테이블 생성
$table_sql = "CREATE TABLE IF NOT EXISTS gangnamunni_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    subtitle VARCHAR(500),
    description TEXT,
    image_url VARCHAR(1000),
    hospital_id INT,
    hospital_name VARCHAR(255),
    category VARCHAR(100),
    original_price INT DEFAULT 0,
    discount_price INT DEFAULT 0,
    discount_rate INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    view_count INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active, display_order),
    INDEX idx_hospital (hospital_id),
    INDEX idx_category (category),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($connect, $table_sql)) {
    echo "✅ gangnamunni_events 테이블이 생성되었습니다.\n";
} else {
    echo "❌ 테이블 생성 실패: " . mysqli_error($connect) . "\n";
}

// 샘플 데이터 삽입
$sample_events = [
    [
        'title' => '눈성형 특별 이벤트',
        'subtitle' => '쌍꺼풀 수술 20% 할인',
        'description' => '자연스러운 눈매 완성! 쌍꺼풀 수술 특별 할인 이벤트',
        'category' => '눈성형',
        'original_price' => 2000000,
        'discount_price' => 1600000,
        'discount_rate' => 20,
        'start_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d', strtotime('+30 days'))
    ],
    [
        'title' => '코성형 프리미엄 패키지',
        'subtitle' => '콧대+코끝 동시 진행 시 특가',
        'description' => '프리미엄 코성형 패키지 진행 중',
        'category' => '코성형',
        'original_price' => 5000000,
        'discount_price' => 4000000,
        'discount_rate' => 20,
        'start_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d', strtotime('+60 days'))
    ],
    [
        'title' => '리프팅 시술 특가',
        'subtitle' => '올리지오 + 인모드 결합',
        'description' => '탄력있는 피부를 위한 리프팅 시술',
        'category' => '리프팅',
        'original_price' => 800000,
        'discount_price' => 600000,
        'discount_rate' => 25,
        'start_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d', strtotime('+45 days'))
    ]
];

foreach ($sample_events as $event) {
    $sql = "INSERT INTO gangnamunni_events
            (title, subtitle, description, category, original_price, discount_price, discount_rate, start_date, end_date)
            VALUES (
                '" . mysqli_real_escape_string($connect, $event['title']) . "',
                '" . mysqli_real_escape_string($connect, $event['subtitle']) . "',
                '" . mysqli_real_escape_string($connect, $event['description']) . "',
                '" . mysqli_real_escape_string($connect, $event['category']) . "',
                {$event['original_price']},
                {$event['discount_price']},
                {$event['discount_rate']},
                '{$event['start_date']}',
                '{$event['end_date']}'
            )";

    if (mysqli_query($connect, $sql)) {
        echo "✅ 샘플 이벤트 추가: {$event['title']}\n";
    }
}

mysqli_close($connect);

echo "\n✨ 이벤트 테이블 설정이 완료되었습니다!\n";
