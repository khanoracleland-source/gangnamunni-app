<?php
/**
 * 디자인 모드 설정 (admin_design_mode.php)
 * 피그마 원본 / 확장 모드 / 커스텀 모드 전환
 * @created 2026-03-25
 */
session_start();
$admin_page_title = '디자인 모드 설정';
$admin_current = 'design_mode';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
if (!defined('SITE_FULL_URL')) define('SITE_FULL_URL', 'https://hms.seoulbeauty.app/hairwang/app/');

if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
require_admin_login();
require_permission('design_mode');

require_once __DIR__ . '/includes/settings_helper.php';

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$msg = '';
$msg_type = 'success';

// ===== 개별 설정 정의 =====
$features = [
    'dm_index_treatment_grid' => [
        'label' => '시술 카테고리 그리드',
        'page' => 'index.php (홈)',
        'desc' => '홈 페이지에 시술 카테고리 6개 카드 그리드 표시',
        'icon' => 'bi-grid-3x3-gap',
    ],
    'dm_hospitals_treatment_filter' => [
        'label' => '시술 필터 탭',
        'page' => 'hospitals.php (병원 목록)',
        'desc' => '병원 목록 상단에 시술 카테고리 가로 스크롤 필터 탭 표시',
        'icon' => 'bi-funnel',
    ],
    'dm_detail_treatment_chips' => [
        'label' => '시술 해시태그 칩',
        'page' => 'detail.php (병원 상세)',
        'desc' => '병원 상세 페이지에 해당 병원 시술 해시태그 칩 목록 표시',
        'icon' => 'bi-tags',
    ],
    'dm_detail_before_after' => [
        'label' => '비포/애프터 갤러리',
        'page' => 'detail.php (병원 상세)',
        'desc' => '병원 상세 페이지에 비포/애프터 사진 가로 스크롤 갤러리 표시',
        'icon' => 'bi-images',
    ],
    'dm_review_treatment_select' => [
        'label' => '시술 선택 UI',
        'page' => 'review_write.php (후기 작성)',
        'desc' => '후기 작성 시 받은 시술 다중 선택 체크박스 UI 표시',
        'icon' => 'bi-ui-checks',
    ],
    'dm_review_treatment_scorer' => [
        'label' => '시술 감성 점수 분석',
        'page' => 'review_write.php (후기 작성)',
        'desc' => '후기 등록 시 시술별 감성 점수 자동 분석 실행',
        'icon' => 'bi-graph-up-arrow',
    ],
    'dm_show_treatment_ranking' => [
        'label' => '시술 랭킹 페이지 링크',
        'page' => '네비게이션',
        'desc' => '글로벌 네비/하단 탭에 시술 랭킹 페이지 링크 표시',
        'icon' => 'bi-trophy',
    ],
    'dm_show_treatment_nav' => [
        'label' => '시술 탐색 메뉴',
        'page' => '네비게이션',
        'desc' => '네비게이션에 시술 탐색(카테고리 브라우징) 메뉴 표시',
        'icon' => 'bi-search-heart',
    ],
];

// ===== POST 저장 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $msg = 'CSRF 토큰 오류. 페이지를 새로고침하세요.';
        $msg_type = 'danger';
    } else {
        $new_mode = $_POST['dm_mode'] ?? 'figma';
        if (!in_array($new_mode, ['figma', 'extended', 'custom'], true)) {
            $new_mode = 'figma';
        }
        updateSetting($connect, 'dm_mode', $new_mode);

        // 개별 설정 저장 (custom 모드용)
        foreach ($features as $key => $info) {
            $val = isset($_POST[$key]) ? '1' : '0';
            updateSetting($connect, $key, $val);
        }

        $mode_labels = ['figma' => '피그마 원본', 'extended' => '확장 모드', 'custom' => '커스텀'];
        $msg = "디자인 모드가 <strong>{$mode_labels[$new_mode]}</strong>으로 변경되었습니다.";
    }
}

// GET ?msg=
if (!empty($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// ===== 현재 설정 로드 =====
$current_mode = getSettingValue($connect, 'dm_mode', 'figma');
$feature_values = [];
foreach ($features as $key => $info) {
    $feature_values[$key] = getSettingValue($connect, $key, '0');
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.dm-preview-frame { border:2px solid var(--bs-border-color); border-radius:8px; background:var(--bs-body-bg); transition:width 0.3s; }
.dm-size-input { width:60px; font-size:12px; text-align:center; padding:2px 4px; }
.dm-preview-wrap { position:sticky; top:70px; }
</style>
<div class="container-fluid py-4">
<div class="row g-3">
<div class="col-lg-7">
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show">
        <i class="bi bi-<?php echo $msg_type === 'danger' ? 'exclamation-triangle-fill' : 'check-circle-fill'; ?> me-1"></i>
        <?php echo $msg; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-palette me-2"></i>디자인 모드 설정</h4>
            <small class="text-muted">피그마 원본 디자인과 확장 기능 간 전환을 관리합니다</small>
        </div>
        <span class="badge bg-<?php echo $current_mode === 'figma' ? 'primary' : ($current_mode === 'extended' ? 'success' : 'warning'); ?> fs-6">
            현재: <?php echo ['figma' => '피그마 원본', 'extended' => '확장 모드', 'custom' => '커스텀'][$current_mode] ?? $current_mode; ?>
        </span>
    </div>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="save">

        <!-- 모드 선택 카드 -->
        <div class="row g-3 mb-4">
            <!-- 피그마 원본 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 <?php echo $current_mode === 'figma' ? 'border-primary border-2' : ''; ?>" id="card-figma">
                    <div class="card-body text-center py-4">
                        <div class="form-check d-inline-block">
                            <input class="form-check-input" type="radio" name="dm_mode" id="mode_figma" value="figma"
                                <?php echo $current_mode === 'figma' ? 'checked' : ''; ?> onchange="onModeChange()">
                        </div>
                        <div class="mt-2">
                            <i class="bi bi-pencil-square text-primary" style="font-size:2.5rem;"></i>
                        </div>
                        <h5 class="mt-3 mb-1">피그마 원본</h5>
                        <p class="text-muted small mb-0">피그마에 설계된 원본 디자인만 표시합니다.<br>추가된 시술 관련 컴포넌트는 숨깁니다.</p>
                        <span class="badge bg-primary mt-2">기본값</span>
                    </div>
                </div>
            </div>

            <!-- 확장 모드 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 <?php echo $current_mode === 'extended' ? 'border-success border-2' : ''; ?>" id="card-extended">
                    <div class="card-body text-center py-4">
                        <div class="form-check d-inline-block">
                            <input class="form-check-input" type="radio" name="dm_mode" id="mode_extended" value="extended"
                                <?php echo $current_mode === 'extended' ? 'checked' : ''; ?> onchange="onModeChange()">
                        </div>
                        <div class="mt-2">
                            <i class="bi bi-rocket-takeoff text-success" style="font-size:2.5rem;"></i>
                        </div>
                        <h5 class="mt-3 mb-1">확장 모드</h5>
                        <p class="text-muted small mb-0">시술 관리 시스템의 모든 추가 기능을<br>전부 활성화합니다.</p>
                        <span class="badge bg-success mt-2">전체 ON</span>
                    </div>
                </div>
            </div>

            <!-- 커스텀 -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100 <?php echo $current_mode === 'custom' ? 'border-warning border-2' : ''; ?>" id="card-custom">
                    <div class="card-body text-center py-4">
                        <div class="form-check d-inline-block">
                            <input class="form-check-input" type="radio" name="dm_mode" id="mode_custom" value="custom"
                                <?php echo $current_mode === 'custom' ? 'checked' : ''; ?> onchange="onModeChange()">
                        </div>
                        <div class="mt-2">
                            <i class="bi bi-sliders text-warning" style="font-size:2.5rem;"></i>
                        </div>
                        <h5 class="mt-3 mb-1">커스텀</h5>
                        <p class="text-muted small mb-0">각 기능별로 개별 ON/OFF를<br>직접 선택합니다.</p>
                        <span class="badge bg-warning text-dark mt-2">개별 설정</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 개별 설정 (커스텀 모드용) -->
        <div class="card border-0 shadow-sm mb-4" id="customSettings" style="<?php echo $current_mode === 'custom' ? '' : 'display:none;'; ?>">
            <div class="card-header bg-transparent">
                <h6 class="mb-0"><i class="bi bi-toggles me-2"></i>개별 기능 설정</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px;" class="text-center">상태</th>
                                <th>기능</th>
                                <th>적용 페이지</th>
                                <th>설명</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($features as $key => $info): ?>
                            <tr>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center mb-0">
                                        <input class="form-check-input" type="checkbox" name="<?php echo $key; ?>"
                                            id="<?php echo $key; ?>" value="1"
                                            <?php echo $feature_values[$key] === '1' ? 'checked' : ''; ?>>
                                    </div>
                                </td>
                                <td>
                                    <i class="bi <?php echo $info['icon']; ?> me-2 text-primary"></i>
                                    <strong><?php echo htmlspecialchars($info['label']); ?></strong>
                                </td>
                                <td><code class="small"><?php echo htmlspecialchars($info['page']); ?></code></td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($info['desc']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 안내 -->
        <div class="card border-0 shadow-sm mb-4 bg-light">
            <div class="card-body">
                <h6 class="mb-2"><i class="bi bi-info-circle me-1"></i>안내</h6>
                <ul class="mb-0 small text-muted">
                    <li><strong>피그마 원본</strong>: 피그마에 설계된 디자인만 표시됩니다. 추가된 시술 관련 UI 컴포넌트는 숨겨집니다.</li>
                    <li><strong>확장 모드</strong>: DB 기반 시술 관리, 감성 분석, 랭킹 등 추가 개발된 기능이 모두 표시됩니다.</li>
                    <li><strong>커스텀</strong>: 기능별로 개별 ON/OFF를 선택할 수 있습니다.</li>
                    <li>모드를 변경해도 <strong>DB, API, 관리자 페이지는 영향받지 않습니다</strong>. 프론트엔드 표시만 제어됩니다.</li>
                </ul>
            </div>
        </div>

        <!-- 저장 버튼 -->
        <div class="text-end">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-lg me-1"></i>설정 저장
            </button>
        </div>
    </form>
</div><!-- /col-lg-7 -->

<!-- RIGHT: 라이브 프리뷰 -->
<div class="col-lg-5">
    <div class="dm-preview-wrap text-center">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <select class="form-select form-select-sm" id="dmDeviceSel" onchange="dmApplyDevice(this.value)" style="width:auto;font-size:12px;">
                <optgroup label="Phone">
                    <option value="412,915" selected>Galaxy S26</option>
                    <option value="412,892">Galaxy S25</option>
                    <option value="393,852">iPhone 16 Pro</option>
                    <option value="430,932">iPhone 16 Pro Max</option>
                    <option value="375,812">iPhone XS</option>
                    <option value="375,667">iPhone SE</option>
                </optgroup>
                <optgroup label="PC">
                    <option value="1920,1280">FHD</option>
                    <option value="1280,800">WXGA</option>
                </optgroup>
            </select>
            <div class="d-flex align-items-center gap-1">
                <input type="number" class="form-control form-control-sm dm-size-input" id="dmW" value="412" onchange="dmApplySize()">
                <span class="text-muted">×</span>
                <input type="number" class="form-control form-control-sm dm-size-input" id="dmH" value="915" onchange="dmApplySize()">
            </div>
            <div class="d-flex gap-1">
                <select class="form-select form-select-sm" id="dmPageSel" onchange="dmSetPage(this.value)" style="width:auto;font-size:12px;">
                    <option value="index.php">홈</option>
                    <option value="hospitals.php">병원</option>
                    <option value="detail.php?id=8093">상세</option>
                    <option value="review_write.php">후기작성</option>
                    <option value="treatment_ranking.php">시술랭킹</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary" onclick="dmReload()" title="새로고침"><i class="bi bi-arrow-clockwise"></i></button>
                <button class="btn btn-sm btn-outline-primary" onclick="window.open(dmBase+dmPage,'_blank')" title="새 탭"><i class="bi bi-box-arrow-up-right"></i></button>
            </div>
        </div>
        <iframe id="dmPreviewFrame" class="dm-preview-frame" style="width:412px;height:915px;" src="<?php echo SITE_FULL_URL; ?>index.php"></iframe>
    </div>
</div>

</div><!-- /row -->
</div><!-- /container -->

<script>
function onModeChange() {
    var mode = document.querySelector('input[name="dm_mode"]:checked').value;
    var customEl = document.getElementById('customSettings');
    customEl.style.display = mode === 'custom' ? '' : 'none';

    // 카드 보더 업데이트
    ['figma','extended','custom'].forEach(function(m) {
        var card = document.getElementById('card-' + m);
        card.classList.remove('border-primary','border-success','border-warning','border-2');
    });
    var colors = {figma:'primary', extended:'success', custom:'warning'};
    var sel = document.getElementById('card-' + mode);
    sel.classList.add('border-' + colors[mode], 'border-2');
}
// 카드 클릭 시 라디오 선택
document.querySelectorAll('[id^="card-"]').forEach(function(card) {
    card.style.cursor = 'pointer';
    card.addEventListener('click', function(e) {
        if (e.target.tagName === 'INPUT') return;
        var mode = this.id.replace('card-', '');
        document.getElementById('mode_' + mode).checked = true;
        onModeChange();
    });
});

// 프리뷰
var dmBase = '<?php echo SITE_FULL_URL; ?>';
var dmPage = 'index.php';
function dmReload() { document.getElementById('dmPreviewFrame').src = dmBase + dmPage + '?_t=' + Date.now(); }
function dmSetPage(p) { dmPage = p; dmReload(); }
function dmApplyDevice(v) { var s=v.split(','); document.getElementById('dmW').value=s[0]; document.getElementById('dmH').value=s[1]; dmApplySize(); }
function dmApplySize() { var f=document.getElementById('dmPreviewFrame'); f.style.width=(parseInt(document.getElementById('dmW').value)||412)+'px'; f.style.height=(parseInt(document.getElementById('dmH').value)||915)+'px'; }
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
