<?php
/**
 * 하단 네비게이션 메뉴 테이블 생성 및 초기 데이터 설정
 */

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');

// 네비게이션 메뉴 테이블 생성
$table_sql = "CREATE TABLE IF NOT EXISTS navigation_menu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    icon VARCHAR(10) NOT NULL,
    label VARCHAR(50) NOT NULL,
    page_url VARCHAR(255) NOT NULL,
    icon_type ENUM('emoji', 'image') DEFAULT 'emoji',
    icon_image VARCHAR(500),
    icon_image_active VARCHAR(500),
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if (mysqli_query($connect, $table_sql)) {
    echo "✅ navigation_menu 테이블이 생성되었습니다.\n";
} else {
    echo "❌ 테이블 생성 실패: " . mysqli_error($connect) . "\n";
    exit;
}

// 기존 테이블에 컬럼 추가 (존재하지 않는 경우)
$alter_queries = [
    "ALTER TABLE navigation_menu ADD COLUMN IF NOT EXISTS icon_type ENUM('emoji', 'image') DEFAULT 'emoji' AFTER page_url",
    "ALTER TABLE navigation_menu ADD COLUMN IF NOT EXISTS icon_image VARCHAR(500) AFTER icon_type",
    "ALTER TABLE navigation_menu ADD COLUMN IF NOT EXISTS icon_image_active VARCHAR(500) AFTER icon_image"
];

foreach ($alter_queries as $alter_sql) {
    @mysqli_query($connect, $alter_sql);
}

echo "✅ 테이블 컬럼 업데이트 완료\n";

// 기존 메뉴 확인
$check_sql = "SELECT COUNT(*) as cnt FROM navigation_menu";
$result = mysqli_query($connect, $check_sql);
$row = mysqli_fetch_assoc($result);

if ($row['cnt'] == 0) {
    // 기본 메뉴 삽입
    $default_menus = [
        ['icon' => '🏠', 'label' => '홈', 'page_url' => 'index.php', 'display_order' => 1],
        ['icon' => '🏥', 'label' => '병원', 'page_url' => 'hospitals.php', 'display_order' => 2],
        ['icon' => '🎁', 'label' => '이벤트', 'page_url' => 'events.php', 'display_order' => 3],
        ['icon' => '💬', 'label' => '커뮤니티', 'page_url' => 'tips.php', 'display_order' => 4],
        ['icon' => '📰', 'label' => '칼럼', 'page_url' => 'columns.php', 'display_order' => 5]
    ];

    foreach ($default_menus as $menu) {
        $sql = "INSERT INTO navigation_menu (icon, label, page_url, display_order)
                VALUES (
                    '" . mysqli_real_escape_string($connect, $menu['icon']) . "',
                    '" . mysqli_real_escape_string($connect, $menu['label']) . "',
                    '" . mysqli_real_escape_string($connect, $menu['page_url']) . "',
                    " . $menu['display_order'] . "
                )";

        if (mysqli_query($connect, $sql)) {
            echo "✅ 메뉴 추가: {$menu['icon']} {$menu['label']}\n";
        } else {
            echo "❌ 메뉴 추가 실패: " . mysqli_error($connect) . "\n";
        }
    }
} else {
    echo "ℹ️  기존 메뉴가 이미 존재합니다 ({$row['cnt']}개)\n";
}

mysqli_close($connect);

echo "\n✨ 네비게이션 메뉴 테이블 설정이 완료되었습니다!\n";
