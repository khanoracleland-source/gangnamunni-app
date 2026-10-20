<?php
// [인수인계 정비] 개발용 페이지 - 관리자 권한 없으면 접근 차단
@session_start();
require_once __DIR__ . '/includes/admin_session_guard.php'; sb_admin_session_guard(); // 삭제·비활성·역할변경 관리자 세션 차단
if (empty($_SESSION['ad_admin_logged_in'])) {
    http_response_code(403);
    die('접근 권한이 없습니다.');
}
?>
<?php
/**
 * 강남언니 데이터 자동 Import
 */

set_time_limit(300); // 5분 타임아웃

// 데이터베이스 연결
require_once __DIR__ . '/../dbconfig.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>데이터 Import</title>";
echo "<style>body{font-family:sans-serif;padding:40px;background:#f5f5f5;max-width:800px;margin:0 auto;}";
echo "h1{color:#AC1830;} .log{background:#000;color:#0f0;padding:20px;border-radius:8px;font-family:monospace;white-space:pre-wrap;margin:20px 0;}</style></head><body>";

echo "<h1>📥 강남언니 데이터 Import</h1>";

// SQL 파일 경로 (절대 경로)
$sql_files = [
    'hospitals' => 'H:/carebaby_project/site_get/gangnamunni_hospitals_import.sql',
    'tips' => 'H:/carebaby_project/site_get/gangnamunni_tips_import.sql'
];

echo "<h2>📋 파일 확인</h2>";
$files_to_import = [];

foreach ($sql_files as $type => $sql_file) {
    if (file_exists($sql_file)) {
        echo "<p>✅ {$type} 파일 발견: " . basename($sql_file) . " (" . number_format(filesize($sql_file)) . " bytes)</p>";
        $files_to_import[$type] = $sql_file;
    } else {
        echo "<p style='color:orange;'>⚠️ {$type} 파일 없음: " . basename($sql_file) . "</p>";
    }
}

if (empty($files_to_import)) {
    echo "<p style='color:red;'>❌ Import할 SQL 파일이 없습니다.</p>";
    echo "</body></html>";
    exit;
}

// SQL 파일 순서대로 Import
$total_query_count = 0;
$import_results = [];

foreach ($files_to_import as $type => $sql_file) {
    echo "<h2>📥 {$type} 데이터 Import 중...</h2>";
    echo "<div class='log'>";

    // SQL 파일 읽기
    $sql_content = file_get_contents($sql_file);

    // DB 이름을 hairwang으로 변경
    $sql_content = str_replace('USE kivicare_laravel;', 'USE hairwang;', $sql_content);
    $sql_content = str_replace('kivicare_laravel', 'hairwang', $sql_content);

    // mysqli_multi_query 사용
    if (mysqli_multi_query($connect, $sql_content)) {
        echo "SQL 실행 시작...\n";
        $query_count = 0;

        do {
            $query_count++;
            $total_query_count++;
            if ($query_count % 10 == 0) {
                echo "쿼리 {$query_count} 처리 중...\n";
                flush();
            }

            // 결과가 있으면 해제
            if ($result = mysqli_store_result($connect)) {
                mysqli_free_result($result);
            }

            // 에러 확인
            if (mysqli_error($connect)) {
                $error = mysqli_error($connect);
                // Table already exists나 Duplicate 에러는 무시
                if (strpos($error, 'already exists') === false &&
                    strpos($error, 'Duplicate') === false &&
                    !empty($error)) {
                    echo "⚠️ 경고: {$error}\n";
                }
            }

        } while (mysqli_next_result($connect));

        echo "\n✅ {$type} SQL 실행 완료!\n";
        echo "처리된 쿼리: {$query_count}개\n";
        $import_results[$type] = $query_count;
    } else {
        echo "❌ {$type} SQL 실행 실패: " . mysqli_error($connect) . "\n";
        $import_results[$type] = 0;
    }

    echo "</div>";
}

// 결과 확인
echo "<h2>📊 Import 결과 확인</h2>";

$hospitals_count = 0;
$tips_count = 0;

$count_result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_hospitals");
if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $hospitals_count = $count_row['cnt'];
}

$tips_count_result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_tips");
if ($tips_count_result) {
    $tips_count_row = mysqli_fetch_assoc($tips_count_result);
    $tips_count = $tips_count_row['cnt'];
}

if ($hospitals_count > 0 || $tips_count > 0) {
    echo "<div style='background:#d4edda;color:#155724;padding:20px;border-radius:8px;border-left:4px solid #28a745;margin:20px 0;'>";
    echo "<h2>✅ Import 성공!</h2>";
    echo "<p style='font-size:18px;'>";
    if ($hospitals_count > 0) {
        echo "🏥 병원 데이터: <strong style='font-size:24px;color:#AC1830;'>{$hospitals_count}</strong>개<br>";
    }
    if ($tips_count > 0) {
        echo "💡 꿀팁 데이터: <strong style='font-size:24px;color:#AC1830;'>{$tips_count}</strong>개<br>";
    }
    echo "</p>";
    echo "<p>총 <strong>{$total_query_count}</strong>개의 쿼리가 실행되었습니다.</p>";
    echo "<p><a href='index.php' style='display:inline-block;margin-top:10px;padding:15px 30px;background:#AC1830;color:white;text-decoration:none;border-radius:8px;font-size:18px;'>강남언니 페이지 보기 →</a></p>";
    echo "</div>";
} else {
    echo "<p style='color:red;'>데이터 확인 실패: 데이터가 없습니다.</p>";
}

mysqli_close($connect);

echo "</body></html>";
?>
