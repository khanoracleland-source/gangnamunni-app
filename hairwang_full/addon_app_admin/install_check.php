<?php
/**
 * 설치 확인 및 자동 설치 스크립트
 */

// 그누보드 경로 설정 (중복 정의 방지)
if (!defined('G5_PATH')) {
    $g5_path = realpath(__DIR__ . '/../');
    define('G5_PATH', $g5_path);
}

// common.php 로드
include_once(G5_PATH . '/common.php');

// 관리자 체크 제거 - 테스트용
// if (!$is_admin) {
//     die('관리자만 접근 가능합니다.');
// }

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>설치 확인 - Hair Wang 앱 관리자</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f7fa; padding: 40px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; border-radius: 12px; padding: 30px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { font-size: 28px; color: #333; margin-bottom: 10px; }
        h2 { font-size: 20px; color: #aa20ff; margin-bottom: 20px; border-bottom: 2px solid #aa20ff; padding-bottom: 10px; }
        .status { padding: 15px; border-radius: 8px; margin-bottom: 15px; }
        .status.ok { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .btn { display: inline-block; padding: 12px 24px; background: #aa20ff; color: white; text-decoration: none; border-radius: 8px; border: none; cursor: pointer; font-size: 16px; margin-right: 10px; }
        .btn:hover { background: #8919d9; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        .sql-query { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; font-family: 'Courier New', monospace; font-size: 13px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>📦 Hair Wang 앱 관리자 설치 확인</h1>
            <p style="color: #666; margin-top: 10px;">데이터베이스 테이블 설치 상태를 확인합니다.</p>
        </div>

        <?php
        // 필요한 테이블 목록
        $required_tables = array(
            'addon_app_admin_boards',
            'addon_app_admin_site_config',
            'addon_app_admin_header',
            'addon_app_admin_footer',
            'addon_app_admin_main_banners',
            'addon_app_admin_main_sections',
            'addon_app_admin_media',
            'addon_app_admin_app_sources',
            'addon_app_admin_app_images',
            'addon_app_admin_logs'
        );

        $missing_tables = array();
        $existing_tables = array();

        // 각 테이블 존재 확인
        foreach ($required_tables as $table_name) {
            $sql = "SHOW TABLES LIKE '{$table_name}'";
            $result = sql_query($sql);

            if ($result && sql_num_rows($result) > 0) {
                $existing_tables[] = $table_name;
            } else {
                $missing_tables[] = $table_name;
            }
        }

        // 결과 표시
        if (empty($missing_tables)) {
            echo '<div class="card">';
            echo '<h2>✅ 설치 완료</h2>';
            echo '<div class="status ok">';
            echo '<strong>모든 테이블이 정상적으로 설치되어 있습니다.</strong><br>';
            echo '총 ' . count($existing_tables) . '개의 테이블이 확인되었습니다.';
            echo '</div>';

            echo '<table>';
            echo '<thead><tr><th>테이블명</th><th>상태</th></tr></thead>';
            echo '<tbody>';
            foreach ($existing_tables as $table) {
                echo '<tr><td>' . $table . '</td><td style="color: #28a745;">✅ 존재</td></tr>';
            }
            echo '</tbody></table>';

            echo '<p style="margin-top: 20px;"><a href="./admin/" class="btn">관리자 페이지로 이동</a></p>';
            echo '</div>';
        } else {
            echo '<div class="card">';
            echo '<h2>⚠️ 설치 필요</h2>';
            echo '<div class="status warning">';
            echo '<strong>' . count($missing_tables) . '개의 테이블이 설치되지 않았습니다.</strong><br>';
            echo '아래 버튼을 클릭하여 자동 설치를 진행하세요.';
            echo '</div>';

            echo '<table>';
            echo '<thead><tr><th>테이블명</th><th>상태</th></tr></thead>';
            echo '<tbody>';
            foreach ($required_tables as $table) {
                if (in_array($table, $existing_tables)) {
                    echo '<tr><td>' . $table . '</td><td style="color: #28a745;">✅ 존재</td></tr>';
                } else {
                    echo '<tr><td>' . $table . '</td><td style="color: #dc3545;">❌ 없음</td></tr>';
                }
            }
            echo '</tbody></table>';

            echo '<form method="post" style="margin-top: 20px;">';
            echo '<button type="submit" name="auto_install" class="btn">🚀 자동 설치 시작</button>';
            echo '<a href="./" class="btn btn-secondary">취소</a>';
            echo '</form>';
            echo '</div>';
        }

        // 자동 설치 처리
        if (isset($_POST['auto_install'])) {
            echo '<div class="card">';
            echo '<h2>🔧 설치 진행중...</h2>';

            $schema_file = __DIR__ . '/sql/schema.sql';

            if (!file_exists($schema_file)) {
                echo '<div class="status error">schema.sql 파일을 찾을 수 없습니다: ' . $schema_file . '</div>';
            } else {
                $sql_content = file_get_contents($schema_file);

                // 주석 제거
                $sql_content = preg_replace('/^--.*$/m', '', $sql_content);

                // 여러 줄 주석 제거
                $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);

                // SQL 문을 세미콜론으로 분리 (단, 괄호 안의 세미콜론은 제외)
                $queries = array();
                $current_query = '';
                $in_string = false;
                $string_char = '';

                for ($i = 0; $i < strlen($sql_content); $i++) {
                    $char = $sql_content[$i];

                    // 문자열 처리
                    if (($char == '"' || $char == "'") && ($i == 0 || $sql_content[$i-1] != '\\')) {
                        if (!$in_string) {
                            $in_string = true;
                            $string_char = $char;
                        } else if ($char == $string_char) {
                            $in_string = false;
                        }
                    }

                    // 세미콜론 처리 (문자열 밖에서만)
                    if ($char == ';' && !$in_string) {
                        $query = trim($current_query);
                        if (!empty($query)) {
                            $queries[] = $query;
                        }
                        $current_query = '';
                    } else {
                        $current_query .= $char;
                    }
                }

                // 마지막 쿼리 추가
                $query = trim($current_query);
                if (!empty($query)) {
                    $queries[] = $query;
                }

                $success_count = 0;
                $error_count = 0;
                $errors = array();

                foreach ($queries as $query) {
                    $query = trim($query);

                    // 빈 쿼리 건너뛰기
                    if (empty($query)) {
                        continue;
                    }

                    $result = @sql_query($query);

                    if ($result || mysqli_affected_rows($connect_db) >= 0) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $errors[] = array(
                            'query' => substr($query, 0, 200) . '...',
                            'error' => mysqli_error($connect_db)
                        );
                    }
                }

                if ($error_count == 0) {
                    echo '<div class="status ok">';
                    echo '<strong>✅ 설치가 성공적으로 완료되었습니다!</strong><br>';
                    echo '총 ' . $success_count . '개의 쿼리가 실행되었습니다.';
                    echo '</div>';
                    echo '<p style="margin-top: 20px;"><a href="./admin/" class="btn">관리자 페이지로 이동</a></p>';
                } else {
                    echo '<div class="status error">';
                    echo '<strong>⚠️ 일부 오류가 발생했습니다</strong><br>';
                    echo '성공: ' . $success_count . '개 / 실패: ' . $error_count . '개';
                    echo '</div>';

                    if (!empty($errors)) {
                        echo '<h3 style="margin-top: 20px;">오류 내역:</h3>';
                        foreach ($errors as $error) {
                            echo '<div class="sql-query">';
                            echo '<strong>쿼리:</strong> ' . htmlspecialchars($error['query']) . '<br>';
                            echo '<strong style="color: #dc3545;">오류:</strong> ' . htmlspecialchars($error['error']);
                            echo '</div>';
                        }
                    }
                }
            }

            echo '</div>';
        }
        ?>

        <div class="card">
            <h2>ℹ️ 데이터베이스 정보</h2>
            <table>
                <tr>
                    <th>항목</th>
                    <th>값</th>
                </tr>
                <tr>
                    <td>DB 호스트</td>
                    <td><?php echo G5_MYSQL_HOST; ?></td>
                </tr>
                <tr>
                    <td>DB 이름</td>
                    <td><?php echo G5_MYSQL_DB; ?></td>
                </tr>
                <tr>
                    <td>DB 사용자</td>
                    <td><?php echo G5_MYSQL_USER; ?></td>
                </tr>
                <tr>
                    <td>테이블 프리픽스</td>
                    <td>addon_app_admin_</td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
