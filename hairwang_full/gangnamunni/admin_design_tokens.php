<?php
session_start();
$admin_page_title = '디자인 토큰 관리';
$admin_current = 'design_tokens';

require_once __DIR__ . '/../dbconfig.php';
if (!defined('SITE_FULL_URL')) define('SITE_FULL_URL', 'https://hms.seoulbeauty.app/hairwang/app/');
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

// DB 연결
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}

loadThemeSettings($connect);
require_admin_login();
require_permission('design_mode');

if (file_exists(__DIR__ . '/includes/settings_helper.php')) {
    require_once __DIR__ . '/includes/settings_helper.php';
}
require_once __DIR__ . '/includes/design_tokens.php';

// DB에 기본 토큰이 없으면 자동 삽입
ensureDesignTokensExist($connect);

// === POST 처리 ===
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save_tokens') {
        $updated = 0;
        if (!empty($_POST['tokens']) && is_array($_POST['tokens'])) {
            foreach ($_POST['tokens'] as $key => $value) {
                $key = mysqli_real_escape_string($connect, $key);
                $value = trim($value);
                $escaped_value = mysqli_real_escape_string($connect, $value);
                $q = mysqli_query($connect, "UPDATE gangnamunni_settings SET setting_value='{$escaped_value}' WHERE setting_key='{$key}' AND setting_group='figma_design'");
                if ($q && mysqli_affected_rows($connect) > 0) $updated++;
            }
        }
        // theme_settings도 동기화 (primary_color 등)
        if (isset($_POST['tokens']['fd_primary_color'])) {
            $pc = mysqli_real_escape_string($connect, trim($_POST['tokens']['fd_primary_color']));
            mysqli_query($connect, "UPDATE gangnamunni_theme_settings SET setting_value='{$pc}' WHERE setting_key='primary_color'");
            mysqli_query($connect, "UPDATE gangnamunni_theme_settings SET setting_value='{$pc}' WHERE setting_key='login_button_bg'");
            mysqli_query($connect, "UPDATE gangnamunni_theme_settings SET setting_value='{$pc}' WHERE setting_key='link_color'");
        }
        if (isset($_POST['tokens']['fd_primary_dark'])) {
            $pd = mysqli_real_escape_string($connect, trim($_POST['tokens']['fd_primary_dark']));
            mysqli_query($connect, "UPDATE gangnamunni_theme_settings SET setting_value='{$pd}' WHERE setting_key='secondary_color'");
            mysqli_query($connect, "UPDATE gangnamunni_theme_settings SET setting_value='{$pd}' WHERE setting_key='link_hover_color'");
        }

        // 캐시 무효화
        global $_design_tokens_cache;
        $_design_tokens_cache = null;
        loadDesignTokens($connect);

        $msg = "{$updated}개 디자인 토큰이 저장되었습니다. 테마 설정도 동기화되었습니다.";
        $msg_type = 'success';
    }

    if ($action === 'reset_defaults') {
        $defaults = getDesignTokenDefaults();
        $updated = 0;
        foreach ($defaults as $key => [$val, $type, $desc]) {
            $q = mysqli_query($connect, "UPDATE gangnamunni_settings SET setting_value='" . mysqli_real_escape_string($connect, $val) . "' WHERE setting_key='{$key}' AND setting_group='figma_design'");
            if ($q && mysqli_affected_rows($connect) > 0) $updated++;
        }
        // theme_settings도 기본값으로
        mysqli_query($connect, "UPDATE gangnamunni_theme_settings SET setting_value='#AE1932' WHERE setting_key='primary_color'");
        mysqli_query($connect, "UPDATE gangnamunni_theme_settings SET setting_value='#8A1428' WHERE setting_key='secondary_color'");
        mysqli_query($connect, "UPDATE gangnamunni_theme_settings SET setting_value='#AE1932' WHERE setting_key='login_button_bg'");
        mysqli_query($connect, "UPDATE gangnamunni_theme_settings SET setting_value='#AE1932' WHERE setting_key='link_color'");
        $msg = "{$updated}개 설정이 피그마 기본값으로 복원되었습니다.";
        $msg_type = 'warning';
    }
}

// === 설정 로드 ===
$tokens = [];
$q = mysqli_query($connect, "SELECT * FROM gangnamunni_settings WHERE setting_group='figma_design' ORDER BY id");
if ($q) {
    while ($row = mysqli_fetch_assoc($q)) {
        $tokens[] = $row;
    }
}

// 그룹 분류
$groups = [
    'colors' => ['label' => '색상 (피그마 디자인 토큰)', 'keys' => [
        'fd_primary_color','fd_primary_light','fd_primary_dark',
        'fd_inactive_color','fd_subtitle_color','fd_placeholder_color','fd_price_strike_color',
        'fd_page_bg_color','fd_header_bg_color','fd_border_light_color','fd_dark_color',
        'fd_login_btn_bg','fd_region_btn_bg',
        'fd_bottom_nav_active','fd_hospital_price_color',
        'fd_community_tab_active','fd_community_tab_inactive',
    ]],
    'texts' => ['label' => '텍스트/라벨', 'keys' => [
        'fd_login_btn_text','fd_region_btn_text','fd_search_placeholder',
        'fd_section_tips_title','fd_section_hospitals_title','fd_section_events_title',
        'fd_sort_chips',
    ]],
    'toggles' => ['label' => '표시/숨김 토글', 'keys' => [
        'fd_treatment_table_visible','fd_old_grid_visible','fd_quick_menu_visible',
        'fd_category_filter_visible','fd_ad_banner_sticky',
    ]],
    'nav' => ['label' => '하단 네비게이션 탭', 'keys' => [
        'fd_bottom_nav_tab1_label','fd_bottom_nav_tab1_url','fd_bottom_nav_tab1_icon',
        'fd_bottom_nav_tab2_label','fd_bottom_nav_tab2_url','fd_bottom_nav_tab2_icon',
        'fd_bottom_nav_tab3_label','fd_bottom_nav_tab3_url','fd_bottom_nav_tab3_icon',
        'fd_bottom_nav_tab4_label','fd_bottom_nav_tab4_url','fd_bottom_nav_tab4_icon',
    ]],
    'layout' => ['label' => '레이아웃 수치', 'keys' => [
        'fd_search_border_radius','fd_card_border_radius','fd_chip_border_radius','fd_bottom_nav_height',
    ]],
    'data' => ['label' => '데이터 (JSON)', 'keys' => ['fd_treatment_categories']],
];

// 토큰을 key로 인덱싱
$token_map = [];
foreach ($tokens as $t) {
    $token_map[$t['setting_key']] = $t;
}

include __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?php echo $msg_type === 'success' ? 'success' : 'warning'; ?> alert-dismissible fade show">
    <?php echo $msg; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">디자인 토큰 관리</h4>
        <p class="text-muted mb-0">피그마 디자인 값을 DB에서 관리합니다. 수정 즉시 전체 사이트에 반영됩니다.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo SITE_FULL_URL; ?>index.php" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-eye me-1"></i>미리보기
        </a>
        <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="reset_defaults">
            <button type="submit" class="btn btn-outline-warning btn-sm" onclick="return confirm('기본값으로 복원하시겠습니까?')">
                <i class="bi bi-arrow-counterclockwise me-1"></i>기본값 복원
            </button>
        </form>
    </div>
</div>

<!-- 페이지 바로가기 -->
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-link-45deg me-1"></i> 페이지 바로가기</div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo SITE_FULL_URL; ?>index.php" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-house me-1"></i>홈</a>
            <a href="<?php echo SITE_FULL_URL; ?>hospitals.php" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-building me-1"></i>병원</a>
            <a href="<?php echo SITE_FULL_URL; ?>community.php" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-chat-square-text me-1"></i>커뮤니티</a>
            <a href="<?php echo SITE_FULL_URL; ?>mypage.php" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-person me-1"></i>마이페이지</a>
            <a href="<?php echo SITE_FULL_URL; ?>detail.php?id=8093" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-text me-1"></i>병원 상세</a>
            <a href="<?php echo SITE_FULL_URL; ?>booking.php?hospital_id=8093" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar me-1"></i>예약</a>
            <a href="<?php echo SITE_FULL_URL; ?>reviews.php" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-star me-1"></i>후기</a>
            <a href="<?php echo SITE_FULL_URL; ?>events.php" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar-event me-1"></i>이벤트</a>
            <a href="<?php echo SITE_FULL_URL; ?>map.php" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-map me-1"></i>지도</a>
            <a href="<?php echo SITE_FULL_URL; ?>rankings.php" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-trophy me-1"></i>랭킹</a>
            <a href="<?php echo SITE_FULL_URL; ?>price_compare.php" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-tags me-1"></i>가격비교</a>
            <a href="<?php echo SITE_FULL_URL; ?>tips.php" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-lightbulb me-1"></i>꿀팁</a>
        </div>
    </div>
</div>

<!-- 실시간 미리보기 -->
<style>
.dt-size-input { width:60px; font-size:12px; text-align:center; padding:2px 4px; }
</style>
<div class="card mb-4">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-phone me-1"></i> 실시간 미리보기</span>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <select class="form-select form-select-sm" id="dtDeviceSelect" onchange="dtApplyDevice(this.value)" style="width:auto;font-size:12px;">
                    <optgroup label="Phone">
                        <option value="412,915" selected>Galaxy S26</option>
                        <option value="412,892">Galaxy S25</option>
                        <option value="393,852">iPhone 16 Pro</option>
                        <option value="430,932">iPhone 16 Pro Max</option>
                        <option value="375,812">iPhone XS</option>
                        <option value="360,800">Galaxy A13</option>
                        <option value="375,667">iPhone SE</option>
                    </optgroup>
                    <optgroup label="PC">
                        <option value="1920,1280">FHD (1920×1280)</option>
                        <option value="1440,900">WXGA+ (1440×900)</option>
                        <option value="1280,800">WXGA (1280×800)</option>
                    </optgroup>
                </select>
                <div class="d-flex align-items-center gap-1">
                    <input type="number" class="form-control form-control-sm dt-size-input" id="dtW" value="412" onchange="dtApplySize()">
                    <span class="text-muted">×</span>
                    <input type="number" class="form-control form-control-sm dt-size-input" id="dtH" value="915" onchange="dtApplySize()">
                </div>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary active" onclick="setPreview('index.php',this)">홈</button>
                    <button class="btn btn-outline-secondary" onclick="setPreview('hospitals.php',this)">병원</button>
                    <button class="btn btn-outline-secondary" onclick="setPreview('community.php',this)">커뮤니티</button>
                    <button class="btn btn-outline-secondary" onclick="setPreview('mypage.php',this)">마이</button>
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="window.open(dtBase+dtPage,'_blank')" title="새 탭"><i class="bi bi-box-arrow-up-right"></i></button>
            </div>
        </div>
    </div>
    <div class="card-body p-0 text-center">
        <iframe id="previewFrame" src="<?php echo SITE_FULL_URL; ?>index.php" style="width:412px;height:915px;border:none;border-left:1px solid #eee;border-right:1px solid #eee;"></iframe>
    </div>
</div>
<script>
var dtBase = '<?php echo SITE_FULL_URL; ?>';
var dtPage = 'index.php';
function setPreview(page, btn) {
    dtPage = page;
    document.getElementById('previewFrame').src = dtBase + page + '?_t=' + Date.now();
    if (btn) { document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active')); btn.classList.add('active'); }
}
function dtApplyDevice(val) {
    var p = val.split(',');
    document.getElementById('dtW').value = p[0];
    document.getElementById('dtH').value = p[1];
    dtApplySize();
}
function dtApplySize() {
    var f = document.getElementById('previewFrame');
    f.style.width = (parseInt(document.getElementById('dtW').value)||412) + 'px';
    f.style.height = (parseInt(document.getElementById('dtH').value)||915) + 'px';
}
</script>

<form method="post" id="tokenForm">
    <input type="hidden" name="action" value="save_tokens">

    <?php foreach ($groups as $gkey => $group): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#group_<?php echo $gkey; ?>" style="cursor:pointer;">
            <span><?php echo $group['label']; ?> <span class="badge bg-secondary"><?php echo count($group['keys']); ?></span></span>
            <i class="bi bi-chevron-down"></i>
        </div>
        <div class="collapse show" id="group_<?php echo $gkey; ?>">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th style="width:200px;">키</th>
                                <th style="width:200px;">설명</th>
                                <th>값</th>
                                <th style="width:60px;">미리보기</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($group['keys'] as $key):
                            $t = $token_map[$key] ?? null;
                            if (!$t) continue;
                            $val = htmlspecialchars($t['setting_value']);
                            $type = $t['setting_type'];
                            $desc = htmlspecialchars($t['description']);
                        ?>
                            <tr>
                                <td><code class="small"><?php echo $key; ?></code></td>
                                <td class="text-muted small"><?php echo $desc; ?></td>
                                <td>
                                    <?php if ($type === 'color'): ?>
                                    <div class="input-group input-group-sm">
                                        <input type="color" class="form-control form-control-color" value="<?php echo $val; ?>"
                                               onchange="this.nextElementSibling.value=this.value" style="width:40px;padding:2px;">
                                        <input type="text" name="tokens[<?php echo $key; ?>]" class="form-control" value="<?php echo $val; ?>"
                                               onchange="this.previousElementSibling.value=this.value" pattern="#[0-9A-Fa-f]{6}">
                                    </div>
                                    <?php elseif ($type === 'toggle'): ?>
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="tokens[<?php echo $key; ?>]" value="0">
                                        <input class="form-check-input" type="checkbox" name="tokens[<?php echo $key; ?>]" value="1"
                                               <?php echo $t['setting_value'] === '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label small"><?php echo $t['setting_value'] === '1' ? 'ON' : 'OFF'; ?></label>
                                    </div>
                                    <?php elseif ($type === 'json'): ?>
                                    <textarea name="tokens[<?php echo $key; ?>]" class="form-control form-control-sm font-monospace" rows="4"
                                              style="font-size:11px;"><?php echo $val; ?></textarea>
                                    <?php elseif ($type === 'number'): ?>
                                    <div class="input-group input-group-sm" style="max-width:120px;">
                                        <input type="number" name="tokens[<?php echo $key; ?>]" class="form-control" value="<?php echo $val; ?>" min="0" max="100">
                                        <span class="input-group-text">px</span>
                                    </div>
                                    <?php else: ?>
                                    <input type="text" name="tokens[<?php echo $key; ?>]" class="form-control form-control-sm" value="<?php echo $val; ?>">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($type === 'color'): ?>
                                    <div style="width:28px;height:28px;border-radius:6px;background:<?php echo $val; ?>;border:1px solid #ddd;"></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>저장 및 적용
        </button>
        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('previewFrame').src='<?php echo SITE_FULL_URL; ?>index.php?_t='+Date.now()">
            <i class="bi bi-arrow-clockwise me-1"></i>미리보기 새로고침
        </button>
    </div>
</form>

<script>
// 토글 라벨 업데이트
document.querySelectorAll('.form-check-input').forEach(cb => {
    cb.addEventListener('change', function() {
        this.closest('.form-check').querySelector('.form-check-label').textContent = this.checked ? 'ON' : 'OFF';
    });
});
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
