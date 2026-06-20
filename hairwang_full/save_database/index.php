<?php
/**
 * 데이터베이스 백업 도구
 * 4개의 데이터베이스를 백업하는 인터페이스
 */

// 날짜시간 포맷
date_default_timezone_set('Asia/Seoul');
$now = date('Y-m-d_H-i-s');

// 데이터베이스 설정
$databases = [
    1 => [
        'name' => 'carecraft_2025',
        'description' => 'carebaby 프로젝트 (NFOR 솔루션)',
        'status' => '확인됨'
    ],
    2 => [
        'name' => 'frezka',
        'description' => 'frezka & kivicare 프로젝트 (Laravel)',
        'status' => '확인됨'
    ],
    3 => [
        'name' => 'hairwang', // dbconfig.php에서 읽어옴
        'description' => 'hairwang 프로젝트 (그누보드5)',
        'status' => '확인 필요'
    ],
    4 => [
        'name' => 'kivicare',
        'description' => 'KiviCare 프로젝트',
        'status' => '확인됨'
    ],
    5 => [
        'name' => 'kivicare_laravel',
        'description' => 'KiviCare Laravel 프로젝트',
        'status' => '확인됨'
    ],
    6 => [
        'name' => 'frezka_local',
        'description' => 'Frezka Local 프로젝트',
        'status' => '확인됨'
    ]
];

// hairwang 데이터베이스 이름 확인
if (file_exists('../dbconfig.php')) {
    include_once '../dbconfig.php';
    if (defined('G5_MYSQL_DB')) {
        $databases[3]['name'] = G5_MYSQL_DB;
        $databases[3]['status'] = '확인됨';
    }
}

// 전체 데이터베이스 검색 기능
$show_all_databases = isset($_GET['show_all']) && $_GET['show_all'] === '1';
if ($show_all_databases) {
    // MySQL 연결 정보
    $host = 'localhost';
    $user = 'root';
    $password = '<REMOVED_DB_PASSWORD>';

    // MySQL 연결
    $conn = @new mysqli($host, $user, $password);

    if (!$conn->connect_error) {
        // 모든 데이터베이스 조회
        $result = $conn->query("SHOW DATABASES");

        if ($result) {
            $databases = []; // 기존 목록 초기화
            $id = 1;

            // 시스템 데이터베이스 제외 목록
            $exclude_dbs = ['information_schema', 'mysql', 'performance_schema', 'sys', 'phpmyadmin'];

            while ($row = $result->fetch_assoc()) {
                $db_name = $row['Database'];

                // 시스템 데이터베이스 제외
                if (!in_array(strtolower($db_name), $exclude_dbs)) {
                    $databases[$id] = [
                        'name' => $db_name,
                        'description' => '자동 검색된 데이터베이스',
                        'status' => '확인됨'
                    ];
                    $id++;
                }
            }
        }

        $conn->close();
    }
}

// 백업 메시지
$backup_message = '';
$backup_success = false;
$backup_errors = [];

// 삭제 메시지
$delete_message = '';
if (isset($_GET['deleted'])) {
    switch ($_GET['deleted']) {
        case 'success':
            $delete_message = '백업 파일이 성공적으로 삭제되었습니다.';
            break;
        case 'error':
            $delete_message = '백업 파일 삭제에 실패했습니다.';
            break;
        case 'invalid':
            $delete_message = '유효하지 않은 파일 형식입니다.';
            break;
        case 'notfound':
            $delete_message = '파일을 찾을 수 없습니다.';
            break;
    }
}

// POST 요청 처리 (백업 실행)
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        if (isset($_POST['backup_all'])) {
            $backup_ids = array_keys($databases);
            
            if (empty($backup_ids)) {
                $backup_message = '백업할 데이터베이스가 없습니다.';
            } else {
                $backup_count = 0;
                $backup_progress = [];
                $backup_errors = [];
                
                foreach ($backup_ids as $id) {
                    if (isset($databases[$id])) {
                        $db = $databases[$id];
                        $backup_file = 'dbbackup/' . $db['name'] . '_backup_' . $now . '.sql';
                        $command = '"C:\xampp\mysql\bin\mysqldump.exe" -h localhost -u root -p<REMOVED_DB_PASSWORD> ' . $db['name'] . ' > ' . $backup_file;
                        
                        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                            $output = [];
                            $return_var = 0;
                            exec($command . ' 2>&1', $output, $return_var);
                            sleep(3);
                            if (file_exists($backup_file) && filesize($backup_file) > 100) {
                                $backup_count++;
                                $backup_progress[] = $db['name'] . ': 백업 완료 (' . formatBytes(filesize($backup_file)) . ')';
                            } else {
                                $backup_errors[] = $db['name'] . ': 백업 실패';
                            }
                        } else {
                            $output = [];
                            $return_var = 0;
                            exec($command, $output, $return_var);
                            if ($return_var === 0 && file_exists($backup_file) && filesize($backup_file) > 100) {
                                $backup_count++;
                                $backup_progress[] = $db['name'] . ': 백업 완료 (' . formatBytes(filesize($backup_file)) . ')';
                            } else {
                                $backup_errors[] = $db['name'] . ': 백업 실패';
                            }
                        }
                    }
                }
                
                if ($backup_count > 0) {
                    $backup_message = "✅ 총 {$backup_count}개의 데이터베이스 전체 백업이 완료되었습니다.\n\n";
                    $backup_message .= "📊 백업 상세:\n";
                    foreach ($backup_progress as $progress) {
                        $backup_message .= "• " . $progress . "\n";
                    }
                    if (!empty($backup_errors)) {
                        $backup_message .= "\n⚠️ 실패한 백업:\n";
                        foreach ($backup_errors as $error) {
                            $backup_message .= "• " . $error . "\n";
                        }
                    }
                    $backup_success = true;
                } else {
                    $backup_message = '❌ 백업에 모두 실패했습니다.';
                    if (!empty($backup_errors)) {
                        $backup_message .= "\n\n에러 상세:\n";
                        foreach ($backup_errors as $error) {
                            $backup_message .= "• " . $error . "\n";
                        }
                    }
                    $backup_success = false;
                }
            }
        } elseif (isset($_POST['backup'])) {
            $backup_ids = isset($_POST['databases']) ? $_POST['databases'] : [];
            
            if (empty($backup_ids)) {
                $backup_message = '백업할 데이터베이스를 선택해주세요.';
            } else {
                $backup_count = 0;
                $backup_progress = [];
                $backup_errors = [];
                
                foreach ($backup_ids as $id) {
                    if (isset($databases[$id])) {
                        $db = $databases[$id];
                        $backup_file = 'dbbackup/' . $db['name'] . '_backup_' . $now . '.sql';
                        $command = '"C:\xampp\mysql\bin\mysqldump.exe" -h localhost -u root -p<REMOVED_DB_PASSWORD> ' . $db['name'] . ' > ' . $backup_file;
                        
                        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                            $output = [];
                            $return_var = 0;
                            exec($command . ' 2>&1', $output, $return_var);
                            sleep(3);
                            if (file_exists($backup_file) && filesize($backup_file) > 100) {
                                $backup_count++;
                                $backup_progress[] = $db['name'] . ': 백업 완료 (' . formatBytes(filesize($backup_file)) . ')';
                            } else {
                                $backup_errors[] = $db['name'] . ': 백업 실패';
                            }
                        } else {
                            $output = [];
                            $return_var = 0;
                            exec($command, $output, $return_var);
                            if ($return_var === 0 && file_exists($backup_file) && filesize($backup_file) > 100) {
                                $backup_count++;
                                $backup_progress[] = $db['name'] . ': 백업 완료 (' . formatBytes(filesize($backup_file)) . ')';
                            } else {
                                $backup_errors[] = $db['name'] . ': 백업 실패';
                            }
                        }
                    }
                }
                
                if ($backup_count > 0) {
                    $backup_message = "✅ 총 {$backup_count}개의 데이터베이스 백업이 완료되었습니다.\n\n";
                    $backup_message .= "📊 백업 상세:\n";
                    foreach ($backup_progress as $progress) {
                        $backup_message .= "• " . $progress . "\n";
                    }
                    if (!empty($backup_errors)) {
                        $backup_message .= "\n⚠️ 실패한 백업:\n";
                        foreach ($backup_errors as $error) {
                            $backup_message .= "• " . $error . "\n";
                        }
                    }
                    $backup_success = true;
                } else {
                    $backup_message = '❌ 백업에 모두 실패했습니다.';
                    if (!empty($backup_errors)) {
                        $backup_message .= "\n\n에러 상세:\n";
                        foreach ($backup_errors as $error) {
                            $backup_message .= "• " . $error . "\n";
                        }
                    }
                    $backup_success = false;
                }
            }
        } elseif (isset($_POST['backup_single'])) {
            $db_id = isset($_POST['db_id']) ? intval($_POST['db_id']) : 0;
            
            if (isset($databases[$db_id])) {
                $db = $databases[$db_id];
                $backup_file = 'dbbackup/' . $db['name'] . '_backup_' . $now . '.sql';
                $command = '"C:\xampp\mysql\bin\mysqldump.exe" -h localhost -u root -p<REMOVED_DB_PASSWORD> ' . $db['name'] . ' > ' . $backup_file;
                
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $output = [];
                    $return_var = 0;
                    exec($command . ' 2>&1', $output, $return_var);
                    sleep(3);
                    if (file_exists($backup_file) && filesize($backup_file) > 100) {
                        $backup_message = $db['name'] . ' 백업이 완료되었습니다.';
                        $backup_success = true;
                    } else {
                        $backup_message = $db['name'] . ' 백업에 실패했습니다.';
                        $backup_success = false;
                    }
                } else {
                    exec($command, $output, $return_var);
                    if ($return_var === 0 && file_exists($backup_file)) {
                        $backup_message = $db['name'] . ' 백업이 완료되었습니다.';
                        $backup_success = true;
                    } else {
                        $backup_message = $db['name'] . ' 백업에 실패했습니다.';
                        $backup_success = false;
                    }
                }
            }
        }
        
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $backup_success,
                'message' => $backup_message,
                'errors' => $backup_errors ?? []
            ]);
            exit;
        }
    }
} catch (Exception $e) {
    $backup_message = '❌ 오류 발생: ' . $e->getMessage();
    $backup_success = false;
    error_log('Backup error: ' . $e->getMessage());
    
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $backup_message,
            'errors' => [$e->getMessage()]
        ]);
        exit;
    }
}

// 기존 백업 파일 목록 가져오기
$backup_files = [];
$backup_dir = 'dbbackup/';
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $file_path = $backup_dir . '/' . $file;

            // 파일이 실제로 존재하는지 확인
            if (file_exists($file_path)) {
                $backup_files[] = [
                    'name' => $file,
                    'size' => filesize($file_path),
                    'modified' => filemtime($file_path),
                    'formatted_size' => formatBytes(filesize($file_path)),
                    'formatted_date' => date('Y-m-d H:i:s', filemtime($file_path))
                ];
            }
        }
    }
    // 수정일시간 역순 정렬
    usort($backup_files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}

// 파일 크기 포맷 함수
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// MySQL 연결 테스트 (선택사항)
function testConnection($db_name) {
    try {
        $conn = new mysqli('localhost', 'root', '<REMOVED_DB_PASSWORD>', $db_name);
        if ($conn->connect_error) {
            return false;
        }
        $conn->close();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>데이터베이스 백업 도구</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: '맑은 고딕', 'Malgun Gothic', sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4CAF50;
        }
        
        h2 {
            color: #555;
            margin-bottom: 20px;
            margin-top: 30px;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: <?php echo $backup_message ? 'block' : 'none'; ?>;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .database-list {
            margin-bottom: 30px;
        }
        
        .database-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .database-item:hover {
            background-color: #f0f0f0;
            transform: translateX(5px);
        }
        
        .db-info {
            flex: 1;
        }
        
        .db-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .db-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .db-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .db-status.confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .db-status.pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .db-status.optional {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .db-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #45a049;
            transform: scale(1.05);
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #218838;
            transform: scale(1.05);
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        .checkbox {
            width: 20px;
            height: 20px;
            margin-right: 15px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .connection-status {
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .connection-status.connected {
            background-color: #d4edda;
            color: #155724;
        }
        
        .connection-status.disconnected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .info-box {
            background-color: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .info-box h3 {
            color: #004085;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            margin-left: 20px;
            color: #004085;
        }
        
        .info-box li {
            margin-bottom: 5px;
        }

        /* 백업 파일 목록 스타일 */
        .backup-files {
            background-color: #fff;
            border-radius: 8px;
            overflow: hidden;
        }

        .backup-table {
            width: 100%;
            border-collapse: collapse;
        }

        .backup-table thead {
            background-color: #f8f9fa;
        }

        .backup-table th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        .backup-table td {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
        }

        .backup-table tr:hover {
            background-color: #f8f9fa;
        }

        .backup-table tr:last-child td {
            border-bottom: none;
        }

        .backup-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .backup-meta {
            font-size: 13px;
            color: #666;
        }

        .backup-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
            border-radius: 4px;
        }

        /* 전체 DB 검색 옵션 */
        .db-search-option {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .db-search-option label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-weight: normal;
            margin: 0;
        }

        .db-search-option input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .db-search-option .info-text {
            color: #856404;
            font-size: 14px;
            margin-left: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ 데이터베이스 백업 도구</h1>
        
        <?php if ($backup_message): ?>
        <div class="message <?php echo $backup_success ? 'success' : 'error'; ?>">
            <?php echo $backup_message; ?>
            <?php if (!empty($backup_errors)): ?>
            <ul style="margin-top: 10px; margin-left: 20px;">
                <?php foreach ($backup_errors as $error): ?>
                <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3>ℹ️ 사용 정보</h3>
            <ul>
                <li>호스트: localhost (XAMPP)</li>
                <li>사용자: root</li>
                <li>비밀번호: <REMOVED_DB_PASSWORD></li>
                <li>백업 경로: dbbackup/ 폴더</li>
                <li>파일 형식: 데이터베이스명_backup_YYYY-MM-DD_HH-MM-SS.sql</li>
            </ul>
        </div>

        <!-- 전체 DB 검색 옵션 -->
        <div class="db-search-option">
            <label>
                <input type="checkbox" id="showAllDatabases" <?php echo $show_all_databases ? 'checked' : ''; ?> onchange="toggleAllDatabases(this.checked)">
                <span><strong>🔍 전체 데이터베이스 자동 검색</strong></span>
            </label>
            <span class="info-text">
                <?php if ($show_all_databases): ?>
                    ✅ 이 PC의 모든 데이터베이스 표시 중 (<?php echo count($databases); ?>개)
                <?php else: ?>
                    체크하면 MySQL 서버의 모든 데이터베이스를 자동으로 검색합니다
                <?php endif; ?>
            </span>
        </div>

        <h2>📋 데이터베이스 목록</h2>
        
        <form method="POST" action="">
            <div class="database-list">
                <?php foreach ($databases as $id => $db): ?>
                <div class="database-item">
                    <input type="checkbox" class="checkbox" name="databases[]" value="<?php echo $id; ?>" id="db_<?php echo $id; ?>">
                    <div class="db-info">
                        <div class="db-name">
                            <?php echo $db['name']; ?>
                            <span class="db-status <?php echo $db['status'] === '확인됨' ? 'confirmed' : ($db['status'] === '확인 필요' ? 'pending' : 'optional'); ?>">
                                <?php echo $db['status']; ?>
                            </span>
                        </div>
                        <div class="db-description"><?php echo $db['description']; ?></div>
                    </div>
                    <div class="db-actions">
                        <?php
                        $connected = testConnection($db['name']);
                        ?>
                        <span class="connection-status <?php echo $connected ? 'connected' : 'disconnected'; ?>">
                            <?php echo $connected ? '✅ 연결됨' : '❌ 연결안됨'; ?>
                        </span>
                        <button type="button" class="btn btn-success btn-single-backup <?php echo !$connected ? 'disabled' : ''; ?>" data-db-id="<?php echo $id; ?>" data-db-name="<?php echo $db['name']; ?>" <?php echo !$connected ? 'disabled' : ''; ?>>
                            📥 백업
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-group" style="margin-bottom: 10px;">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="selectAll" onchange="selectAll()" style="width: 18px; height: 18px;">
                    전체 선택
                </label>
            </div>
            
            <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                <button type="submit" name="backup" value="1" class="btn btn-primary" style="flex: 1; padding: 15px; font-size: 16px;">
                    ✅ 선택한 데이터베이스 백업
                </button>
                <button type="submit" name="backup_all" value="1" class="btn btn-success" style="flex: 1; padding: 15px; font-size: 16px;">
                    ⚡ 전체 선택 후 전체 백업
                </button>
            </div>
        </form>
        
        <h2>📁 기존 백업 파일</h2>
        
        <?php if (empty($backup_files)): ?>
        <p style="color: #999; text-align: center; padding: 30px;">
            백업 파일이 없습니다.
        </p>
        <?php else: ?>
        <div class="backup-files">
            <table class="backup-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">파일명</th>
                        <th style="width: 15%;">크기</th>
                        <th style="width: 20%;">생성일</th>
                        <th style="width: 15%; text-align: right;">작업</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backup_files as $backup): ?>
                    <tr>
                        <td>
                            <div class="backup-name"><?php echo $backup['name']; ?></div>
                        </td>
                        <td><?php echo $backup['formatted_size']; ?></td>
                        <td><?php echo $backup['formatted_date']; ?></td>
                        <td>
                            <div class="backup-actions">
                                <a href="dbbackup/<?php echo $backup['name']; ?>" class="btn btn-small btn-primary" download>
                                    ⬇️ 다운로드
                                </a>
                                <button type="button" onclick="if(confirm('정말로 삭제하시겠습니까?')) {
                                    window.location.href = 'delete.php?file=<?php echo urlencode($backup['name']); ?>';
                                }" class="btn btn-small btn-danger">
                                    🗑️ 삭제
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; padding: 15px; background-color: #f9f9f9; border: 1px solid #ddd; border-radius: 5px;">
                <strong>📊 백업 파일 정보</strong>
                <p style="margin-top: 10px; color: #666;">
                    총 <?php echo count($backup_files); ?>개의 백업 파일이 있습니다.<br>
                    저장 경로: <?php echo realpath('dbbackup/'); ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="margin-top: 30px; text-align: center; color: #999; font-size: 12px;">
            © 2026 데이터베이스 백업 도구 | XAMPP 환경
        </div>
    </div>
    
    <script>
        function selectAll() {
            const checkboxes = document.querySelectorAll('.database-list input[type="checkbox"]');
            const allChecked = document.getElementById('selectAll').checked;
            checkboxes.forEach(cb => {
                cb.checked = allChecked;
            });
        }
        
        function showMessage(message, isSuccess) {
            const existingMessage = document.querySelector('.message');
            if (existingMessage) {
                existingMessage.remove();
            }
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message ' + (isSuccess ? 'success' : 'error');
            messageDiv.style.display = 'block';
            messageDiv.textContent = message;
            
            const container = document.querySelector('.container');
            const h1 = container.querySelector('h1');
            h1.after(messageDiv);
            
            setTimeout(() => {
                messageDiv.style.transition = 'opacity 0.5s';
                messageDiv.style.opacity = '0';
                setTimeout(() => {
                    messageDiv.remove();
                }, 500);
            }, 10000);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }
            
            const form = document.querySelector('form[method="POST"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(form);
                    const backupAll = formData.get('backup_all');
                    const backup = formData.get('backup');
                    const backupSingle = formData.get('backup_single');
                    
                    console.log('=== 백업 요청 시작 ===');
                    console.log('Request method:', 'POST');
                    console.log('Form data:', Object.fromEntries(formData));
                    
                    if (backupAll) {
                        console.log('전체 백업 요청');
                        showMessage('전체 백업을 시작합니다...', true);
                    } else if (backup) {
                        console.log('선택된 백업 요청');
                        showMessage('선택된 데이터베이스 백업을 시작합니다...', true);
                    } else if (backupSingle) {
                        console.log('단일 백업 요청');
                        showMessage('단일 데이터베이스 백업을 시작합니다...', true);
                    }
                    
                    console.log('백업 경로: dbbackup/');
                    console.log('현재 시간:', new Date().toLocaleString('ko-KR'));
                    console.log('백업 파일명 형식: 데이터베이스명_backup_' + new Date().toISOString().slice(0,10) + '.sql');
                    
                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(response => {
                        console.log('=== 응답 수신 ===');
                        console.log('Response status:', response.status);
                        console.log('Response ok:', response.ok);
                        return response.json();
                    })
                    .then(data => {
                        console.log('=== 백업 완료 ===');
                        console.log('백업 결과:', data);
                        
                        if (data.success) {
                            console.log('✅ 백업 성공');
                            console.log('메시지:', data.message);
                            if (data.errors && data.errors.length > 0) {
                                console.warn('⚠️ 부분 실패:', data.errors);
                            }
                            showMessage(data.message, true);
                        } else {
                            console.error('❌ 백업 실패');
                            console.error('메시지:', data.message);
                            if (data.errors && data.errors.length > 0) {
                                console.error('에러 상세:', data.errors);
                            }
                            showMessage(data.message, false);
                        }
                        
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    })
                    .catch(error => {
                        console.error('=== 백업 실패 ===');
                        console.error('에러:', error);
                        console.error('에러 스택:', error.stack);
                        showMessage('백업 중 오류가 발생했습니다: ' + error.message, false);
                    });
                });
            }
            
            const singleBackupButtons = document.querySelectorAll('.btn-single-backup');
            singleBackupButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const dbId = this.getAttribute('data-db-id');
                    const dbName = this.getAttribute('data-db-name');
                    
                    console.log('=== 단일 백업 요청 ===');
                    console.log('데이터베이스 ID:', dbId);
                    console.log('데이터베이스 이름:', dbName);
                    console.log('백업 경로: dbbackup/');
                    console.log('현재 시간:', new Date().toLocaleString('ko-KR'));
                    
                    showMessage(dbName + ' 백업을 시작합니다...', true);
                    
                    const formData = new FormData();
                    formData.append('backup_single', '1');
                    formData.append('db_id', dbId);
                    
                    fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(response => {
                        console.log('=== 단일 백업 응답 수신 ===');
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('=== 단일 백업 완료 ===');
                        console.log('백업 결과:', data);
                        
                        if (data.success) {
                            console.log('✅ 백업 성공');
                            console.log('메시지:', data.message);
                            showMessage(data.message, true);
                        } else {
                            console.error('❌ 백업 실패');
                            console.error('메시지:', data.message);
                            showMessage(data.message, false);
                        }
                        
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    })
                    .catch(error => {
                        console.error('=== 단일 백업 실패 ===');
                        console.error('에러:', error);
                        console.error('에러 스택:', error.stack);
                        showMessage('백업 중 오류가 발생했습니다: ' + error.message, false);
                    });
                });
            });
        });

        // ============================================
        // 전체 데이터베이스 검색 토글
        // ============================================
        function toggleAllDatabases(checked) {
            var currentUrl = window.location.href.split('?')[0];

            if (checked) {
                // show_all=1 파라미터 추가
                window.location.href = currentUrl + '?show_all=1';
            } else {
                // show_all 파라미터 제거
                window.location.href = currentUrl;
            }
        }

        console.log('%c========================================', 'color: #4CAF50; font-weight: bold; font-size: 16px;');
        console.log('%c🗄️ 데이터베이스 백업 도구', 'color: #4CAF50; font-weight: bold; font-size: 14px;');
        console.log('%c========================================', 'color: #4CAF50; font-weight: bold; font-size: 16px;');
        console.log('%c전체 DB 검색:', 'color: #2196f3; font-weight: bold;', <?php echo $show_all_databases ? 'true' : 'false'; ?>);
        console.log('%c데이터베이스 개수:', 'color: #2196f3; font-weight: bold;', <?php echo count($databases); ?>);
        console.log('%c========================================', 'color: #4CAF50; font-weight: bold; font-size: 16px;');
    </script>
</body>
</html>
