<?php
/**
 * 데이터베이스 테이블 확인 페이지
 * @package AddonAppAdmin
 * @version 1.0.0
 */

include_once('./_common.php');

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>데이터베이스 테이블 확인</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; }
        h1 { color: #333; border-bottom: 2px solid #aa20ff; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
        th { background: #aa20ff; color: white; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { background: #e3f2fd; padding: 15px; margin: 20px 0; border-left: 4px solid #2196f3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 데이터베이스 테이블 확인</h1>

        <?php
        echo '<div class="info">';
        echo '<strong>DB 연결 정보:</strong><br>';
        echo 'Host: ' . (defined('G5_MYSQL_HOST') ? G5_MYSQL_HOST : '<span class="error">미정의</span>') . '<br>';
        echo 'User: ' . (defined('G5_MYSQL_USER') ? G5_MYSQL_USER : '<span class="error">미정의</span>') . '<br>';
        echo 'Database: ' . (defined('G5_MYSQL_DB') ? G5_MYSQL_DB : '<span class="error">미정의</span>') . '<br>';
        echo 'Table Prefix: ' . (defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_') . '<br>';
        echo '</div>';

        // 필요한 테이블 목록
        $required_tables = array(
            'addon_app_admin_boards' => '게시판 설정',
            'addon_app_admin_site_config' => '사이트 설정',
            'addon_app_admin_header' => '헤더 설정',
            'addon_app_admin_footer' => '푸터 설정',
            'addon_app_admin_main_banners' => '메인 배너',
            'addon_app_admin_main_sections' => '메인 섹션',
            'addon_app_admin_media' => '미디어 파일',
            'addon_app_admin_app_sources' => '앱 소스',
            'addon_app_admin_app_images' => '앱 이미지',
            'addon_app_admin_logs' => '활동 로그'
        );

        echo '<h2>필수 테이블 확인</h2>';
        echo '<table>';
        echo '<thead><tr><th>테이블명</th><th>설명</th><th>상태</th><th>레코드 수</th></tr></thead>';
        echo '<tbody>';

        $all_tables_exist = true;

        foreach ($required_tables as $table_name => $description) {
            // 테이블 존재 확인
            $check_sql = "SHOW TABLES LIKE '{$table_name}'";
            $result = sql_query($check_sql);
            $exists = sql_num_rows($result) > 0;

            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($table_name) . '</strong></td>';
            echo '<td>' . htmlspecialchars($description) . '</td>';

            if ($exists) {
                echo '<td class="success">✓ 존재</td>';

                // 레코드 수 확인
                $count_sql = "SELECT COUNT(*) as cnt FROM {$table_name}";
                $count_result = sql_fetch($count_sql);
                $count = $count_result ? $count_result['cnt'] : 0;

                echo '<td>' . number_format($count) . '개</td>';
            } else {
                echo '<td class="error">✗ 없음</td>';
                echo '<td>-</td>';
                $all_tables_exist = false;
            }

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        // 전체 addon_app_admin_ 테이블 조회
        echo '<h2>전체 addon_app_admin_ 테이블 목록</h2>';
        $all_tables_sql = "SHOW TABLES LIKE 'addon_app_admin_%'";
        $all_tables_result = sql_query($all_tables_sql);

        if (sql_num_rows($all_tables_result) > 0) {
            echo '<table>';
            echo '<thead><tr><th>번호</th><th>테이블명</th><th>레코드 수</th></tr></thead>';
            echo '<tbody>';

            $index = 1;
            while ($row = sql_fetch_array($all_tables_result)) {
                $table_name = $row[0];

                // 레코드 수 확인
                $count_sql = "SELECT COUNT(*) as cnt FROM {$table_name}";
                $count_result = sql_fetch($count_sql);
                $count = $count_result ? $count_result['cnt'] : 0;

                echo '<tr>';
                echo '<td>' . $index++ . '</td>';
                echo '<td><strong>' . htmlspecialchars($table_name) . '</strong></td>';
                echo '<td>' . number_format($count) . '개</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p class="error">addon_app_admin_ 테이블이 하나도 없습니다!</p>';
        }

        // 최종 판정
        echo '<div class="info" style="margin-top: 30px;">';
        if ($all_tables_exist) {
            echo '<h3 class="success">✓ 모든 필수 테이블이 존재합니다!</h3>';
            echo '<p>데이터베이스 스키마가 정상적으로 설치되었습니다.</p>';
        } else {
            echo '<h3 class="error">✗ 일부 테이블이 없습니다!</h3>';
            echo '<p>schema.sql 파일을 실행해야 합니다.</p>';
            echo '<p><strong>설치 경로:</strong> addon_app_admin/sql/schema.sql</p>';
            echo '<p><strong>실행 방법:</strong></p>';
            echo '<ol>';
            echo '<li>phpMyAdmin 또는 MySQL 클라이언트 접속</li>';
            echo '<li>hairwang 데이터베이스 선택</li>';
            echo '<li>SQL 탭에서 schema.sql 파일 내용 실행</li>';
            echo '</ol>';
        }
        echo '</div>';

        // 변수 값 확인
        echo '<h2>설정된 테이블 변수</h2>';
        echo '<table>';
        echo '<thead><tr><th>변수명</th><th>값</th></tr></thead>';
        echo '<tbody>';

        $table_vars = array(
            'addon_app_admin_table_boards',
            'addon_app_admin_table_site_config',
            'addon_app_admin_table_header',
            'addon_app_admin_table_footer',
            'addon_app_admin_table_main_banners',
            'addon_app_admin_table_main_sections',
            'addon_app_admin_table_media',
            'addon_app_admin_table_app_sources',
            'addon_app_admin_table_app_images',
            'addon_app_admin_table_logs'
        );

        foreach ($table_vars as $var_name) {
            echo '<tr>';
            echo '<td><code>' . $var_name . '</code></td>';
            echo '<td>' . (isset($$var_name) ? htmlspecialchars($$var_name) : '<span class="error">미설정</span>') . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        ?>

        <p style="margin-top: 30px; text-align: center;">
            <a href="./" style="display: inline-block; padding: 12px 30px; background: #aa20ff; color: white; text-decoration: none; border-radius: 5px;">← 메인 대시보드로 돌아가기</a>
        </p>
    </div>
</body>
</html>
