<?php
/**
 * admin_backup.php - 백업 관리
 * DB/소스/전체 백업 생성, 이력 조회, 다운로드, 삭제
 */
session_start();
$admin_page_title = '백업 관리';
$admin_current = 'backup';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('backup');

$backup_dir = __DIR__ . '/backups';
if (!is_dir($backup_dir)) mkdir($backup_dir, 0750, true);
if (!file_exists($backup_dir . '/.htaccess')) {
    file_put_contents($backup_dir . '/.htaccess', "Order deny,allow\nDeny from all\n");
}

// ========== GET ë°±ì ë¤ì´ë¡ë ==========
if (isset($_GET['download'])) {
    $fn = basename($_GET['download']);
    $path = $backup_dir . '/' . $fn;
    if (file_exists($path)) {
        set_time_limit(0);
        ini_set('memory_limit', '64M');
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fn . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache');
        $fp = fopen($path, 'rb');
        while (!feof($fp)) {
            echo fread($fp, 8192);
            flush();
        }
        fclose($fp);
        exit;
    }
    header('HTTP/1.1 404 Not Found');
    exit;
}

// ========== ì± ë¹ë ë¤ì´ë¡ë ==========
if (isset($_GET['app_download'])) {
    $path = base64_decode($_GET['app_download']);
    if (file_exists($path) && strpos(realpath($path), realpath(__DIR__)) === 0) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    header('HTTP/1.1 404 Not Found');
    exit;
}

// ========== ìëªí¤ ë¤ì´ë¡ë ==========
if (isset($_GET['signing_download'])) {
    $path = base64_decode($_GET['signing_download']);
    if (file_exists($path) && strpos(realpath($path), realpath(__DIR__)) === 0) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    header('HTTP/1.1 404 Not Found');
    exit;
}

// ========== AJAX 핸들러 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'];

    switch ($action) {
        case 'backup_db':
            set_time_limit(600);
            $free = disk_free_space($backup_dir);
            if ($free < 1073741824) {
                echo json_encode(['success' => false, 'message' => '디스크 공간 부족 (여유: ' . round($free/1073741824, 2) . 'GB)']);
                break;
            }
            $filename = 'db_backup_' . date('Ymd_His') . '.sql.gz';
            $filepath = $backup_dir . '/' . $filename;

            // defaults-extra-file로 비밀번호 안전하게 전달
            $cnf = tempnam('/tmp', 'my');
            file_put_contents($cnf, "[mysqldump]\nuser=" . G5_MYSQL_USER . "\npassword=" . G5_MYSQL_PASSWORD . "\nhost=" . G5_MYSQL_HOST . "\nport=" . G5_MYSQL_PORT . "\n");
            chmod($cnf, 0600);

            $err_file = tempnam('/tmp', 'bkerr_');
            $cmd = sprintf('/usr/bin/mysqldump --defaults-extra-file=%s %s 2>%s | gzip > %s',
                escapeshellarg($cnf),
                escapeshellarg(G5_MYSQL_DB),
                escapeshellarg($err_file),
                escapeshellarg($filepath)
            );
            exec($cmd, $output, $ret);
            @unlink($cnf);
            $err_msg = trim(@file_get_contents($err_file));
            @unlink($err_file);

            if (file_exists($filepath) && filesize($filepath) > 100) {
                echo json_encode(['success' => true, 'message' => 'DB 백업 완료', 'filename' => $filename, 'size' => filesize($filepath)]);
            } else {
                @unlink($filepath);
                echo json_encode(['success' => false, 'message' => 'DB 백업 실패: ' . ($err_msg ?: 'ret=' . $ret)]);
            }
            break;

        case 'backup_source':
            set_time_limit(600);
            $free = disk_free_space($backup_dir);
            if ($free < 1073741824) {
                echo json_encode(['success' => false, 'message' => '디스크 공간 부족']);
                break;
            }
            $filename = 'source_backup_' . date('Ymd_His') . '.tar.gz';
            $filepath = $backup_dir . '/' . $filename;

            $cmd = sprintf('cd %s && tar czf %s --exclude=./backups --exclude=./node_modules --exclude=./.git --exclude=./auto_manual_engine/node_modules --exclude=./uploads/app-book --exclude=./uploads/screenshot-book . 2>&1',
                escapeshellarg(__DIR__),
                escapeshellarg($filepath)
            );
            exec($cmd, $output, $ret);

            if (file_exists($filepath) && filesize($filepath) > 100) {
                echo json_encode(['success' => true, 'message' => '소스 백업 완료', 'filename' => $filename, 'size' => filesize($filepath)]);
            } else {
                @unlink($filepath);
                echo json_encode(['success' => false, 'message' => '소스 백업 실패: ' . implode("\n", array_slice($output, 0, 3))]);
            }
            break;

        case 'backup_full':
            set_time_limit(600);
            $free = disk_free_space($backup_dir);
            if ($free < 2147483648) {
                echo json_encode(['success' => false, 'message' => '디스크 공간 부족 (2GB 이상 필요)']);
                break;
            }
            $ts = date('Ymd_His');
            $filename = 'full_backup_' . $ts . '.tar.gz';
            $filepath = $backup_dir . '/' . $filename;

            // DB dump
            $db_file = '/tmp/db_dump_' . $ts . '.sql';
            $cnf = tempnam('/tmp', 'my');
            file_put_contents($cnf, "[mysqldump]\nuser=" . G5_MYSQL_USER . "\npassword=" . G5_MYSQL_PASSWORD . "\nhost=" . G5_MYSQL_HOST . "\nport=" . G5_MYSQL_PORT . "\n");
            chmod($cnf, 0600);
            exec(sprintf('/usr/bin/mysqldump --defaults-extra-file=%s %s > %s 2>&1',
                escapeshellarg($cnf), escapeshellarg(G5_MYSQL_DB), escapeshellarg($db_file)
            ), $out1, $ret1);
            @unlink($cnf);

            if (!file_exists($db_file) || filesize($db_file) < 100) {
                echo json_encode(['success' => false, 'message' => 'DB 덤프 실패']);
                break;
            }

            // Source + DB tar
            $cmd = sprintf('cd %s && tar czf %s --exclude=./backups --exclude=./node_modules --exclude=./.git --exclude=./auto_manual_engine/node_modules --exclude=./uploads/app-book --exclude=./uploads/screenshot-book . -C /tmp db_dump_%s.sql 2>&1',
                escapeshellarg(__DIR__), escapeshellarg($filepath), $ts
            );
            exec($cmd, $out2, $ret2);
            @unlink($db_file);

            if (file_exists($filepath) && filesize($filepath) > 100) {
                echo json_encode(['success' => true, 'message' => '전체 백업 완료', 'filename' => $filename, 'size' => filesize($filepath)]);
            } else {
                @unlink($filepath);
                echo json_encode(['success' => false, 'message' => '전체 백업 실패']);
            }
            break;

        case 'list':
            $files = array_merge(
                glob($backup_dir . '/*.sql.gz') ?: [],
                glob($backup_dir . '/*.tar.gz') ?: []
            );
            $backups = [];
            $total_size = 0;
            foreach ($files as $f) {
                $name = basename($f);
                if ($name === '.htaccess') continue;
                $type = 'unknown';
                if (strpos($name, 'db_backup_') === 0) $type = 'db';
                elseif (strpos($name, 'source_backup_') === 0) $type = 'source';
                elseif (strpos($name, 'full_backup_') === 0) $type = 'full';
                $sz = filesize($f);
                $total_size += $sz;
                $backups[] = [
                    'filename' => $name, 'type' => $type, 'size' => $sz,
                    'date' => date('Y-m-d H:i:s', filemtime($f)), 'timestamp' => filemtime($f),
                ];
            }
            usort($backups, function($a, $b) { return $b['timestamp'] - $a['timestamp']; });

            // 디스크 정보
            $disk_total = disk_total_space($backup_dir);
            $disk_free = disk_free_space($backup_dir);
            echo json_encode([
                'success' => true,
                'data' => $backups,
                'disk' => [
                    'total' => $disk_total,
                    'free' => $disk_free,
                    'used' => $disk_total - $disk_free,
                    'backup_size' => $total_size,
                    'backup_count' => count($backups),
                ]
            ]);
            break;

        case 'delete':
            $filenames = $_POST['filenames'] ?? [];
            if (is_string($filenames)) $filenames = [$filenames];
            $deleted = 0;
            foreach ($filenames as $fn) {
                $fn = basename($fn);
                $path = $backup_dir . '/' . $fn;
                if (file_exists($path) && is_file($path) && $fn !== '.htaccess') {
                    if (unlink($path)) $deleted++;
                }
            }
            echo json_encode(['success' => $deleted > 0, 'message' => $deleted . '개 파일 삭제 완료']);
            break;

        case 'download':
            $fn = basename($_POST['filename'] ?? '');
            $path = $backup_dir . '/' . $fn;
            if (!file_exists($path)) {
                echo json_encode(['success' => false, 'message' => '파일을 찾을 수 없습니다.']);
                break;
            }
            echo json_encode(["success" => true, "url" => "admin_backup.php?download=" . urlencode($fn)]);
            break;

        case 'app_list':
            $build_dir = __DIR__ . '/app_builds';
            $files = [];
            foreach (['android/admin', 'android/patient', 'apk/admin', 'apk/patient'] as $sub) {
                $dir = $build_dir . '/' . $sub;
                if (!is_dir($dir)) continue;
                foreach (scandir($dir) as $fn) {
                    if ($fn === '.' || $fn === '..' || $fn === '.htaccess') continue;
                    $fp = $dir . '/' . $fn;
                    $parts = explode('/', $sub);
                    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
                    $type = ($parts[0] === 'apk' || $ext === 'apk') ? 'apk' : 'aab';
                    $files[] = [
                        'type' => $type,
                        'app' => $parts[1],
                        'filename' => $fn,
                        'subpath' => $sub . '/' . $fn,
                        'size' => filesize($fp),
                        'mtime' => date('Y-m-d H:i:s', filemtime($fp)),
                        'date' => date('Y-m-d', filemtime($fp)),
                        'timestamp' => filemtime($fp),
                    ];
                }
            }
            usort($files, function($a, $b) { return $b['timestamp'] - $a['timestamp']; });
            echo json_encode(['success' => true, 'data' => $files]);
            break;

        case 'app_delete':
            $subpath = $_POST['subpath'] ?? '';
            if (!$subpath || strpos($subpath, '..') !== false) {
                echo json_encode(['success' => false, 'message' => '잘못된 경로']);
                break;
            }
            $fp = __DIR__ . '/app_builds/' . $subpath;
            if (file_exists($fp) && unlink($fp)) {
                echo json_encode(['success' => true, 'message' => '삭제 완료: ' . basename($subpath)]);
            } else {
                echo json_encode(['success' => false, 'message' => '삭제 실패']);
            }
            break;

        case 'app_delete_multi':
            $paths = json_decode($_POST['paths'] ?? '[]', true);
            $deleted = 0;
            foreach ($paths as $sp) {
                if (strpos($sp, '..') !== false) continue;
                $fp = __DIR__ . '/app_builds/' . $sp;
                if (file_exists($fp) && unlink($fp)) $deleted++;
            }
            echo json_encode(['success' => true, 'message' => $deleted . '개 파일 삭제 완료']);
            break;

        case 'app_delete_all':
            $platform = $_POST['platform'] ?? '';
            if (!in_array($platform, ['android', 'apk', 'all'])) {
                echo json_encode(['success' => false, 'message' => '잘못된 플랫폼']);
                break;
            }
            $dirs = [];
            if ($platform === 'all' || $platform === 'android') {
                $dirs[] = __DIR__ . '/app_builds/android/admin';
                $dirs[] = __DIR__ . '/app_builds/android/patient';
            }
            if ($platform === 'all' || $platform === 'apk') {
                $dirs[] = __DIR__ . '/app_builds/apk/admin';
                $dirs[] = __DIR__ . '/app_builds/apk/patient';
            }
            $deleted = 0;
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) continue;
                foreach (scandir($dir) as $fn) {
                    if ($fn === '.' || $fn === '..' || $fn === '.htaccess') continue;
                    if (unlink($dir . '/' . $fn)) $deleted++;
                }
            }
            echo json_encode(['success' => true, 'message' => $deleted . '개 파일 전체 삭제 완료']);
            break;

        case 'signing_list':
            $sk_dir = __DIR__ . '/app_builds/signing_keys';
            $files = [];
            if (is_dir($sk_dir)) {
                foreach (scandir($sk_dir) as $fn) {
                    if ($fn === '.' || $fn === '..' || $fn === '.htaccess') continue;
                    $fp = $sk_dir . '/' . $fn;
                    $files[] = [
                        'filename' => $fn,
                        'size' => filesize($fp),
                        'mtime' => date('Y-m-d H:i:s', filemtime($fp)),
                    ];
                }
            }
            echo json_encode(['success' => true, 'data' => $files]);
            break;

        case 'signing_download':
            $filename = basename($_POST['filename'] ?? '');
            if (!$filename || strpos($filename, '..') !== false) {
                echo json_encode(['success' => false, 'message' => '잘못된 파일명']);
                break;
            }
            $fp = __DIR__ . '/app_builds/signing_keys/' . $filename;
            if (!file_exists($fp)) {
                echo json_encode(['success' => false, 'message' => '파일 없음']);
                break;
            }
            $subpath = $_POST['subpath'] ?? '';
            if (!$subpath || strpos($subpath, '..') !== false) {
                echo json_encode(['success' => false, 'message' => '잘못된 경로']);
                break;
            }
            $fp = __DIR__ . '/app_builds/' . $subpath;
            if (!file_exists($fp)) {
                echo json_encode(['success' => false, 'message' => '파일 없음']);
                break;
            }
            $ts = date('Ymd_His');
            $ab_dir = __DIR__ . '/app_builds';
            if (!is_dir($ab_dir)) {
                echo json_encode(['success' => false, 'message' => 'app_builds 디렉토리가 없습니다']);
                break;
            }
            $out = $backup_dir . '/app_builds_' . $ts . '.tar.gz';
            $cmd = 'cd ' . escapeshellarg(dirname($ab_dir)) . ' && tar czf ' . escapeshellarg($out) . ' app_builds 2>&1';
            $result = shell_exec($cmd);
            if (file_exists($out) && filesize($out) > 0) {
                echo json_encode(['success' => true, 'message' => '앱 빌드 백업 완료: ' . basename($out) . ' (' . round(filesize($out)/1048576, 1) . 'MB)']);
            } else {
                echo json_encode(['success' => false, 'message' => '앱 빌드 백업 실패: ' . ($result ?: 'unknown error')]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => '알 수 없는 액션']);
    }
    exit;
}

include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 + 탭 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-hdd-stack"></i> 백업 관리</h5>
</div>

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-backup" type="button">
            <i class="bi bi-archive"></i> 백업
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-server" type="button">
            <i class="bi bi-diagram-3"></i> 서버 구조
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-app-builds" type="button">
            <i class="bi bi-phone"></i> 앱 빌드
        </button>
    </li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="tab-backup">

<!-- 디스크 정보 -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <small class="text-muted d-block">전체 용량</small>
                <strong id="diskTotal">-</strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <small class="text-muted d-block">사용 중</small>
                <strong id="diskUsed">-</strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <small class="text-muted d-block">여유 공간</small>
                <strong id="diskFree" class="text-success">-</strong>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body py-2">
                <small class="text-muted d-block">백업 폴더</small>
                <strong id="backupSize" class="text-primary">-</strong>
                <small class="text-muted d-block" id="backupCount"></small>
            </div>
        </div>
    </div>
</div>

<!-- 4 액션 버튼 -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <button class="btn btn-primary w-100 py-3" onclick="doBackup('db')" id="btnBackupDb">
            <i class="bi bi-database-down d-block mb-1" style="font-size:24px;"></i>
            DB 백업
            <small class="d-block text-white-50 mt-1">mysqldump + gzip</small>
        </button>
    </div>
    <div class="col-md-3">
        <button class="btn btn-success w-100 py-3" onclick="doBackup('source')" id="btnBackupSource">
            <i class="bi bi-file-earmark-code d-block mb-1" style="font-size:24px;"></i>
            소스 백업
            <small class="d-block opacity-75 mt-1">PHP/JS/CSS tar.gz</small>
        </button>
    </div>
    <div class="col-md-3">
        <button class="btn btn-warning w-100 py-3" onclick="doBackup('full')" id="btnBackupFull">
            <i class="bi bi-archive d-block mb-1" style="font-size:24px;"></i>
            전체 백업
            <small class="d-block opacity-75 mt-1">DB + 소스 통합</small>
        </button>
    </div>
    <div class="col-md-3">
        <button class="btn btn-info w-100 py-3 text-white" onclick="doBackup('app_source')" id="btnBackupApp">
            <i class="bi bi-phone d-block mb-1" style="font-size:24px;"></i>
            앱 빌드 백업
            <small class="d-block opacity-75 mt-1">AAB/APK/서명키</small>
        </button>
    </div>
</div>

<!-- 진행 상태 -->
<div class="alert alert-info d-none" id="backupProgress">
    <div class="d-flex align-items-center">
        <div class="spinner-border spinner-border-sm me-2"></div>
        <span id="backupProgressText">백업 진행 중...</span>
    </div>
</div>

<!-- 백업 이력 -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> 백업 이력</span>
        <button class="btn btn-outline-danger btn-sm d-none" id="btnDeleteSelected" onclick="deleteSelected()">
            <i class="bi bi-trash"></i> 선택 삭제 (<span id="selectedCount">0</span>)
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width:40px;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="checkAll" onchange="toggleAll(this)">
                        </th>
                        <th>파일명</th>
                        <th style="width:80px;" class="text-center">유형</th>
                        <th style="width:100px;" class="text-end">크기</th>
                        <th style="width:160px;">생성일시</th>
                        <th style="width:100px;" class="text-center">작업</th>
                    </tr>
                </thead>
                <tbody id="backupList"></tbody>
            </table>
        </div>
        <div class="text-center py-5 text-muted d-none" id="emptyState">
            <i class="bi bi-archive" style="font-size:48px;"></i>
            <p class="mt-2 mb-0">백업 파일이 없습니다.<br>위 버튼을 클릭하여 백업을 시작하세요.</p>
        </div>
    </div>
</div>

</div><!-- end tab-backup -->

<div class="tab-pane fade" id="tab-server">

<style>
.server-tree { font-family: 'Cascadia Code', 'Fira Code', 'Consolas', monospace; font-size: 13px; line-height: 1.8; }
.server-tree .tree-item { padding: 2px 0; display: flex; align-items: center; gap: 8px; }
.server-tree .tree-line { color: var(--bs-secondary); user-select: none; }
.server-tree .tree-icon { width: 20px; text-align: center; flex-shrink: 0; }
.server-tree .tree-label { font-weight: 500; }
.server-tree .tree-desc { color: var(--bs-secondary); font-size: 12px; margin-left: 4px; }
.info-pill { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 50px; font-size: 13px; font-weight: 500; }
.info-card-gradient { background: linear-gradient(135deg, var(--card-from), var(--card-to)); border: none; color: #fff; }
.backup-file-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 14px; border-radius: 8px; margin-bottom: 4px; }
.backup-file-item:nth-child(odd) { background: var(--bs-tertiary-bg); }
.stat-ring { width: 90px; height: 90px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; border: 4px solid; }
</style>

<?php
// 시스템 정보 미리 수집
$web_root = '/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas';
$total_disk = disk_total_space('/');
$free_disk = disk_free_space('/');
$used_disk = $total_disk - $free_disk;
$disk_pct = round($used_disk / $total_disk * 100, 1);

$mem = @file_get_contents('/proc/meminfo');
$mem_total = $mem_avail = 0;
if ($mem) {
    preg_match('/MemTotal:\s+(\d+)/', $mem, $mt);
    preg_match('/MemAvailable:\s+(\d+)/', $mem, $ma);
    $mem_total = ($mt[1] ?? 0) / 1048576;
    $mem_avail = ($ma[1] ?? 0) / 1048576;
}
$mem_used = $mem_total - $mem_avail;
$mem_pct = $mem_total > 0 ? round($mem_used / $mem_total * 100, 1) : 0;

// DB 정보
$r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema='hms_saas'");
$table_count = mysqli_fetch_assoc($r)['cnt'] ?? 0;
$r2 = mysqli_query($connect, "SELECT SUM(data_length + index_length) as total FROM information_schema.tables WHERE table_schema='hms_saas'");
$db_size = mysqli_fetch_assoc($r2)['total'] ?? 0;
$r3 = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema='hms_saas' AND table_name LIKE 'gangnamunni_%'");
$gn_count = mysqli_fetch_assoc($r3)['cnt'] ?? 0;
$r4 = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema='hms_saas' AND table_name NOT LIKE 'gangnamunni_%' AND table_name NOT LIKE 'g5_%' AND table_name NOT LIKE 'hospital_%'");
$hms_count = mysqli_fetch_assoc($r4)['cnt'] ?? 0;
$r5 = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema='hms_saas' AND table_name LIKE 'g5_%'");
$g5_count = mysqli_fetch_assoc($r5)['cnt'] ?? 0;

$du_result = trim(@shell_exec('du -sh ' . __DIR__ . ' 2>/dev/null') ?: 'N/A');
?>

<!-- 시스템 리소스 요약 (원형 게이지) -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body d-flex flex-column align-items-center py-3">
                <div class="stat-ring mb-2" style="border-color: <?= $disk_pct > 80 ? '#dc3545' : ($disk_pct > 60 ? '#ffc107' : '#198754') ?>;">
                    <span class="fs-5 fw-bold"><?= $disk_pct ?>%</span>
                    <small class="text-muted" style="font-size:10px;">사용</small>
                </div>
                <div class="fw-semibold">디스크</div>
                <small class="text-muted"><?= round($used_disk/1073741824,1) ?> / <?= round($total_disk/1073741824,1) ?> GB</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body d-flex flex-column align-items-center py-3">
                <div class="stat-ring mb-2" style="border-color: <?= $mem_pct > 80 ? '#dc3545' : ($mem_pct > 60 ? '#ffc107' : '#198754') ?>;">
                    <span class="fs-5 fw-bold"><?= $mem_pct ?>%</span>
                    <small class="text-muted" style="font-size:10px;">사용</small>
                </div>
                <div class="fw-semibold">메모리</div>
                <small class="text-muted"><?= round($mem_used,1) ?> / <?= round($mem_total,1) ?> GB</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body d-flex flex-column align-items-center py-3">
                <div class="stat-ring mb-2" style="border-color: #6f42c1;">
                    <span class="fs-5 fw-bold"><?= $table_count ?></span>
                    <small class="text-muted" style="font-size:10px;">tables</small>
                </div>
                <div class="fw-semibold">Database</div>
                <small class="text-muted"><?= round($db_size/1048576,1) ?> MB (hms_saas)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 text-center">
            <div class="card-body py-3">
                <div class="d-flex flex-column gap-1 text-start" style="font-size:13px;">
                    <div><i class="bi bi-hdd-rack text-primary me-2"></i><?= php_uname('s') . ' ' . php_uname('r') ?></div>
                    <div><i class="bi bi-filetype-php text-info me-2"></i>PHP <?= phpversion() ?></div>
                    <div><i class="bi bi-database text-success me-2"></i>MySQL <?= mysqli_get_server_info($connect) ?></div>
                    <div><i class="bi bi-globe text-warning me-2"></i><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Apache' ?></div>
                    <div><i class="bi bi-folder text-secondary me-2"></i><?= $du_result ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 디렉토리 트리 구조 -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-transparent">
                <i class="bi bi-diagram-3 text-primary me-2"></i>
                <strong>서버 디렉토리 구조</strong>
            </div>
            <div class="card-body server-tree">
                <div class="tree-item">
                    <span class="tree-icon text-warning"><i class="bi bi-hdd-fill"></i></span>
                    <span class="tree-label">/var/www/html/</span>
                    <span class="tree-desc">DocumentRoot</span>
                </div>
                <div class="tree-item ps-3">
                    <span class="tree-line">|--</span>
                    <span class="tree-icon text-primary"><i class="bi bi-folder-fill"></i></span>
                    <span class="tree-label">hms-saas/</span>
                    <span class="tree-desc">InfyHMS Laravel 10</span>
                </div>
                <div class="tree-item ps-5">
                    <span class="tree-line">|--</span>
                    <span class="tree-icon text-info"><i class="bi bi-folder-fill"></i></span>
                    <span class="tree-label">hairwang_full/</span>
                    <span class="tree-desc">Apache Alias /hairwang</span>
                </div>
                <div class="tree-item ps-5" style="padding-left:4.5rem!important;">
                    <span class="tree-line">|--</span>
                    <span class="tree-icon" style="color:#e91e8c;"><i class="bi bi-folder-fill"></i></span>
                    <span class="tree-label" style="color:#e91e8c;">gangnamunni/</span>
                    <span class="badge bg-danger bg-opacity-10 text-danger ms-1" style="font-size:11px;">Seoul Beauty</span>
                </div>
                <div class="tree-item" style="padding-left:6.5rem!important;">
                    <span class="tree-line">|--</span>
                    <span class="tree-icon text-secondary"><i class="bi bi-folder"></i></span>
                    <span>includes/</span>
                    <span class="tree-desc">admin_header, admin_auth, ai_proxy ...</span>
                </div>
                <div class="tree-item" style="padding-left:6.5rem!important;">
                    <span class="tree-line">|--</span>
                    <span class="tree-icon text-secondary"><i class="bi bi-folder"></i></span>
                    <span>api/</span>
                    <span class="tree-desc">REST API endpoints</span>
                </div>
                <div class="tree-item" style="padding-left:6.5rem!important;">
                    <span class="tree-line">|--</span>
                    <span class="tree-icon text-secondary"><i class="bi bi-folder"></i></span>
                    <span>ai/</span>
                    <span class="tree-desc">AI config, proxy</span>
                </div>
                <div class="tree-item" style="padding-left:6.5rem!important;">
                    <span class="tree-line">|--</span>
                    <span class="tree-icon text-secondary"><i class="bi bi-folder"></i></span>
                    <span>assets/</span>
                    <span class="tree-desc">images, css, js</span>
                </div>
                <div class="tree-item" style="padding-left:6.5rem!important;">
                    <span class="tree-line">|--</span>
                    <span class="tree-icon text-secondary"><i class="bi bi-folder"></i></span>
                    <span>manual/</span>
                    <span class="tree-desc">자동 매뉴얼 15종</span>
                </div>
                <div class="tree-item" style="padding-left:6.5rem!important;">
                    <span class="tree-line">|--</span>
                    <span class="tree-icon text-secondary"><i class="bi bi-folder"></i></span>
                    <span>sync/</span>
                    <span class="tree-desc">cron_sync.sh (5분)</span>
                </div>
                <div class="tree-item" style="padding-left:6.5rem!important;">
                    <span class="tree-line">`--</span>
                    <span class="tree-icon text-warning"><i class="bi bi-folder-fill"></i></span>
                    <span class="fw-semibold">backups/</span>
                    <span class="tree-desc">.htaccess deny, 이 페이지에서 관리</span>
                </div>

                <hr class="my-2 opacity-25">
                <div class="tree-item">
                    <span class="tree-icon text-danger"><i class="bi bi-hdd-fill"></i></span>
                    <span class="tree-label">/root/</span>
                    <span class="tree-desc">서버 전체 백업</span>
                </div>
                <div class="tree-item ps-3">
                    <span class="tree-line">|--</span>
                    <span class="tree-icon text-secondary"><i class="bi bi-file-earmark-zip"></i></span>
                    <span>backup_gangnamunni_*.tar.gz</span>
                    <span class="tree-desc">소스 백업</span>
                </div>
                <div class="tree-item ps-3">
                    <span class="tree-line">`--</span>
                    <span class="tree-icon text-secondary"><i class="bi bi-file-earmark-code"></i></span>
                    <span>backup_hms_saas_*.sql</span>
                    <span class="tree-desc">DB 백업</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <!-- 접속 정보 -->
        <div class="card mb-3">
            <div class="card-header bg-transparent">
                <i class="bi bi-link-45deg text-success me-2"></i>
                <strong>접속 정보</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td class="ps-3 text-muted" style="width:100px;">도메인</td><td><a href="https://hms.seoulbeauty.app/hairwang/gangnamunni/" target="_blank" class="text-decoration-none">hms.seoulbeauty.app <i class="bi bi-box-arrow-up-right" style="font-size:11px;"></i></a></td></tr>
                        <tr><td class="ps-3 text-muted">서버 IP</td><td><code>5.104.82.35</code></td></tr>
                        <tr><td class="ps-3 text-muted">DB</td><td><code>hms_saas</code> (MariaDB)</td></tr>
                        <tr><td class="ps-3 text-muted">CDN</td><td><span class="badge bg-warning text-dark">Cloudflare</span> Proxy</td></tr>
                        <tr><td class="ps-3 text-muted">SSL</td><td><span class="badge bg-success">Active</span> Let's Encrypt</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 최근 서버 백업 -->
        <div class="card">
            <div class="card-header bg-transparent">
                <i class="bi bi-clock-history text-info me-2"></i>
                <strong>최근 서버 백업</strong>
            </div>
            <div class="card-body py-2">
<?php
$root_backups = glob('/root/backup_*');
if ($root_backups) {
    usort($root_backups, function($a, $b) { return filemtime($b) - filemtime($a); });
    foreach (array_slice($root_backups, 0, 6) as $bf) {
        $bn = basename($bf);
        $size = @filesize($bf);
        $mtime = date('m/d H:i', @filemtime($bf));
        $icon = strpos($bn, '.sql') !== false ? 'bi-database' : 'bi-file-zip';
        $color = strpos($bn, '.sql') !== false ? 'text-info' : 'text-warning';
        $size_str = $size > 1073741824 ? round($size/1073741824,1).'GB' : round($size/1048576).'MB';
        echo "<div class=\"backup-file-item\">";
        echo "<div><i class=\"bi {$icon} {$color} me-2\"></i><small>{$bn}</small></div>";
        echo "<div><small class=\"text-muted\">{$size_str} &middot; {$mtime}</small></div>";
        echo "</div>\n";
    }
} else {
    echo "<p class=\"text-muted text-center py-2 mb-0\">서버 백업 파일 없음</p>";
}
?>
            </div>
        </div>
    </div>
</div>

<!-- DB 테이블 구성 -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <div>
                    <i class="bi bi-database text-primary me-2"></i>
                    <strong>데이터베이스 구성</strong>
                    <span class="text-muted ms-2" style="font-size:13px;">hms_saas &middot; <?= $table_count ?> tables &middot; <?= round($db_size/1048576,1) ?> MB</span>
                </div>
                <div class="d-flex gap-2">
                    <span class="info-pill" style="background:rgba(233,30,140,0.1); color:#e91e8c;">
                        <i class="bi bi-circle-fill" style="font-size:8px;"></i> 서울미녀 <?= $gn_count ?>
                    </span>
                    <span class="info-pill" style="background:rgba(13,110,253,0.1); color:#0d6efd;">
                        <i class="bi bi-circle-fill" style="font-size:8px;"></i> HMS <?= $hms_count ?>
                    </span>
                    <span class="info-pill" style="background:rgba(108,117,125,0.1); color:#6c757d;">
                        <i class="bi bi-circle-fill" style="font-size:8px;"></i> Gnuboard <?= $g5_count ?>
                    </span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height:400px; overflow-y:auto;">
                    <table class="table table-sm table-hover mb-0 align-middle">
                        <thead class="sticky-top" style="z-index:1;">
                            <tr>
                                <th class="ps-3" style="width:50px;">#</th>
                                <th>테이블명</th>
                                <th class="text-end" style="width:100px;">행 수</th>
                                <th class="text-end pe-3" style="width:100px;">크기</th>
                            </tr>
                        </thead>
                        <tbody>
<?php
$r6 = mysqli_query($connect, "SELECT table_name, table_rows, ROUND((data_length + index_length)/1024) as size_kb FROM information_schema.tables WHERE table_schema='hms_saas' AND table_name LIKE 'gangnamunni_%' ORDER BY (data_length + index_length) DESC");
$idx = 1;
while ($t = mysqli_fetch_assoc($r6)) {
    $name = $t['table_name'];
    $short = str_replace('gangnamunni_', '', $name);
    $rows = number_format($t['table_rows']);
    $size = $t['size_kb'];
    $size_str = $size >= 1024 ? round($size/1024,1).' MB' : $size.' KB';
    $bar_w = min(100, max(5, $size / 50));
    echo "<tr>";
    echo "<td class=\"ps-3 text-muted\">{$idx}</td>";
    echo "<td><code class=\"text-muted\">gangnamunni_</code><strong>{$short}</strong></td>";
    echo "<td class=\"text-end\">{$rows}</td>";
    echo "<td class=\"text-end pe-3\">";
    echo "<div class=\"d-flex align-items-center justify-content-end gap-2\">";
    echo "<div style=\"width:60px;height:4px;background:var(--bs-tertiary-bg);border-radius:2px;overflow:hidden;\">";
    echo "<div style=\"width:{$bar_w}%;height:100%;background:linear-gradient(90deg,#667eea,#e91e8c);border-radius:2px;\"></div>";
    echo "</div>";
    echo "<span>{$size_str}</span>";
    echo "</div>";
    echo "</td></tr>\n";
    $idx++;
}
?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 앱 정보 -->
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-transparent">
                <i class="bi bi-google-play text-success me-2"></i>
                <strong>Google Play Store</strong>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3 p-2 rounded" style="background:var(--bs-tertiary-bg);">
                    <div style="width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-hospital text-white" style="font-size:20px;"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Seoul Beauty Partner</div>
                        <small class="text-muted">com.celebhospital.admin</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 p-2 rounded" style="background:var(--bs-tertiary-bg);">
                    <div style="width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#f093fb,#f5576c);display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-heart-pulse text-white" style="font-size:20px;"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Seoul Beauty</div>
                        <small class="text-muted">com.celebhospital.patient</small>
                    </div>
                </div>
                <div class="mt-3 text-muted" style="font-size:13px;">
                    <i class="bi bi-person-badge me-1"></i> SJ System
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-transparent">
                <i class="bi bi-apple text-dark me-2"></i>
                <strong>Apple App Store</strong>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3 p-2 rounded" style="background:var(--bs-tertiary-bg);">
                    <div style="width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-hospital text-white" style="font-size:20px;"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Seoul Beauty Partner</div>
                        <small class="text-muted">com.celebhospital.admin</small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 p-2 rounded" style="background:var(--bs-tertiary-bg);">
                    <div style="width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,#f093fb,#f5576c);display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-heart-pulse text-white" style="font-size:20px;"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Seoul Beauty</div>
                        <small class="text-muted">com.celebhospital.patient</small>
                    </div>
                </div>
                <div class="mt-3 text-muted" style="font-size:13px;">
                    <i class="bi bi-person-badge me-1"></i> Oracle Land Co.,Ltd.
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- end tab-server -->

<div class="tab-pane fade" id="tab-app-builds">

<style>
.build-card { border-radius: 12px; overflow: hidden; }
.build-card .card-header { border-bottom: 2px solid; }
.build-card.aab .card-header { border-color: #3ddc84; background: linear-gradient(135deg, rgba(61,220,132,0.08), rgba(61,220,132,0.02)); }
.build-card.apk .card-header { border-color: #ff6b35; background: linear-gradient(135deg, rgba(255,107,53,0.08), rgba(255,107,53,0.02)); }
.build-row { display: flex; align-items: center; padding: 10px 16px; border-bottom: 1px solid var(--bs-border-color); transition: background 0.15s; }
.build-row:hover { background: var(--bs-tertiary-bg); }
.build-row:last-child { border-bottom: none; }
.build-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
.build-badge.admin { background: rgba(102,126,234,0.15); color: #667eea; }
.build-badge.patient { background: rgba(240,147,251,0.15); color: #d946a8; }
.build-empty { text-align: center; padding: 40px 20px; color: var(--bs-secondary); }
.build-empty i { font-size: 48px; display: block; margin-bottom: 12px; opacity: 0.3; }
.date-group { padding: 6px 16px; background: var(--bs-tertiary-bg); font-size: 12px; font-weight: 600; color: var(--bs-secondary); border-bottom: 1px solid var(--bs-border-color); }
</style>

<!-- 상단 액션 바 -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h6 class="mb-0"><i class="bi bi-phone"></i> 앱 빌드 관리</h6>
        <small class="text-muted">AAB (Google Play 제출용) / APK (직접 설치용)</small>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-danger btn-sm d-none" id="btnAppDeleteSelected" onclick="appDeleteSelected()">
            <i class="bi bi-trash"></i> 선택 삭제 (<span id="appSelectedCount">0</span>)
        </button>
        <button class="btn btn-outline-danger btn-sm" onclick="if(confirm('전체 빌드 파일을 삭제하시겠습니까?')) appDeleteAll('all')">
            <i class="bi bi-trash3"></i> 전체 삭제
        </button>
    </div>
</div>

<!-- AAB / APK 2열 -->
<div class="row g-3 mb-3">
    <!-- AAB -->
    <div class="col-lg-6">
        <div class="card build-card aab h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="#3ddc84"><path d="M6 18c0 .55.45 1 1 1h1v3.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5V19h2v3.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5V19h1c.55 0 1-.45 1-1V8H6v10zM3.5 8C2.67 8 2 8.67 2 9.5v7c0 .83.67 1.5 1.5 1.5S5 17.33 5 16.5v-7C5 8.67 4.33 8 3.5 8zm17 0c-.83 0-1.5.67-1.5 1.5v7c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5v-7c0-.83-.67-1.5-1.5-1.5zm-4.97-5.84l1.3-1.3c.2-.2.2-.51 0-.71-.2-.2-.51-.2-.71 0l-1.48 1.48C13.85 1.23 12.95 1 12 1c-.96 0-1.86.23-2.66.63L7.85.15c-.2-.2-.51-.2-.71 0-.2.2-.2.51 0 .71l1.31 1.31C6.97 3.26 6 5.01 6 7h12c0-1.99-.97-3.75-2.47-4.84zM10 5H9V4h1v1zm5 0h-1V4h1v1z"/></svg>
                <strong>AAB</strong>
                <small class="text-muted">Google Play 제출용</small>
                <small class="text-muted ms-auto" id="aabCount">-</small>
            </div>
            <div class="card-body p-0" id="aabList">
                <div class="build-empty"><i class="bi bi-hourglass"></i>로딩 중...</div>
            </div>
        </div>
    </div>

    <!-- APK -->
    <div class="col-lg-6">
        <div class="card build-card apk h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-box-seam-fill" style="font-size:20px; color:#ff6b35;"></i>
                <strong>APK</strong>
                <small class="text-muted">직접 설치용</small>
                <small class="text-muted ms-auto" id="apkCount">-</small>
            </div>
            <div class="card-body p-0" id="apkList">
                <div class="build-empty"><i class="bi bi-hourglass"></i>로딩 중...</div>
            </div>
        </div>
    </div>
</div>

<!-- 디스크 사용량 -->
<div class="card">
    <div class="card-body py-2 d-flex justify-content-between align-items-center">
        <small class="text-muted"><i class="bi bi-hdd me-1"></i> 저장 경로: <code>gangnamunni/app_builds/</code></small>
        <small class="text-muted" id="appBuildsTotalSize">-</small>
    </div>
</div>

</div><!-- end tab-app-builds -->
</div><!-- end tab-content -->

<?php
$admin_extra_js = <<<'JSEOF'

function loadBackups() {
    console.log('[DEBUG] loadBackups() called');
    fetch('admin_backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=list'
    })
    .then(function(r) { console.log('[DEBUG] list response status:', r.status); return r.json(); })
    .then(function(data) {
        console.log('[DEBUG] list data:', data);
        if (!data.success) return;

        // 디스크 정보 업데이트
        if (data.disk) {
            document.getElementById('diskTotal').textContent = formatSize(data.disk.total);
            document.getElementById('diskUsed').textContent = formatSize(data.disk.used);
            document.getElementById('diskFree').textContent = formatSize(data.disk.free);
            document.getElementById('backupSize').textContent = formatSize(data.disk.backup_size);
            document.getElementById('backupCount').textContent = data.disk.backup_count + '개 파일';
        }

        var tbody = document.getElementById('backupList');
        var empty = document.getElementById('emptyState');

        if (data.data.length === 0) {
            tbody.innerHTML = '';
            empty.classList.remove('d-none');
            return;
        }
        empty.classList.add('d-none');
        document.getElementById('checkAll').checked = false;
        document.getElementById('btnDeleteSelected').classList.add('d-none');

        tbody.innerHTML = data.data.map(function(b) {
            var typeMap = {db: ['DB', 'primary'], source: ['\uc18c\uc2a4', 'success'], full: ['\uc804\uccb4', 'warning']};
            var t = typeMap[b.type] || [b.type, 'secondary'];
            return '<tr>' +
                '<td class="text-center"><input type="checkbox" class="form-check-input backup-check" value="' + esc(b.filename) + '" onchange="updateDeleteBtn()"></td>' +
                '<td><code class="small">' + esc(b.filename) + '</code></td>' +
                '<td class="text-center"><span class="badge bg-' + t[1] + '">' + t[0] + '</span></td>' +
                '<td class="text-end">' + formatSize(b.size) + '</td>' +
                '<td class="text-nowrap small">' + b.date + '</td>' +
                '<td class="text-center">' +
                    '<button class="btn btn-outline-primary btn-sm py-0 px-1" onclick="downloadBackup(\'' + esc(b.filename) + '\')" title="\ub2e4\uc6b4\ub85c\ub4dc"><i class="bi bi-download"></i></button> ' +
                    '<button class="btn btn-outline-danger btn-sm py-0 px-1" onclick="deleteSingle(\'' + esc(b.filename) + '\')" title="\uc0ad\uc81c"><i class="bi bi-trash"></i></button>' +
                '</td></tr>';
        }).join('');
    })
    .catch(function(err) { console.error('[DEBUG] loadBackups error:', err); });
}

function doBackup(type) {
    var actionMap = {db: 'backup_db', source: 'backup_source', full: 'backup_full', app_source: 'app_source'};
    var labelMap = {db: 'DB', source: '\uc18c\uc2a4', full: '\uc804\uccb4', app_source: '\uc571 \ube4c\ub4dc'};
    console.log('[DEBUG] doBackup("' + type + '") called, action=' + actionMap[type]);

    if (!confirm(labelMap[type] + ' \ubc31\uc5c5\uc744 \uc2dc\uc791\ud558\uc2dc\uaca0\uc2b5\ub2c8\uae4c?')) {
        console.log('[DEBUG] doBackup cancelled by user');
        return;
    }

    var progress = document.getElementById('backupProgress');
    var progressText = document.getElementById('backupProgressText');
    progress.classList.remove('d-none');
    progressText.textContent = labelMap[type] + ' \ubc31\uc5c5 \uc9c4\ud589 \uc911... (\uc18c\uc2a4/\uc804\uccb4\ub294 \uc218 \ubd84 \uc18c\uc694)';
    document.querySelectorAll('[id^="btnBackup"]').forEach(function(b) { b.disabled = true; });

    console.log('[DEBUG] Sending backup request...');
    fetch('admin_backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=' + actionMap[type]
    })
    .then(function(r) { console.log('[DEBUG] backup response status:', r.status); return r.json(); })
    .then(function(data) {
        console.log('[DEBUG] backup result:', data);
        progress.classList.add('d-none');
        document.querySelectorAll('[id^="btnBackup"]').forEach(function(b) { b.disabled = false; });
        if (data.success) {
            alert(data.message + '\n\ud30c\uc77c: ' + data.filename + '\n\ud06c\uae30: ' + formatSize(data.size));
            loadBackups();
        } else {
            alert('\uc624\ub958: ' + data.message);
        }
    })
    .catch(function(err) {
        console.error('[DEBUG] backup fetch error:', err);
        progress.classList.add('d-none');
        document.querySelectorAll('[id^="btnBackup"]').forEach(function(b) { b.disabled = false; });
        alert('\ubc31\uc5c5 \uc911 \uc624\ub958\uac00 \ubc1c\uc0dd\ud588\uc2b5\ub2c8\ub2e4.');
    });
}

function downloadBackup(filename) {
    console.log('[DEBUG] downloadBackup("' + filename + '")');
    fetch('admin_backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=download&filename=' + encodeURIComponent(filename)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        console.log('[DEBUG] download:', data);
        if (data.success) {
            window.location.href = data.url;
        } else {
            alert(data.message);
        }
    });
}

function deleteSingle(filename) {
    console.log('[DEBUG] deleteSingle("' + filename + '")');
    if (!confirm('\uc774 \ubc31\uc5c5 \ud30c\uc77c\uc744 \uc0ad\uc81c\ud558\uc2dc\uaca0\uc2b5\ub2c8\uae4c?\n' + filename)) return;
    fetch('admin_backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete&filenames[]=' + encodeURIComponent(filename)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) { console.log('[DEBUG] delete result:', data); alert(data.message); loadBackups(); });
}

function deleteSelected() {
    var checked = document.querySelectorAll('.backup-check:checked');
    console.log('[DEBUG] deleteSelected() count=' + checked.length);
    if (checked.length === 0) return;
    if (!confirm(checked.length + '\uac1c \ud30c\uc77c\uc744 \uc0ad\uc81c\ud558\uc2dc\uaca0\uc2b5\ub2c8\uae4c?')) return;
    var params = Array.from(checked).map(function(c) { return 'filenames[]=' + encodeURIComponent(c.value); }).join('&');
    fetch('admin_backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete&' + params
    })
    .then(function(r) { return r.json(); })
    .then(function(data) { console.log('[DEBUG] bulk delete result:', data); alert(data.message); loadBackups(); });
}

function toggleAll(el) {
    console.log('[DEBUG] toggleAll(' + el.checked + ')');
    document.querySelectorAll('.backup-check').forEach(function(c) { c.checked = el.checked; });
    updateDeleteBtn();
}

function updateDeleteBtn() {
    var count = document.querySelectorAll('.backup-check:checked').length;
    document.getElementById('btnDeleteSelected').classList.toggle('d-none', count === 0);
    document.getElementById('selectedCount').textContent = count;
}

function formatSize(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
    return (bytes / 1073741824).toFixed(1) + ' GB';
}

function esc(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}


// ========== App Builds Tab ==========
function loadAppBuilds() {
    fetch('admin_backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=app_list'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) return;
        var aab = data.data.filter(function(f) { return f.type === 'aab'; });
        var apk = data.data.filter(function(f) { return f.type === 'apk'; });
        renderBuildList('aabList', aab, 'aab');
        renderBuildList('apkList', apk, 'apk');
        document.getElementById('aabCount').textContent = aab.length + ' files';
        document.getElementById('apkCount').textContent = apk.length + ' files';
        var totalSize = data.data.reduce(function(s, f) { return s + f.size; }, 0);
        document.getElementById('appBuildsTotalSize').textContent = '총 ' + formatSize(totalSize);
    });
}

function renderBuildList(containerId, files, buildType) {
    var container = document.getElementById(containerId);
    if (files.length === 0) {
        var icon = buildType === 'aab' ? 'bi-android2' : 'bi-box-seam';
        container.innerHTML = '<div class="build-empty"><i class="bi ' + icon + '"></i>빌드 파일이 없습니다</div>';
        return;
    }
    var html = '';
    var lastDate = '';
    files.forEach(function(f) {
        var fileDate = f.date || f.mtime.substring(0, 10);
        if (fileDate !== lastDate) {
            html += '<div class="date-group"><i class="bi bi-calendar3 me-1"></i>' + fileDate + '</div>';
            lastDate = fileDate;
        }
        var appBadge = f.app === 'admin'
            ? '<span class="build-badge admin">Partner</span>'
            : '<span class="build-badge patient">Patient</span>';
        var ext = f.filename.split('.').pop().toUpperCase();
        html += '<div class="build-row">';
        html += '<div style="flex:0 0 30px;"><input type="checkbox" class="form-check-input app-build-check" data-path="' + esc(f.subpath) + '" onchange="updateAppDeleteBtn()"></div>';
        html += '<div style="flex:1; min-width:0;">';
        html += '<div class="d-flex align-items-center gap-2 mb-1">';
        html += appBadge;
        html += '<span class="badge bg-secondary bg-opacity-10 text-secondary" style="font-size:10px;">' + ext + '</span>';
        html += '</div>';
        html += '<div class="text-truncate" style="font-size:13px;" title="' + esc(f.filename) + '">' + esc(f.filename) + '</div>';
        html += '<small class="text-muted">' + formatSize(f.size) + ' &middot; ' + f.mtime.substring(11) + '</small>';
        html += '</div>';
        html += '<div class="d-flex gap-1">';
        html += '<button class="btn btn-sm btn-outline-primary" onclick="appDownload(\'' + esc(f.subpath) + '\')"><i class="bi bi-download"></i></button>';
        html += '<button class="btn btn-sm btn-outline-danger" onclick="appDelete(\'' + esc(f.subpath) + '\')"><i class="bi bi-trash"></i></button>';
        html += '</div>';
        html += '</div>';
    });
    container.innerHTML = html;
}

function appDownload(subpath) {
    fetch('admin_backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=app_download&subpath=' + encodeURIComponent(subpath)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success && data.url) {
            window.location.href = data.url;
        } else {
            alert(data.message || '다운로드 실패');
        }
    });
}

function appDelete(subpath) {
    if (!confirm('정말 삭제하시겠습니까?\n' + subpath)) return;
    fetch('admin_backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=app_delete&subpath=' + encodeURIComponent(subpath)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        alert(data.message);
        if (data.success) loadAppBuilds();
    });
}

function appDeleteSelected() {
    var checks = document.querySelectorAll('.app-build-check:checked');
    if (checks.length === 0) return;
    if (!confirm(checks.length + '개 파일을 삭제하시겠습니까?')) return;
    var paths = [];
    checks.forEach(function(c) { paths.push(c.dataset.path); });
    fetch('admin_backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=app_delete_multi&paths=' + encodeURIComponent(JSON.stringify(paths))
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        alert(data.message);
        if (data.success) loadAppBuilds();
    });
}

function appDeleteAll(platform) {
    var label = platform === 'all' ? '전체' : (platform === 'android' ? 'Android' : 'iOS');
    if (!confirm(label + ' 빌드 파일을 모두 삭제하시겠습니까?')) return;
    fetch('admin_backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=app_delete_all&platform=' + encodeURIComponent(platform)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        alert(data.message);
        if (data.success) loadAppBuilds();
    });
}

function loadSigningKeys() {
    fetch('admin_backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=signing_list'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) return;
        var container = document.getElementById('signingList');
        document.getElementById('signingCount').textContent = data.data.length + ' files';
        if (data.data.length === 0) {
            container.innerHTML = '<div class="build-empty"><i class="bi bi-key"></i>서명키 파일이 없습니다</div>';
            return;
        }
        var html = '';
        data.data.forEach(function(f) {
            var ext = f.filename.split('.').pop().toLowerCase();
            var icon = 'bi-file-earmark';
            var color = 'text-secondary';
            if (ext === 'jks') { icon = 'bi-shield-lock-fill'; color = 'text-danger'; }
            else if (ext === 'zip') { icon = 'bi-file-earmark-zip-fill'; color = 'text-warning'; }
            else if (ext === 'txt') { icon = 'bi-file-text-fill'; color = 'text-info'; }

            html += '<div class="build-row">';
            html += '<div style="flex:0 0 36px; text-align:center;"><i class="bi ' + icon + ' ' + color + '" style="font-size:20px;"></i></div>';
            html += '<div style="flex:1; min-width:0;">';
            html += '<div style="font-size:13px; font-weight:500;">' + esc(f.filename) + '</div>';
            html += '<small class="text-muted">' + formatSize(f.size) + ' &middot; ' + f.mtime + '</small>';
            html += '</div>';
            html += '<button class="btn btn-sm btn-outline-warning" onclick="signingDownload(\'' + esc(f.filename) + '\')"><i class="bi bi-download me-1"></i>다운로드</button>';
            html += '</div>';
        });
        container.innerHTML = html;
    });
}

function signingDownload(filename) {
    fetch('admin_backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=signing_download&filename=' + encodeURIComponent(filename)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success && data.url) {
            window.location.href = data.url;
        } else {
            alert(data.message || '다운로드 실패');
        }
    });
}

function updateAppDeleteBtn() {
    var count = document.querySelectorAll('.app-build-check:checked').length;
    document.getElementById('btnAppDeleteSelected').classList.toggle('d-none', count === 0);
    document.getElementById('appSelectedCount').textContent = count;
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('[DEBUG] admin_backup.php DOMContentLoaded');
    loadBackups();
    loadAppBuilds();
});
JSEOF;

if ($connect) mysqli_close($connect);
include __DIR__ . '/includes/admin_footer.php';
?>