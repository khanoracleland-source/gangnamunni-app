<?php
/**
 * ========================================
 * 데이터베이스 연결 테스트 스크립트
 * ========================================
 *
 * 사용법: php test_db_connection.php
 * 또는 브라우저에서: http://domain.com/test_db_connection.php
 */

// dbconfig.php 로드
if (!file_exists(__DIR__ . '/dbconfig.php')) {
    die("❌ 오류: dbconfig.php 파일이 없습니다.\n");
}

require_once __DIR__ . '/dbconfig.php';

echo "========================================\n";
echo "데이터베이스 연결 테스트\n";
echo "========================================\n\n";

// 연결 정보 출력
echo "📍 연결 정보:\n";
echo "   호스트: " . G5_MYSQL_HOST . "\n";
echo "   포트: " . G5_MYSQL_PORT . "\n";
echo "   사용자: " . G5_MYSQL_USER . "\n";
echo "   데이터베이스: " . G5_MYSQL_DB . "\n\n";

// MySQLi 연결 시도
echo "🔄 연결 시도 중...\n";

$mysqli = new mysqli(
    G5_MYSQL_HOST,
    G5_MYSQL_USER,
    G5_MYSQL_PASSWORD,
    G5_MYSQL_DB,
    G5_MYSQL_PORT
);

// 연결 확인
if ($mysqli->connect_error) {
    echo "❌ 연결 실패: " . $mysqli->connect_error . "\n";
    echo "\n";
    echo "해결 방법:\n";
    echo "1. 데이터베이스 서버가 실행 중인지 확인하세요\n";
    echo "2. 호스트 주소와 포트가 올바른지 확인하세요\n";
    echo "3. 사용자명과 비밀번호가 올바른지 확인하세요\n";
    echo "4. 데이터베이스가 존재하는지 확인하세요\n";
    echo "5. 방화벽에서 포트 3306이 열려있는지 확인하세요\n";
    exit(1);
}

echo "✅ 연결 성공!\n\n";

// 문자셋 설정
$mysqli->set_charset("utf8mb4");

// MySQL 버전 확인
$result = $mysqli->query("SELECT VERSION()");
$row = $result->fetch_array();
echo "📌 MySQL 버전: " . $row[0] . "\n";

// 현재 데이터베이스 확인
$result = $mysqli->query("SELECT DATABASE()");
$row = $result->fetch_array();
echo "📌 현재 데이터베이스: " . $row[0] . "\n\n";

// 테이블 목록 확인
$result = $mysqli->query("SHOW TABLES");
$table_count = $result->num_rows;

echo "📊 테이블 목록 (" . $table_count . "개):\n";

if ($table_count > 0) {
    $tables = [];
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }

    // 테이블을 10개씩 나누어 표시
    $chunks = array_chunk($tables, 10);
    foreach ($chunks as $chunk) {
        echo "   - " . implode(", ", $chunk) . "\n";
    }
} else {
    echo "   ⚠️ 테이블이 없습니다. 데이터베이스가 비어있을 수 있습니다.\n";
}

echo "\n";

// g5_ 프리픽스 테이블 개수 확인
$result = $mysqli->query("SHOW TABLES LIKE 'g5_%'");
$g5_table_count = $result->num_rows;
echo "📌 그누보드 테이블 (g5_*): " . $g5_table_count . "개\n";

// 연결 종료
$mysqli->close();

echo "\n";
echo "========================================\n";
echo "테스트 완료! ✅\n";
echo "========================================\n";
echo "\n";
echo "⚠️ 보안 주의: 테스트 완료 후 이 파일(test_db_connection.php)을 삭제하세요!\n";
?>
