<?php
/**
 * 앱 관리자 설치 스크립트
 * @package AddonAppAdmin
 * @version 1.0.0
 */

// 그누보드 경로 설정 (중복 정의 방지)
if (!defined('G5_PATH')) {
    $g5_path = realpath(dirname(__FILE__) . '/../');
    define('G5_PATH', $g5_path);
} else {
    // 이미 정의되어 있으면 사용
    $g5_path = G5_PATH;
}

// 그누보드 공통 파일 로드
$common_file = G5_PATH . '/common.php';
if (file_exists($common_file)) {
    include_once($common_file);
} else {
    die('그누보드 common.php 파일을 찾을 수 없습니다.');
}

// 관리자 체크 제거됨 - 누구나 설치 가능
// if (!isset($is_admin) || !$is_admin) {
//     echo '<script>alert("관리자만 접근 가능합니다."); location.href="' . G5_URL . '";</script>';
//     exit;
// }

$install_log = array();
$has_error = false;

// POST 요청 시 설치 실행
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {

    // SQL 파일 읽기
    $sql_file = __DIR__ . '/sql/schema.sql';

    if (!file_exists($sql_file)) {
        $install_log[] = array('type' => 'error', 'message' => 'schema.sql 파일을 찾을 수 없습니다.');
        $has_error = true;
    } else {
        $sql_content = file_get_contents($sql_file);

        if ($sql_content === false) {
            $install_log[] = array('type' => 'error', 'message' => 'schema.sql 파일을 읽을 수 없습니다.');
            $has_error = true;
        } else {
            // SQL 문을 세미콜론으로 분리
            $sql_statements = explode(';', $sql_content);

            $success_count = 0;
            $error_count = 0;

            foreach ($sql_statements as $sql) {
                $sql = trim($sql);

                // 빈 문장이나 주석은 건너뛰기
                if (empty($sql) || substr($sql, 0, 2) === '--') {
                    continue;
                }

                // SQL 실행
                $result = sql_query($sql, false);

                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                    $error_msg = sql_error();

                    // 테이블이 이미 존재하는 경우는 경고로만 표시
                    if (strpos($error_msg, 'already exists') !== false || strpos($error_msg, '이미 존재') !== false) {
                        $install_log[] = array('type' => 'warning', 'message' => '이미 존재하는 테이블: ' . substr($sql, 0, 100) . '...');
                    } else {
                        $install_log[] = array('type' => 'error', 'message' => 'SQL 오류: ' . $error_msg);
                        $has_error = true;
                    }
                }
            }

            $install_log[] = array('type' => 'success', 'message' => "성공: {$success_count}개, 실패: {$error_count}개");

            if (!$has_error) {
                $install_log[] = array('type' => 'success', 'message' => '✓ 모든 테이블이 성공적으로 생성되었습니다!');
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>앱 관리자 설치</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .header {
            background: #aa20ff;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.9;
            font-size: 14px;
        }
        .content {
            padding: 40px;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #2196f3;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 4px;
        }
        .info-box h3 {
            color: #2196f3;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .info-box ul {
            margin-left: 20px;
            color: #555;
            line-height: 1.8;
        }
        .install-btn {
            width: 100%;
            padding: 18px;
            background: #aa20ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(170, 32, 255, 0.3);
        }
        .install-btn:hover {
            background: #8919d9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(170, 32, 255, 0.4);
        }
        .install-btn:active {
            transform: translateY(0);
        }
        .log-container {
            margin-top: 30px;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            background: #fafafa;
        }
        .log-item {
            padding: 12px;
            margin-bottom: 10px;
            border-radius: 6px;
            border-left: 4px solid #ddd;
        }
        .log-item.success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .log-item.error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        .log-item.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .log-item.info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
            color: #0c5460;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            color: #666;
            font-size: 14px;
        }
        .footer a {
            color: #aa20ff;
            text-decoration: none;
            font-weight: bold;
        }
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 Hair Wang 앱 관리자 설치</h1>
            <p>데이터베이스 테이블을 생성합니다</p>
        </div>

        <div class="content">
            <div class="info-box">
                <h3>📋 설치 내용</h3>
                <ul>
                    <li>addon_app_admin_boards - 게시판 설정</li>
                    <li>addon_app_admin_site_config - 사이트 설정</li>
                    <li>addon_app_admin_header - 헤더 설정</li>
                    <li>addon_app_admin_footer - 푸터 설정</li>
                    <li>addon_app_admin_main_banners - 메인 배너</li>
                    <li>addon_app_admin_main_sections - 메인 섹션</li>
                    <li>addon_app_admin_media - 미디어 파일</li>
                    <li>addon_app_admin_app_sources - 앱 소스</li>
                    <li>addon_app_admin_app_images - 앱 이미지</li>
                    <li>addon_app_admin_logs - 활동 로그</li>
                </ul>
            </div>

            <?php if (!empty($install_log)): ?>
            <div class="log-container">
                <h3 style="margin-bottom: 15px; color: #333;">설치 로그</h3>
                <?php foreach ($install_log as $log): ?>
                <div class="log-item <?php echo $log['type']; ?>">
                    <?php echo htmlspecialchars($log['message']); ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!$has_error || empty($install_log)): ?>
            <form method="post" action="">
                <button type="submit" name="install" class="install-btn">
                    <?php echo empty($install_log) ? '🚀 설치 시작' : '🔄 재설치'; ?>
                </button>
            </form>
            <?php endif; ?>

            <?php if (!empty($install_log) && !$has_error): ?>
            <div style="margin-top: 20px; text-align: center;">
                <a href="./admin/" style="display: inline-block; padding: 12px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;">
                    ✓ 관리자 페이지로 이동
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p>Hair Wang App Admin v1.0.0</p>
            <p><a href="./admin/check-db.php">데이터베이스 테이블 확인</a></p>
        </div>
    </div>
</body>
</html>
