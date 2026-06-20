<?php
/**
 * 크롤링 데이터 통합 관리 테이블 생성
 */

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');

echo "=== 크롤링 데이터 테이블 생성 시작 ===\n\n";

// 1. 사이트 정보 테이블
$tables[] = "CREATE TABLE IF NOT EXISTS crawled_sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_name VARCHAR(100) NOT NULL,
    site_url VARCHAR(255) NOT NULL,
    site_type VARCHAR(50),
    total_files INT DEFAULT 0,
    total_size_mb DECIMAL(10,2) DEFAULT 0,
    crawled_date DATE,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_site_url (site_url),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// 2. 병원 정보 테이블
$tables[] = "CREATE TABLE IF NOT EXISTS crawled_hospitals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT,
    hospital_id VARCHAR(100),
    hospital_name VARCHAR(255) NOT NULL,
    address TEXT,
    location VARCHAR(255),
    phone VARCHAR(50),
    description TEXT,
    services TEXT,
    review_count INT DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0,
    file_path TEXT,
    file_size_kb INT DEFAULT 0,
    images TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_site_hospital (site_id, hospital_id),
    INDEX idx_name (hospital_name),
    INDEX idx_location (location),
    FOREIGN KEY (site_id) REFERENCES crawled_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// 3. 의사 정보 테이블
$tables[] = "CREATE TABLE IF NOT EXISTS crawled_doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT,
    doctor_id VARCHAR(100),
    doctor_name VARCHAR(100) NOT NULL,
    hospital_id INT,
    hospital_name VARCHAR(255),
    specialty VARCHAR(100),
    description TEXT,
    file_path TEXT,
    file_size_kb INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_site_doctor (site_id, doctor_id),
    INDEX idx_name (doctor_name),
    INDEX idx_hospital (hospital_id),
    FOREIGN KEY (site_id) REFERENCES crawled_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// 4. 이벤트/시술 정보 테이블
$tables[] = "CREATE TABLE IF NOT EXISTS crawled_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT,
    event_id VARCHAR(100),
    event_name VARCHAR(500) NOT NULL,
    hospital_name VARCHAR(255),
    brand_name VARCHAR(255),
    category VARCHAR(100),
    description TEXT,
    original_price INT DEFAULT 0,
    discount_price INT DEFAULT 0,
    review_count INT DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0,
    file_path TEXT,
    file_size_kb INT DEFAULT 0,
    images TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_site_event (site_id, event_id),
    INDEX idx_name (event_name(100)),
    INDEX idx_category (category),
    INDEX idx_price (discount_price),
    FOREIGN KEY (site_id) REFERENCES crawled_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// 5. 의료 팁/커뮤니티 테이블
$tables[] = "CREATE TABLE IF NOT EXISTS crawled_tips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT,
    tip_id VARCHAR(100),
    title VARCHAR(500) NOT NULL,
    content TEXT,
    category VARCHAR(100),
    author VARCHAR(100),
    views INT DEFAULT 0,
    file_path TEXT,
    file_size_kb INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_site_tip (site_id, tip_id),
    INDEX idx_title (title(100)),
    INDEX idx_category (category),
    FOREIGN KEY (site_id) REFERENCES crawled_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// 6. 이미지 정보 테이블
$tables[] = "CREATE TABLE IF NOT EXISTS crawled_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT,
    image_type VARCHAR(50),
    image_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255),
    file_size_kb INT DEFAULT 0,
    format VARCHAR(10),
    related_id INT,
    related_type ENUM('hospital', 'doctor', 'event', 'tip', 'other'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (image_type),
    INDEX idx_related (related_type, related_id),
    FOREIGN KEY (site_id) REFERENCES crawled_sites(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

// 테이블 생성
foreach ($tables as $i => $sql) {
    if (mysqli_query($connect, $sql)) {
        echo "✅ 테이블 " . ($i + 1) . " 생성 완료\n";
    } else {
        echo "❌ 테이블 " . ($i + 1) . " 생성 실패: " . mysqli_error($connect) . "\n";
    }
}

// 기본 사이트 정보 추가
$sites = [
    ['web.babitalk.com', 'https://web.babitalk.com', '병원 검색 플랫폼', 47, 2.9],
    ['www.yeoshin.co.kr', 'https://www.yeoshin.co.kr', '피부과/성형외과 예약', 1144, 742],
    ['www.gangnamunni.com', 'https://www.gangnamunni.com', '성형/피부시술 정보', 3544, 510]
];

echo "\n=== 사이트 정보 등록 ===\n";
foreach ($sites as $site) {
    $sql = "INSERT INTO crawled_sites (site_name, site_url, site_type, total_files, total_size_mb, crawled_date, status)
            VALUES (
                '" . mysqli_real_escape_string($connect, $site[0]) . "',
                '" . mysqli_real_escape_string($connect, $site[1]) . "',
                '" . mysqli_real_escape_string($connect, $site[2]) . "',
                {$site[3]},
                {$site[4]},
                CURDATE(),
                'active'
            )
            ON DUPLICATE KEY UPDATE
                total_files = {$site[3]},
                total_size_mb = {$site[4]},
                crawled_date = CURDATE()";

    if (mysqli_query($connect, $sql)) {
        echo "✅ 사이트 등록: {$site[0]}\n";
    } else {
        echo "❌ 사이트 등록 실패: " . mysqli_error($connect) . "\n";
    }
}

mysqli_close($connect);

echo "\n✨ 테이블 생성이 완료되었습니다!\n";
echo "\n다음 단계: import_crawled_data.php 실행\n";
