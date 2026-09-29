<?php
/**
 * 하단 네비게이션 탭 설정
 * 각 탭(홈/병원/이벤트/후기/마이)의 표시 ON/OFF
 */
session_start();
$admin_page_title = '하단 탭 설정';
$admin_current = 'bottom_nav_settings';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('nav_settings');

// 저장 처리
$save_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_bottom_nav') {
    $keys = [
        'bottom_nav_home_enabled',
        'bottom_nav_hospital_enabled',
        'bottom_nav_event_enabled',
        'bottom_nav_review_enabled',
        'bottom_nav_my_enabled',
    ];
    foreach ($keys as $key) {
        $val = isset($_POST[$key]) ? '1' : '0';
        $ek = mysqli_real_escape_string($connect, $key);
        $ev = mysqli_real_escape_string($connect, $val);
        mysqli_query($connect, "UPDATE gangnamunni_settings SET setting_value = '{$ev}' WHERE setting_key = '{$ek}'");
        if (mysqli_affected_rows($connect) === 0) {
            mysqli_query($connect, "INSERT IGNORE INTO gangnamunni_settings (setting_group, setting_key, setting_value, setting_type, description) VALUES ('bottom_nav', '{$ek}', '{$ev}', 'boolean', '')");
        }
    }
    $save_msg = '하단 탭 설정이 저장되었습니다.';
}

// 현재 설정 로드
$nav_settings = [];
$q = mysqli_query($connect, "SELECT setting_key, setting_value FROM gangnamunni_settings WHERE setting_group = 'bottom_nav'");
if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $nav_settings[$row['setting_key']] = $row['setting_value'];
    }
}

$tabs = [
    ['key' => 'bottom_nav_home_enabled', 'label' => '홈', 'icon' => 'bi-house-fill', 'desc' => '메인 홈 페이지 (index.php)'],
    ['key' => 'bottom_nav_hospital_enabled', 'label' => '병원', 'icon' => 'bi-building-fill', 'desc' => '병원 목록 페이지 (hospitals.php)'],
    ['key' => 'bottom_nav_event_enabled', 'label' => '이벤트', 'icon' => 'bi-calendar-event-fill', 'desc' => '이벤트 목록 페이지 (events.php)'],
    ['key' => 'bottom_nav_review_enabled', 'label' => '후기', 'icon' => 'bi-star-fill', 'desc' => '후기 목록 페이지 (reviews.php)'],
    ['key' => 'bottom_nav_my_enabled', 'label' => '마이', 'icon' => 'bi-person-fill', 'desc' => '마이페이지 (mypage.php)'],
];

include __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0"><i class="bi bi-phone me-2"></i>하단 탭 설정</h2>
            </div>

            <?php if (!empty($save_msg)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-1"></i><?php echo $save_msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header" style="background: var(--primary-color, #AE1932); color: #fff;">
                    <h5 class="mb-0"><i class="bi bi-toggles me-2"></i>하단 네비게이션 탭 ON/OFF</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">사용자에게 보이는 하단 고정 탭을 개별적으로 켜거나 끌 수 있습니다. 최소 1개 이상의 탭을 활성화해 주세요.</p>

                    <form method="POST" id="navSettingsForm">
                        <input type="hidden" name="action" value="save_bottom_nav">

                        <?php foreach ($tabs as $tab): ?>
                        <?php $is_on = ($nav_settings[$tab['key']] ?? '1') === '1'; ?>
                        <div class="d-flex align-items-center justify-content-between py-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <span style="width:40px;height:40px;border-radius:10px;background:var(--bs-tertiary-bg, #f0f0f0);display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--primary-color, #AE1932);">
                                    <i class="bi <?php echo $tab['icon']; ?>"></i>
                                </span>
                                <div>
                                    <div class="fw-bold"><?php echo $tab['label']; ?> 탭</div>
                                    <div class="text-muted" style="font-size:13px;"><?php echo $tab['desc']; ?></div>
                                </div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="<?php echo $tab['key']; ?>" value="1" id="<?php echo $tab['key']; ?>" <?php echo $is_on ? 'checked' : ''; ?> style="width:3em;height:1.5em;cursor:pointer;">
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <div class="mt-4 d-flex justify-content-between align-items-center">
                            <span class="text-muted" style="font-size:13px;" id="tabCountInfo"></span>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check2 me-1"></i>저장
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 미리보기 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-eye me-2"></i>미리보기</h5>
                </div>
                <div class="card-body p-0">
                    <div id="navPreview" style="position:relative;height:60px;background:var(--bs-body-bg, #fff);border-top:1px solid var(--bs-border-color, #eee);display:flex;align-items:stretch;max-width:400px;margin:20px auto;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);"></div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// 실시간 미리보기 업데이트
var tabData = <?php echo json_encode($tabs); ?>;
function updatePreview() {
    var preview = document.getElementById('navPreview');
    var count = 0;
    var html = '';
    tabData.forEach(function(tab) {
        var cb = document.getElementById(tab.key);
        if (cb && cb.checked) {
            count++;
            html += '<div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;color:var(--primary-color, #AE1932);font-size:10px;gap:2px;">' +
                '<i class="bi ' + tab.icon + '" style="font-size:20px;"></i>' +
                '<span>' + tab.label + '</span></div>';
        }
    });
    preview.innerHTML = html || '<div style="flex:1;display:flex;align-items:center;justify-content:center;color:#999;">탭을 1개 이상 활성화하세요</div>';
    document.getElementById('tabCountInfo').textContent = '활성 탭: ' + count + '개 / 5개';
}

document.querySelectorAll('.form-check-input').forEach(function(cb) {
    cb.addEventListener('change', updatePreview);
});
updatePreview();
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
