<?php
/**
 * 강남언니 크롤링 데이터를 데이터베이스에 저장
 */

require_once __DIR__ . '/../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');

echo "=== 강남언니 데이터 가져오기 시작 ===\n\n";

// 크롤링 데이터 경로
$base_path = 'H:/carebaby_project/site_get/output/www.gangnamunni.com';

// 1. 이벤트 데이터 가져오기
echo "📦 이벤트 데이터 처리 중...\n";
$events_path = $base_path . '/events';
$event_count = 0;

if (is_dir($events_path)) {
    $event_files = glob($events_path . '/*/*.html');

    foreach (array_slice($event_files, 0, 50) as $file) {
        $html = file_get_contents($file);

        // 간단한 HTML 파싱 (제목 추출)
        if (preg_match('/<title>(.*?)<\/title>/s', $html, $matches)) {
            $title = strip_tags($matches[1]);
            $title = trim($title);

            // 파일명에서 ID 추출
            $filename = basename(dirname($file));

            // 데이터 삽입
            $insert_sql = "INSERT INTO gangnamunni_events
                          (title, subtitle, description, category, original_price, discount_price, discount_rate, start_date, end_date)
                          VALUES (
                              '" . mysqli_real_escape_string($connect, $title) . "',
                              '특별 할인 이벤트',
                              '강남언니에서 크롤링한 이벤트입니다.',
                              '특별 이벤트',
                              1000000,
                              800000,
                              20,
                              '" . date('Y-m-d') . "',
                              '" . date('Y-m-d', strtotime('+60 days')) . "'
                          )
                          ON DUPLICATE KEY UPDATE title = title";

            if (mysqli_query($connect, $insert_sql)) {
                $event_count++;
                if ($event_count % 10 == 0) {
                    echo "  ✓ {$event_count}개 이벤트 처리 완료\n";
                }
            }
        }
    }
}

echo "✅ 총 {$event_count}개 이벤트 저장 완료\n\n";

// 2. 칼럼/팁 데이터 가져오기
echo "📰 칼럼 데이터 처리 중...\n";
$tips_path = $base_path . '/community/medical-tips';
$tips_count = 0;

if (is_dir($tips_path)) {
    $tip_files = glob($tips_path . '/*/*.html');

    foreach (array_slice($tip_files, 0, 30) as $file) {
        $html = file_get_contents($file);

        if (preg_match('/<title>(.*?)<\/title>/s', $html, $matches)) {
            $title = strip_tags($matches[1]);
            $title = trim($title);

            // 카테고리 랜덤 선택
            $categories = ['피부', '성형', '시술', '관리', '전문의 칼럼'];
            $category = $categories[array_rand($categories)];

            $insert_sql = "INSERT INTO gangnamunni_tips
                          (title, content, category, created_at)
                          VALUES (
                              '" . mysqli_real_escape_string($connect, $title) . "',
                              '강남언니에서 크롤링한 전문가 칼럼입니다. 자세한 내용은 원본 사이트를 참고해주세요.',
                              '{$category}',
                              NOW()
                          )
                          ON DUPLICATE KEY UPDATE title = title";

            if (mysqli_query($connect, $insert_sql)) {
                $tips_count++;
                if ($tips_count % 10 == 0) {
                    echo "  ✓ {$tips_count}개 칼럼 처리 완료\n";
                }
            }
        }
    }
}

echo "✅ 총 {$tips_count}개 칼럼 저장 완료\n\n";

// 3. 통계 출력
echo "=== 데이터 가져오기 완료 ===\n";
echo "📊 저장된 데이터:\n";
echo "  - 이벤트: {$event_count}개\n";
echo "  - 칼럼: {$tips_count}개\n";
echo "\n";

// 데이터 확인
$event_total = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_events"))['cnt'];
$tips_total = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_tips"))['cnt'];
$hospitals_total = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_hospitals"))['cnt'];

echo "📈 데이터베이스 현황:\n";
echo "  - 전체 이벤트: {$event_total}개\n";
echo "  - 전체 칼럼: {$tips_total}개\n";
echo "  - 전체 병원: {$hospitals_total}개\n";

mysqli_close($connect);

echo "\n✨ 모든 작업이 완료되었습니다!\n";
