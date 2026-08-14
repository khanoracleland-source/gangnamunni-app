<?php
require_once __DIR__ . '/includes/temporarily_disabled_pages.php';
enforce_temporarily_disabled_admin_page(__FILE__);

/**
 * admin_server_info.php - 서버 인프라 대시보드
 * SSH/SFTP 접속정보, 디렉토리 구조, 서비스 현황, 도메인 매핑
 */
session_start();
$admin_page_title = '서버 인프라';
$admin_page_description = '서버 접속정보, 디렉토리 구조, 서비스 현황을 한눈에 확인합니다.';
$admin_current = 'server_info';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('backup');

// ========== 서버 정보 수집 ==========
$server_ip = '5.104.82.35';
$hostname = @gethostname() ?: 'mail.seoulbeauty.app';
$os_info = '';
if (file_exists('/etc/os-release')) {
    $lines = file('/etc/os-release', FILE_IGNORE_NEW_LINES);
    foreach ($lines as $l) {
        if (strpos($l, 'PRETTY_NAME=') === 0) {
            $os_info = trim(str_replace(['PRETTY_NAME=', '"'], '', $l));
            break;
        }
    }
}
if (!$os_info) $os_info = php_uname('s') . ' ' . php_uname('r');

$cpu_cores = (int) @trim(shell_exec('nproc 2>/dev/null')) ?: 8;
$mem_raw = @shell_exec('free -b 2>/dev/null');
$mem_total = $mem_used = $mem_avail = 0;
if ($mem_raw && preg_match('/Mem:\s+(\d+)\s+(\d+)\s+\d+\s+\d+\s+\d+\s+(\d+)/', $mem_raw, $m)) {
    $mem_total = (float)$m[1];
    $mem_used = (float)$m[2];
    $mem_avail = (float)$m[3];
}

$disk_total = @disk_total_space('/') ?: 0;
$disk_free = @disk_free_space('/') ?: 0;
$disk_used = $disk_total - $disk_free;
$disk_pct = $disk_total > 0 ? round($disk_used / $disk_total * 100, 1) : 0;
$mem_pct = $mem_total > 0 ? round($mem_used / $mem_total * 100, 1) : 0;

$uptime_raw = @trim(shell_exec('uptime -p 2>/dev/null')) ?: '';
$load_avg = sys_getloadavg();

$php_ver = phpversion();
$apache_ver = @trim(shell_exec('httpd -v 2>/dev/null | head -1')) ?: 'Apache';
$mysql_ver = mysqli_get_server_info($connect);

// DB 테이블 수
$r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema='hms_saas'");
$table_count = $r ? (int)mysqli_fetch_assoc($r)['cnt'] : 0;
$r = mysqli_query($connect, "SELECT SUM(data_length + index_length) as total FROM information_schema.tables WHERE table_schema='hms_saas'");
$db_size = $r ? (float)(mysqli_fetch_assoc($r)['total'] ?? 0) : 0;

// 서울미녀 PHP 파일 수
$gn_php_count = (int) @trim(shell_exec("find " . __DIR__ . " -name '*.php' -not -path '*/backups/*' 2>/dev/null | wc -l")) ?: 0;
$gn_php_root = (int) @trim(shell_exec("find " . __DIR__ . " -maxdepth 1 -name '*.php' 2>/dev/null | wc -l")) ?: 0;
// hairwang_full (Gnuboard + 서울미녀) PHP
$hw_base = dirname(__DIR__);
$hw_php_count = (int) @trim(shell_exec("find " . escapeshellarg($hw_base) . " -name '*.php' -not -path '*/backups/*' -not -path '*/.auto-claude/*' 2>/dev/null | wc -l")) ?: 0;
// 전체 프로젝트 PHP (vendor/node_modules 제외)
$project_base = dirname($hw_base);
$all_php_count = (int) @trim(shell_exec("find " . escapeshellarg($project_base) . " -name '*.php' -not -path '*/backups/*' -not -path '*/.auto-claude/*' -not -path '*/vendor/*' -not -path '*/node_modules/*' 2>/dev/null | wc -l")) ?: 0;

// index.php 파일 정보
$index_files = [
    ['path' => $project_base . '/public/index.php', 'label' => 'Laravel 진입점', 'url' => 'https://hms.seoulbeauty.app/', 'badge' => 'HMS', 'color' => '#FF2D20'],
    ['path' => $hw_base . '/index.php', 'label' => 'Gnuboard 진입점', 'url' => 'https://hms.seoulbeauty.app/hairwang/', 'badge' => 'Gnuboard', 'color' => '#f59e0b'],
    ['path' => __DIR__ . '/index.php', 'label' => '서울미녀 메인 페이지', 'url' => 'https://hms.seoulbeauty.app/hairwang/gangnamunni/', 'badge' => '서울미녀', 'color' => '#AE1932'],
];
foreach ($index_files as &$ifile) {
    $ifile['exists'] = file_exists($ifile['path']);
    $ifile['size'] = $ifile['exists'] ? filesize($ifile['path']) : 0;
    $ifile['mtime'] = $ifile['exists'] ? date('Y-m-d H:i', filemtime($ifile['path'])) : '-';
    $ifile['realpath'] = $ifile['exists'] ? realpath($ifile['path']) : $ifile['path'];
}
unset($ifile);

// 서비스 상태 체크
function check_service($name) {
    $status = @trim(shell_exec("systemctl is-active $name 2>/dev/null"));
    return $status === 'active';
}

$services = [
    ['name' => 'Apache (httpd)', 'key' => 'httpd', 'icon' => 'bi-globe', 'color' => '#e74c3c'],
    ['name' => 'MariaDB', 'key' => 'mariadb', 'icon' => 'bi-database', 'color' => '#003545'],
    ['name' => 'PHP-FPM 8.2', 'key' => 'php82-php-fpm', 'icon' => 'bi-filetype-php', 'color' => '#777BB4'],
    ['name' => 'PHP-FPM 8.3', 'key' => 'php83-php-fpm', 'icon' => 'bi-filetype-php', 'color' => '#777BB4'],
    ['name' => 'PHP-FPM 8.4', 'key' => 'php84-php-fpm', 'icon' => 'bi-filetype-php', 'color' => '#777BB4'],
    ['name' => 'Docker', 'key' => 'docker', 'icon' => 'bi-box', 'color' => '#2496ED'],
    ['name' => 'Tailscale VPN', 'key' => 'tailscaled', 'icon' => 'bi-shield-lock', 'color' => '#4a5568'],
    ['name' => 'HMS Laravel', 'key' => 'hms-artisan', 'icon' => 'bi-hospital', 'color' => '#FF2D20'],
];

// Docker 컨테이너 수
$docker_count = (int) @trim(shell_exec("docker ps -q 2>/dev/null | wc -l")) ?: 0;

$admin_page_description = "서버 접속정보, 디렉토리 구조, 서비스 현황을 한눈에 확인합니다.";
require_once __DIR__ . '/includes/admin_header.php';

function fmt_bytes($b, $dec = 1) {
    if ($b >= 1073741824) return round($b / 1073741824, $dec) . ' GB';
    if ($b >= 1048576) return round($b / 1048576, $dec) . ' MB';
    if ($b >= 1024) return round($b / 1024, $dec) . ' KB';
    return $b . ' B';
}
?>

<style>
.si-card {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    padding: 20px;
    height: 100%;
    transition: all 0.2s;
}
.si-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}
[data-bs-theme="dark"] .si-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.si-card-title {
    font-size: 13px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--bs-secondary-color);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.si-stat-big {
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
}
.si-stat-label {
    font-size: 12px;
    color: var(--bs-secondary-color);
}
.si-conn-box {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    position: relative;
    overflow: hidden;
}
.si-conn-box::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(99,102,241,0.15) 0%, transparent 70%);
    border-radius: 50%;
}
.si-conn-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    background: rgba(255,255,255,0.06);
    border-radius: 8px;
    margin-bottom: 8px;
    font-family: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace;
    font-size: 13px;
    position: relative;
    transition: background 0.2s;
}
.si-conn-item:hover {
    background: rgba(255,255,255,0.12);
}
.si-conn-label {
    font-size: 11px;
    color: #94a3b8;
    min-width: 80px;
    font-family: -apple-system, 'Noto Sans KR', sans-serif;
}
.si-conn-value {
    color: #a5b4fc;
    word-break: break-all;
}
.si-copy-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(99,102,241,0.3);
    border: none;
    color: #c7d2fe;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    cursor: pointer;
    opacity: 0;
    transition: all 0.2s;
}
.si-conn-item:hover .si-copy-btn {
    opacity: 1;
}
.si-copy-btn:hover {
    background: rgba(99,102,241,0.5);
}
.si-tree {
    font-family: 'JetBrains Mono', 'Fira Code', monospace;
    font-size: 12.5px;
    line-height: 1.8;
    color: var(--bs-body-color);
}
.si-tree .folder {
    color: #f59e0b;
    font-weight: 600;
}
.si-tree .file {
    color: #60a5fa;
}
.si-tree .comment {
    color: #6b7280;
    font-style: italic;
}
.si-tree .highlight {
    background: rgba(99,102,241,0.12);
    padding: 1px 6px;
    border-radius: 4px;
}
.si-svc-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 8px;
    background: var(--bs-tertiary-bg);
    margin-bottom: 6px;
    transition: all 0.2s;
}
.si-svc-item:hover {
    background: var(--bs-secondary-bg);
}
.si-svc-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.si-svc-dot.active {
    background: #22c55e;
    box-shadow: 0 0 6px rgba(34,197,94,0.5);
}
.si-svc-dot.inactive {
    background: #ef4444;
    box-shadow: 0 0 6px rgba(239,68,68,0.3);
}
.si-domain-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    border-radius: 8px;
    background: var(--bs-tertiary-bg);
    margin-bottom: 6px;
}
.si-domain-row .arrow {
    color: var(--bs-secondary-color);
    font-size: 16px;
}
.si-progress-bar {
    height: 8px;
    border-radius: 4px;
    background: var(--bs-tertiary-bg);
    overflow: hidden;
    margin-top: 6px;
}
.si-progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s ease;
}
.si-tab-nav .nav-link {
    font-weight: 600;
    font-size: 13px;
    padding: 10px 16px;
    border-radius: 8px 8px 0 0;
}
@media (max-width: 768px) {
    .si-conn-box { padding: 16px; }
    .si-conn-item { flex-wrap: wrap; padding: 8px 10px; }
    .si-stat-big { font-size: 22px; }
}
</style>

<div class="container-fluid" style="max-width:1400px;">

    <!-- 페이지 헤더 -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1 fw-bold"><i class="bi bi-hdd-rack me-2"></i>서버 인프라 대시보드</h4>
            <p class="text-muted mb-0" style="font-size:13px;">Contabo VPS · <?= htmlspecialchars($os_info) ?> · <?= $uptime_raw ?></p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-success-subtle text-success" style="font-size:12px;">
                <i class="bi bi-circle-fill me-1" style="font-size:6px;vertical-align:middle;"></i> Online
            </span>
            <span class="badge bg-secondary-subtle text-secondary" style="font-size:12px;">
                Load: <?= implode(' / ', array_map(fn($v) => number_format($v, 2), $load_avg)) ?>
            </span>
        </div>
    </div>

    <!-- 탭 네비게이션 -->
    <ul class="nav nav-tabs si-tab-nav mb-3" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-overview" type="button">
                <i class="bi bi-speedometer2 me-1"></i> Overview
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-connection" type="button">
                <i class="bi bi-plug me-1"></i> 접속 정보
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-structure" type="button">
                <i class="bi bi-diagram-3 me-1"></i> 디렉토리 구조
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-services" type="button">
                <i class="bi bi-gear me-1"></i> 서비스
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-domains" type="button">
                <i class="bi bi-globe me-1"></i> 도메인
            </button>
        </li>
    </ul>

    <div class="tab-content">

    <!-- ===== TAB: Overview ===== -->
    <div class="tab-pane fade show active" id="tab-overview">
        <div class="row g-3 mb-4">
            <!-- Server Spec Cards -->
            <div class="col-md-3 col-6">
                <div class="si-card text-center">
                    <div class="si-card-title justify-content-center"><i class="bi bi-cpu"></i> CPU</div>
                    <div class="si-stat-big text-primary"><?= $cpu_cores ?></div>
                    <div class="si-stat-label">vCPU Cores</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="si-card text-center">
                    <div class="si-card-title justify-content-center"><i class="bi bi-memory"></i> RAM</div>
                    <div class="si-stat-big" style="color:#8b5cf6;"><?= fmt_bytes($mem_total, 0) ?></div>
                    <div class="si-stat-label">사용 <?= $mem_pct ?>% (<?= fmt_bytes($mem_used) ?>)</div>
                    <div class="si-progress-bar">
                        <div class="si-progress-fill" style="width:<?= $mem_pct ?>%; background:<?= $mem_pct > 80 ? '#ef4444' : ($mem_pct > 60 ? '#f59e0b' : '#22c55e') ?>;"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="si-card text-center">
                    <div class="si-card-title justify-content-center"><i class="bi bi-device-hdd"></i> DISK</div>
                    <div class="si-stat-big" style="color:#f59e0b;"><?= fmt_bytes($disk_total, 0) ?></div>
                    <div class="si-stat-label">사용 <?= $disk_pct ?>% · 여유 <?= fmt_bytes($disk_free) ?></div>
                    <div class="si-progress-bar">
                        <div class="si-progress-fill" style="width:<?= $disk_pct ?>%; background:<?= $disk_pct > 80 ? '#ef4444' : ($disk_pct > 60 ? '#f59e0b' : '#22c55e') ?>;"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="si-card text-center">
                    <div class="si-card-title justify-content-center"><i class="bi bi-database"></i> DB</div>
                    <div class="si-stat-big" style="color:#06b6d4;"><?= $table_count ?></div>
                    <div class="si-stat-label">tables · <?= fmt_bytes($db_size) ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Info Grid -->
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="si-card">
                    <div class="si-card-title"><i class="bi bi-info-circle"></i> 서버 사양</div>
                    <table class="table table-sm mb-0" style="font-size:13px;">
                        <tr><td class="text-muted" style="width:140px;">호스팅</td><td class="fw-semibold">Contabo VPS</td></tr>
                        <tr><td class="text-muted">IP</td><td class="fw-semibold font-monospace"><?= $server_ip ?></td></tr>
                        <tr><td class="text-muted">호스트명</td><td class="fw-semibold"><?= htmlspecialchars($hostname) ?></td></tr>
                        <tr><td class="text-muted">OS</td><td class="fw-semibold"><?= htmlspecialchars($os_info) ?></td></tr>
                        <tr><td class="text-muted">Apache</td><td class="fw-semibold"><?= htmlspecialchars($apache_ver) ?></td></tr>
                        <tr><td class="text-muted">PHP</td><td class="fw-semibold"><?= $php_ver ?></td></tr>
                        <tr><td class="text-muted">MariaDB</td><td class="fw-semibold"><?= htmlspecialchars($mysql_ver) ?></td></tr>
                        <tr><td class="text-muted">Uptime</td><td class="fw-semibold"><?= htmlspecialchars($uptime_raw) ?></td></tr>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="si-card">
                    <div class="si-card-title"><i class="bi bi-bar-chart"></i> 프로젝트 현황</div>
                    <table class="table table-sm mb-0" style="font-size:13px;">
                        <tr><td class="text-muted" style="width:180px;">전체 프로젝트 PHP</td><td class="fw-semibold"><?= number_format($all_php_count) ?>개</td></tr>
                        <tr><td class="text-muted">&nbsp;&nbsp;└ hairwang_full PHP</td><td class="fw-semibold"><?= number_format($hw_php_count) ?>개 <span class="text-muted" style="font-size:11px;">(Gnuboard+서울미녀)</span></td></tr>
                        <tr><td class="text-muted">&nbsp;&nbsp;&nbsp;&nbsp;└ 서울미녀 PHP</td><td class="fw-semibold"><?= $gn_php_count ?>개 <span class="text-muted" style="font-size:11px;">(루트 <?= $gn_php_root ?> + 하위 <?= $gn_php_count - $gn_php_root ?>)</span></td></tr>
                        <tr><td class="text-muted">DB (hms_saas)</td><td class="fw-semibold"><?= $table_count ?>개 테이블 · <?= fmt_bytes($db_size) ?></td></tr>
                        <tr><td class="text-muted">Docker 컨테이너</td><td class="fw-semibold"><?= $docker_count ?>개 (Mailcow)</td></tr>
                        <tr><td class="text-muted">PHP 버전</td><td class="fw-semibold">7.4, 8.2, 8.3, 8.4 (FPM)</td></tr>
                        <tr><td class="text-muted">도메인</td><td class="fw-semibold">hms.seoulbeauty.app (Cloudflare)</td></tr>
                        <tr><td class="text-muted">메일</td><td class="fw-semibold">mail.seoulbeauty.app (Mailcow)</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- index.php 위치 (눈에 띄게) -->
        <div class="row g-3 mt-2">
            <div class="col-12">
                <div class="si-card" style="border-left:4px solid #AE1932;">
                    <div class="si-card-title"><i class="bi bi-file-earmark-code" style="color:#AE1932;"></i> index.php 파일 위치</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" style="font-size:13px;">
                            <thead>
                                <tr>
                                    <th style="width:80px;">구분</th>
                                    <th>서버 절대 경로</th>
                                    <th style="width:80px;">크기</th>
                                    <th style="width:140px;">수정일</th>
                                    <th>접속 URL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($index_files as $ifile): ?>
                                <tr>
                                    <td><span class="badge" style="background:<?= $ifile['color'] ?>;"><?= $ifile['badge'] ?></span></td>
                                    <td>
                                        <code class="text-primary" style="font-size:12px;"><?= htmlspecialchars($ifile['realpath']) ?></code>
                                        <div style="font-size:11px; color:var(--bs-secondary-color);"><?= $ifile['label'] ?></div>
                                    </td>
                                    <td class="text-end"><?= fmt_bytes($ifile['size']) ?></td>
                                    <td style="font-size:12px;"><?= $ifile['mtime'] ?></td>
                                    <td>
                                        <a href="<?= $ifile['url'] ?>" target="_blank" class="text-decoration-none" style="font-size:12px;">
                                            <?= $ifile['url'] ?> <i class="bi bi-box-arrow-up-right" style="font-size:9px;"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2 p-2 rounded" style="background:var(--bs-tertiary-bg); font-size:12px;">
                        <i class="bi bi-info-circle text-primary me-1"></i>
                        <code>/root</code> 폴더에는 index 파일 없음. 모든 웹 파일은 <code>/var/www/html/</code> 하위에 위치합니다.
                        DocumentRoot는 <code>public/</code> 디렉토리이며, <code>/hairwang</code>은 Apache Alias로 연결됩니다.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== TAB: 접속 정보 ===== -->
    <div class="tab-pane fade" id="tab-connection">
        <div class="row g-4">
            <!-- SSH/SFTP -->
            <div class="col-lg-6">
                <div class="si-conn-box">
                    <h5 class="fw-bold mb-3" style="color:#a5b4fc;"><i class="bi bi-terminal me-2"></i>SSH / SFTP 접속</h5>
                    <div class="si-conn-item">
                        <span class="si-conn-label">Host</span>
                        <span class="si-conn-value"><?= $server_ip ?></span>
                        <button class="si-copy-btn" onclick="copyText('<?= $server_ip ?>')"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <div class="si-conn-item">
                        <span class="si-conn-label">Port</span>
                        <span class="si-conn-value">22</span>
                        <button class="si-copy-btn" onclick="copyText('22')"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <div class="si-conn-item">
                        <span class="si-conn-label">User</span>
                        <span class="si-conn-value">root</span>
                        <button class="si-copy-btn" onclick="copyText('root')"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <div class="si-conn-item">
                        <span class="si-conn-label">Password</span>
                        <span class="si-conn-value" id="ssh-pw">••••••••</span>
                        <button class="si-copy-btn" onclick="togglePw('ssh-pw','<REMOVED_SSH_PASSWORD>')" style="opacity:1;"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="si-conn-item" style="background:rgba(99,102,241,0.12);">
                        <span class="si-conn-label">SSH 명령</span>
                        <span class="si-conn-value" style="font-size:12px;">ssh root@<?= $server_ip ?></span>
                        <button class="si-copy-btn" onclick="copyText('ssh root@<?= $server_ip ?>')"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <div class="mt-3 p-2 rounded" style="background:rgba(245,158,11,0.1); font-size:12px; color:#fbbf24;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        FTP 서비스(vsftpd/proftpd)는 설치되어 있지 않습니다. <strong>SFTP</strong>(SSH 기반 파일 전송)를 사용하세요.
                        FileZilla에서 프로토콜을 "SFTP"로 선택하면 됩니다.
                    </div>
                </div>
            </div>

            <!-- Database -->
            <div class="col-lg-6">
                <div class="si-conn-box">
                    <h5 class="fw-bold mb-3" style="color:#a5b4fc;"><i class="bi bi-database me-2"></i>Database 접속</h5>
                    <div class="si-conn-item">
                        <span class="si-conn-label">Host</span>
                        <span class="si-conn-value">localhost (127.0.0.1)</span>
                    </div>
                    <div class="si-conn-item">
                        <span class="si-conn-label">Port</span>
                        <span class="si-conn-value">3306</span>
                    </div>
                    <div class="si-conn-item">
                        <span class="si-conn-label">User</span>
                        <span class="si-conn-value">root</span>
                    </div>
                    <div class="si-conn-item">
                        <span class="si-conn-label">Password</span>
                        <span class="si-conn-value" id="db-pw">••••••••</span>
                        <button class="si-copy-btn" onclick="togglePw('db-pw','<REMOVED_DB_PASSWORD>')" style="opacity:1;"><i class="bi bi-eye"></i></button>
                    </div>
                    <div class="si-conn-item">
                        <span class="si-conn-label">Database</span>
                        <span class="si-conn-value">hms_saas</span>
                        <button class="si-copy-btn" onclick="copyText('hms_saas')"><i class="bi bi-clipboard"></i></button>
                    </div>
                    <div class="si-conn-item" style="background:rgba(99,102,241,0.12);">
                        <span class="si-conn-label">phpMyAdmin</span>
                        <span class="si-conn-value" style="font-size:12px;">https://<?= $server_ip ?>/phpMyAdmin-5.2.2/</span>
                        <button class="si-copy-btn" onclick="window.open('https://<?= $server_ip ?>/phpMyAdmin-5.2.2/','_blank')"><i class="bi bi-box-arrow-up-right"></i></button>
                    </div>
                    <div class="mt-3 p-2 rounded" style="background:rgba(34,197,94,0.1); font-size:12px; color:#86efac;">
                        <i class="bi bi-info-circle me-1"></i>
                        외부 접속 시 SSH 터널링 필요: <code style="color:#a5b4fc;">ssh -L 3307:localhost:3306 root@<?= $server_ip ?></code>
                    </div>
                </div>
            </div>

            <!-- Mailcow -->
            <div class="col-lg-6">
                <div class="si-conn-box">
                    <h5 class="fw-bold mb-3" style="color:#a5b4fc;"><i class="bi bi-envelope me-2"></i>메일 서버 (Mailcow)</h5>
                    <div class="si-conn-item">
                        <span class="si-conn-label">관리자 URL</span>
                        <span class="si-conn-value" style="font-size:12px;">https://mail.seoulbeauty.app</span>
                        <button class="si-copy-btn" onclick="window.open('https://mail.seoulbeauty.app','_blank')"><i class="bi bi-box-arrow-up-right"></i></button>
                    </div>
                    <div class="si-conn-item">
                        <span class="si-conn-label">Admin</span>
                        <span class="si-conn-value">admin@seoulbeauty.app</span>
                    </div>
                    <div class="si-conn-item">
                        <span class="si-conn-label">SMTP</span>
                        <span class="si-conn-value">mail.seoulbeauty.app:587 (STARTTLS)</span>
                    </div>
                    <div class="si-conn-item">
                        <span class="si-conn-label">IMAP</span>
                        <span class="si-conn-value">mail.seoulbeauty.app:993 (SSL)</span>
                    </div>
                    <div class="si-conn-item">
                        <span class="si-conn-label">Docker</span>
                        <span class="si-conn-value"><?= $docker_count ?>개 컨테이너 실행 중</span>
                    </div>
                </div>
            </div>

            <!-- FileZilla Quick Setup -->
            <div class="col-lg-6">
                <div class="si-conn-box">
                    <h5 class="fw-bold mb-3" style="color:#a5b4fc;"><i class="bi bi-folder2-open me-2"></i>FileZilla SFTP 설정</h5>
                    <div style="background:rgba(255,255,255,0.05); border-radius:8px; padding:16px; font-size:13px; line-height:2;">
                        <div><span style="color:#94a3b8;">1.</span> 프로토콜: <strong style="color:#a5b4fc;">SFTP - SSH File Transfer Protocol</strong></div>
                        <div><span style="color:#94a3b8;">2.</span> 호스트: <strong style="color:#a5b4fc;"><?= $server_ip ?></strong></div>
                        <div><span style="color:#94a3b8;">3.</span> 포트: <strong style="color:#a5b4fc;">22</strong></div>
                        <div><span style="color:#94a3b8;">4.</span> 로그온 유형: <strong style="color:#a5b4fc;">일반</strong></div>
                        <div><span style="color:#94a3b8;">5.</span> 사용자: <strong style="color:#a5b4fc;">root</strong></div>
                        <div><span style="color:#94a3b8;">6.</span> 비밀번호: <strong style="color:#a5b4fc;">(SSH 비밀번호와 동일)</strong></div>
                    </div>
                    <div class="mt-3 p-2 rounded" style="background:rgba(96,165,250,0.1); font-size:12px; color:#93c5fd;">
                        <i class="bi bi-lightbulb me-1"></i>
                        접속 후 리모트 사이트를 <code style="color:#a5b4fc;">/var/www/html/</code>로 이동하면 프로젝트 파일에 접근할 수 있습니다.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== TAB: 디렉토리 구조 ===== -->
    <div class="tab-pane fade" id="tab-structure">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="si-card">
                    <div class="si-card-title"><i class="bi bi-folder"></i> 주요 디렉토리 트리</div>
                    <div class="si-tree" style="overflow-x:auto; padding:8px;">
<pre style="margin:0; background:transparent; border:none; font-size:12.5px; line-height:1.9;">
<span class="folder">/var/www/html/</span>                              <span class="comment">── 웹 루트</span>
├── <span class="folder">multi-hospital-management-system-saas-hospital/</span>
│   └── <span class="folder">hms-saas-v5.8.0/</span>
│       └── <span class="folder">hms-saas/</span>                        <span class="comment">── HMS Laravel 프로젝트</span>
│           ├── <span class="folder highlight">public/</span>                      <span class="comment">── DocumentRoot (hms.seoulbeauty.app)</span>
│           │   └── <span class="file">index.php</span>                <span class="comment">── Laravel 진입점 (1.7KB)</span>
│           ├── <span class="folder">app/</span>                         <span class="comment">── Laravel 앱 소스</span>
│           ├── <span class="folder">routes/</span>                      <span class="comment">── API/Web 라우트</span>
│           ├── <span class="folder">storage/</span>                     <span class="comment">── 로그/캐시</span>
│           └── <span class="folder highlight">hairwang_full/</span>               <span class="comment">── Gnuboard + 서울미녀</span>
│               ├── <span class="file">index.php</span>                <span class="comment">── Gnuboard 진입점 (3KB)</span>
│               ├── <span class="folder">bbs/</span>                     <span class="comment">── 게시판</span>
│               ├── <span class="folder">data/</span>                    <span class="comment">── 업로드 데이터</span>
│               ├── <span class="folder">skin/</span>                    <span class="comment">── 스킨</span>
│               ├── <span class="folder">shop/</span>                    <span class="comment">── 영카트 PG</span>
│               └── <span class="folder highlight">gangnamunni/</span>             <span class="comment">── 서울미녀 프론트+관리자 (<?= $gn_php_count ?>개 PHP)</span>
│                   ├── <span class="file highlight">index.php</span>            <span class="comment">── 서울미녀 메인 (45KB) ★</span>
│                   ├── <span class="file">dashboard.php</span>            <span class="comment">── 관리자 로그인+대시보드</span>
│                   ├── <span class="file">hospitals.php</span>            <span class="comment">── 병원 목록 (3뷰)</span>
│                   ├── <span class="file">reviews.php</span>              <span class="comment">── 후기 목록</span>
│                   ├── <span class="file">booking.php</span>              <span class="comment">── 예약</span>
│                   ├── <span class="file">detail.php</span>               <span class="comment">── 병원 상세</span>
│                   ├── <span class="folder">includes/</span>                <span class="comment">── 공통 컴포넌트</span>
│                   │   ├── <span class="file">admin_header.php</span>     <span class="comment">── 관리자 레이아웃</span>
│                   │   ├── <span class="file">admin_auth.php</span>       <span class="comment">── 인증/RBAC</span>
│                   │   ├── <span class="file">global_nav.php</span>       <span class="comment">── 프론트 네비</span>
│                   │   ├── <span class="file">bottom_nav.php</span>       <span class="comment">── 하단 5탭</span>
│                   │   ├── <span class="file">ad_banner.php</span>        <span class="comment">── 광고 배너</span>
│                   │   ├── <span class="file">cpv_engine.php</span>       <span class="comment">── CPV 과금 엔진</span>
│                   │   └── <span class="file">ai_proxy.php</span>         <span class="comment">── AI 프록시</span>
│                   ├── <span class="folder">api/</span>                     <span class="comment">── API 엔드포인트</span>
│                   ├── <span class="folder">manual/</span>                  <span class="comment">── 자동 매뉴얼 15종</span>
│                   ├── <span class="folder">downloads/</span>               <span class="comment">── 앱 서명키 등</span>
│                   ├── <span class="folder">backups/</span>                 <span class="comment">── DB/소스 백업</span>
│                   ├── <span class="file">admin_*.php</span>              <span class="comment">── 관리자 페이지 (~45개)</span>
│                   └── <span class="file">*.php</span>                    <span class="comment">── 프론트 페이지 (~30개)</span>
├── <span class="folder">phpMyAdmin-5.2.2/</span>                    <span class="comment">── phpMyAdmin</span>
├── <span class="folder">backup/</span>                              <span class="comment">── 전체 백업</span>
├── <span class="folder">site_get/</span>                            <span class="comment">── 크롤링/배포 스크립트</span>
└── <span class="file">info.php</span>                             <span class="comment">── phpinfo()</span>
</pre>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="si-card mb-3">
                    <div class="si-card-title"><i class="bi bi-file-earmark-code"></i> Index 파일 위치</div>
                    <table class="table table-sm mb-0" style="font-size:12px;">
                        <thead>
                            <tr><th>파일</th><th>크기</th><th>역할</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="font-monospace text-primary">public/index.php</td>
                                <td>1.7KB</td>
                                <td><span class="badge bg-danger-subtle text-danger">Laravel 진입</span></td>
                            </tr>
                            <tr>
                                <td class="font-monospace text-primary">hairwang_full/index.php</td>
                                <td>3.0KB</td>
                                <td><span class="badge bg-warning-subtle text-warning">Gnuboard</span></td>
                            </tr>
                            <tr>
                                <td class="font-monospace text-primary">gangnamunni/index.php</td>
                                <td>45KB</td>
                                <td><span class="badge bg-success-subtle text-success">서울미녀 메인</span></td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="mt-2 p-2 rounded" style="background:var(--bs-tertiary-bg); font-size:11px;">
                        <i class="bi bi-info-circle me-1 text-primary"></i>
                        <strong>/root</strong> 폴더에는 index 파일 없음. 모든 웹 파일은 <code>/var/www/html/</code> 하위에 위치.
                    </div>
                </div>

                <div class="si-card mb-3">
                    <div class="si-card-title"><i class="bi bi-link-45deg"></i> Apache Alias 매핑</div>
                    <div style="font-size:12px;">
                        <?php
                        $aliases = [
                            ['/hairwang', 'hairwang_full/', '서울미녀+Gnuboard'],
                            ['/bbs', 'hairwang_full/bbs/', '게시판'],
                            ['/shop', 'hairwang_full/shop/', '영카트 PG'],
                            ['/js', 'hairwang_full/js/', 'JavaScript'],
                            ['/css', 'hairwang_full/css/', 'CSS'],
                            ['/img', 'hairwang_full/img/', '이미지'],
                            ['/api', 'localhost:8000/api', 'HMS Laravel API'],
                        ];
                        foreach ($aliases as $a): ?>
                        <div class="si-domain-row" style="font-size:12px; padding:6px 10px;">
                            <code class="text-warning" style="min-width:70px;"><?= $a[0] ?></code>
                            <span class="arrow"><i class="bi bi-arrow-right"></i></span>
                            <code class="text-info" style="font-size:11px;"><?= $a[1] ?></code>
                            <span class="text-muted ms-auto" style="font-size:10px;"><?= $a[2] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="si-card">
                    <div class="si-card-title"><i class="bi bi-hdd-stack"></i> PHP 파일 통계</div>
                    <table class="table table-sm mb-0" style="font-size:13px;">
                        <tr><td class="text-muted">전체 프로젝트</td><td class="fw-bold"><?= number_format($all_php_count) ?>개</td></tr>
                        <tr><td class="text-muted">hairwang_full</td><td class="fw-bold"><?= number_format($hw_php_count) ?>개</td></tr>
                        <tr><td class="text-muted">서울미녀</td><td class="fw-bold"><?= $gn_php_count ?>개</td></tr>
                        <tr><td class="text-muted">DB 테이블</td><td class="fw-bold"><?= $table_count ?>개</td></tr>
                        <tr><td class="text-muted">DB 크기</td><td class="fw-bold"><?= fmt_bytes($db_size) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== TAB: 서비스 ===== -->
    <div class="tab-pane fade" id="tab-services">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="si-card">
                    <div class="si-card-title"><i class="bi bi-activity"></i> 시스템 서비스</div>
                    <?php foreach ($services as $svc):
                        $active = check_service($svc['key']);
                    ?>
                    <div class="si-svc-item">
                        <div class="si-svc-dot <?= $active ? 'active' : 'inactive' ?>"></div>
                        <i class="<?= $svc['icon'] ?>" style="color:<?= $svc['color'] ?>; font-size:18px;"></i>
                        <div>
                            <div class="fw-semibold" style="font-size:13px;"><?= $svc['name'] ?></div>
                            <div style="font-size:11px; color:var(--bs-secondary-color);"><?= $svc['key'] ?></div>
                        </div>
                        <span class="ms-auto badge <?= $active ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>" style="font-size:11px;">
                            <?= $active ? 'Running' : 'Stopped' ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="si-card">
                    <div class="si-card-title"><i class="bi bi-box"></i> Docker 컨테이너 (Mailcow)</div>
                    <?php
                    $docker_output = @shell_exec("docker ps --format '{{.Names}}|{{.Status}}' 2>/dev/null");
                    $containers = [];
                    if ($docker_output) {
                        foreach (explode("\n", trim($docker_output)) as $line) {
                            $parts = explode('|', $line, 2);
                            if (count($parts) === 2) {
                                $containers[] = ['name' => $parts[0], 'status' => $parts[1]];
                            }
                        }
                    }
                    if (empty($containers)): ?>
                        <div class="text-muted text-center py-3">Docker 정보를 가져올 수 없습니다.</div>
                    <?php else:
                        foreach ($containers as $c):
                            $short = str_replace('mailcowdockerized-', '', $c['name']);
                            $short = str_replace('-mailcow-1', '', $short);
                            $up = strpos($c['status'], 'Up') === 0;
                    ?>
                    <div class="si-svc-item" style="padding:6px 12px;">
                        <div class="si-svc-dot <?= $up ? 'active' : 'inactive' ?>"></div>
                        <div style="font-size:12px; font-weight:600;"><?= htmlspecialchars($short) ?></div>
                        <span class="ms-auto" style="font-size:10px; color:var(--bs-secondary-color);"><?= htmlspecialchars($c['status']) ?></span>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== TAB: 도메인 ===== -->
    <div class="tab-pane fade" id="tab-domains">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="si-card">
                    <div class="si-card-title"><i class="bi bi-globe2"></i> 도메인 & URL 매핑</div>
                    <?php
                    $domains = [
                        ['url' => 'https://hms.seoulbeauty.app/', 'desc' => 'HMS Laravel (메인)', 'target' => 'public/index.php', 'badge' => 'Laravel', 'color' => '#FF2D20'],
                        ['url' => 'https://hms.seoulbeauty.app/hairwang/gangnamunni/', 'desc' => '서울미녀 메인', 'target' => 'gangnamunni/index.php', 'badge' => '서울미녀', 'color' => '#AE1932'],
                        ['url' => 'https://hms.seoulbeauty.app/hairwang/gangnamunni/dashboard.php', 'desc' => '관리자 대시보드', 'target' => 'gangnamunni/dashboard.php', 'badge' => 'Admin', 'color' => '#7B1FA2'],
                        ['url' => 'https://hms.seoulbeauty.app/api/', 'desc' => 'HMS REST API', 'target' => 'localhost:8000/api', 'badge' => 'API', 'color' => '#2196F3'],
                        ['url' => 'https://mail.seoulbeauty.app/', 'desc' => '메일 관리 (Mailcow)', 'target' => 'Docker:8443', 'badge' => 'Mail', 'color' => '#4CAF50'],
                        ['url' => 'https://hms.seoulbeauty.app/hairwang/gangnamunni/manual/', 'desc' => '자동 매뉴얼 15종', 'target' => 'gangnamunni/manual/', 'badge' => 'Docs', 'color' => '#FF9800'],
                    ];
                    foreach ($domains as $d): ?>
                    <div class="si-domain-row">
                        <span class="badge" style="background:<?= $d['color'] ?>; font-size:10px; min-width:50px;"><?= $d['badge'] ?></span>
                        <div style="flex:1;">
                            <a href="<?= $d['url'] ?>" target="_blank" class="text-decoration-none fw-semibold" style="font-size:13px;">
                                <?= $d['url'] ?> <i class="bi bi-box-arrow-up-right" style="font-size:10px;"></i>
                            </a>
                            <div style="font-size:11px; color:var(--bs-secondary-color);"><?= $d['desc'] ?></div>
                        </div>
                        <code style="font-size:11px; color:var(--bs-secondary-color);"><?= $d['target'] ?></code>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="si-card mb-3">
                    <div class="si-card-title"><i class="bi bi-shield-check"></i> Cloudflare</div>
                    <table class="table table-sm mb-0" style="font-size:12px;">
                        <tr><td class="text-muted">도메인</td><td class="fw-bold">seoulbeauty.app</td></tr>
                        <tr><td class="text-muted">프록시</td><td><span class="badge bg-warning text-dark">ON (주황 구름)</span></td></tr>
                        <tr><td class="text-muted">SSL</td><td><span class="badge bg-success">Full (strict)</span></td></tr>
                        <tr><td class="text-muted">캐시</td><td><span class="badge bg-info">Standard</span></td></tr>
                    </table>
                </div>

                <div class="si-card">
                    <div class="si-card-title"><i class="bi bi-hdd-network"></i> VHost 설정 파일</div>
                    <div style="font-size:12px;">
                        <?php
                        $vhosts = @scandir('/etc/httpd/conf.d/') ?: [];
                        $vhosts = array_filter($vhosts, fn($f) => str_ends_with($f, '.conf') && strpos($f, 'seoulbeauty') !== false);
                        foreach ($vhosts as $vh): ?>
                        <div style="padding:4px 0;">
                            <i class="bi bi-file-earmark-text text-primary me-1"></i>
                            <code style="font-size:11px;">/etc/httpd/conf.d/<?= htmlspecialchars($vh) ?></code>
                        </div>
                        <?php endforeach;
                        if (empty($vhosts)): ?>
                        <div class="text-muted">VHost 파일을 읽을 수 없습니다.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    </div><!-- end tab-content -->
</div>

<script>
function copyText(text) {
    navigator.clipboard.writeText(text).then(function() {
        // 토스트 표시
        var toast = document.createElement('div');
        toast.textContent = '복사됨!';
        toast.style.cssText = 'position:fixed;bottom:80px;right:20px;background:#22c55e;color:#fff;padding:8px 16px;border-radius:8px;font-size:13px;z-index:9999;animation:fadeInUp 0.3s ease;';
        document.body.appendChild(toast);
        setTimeout(function() { toast.remove(); }, 1500);
    });
}

function togglePw(elId, pw) {
    var el = document.getElementById(elId);
    if (el.textContent === '••••••••') {
        el.textContent = pw;
        el.style.color = '#fbbf24';
    } else {
        el.textContent = '••••••••';
        el.style.color = '';
    }
}

// 키보드 단축키
document.addEventListener('keydown', function(e) {
    if (e.altKey) {
        var tabs = ['tab-overview','tab-connection','tab-structure','tab-services','tab-domains'];
        var idx = parseInt(e.key) - 1;
        if (idx >= 0 && idx < tabs.length) {
            e.preventDefault();
            var tabEl = document.querySelector('[data-bs-target="#' + tabs[idx] + '"]');
            if (tabEl) new bootstrap.Tab(tabEl).show();
        }
    }
});
</script>

<style>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<?php
mysqli_close($connect);
require_once __DIR__ . '/includes/admin_footer.php';
?>
