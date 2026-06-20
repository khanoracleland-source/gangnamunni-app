<?php
session_start();
$admin_page_title = '앱 다운로드';
$admin_current = isset($_GET['app']) && $_GET['app'] === 'admin' ? 'app_admin' : 'app_patient';
$admin_page_description = '서울미녀 앱 스토어 정보 및 다운로드 링크';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();

$current_app = isset($_GET['app']) ? $_GET['app'] : 'patient';

$apps = [
    'patient' => [
        'name' => '서울미녀',
        'package' => 'com.celebhospital.patient',
        'desc' => '성형·피부과 전문 비교 플랫폼. 병원 검색, 후기, 예약, 이벤트를 한 곳에서.',
        'gradient' => 'linear-gradient(135deg,#667eea 0%,#764ba2 100%)',
        'google_play' => 'https://play.google.com/store/apps/details?id=com.celebhospital.patient',
        'app_store' => '',
        'screenshots' => [
            ['file' => 'patient_01_home.png', 'label' => '홈'],
            ['file' => 'patient_02_hospitals.png', 'label' => '병원 목록'],
            ['file' => 'patient_03_reviews.png', 'label' => '후기'],
            ['file' => 'patient_04_booking.png', 'label' => '예약'],
        ],
        'features' => ['병원 검색 & 비교', '시술 후기 열람', '온라인 예약', '이벤트 & 프로모션', '상담 신청', '푸시 알림'],
        'version' => 'v1.9.0',
        'min_android' => 'Android 5.0+',
        'min_ios' => 'iOS 14.0+',
    ],
    'admin' => [
        'name' => '서울미녀 파트너',
        'package' => 'com.celebhospital.admin',
        'desc' => '병원 관리자를 위한 파트너 앱. 예약 관리, 후기 관리, 통계를 모바일에서.',
        'gradient' => 'linear-gradient(135deg,#f093fb 0%,#f5576c 100%)',
        'google_play' => 'https://play.google.com/store/apps/details?id=com.celebhospital.admin',
        'app_store' => '',
        'screenshots' => [
            ['file' => 'admin_01_login.png', 'label' => '로그인'],
            ['file' => 'admin_02_dashboard.png', 'label' => '대시보드'],
            ['file' => 'admin_03_hospitals.png', 'label' => '병원 관리'],
            ['file' => 'admin_04_bookings.png', 'label' => '예약 관리'],
        ],
        'features' => ['대시보드 & 통계', '예약 관리', '병원 정보 관리', '후기 모니터링', '광고 관리', '푸시 알림 발송'],
        'version' => 'v1.9.0',
        'min_android' => 'Android 5.0+',
        'min_ios' => 'iOS 14.0+',
    ],
];

$app = $apps[$current_app] ?? $apps['patient'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0"><i class="bi bi-phone me-2"></i><?= htmlspecialchars($app['name']) ?></h5>
    <div class="btn-group btn-group-sm">
        <a href="?app=patient" class="btn <?= $current_app === 'patient' ? 'btn-primary' : 'btn-outline-primary' ?>">서울미녀</a>
        <a href="?app=admin" class="btn <?= $current_app === 'admin' ? 'btn-primary' : 'btn-outline-primary' ?>">서울미녀 파트너</a>
    </div>
</div>

<!-- App Hero Card -->
<div class="card mb-4 border-0 overflow-hidden" style="background:<?= $app['gradient'] ?>; color:#fff;">
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-md-7">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width:64px; height:64px; background:rgba(255,255,255,0.2); border-radius:16px; display:flex; align-items:center; justify-content:center;">
                        <i class="bi bi-phone" style="font-size:32px;"></i>
                    </div>
                    <div>
                        <h3 class="mb-0 fw-bold"><?= htmlspecialchars($app['name']) ?></h3>
                        <div class="opacity-75" style="font-size:13px;"><?= htmlspecialchars($app['package']) ?></div>
                    </div>
                </div>
                <p class="mb-3 opacity-90" style="font-size:15px;"><?= htmlspecialchars($app['desc']) ?></p>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge" style="background:rgba(255,255,255,0.2); font-size:12px; padding:6px 12px;">
                        <i class="bi bi-tag me-1"></i><?= $app['version'] ?>
                    </span>
                    <span class="badge" style="background:rgba(255,255,255,0.2); font-size:12px; padding:6px 12px;">
                        <i class="bi bi-android2 me-1"></i><?= $app['min_android'] ?>
                    </span>
                    <span class="badge" style="background:rgba(255,255,255,0.2); font-size:12px; padding:6px 12px;">
                        <i class="bi bi-apple me-1"></i><?= $app['min_ios'] ?>
                    </span>
                </div>
            </div>
            <div class="col-md-5 mt-3 mt-md-0">
                <div class="d-flex gap-2 justify-content-md-end">
                    <?php if ($app['google_play']): ?>
                    <a href="<?= htmlspecialchars($app['google_play']) ?>" target="_blank" class="btn btn-light btn-lg fw-bold" style="border-radius:12px;">
                        <i class="bi bi-google me-2"></i>Google Play
                    </a>
                    <?php endif; ?>
                    <?php if ($app['app_store']): ?>
                    <a href="<?= htmlspecialchars($app['app_store']) ?>" target="_blank" class="btn btn-light btn-lg fw-bold" style="border-radius:12px;">
                        <i class="bi bi-apple me-2"></i>App Store
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Screenshots -->
<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0"><i class="bi bi-images me-2"></i>앱 스크린샷</h6>
    </div>
    <div class="card-body">
        <div class="d-flex gap-3 overflow-auto pb-2" style="scroll-snap-type:x mandatory;">
            <?php foreach ($app['screenshots'] as $ss): ?>
            <div style="flex:0 0 auto; scroll-snap-align:start; text-align:center;">
                <div style="width:200px; height:355px; border-radius:16px; overflow:hidden; border:2px solid var(--bs-border-color); box-shadow:0 4px 12px rgba(0,0,0,0.1); cursor:pointer; position:relative;"
                     onclick="openLightbox(this.querySelector('img').src)">
                    <img src="/hairwang/gangnamunni/screenshots/store/<?= htmlspecialchars($ss['file']) ?>"
                         style="width:100%; height:100%; object-fit:cover;" loading="lazy">
                </div>
                <div class="mt-2 text-body-secondary" style="font-size:12px; font-weight:600;"><?= htmlspecialchars($ss['label']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Features & Download Links -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0"><i class="bi bi-stars me-2"></i>주요 기능</h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <?php foreach ($app['features'] as $feat): ?>
                    <div class="col-6">
                        <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:var(--bs-tertiary-bg);">
                            <i class="bi bi-check-circle-fill text-success"></i>
                            <span style="font-size:13px;"><?= htmlspecialchars($feat) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-body-tertiary">
                <h6 class="mb-0"><i class="bi bi-download me-2"></i>다운로드 링크</h6>
            </div>
            <div class="card-body">
                <!-- Google Play -->
                <?php if ($app['google_play']): ?>
                <div class="p-3 rounded mb-3" style="background:var(--bs-tertiary-bg);">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div style="width:40px; height:40px; background:#01875f; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                            <i class="bi bi-google text-white" style="font-size:18px;"></i>
                        </div>
                        <div>
                            <div class="fw-bold">Google Play</div>
                            <div class="text-body-secondary" style="font-size:12px;">Android</div>
                        </div>
                        <a href="<?= htmlspecialchars($app['google_play']) ?>" target="_blank" class="btn btn-sm btn-success ms-auto">
                            <i class="bi bi-box-arrow-up-right me-1"></i>스토어 열기
                        </a>
                    </div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" value="<?= htmlspecialchars($app['google_play']) ?>" readonly id="gplay_url">
                        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('gplay_url').value); this.innerHTML='<i class=\'bi bi-check\'></i> 복사됨'; setTimeout(()=>this.innerHTML='<i class=\'bi bi-clipboard\'></i> 복사',2000);">
                            <i class="bi bi-clipboard"></i> 복사
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- App Store -->
                <div class="p-3 rounded mb-3" style="background:var(--bs-tertiary-bg);">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div style="width:40px; height:40px; background:#000; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                            <i class="bi bi-apple text-white" style="font-size:18px;"></i>
                        </div>
                        <div>
                            <div class="fw-bold">App Store</div>
                            <div class="text-body-secondary" style="font-size:12px;">iOS</div>
                        </div>
                        <?php if ($app['app_store']): ?>
                        <a href="<?= htmlspecialchars($app['app_store']) ?>" target="_blank" class="btn btn-sm btn-dark ms-auto">
                            <i class="bi bi-box-arrow-up-right me-1"></i>스토어 열기
                        </a>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark ms-auto"><i class="bi bi-clock me-1"></i>심사 중</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($app['app_store']): ?>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" value="<?= htmlspecialchars($app['app_store']) ?>" readonly id="appstore_url">
                        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('appstore_url').value); this.innerHTML='<i class=\'bi bi-check\'></i> 복사됨'; setTimeout(()=>this.innerHTML='<i class=\'bi bi-clipboard\'></i> 복사',2000);">
                            <i class="bi bi-clipboard"></i> 복사
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="text-body-secondary" style="font-size:13px;">Apple App Store 심사 진행 중입니다.</div>
                    <?php endif; ?>
                </div>

                <!-- AAB Download -->
                <div class="p-3 rounded" style="background:var(--bs-tertiary-bg);">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:40px; height:40px; background:#6366f1; border-radius:10px; display:flex; align-items:center; justify-content:center;">
                            <i class="bi bi-file-earmark-zip text-white" style="font-size:18px;"></i>
                        </div>
                        <div>
                            <div class="fw-bold">AAB 빌드 파일</div>
                            <div class="text-body-secondary" style="font-size:12px;"><?= $app['version'] ?> (versionCode 25)</div>
                        </div>
                        <a href="/hairwang/gangnamunni/app_builds/android/<?= $current_app ?>/com.celebhospital.<?= $current_app ?>-v1.9.0-vc25.aab" download class="btn btn-sm btn-outline-primary ms-auto">
                            <i class="bi bi-download me-1"></i>다운로드
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Google Play Embed Preview -->
<?php if ($app['google_play']): ?>
<div class="card mb-4">
    <div class="card-header bg-body-tertiary">
        <h6 class="mb-0"><i class="bi bi-google me-2"></i>Google Play 스토어 페이지</h6>
    </div>
    <div class="card-body text-center p-4">
        <img src="/hairwang/gangnamunni/screenshots/store/gplay_<?= $current_app ?>.png"
             style="max-width:100%; border-radius:12px; border:1px solid var(--bs-border-color); box-shadow:0 4px 16px rgba(0,0,0,0.1); cursor:pointer;"
             onclick="openLightbox(this.src)" loading="lazy">
        <div class="mt-3">
            <a href="<?= htmlspecialchars($app['google_play']) ?>" target="_blank" class="btn btn-success">
                <i class="bi bi-google me-2"></i>Google Play에서 보기
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Lightbox -->
<div id="lightbox" onclick="this.style.display='none'" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; cursor:pointer; justify-content:center; align-items:center;">
    <img id="lightbox-img" style="max-width:90%; max-height:90%; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.5);">
    <div style="position:absolute; top:20px; right:30px; color:#fff; font-size:32px; cursor:pointer;">&times;</div>
</div>
<script>
function openLightbox(src) {
    const lb = document.getElementById('lightbox');
    document.getElementById('lightbox-img').src = src;
    lb.style.display = 'flex';
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
