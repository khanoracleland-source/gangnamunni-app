<?php
/**
 * 개발자 대시보드 - Bootstrap 5.3
 */
session_start();
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/theme_helper.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');

$is_logged_in = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
if (!$is_logged_in) { header('Location: dashboard.php'); exit; }

// DB 테이블
$tables = [];
$result = mysqli_query($connect, "SHOW TABLES");
while ($row = mysqli_fetch_array($result)) {
    $tn = $row[0];
    $cnt = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as c FROM `{$tn}`"))['c'];
    $st = mysqli_fetch_assoc(mysqli_query($connect, "SHOW TABLE STATUS LIKE '{$tn}'"));
    $tables[] = ['name'=>$tn, 'rows'=>number_format($cnt), 'engine'=>$st['Engine'], 'collation'=>$st['Collation'], 'size'=>round(($st['Data_length']+$st['Index_length'])/1024/1024, 2)];
}

// API 파일
$api_dir = __DIR__ . '/api/';
$api_files = [];
if (is_dir($api_dir)) {
    foreach (scandir($api_dir) as $f) {
        if (pathinfo($f, PATHINFO_EXTENSION)==='php')
            $api_files[] = ['name'=>$f, 'path'=>'/api/'.$f, 'size'=>filesize($api_dir.$f), 'modified'=>date('Y-m-d H:i', filemtime($api_dir.$f))];
    }
}

// 주요 파일
$main_files = ['index.php'=>'메인 페이지','detail_babitalk.php'=>'병원 상세','consultation.php'=>'상담 신청','search.php'=>'검색','editor/review_editor.php'=>'리뷰 에디터','config.php'=>'환경 설정','../dbconfig.php'=>'DB 설정'];
$file_info = [];
foreach ($main_files as $f=>$d) { $p=__DIR__.'/'.$f; if(file_exists($p)) $file_info[]=['name'=>$f,'desc'=>$d,'size'=>filesize($p),'modified'=>date('Y-m-d H:i',filemtime($p)),'lines'=>count(file($p))]; }

// 환경 변수
$env_vars = ['USE_DEMO_DATA'=>USE_DEMO_DATA?'true':'false','HOSPITALS_PER_PAGE'=>HOSPITALS_PER_PAGE,'REVIEWS_PER_PAGE'=>REVIEWS_PER_PAGE,'UPLOAD_MAX_SIZE'=>round(UPLOAD_MAX_SIZE/1024/1024,2).' MB','UPLOAD_MAX_IMAGES'=>UPLOAD_MAX_IMAGES];

// DB 설정
$db_config = ['Host'=>G5_MYSQL_HOST,'Port'=>G5_MYSQL_PORT,'Database'=>G5_MYSQL_DB,'User'=>G5_MYSQL_USER,'Charset'=>'utf8mb4'];

// PHP 확장
$php_ext = ['mysqli','gd','curl','json','mbstring','zip'];

// 서버 정보
$server_info = ['PHP Version'=>PHP_VERSION,'Server Software'=>$_SERVER['SERVER_SOFTWARE']??'Unknown','Document Root'=>$_SERVER['DOCUMENT_ROOT']??'Unknown','Server Name'=>$_SERVER['SERVER_NAME']??'Unknown','Server IP'=>$_SERVER['SERVER_ADDR']??'Unknown','Max Execution'=>ini_get('max_execution_time').'s','Memory Limit'=>ini_get('memory_limit'),'Upload Max'=>ini_get('upload_max_filesize'),'Post Max'=>ini_get('post_max_size')];

$menu_items = [
    ['url'=>'dashboard.php','icon'=>'🏠','text'=>'관리 대시보드'],
    ['url'=>'auto_test.php','icon'=>'🧪','text'=>'자동 테스트'],
    ['url'=>'functional_test.php','icon'=>'🔬','text'=>'기능 테스트'],
    ['url'=>'admin_ads.php','icon'=>'📢','text'=>'광고 관리'],
    ['url'=>'admin_reviews.php','icon'=>'⭐','text'=>'리뷰 관리'],
    ['url'=>'admin_theme.php','icon'=>'🎨','text'=>'테마 설정'],
    ['url'=>'admin_categories.php','icon'=>'📁','text'=>'카테고리'],
    ['url'=>'admin_navigation.php','icon'=>'🧭','text'=>'네비게이션'],
    ['url'=>'admin_hospital_images.php','icon'=>'🖼','text'=>'병원 이미지'],
    ['url'=>'admin_upgrade_plan.php','icon'=>'🚀','text'=>'인프라 확장'],
];
$adv_items = [
    ['url'=>'ai_ad_editor.php','icon'=>'🤖','text'=>'AI 에디터'],
    ['url'=>'ab_test_dashboard.php','icon'=>'🧪','text'=>'A/B 테스트'],
    ['url'=>'admin_maintenance.php','icon'=>'🔧','text'=>'점검 모드'],
];
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="dark">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>서울미녀 개발자 대시보드</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --sidebar-w: 230px; }
        body { font-family: 'Monaco','Courier New',monospace; background: #1a1a2e; }

        .top-header { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); position: fixed; top: 0; left: 0; right: 0; height: 50px; z-index: 1030; }
        .top-header h1, .top-header a, .top-header span { color: #1e1e1e !important; }

        .sidebar { position: fixed; top: 50px; left: 0; bottom: 0; width: var(--sidebar-w); background: #16213e; overflow-y: auto; z-index: 1020; border-right: 1px solid #1a1a2e; }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 4px; }
        .sidebar .nav-link { color: rgba(255,255,255,0.65); font-size: 13px; padding: 8px 16px; border-left: 3px solid transparent; display: flex; align-items: center; gap: 10px; }
        .sidebar .nav-link:hover { color: #4fc3f7; background: rgba(255,255,255,0.05); border-left-color: #4fc3f7; }
        .sidebar .nav-link.active { color: #4fc3f7; border-left-color: #4fc3f7; background: rgba(79,195,247,0.1); }
        .nav-section { color: rgba(255,255,255,0.35); font-size: 10px; text-transform: uppercase; letter-spacing: 1px; padding: 16px 16px 4px; }

        .main-wrap { margin-left: var(--sidebar-w); margin-top: 50px; padding: 20px; min-height: calc(100vh - 50px); }

        .dev-card { background: #16213e; border: 1px solid #0f3460; border-radius: 10px; }
        .dev-card .card-header { background: #0f3460; border-bottom: 1px solid #0f3460; font-size: 13px; font-weight: 700; color: #4fc3f7; }

        .kv-row { display: flex; padding: 6px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 12px; }
        .kv-row:last-child { border-bottom: none; }
        .kv-key { width: 160px; color: #9cdcfe; font-weight: 600; flex-shrink: 0; }
        .kv-val { color: #d4d4d4; word-break: break-all; }

        .table-dark th { background: #0f3460; color: #4fc3f7; font-size: 12px; }
        .table-dark td { font-size: 12px; vertical-align: middle; }

        .code-block { background: #0a0a0a; border: 1px solid #333; border-radius: 6px; padding: 12px; font-size: 11px; line-height: 1.5; color: #d4d4d4; overflow-x: auto; }
        .code-comment { color: #6a9955; }
        .code-keyword { color: #569cd6; }
        .code-string { color: #ce9178; }

        .stat-box { background: #0f3460; border-radius: 10px; text-align: center; padding: 16px; }
        .stat-box .num { font-size: 28px; font-weight: 700; color: #4fc3f7; }
        .stat-box .lbl { font-size: 11px; color: rgba(255,255,255,0.5); margin-top: 4px; }

        @media (max-width: 992px) {
            .sidebar { display: none; }
            .main-wrap { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- 헤더 -->
<header class="top-header d-flex align-items-center justify-content-between px-3">
    <h1 class="fw-bold fs-6 mb-0">💻 서울미녀 개발자 대시보드</h1>
    <div class="d-flex align-items-center gap-2">
        <span class="small">👤 <?= htmlspecialchars($_SESSION['ad_admin_user']) ?></span>
        <a href="dashboard.php" class="btn btn-sm btn-dark">← 관리자 대시보드</a>
    </div>
</header>

<!-- 사이드바 -->
<nav class="sidebar">
    <div class="nav-section mt-2">📋 개발 메뉴</div>
    <ul class="nav flex-column">
        <?php foreach ($menu_items as $m): ?>
        <li class="nav-item"><a href="<?=$m['url']?>" class="nav-link" target="_blank"><span><?=$m['icon']?></span> <?=$m['text']?></a></li>
        <?php endforeach; ?>
    </ul>
    <div class="nav-section">⚡ 고급 기능</div>
    <ul class="nav flex-column">
        <?php foreach ($adv_items as $m): ?>
        <li class="nav-item"><a href="<?=$m['url']?>" class="nav-link active" target="_blank"><span><?=$m['icon']?></span> <?=$m['text']?> <span class="badge bg-primary ms-auto" style="font-size:9px">NEW</span></a></li>
        <?php endforeach; ?>
        <li class="nav-item"><a href="index.php" class="nav-link" target="_blank"><span>🌐</span> 사이트 보기</a></li>
    </ul>
</nav>

<!-- 메인 -->
<div class="main-wrap">

    <!-- 통계 -->
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="stat-box"><div class="num"><?=count($tables)?></div><div class="lbl">DB 테이블</div></div></div>
        <div class="col-md-4"><div class="stat-box"><div class="num"><?=count($api_files)?></div><div class="lbl">API 엔드포인트</div></div></div>
        <div class="col-md-4"><div class="stat-box"><div class="num"><?=count($file_info)?></div><div class="lbl">주요 파일</div></div></div>
    </div>

    <!-- 서버 + DB -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dev-card h-100">
                <div class="card-header">🖥️ 서버 정보</div>
                <div class="card-body p-3">
                    <?php foreach ($server_info as $k=>$v): ?>
                    <div class="kv-row"><div class="kv-key"><?=$k?>:</div><div class="kv-val"><?=htmlspecialchars($v)?></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dev-card h-100">
                <div class="card-header">🗄️ 데이터베이스 연결</div>
                <div class="card-body p-3">
                    <?php foreach ($db_config as $k=>$v): ?>
                    <div class="kv-row"><div class="kv-key"><?=$k?>:</div><div class="kv-val"><span class="badge bg-success" style="font-size:8px">●</span> <?=htmlspecialchars($v)?></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 환경 변수 + PHP 모듈 -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dev-card h-100">
                <div class="card-header">⚙️ 환경 변수 (config.php)</div>
                <div class="card-body p-3">
                    <?php foreach ($env_vars as $k=>$v): ?>
                    <div class="kv-row"><div class="kv-key"><?=$k?>:</div><div class="kv-val"><?=htmlspecialchars($v)?></div></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dev-card h-100">
                <div class="card-header">🔌 PHP 확장 모듈</div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <?php foreach ($php_ext as $e): ?>
                        <div class="col-4">
                            <span class="badge <?=extension_loaded($e)?'bg-success':'bg-danger'?> w-100 py-2"><?=extension_loaded($e)?'✓':'✗'?> <?=$e?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DB 테이블 -->
    <div class="card dev-card mb-4">
        <div class="card-header">🗄️ 데이터베이스 테이블 (<?=count($tables)?>개)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-striped table-hover mb-0">
                    <thead><tr><th>테이블명</th><th>레코드</th><th>엔진</th><th>인코딩</th><th>크기</th></tr></thead>
                    <tbody>
                    <?php foreach ($tables as $t): ?>
                        <tr><td><span class="badge bg-info text-dark"><?=htmlspecialchars($t['name'])?></span></td><td><?=$t['rows']?></td><td><?=$t['engine']?></td><td class="small"><?=$t['collation']?></td><td><?=$t['size']?> MB</td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- API + 주요 파일 -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dev-card h-100">
                <div class="card-header">🔗 API 엔드포인트 (<?=count($api_files)?>개)</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead><tr><th>파일</th><th>경로</th><th>크기</th><th>수정일</th></tr></thead>
                            <tbody>
                            <?php foreach ($api_files as $a): ?>
                                <tr><td><span class="badge bg-success"><?=$a['name']?></span></td><td class="small" style="color:#9cdcfe"><?=$a['path']?></td><td><?=number_format($a['size'])?></td><td class="small"><?=$a['modified']?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dev-card h-100">
                <div class="card-header">📄 주요 파일</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead><tr><th>파일</th><th>설명</th><th>줄</th><th>크기</th></tr></thead>
                            <tbody>
                            <?php foreach ($file_info as $f): ?>
                                <tr><td><span class="badge bg-warning text-dark"><?=$f['name']?></span></td><td><?=$f['desc']?></td><td><?=number_format($f['lines'])?></td><td><?=number_format($f['size'])?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- API 예제 -->
    <div class="card dev-card mb-4">
        <div class="card-header">📡 API 사용 예제</div>
        <div class="card-body">
            <div class="code-block"><span class="code-comment">// 병원 목록 조회</span>
<span class="code-keyword">GET</span> <span class="code-string">/api/list_hospitals.php</span>
<span class="code-keyword">Parameters:</span> page, limit, category

<span class="code-comment">// 리뷰 저장</span>
<span class="code-keyword">POST</span> <span class="code-string">/api/save_review.php</span>
<span class="code-keyword">Body:</span> { hospital_id, rating, content, images }

<span class="code-comment">// 이미지 업로드</span>
<span class="code-keyword">POST</span> <span class="code-string">/api/upload_image.php</span>
<span class="code-keyword">Content-Type:</span> multipart/form-data</div>
        </div>
    </div>

    <!-- 개발 도구 + 보안 -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="card dev-card h-100">
                <div class="card-header">🛠️ 개발 도구</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-4"><a href="editor/review_editor.php?mode=create&hospital_id=1" target="_blank" class="btn btn-outline-info btn-sm w-100">📝 리뷰 에디터</a></div>
                        <div class="col-4"><a href="index.php" target="_blank" class="btn btn-outline-info btn-sm w-100">🏠 메인 보기</a></div>
                        <div class="col-4"><a href="https://hms.seoulbeauty.app/admin" target="_blank" class="btn btn-outline-info btn-sm w-100">🏥 HMS</a></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card dev-card h-100 border-danger">
                <div class="card-header bg-danger text-white">⚠️ 보안 주의사항</div>
                <div class="card-body small">
                    <ul class="mb-0" style="padding-left:16px; line-height:1.8;">
                        <li>이 페이지는 개발 환경에서만 사용</li>
                        <li>프로덕션: IP 화이트리스트 적용</li>
                        <li>DB 비밀번호: 환경 변수 관리</li>
                        <li>민감 정보: .gitignore 설정 필수</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
