<?php
// [인수인계 정비] 개발용 페이지 - 관리자 권한 없으면 접근 차단
@session_start();
if (empty($_SESSION['ad_admin_logged_in'])) {
    http_response_code(403);
    die('접근 권한이 없습니다.');
}
?>
<?php
/**
 * 서울미녀 데이터베이스 확인 페이지
 */

// 데이터베이스 연결
require_once __DIR__ . '/../dbconfig.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>DB 확인</title>";
echo "<style>body{font-family:sans-serif;padding:40px;background:#f5f5f5;}";
echo "h1{color:#AE1932;} pre{background:#fff;padding:20px;border-radius:8px;overflow:auto;}</style></head><body>";

echo "<h1>🔍 서울미녀 데이터베이스 확인</h1>";

// 1. 테이블 존재 확인
echo "<h2>1. 테이블 존재 확인</h2>";
$table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_hospitals'");
$table_exists = mysqli_num_rows($table_check) > 0;

if ($table_exists) {
    echo "<p style='color:green;'>✅ gangnamunni_hospitals 테이블이 존재합니다.</p>";
} else {
    echo "<p style='color:red;'>❌ gangnamunni_hospitals 테이블이 없습니다.</p>";
    echo "<p>SQL 파일을 다시 import 해주세요.</p>";
    echo "</body></html>";
    exit;
}

// 2. 데이터 개수 확인
echo "<h2>2. 데이터 개수 확인</h2>";
$count_result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_hospitals");
$count_row = mysqli_fetch_assoc($count_result);
$total_count = $count_row['cnt'];

echo "<p>총 <strong style='color:#AE1932;font-size:24px;'>{$total_count}</strong>개의 병원 데이터가 있습니다.</p>";

if ($total_count == 0) {
    echo "<p style='color:red;'>⚠️ 데이터가 비어 있습니다. SQL import를 다시 확인해주세요.</p>";
}

// 3. 샘플 데이터 5개 조회
echo "<h2>3. 샘플 데이터 (상위 5개)</h2>";
$sample_query = "SELECT id, name, rating, review_count, address FROM gangnamunni_hospitals ORDER BY rating DESC LIMIT 5";
$sample_result = mysqli_query($connect, $sample_query);

if ($sample_result && mysqli_num_rows($sample_result) > 0) {
    echo "<table style='width:100%;background:white;border-collapse:collapse;'>";
    echo "<tr style='background:#AE1932;color:white;'>";
    echo "<th style='padding:12px;text-align:left;'>ID</th>";
    echo "<th style='padding:12px;text-align:left;'>병원명</th>";
    echo "<th style='padding:12px;text-align:left;'>평점</th>";
    echo "<th style='padding:12px;text-align:left;'>리뷰수</th>";
    echo "<th style='padding:12px;text-align:left;'>주소</th>";
    echo "</tr>";

    while ($row = mysqli_fetch_assoc($sample_result)) {
        echo "<tr style='border-bottom:1px solid #f5f5f5;'>";
        echo "<td style='padding:12px;'>" . $row['id'] . "</td>";
        echo "<td style='padding:12px;'><strong>" . htmlspecialchars($row['name']) . "</strong></td>";
        echo "<td style='padding:12px;'>" . number_format($row['rating'], 1) . "</td>";
        echo "<td style='padding:12px;'>" . number_format($row['review_count']) . "</td>";
        echo "<td style='padding:12px;'>" . htmlspecialchars(mb_substr($row['address'], 0, 30)) . "...</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:red;'>데이터 조회 실패</p>";
}

// 4. 테이블 구조 확인
echo "<h2>4. 테이블 구조</h2>";
$structure_result = mysqli_query($connect, "DESCRIBE gangnamunni_hospitals");

echo "<pre>";
while ($col = mysqli_fetch_assoc($structure_result)) {
    echo sprintf("%-30s %-20s %s\n", $col['Field'], $col['Type'], $col['Key']);
}
echo "</pre>";

// 5. 꿀팁 테이블 확인
echo "<h2>5. 꿀팁 테이블 확인</h2>";
$tips_table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_tips'");
$tips_table_exists = mysqli_num_rows($tips_table_check) > 0;

if ($tips_table_exists) {
    echo "<p style='color:green;'>✅ gangnamunni_tips 테이블이 존재합니다.</p>";

    $tips_count_result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_tips");
    $tips_count_row = mysqli_fetch_assoc($tips_count_result);
    $tips_total_count = $tips_count_row['cnt'];

    echo "<p>총 <strong style='color:#AE1932;font-size:24px;'>{$tips_total_count}</strong>개의 꿀팁 데이터가 있습니다.</p>";

    if ($tips_total_count > 0) {
        echo "<h3>꿀팁 샘플 데이터 (상위 3개)</h3>";
        $tips_sample_query = "SELECT id, title, category, views, likes, created_at FROM gangnamunni_tips ORDER BY created_at DESC LIMIT 3";
        $tips_sample_result = mysqli_query($connect, $tips_sample_query);

        if ($tips_sample_result && mysqli_num_rows($tips_sample_result) > 0) {
            echo "<table style='width:100%;background:white;border-collapse:collapse;'>";
            echo "<tr style='background:#AE1932;color:white;'>";
            echo "<th style='padding:12px;text-align:left;'>ID</th>";
            echo "<th style='padding:12px;text-align:left;'>제목</th>";
            echo "<th style='padding:12px;text-align:left;'>카테고리</th>";
            echo "<th style='padding:12px;text-align:left;'>조회수</th>";
            echo "<th style='padding:12px;text-align:left;'>좋아요</th>";
            echo "</tr>";

            while ($row = mysqli_fetch_assoc($tips_sample_result)) {
                echo "<tr style='border-bottom:1px solid #f5f5f5;'>";
                echo "<td style='padding:12px;'>" . $row['id'] . "</td>";
                echo "<td style='padding:12px;'><strong>" . htmlspecialchars($row['title']) . "</strong></td>";
                echo "<td style='padding:12px;'>" . htmlspecialchars($row['category']) . "</td>";
                echo "<td style='padding:12px;'>" . number_format($row['views']) . "</td>";
                echo "<td style='padding:12px;'>" . number_format($row['likes']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
} else {
    echo "<p style='color:orange;'>⚠️ gangnamunni_tips 테이블이 없습니다. (선택사항)</p>";
}

// 6. 진단 결과
echo "<h2>6. 진단 결과</h2>";

if ($total_count > 0) {
    echo "<div style='background:#d4edda;color:#155724;padding:20px;border-radius:8px;border-left:4px solid #28a745;'>";
    echo "<h3>✅ 데이터베이스가 정상입니다!</h3>";
    echo "<p>🏥 병원 데이터: <strong>{$total_count}</strong>개</p>";
    if ($tips_table_exists && isset($tips_total_count)) {
        echo "<p>💡 꿀팁 데이터: <strong>{$tips_total_count}</strong>개</p>";
    }
    echo "<p><a href='index.php' style='display:inline-block;margin-top:10px;padding:12px 24px;background:#AE1932;color:white;text-decoration:none;border-radius:8px;'>서울미녀 페이지로 이동</a></p>";
    echo "</div>";
} else {
    echo "<div style='background:#fff3cd;color:#856404;padding:20px;border-radius:8px;border-left:4px solid #ffc107;'>";
    echo "<h3>⚠️ 데이터가 없습니다</h3>";
    echo "<p>SQL 파일을 import 해주세요:</p>";
    echo "<p><a href='import_data.php' style='display:inline-block;margin-top:10px;padding:12px 24px;background:#AE1932;color:white;text-decoration:none;border-radius:8px;'>자동 Import 실행</a></p>";
    echo "</div>";
}

echo "</body></html>";

mysqli_close($connect);
?>
